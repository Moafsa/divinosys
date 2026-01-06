<?php
/**
 * Script para visualizar logs do sistema
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Logs do Sistema</h1>";
echo "<style>
    body { font-family: monospace; margin: 20px; background: #f5f5f5; }
    pre { background: #fff; padding: 15px; border-radius: 5px; border: 1px solid #ddd; overflow-x: auto; }
    .log-section { margin: 20px 0; }
    h2 { color: #333; }
</style>";

// Verificar logs do PHP
$phpErrorLog = ini_get('error_log');
if ($phpErrorLog && file_exists($phpErrorLog)) {
    echo "<div class='log-section'>";
    echo "<h2>PHP Error Log: $phpErrorLog</h2>";
    $lines = file($phpErrorLog);
    $recentLines = array_slice($lines, -100);
    echo "<pre>" . htmlspecialchars(implode('', $recentLines)) . "</pre>";
    echo "</div>";
} else {
    echo "<p>PHP error_log não encontrado ou não configurado.</p>";
}

// Verificar logs da aplicação
$appLog = __DIR__ . '/logs/app.log';
if (file_exists($appLog)) {
    echo "<div class='log-section'>";
    echo "<h2>App Log</h2>";
    $lines = file($appLog);
    $recentLines = array_slice($lines, -100);
    echo "<pre>" . htmlspecialchars(implode('', $recentLines)) . "</pre>";
    echo "</div>";
}

// Buscar logs relacionados a phone_auth
echo "<div class='log-section'>";
echo "<h2>Últimas 50 linhas com 'phone_auth' ou 'validateAccessCode'</h2>";
echo "<pre>";

$allLogs = [];
if (file_exists($appLog)) {
    $allLogs = array_merge($allLogs, file($appLog));
}
if ($phpErrorLog && file_exists($phpErrorLog)) {
    $allLogs = array_merge($allLogs, file($phpErrorLog));
}

$filtered = array_filter($allLogs, function($line) {
    return stripos($line, 'phone_auth') !== false || 
           stripos($line, 'validateAccessCode') !== false ||
           stripos($line, 'selectedEstablishment') !== false ||
           stripos($line, 'tipo_usuario') !== false ||
           stripos($line, 'accessType') !== false;
});

$recent = array_slice($filtered, -50);
echo htmlspecialchars(implode('', $recent));
echo "</pre>";
echo "</div>";

echo "<p><a href='?refresh=1'>Atualizar</a> | <a href='index.php?view=login'>Voltar ao Login</a></p>";
?>













