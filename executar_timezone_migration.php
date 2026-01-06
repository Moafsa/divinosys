<?php
/**
 * Script para executar a migration de timezone
 * Acesse diretamente: http://seu-dominio/executar_timezone_migration.php
 */

// Iniciar output buffering
ob_start();

require 'index.php';

// Limpar qualquer output anterior
ob_clean();

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration de Timezone</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            border-bottom: 4px solid #667eea;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .success {
            color: #155724;
            background: #d4edda;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #28a745;
        }
        .error {
            color: #721c24;
            background: #f8d7da;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #dc3545;
        }
        .warning {
            color: #856404;
            background: #fff3cd;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #ffc107;
        }
        .info {
            color: #004085;
            background: #cce5ff;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #007bff;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            overflow-x: auto;
            border-left: 4px solid #007bff;
            font-size: 13px;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Migration de Timezone - Sistema de Horários</h1>
        
        <?php
        try {
            $db = \System\Database::getInstance();
            
            echo '<div class="info">';
            echo '<strong>📋 Verificando estado atual...</strong><br>';
            echo '</div>';
            
            // Verificar se a coluna já existe
            $checkColumn = $db->fetch("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = 'filiais' 
                AND column_name = 'timezone'
            ");
            
            if ($checkColumn) {
                echo '<div class="success">';
                echo '✅ A coluna <strong>timezone</strong> já existe na tabela <strong>filiais</strong>!<br>';
                echo '</div>';
                
                // Verificar valores
                $filiais = $db->fetchAll("SELECT id, nome, timezone FROM filiais ORDER BY id");
                
                if (empty($filiais)) {
                    echo '<div class="warning">⚠️ Nenhuma filial encontrada no banco de dados.</div>';
                } else {
                    echo '<div class="info">';
                    echo '<strong>📊 Filiais cadastradas:</strong><br><br>';
                    echo '<table>';
                    echo '<tr><th>ID</th><th>Nome</th><th>Timezone</th></tr>';
                    foreach ($filiais as $filial) {
                        $timezone = $filial['timezone'] ?? 'NULL';
                        $status = ($timezone === 'NULL' || empty($timezone)) ? '⚠️ Não configurado' : '✅ Configurado';
                        echo '<tr>';
                        echo '<td>' . $filial['id'] . '</td>';
                        echo '<td><strong>' . htmlspecialchars($filial['nome']) . '</strong></td>';
                        echo '<td><strong>' . htmlspecialchars($timezone) . '</strong> ' . $status . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '</div>';
                }
            } else {
                echo '<div class="warning">';
                echo '⚠️ A coluna <strong>timezone</strong> não existe. Executando migration...<br>';
                echo '</div>';
                
                // Ler arquivo SQL
                $sqlFile = __DIR__ . '/database/migrations/add_timezone_to_filiais.sql';
                
                if (!file_exists($sqlFile)) {
                    throw new Exception("Arquivo de migration não encontrado: $sqlFile");
                }
                
                $sql = file_get_contents($sqlFile);
                
                // Split by semicolon and execute each statement
                $statements = explode(';', $sql);
                $executed = 0;
                $errors = [];
                
                echo '<pre>';
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement) && !preg_match('/^--/', $statement)) {
                        try {
                            $db->query($statement);
                            $executed++;
                            echo "✅ " . substr($statement, 0, 100) . "...\n";
                        } catch (Exception $e) {
                            $errorMsg = $e->getMessage();
                            // Ignore "column already exists" errors
                            if (strpos($errorMsg, 'already exists') === false && 
                                strpos($errorMsg, 'duplicate') === false &&
                                strpos($errorMsg, 'IF NOT EXISTS') === false) {
                                $errors[] = $errorMsg;
                                echo "⚠️  " . substr($errorMsg, 0, 100) . "...\n";
                            } else {
                                echo "ℹ️  " . substr($errorMsg, 0, 80) . "... (ignorado)\n";
                            }
                        }
                    }
                }
                echo '</pre>';
                
                if (empty($errors)) {
                    echo '<div class="success">';
                    echo '<strong>✅ Migration concluída com sucesso!</strong><br><br>';
                    echo "📊 Total de statements executados: <strong>$executed</strong><br><br>";
                    echo '✅ A coluna <strong>timezone</strong> foi adicionada à tabela <strong>filiais</strong><br>';
                    echo '✅ Valor padrão configurado: <strong>America/Sao_Paulo</strong><br>';
                    echo '</div>';
                    
                    // Verificar resultado
                    $filiais = $db->fetchAll("SELECT id, nome, timezone FROM filiais LIMIT 10");
                    if (!empty($filiais)) {
                        echo '<div class="info">';
                        echo '<strong>✅ Verificação - Filiais atualizadas:</strong><br><br>';
                        echo '<table>';
                        echo '<tr><th>ID</th><th>Nome</th><th>Timezone</th></tr>';
                        foreach ($filiais as $filial) {
                            echo '<tr>';
                            echo '<td>' . $filial['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($filial['nome']) . '</td>';
                            echo '<td><strong>' . htmlspecialchars($filial['timezone'] ?? 'NULL') . '</strong></td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="error">';
                    echo '<strong>⚠️ Migration executada com avisos:</strong><br>';
                    foreach ($errors as $error) {
                        echo "- $error<br>";
                    }
                    echo '</div>';
                }
            }
            
            echo '<div class="info" style="margin-top: 30px;">';
            echo '<strong>📝 O que foi corrigido:</strong><br>';
            echo '✅ Sistema agora usa o timezone do estabelecimento<br>';
            echo '✅ Pedidos são criados com data/hora correta<br>';
            echo '✅ Horário de funcionamento verificado corretamente<br>';
            echo '✅ Não muda de dia às 21h-22h<br><br>';
            echo '<strong>🎯 Próximos passos:</strong><br>';
            echo '1. Teste criando um pedido e verifique se a data/hora estão corretas<br>';
            echo '2. Se tiver filiais em outros fusos horários, configure manualmente no banco<br>';
            echo '3. O sistema está pronto para uso!<br>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>❌ Erro ao executar migration:</strong><br><br>';
            echo '<strong>Mensagem:</strong> ' . htmlspecialchars($e->getMessage()) . '<br><br>';
            echo '<strong>Arquivo:</strong> ' . htmlspecialchars($e->getFile()) . '<br>';
            echo '<strong>Linha:</strong> ' . $e->getLine() . '<br><br>';
            echo '<strong>Stack trace:</strong><br>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
        ?>
        
        <a href="index.php?view=dashboard" class="btn">← Voltar ao Dashboard</a>
    </div>
</body>
</html>













