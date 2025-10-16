<?php
/**
 * Script para corrigir coluna token_sessao na tabela sessoes_ativas
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
    
    echo "<h1>🔧 CORREÇÃO COLUNA TOKEN_SESSAO</h1>";
    echo "<p>✅ Conectado ao banco: $dbname</p>";
    
    // 1. Verificar estrutura atual da tabela sessoes_ativas
    echo "<h2>1. Verificando estrutura atual...</h2>";
    
    try {
        $result = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'sessoes_ativas' ORDER BY ordinal_position");
        $columns = $result->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>📊 Colunas atuais em sessoes_ativas: " . implode(', ', $columns) . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro ao verificar estrutura: " . $e->getMessage() . "</p>";
    }
    
    // 2. Adicionar coluna token_sessao se não existir
    echo "<h2>2. Adicionando coluna token_sessao...</h2>";
    
    try {
        $pdo->exec("ALTER TABLE sessoes_ativas ADD COLUMN token_sessao VARCHAR(255);");
        echo "<p>✅ Coluna token_sessao adicionada com sucesso!</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p>✅ Coluna token_sessao já existe</p>";
        } else {
            echo "<p>❌ Erro ao adicionar token_sessao: " . $e->getMessage() . "</p>";
        }
    }
    
    // 3. Verificar se foi adicionada
    echo "<h2>3. Verificando se foi adicionada...</h2>";
    
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'sessoes_ativas' AND column_name = 'token_sessao'");
        $count = $result->fetchColumn();
        echo "<p>📊 Coluna token_sessao existe: " . ($count > 0 ? "SIM" : "NÃO") . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro na verificação: " . $e->getMessage() . "</p>";
    }
    
    // 4. Testar inserção com token_sessao
    echo "<h2>4. Testando inserção com token_sessao...</h2>";
    
    try {
        $token = 'test_token_sessao_' . time();
        $token_sessao = 'test_token_sessao_' . time();
        $expira = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $pdo->prepare("
            INSERT INTO sessoes_ativas (usuario_global_id, tenant_id, filial_id, token, token_sessao, ip_address, user_agent, expira_em) 
            VALUES (1, 1, 1, ?, ?, '127.0.0.1', 'Test User Agent', ?)
        ");
        $stmt->execute([$token, $token_sessao, $expira]);
        echo "<p>✅ Inserção com token_sessao funcionou!</p>";
        
        // Limpar teste
        $pdo->exec("DELETE FROM sessoes_ativas WHERE token = '$token'");
        echo "<p>✅ Registro de teste removido</p>";
        
    } catch (Exception $e) {
        echo "<p>❌ Erro no teste: " . $e->getMessage() . "</p>";
    }
    
    // 5. Verificar estrutura final
    echo "<h2>5. Verificando estrutura final...</h2>";
    
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




