<?php
// Health check endpoint for Coolify
header('Content-Type: application/json');

try {
    // Check if database connection works
    require_once 'system/Database.php';
    require_once 'system/Config.php';
    
    $config = \System\Config::getInstance();
    $db = \System\Database::getInstance();
    
    // Simple query to test database
    $result = $db->query("SELECT 1 as test");
    $test = $result->fetch();
    
    if ($test && $test['test'] == 1) {
        echo json_encode([
            'status' => 'healthy',
            'database' => 'connected',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Database test failed');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'unhealthy',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
