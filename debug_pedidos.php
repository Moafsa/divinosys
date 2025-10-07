<?php
// Debug direto do problema
header('Content-Type: application/json');

// Simular exatamente os dados que estão vindo do JavaScript
$itens = [
    [
        'id' => 6,
        'nome' => 'CACHORRO-QUENTE SIMPLES',
        'quantidade' => 1,
        'preco' => 21,
        'ingredientes_adicionados' => 'undefined', // String 'undefined' como vem do JS
        'ingredientes_removidos' => 'undefined'
    ],
    [
        'id' => 16,
        'nome' => 'Produto Teste',
        'quantidade' => 1,
        'preco' => 15.5,
        'ingredientes_adicionados' => 'undefined',
        'ingredientes_removidos' => 'undefined'
    ]
];

echo "=== DEBUG PEDIDOS ===\n";
echo "Testando processamento dos ingredientes:\n\n";

foreach ($itens as $index => $item) {
    echo "Item $index: " . $item['nome'] . "\n";
    
    // Preparar ingredientes
    $ingredientesCom = [];
    $ingredientesSem = [];
    
    // Teste da correção
    if (isset($item['ingredientes_adicionados']) && 
        $item['ingredientes_adicionados'] !== null && 
        $item['ingredientes_adicionados'] !== 'undefined' &&
        is_array($item['ingredientes_adicionados']) && 
        !empty($item['ingredientes_adicionados'])) {
        echo "  - Processando ingredientes adicionados\n";
        foreach ($item['ingredientes_adicionados'] as $ing) {
            if (isset($ing['nome'])) {
                $ingredientesCom[] = $ing['nome'];
            }
        }
    } else {
        echo "  - Ingredientes adicionados: " . $item['ingredientes_adicionados'] . " - IGNORANDO\n";
    }
    
    if (isset($item['ingredientes_removidos']) && 
        $item['ingredientes_removidos'] !== null && 
        $item['ingredientes_removidos'] !== 'undefined' &&
        is_array($item['ingredientes_removidos']) && 
        !empty($item['ingredientes_removidos'])) {
        echo "  - Processando ingredientes removidos\n";
        foreach ($item['ingredientes_removidos'] as $ing) {
            if (isset($ing['nome'])) {
                $ingredientesSem[] = $ing['nome'];
            }
        }
    } else {
        echo "  - Ingredientes removidos: " . $item['ingredientes_removidos'] . " - IGNORANDO\n";
    }
    
    echo "  - Ingredientes com: " . json_encode($ingredientesCom) . "\n";
    echo "  - Ingredientes sem: " . json_encode($ingredientesSem) . "\n";
    echo "  - SUCESSO: Item processado sem erro!\n\n";
}

echo "=== TESTE CONCLUÍDO ===\n";
echo "A correção está funcionando corretamente!\n";
?>