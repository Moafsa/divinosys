<?php
/**
 * Script to normalize all phone numbers in usuarios_globais table
 * Removes formatting (parentheses, dashes, spaces) to ensure consistent searching
 */

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Normalização de Telefones</h1>";

try {
    $db = \System\Database::getInstance();
    
    // Get all clients with phone numbers
    $clientes = $db->fetchAll(
        "SELECT id, nome, telefone 
         FROM usuarios_globais 
         WHERE telefone IS NOT NULL 
         AND telefone != '' 
         ORDER BY id"
    );
    
    echo "<p>Encontrados " . count($clientes) . " clientes com telefone.</p>";
    
    $atualizados = 0;
    $semMudanca = 0;
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Nome</th><th>Telefone Original</th><th>Telefone Normalizado</th><th>Status</th></tr>";
    
    foreach ($clientes as $cliente) {
        $telefoneOriginal = $cliente['telefone'];
        $telefoneNormalizado = preg_replace('/[^0-9]/', '', $telefoneOriginal);
        
        // Only update if phone has formatting
        if ($telefoneOriginal !== $telefoneNormalizado) {
            $db->update(
                'usuarios_globais',
                ['telefone' => $telefoneNormalizado],
                'id = ?',
                [$cliente['id']]
            );
            
            echo "<tr style='color: green;'>";
            echo "<td>{$cliente['id']}</td>";
            echo "<td>{$cliente['nome']}</td>";
            echo "<td>$telefoneOriginal</td>";
            echo "<td>$telefoneNormalizado</td>";
            echo "<td>✓ Atualizado</td>";
            echo "</tr>";
            
            $atualizados++;
        } else {
            echo "<tr style='color: gray;'>";
            echo "<td>{$cliente['id']}</td>";
            echo "<td>{$cliente['nome']}</td>";
            echo "<td>$telefoneOriginal</td>";
            echo "<td>$telefoneNormalizado</td>";
            echo "<td>- Sem mudança</td>";
            echo "</tr>";
            
            $semMudanca++;
        }
    }
    
    echo "</table>";
    
    echo "<h2>Resumo</h2>";
    echo "<p style='color: green;'>✓ $atualizados telefones normalizados</p>";
    echo "<p style='color: gray;'>- $semMudanca telefones já estavam normalizados</p>";
    
    echo "<p><strong>Normalização concluída!</strong> Agora todos os telefones estão no formato numérico puro, facilitando as buscas.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>ERRO: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

?>




