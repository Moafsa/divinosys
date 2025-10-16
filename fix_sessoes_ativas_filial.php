<?php
// fix_sessoes_ativas_filial.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 Script de Correção: Ajustar Coluna 'filial_id' em 'sessoes_ativas'</h1>";

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

    // --- 2. Corrigir coluna filial_id para permitir NULL ---
    echo "<h2>2. Corrigindo coluna 'filial_id' para permitir NULL...</h2>";
    try {
        $pdo->exec("ALTER TABLE sessoes_ativas ALTER COLUMN filial_id DROP NOT NULL;");
        echo "<p>✅ Coluna 'filial_id' agora permite NULL.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'does not exist') !== false) {
            echo "<p>⚠️ Coluna 'filial_id' não existe. Criando tabela completa...</p>";
            
            // Dropar e recriar a tabela com estrutura correta
            $pdo->exec("DROP TABLE IF EXISTS sessoes_ativas CASCADE;");
            $pdo->exec("
                CREATE TABLE sessoes_ativas (
                    id SERIAL PRIMARY KEY,
                    usuario_global_id INTEGER NOT NULL,
                    tenant_id INTEGER NOT NULL,
                    filial_id INTEGER,
                    token VARCHAR(255) NOT NULL UNIQUE,
                    token_sessao VARCHAR(255),
                    expira_em TIMESTAMP NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );
            ");
            echo "<p>✅ Tabela 'sessoes_ativas' recriada com estrutura correta.</p>";
        } else {
            throw $e;
        }
    }

    // --- 3. Adicionar índices para performance ---
    echo "<h2>3. Adicionando índices para performance...</h2>";
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_usuario_global_id ON sessoes_ativas (usuario_global_id);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_token ON sessoes_ativas (token);");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sessoes_ativas_token_sessao ON sessoes_ativas (token_sessao);");
        echo "<p>✅ Índices adicionados/verificados.</p>";
    } catch (PDOException $e) {
        echo "<p>⚠️ Erro ao criar índices: " . $e->getMessage() . "</p>";
    }

    // --- 4. Verificação Final ---
    echo "<h1>✅ Verificação Final</h1>";

    // Verificar estrutura final
    $stmt = $pdo->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'sessoes_ativas' AND column_name = 'filial_id'");
    $filialCol = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($filialCol) {
        echo "<p>✅ Coluna 'filial_id' existe e permite NULL: " . ($filialCol['is_nullable'] === 'YES' ? 'SIM' : 'NÃO') . "</p>";
    } else {
        echo "<p>❌ Coluna 'filial_id' não encontrada.</p>";
    }

    // Testar inserção com filial_id NULL
    echo "<h2>Testando inserção com filial_id NULL:</h2>";
    try {
        $testToken = bin2hex(random_bytes(32));
        $testTokenSessao = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO sessoes_ativas (usuario_global_id, tenant_id, filial_id, token, token_sessao, expira_em) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([1, 1, null, $testToken, $testTokenSessao, date('Y-m-d H:i:s', strtotime('+1 hour'))]);
        echo "<p>✅ Inserção de teste com filial_id NULL realizada com sucesso.</p>";
        
        // Limpar teste
        $pdo->exec("DELETE FROM sessoes_ativas WHERE token = '$testToken'");
        echo "<p>✅ Dados de teste removidos.</p>";
    } catch (PDOException $e) {
        echo "<p>❌ Erro ao testar inserção: " . $e->getMessage() . "</p>";
    }

    echo "<h1>🎉 Correção Concluída!</h1>";
    echo "<p><strong>Próximo passo:</strong> Teste o login novamente em <a href='https://divinosys.conext.click/index.php?view=login' target='_blank'>https://divinosys.conext.click/index.php?view=login</a></p>";

} catch (PDOException $e) {
    echo "<h1>❌ Erro Crítico na Correção do Banco de Dados</h1>";
    echo "<p>Erro: " . $e->getMessage() . "</p>";
    error_log("Erro crítico em fix_sessoes_ativas_filial.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
}
?>


