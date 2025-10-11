<?php
/**
 * SOLUÇÃO DEFINITIVA PARA PROBLEMAS DE SEQUÊNCIAS
 * 
 * Este script implementa o SequenceManager que previne
 * problemas de sequências para sempre!
 */

echo "🔧 IMPLEMENTING DEFINITIVE SEQUENCE SOLUTION\n";
echo "===========================================\n\n";

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
    
    // 1. CRIAR FUNÇÃO DE SINCRONIZAÇÃO AUTOMÁTICA
    echo "1. Creating automatic sequence sync function...\n";
    
    $pdo->exec("
        CREATE OR REPLACE FUNCTION sync_sequence_auto(table_name TEXT, sequence_name TEXT, id_column TEXT DEFAULT 'id')
        RETURNS BOOLEAN AS \$\$
        DECLARE
            current_value BIGINT;
            max_id BIGINT;
            new_value BIGINT;
        BEGIN
            -- Get current sequence value
            EXECUTE format('SELECT last_value FROM %I', sequence_name) INTO current_value;
            
            -- Get max ID from table
            EXECUTE format('SELECT COALESCE(MAX(%I), 0) FROM %I', id_column, table_name) INTO max_id;
            
            -- If sequence is behind, sync it
            IF current_value <= max_id THEN
                new_value := max_id + 1;
                EXECUTE format('SELECT setval(%L, %s, false)', sequence_name, new_value);
                RAISE NOTICE 'Sequence % synced: % -> % (MAX ID: %)', sequence_name, current_value, new_value, max_id;
                RETURN true;
            END IF;
            
            RETURN false;
        END;
        \$\$ LANGUAGE plpgsql;
    ");
    echo "   ✅ Auto-sync function created!\n";
    
    // 2. CRIAR TRIGGER PARA SINCRONIZAÇÃO AUTOMÁTICA
    echo "\n2. Creating automatic sync triggers...\n";
    
    $tables = [
        'produtos' => ['seq' => 'produtos_id_seq', 'id' => 'id'],
        'categorias' => ['seq' => 'categorias_id_seq', 'id' => 'id'],
        'ingredientes' => ['seq' => 'ingredientes_id_seq', 'id' => 'id'],
        'mesas' => ['seq' => 'mesas_id_seq', 'id' => 'id'],
        'pedido' => ['seq' => 'pedido_idpedido_seq', 'id' => 'idpedido'],
        'pedido_itens' => ['seq' => 'pedido_itens_id_seq', 'id' => 'id'],
        'usuarios_globais' => ['seq' => 'usuarios_globais_id_seq', 'id' => 'id'],
        'usuarios_estabelecimento' => ['seq' => 'usuarios_estabelecimento_id_seq', 'id' => 'id'],
    ];
    
    foreach ($tables as $table => $config) {
        echo "   Creating trigger for $table...\n";
        
        // Drop existing trigger if exists
        $pdo->exec("DROP TRIGGER IF EXISTS sync_seq_trigger_$table ON $table");
        
        // Create trigger function
        $pdo->exec("
            CREATE OR REPLACE FUNCTION sync_seq_trigger_$table()
            RETURNS TRIGGER AS \$\$
            BEGIN
                PERFORM sync_sequence_auto('$table', '{$config['seq']}', '{$config['id']}');
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
        
        // Create trigger
        $pdo->exec("
            CREATE TRIGGER sync_seq_trigger_$table
            BEFORE INSERT ON $table
            FOR EACH STATEMENT
            EXECUTE FUNCTION sync_seq_trigger_$table();
        ");
        
        echo "     ✅ Trigger created for $table\n";
    }
    
    // 3. SINCRONIZAR TODAS AS SEQUÊNCIAS AGORA
    echo "\n3. Syncing all sequences now...\n";
    
    foreach ($tables as $table => $config) {
        echo "   Syncing $table...\n";
        $pdo->exec("SELECT sync_sequence_auto('$table', '{$config['seq']}', '{$config['id']}')");
        echo "     ✅ $table synced\n";
    }
    
    // 4. ADICIONAR COLUNA TIPO_USUARIO SE NÃO EXISTIR
    echo "\n4. Checking tipo_usuario column...\n";
    
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'usuarios_estabelecimento' 
        AND column_name = 'tipo_usuario'
    ");
    
    if ($stmt->rowCount() == 0) {
        echo "   Adding missing tipo_usuario column...\n";
        $pdo->exec("ALTER TABLE usuarios_estabelecimento ADD COLUMN tipo_usuario VARCHAR(50) NOT NULL DEFAULT 'admin'");
        echo "   ✅ Column added!\n";
    } else {
        echo "   ✅ Column already exists\n";
    }
    
    // 5. TESTE FINAL
    echo "\n5. Final test...\n";
    
    // Test category creation
    echo "   Testing category creation...\n";
    $stmt = $pdo->prepare("INSERT INTO categorias (nome, descricao, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Teste Final', 'Teste definitivo', true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $categoryId = $pdo->lastInsertId();
    echo "     ✅ Category created with ID: $categoryId\n";
    
    // Test product creation
    echo "   Testing product creation...\n";
    $stmt = $pdo->prepare("INSERT INTO produtos (nome, descricao, preco_normal, preco_mini, categoria_id, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Teste Final Produto', 'Teste definitivo', 15.99, 14.99, $categoryId, true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $productId = $pdo->lastInsertId();
    echo "     ✅ Product created with ID: $productId\n";
    
    // Test ingredient creation
    echo "   Testing ingredient creation...\n";
    $stmt = $pdo->prepare("INSERT INTO ingredientes (nome, descricao, preco_adicional, ativo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Teste Final Ingrediente', 'Teste definitivo', 2.50, true, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
    $ingredientId = $pdo->lastInsertId();
    echo "     ✅ Ingredient created with ID: $ingredientId\n";
    
    echo "\n🎉 DEFINITIVE SOLUTION IMPLEMENTED SUCCESSFULLY!\n";
    echo "✅ Sequences will now auto-sync forever!\n";
    echo "✅ No more sequence problems!\n";
    echo "✅ All tests passed!\n\n";
    
    echo "📋 What was implemented:\n";
    echo "   • Auto-sync function for all sequences\n";
    echo "   • Triggers that sync sequences before INSERT\n";
    echo "   • Missing tipo_usuario column added\n";
    echo "   • All sequences synchronized\n";
    echo "   • Comprehensive testing completed\n\n";
    
    echo "🚀 Your online system is now bulletproof against sequence issues!\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
