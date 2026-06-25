<?php

namespace System;

class FiadoClienteService
{
    public static function findByTelefone(Database $db, string $telefone, int $tenantId): ?array
    {
        $variacoes = TelefoneHelper::getVariacoes($telefone);
        if (empty($variacoes)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($variacoes), '?'));
        $params = array_merge([$tenantId], $variacoes);
        $sufixo = TelefoneHelper::chaveAgrupamento($telefone);
        $sufixoSql = '';
        if (strlen($sufixo) >= 8) {
            $sufixoSql = " OR RIGHT(REGEXP_REPLACE(COALESCE(telefone, ''), '[^0-9]', '', 'g'), 8) = ?";
            $params[] = $sufixo;
        }

        return $db->fetch(
            "SELECT * FROM clientes_fiado
             WHERE tenant_id = ?
               AND (
                    REGEXP_REPLACE(COALESCE(telefone, ''), '[^0-9]', '', 'g') IN ({$placeholders})
                    {$sufixoSql}
               )
             ORDER BY saldo_devedor DESC,
                      CASE WHEN nome ILIKE 'Cliente Mesa%' THEN 1
                           WHEN nome ILIKE 'Cliente N??o Identificado%' THEN 1
                           ELSE 0 END,
                      id ASC
             LIMIT 1",
            $params
        ) ?: null;
    }

    public static function findOrCreate(
        Database $db,
        int $tenantId,
        int $filialId,
        string $nome,
        string $telefone,
        ?int $usuarioGlobalId = null
    ): int {
        $telefoneBusca = $telefone;
        if ($telefoneBusca === '' && $usuarioGlobalId) {
            $ug = $db->fetch('SELECT nome, telefone FROM usuarios_globais WHERE id = ?', [$usuarioGlobalId]);
            if ($ug) {
                $telefoneBusca = (string) ($ug['telefone'] ?? '');
                if (trim($nome) === '' || stripos($nome, 'Cliente Mesa') === 0) {
                    $nome = (string) ($ug['nome'] ?? $nome);
                }
            }
        }

        $existente = self::findByTelefone($db, $telefoneBusca, $tenantId);
        if ($existente) {
            $updates = [];
            $canonico = TelefoneHelper::canonico($telefoneBusca);
            if ($canonico !== '' && ($existente['telefone'] ?? '') !== $canonico) {
                $updates['telefone'] = $canonico;
            }
            $nomeAtual = trim((string) ($existente['nome'] ?? ''));
            $nomeNovo = trim($nome);
            if ($nomeNovo !== '' && $nomeNovo !== $nomeAtual) {
                $nomeGenerico = $nomeAtual === ''
                    || stripos($nomeAtual, 'Cliente Mesa') === 0
                    || stripos($nomeAtual, 'Cliente N??o Identificado') === 0;
                if ($nomeGenerico) {
                    $updates['nome'] = $nomeNovo;
                }
            }
            if (!empty($updates)) {
                $updates['updated_at'] = date('Y-m-d H:i:s');
                $db->update('clientes_fiado', $updates, 'id = ?', [(int) $existente['id']]);
            }
            return (int) $existente['id'];
        }

        $canonico = TelefoneHelper::canonico($telefoneBusca);
        return (int) $db->insert('clientes_fiado', [
            'nome' => trim($nome) !== '' ? trim($nome) : 'Cliente N??o Identificado',
            'telefone' => $canonico !== '' ? $canonico : $telefoneBusca,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'saldo_devedor' => 0,
            'status' => 'ativo',
        ]);
    }

    public static function registrarVendaFiada(
        Database $db,
        int $clienteFiadoId,
        int $pedidoId,
        float $valor,
        int $tenantId,
        int $filialId,
        ?string $dataBase = null
    ): void {
        $existente = $db->fetch(
            'SELECT id FROM vendas_fiadas WHERE pedido_id = ? AND tenant_id = ?',
            [$pedidoId, $tenantId]
        );
        if ($existente) {
            return;
        }

        $dataBase = $dataBase ?: date('Y-m-d H:i:s');
        $db->insert('vendas_fiadas', [
            'cliente_id' => $clienteFiadoId,
            'pedido_id' => $pedidoId,
            'valor_total' => $valor,
            'status' => 'pendente',
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'data_vencimento' => date('Y-m-d', strtotime($dataBase . ' + 30 days')),
        ]);

        $db->query(
            'UPDATE clientes_fiado SET saldo_devedor = saldo_devedor + ? WHERE id = ?',
            [$valor, $clienteFiadoId]
        );
    }

    /** Unifica registros duplicados em clientes_fiado pelo telefone (mesmo tenant). */
    public static function unificarDuplicados(Database $db, int $tenantId): int
    {
        $clientes = $db->fetchAll(
            "SELECT * FROM clientes_fiado WHERE tenant_id = ? AND telefone IS NOT NULL AND telefone != ''",
            [$tenantId]
        );

        $grupos = [];
        foreach ($clientes as $c) {
            $chave = TelefoneHelper::chaveAgrupamento($c['telefone']);
            if ($chave === '') {
                continue;
            }
            $grupos[$chave][] = $c;
        }

        $unificados = 0;
        foreach ($grupos as $lista) {
            if (count($lista) <= 1) {
                continue;
            }

            usort($lista, static function ($a, $b) {
                $score = static function ($row) {
                    $s = (float) ($row['saldo_devedor'] ?? 0);
                    if ($s > 0) {
                        $s += 1000;
                    }
                    if (!empty($row['cpf_cnpj'])) {
                        $s += 10;
                    }
                    $nome = trim((string) ($row['nome'] ?? ''));
                    if ($nome !== '' && stripos($nome, 'Cliente Mesa') !== 0) {
                        $s += 5;
                    }
                    return $s;
                };
                $diff = $score($b) <=> $score($a);
                return $diff !== 0 ? $diff : ((int) $a['id'] <=> (int) $b['id']);
            });

            $principal = array_shift($lista);
            $principalId = (int) $principal['id'];
            $canonico = TelefoneHelper::canonico($principal['telefone']);

            foreach ($lista as $dup) {
                $dupId = (int) $dup['id'];
                $db->query(
                    'UPDATE vendas_fiadas SET cliente_id = ? WHERE cliente_id = ?',
                    [$principalId, $dupId]
                );
                $db->query(
                    'UPDATE clientes_fiado SET saldo_devedor = saldo_devedor + ? WHERE id = ?',
                    [(float) ($dup['saldo_devedor'] ?? 0), $principalId]
                );
                $db->delete('clientes_fiado', 'id = ?', [$dupId]);
                $unificados++;
            }

            if ($canonico !== '' && ($principal['telefone'] ?? '') !== $canonico) {
                $db->update('clientes_fiado', ['telefone' => $canonico], 'id = ?', [$principalId]);
            }
        }

        return $unificados;
    }
}
<?php

