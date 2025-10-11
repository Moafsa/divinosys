<?php
/**
 * FIX TIPO_USUARIO ONLINE - Corrige apenas a coluna faltante
 * Execute este script apenas ONLINE, não afeta o ambiente local
 */

echo "<h1>🔧 Fixing tipo_usuario Column Online</h1>";

try {
    // Usar variáveis de ambiente (funciona no Coolify)
    $host = $_ENV['DB_HOST'] ?? 'postgres';
    $port = $_ENV['DB_PORT'] ?? '5432';
    $dbname = $_ENV['DB_NAME'] ?? 'divino_db';
    $user = $_ENV['DB_USER'] ?? 'divino_user';
    $password = $_ENV['DB_PASSWORD'] ?? 'divino_password';
    
    echo "<p>Connecting to: $dbname@$host:$port</p>";
    
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Verificar se a coluna existe
    echo "<h2>🔍 Checking tipo_usuario column...</h2>";
    
    $stmt = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'usuarios_estabelecimento' 
        AND column_name = 'tipo_usuario'
    ");
    
    if ($stmt->rowCount() == 0) {
        echo "<p style='color: orange;'>⚠️ Column 'tipo_usuario' does not exist. Adding it...</p>";
        
        // Adicionar a coluna
        $pdo->exec("ALTER TABLE usuarios_estabelecimento ADD COLUMN tipo_usuario VARCHAR(50) NOT NULL DEFAULT 'admin'");
        
        echo "<p style='color: green;'>✅ Column 'tipo_usuario' added successfully!</p>";
        
        // Verificar se há registros existentes e atualizar se necessário
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios_estabelecimento WHERE tipo_usuario IS NULL");
        $nullCount = $stmt->fetchColumn();
        
        if ($nullCount > 0) {
            echo "<p style='color: orange;'>⚠️ Found $nullCount records with NULL tipo_usuario. Updating...</p>";
            $pdo->exec("UPDATE usuarios_estabelecimento SET tipo_usuario = 'admin' WHERE tipo_usuario IS NULL");
            echo "<p style='color: green;'>✅ Updated $nullCount records!</p>";
        }
        
    } else {
        echo "<p style='color: green;'>✅ Column 'tipo_usuario' already exists!</p>";
    }
    
    // Teste de criação de usuário
    echo "<h2>🧪 Testing user creation...</h2>";
    
    try {
        // Criar usuário de teste
        $stmt = $pdo->prepare("
            INSERT INTO usuarios_globais (nome, email, telefone, tipo_usuario, ativo, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?) 
            RETURNING id
        ");
        $stmt->execute([
            'Teste Online ' . time(),
            'teste' . time() . '@example.com',
            '5554999999999',
            'admin',
            true,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
        $usuarioGlobalId = $stmt->fetchColumn();
        
        echo "<p style='color: green;'>✅ Global user created with ID: $usuarioGlobalId</p>";
        
        // Criar usuário de estabelecimento
        $stmt = $pdo->prepare("
            INSERT INTO usuarios_estabelecimento (usuario_global_id, tenant_id, filial_id, tipo_usuario, cargo, ativo, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $usuarioGlobalId,
            1, // tenant_id
            1, // filial_id
            'admin', // tipo_usuario
            'Admin', // cargo
            true,
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
        
        echo "<p style='color: green;'>✅ Establishment user created successfully!</p>";
        
        // Limpar dados de teste
        $pdo->exec("DELETE FROM usuarios_estabelecimento WHERE usuario_global_id = $usuarioGlobalId");
        $pdo->exec("DELETE FROM usuarios_globais WHERE id = $usuarioGlobalId");
        
        echo "<p style='color: blue;'>ℹ️ Test data cleaned up</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ User creation test failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<h2 style='color: green;'>🎉 FIX COMPLETED SUCCESSFULLY!</h2>";
    echo "<p>User creation should now work online!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
