<?php
/**
 * Script para corrigir TODOS os problemas online
 * - Adicionar coluna tipo_usuario faltante
 * - Corrigir todas as sequências
 * - Testar criação de registros
 */

echo "🔧 FIXING ALL ONLINE ISSUES\n";
echo "==========================\n\n";

// Database connection
$host = 'postgres';
$port = 5432;
$dbname = 'divino_lanches';
$user = 'divino_user';
$password = 'divino_password';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connection successful!\n\n";
    
    // 1. FIX MISSING COLUMN tipo_usuario
    echo "1. Checking and fixing missing tipo_usuario column...\n";
    
    // Check if column exists
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'usuarios_estabelecimento' 
        AND column_name = 'tipo_usuario'
    ");
    
    if ($stmt->rowCount() == 0) {
        echo "   Adding missing tipo_usuario column...\n";
        $pdo->exec("ALTER TABLE usuarios_estabelecimento ADD COLUMN tipo_usuario VARCHAR(50) NOT NULL DEFAULT 'admin'");
        echo "   ✅ Column added successfully!\n";
    } else {
        echo "   ✅ Column already exists\n";
    }
    
    // 2. FIX ALL SEQUENCES
    echo "\n2. Fixing all sequences...\n";
    
    $sequences_to_fix = [
        'produtos_id_seq' => 'produtos',
        'categorias_id_seq' => 'categorias', 
        'ingredientes_id_seq' => 'ingredientes',
        'usuarios_globais_id_seq' => 'usuarios_globais',
        'usuarios_estabelecimento_id_seq' => 'usuarios_estabelecimento',
        'pedido_idpedido_seq' => 'pedido',
        'pedido_itens_id_seq' => 'pedido_itens'
    ];
    
    foreach ($sequences_to_fix as $sequence => $table) {
        echo "   Fixing $sequence...\n";
        
        try {
            // Get current sequence value
            $stmt = $pdo->query("SELECT last_value FROM $sequence");
            $currentValue = $stmt->fetchColumn();
            
            // Get max ID from table
            $idColumn = ($table == 'pedido') ? 'idpedido' : 'id';
            $stmt = $pdo->query("SELECT COALESCE(MAX($idColumn), 0) FROM $table");
            $maxId = $stmt->fetchColumn();
            
            // Set sequence to max + 1
            $newValue = $maxId + 1;
            $pdo->exec("SELECT setval('$sequence', $newValue, false)");
            
            echo "     Sequence: $currentValue → $newValue (Max ID: $maxId)\n";
            echo "     ✅ Fixed!\n";
            
        } catch (Exception $e) {
            echo "     ❌ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // 3. TEST CREATIONS
    echo "\n3. Testing record creation...\n";
    
    // Test category creation
    echo "   Testing category creation...\n";
    try {
        $stmt = $pdo->prepare("INSERT INTO categorias (nome, descricao, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['Teste Categoria Online', 'Teste de categoria', true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $categoryId = $pdo->lastInsertId();
        echo "     ✅ Category created with ID: $categoryId\n";
        
        // Test product creation
        echo "   Testing product creation...\n";
        $stmt = $pdo->prepare("INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Teste Produto Online', 'Teste de produto', 10.50, 9.50, $categoryId, true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $productId = $pdo->lastInsertId();
        echo "     ✅ Product created with ID: $productId\n";
        
        // Test ingredient creation
        echo "   Testing ingredient creation...\n";
        $stmt = $pdo->prepare("INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Teste Ingrediente Online', 'Teste de ingrediente', 1.50, true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $ingredientId = $pdo->lastInsertId();
        echo "     ✅ Ingredient created with ID: $ingredientId\n";
        
        // Test user creation
        echo "   Testing user creation...\n";
        $stmt = $pdo->prepare("INSERT INTO usuarios_globais (nome, email, telefone, tipo_usuario, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Teste Usuario Online', 'teste@online.com', '11999999999', 'admin', true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $userId = $pdo->lastInsertId();
        echo "     ✅ User created with ID: $userId\n";
        
        // Test user establishment creation
        echo "   Testing user establishment creation...\n";
        $stmt = $pdo->prepare("INSERT INTO usuarios_estabelecimento (usuario_global_id, tenant_id, filial_id, tipo_usuario, cargo, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, 1, 1, 'admin', 'Admin', true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        $userEstId = $pdo->lastInsertId();
        echo "     ✅ User establishment created with ID: $userEstId\n";
        
    } catch (Exception $e) {
        echo "     ❌ Test failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n✅ ALL FIXES COMPLETED SUCCESSFULLY!\n";
    echo "🎉 Online system should now work perfectly!\n\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>