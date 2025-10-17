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
    
    // Buscar mesas (primeiro tentar com tenant_id = 1, depois sem filtro)
    $stmt = $pdo->prepare("
        SELECT 
            id_mesa,
            nome,
            status
        FROM mesas 
        WHERE tenant_id = 1 AND filial_id = 1 
        ORDER BY id_mesa
    ");
    $stmt->execute();
    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se não encontrou mesas com tenant_id = 1, buscar todas
    if (empty($mesas)) {
        $stmt = $pdo->prepare("
            SELECT 
                id_mesa,
                nome,
                status
            FROM mesas 
            ORDER BY id_mesa
        ");
        $stmt->execute();
        $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Verificar status real das mesas baseado nos pedidos ativos
    foreach ($mesas as &$mesa) {
        // Verificar se há pedidos ativos para esta mesa
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM pedido 
            WHERE idmesa = ? 
            AND status IN ('aberto', 'preparando', 'pronto')
            AND tenant_id = 1 
            AND filial_id = 1
        ");
        $stmt->execute([$mesa['id_mesa']]);
        $pedidosAtivos = $stmt->fetchColumn();
        
        // Atualizar status baseado nos pedidos
        if ($pedidosAtivos > 0) {
            $mesa['status'] = 'ocupada';
        } else {
            $mesa['status'] = 'livre';
        }
    }
    
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
