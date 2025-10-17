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
    
    // Buscar mesas (usando tenant_id = 1 e filial_id = 1 como padrão)
    $stmt = $pdo->prepare("
        SELECT 
            id_mesa,
            nome,
            CASE 
                WHEN status = 'livre' THEN 'livre'
                ELSE 'ocupada'
            END as status
        FROM mesas 
        WHERE tenant_id = 1 AND filial_id = 1 
        ORDER BY id_mesa
    ");
    $stmt->execute();
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Adicionar opção de delivery
    $mesas[] = [
        'id_mesa' => 'delivery',
        'nome' => 'Delivery',
        'status' => 'livre'
    ];
    
    echo json_encode([
        'success' => true,
        'mesas' => $mesas
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
