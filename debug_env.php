<?php
/**
 * Debug Environment Variables
 * This script shows all environment variables for debugging
 */

echo "=== ENVIRONMENT VARIABLES DEBUG ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n\n";

echo "=== DATABASE VARIABLES ===\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'NOT SET') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: 'NOT SET') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: 'NOT SET') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: 'NOT SET') . "\n";
echo "DB_PASSWORD: " . (getenv('DB_PASSWORD') ?: 'NOT SET') . "\n\n";

echo "=== ALL ENVIRONMENT VARIABLES ===\n";
$envVars = getenv();
foreach ($envVars as $key => $value) {
    if (strpos($key, 'DB_') === 0 || strpos($key, 'APP_') === 0) {
        echo "$key: $value\n";
    }
}

echo "\n=== CONFIG TEST ===\n";
try {
    require_once 'system/Config.php';
    $config = System\Config::getInstance();
    $dbConfig = $config->getDatabaseConfig();
    echo "Database Config from Config class:\n";
    print_r($dbConfig);
} catch (Exception $e) {
    echo "Error loading config: " . $e->getMessage() . "\n";
}
?>