namespace System;

class FiadoClienteService
{
    public static function findByTelefone(Database $db, string $telefone, int $tenantId): ?array
    {
        $variacoes = TelefoneHelper::getVariacoes($telefone);
        if (empty($variacoes)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($variacoes), '?'));
        $params = array_merge([$tenantId], $variacoes);
        $sufixo = TelefoneHelper::chaveAgrupamento($telefone);
        $sufixoSql = '';
        if (strlen($sufixo) >= 8) {
            $sufixoSql = " OR RIGHT(REGEXP_REPLACE(COALESCE(telefone, ''), '[^0-9]', '', 'g'), 8) = ?";
            $params[] = $sufixo;
        }

        return $db->fetch(
            "SELECT * FROM clientes_fiado
             WHERE tenant_id = ?
               AND (
                    REGEXP_REPLACE(COALESCE(telefone, ''), '[^0-9]', '', 'g') IN ({$placeholders})
                    {$sufixoSql}
               )
             ORDER BY saldo_devedor DESC,
                      CASE WHEN nome ILIKE 'Cliente Mesa%' THEN 1
                           WHEN nome ILIKE 'Cliente N??o Identificado%' THEN 1
                           ELSE 0 END,
                      id ASC
             LIMIT 1",
            $params
        ) ?: null;
    }

    public static function findOrCreate(
        Database $db,
        int $tenantId,
        int $filialId,
        string $nome,
        string $telefone,
        ?int $usuarioGlobalId = null
    ): int {
        $telefoneBusca = $telefone;
        if ($telefoneBusca === '' && $usuarioGlobalId) {
            $ug = $db->fetch('SELECT nome, telefone FROM usuarios_globais WHERE id = ?', [$usuarioGlobalId]);
            if ($ug) {
                $telefoneBusca = (string) ($ug['telefone'] ?? '');
                if (trim($nome) === '' || stripos($nome, 'Cliente Mesa') === 0) {
                    $nome = (string) ($ug['nome'] ?? $nome);
                }
            }
        }

        $existente = self::findByTelefone($db, $telefoneBusca, $tenantId);
        if ($existente) {
            $updates = [];
            $canonico = TelefoneHelper::canonico($telefoneBusca);
            if ($canonico !== '' && ($existente['telefone'] ?? '') !== $canonico) {
                $updates['telefone'] = $canonico;
            }
            $nomeAtual = trim((string) ($existente['nome'] ?? ''));
            $nomeNovo = trim($nome);
            if ($nomeNovo !== '' && $nomeNovo !== $nomeAtual) {
                $nomeGenerico = $nomeAtual === ''
                    || stripos($nomeAtual, 'Cliente Mesa') === 0
                    || stripos($nomeAtual, 'Cliente N??o Identificado') === 0;
                if ($nomeGenerico) {
                    $updates['nome'] = $nomeNovo;
                }
            }
            if (!empty($updates)) {
                $updates['updated_at'] = date('Y-m-d H:i:s');
                $db->update('clientes_fiado', $updates, 'id = ?', [(int) $existente['id']]);
            }
            return (int) $existente['id'];
        }

        $canonico = TelefoneHelper::canonico($telefoneBusca);
        return (int) $db->insert('clientes_fiado', [
            'nome' => trim($nome) !== '' ? trim($nome) : 'Cliente N??o Identificado',
            'telefone' => $canonico !== '' ? $canonico : $telefoneBusca,
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'saldo_devedor' => 0,
            'status' => 'ativo',
        ]);
    }

    public static function registrarVendaFiada(
        Database $db,
        int $clienteFiadoId,
        int $pedidoId,
        float $valor,
        int $tenantId,
        int $filialId,
        ?string $dataBase = null
    ): void {
        $existente = $db->fetch(
            'SELECT id FROM vendas_fiadas WHERE pedido_id = ? AND tenant_id = ?',
            [$pedidoId, $tenantId]
        );
        if ($existente) {
            return;
        }

        $dataBase = $dataBase ?: date('Y-m-d H:i:s');
        $db->insert('vendas_fiadas', [
            'cliente_id' => $clienteFiadoId,
            'pedido_id' => $pedidoId,
            'valor_total' => $valor,
            'status' => 'pendente',
            'tenant_id' => $tenantId,
            'filial_id' => $filialId,
            'data_vencimento' => date('Y-m-d', strtotime($dataBase . ' + 30 days')),
        ]);

        $db->query(
            'UPDATE clientes_fiado SET saldo_devedor = saldo_devedor + ? WHERE id = ?',
            [$valor, $clienteFiadoId]
        );
    }

    /** Unifica registros duplicados em clientes_fiado pelo telefone (mesmo tenant). */
    public static function unificarDuplicados(Database $db, int $tenantId): int
    {
        $clientes = $db->fetchAll(
            "SELECT * FROM clientes_fiado WHERE tenant_id = ? AND telefone IS NOT NULL AND telefone != ''",
            [$tenantId]
        );

        $grupos = [];
        foreach ($clientes as $c) {
            $chave = TelefoneHelper::chaveAgrupamento($c['telefone']);
            if ($chave === '') {
                continue;
            }
            $grupos[$chave][] = $c;
        }

        $unificados = 0;
        foreach ($grupos as $lista) {
            if (count($lista) <= 1) {
                continue;
            }

            usort($lista, static function ($a, $b) {
                $score = static function ($row) {
                    $s = (float) ($row['saldo_devedor'] ?? 0);
                    if ($s > 0) {
                        $s += 1000;
                    }
                    if (!empty($row['cpf_cnpj'])) {
                        $s += 10;
                    }
                    $nome = trim((string) ($row['nome'] ?? ''));
                    if ($nome !== '' && stripos($nome, 'Cliente Mesa') !== 0) {
                        $s += 5;
                    }
                    return $s;
                };
                $diff = $score($b) <=> $score($a);
                return $diff !== 0 ? $diff : ((int) $a['id'] <=> (int) $b['id']);
            });

            $principal = array_shift($lista);
            $principalId = (int) $principal['id'];
            $canonico = TelefoneHelper::canonico($principal['telefone']);

            foreach ($lista as $dup) {
                $dupId = (int) $dup['id'];
                $db->query(
                    'UPDATE vendas_fiadas SET cliente_id = ? WHERE cliente_id = ?',
                    [$principalId, $dupId]
                );
                $db->query(
                    'UPDATE clientes_fiado SET saldo_devedor = saldo_devedor + ? WHERE id = ?',
                    [(float) ($dup['saldo_devedor'] ?? 0), $principalId]
                );
                $db->delete('clientes_fiado', 'id = ?', [$dupId]);
                $unificados++;
            }

            if ($canonico !== '' && ($principal['telefone'] ?? '') !== $canonico) {
                $db->update('clientes_fiado', ['telefone' => $canonico], 'id = ?', [$principalId]);
            }
        }

        return $unificados;
    }
}
