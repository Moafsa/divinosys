<?php
// Incluir configuração do sistema
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Conectar ao banco
    $pdo = new PDO(
        "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Buscar produtos (primeiro tentar com tenant_id = 1, depois sem filtro)
    $stmt = $pdo->prepare("
        SELECT 
            id,
            nome,
            preco,
            categoria,
            ativo
        FROM produtos 
        WHERE tenant_id = 1 AND filial_id = 1 AND ativo = 1
        ORDER BY categoria, nome
    ");
    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se não encontrou produtos com tenant_id = 1, buscar todos
    if (empty($produtos)) {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                nome,
                preco,
                categoria,
                ativo
            FROM produtos 
            WHERE ativo = 1
            ORDER BY categoria, nome
        ");
        $stmt->execute();
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'produtos' => $produtos
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
