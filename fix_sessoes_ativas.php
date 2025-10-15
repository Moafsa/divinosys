<?php
/**
 * Script para criar tabela sessoes_ativas e corrigir sistema de sessões
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
    
    echo "<h1>🔧 CORREÇÃO SISTEMA DE SESSÕES ATIVAS</h1>";
    echo "<p>✅ Conectado ao banco: $dbname</p>";
    
    // 1. Verificar se a tabela já existe
    echo "<h2>1. Verificando tabela sessoes_ativas...</h2>";
    
    try {
        $result = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'sessoes_ativas')");
        $exists = $result->fetchColumn();
        echo "<p>📊 Tabela sessoes_ativas existe: " . ($exists ? "SIM" : "NÃO") . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro ao verificar: " . $e->getMessage() . "</p>";
    }
    
    // 2. Criar tabela sessoes_ativas se não existir
    echo "<h2>2. Criando tabela sessoes_ativas...</h2>";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessoes_ativas (
            id SERIAL PRIMARY KEY,
            usuario_global_id INTEGER NOT NULL,
            tenant_id INTEGER NOT NULL,
            filial_id INTEGER NOT NULL,
            token VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            ultimo_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expira_em TIMESTAMP NOT NULL,
            ativo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(token),
            UNIQUE(usuario_global_id, tenant_id, filial_id)
        );
    ");
    echo "<p>✅ Tabela sessoes_ativas criada/verificada</p>";
    
    // 3. Criar índices para performance
    echo "<h2>3. Criando índices...</h2>";
    
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_token ON sessoes_ativas(token);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_usuario ON sessoes_ativas(usuario_global_id, tenant_id, filial_id);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_expira ON sessoes_ativas(expira_em);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_ativo ON sessoes_ativas(ativo);");
        echo "<p>✅ Índices criados</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso índices: " . $e->getMessage() . "</p>";
    }
    
    // 4. Limpar sessões expiradas (se existirem)
    echo "<h2>4. Limpando sessões expiradas...</h2>";
    
    try {
        $result = $pdo->exec("DELETE FROM sessoes_ativas WHERE expira_em < NOW() OR ativo = FALSE");
        echo "<p>✅ Sessões expiradas removidas: $result registros</p>";
    } catch (Exception $e) {
        echo "<p>⚠️ Aviso limpeza: " . $e->getMessage() . "</p>";
    }
    
    // 5. Testar inserção de sessão de teste
    echo "<h2>5. Testando inserção de sessão...</h2>";
    
    try {
        $token = 'test_token_' . time();
        $expira = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $pdo->prepare("
            INSERT INTO sessoes_ativas (usuario_global_id, tenant_id, filial_id, token, ip_address, user_agent, expira_em) 
            VALUES (1, 1, 1, ?, '127.0.0.1', 'Test User Agent', ?)
        ");
        $stmt->execute([$token, $expira]);
        echo "<p>✅ Sessão de teste inserida com sucesso</p>";
        
        // Limpar sessão de teste
        $pdo->exec("DELETE FROM sessoes_ativas WHERE token = '$token'");
        echo "<p>✅ Sessão de teste removida</p>";
        
    } catch (Exception $e) {
        echo "<p>❌ Erro no teste: " . $e->getMessage() . "</p>";
    }
    
    // 6. Verificar estrutura final
    echo "<h2>6. Verificando estrutura final...</h2>";
    
    try {
        $result = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'sessoes_ativas' ORDER BY ordinal_position");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>📋 Colunas da tabela sessoes_ativas:</p>";
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li><strong>{$col['column_name']}</strong> - {$col['data_type']}</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "<p>❌ Erro na verificação final: " . $e->getMessage() . "</p>";
    }
    
    echo "<h1>🎉 CORREÇÃO CONCLUÍDA!</h1>";
    echo "<p><a href='index.php?view=login'>Testar login por telefone novamente</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro crítico:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>
