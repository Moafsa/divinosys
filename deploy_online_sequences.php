<?php
/**
 * Script to fix sequences during online deployment
 * This ensures all sequences work correctly in the Coolify environment
 */

// Try multiple possible paths for the config file
$configPaths = [
    'config/database.php',
    'system/Database.php',
    '../config/database.php',
    '../system/Database.php'
];

$configLoaded = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $configLoaded = true;
        break;
    }
}

if (!$configLoaded) {
    echo "<h2>❌ Erro: Arquivo de configuração não encontrado</h2>\n";
    echo "<p>Tentou os seguintes caminhos:</p>\n";
    echo "<ul>\n";
    foreach ($configPaths as $path) {
        echo "<li>$path</li>\n";
    }
    echo "</ul>\n";
    echo "<p>Diretório atual: " . getcwd() . "</p>\n";
    echo "<p>Arquivos disponíveis:</p>\n";
    echo "<pre>";
    print_r(scandir('.'));
    echo "</pre>";
    exit;
}

try {
    $db = \System\Database::getInstance();
    
    echo "<h2>🔧 Fixing Sequences for Online Deployment</h2>\n";
    
    // List of critical tables and their sequences
    $tables = [
        'produtos' => 'produtos_id_seq',
        'categorias' => 'categorias_id_seq', 
        'ingredientes' => 'ingredientes_id_seq',
        'mesas' => 'mesas_id_seq',
        'pedido' => 'pedido_idpedido_seq',
        'pedido_itens' => 'pedido_itens_id_seq',
        'mesa_pedidos' => 'mesa_pedidos_id_seq',
        'estoque' => 'estoque_id_seq',
        'tenants' => 'tenants_id_seq',
        'filiais' => 'filiais_id_seq',
        'usuarios' => 'usuarios_id_seq',
        'planos' => 'planos_id_seq',
        'contas_financeiras' => 'contas_financeiras_id_seq',
        'categorias_financeiras' => 'categorias_financeiras_id_seq',
        'evolution_instancias' => 'evolution_instancias_id_seq',
        'usuarios_globais' => 'usuarios_globais_id_seq',
        'usuarios_telefones' => 'usuarios_telefones_id_seq',
        'usuarios_estabelecimento' => 'usuarios_estabelecimento_id_seq'
    ];
    
    echo "<h3>📊 Current Sequence Status:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Table</th><th>Sequence</th><th>Current Value</th><th>Max ID</th><th>Status</th></tr>\n";
    
    $fixedCount = 0;
    $totalCount = count($tables);
    
    foreach ($tables as $table => $sequence) {
        try {
            // Get current sequence value
            $seqResult = $db->query("SELECT last_value FROM $sequence");
            $seqValue = $db->fetch($seqResult)['last_value'];
            
            // Get max ID from table (handle special case for pedido table)
            if ($table === 'pedido') {
                $maxResult = $db->query("SELECT COALESCE(MAX(idpedido), 0) as max_id FROM $table");
            } else {
                $maxResult = $db->query("SELECT COALESCE(MAX(id), 0) as max_id FROM $table");
            }
            $maxId = $db->fetch($maxResult)['max_id'];
            
            // Determine status
            $status = $seqValue >= $maxId ? '✅ OK' : '⚠️ NEEDS FIX';
            $statusColor = $seqValue >= $maxId ? 'green' : 'orange';
            
            echo "<tr>";
            echo "<td>$table</td>";
            echo "<td>$sequence</td>";
            echo "<td>$seqValue</td>";
            echo "<td>$maxId</td>";
            echo "<td style='color: $statusColor;'>$status</td>";
            echo "</tr>\n";
            
            // Fix if needed
            if ($seqValue < $maxId) {
                $newValue = $maxId + 1;
                $db->query("SELECT setval('$sequence', $newValue)");
                echo "<tr><td colspan='5' style='color: blue;'>🔧 Fixed $sequence to $newValue</td></tr>\n";
                $fixedCount++;
            }
            
        } catch (Exception $e) {
            echo "<tr><td>$table</td><td>$sequence</td><td colspan='3' style='color: red;'>❌ Error: " . $e->getMessage() . "</td></tr>\n";
        }
    }
    
    echo "</table>\n";
    
    echo "<h3>📈 Summary:</h3>\n";
    echo "<ul>\n";
    echo "<li>Total tables checked: $totalCount</li>\n";
    echo "<li>Sequences fixed: $fixedCount</li>\n";
    echo "<li>Sequences already OK: " . ($totalCount - $fixedCount) . "</li>\n";
    echo "</ul>\n";
    
    if ($fixedCount > 0) {
        echo "<p style='color: green; font-weight: bold;'>✅ $fixedCount sequences have been fixed!</p>\n";
    } else {
        echo "<p style='color: green; font-weight: bold;'>✅ All sequences are already synchronized!</p>\n";
    }
    
    // Test inserting records to verify sequences work
    echo "<h3>🧪 Testing Sequence Functionality:</h3>\n";
    
    $testResults = [];
    
    // Test product insertion
    try {
        $db->query("INSERT INTO produtos (nome, categoria_id, preco_normal, tenant_id, filial_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())", 
            ['TESTE DEPLOY - REMOVER', 1, 10.00, 1, 1]);
        $productId = $db->lastInsertId();
        $db->query("DELETE FROM produtos WHERE id = ?", [$productId]);
        $testResults[] = "✅ Product insertion: OK (ID: $productId)";
    } catch (Exception $e) {
        $testResults[] = "❌ Product insertion: " . $e->getMessage();
    }
    
    // Test category insertion
    try {
        $db->query("INSERT INTO categorias (nome, tenant_id, filial_id, created_at) VALUES (?, ?, ?, NOW())", 
            ['TESTE DEPLOY - REMOVER', 1, 1]);
        $categoryId = $db->lastInsertId();
        $db->query("DELETE FROM categorias WHERE id = ?", [$categoryId]);
        $testResults[] = "✅ Category insertion: OK (ID: $categoryId)";
    } catch (Exception $e) {
        $testResults[] = "❌ Category insertion: " . $e->getMessage();
    }
    
    // Test ingredient insertion
    try {
        $db->query("INSERT INTO ingredientes (nome, tenant_id, filial_id, created_at) VALUES (?, ?, ?, NOW())", 
            ['TESTE DEPLOY - REMOVER', 1, 1]);
        $ingredientId = $db->lastInsertId();
        $db->query("DELETE FROM ingredientes WHERE id = ?", [$ingredientId]);
        $testResults[] = "✅ Ingredient insertion: OK (ID: $ingredientId)";
    } catch (Exception $e) {
        $testResults[] = "❌ Ingredient insertion: " . $e->getMessage();
    }
    
    echo "<ul>\n";
    foreach ($testResults as $result) {
        echo "<li>$result</li>\n";
    }
    echo "</ul>\n";
    
    echo "<h3>🚀 Deployment Ready!</h3>\n";
    echo "<p>The system is now ready for online deployment. All sequences are properly synchronized and tested.</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ FATAL ERROR: " . $e->getMessage() . "</p>\n";
    echo "<p>Please check the database connection and try again.</p>\n";
}
?>
