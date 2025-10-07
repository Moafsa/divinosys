<?php
// Script para forçar a cópia do arquivo pedidos.php para o container
echo "Forçando cópia do arquivo pedidos.php...\n";

// Executar comando docker cp
$command = 'docker cp mvc/ajax/pedidos.php divino-lanches-app:/var/www/html/mvc/ajax/pedidos.php';
$output = shell_exec($command . ' 2>&1');

echo "Comando executado: $command\n";
echo "Saída: $output\n";

if ($output === null) {
    echo "ERRO: Não foi possível executar o comando\n";
} else {
    echo "SUCESSO: Arquivo copiado para o container\n";
}

// Verificar se o arquivo foi copiado
$checkCommand = 'docker exec divino-lanches-app ls -la /var/www/html/mvc/ajax/pedidos.php';
$checkOutput = shell_exec($checkCommand . ' 2>&1');

echo "\nVerificando se o arquivo existe no container:\n";
echo "Comando: $checkCommand\n";
echo "Saída: $checkOutput\n";

if (strpos($checkOutput, 'pedidos.php') !== false) {
    echo "SUCESSO: Arquivo encontrado no container\n";
} else {
    echo "ERRO: Arquivo não encontrado no container\n";
}
?>
