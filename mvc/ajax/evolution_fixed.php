<?php

// Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Conectar ao banco diretamente
try {
    $pdo = new PDO('pgsql:host=divino-lanches-db;port=5432;dbname=divino_db', 'divino_user', 'divino_password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de conexão: ' . $e->getMessage()]);
    exit;
}

// Carregar configurações
$config = require_once __DIR__ . '/../../config/evolution.php';

// Função para buscar instâncias da Evolution API
function buscarInstanciasEvolution() {
    global $config;
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['base_url'] . '/instance/fetchInstances');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $config['api_key']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['data'] ?? [];
        } else {
            return [];
        }
    } catch (Exception $e) {
        return [];
    }
}

// Função para conectar instância existente
function conectarInstanciaExistente($nomeInstancia) {
    global $config;
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['base_url'] . '/instance/connect/' . $nomeInstancia);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $config['api_key']
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        } else {
            return ['success' => false, 'message' => 'Erro ao conectar instância'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
}

// Obter ação
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'listar_instancias':
        try {
            // Buscar instâncias da Evolution API
            $evolutionInstancias = buscarInstanciasEvolution();
            
            // Buscar instâncias do banco local
            $stmt = $pdo->query("SELECT * FROM evolution_instancias ORDER BY created_at DESC");
            $instanciasLocais = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'instancias' => $evolutionInstancias,
                'instancias_locais' => $instanciasLocais
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao listar instâncias: ' . $e->getMessage()]);
        }
        break;
        
    case 'criar_instancia':
        try {
            $nome = $_POST['nome_instancia'] ?? '';
            $telefone = $_POST['numero_telefone'] ?? '';
            
            if (empty($nome) || empty($telefone)) {
                echo json_encode(['success' => false, 'message' => 'Nome e telefone são obrigatórios']);
                break;
            }
            
            // Verificar se já existe no banco local
            $stmt = $pdo->prepare("SELECT id FROM evolution_instancias WHERE nome_instancia = ?");
            $stmt->execute([$nome]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Instância já existe no sistema local']);
                break;
            }
            
            // Verificar se existe na Evolution API
            $evolutionInstancias = buscarInstanciasEvolution();
            $existeNaEvolution = false;
            foreach ($evolutionInstancias as $instancia) {
                if ($instancia['instanceName'] === $nome) {
                    $existeNaEvolution = true;
                    break;
                }
            }
            
            if ($existeNaEvolution) {
                // Conectar instância existente
                $resultado = conectarInstanciaExistente($nome);
                if ($resultado['success']) {
                    // Salvar no banco local
                    $stmt = $pdo->prepare("INSERT INTO evolution_instancias (nome_instancia, numero_telefone, status, tenant_id, filial_id) VALUES (?, ?, 'connecting', 1, 1)");
                    $stmt->execute([$nome, $telefone]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Instância existente conectada com sucesso'
                    ]);
                } else {
                    echo json_encode($resultado);
                }
            } else {
                // Criar nova instância
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $config['base_url'] . '/instance/create');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'instanceName' => $nome,
                    'qrcode' => true,
                    'integration' => 'WHATSAPP-BAILEYS'
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'apikey: ' . $config['api_key']
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 201) {
                    // Salvar no banco local
                    $stmt = $pdo->prepare("INSERT INTO evolution_instancias (nome_instancia, numero_telefone, status, tenant_id, filial_id) VALUES (?, ?, 'created', 1, 1)");
                    $stmt->execute([$nome, $telefone]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Instância criada com sucesso'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro ao criar instância na Evolution API']);
                }
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao criar instância: ' . $e->getMessage()]);
        }
        break;
        
    case 'obter_qrcode':
        try {
            $nome = $_GET['nome_instancia'] ?? '';
            
            if (empty($nome)) {
                echo json_encode(['success' => false, 'message' => 'Nome da instância é obrigatório']);
                break;
            }
            
            // Buscar QR Code da Evolution API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $config['base_url'] . '/instance/connect/' . $nome);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $config['api_key']
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['data']['qrcode']['base64'])) {
                    echo json_encode([
                        'success' => true,
                        'qr_code' => $data['data']['qrcode']['base64'],
                        'message' => 'QR Code obtido com sucesso'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'QR Code não disponível']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao obter QR Code']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao obter QR Code: ' . $e->getMessage()]);
        }
        break;
        
    case 'deletar_instancia':
        try {
            $nome = $_POST['nome_instancia'] ?? '';
            
            if (empty($nome)) {
                echo json_encode(['success' => false, 'message' => 'Nome da instância é obrigatório']);
                break;
            }
            
            // Deletar do banco local
            $stmt = $pdo->prepare("DELETE FROM evolution_instancias WHERE nome_instancia = ?");
            $stmt->execute([$nome]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Instância removida do sistema local'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar instância: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação não encontrada']);
        break;
}
