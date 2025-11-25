<?php

namespace System\Storage;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Exception;

class MinIO
{
    private static $instance = null;
    private $s3Client;
    private $bucket;
    private $publicUrl;

    private function __construct()
    {
        $endpoint = $_ENV['MINIO_ENDPOINT'] ?? getenv('MINIO_ENDPOINT') ?? '';
        $accessKey = $_ENV['MINIO_ACCESS_KEY'] ?? getenv('MINIO_ACCESS_KEY') ?? '';
        $secretKey = $_ENV['MINIO_SECRET_KEY'] ?? getenv('MINIO_SECRET_KEY') ?? '';
        $this->bucket = $_ENV['MINIO_BUCKET'] ?? getenv('MINIO_BUCKET') ?? 'divinosys';
        // Se MINIO_PUBLIC_URL não estiver definido ou for o console, usar o endpoint
        $publicUrl = $_ENV['MINIO_PUBLIC_URL'] ?? getenv('MINIO_PUBLIC_URL') ?? '';
        // Se a URL pública for o console (winio.conext.click), usar o endpoint (ws3.conext.click)
        if (empty($publicUrl) || strpos($publicUrl, 'winio.conext.click') !== false) {
            $this->publicUrl = $endpoint; // Usar o endpoint diretamente
        } else {
            $this->publicUrl = $publicUrl;
        }
        
        if (empty($endpoint) || empty($accessKey) || empty($secretKey)) {
            throw new Exception('MinIO credentials not configured. Please set MINIO_ENDPOINT, MINIO_ACCESS_KEY, and MINIO_SECRET_KEY in your environment variables.');
        }

        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1', // MinIO doesn't use regions, but AWS SDK requires it
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey,
            ],
            'http' => [
                'verify' => false, // Desabilitar verificação SSL para desenvolvimento
            ],
        ]);

        // Garantir que o bucket existe
        $this->ensureBucketExists();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function ensureBucketExists()
    {
        try {
            if (!$this->s3Client->doesBucketExist($this->bucket)) {
                $this->s3Client->createBucket([
                    'Bucket' => $this->bucket,
                ]);
            }
        } catch (AwsException $e) {
            // Se o bucket já existe ou há outro erro, continuar
            error_log('MinIO bucket check: ' . $e->getMessage());
        }
    }

    /**
     * Upload de arquivo para MinIO
     * 
     * @param string $filePath Caminho local do arquivo ou conteúdo do arquivo
     * @param string $objectKey Chave do objeto no MinIO (caminho no bucket)
     * @param string $contentType Tipo MIME do arquivo
     * @param bool $isContent Se true, $filePath contém o conteúdo do arquivo diretamente
     * @return string URL pública do arquivo
     * @throws Exception
     */
    public function upload($filePath, $objectKey, $contentType = null, $isContent = false)
    {
        try {
            $params = [
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
            ];

            if ($isContent) {
                // Se $filePath contém o conteúdo diretamente
                $params['Body'] = $filePath;
            } else {
                // Se $filePath é um caminho de arquivo
                if (!file_exists($filePath)) {
                    throw new Exception("File not found: {$filePath}");
                }
                $params['Body'] = fopen($filePath, 'rb');
            }

            if ($contentType) {
                $params['ContentType'] = $contentType;
            }

            // Tentar tornar o arquivo público (pode não funcionar se o bucket não tiver política)
            try {
                $params['ACL'] = 'public-read';
            } catch (\Exception $e) {
                // ACL pode não estar disponível, continuar sem ele
            }

            $result = $this->s3Client->putObject($params);
            
            // Se o ACL não funcionou, tentar definir política após o upload
            try {
                $this->s3Client->putObjectAcl([
                    'Bucket' => $this->bucket,
                    'Key' => $objectKey,
                    'ACL' => 'public-read'
                ]);
            } catch (\Exception $e) {
                // Ignorar erro de ACL - o arquivo pode já ser público por política do bucket
                error_log('MinIO ACL warning: ' . $e->getMessage());
            }

            // Se não fechou o resource, fechar
            if (!$isContent && is_resource($params['Body'])) {
                fclose($params['Body']);
            }

            // Retornar URL pública
            if ($this->publicUrl) {
                return rtrim($this->publicUrl, '/') . '/' . $this->bucket . '/' . $objectKey;
            }

            // Fallback: construir URL do endpoint
            return rtrim($this->s3Client->getEndpoint()->getBaseUrl(), '/') . '/' . $this->bucket . '/' . $objectKey;
        } catch (AwsException $e) {
            error_log('MinIO upload error: ' . $e->getMessage());
            throw new Exception('Failed to upload file to MinIO: ' . $e->getMessage());
        }
    }

    /**
     * Upload de arquivo temporário (de $_FILES)
     * 
     * @param array $fileArray Array do $_FILES
     * @param string $prefix Prefixo para o caminho no bucket (ex: 'produtos', 'financeiro')
     * @return string URL pública do arquivo
     * @throws Exception
     */
    public function uploadFile($fileArray, $prefix = 'uploads')
    {
        if (!isset($fileArray['tmp_name']) || !isset($fileArray['error'])) {
            throw new Exception('Invalid file array');
        }

        if ($fileArray['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $fileArray['error']);
        }

        $extension = pathinfo($fileArray['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        $objectKey = $prefix . '/' . $fileName;

        $contentType = $fileArray['type'] ?? mime_content_type($fileArray['tmp_name']);

        return $this->upload($fileArray['tmp_name'], $objectKey, $contentType, false);
    }

    /**
     * Upload de conteúdo de arquivo diretamente
     * 
     * @param string $content Conteúdo do arquivo
     * @param string $fileName Nome do arquivo
     * @param string $prefix Prefixo para o caminho no bucket
     * @param string $contentType Tipo MIME
     * @return string URL pública do arquivo
     * @throws Exception
     */
    public function uploadContent($content, $fileName, $prefix = 'uploads', $contentType = null)
    {
        $objectKey = $prefix . '/' . $fileName;
        
        if (!$contentType) {
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $contentType = $this->guessContentType($extension);
        }

        return $this->upload($content, $objectKey, $contentType, true);
    }

    /**
     * Deletar arquivo do MinIO
     * 
     * @param string $objectKey Chave do objeto no MinIO
     * @return bool
     * @throws Exception
     */
    public function delete($objectKey)
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('MinIO delete error: ' . $e->getMessage());
            throw new Exception('Failed to delete file from MinIO: ' . $e->getMessage());
        }
    }

    /**
     * Deletar arquivo pela URL pública
     * 
     * @param string $url URL pública do arquivo
     * @return bool
     */
    public function deleteByUrl($url)
    {
        // Extrair object key da URL
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        
        // Remover o bucket do caminho
        $parts = explode('/', trim($path, '/'));
        if (count($parts) > 1 && $parts[0] === $this->bucket) {
            array_shift($parts); // Remover o bucket
            $objectKey = implode('/', $parts);
            return $this->delete($objectKey);
        }
        
        // Se não conseguir extrair, tentar usar o caminho completo
        $objectKey = ltrim($path, '/');
        return $this->delete($objectKey);
    }

    /**
     * Obter URL pública do arquivo
     * 
     * @param string $objectKey Chave do objeto no MinIO
     * @return string URL pública
     */
    public function getUrl($objectKey)
    {
        if ($this->publicUrl) {
            return rtrim($this->publicUrl, '/') . '/' . $this->bucket . '/' . $objectKey;
        }

        return rtrim($this->s3Client->getEndpoint()->getBaseUrl(), '/') . '/' . $this->bucket . '/' . $objectKey;
    }

    /**
     * Verificar se arquivo existe
     * 
     * @param string $objectKey Chave do objeto no MinIO
     * @return bool
     */
    public function exists($objectKey)
    {
        try {
            return $this->s3Client->doesObjectExist($this->bucket, $objectKey);
        } catch (AwsException $e) {
            error_log('MinIO exists check error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obter conteúdo do arquivo
     * 
     * @param string $objectKey Chave do objeto no MinIO
     * @return string Conteúdo do arquivo
     * @throws Exception
     */
    public function getContent($objectKey)
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key' => $objectKey,
            ]);
            return (string) $result['Body'];
        } catch (AwsException $e) {
            error_log('MinIO get content error: ' . $e->getMessage());
            throw new Exception('Failed to get file content from MinIO: ' . $e->getMessage());
        }
    }

    /**
     * Extrair object key de uma URL do MinIO
     * 
     * @param string $url URL pública do arquivo
     * @return string Object key
     */
    public function extractObjectKey($url)
    {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        $parts = explode('/', trim($path, '/'));
        
        if (count($parts) > 1 && $parts[0] === $this->bucket) {
            array_shift($parts);
            return implode('/', $parts);
        }
        
        return ltrim($path, '/');
    }

    /**
     * Adivinhar content type pela extensão
     * 
     * @param string $extension Extensão do arquivo
     * @return string Content type
     */
    private function guessContentType($extension)
    {
        $types = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return $types[strtolower($extension)] ?? 'application/octet-stream';
    }
}

