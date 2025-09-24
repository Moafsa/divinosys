<?php
require_once 'system/Config.php';
require_once 'system/Database.php';

$db = \System\Database::getInstance();

echo "=== CORRIGINDO INGREDIENTES 'ARRAY' ===\n";

// Buscar todos os itens com ingredientes "Array"
$itens = $db->fetchAll("SELECT * FROM pedido_itens WHERE ingredientes_com = 'Array' OR ingredientes_sem = 'Array'");

echo "Encontrados " . count($itens) . " itens com ingredientes 'Array'\n";

foreach ($itens as $item) {
    echo "Corrigindo item ID: " . $item['id'] . "\n";
    
    $ingredientesCom = '';
    $ingredientesSem = '';
    
    if ($item['ingredientes_com'] === 'Array') {
        $ingredientesCom = '';
    } else {
        $ingredientesCom = $item['ingredientes_com'];
    }
    
    if ($item['ingredientes_sem'] === 'Array') {
        $ingredientesSem = '';
    } else {
        $ingredientesSem = $item['ingredientes_sem'];
    }
    
    // Atualizar o item
    $db->update(
        'pedido_itens',
        [
            'ingredientes_com' => $ingredientesCom,
            'ingredientes_sem' => $ingredientesSem
        ],
        'id = ?',
        [$item['id']]
    );
    
    echo "  - Ingredientes COM: '$ingredientesCom'\n";
    echo "  - Ingredientes SEM: '$ingredientesSem'\n";
}

echo "\n=== VERIFICAÇÃO FINAL ===\n";
$itensRestantes = $db->fetchAll("SELECT * FROM pedido_itens WHERE ingredientes_com = 'Array' OR ingredientes_sem = 'Array'");
echo "Itens restantes com 'Array': " . count($itensRestantes) . "\n";

echo "Correção concluída!\n";
?>
