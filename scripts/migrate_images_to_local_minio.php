<?php
/**
 * Migra imagens/arquivos referenciados no banco para o MinIO local do container.
 * Uso: php scripts/migrate_images_to_local_minio.php [--dry-run]
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../system/Config.php';
require_once __DIR__ . '/../system/Database.php';
require_once __DIR__ . '/../system/Storage/MinIO.php';

use System\Database;
use System\Storage\MinIO;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$oldHosts = ['ws3.conext.click', 'winio.conext.click'];

$db = Database::getInstance();

$minio = MinIO::getInstance();
$newPublicBase = rtrim($_ENV['MINIO_PUBLIC_URL'] ?? getenv('MINIO_PUBLIC_URL') ?? '', '/');
$bucket = $_ENV['MINIO_BUCKET'] ?? getenv('MINIO_BUCKET') ?? 'divinosys';

function migrateUrl(string $url, MinIO $minio, string $newPublicBase, string $bucket, bool $dryRun): ?string
{
    global $oldHosts;

    if ($url === '' || str_starts_with($url, $newPublicBase)) {
        return null;
    }

    $parsed = parse_url($url);
    $host = $parsed['host'] ?? '';
    $path = ltrim($parsed['path'] ?? '', '/');

    $isLocalUpload = str_starts_with($url, 'uploads/') || str_contains($url, '/uploads/');
    $isOldMinio = in_array($host, $oldHosts, true) || str_contains($path, $bucket . '/');

    if (!$isLocalUpload && !$isOldMinio && !preg_match('#^https?://#', $url)) {
        $localPath = __DIR__ . '/../' . ltrim($url, '/');
        if (!file_exists($localPath)) {
            return null;
        }
        $isLocalUpload = true;
    }

    $objectKey = null;
    if ($isOldMinio) {
        $parts = explode('/', $path);
        if (($parts[0] ?? '') === $bucket) {
            array_shift($parts);
        }
        $objectKey = implode('/', $parts);
    } elseif ($isLocalUpload) {
        $objectKey = preg_replace('#^uploads/#', '', $path);
        if ($objectKey === $path) {
            $objectKey = basename($path);
        }
        $prefix = 'migrated';
        if (str_contains($path, 'pix')) {
            $prefix = 'pix';
        }
        $objectKey = $prefix . '/' . basename($objectKey);
    }

    if (!$objectKey) {
        return null;
    }

    $newUrl = $newPublicBase . '/' . $bucket . '/' . $objectKey;

    if ($dryRun) {
        echo "[dry-run] {$url} -> {$newUrl}\n";
        return $newUrl;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'minio_migrate_');
    try {
        if ($isLocalUpload && !preg_match('#^https?://#', $url)) {
            $source = str_starts_with($url, '/') ? $url : (__DIR__ . '/../' . ltrim($url, '/'));
            if (!copy($source, $tmp)) {
                throw new RuntimeException("Cannot read local file: {$source}");
            }
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 30], 'ssl' => ['verify_peer' => false]]);
            $content = @file_get_contents($url, false, $ctx);
            if ($content === false) {
                throw new RuntimeException("Cannot download: {$url}");
            }
            file_put_contents($tmp, $content);
        }

        $ext = pathinfo($objectKey, PATHINFO_EXTENSION);
        $contentType = match (strtolower($ext)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        $minio->upload($tmp, $objectKey, $contentType, false);
        echo "Migrated: {$url} -> {$newUrl}\n";
        return $newUrl;
    } finally {
        @unlink($tmp);
    }
}

$queries = [
    ['produtos', 'imagem', 'tenant_id IS NOT NULL'],
    ['categorias', 'imagem', '1=1'],
    ['filiais', 'logo_url', 'logo_url IS NOT NULL'],
    ['tenants', 'logo_url', 'logo_url IS NOT NULL'],
    ['configuracao_pix', 'qr_code', 'qr_code IS NOT NULL'],
    ['anexos_financeiros', 'caminho_arquivo', 'caminho_arquivo IS NOT NULL'],
];

$updated = 0;
$skipped = 0;

foreach ($queries as [$table, $column, $where]) {
    try {
        $rows = $db->fetchAll("SELECT id, {$column} AS url FROM {$table} WHERE {$column} IS NOT NULL AND {$column} != '' AND ({$where})");
    } catch (Throwable $e) {
        echo "Skip {$table}.{$column}: {$e->getMessage()}\n";
        continue;
    }

    foreach ($rows as $row) {
        $oldUrl = $row['url'];
        try {
            $newUrl = migrateUrl($oldUrl, $minio, $newPublicBase, $bucket, $dryRun);
            if (!$newUrl || $newUrl === $oldUrl) {
                $skipped++;
                continue;
            }
            if (!$dryRun) {
                $db->query("UPDATE {$table} SET {$column} = ? WHERE id = ?", [$newUrl, $row['id']]);
            }
            $updated++;
        } catch (Throwable $e) {
            echo "Error migrating {$table}#{$row['id']}: {$e->getMessage()}\n";
        }
    }
}

echo "Done. Updated: {$updated}, Skipped: {$skipped}" . ($dryRun ? ' (dry-run)' : '') . "\n";
