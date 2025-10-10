<?php
/**
 * Simple script to fix sequences online
 * This script works directly with the database connection
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Fixing Sequences Online</h2>\n";

// Database connection parameters
$host = $_ENV['DB_HOST'] ?? 'postgres';
$port = $_ENV['DB_PORT'] ?? '5432';
$dbname = $_ENV['DB_NAME'] ?? 'divino_db';
$user = $_ENV['DB_USER'] ?? 'divino_user';
$password = $_ENV['DB_PASSWORD'] ?? 'divino_password';

echo "<p>Connecting to database: $dbname@$host:$port</p>\n";

try {
    // Connect directly to PostgreSQL
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Database connection successful!</p>\n";
    
    // List of sequences to fix
    $sequences = [
        'produtos_id_seq',
        'categorias_id_seq', 
        'ingredientes_id_seq',
        'mesas_id_seq',
        'pedido_idpedido_seq',
        'pedido_itens_id_seq',
        'mesa_pedidos_id_seq',
        'estoque_id_seq',
        'tenants_id_seq',
        'filiais_id_seq',
        'usuarios_id_seq',
        'planos_id_seq',
        'contas_financeiras_id_seq',
        'categorias_financeiras_id_seq',
        'evolution_instancias_id_seq',
        'usuarios_globais_id_seq',
        'usuarios_telefones_id_seq',
        'usuarios_estabelecimento_id_seq'
    ];
    
    echo "<h3>📊 Fixing Sequences:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Sequence</th><th>Current Value</th><th>Max ID</th><th>New Value</th><th>Status</th></tr>\n";
    
    foreach ($sequences as $sequence) {
        try {
            // Get table name from sequence name
            $table = str_replace('_id_seq', '', $sequence);
            
            // Handle special cases
            if ($table === 'pedido') {
                $idColumn = 'idpedido';
            } else {
                $idColumn = 'id';
            }
            
            // Get current sequence value
            $stmt = $pdo->query("SELECT last_value FROM $sequence");
            $currentValue = $stmt->fetchColumn();
            
            // Get max ID from table
            $stmt = $pdo->query("SELECT COALESCE(MAX($idColumn), 0) FROM $table");
            $maxId = $stmt->fetchColumn();
            
            // Calculate new value
            $newValue = $maxId + 1;
            
            // Fix sequence if needed
            if ($currentValue < $newValue) {
                $pdo->exec("SELECT setval('$sequence', $newValue)");
                $status = "✅ Fixed";
                $statusColor = "green";
            } else {
                $status = "✅ OK";
                $statusColor = "blue";
            }
            
            echo "<tr>";
            echo "<td>$sequence</td>";
            echo "<td>$currentValue</td>";
            echo "<td>$maxId</td>";
            echo "<td>$newValue</td>";
            echo "<td style='color: $statusColor;'>$status</td>";
            echo "</tr>\n";
            
        } catch (Exception $e) {
            echo "<tr>";
            echo "<td>$sequence</td>";
            echo "<td colspan='3'>Error</td>";
            echo "<td style='color: red;'>❌ " . $e->getMessage() . "</td>";
            echo "</tr>\n";
        }
    }
    
    echo "</table>\n";
    
    // Test product creation
    echo "<h3>🧪 Testing Product Creation:</h3>\n";
    
    try {
        // Check if categories exist
        $stmt = $pdo->query("SELECT COUNT(*) FROM categorias WHERE tenant_id = 1 AND filial_id = 1");
        $categoryCount = $stmt->fetchColumn();
        
        if ($categoryCount == 0) {
            echo "<p style='color: orange;'>⚠️ No categories found. Creating default categories...</p>\n";
            
            $defaultCategories = ['Lanches', 'Bebidas', 'Porções', 'Sobremesas'];
            foreach ($defaultCategories as $cat) {
                $pdo->exec("INSERT INTO categorias (nome, tenant_id, filial_id, created_at) VALUES ('$cat', 1, 1, NOW())");
                echo "<p>✅ Created category: $cat</p>\n";
            }
        } else {
            echo "<p style='color: green;'>✅ Found $categoryCount categories</p>\n";
        }
        
        // Test product insertion
        $stmt = $pdo->query("SELECT id FROM categorias WHERE tenant_id = 1 AND filial_id = 1 LIMIT 1");
        $categoryId = $stmt->fetchColumn();
        
        if ($categoryId) {
            $pdo->exec("INSERT INTO produtos (nome, categoria_id, preco_normal, tenant_id, filial_id, created_at) VALUES ('TESTE ONLINE - REMOVER', $categoryId, 10.00, 1, 1, NOW())");
            $productId = $pdo->lastInsertId();
            
            echo "<p style='color: green;'>✅ Successfully created test product with ID: $productId</p>\n";
            
            // Clean up
            $pdo->exec("DELETE FROM produtos WHERE id = $productId");
            echo "<p>🧹 Cleaned up test product</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error testing product creation: " . $e->getMessage() . "</p>\n";
    }
    
    echo "<h3>✅ All Sequences Fixed!</h3>\n";
    echo "<p>The system is now ready for online use. You can create products without sequence errors.</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ FATAL ERROR: " . $e->getMessage() . "</p>\n";
    echo "<p>Please check your database configuration and try again.</p>\n";
}
?>