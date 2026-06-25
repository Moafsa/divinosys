<?php

namespace System\WhatsApp;

class WhatsAppOrderDraft
{
    public static function empty(): array
    {
        return [
            'mesa_id' => null,
            'endereco' => '',
            'observacao' => '',
            'taxa_entrega' => 0.0,
            'forma_pagamento' => '',
            'troco_para' => null,
            'pedido_id' => null,
            'cpf' => '',
            'pending_payment' => '',
            'itens' => [],
        ];
    }

    public static function normalize($raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return self::empty();
        }

        return [
            'mesa_id' => $raw['mesa_id'] ?? null,
            'endereco' => (string) ($raw['endereco'] ?? ''),
            'observacao' => (string) ($raw['observacao'] ?? ''),
            'taxa_entrega' => (float) ($raw['taxa_entrega'] ?? 0),
            'forma_pagamento' => (string) ($raw['forma_pagamento'] ?? ''),
            'troco_para' => isset($raw['troco_para']) ? (float) $raw['troco_para'] : null,
            'pedido_id' => isset($raw['pedido_id']) ? (int) $raw['pedido_id'] : null,
            'cpf' => (string) ($raw['cpf'] ?? ''),
            'pending_payment' => (string) ($raw['pending_payment'] ?? ''),
            'itens' => is_array($raw['itens'] ?? null) ? $raw['itens'] : [],
        ];
    }

    public static function addItem(array $draft, array $item): array
    {
        $id = (int) ($item['id'] ?? 0);
        if ($id <= 0) {
            return $draft;
        }

        $qty = max(1, (int) ($item['quantidade'] ?? 1));
        $preco = (float) ($item['preco'] ?? $item['preco_efetivo'] ?? 0);
        $nome = (string) ($item['nome'] ?? '');

        foreach ($draft['itens'] as &$existing) {
            if ((int) ($existing['id'] ?? 0) === $id) {
                $existing['quantidade'] = (int) ($existing['quantidade'] ?? 1) + $qty;
                $existing['preco'] = $preco;
                if ($nome !== '') {
                    $existing['nome'] = $nome;
                }
                return $draft;
            }
        }
        unset($existing);

        $draft['itens'][] = [
            'id' => $id,
            'nome' => $nome,
            'quantidade' => $qty,
            'preco' => $preco,
            'observacao' => (string) ($item['observacao'] ?? ''),
            'tamanho' => (string) ($item['tamanho'] ?? 'normal'),
        ];

        return $draft;
    }

    public static function isReadyForConfirmation(array $draft): bool
    {
        if (empty($draft['itens']) || !empty($draft['pedido_id'])) {
            return false;
        }
        if (empty($draft['mesa_id'])) {
            return false;
        }
        if (($draft['mesa_id'] ?? '') === '999' && empty($draft['endereco'])) {
            return false;
        }

        return true;
    }

    public static function isAwaitingPayment(array $draft): bool
    {
        return !empty($draft['pedido_id']) && empty($draft['forma_pagamento']);
    }

    public static function subtotal(array $draft): float
    {
        $total = 0.0;
        foreach ($draft['itens'] as $item) {
            $total += (float) ($item['preco'] ?? 0) * (int) ($item['quantidade'] ?? 1);
        }

        return round($total, 2);
    }

    public static function total(array $draft): float
    {
        $total = self::subtotal($draft);
        if (($draft['mesa_id'] ?? '') === '999') {
            $total += (float) ($draft['taxa_entrega'] ?? 0);
        }

        return round($total, 2);
    }

    public static function summaryText(array $draft): string
    {
        if (empty($draft['itens'])) {
            return 'Nenhum item anotado ainda.';
        }

        $lines = [];
        foreach ($draft['itens'] as $item) {
            $qtd = (int) ($item['quantidade'] ?? 1);
            $nome = (string) ($item['nome'] ?? 'Item');
            $preco = (float) ($item['preco'] ?? 0);
            $lines[] = sprintf('%dx %s — R$ %s', $qtd, $nome, number_format($preco * $qtd, 2, ',', '.'));
        }

        $isDelivery = ($draft['mesa_id'] ?? '') === '999';
        $lines[] = 'Modalidade: ' . ($isDelivery ? 'Delivery' : (($draft['mesa_id'] ?? '') === '998' ? 'Retirada no balcão' : 'Não definida'));

        if ($isDelivery) {
            if (!empty($draft['endereco'])) {
                $lines[] = 'Endereço: ' . $draft['endereco'];
            }
            if ((float) ($draft['taxa_entrega'] ?? 0) > 0) {
                $lines[] = 'Taxa de entrega: R$ ' . number_format((float) $draft['taxa_entrega'], 2, ',', '.');
            }
        }

        $lines[] = 'Subtotal: R$ ' . number_format(self::subtotal($draft), 2, ',', '.');
        $lines[] = 'Total: R$ ' . number_format(self::total($draft), 2, ',', '.');

        return implode("\n", $lines);
    }

    public static function toCreateOrderPayload(array $draft, string $cliente): array
    {
        $itens = [];
        foreach ($draft['itens'] as $item) {
            $itens[] = [
                'id' => (int) $item['id'],
                'quantidade' => (int) ($item['quantidade'] ?? 1),
                'preco' => (float) ($item['preco'] ?? 0),
                'observacao' => (string) ($item['observacao'] ?? ''),
                'tamanho' => (string) ($item['tamanho'] ?? 'normal'),
            ];
        }

        $obs = [];
        if (($draft['mesa_id'] ?? '') === '999') {
            if (!empty($draft['endereco'])) {
                $obs[] = 'Delivery — Endereço: ' . $draft['endereco'];
            }
            if ((float) ($draft['taxa_entrega'] ?? 0) > 0) {
                $obs[] = 'Taxa de entrega: R$ ' . number_format((float) $draft['taxa_entrega'], 2, ',', '.');
            }
        }
        if (!empty($draft['observacao'])) {
            $obs[] = $draft['observacao'];
        }
        if (!empty($draft['forma_pagamento'])) {
            $obs[] = 'Pagamento: ' . $draft['forma_pagamento'];
            if ($draft['forma_pagamento'] === 'dinheiro' && !empty($draft['troco_para'])) {
                $obs[] = 'Troco para: R$ ' . number_format((float) $draft['troco_para'], 2, ',', '.');
            }
        }

        return [
            'mesa_id' => (string) ($draft['mesa_id'] ?? '998'),
            'cliente' => $cliente,
            'delivery' => ($draft['mesa_id'] ?? '') === '999',
            'taxa_entrega' => (float) ($draft['taxa_entrega'] ?? 0),
            'forma_pagamento' => (string) ($draft['forma_pagamento'] ?? ''),
            'observacao' => implode("\n", $obs) ?: 'Pedido via WhatsApp',
            'itens' => $itens,
            'valor_total' => self::total($draft),
        ];
    }
}
