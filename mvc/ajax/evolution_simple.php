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

// Obter ação
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'listar_instancias':
        try {
            $stmt = $pdo->query("SELECT * FROM evolution_instancias ORDER BY created_at DESC");
            $instancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'instancias' => $instancias
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
            
            // Verificar se já existe
            $stmt = $pdo->prepare("SELECT id FROM evolution_instancias WHERE nome_instancia = ?");
            $stmt->execute([$nome]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Instância já existe']);
                break;
            }
            
            // Criar instância
            $stmt = $pdo->prepare("INSERT INTO evolution_instancias (nome_instancia, numero_telefone, status, tenant_id, filial_id) VALUES (?, ?, 'disconnected', 1, 1)");
            $stmt->execute([$nome, $telefone]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Instância criada com sucesso'
            ]);
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
            
            // Simular QR Code (em produção, chamaria a Evolution API)
            $qrCode = base64_encode('QR_CODE_SIMULADO_PARA_' . $nome);
            
            echo json_encode([
                'success' => true,
                'qr_code' => $qrCode,
                'message' => 'QR Code gerado com sucesso'
            ]);
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
            
            $stmt = $pdo->prepare("DELETE FROM evolution_instancias WHERE nome_instancia = ?");
            $stmt->execute([$nome]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Instância deletada com sucesso'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao deletar instância: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Ação não encontrada']);
        break;
}
