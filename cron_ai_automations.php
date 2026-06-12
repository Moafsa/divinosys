<?php
// cron_ai_automations.php
// Script para rodar as automações de cobrança, abandono e saudade
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/WuzapiService.php';

$db = \System\Database::getInstance();
$wuzapi = new \System\WuzapiService();

echo "Iniciando execucao do Cron de Automacoes IA...\n";

// 1. Cobranças de Fiados
$fiadosParaCobrar = $db->fetchAll(
    "SELECT *, telefone as wpp 
     FROM clientes_fiado
     WHERE cobranca_automatica = true AND saldo_devedor > 0"
);

foreach ($fiadosParaCobrar as $fiado) {
    // Verifica frequência
    $hoje = date('w'); // 0 = Domingo, 1 = Seg, 2 = Ter...
    $freq = $fiado['cobranca_frequencia'];
    
    $deveCobrar = false;
    if ($freq == 'diario') $deveCobrar = true;
    if ($freq == 'semanal' && $hoje == 1) $deveCobrar = true; // Toda segunda
    if ($freq == 'quinzenal' && (date('d') == '05' || date('d') == '20')) $deveCobrar = true;
    if ($freq == 'mensal' && date('d') == '05') $deveCobrar = true;

    if ($deveCobrar && !empty($fiado['wpp'])) {
        $msg = "Olá {$fiado['nome']}! Aqui é a assistente do Divino Lanches. Notamos que você tem um saldo pendente de R$ " . number_format($fiado['saldo_devedor'], 2, ',', '.') . ". Gostaríamos de saber quando você pode acertar. Um abraço!";
        $wuzapi->sendMessage($fiado['wpp'], $msg);
        echo "Cobranca enviada para {$fiado['nome']}\n";
    }
}

// 2. Mensagens de Saudade
$automacoesSaudade = $db->fetchAll("SELECT * FROM ai_automations WHERE tipo = 'saudade' AND ativo = true");
foreach ($automacoesSaudade as $auto) {
    $dias = (int)$auto['tempo_espera'];
    $dataCorte = date('Y-m-d', strtotime("-$dias days"));
    
    // Busca clientes que o último pedido foi há exatamente $dias
    $clientesSaudade = $db->fetchAll(
        "SELECT c.id, c.nome, c.telefone, MAX(p.data_pedido) as ultimo_pedido
         FROM clientes c
         JOIN pedidos p ON p.cliente_id = c.id
         WHERE c.tenant_id = ?
         GROUP BY c.id, c.nome, c.telefone
         HAVING MAX(p.data_pedido)::date = ?",
        [$auto['tenant_id'], $dataCorte]
    );

    foreach ($clientesSaudade as $cliente) {
        if (!empty($cliente['telefone'])) {
            $msg = str_replace('{nome}', $cliente['nome'], $auto['mensagem_template']);
            $wuzapi->sendMessage($cliente['telefone'], $msg);
            echo "Saudade enviada para {$cliente['nome']}\n";
        }
    }
}

echo "Cron finalizado.\n";
