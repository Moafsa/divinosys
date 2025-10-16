<?php
// fix_sessoes_ativas_token.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Script de Correção: Ajustar Coluna 'token' em 'sessoes_ativas'</h1>";

try {
    $host = $_ENV['DB_HOST'] ?? 'postgres';
    $dbname = $_ENV['DB_NAME'] ?? 'divino_lanches';
    $user = $_ENV['DB_USER'] ?? 'postgres';
    $password = $_ENV['DB_PASSWORD'] ?? 'postgres';

    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<p>✅ Conectado ao banco de dados: <strong>$dbname</strong></p>";

    // --- 1. Verificar estrutura atual da tabela sessoes_ativas ---
    echo "<h2>1. Verificando estrutura atual de 'sessoes_ativas'...</h2>";
    $stmt = $pdo->query("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = 'sessoes_ativas' ORDER BY ordinal_position;");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Colunas atuais:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Coluna</th><th>Tipo</th><th>Permite NULL</th><th>Valor Padrão</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['column_name'] . "</td>";
        echo "<td>" . $col['data_type'] . "</td>";
        echo "<td>" . ($col['is_nullable'] === 'YES' ? 'SIM' : 'NÃO') . "</td>";
        echo "<td>" . ($col['column_default'] ?? 'Nenhum') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // --- 2. Corrigir coluna token para permitir NULL ---
    echo "<h2>2. Corrigindo coluna 'token' para permitir NULL...</h2>";
    try {
        $pdo->exec("ALTER TABLE sessoes_ativas ALTER COLUMN token DROP NOT NULL;");
        echo "<p>✅ Coluna 'token' agora permite NULL.</p>";
    } catch (PDOException $e) {
        echo "<p>❌ Erro ao modificar coluna 'token': " . $e->getMessage() . "</p>";
        throw $e;
    }

    // --- 3. Corrigir coluna expira_em para permitir NULL ---
    echo "<h2>3. Corrigindo coluna 'expira_em' para permitir NULL...</h2>";
    try {
        $pdo->exec("ALTER TABLE sessoes_ativas ALTER COLUMN expira_em DROP NOT NULL;");
        echo "<p>✅ Coluna 'expira_em' agora permite NULL.</p>";
    } catch (PDOException $e) {
        echo "<p>❌ Erro ao modificar coluna 'expira_em': " . $e->getMessage() . "</p>";
        throw $e;
    }

    // --- 4. Verificar se created_at e updated_at têm DEFAULT ---
    echo "<h2>4. Verificando colunas de timestamp...</h2>";
    $stmt = $pdo->query("SELECT column_name, column_default FROM information_schema.columns WHERE table_name = 'sessoes_ativas' AND column_name IN ('created_at', 'updated_at')");
    $timestampCols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($timestampCols as $col) {
        if (empty($col['column_default'])) {
            echo "<p>⚠️ Coluna '{$col['column_name']}' não tem valor padrão. Adicionando...</p>";
            try {
                $pdo->exec("ALTER TABLE sessoes_ativas ALTER COLUMN {$col['column_name']} SET DEFAULT CURRENT_TIMESTAMP;");
                echo "<p>✅ Valor padrão adicionado para '{$col['column_name']}'.</p>";
            } catch (PDOException $e) {
                echo "<p>❌ Erro ao adicionar valor padrão para '{$col['column_name']}': " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>✅ Coluna '{$col['column_name']}' já tem valor padrão: {$col['column_default']}</p>";
        }
    }

    // --- 5. Verificação Final ---
    echo "<h1>✅ Verificação Final</h1>";

    // Verificar estrutura final das colunas críticas
    $criticalColumns = ['token', 'expira_em', 'filial_id'];
    foreach ($criticalColumns as $colName) {
        $stmt = $pdo->query("SELECT column_name, is_nullable FROM information_schema.columns WHERE table_name = 'sessoes_ativas' AND column_name = '$colName'");
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($col) {
            echo "<p>✅ Coluna '$colName' permite NULL: " . ($col['is_nullable'] === 'YES' ? 'SIM' : 'NÃO') . "</p>";
        } else {
            echo "<p>❌ Coluna '$colName' não encontrada.</p>";
        }
    }

    // Testar inserção com valores NULL
    echo "<h2>Testando inserção com valores NULL:</h2>";
    try {
        $testTokenSessao = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO sessoes_ativas (usuario_global_id, tenant_id, filial_id, token, token_sessao, expira_em) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, 1, null, null, $testTokenSessao, null]);
        echo "<p>✅ Inserção de teste com valores NULL realizada com sucesso.</p>";
        
        // Limpar teste
        $pdo->exec("DELETE FROM sessoes_ativas WHERE token_sessao = '$testTokenSessao'");
        echo "<p>✅ Dados de teste removidos.</p>";
    } catch (PDOException $e) {
        echo "<p>❌ Erro ao testar inserção: " . $e->getMessage() . "</p>";
    }

    echo "<h1>🎉 Correção Concluída!</h1>";
    echo "<p><strong>Próximo passo:</strong> Teste o login novamente em <a href='https://divinosys.conext.click/index.php?view=login' target='_blank'>https://divinosys.conext.click/index.php?view=login</a></p>";

} catch (PDOException $e) {
    echo "<h1>❌ Erro Crítico na Correção do Banco de Dados</h1>";
    echo "<p>Erro: " . $e->getMessage() . "</p>";
    error_log("Erro crítico em fix_sessoes_ativas_token.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}
?>
