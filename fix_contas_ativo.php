<?php
/**
 * Script para corrigir coluna ativo em contas_financeiras
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
    
    echo "<h1>🔧 CORREÇÃO COLUNA ATIVO - CONTAS_FINANCEIRAS</h1>";
    echo "<p>✅ Conectado ao banco: $dbname</p>";
    
    // 1. Verificar estrutura atual
    echo "<h2>1. Verificando estrutura atual...</h2>";
    
    try {
        $result = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'contas_financeiras' ORDER BY ordinal_position");
        $columns = $result->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>📊 Colunas atuais em contas_financeiras: " . implode(', ', $columns) . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro ao verificar estrutura: " . $e->getMessage() . "</p>";
    }
    
    // 2. Adicionar coluna ativo
    echo "<h2>2. Adicionando coluna ativo...</h2>";
    
    try {
        $pdo->exec("ALTER TABLE contas_financeiras ADD COLUMN ativo BOOLEAN DEFAULT TRUE;");
        echo "<p>✅ Coluna ativo adicionada com sucesso!</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p>✅ Coluna ativo já existe</p>";
        } else {
            echo "<p>❌ Erro ao adicionar ativo: " . $e->getMessage() . "</p>";
        }
    }
    
    // 3. Verificar se foi adicionada
    echo "<h2>3. Verificando se foi adicionada...</h2>";
    
    try {
        $result = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'contas_financeiras' AND column_name = 'ativo'");
        $count = $result->fetchColumn();
        echo "<p>📊 Coluna ativo existe: " . ($count > 0 ? "SIM" : "NÃO") . "</p>";
    } catch (Exception $e) {
        echo "<p>❌ Erro na verificação: " . $e->getMessage() . "</p>";
    }
    
    // 4. Testar a consulta que estava falhando
    echo "<h2>4. Testando consulta...</h2>";
    
    try {
        $result = $pdo->query("SELECT * FROM contas_financeiras WHERE tenant_id = 1 AND filial_id = 1 AND ativo = true ORDER BY nome");
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);
        echo "<p>✅ Consulta funcionou! Encontrados " . count($rows) . " registros</p>";
        if (count($rows) > 0) {
            echo "<p>📋 Primeiro registro: " . json_encode($rows[0]) . "</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Erro na consulta: " . $e->getMessage() . "</p>";
    }
    
    echo "<h1>🎉 CORREÇÃO CONCLUÍDA!</h1>";
    echo "<p><a href='index.php?view=financeiro'>Testar página financeiro</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro crítico:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>
