<?php
// Teste simples para verificar se o AJAX está funcionando
header('Content-Type: application/json');

// Log dos dados recebidos
error_log("POST data: " . print_r($_POST, true));
error_log("GET data: " . print_r($_GET, true));

if (isset($_POST['action']) && $_POST['action'] === 'salvar_categoria') {
    echo json_encode([
        'success' => true, 
        'message' => 'Teste de categoria funcionando!',
        'dados_recebidos' => $_POST,
        'total_campos' => count($_POST)
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Ação não encontrada: ' . ($_POST['action'] ?? 'nenhuma'),
        'dados_recebidos' => $_POST,
        'total_campos' => count($_POST)
    ]);
}
?>
