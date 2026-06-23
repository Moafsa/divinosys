<?php
// cron_estoque.php
// Script para rodar relatórios de estoque baixo e enviar para o WhatsApp do Administrador
// Sugestão de Cron (Linux): 0 14,23 * * * /usr/bin/php /caminho/para/cron_estoque.php
// No Windows: Agendador de Tarefas disparando "php cron_estoque.php" às 14:00 e 23:00

require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/WuzapiService.php';

try {
    $db = \System\Database::getInstance();
    $wuzapi = new \System\WuzapiService();

    echo "Iniciando verificação de estoque baixo...\n";

    // Buscar todos os tenants ativos para poder separar o envio por loja (se houver múltiplas)
    $tenants = $db->fetchAll("SELECT id, nome, telefone FROM tenants WHERE ativo = true");

    if (empty($tenants)) {
        echo "Nenhum tenant (loja) ativo encontrado.\n";
        exit;
    }

    foreach ($tenants as $tenant) {
        $tenantId = $tenant['id'];
        
        // Se o tenant não tiver telefone configurado, busca o primeiro administrador
        $telefoneAdmin = preg_replace('/[^0-9]/', '', $tenant['telefone'] ?? '');
        
        if (empty($telefoneAdmin)) {
            // Tenta buscar o telefone de um usuário administrador
            $admin = $db->fetch("SELECT telefone FROM usuarios WHERE tenant_id = ? AND nivel = 'admin' AND telefone IS NOT NULL LIMIT 1", [$tenantId]);
            if ($admin && !empty($admin['telefone'])) {
                $telefoneAdmin = preg_replace('/[^0-9]/', '', $admin['telefone']);
            }
        }

        if (empty($telefoneAdmin)) {
            echo "Tenant '{$tenant['nome']}' não possui telefone cadastrado nem admin com telefone. Pulando...\n";
            continue;
        }

        // Buscar produtos com baixo estoque para este tenant
        $sqlEstoque = "
            SELECT p.id, p.nome, e.estoque_atual, e.estoque_minimo
            FROM produtos p
            JOIN estoque e ON p.id = e.produto_id AND e.tenant_id = p.tenant_id AND e.filial_id = p.filial_id
            WHERE p.tenant_id = ? AND p.ativo = true
            AND (e.estoque_atual <= e.estoque_minimo OR e.estoque_atual IS NULL)
            ORDER BY COALESCE(e.estoque_atual, 0) - COALESCE(e.estoque_minimo, 0) ASC
        ";
        
        $produtosBaixoEstoque = $db->fetchAll($sqlEstoque, [$tenantId]);

        if (!empty($produtosBaixoEstoque)) {
            $msg = "⚠️ *Alerta de Estoque Baixo* ⚠️\n";
            $msg .= "Olá! Este é um relatório automático do sistema.\n\n";
            $msg .= "Os seguintes produtos precisam de reposição:\n\n";

            foreach ($produtosBaixoEstoque as $produto) {
                $atual = $produto['estoque_atual'] ?? 0;
                $minimo = $produto['estoque_minimo'] ?? 0;
                $msg .= "📦 *{$produto['nome']}*\n";
                $msg .= "Atual: {$atual} | Mínimo: {$minimo}\n\n";
            }

            $msg .= "Acesse o sistema para mais detalhes.";

            // Envia a mensagem
            try {
                $wuzapi->sendMessage($telefoneAdmin, $msg);
                echo "Relatório enviado para o WhatsApp {$telefoneAdmin} (Tenant: {$tenant['nome']}).\n";
            } catch (\Exception $e) {
                echo "Erro ao enviar WhatsApp para {$telefoneAdmin}: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Tenant '{$tenant['nome']}': Todos os produtos estão com estoque adequado.\n";
        }
    }

    echo "Verificação de estoque finalizada com sucesso.\n";

} catch (\Exception $e) {
    echo "Erro na execução do cron de estoque: " . $e->getMessage() . "\n";
}
