<?php
// Simple script to fix admin password
require_once 'system/Config.php';

try {
    $config = new Config();
    $db = new PDO(
        "pgsql:host=postgres;port=5432;dbname=divino_lanches",
        'postgres',
        'divino_password'
    );
    
    // Generate correct hash for admin123
    $correctHash = password_hash('admin123', PASSWORD_DEFAULT);
    
    echo "Generated hash for admin123: $correctHash\n";
    
    // Update admin password
    $stmt = $db->prepare("UPDATE usuarios SET senha = ? WHERE login = 'admin'");
    $result = $stmt->execute([$correctHash]);
    
    if ($result) {
        echo "✅ Admin password updated successfully!\n";
        
        // Verify the update
        $stmt = $db->prepare("SELECT login, senha FROM usuarios WHERE login = 'admin'");
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "User in database: " . $user['login'] . "\n";
        echo "Stored hash: " . $user['senha'] . "\n";
        
        // Test password verification
        if (password_verify('admin123', $user['senha'])) {
            echo "✅ Password verification successful!\n";
        } else {
            echo "❌ Password verification failed!\n";
        }
    } else {
        echo "❌ Failed to update password!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
