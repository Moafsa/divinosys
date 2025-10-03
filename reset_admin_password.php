<?php
require_once 'system/Database.php';
require_once 'system/Config.php';

echo "=== RESET ADMIN PASSWORD ===\n";

$db = \System\Database::getInstance();

try {
    // Hash da nova senha (admin123)
    $new_password = 'admin123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    echo "Updating admin password...\n";
    
    // Update the admin user password
    $result = $db->query(
        "UPDATE usuarios SET senha = ? WHERE login = 'admin' AND nivel = 1",
        [$hashed_password]
    );
    
    if ($result) {
        echo "✅ Admin password updated successfully!\n";
        echo "New credentials:\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
        echo "\nYou can now login with these credentials.\n";
    } else {
        echo "❌ Failed to update password\n";
    }
    
    // Verify the update
    $admin = $db->fetch("SELECT login, nivel FROM usuarios WHERE login = 'admin' AND nivel = 1");
    if ($admin) {
        echo "✅ Admin user verified: {$admin['login']} (level {$admin['nivel']})\n";
    } else {
        echo "❌ Admin user not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== PASSWORD RESET COMPLETED ===\n";
?>
