<?php
/**
 * Script para executar a migration de timezone via web
 * Acesse: index.php?view=run_timezone_migration
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../index.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration de Timezone</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .success {
            color: #28a745;
            background: #d4edda;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            color: #856404;
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            color: #004085;
            background: #cce5ff;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 4px solid #007bff;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Migration de Timezone</h1>
        
        <?php
        try {
            $db = \System\Database::getInstance();
            
            // Verificar se a coluna já existe
            $checkColumn = $db->fetch("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_name = 'filiais' 
                AND column_name = 'timezone'
            ");
            
            if ($checkColumn) {
                echo '<div class="info">';
                echo '✅ A coluna <strong>timezone</strong> já existe na tabela <strong>filiais</strong>.<br>';
                echo 'Verificando valores...';
                echo '</div>';
                
                // Verificar valores
                $filiais = $db->fetchAll("SELECT id, nome, timezone FROM filiais");
                
                if (empty($filiais)) {
                    echo '<div class="warning">Nenhuma filial encontrada no banco de dados.</div>';
                } else {
                    echo '<div class="success">';
                    echo '<strong>Filiais encontradas:</strong><br><br>';
                    echo '<table style="width:100%; border-collapse: collapse;">';
                    echo '<tr style="background: #f8f9fa;"><th style="padding:10px; text-align:left; border:1px solid #ddd;">ID</th><th style="padding:10px; text-align:left; border:1px solid #ddd;">Nome</th><th style="padding:10px; text-align:left; border:1px solid #ddd;">Timezone</th></tr>';
                    foreach ($filiais as $filial) {
                        $timezone = $filial['timezone'] ?? 'NULL';
                        $color = ($timezone === 'NULL' || empty($timezone)) ? '#fff3cd' : '#d4edda';
                        echo '<tr style="background: ' . $color . ';">';
                        echo '<td style="padding:10px; border:1px solid #ddd;">' . $filial['id'] . '</td>';
                        echo '<td style="padding:10px; border:1px solid #ddd;">' . htmlspecialchars($filial['nome']) . '</td>';
                        echo '<td style="padding:10px; border:1px solid #ddd;"><strong>' . htmlspecialchars($timezone) . '</strong></td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '</div>';
                }
            } else {
                echo '<div class="info">Executando migration...</div>';
                
                // Ler arquivo SQL
                $sqlFile = __DIR__ . '/../../database/migrations/add_timezone_to_filiais.sql';
                
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
                            echo "✅ Executado: " . substr($statement, 0, 80) . "...\n";
                        } catch (Exception $e) {
                            $errorMsg = $e->getMessage();
                            // Ignore "column already exists" errors
                            if (strpos($errorMsg, 'already exists') === false && 
                                strpos($errorMsg, 'duplicate') === false &&
                                strpos($errorMsg, 'IF NOT EXISTS') === false) {
                                $errors[] = $errorMsg;
                                echo "⚠️  Aviso: " . $errorMsg . "\n";
                            } else {
                                echo "ℹ️  Info: " . $errorMsg . " (ignorado)\n";
                            }
                        }
                    }
                }
                echo '</pre>';
                
                if (empty($errors)) {
                    echo '<div class="success">';
                    echo '<strong>✅ Migration concluída com sucesso!</strong><br>';
                    echo "Total de statements executados: $executed<br><br>";
                    echo 'A coluna <strong>timezone</strong> foi adicionada à tabela <strong>filiais</strong> com valor padrão <strong>America/Sao_Paulo</strong>.';
                    echo '</div>';
                    
                    // Verificar resultado
                    $filiais = $db->fetchAll("SELECT id, nome, timezone FROM filiais LIMIT 5");
                    if (!empty($filiais)) {
                        echo '<div class="info">';
                        echo '<strong>Verificação:</strong><br>';
                        foreach ($filiais as $filial) {
                            echo "Filial #{$filial['id']} ({$filial['nome']}): " . ($filial['timezone'] ?? 'NULL') . "<br>";
                        }
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
            echo '<strong>📝 Próximos passos:</strong><br>';
            echo '1. O sistema agora usa o timezone do estabelecimento automaticamente<br>';
            echo '2. Se necessário, configure timezone específico por filial no banco de dados<br>';
            echo '3. Teste criando um pedido e verifique se a data/hora estão corretas<br>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>❌ Erro ao executar migration:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '<br><br>';
            echo '<strong>Stack trace:</strong><br>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
        ?>
        
        <a href="index.php?view=dashboard" class="btn">← Voltar ao Dashboard</a>
    </div>
</body>
</html>













