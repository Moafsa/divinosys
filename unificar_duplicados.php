<?php
require_once __DIR__ . '/system/Config.php';
require_once __DIR__ . '/system/Database.php';
require_once __DIR__ . '/system/Session.php';

try {
    $session = \System\Session::getInstance();
    
    // Check if user is logged in
    $userData = $session->getUser();
    if (!$userData || !isset($userData['id'])) {
        die("Acesso negado. Você precisa estar logado.");
    }
    
    // Some systems store role in user array, or in session
    // Just ensuring they are logged in is mostly enough for a one-off script,
    // but if isAdmin exists we use it, otherwise assume valid if logged in.
    if (method_exists($session, 'isAdmin') && !$session->isAdmin()) {
        die("Acesso negado. Apenas administradores.");
    }

    $db = \System\Database::getInstance();
    
    echo "<pre>Buscando todos os clientes...\n";
    $clientes = $db->fetchAll("SELECT * FROM usuarios_globais WHERE tipo_usuario = 'cliente' OR tipo_usuario IS NULL OR tipo_usuario = ''");
    
    $normalizarTelefone = function ($telefone) {
        $telNormalizado = preg_replace('/[^0-9]/', '', (string)$telefone);
        if (empty($telNormalizado)) return [];
        $base = $telNormalizado;
        if (str_starts_with($base, '55') && strlen($base) >= 12) {
            $base = substr($base, 2);
        }
        $variacoes = [$base, '55' . $base];
        if (strlen($base) === 11 && $base[2] === '9') {
            $semNove = substr($base, 0, 2) . substr($base, 3);
            $variacoes[] = $semNove;
            $variacoes[] = '55' . $semNove;
        } elseif (strlen($base) === 10) {
            $comNove = substr($base, 0, 2) . '9' . substr($base, 2);
            $variacoes[] = $comNove;
            $variacoes[] = '55' . $comNove;
        }
        return array_values(array_unique(array_filter($variacoes)));
    };

    // Agrupar por variação
    $grupos = [];
    foreach ($clientes as $c) {
        if (empty($c['telefone'])) continue;
        
        $variacoes = $normalizarTelefone($c['telefone']);
        if (empty($variacoes)) continue;
        
        // Pega a versão base de 10/11 digitos sem 55 como chave do grupo
        $chave = null;
        foreach ($variacoes as $v) {
            if (!str_starts_with($v, '55')) {
                $chave = $v;
                // Prefer length 10 as standard key
                if (strlen($v) === 10) break;
            }
        }
        if (!$chave) $chave = $variacoes[0];
        
        $grupos[$chave][] = $c;
    }

    $duplicadosEncontrados = 0;
    
    foreach ($grupos as $chave => $lista) {
        if (count($lista) <= 1) continue;
        
        $duplicadosEncontrados++;
        echo "\nGrupo de telefone {$chave} tem " . count($lista) . " cadastros:\n";
        
        // Ordena para que o principal seja o mais "completo", ou o mais recente.
        // Vamos dar pontos para quem tem email, cpf. Se igual, ID menor (mais antigo) ganha.
        usort($lista, function($a, $b) {
            $pontosA = (!empty($a['email']) ? 1 : 0) + (!empty($a['cpf']) ? 1 : 0);
            $pontosB = (!empty($b['email']) ? 1 : 0) + (!empty($b['cpf']) ? 1 : 0);
            if ($pontosA !== $pontosB) return $pontosB - $pontosA; // Descending
            return $a['id'] - $b['id']; // Ascending by ID
        });
        
        $principal = $lista[0];
        echo " -> PRINCIPAL: ID {$principal['id']} - Nome: {$principal['nome']} - Email: {$principal['email']} - CPF: {$principal['cpf']} - Tel: {$principal['telefone']}\n";
        
        $idsParaRemover = [];
        for ($i = 1; $i < count($lista); $i++) {
            $secundario = $lista[$i];
            echo "   -> SECUNDARIO: ID {$secundario['id']} - Nome: {$secundario['nome']}\n";
            $idsParaRemover[] = $secundario['id'];
        }
        
        $db->beginTransaction();
        
        foreach ($idsParaRemover as $idAntigo) {
            $db->query("UPDATE pedido SET usuario_global_id = ? WHERE usuario_global_id = ?", [$principal['id'], $idAntigo]);
            $db->query("UPDATE pagamentos_pedido SET usuario_global_id = ? WHERE usuario_global_id = ?", [$principal['id'], $idAntigo]);
            $db->query("UPDATE cliente_historico SET usuario_global_id = ? WHERE usuario_global_id = ?", [$principal['id'], $idAntigo]);
            $db->query("UPDATE clientes_enderecos SET usuario_global_id = ? WHERE usuario_global_id = ?", [$principal['id'], $idAntigo]);
            $db->query("UPDATE clientes_preferencias SET usuario_global_id = ? WHERE usuario_global_id = ?", [$principal['id'], $idAntigo]);
            
            // Delete the duplicate
            $db->query("DELETE FROM usuarios_globais WHERE id = ?", [$idAntigo]);
            
            echo "      [Merge Concluido] Movidos dados do ID {$idAntigo} para ID {$principal['id']}\n";
        }
        
        $db->commit();
    }
    
    if ($duplicadosEncontrados === 0) {
        echo "\nNenhum cliente duplicado encontrado!\n";
    } else {
        echo "\nFinalizado! Total de {$duplicadosEncontrados} grupos de clientes unificados.\n";
    }

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    echo "ERRO: " . $e->getMessage() . "\n";
}
