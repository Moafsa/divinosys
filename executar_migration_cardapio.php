<?php
/**
 * Script to execute migration for exibir_cardapio_online column
 * Access via browser: http://localhost:8080/executar_migration_cardapio.php
 */

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executar Migration - Exibir Cardápio Online</title>
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
            margin-bottom: 20px;
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
        .info {
            color: #004085;
            background: #cce5ff;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Executar Migration - Exibir Cardápio Online</h1>
        
        <?php
        try {
            $db = \System\Database::getInstance();
            
            echo '<div class="info">';
            echo '<strong>Iniciando migration...</strong><br>';
            echo 'Adicionando coluna <code>exibir_cardapio_online</code> à tabela <code>produtos</code>';
            echo '</div>';
            
            // Check if column already exists
            $columnCheck = $db->fetch("
                SELECT 1 
                FROM information_schema.columns 
                WHERE table_schema = 'public' 
                  AND table_name = 'produtos' 
                  AND column_name = 'exibir_cardapio_online'
                LIMIT 1
            ");
            
            if ($columnCheck) {
                echo '<div class="info">';
                echo '✅ A coluna <code>exibir_cardapio_online</code> já existe na tabela produtos.';
                echo '</div>';
            } else {
                // Add column
                $db->query("ALTER TABLE produtos ADD COLUMN IF NOT EXISTS exibir_cardapio_online BOOLEAN DEFAULT true");
                echo '<div class="success">';
                echo '✅ Coluna <code>exibir_cardapio_online</code> adicionada com sucesso!';
                echo '</div>';
            }
            
            // Update existing products
            $updated = $db->query("UPDATE produtos SET exibir_cardapio_online = true WHERE exibir_cardapio_online IS NULL");
            $rowCount = $updated->rowCount();
            
            if ($rowCount > 0) {
                echo '<div class="success">';
                echo "✅ {$rowCount} produto(s) atualizado(s) para exibir no cardápio por padrão.";
                echo '</div>';
            }
            
            // Add comment (may fail, but that's ok)
            try {
                $db->query("COMMENT ON COLUMN produtos.exibir_cardapio_online IS 'Controls if product should be displayed on online menu page'");
                echo '<div class="success">';
                echo '✅ Comentário adicionado à coluna.';
                echo '</div>';
            } catch (\Exception $e) {
                // Comment may fail, but that's ok
                echo '<div class="info">';
                echo '⚠️ Comentário não foi adicionado (não crítico).';
                echo '</div>';
            }
            
            echo '<div class="success" style="margin-top: 20px;">';
            echo '<strong>✅ Migration executada com sucesso!</strong><br>';
            echo 'Agora você pode usar o checkbox "Exibir no Cardápio Online" ao criar ou editar produtos.';
            echo '</div>';
            
        } catch (\Exception $e) {
            echo '<div class="error">';
            echo '<strong>❌ Erro ao executar migration:</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            echo '</div>';
        }
        ?>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p><a href="index.php?view=gerenciar_produtos">← Voltar para Produtos</a></p>
        </div>
    </div>
</body>
</html>

