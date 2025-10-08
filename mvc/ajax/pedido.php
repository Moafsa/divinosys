<?php
/**
 * Arquivo de compatibilidade - redireciona para pedidos.php
 * Mantido para evitar erros 404 em chamadas antigas
 */

// Redirecionar para pedidos.php mantendo todos os parâmetros
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$postData = file_get_contents('php://input');

// Se é uma requisição POST, reenviar os dados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Definir headers para POST
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($postData)
    ];
    
    // Preparar contexto para stream
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $postData
        ]
    ]);
    
    // Redirecionar internamente para pedidos.php
    $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/pedidos.php';
    if (!empty($queryString)) {
        $url .= '?' . $queryString;
    }
    
    // Capturar a saída
    $result = file_get_contents($url, false, $context);
    echo $result;
} else {
    // Para GET, fazer redirecionamento simples
    $url = 'pedidos.php';
    if (!empty($queryString)) {
        $url .= '?' . $queryString;
    }
    
    // Incluir o arquivo pedidos.php
    include $url;
}
?>
