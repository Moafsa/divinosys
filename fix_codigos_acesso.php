<?php
/**
 * Script para criar tabela codigos_acesso e corrigir sistema de login por telefone
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Conectar ao banco
    $host = $_ENV['DB_HOST'] ?? 'postgres';
    $dbname = $_ENV['DB_NAME'] ?? 'divino_lanches';
    $user = $_ENV['DB_USER'] ?? 'postgres';
    $password = $_ENV['DB_PASSWORD'] ?? 'postgres';
    
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>🔧 CORREÇÃO SISTEMA DE LOGIN POR TELEFONE</h1>";
    echo "<p>✅ Conectado ao banco: $dbname</p>";
    
    // 1. Verificar se a tabela já existe
    echo "<h2>1. Verificando tabela codigos_acesso...</h2>";
    
    try {
        $result = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'codigos_acesso')");
        $exists = $result->fetchColumn();
        echo "<p>📊 Tabela codigos_acesso existe: " . ($exists ? "SIM" : "NÃO") . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro ao verificar: " . $e->getMessage() . "</p>";
    }
    
    // 2. Criar tabela codigos_acesso se não existir
    echo "<h2>2. Criando tabela codigos_acesso...</h2>";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS codigos_acesso (
            id SERIAL PRIMARY KEY,
            usuario_global_id INTEGER NOT NULL,
            telefone VARCHAR(20) NOT NULL,
            codigo VARCHAR(10) NOT NULL,
            usado BOOLEAN DEFAULT FALSE,
            expira_em TIMESTAMP NOT NULL,
            tentativas INTEGER DEFAULT 0,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "<p>✅ Tabela codigos_acesso criada/verificada</p>";
    
    // 3. Criar índices para performance
    echo "<h2>3. Criando índices...</h2>";
    
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_codigos_acesso_telefone ON codigos_acesso(telefone);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_codigos_acesso_codigo ON codigos_acesso(codigo);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_codigos_acesso_expira ON codigos_acesso(expira_em);");
        echo "<p>✅ Índices criados</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso índices: " . $e->getMessage() . "</p>";
    }
    
    // 4. Verificar se existe coluna usuario_global_id na tabela usuarios
    echo "<h2>4. Verificando estrutura da tabela usuarios...</h2>";
    
    try {
        $result = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'usuarios' AND column_name = 'usuario_global_id'");
        $hasGlobalId = $result->fetchColumn();
        
        if (!$hasGlobalId) {
            echo "<p>⚠️ Coluna usuario_global_id não existe em usuarios. Adicionando...</p>";
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN usuario_global_id INTEGER;");
            echo "<p>✅ Coluna usuario_global_id adicionada</p>";
        } else {
            echo "<p>✅ Coluna usuario_global_id já existe</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Erro ao verificar usuarios: " . $e->getMessage() . "</p>";
    }
    
    // 5. Testar inserção de código de teste
    echo "<h2>5. Testando inserção de código...</h2>";
    
    try {
        $telefone = '+5554999886692';
        $codigo = '123456';
        $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $stmt = $pdo->prepare("
            INSERT INTO codigos_acesso (usuario_global_id, telefone, codigo, expira_em, tenant_id, filial_id) 
            VALUES (1, ?, ?, ?, 1, 1)
        ");
        $stmt->execute([$telefone, $codigo, $expira]);
        echo "<p>✅ Código de teste inserido com sucesso</p>";
        
        // Limpar código de teste
        $pdo->exec("DELETE FROM codigos_acesso WHERE codigo = '123456'");
        echo "<p>✅ Código de teste removido</p>";
        
    } catch (Exception $e) {
        echo "<p>❌ Erro no teste: " . $e->getMessage() . "</p>";
    }
    
    // 6. Verificar estrutura final
    echo "<h2>6. Verificando estrutura final...</h2>";
    
    try {
        $result = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'codigos_acesso' ORDER BY ordinal_position");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>📋 Colunas da tabela codigos_acesso:</p>";
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li><strong>{$col['column_name']}</strong> - {$col['data_type']}</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p>❌ Erro na verificação final: " . $e->getMessage() . "</p>";
    }
    
    echo "<h1>🎉 CORREÇÃO CONCLUÍDA!</h1>";
    echo "<p><a href='index.php?view=login'>Testar login por telefone</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro crítico:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>
