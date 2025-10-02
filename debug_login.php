<?php
// Script para debug do login - pode ser executado via URL
header('Content-Type: text/plain');

try {
    // Conectar ao banco diretamente
    $pdo = new PDO(
        'pgsql:host=postgres;port=5432;dbname=divino_lanches',
        'postgres',
        'divino_password'
    );
    
    echo "=== DEBUG DO LOGIN ===\n\n";
    
    // Buscar usuário admin
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE login = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ Usuário admin não encontrado!\n";
        exit;
    }
    
    echo "✅ Usuário admin encontrado!\n";
    echo "Login: " . $user['login'] . "\n";
    echo "Hash armazenado: " . $user['senha'] . "\n";
    echo "Tamanho do hash: " . strlen($user['senha']) . " caracteres\n\n";
    
    // Testar diferentes senhas
    $test_passwords = ['admin', 'admin123', 'password', '123456'];
    
    echo "=== TESTANDO DIFERENTES SENHAS ===\n";
    foreach ($test_passwords as $password) {
        $result = password_verify($password, $user['senha']);
        echo "Senha '{$password}': " . ($result ? '✅ CORRETA' : '❌ INCORRETA') . "\n";
    }
    
    echo "\n=== GERANDO NOVO HASH PARA admin123 ===\n";
    $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
    echo "Novo hash: " . $new_hash . "\n";
    $test_new = password_verify('admin123', $new_hash);
    echo "Teste com novo hash: " . ($test_new ? '✅ FUNCIONA' : '❌ NÃO FUNCIONA') . "\n";
    
    echo "\n=== COMANDO SQL PARA ATUALIZAR ===\n";
    echo "UPDATE usuarios SET senha = '{$new_hash}' WHERE login = 'admin';\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
