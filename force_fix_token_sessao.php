<?php
/**
 * Script FORÇADO para corrigir coluna token_sessao
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
    
    echo "<h1>🔧 CORREÇÃO FORÇADA - TOKEN_SESSAO</h1>";
    echo "<p>✅ Conectado ao banco: $dbname</p>";
    
    // 1. Verificar se a tabela existe
    echo "<h2>1. Verificando tabela sessoes_ativas...</h2>";
    
    $result = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'sessoes_ativas')");
    $exists = $result->fetchColumn();
    echo "<p>📊 Tabela sessoes_ativas existe: " . ($exists ? "SIM" : "NÃO") . "</p>";
    
    if (!$exists) {
        echo "<p>❌ Tabela não existe! Criando...</p>";
        $pdo->exec("
            CREATE TABLE sessoes_ativas (
                id SERIAL PRIMARY KEY,
                usuario_global_id INTEGER NOT NULL,
                tenant_id INTEGER NOT NULL,
                filial_id INTEGER NOT NULL,
                token VARCHAR(255),
                token_sessao VARCHAR(255),
                ip_address VARCHAR(45),
                user_agent TEXT,
                ultimo_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expira_em TIMESTAMP NOT NULL,
                ativo BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        echo "<p>✅ Tabela criada com token_sessao</p>";
    } else {
        // 2. Verificar se a coluna existe
        echo "<h2>2. Verificando coluna token_sessao...</h2>";
        
        $result = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'sessoes_ativas' AND column_name = 'token_sessao'");
        $hasColumn = $result->fetchColumn();
        echo "<p>📊 Coluna token_sessao existe: " . ($hasColumn > 0 ? "SIM" : "NÃO") . "</p>";
        
        if ($hasColumn == 0) {
            echo "<p>⚠️ Coluna não existe! Adicionando...</p>";
            
            // Tentar adicionar a coluna
            try {
                $pdo->exec("ALTER TABLE sessoes_ativas ADD COLUMN token_sessao VARCHAR(255);");
                echo "<p>✅ Coluna token_sessao adicionada com sucesso!</p>";
            } catch (Exception $e) {
                echo "<p>❌ Erro ao adicionar coluna: " . $e->getMessage() . "</p>";
                
                // Se falhar, tentar recriar a tabela
                echo "<p>🔄 Tentando recriar tabela...</p>";
                
                // Fazer backup dos dados existentes
                $backup = $pdo->query("SELECT * FROM sessoes_ativas")->fetchAll(PDO::FETCH_ASSOC);
                echo "<p>📋 Backup feito: " . count($backup) . " registros</p>";
                
                // Dropar e recriar
                $pdo->exec("DROP TABLE sessoes_ativas CASCADE;");
                echo "<p>✅ Tabela removida</p>";
                
                $pdo->exec("
                    CREATE TABLE sessoes_ativas (
                        id SERIAL PRIMARY KEY,
                        usuario_global_id INTEGER NOT NULL,
                        tenant_id INTEGER NOT NULL,
                        filial_id INTEGER NOT NULL,
                        token VARCHAR(255),
                        token_sessao VARCHAR(255),
                        ip_address VARCHAR(45),
                        user_agent TEXT,
                        ultimo_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        expira_em TIMESTAMP NOT NULL,
                        ativo BOOLEAN DEFAULT TRUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );
                ");
                echo "<p>✅ Tabela recriada com token_sessao</p>";
                
                // Restaurar dados se houver
                if (!empty($backup)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO sessoes_ativas (usuario_global_id, tenant_id, filial_id, token, ip_address, user_agent, ultimo_acesso, expira_em, ativo, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    foreach ($backup as $row) {
                        $stmt->execute([
                            $row['usuario_global_id'],
                            $row['tenant_id'],
                            $row['filial_id'],
                            $row['token'] ?? null,
                            $row['ip_address'] ?? null,
                            $row['user_agent'] ?? null,
                            $row['ultimo_acesso'] ?? date('Y-m-d H:i:s'),
                            $row['expira_em'],
                            $row['ativo'] ?? true,
                            $row['created_at'] ?? date('Y-m-d H:i:s'),
                            $row['updated_at'] ?? date('Y-m-d H:i:s')
                        ]);
                    }
                    echo "<p>✅ Dados restaurados: " . count($backup) . " registros</p>";
                }
            }
        } else {
            echo "<p>✅ Coluna token_sessao já existe</p>";
        }
    }
    
    // 3. Verificar estrutura final
    echo "<h2>3. Verificando estrutura final...</h2>";
    
    $result = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'sessoes_ativas' ORDER BY ordinal_position");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>📋 Colunas da tabela sessoes_ativas:</p>";
    echo "<ul>";
    foreach ($columns as $col) {
        $highlight = ($col['column_name'] == 'token_sessao') ? ' style="color: green; font-weight: bold;"' : '';
        echo "<li{$highlight}><strong>{$col['column_name']}</strong> - {$col['data_type']}</li>";
    }
    echo "</ul>";
    
    // 4. Testar inserção
    echo "<h2>4. Testando inserção...</h2>";
    
    try {
        $token = 'test_token_' . time();
        $token_sessao = 'test_token_sessao_' . time();
        $expira = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $pdo->prepare("
            INSERT INTO sessoes_ativas (usuario_global_id, tenant_id, filial_id, token, token_sessao, expira_em) 
            VALUES (1, 1, 1, ?, ?, ?)
        ");
        $stmt->execute([$token, $token_sessao, $expira]);
        echo "<p>✅ Inserção com token_sessao funcionou!</p>";
        
        // Limpar teste
        $pdo->exec("DELETE FROM sessoes_ativas WHERE token = '$token'");
        echo "<p>✅ Registro de teste removido</p>";
        
    } catch (Exception $e) {
        echo "<p>❌ Erro no teste: " . $e->getMessage() . "</p>";
    }
    
    echo "<h1>🎉 CORREÇÃO FORÇADA CONCLUÍDA!</h1>";
    echo "<p><a href='index.php?view=login'>Testar login por telefone</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro crítico:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>


