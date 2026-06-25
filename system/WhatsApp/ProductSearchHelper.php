<?php

namespace System\WhatsApp;

class ProductSearchHelper
{
    public static function search($db, int $tenantId, int $filialId, string $query, int $limit = 10, bool $ignoreStock = false): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['success' => false, 'message' => 'Termo de busca vazio'];
        }

        $query = self::expandQuery($query);

        $sql = "SELECT id, nome, preco_normal, preco_promocional, em_promocao, estoque_atual, ativo
                FROM produtos
                WHERE tenant_id = ? AND filial_id = ? AND COALESCE(ativo, true) = true
                  AND nome ILIKE ?
                ORDER BY
                    CASE WHEN nome ILIKE ? THEN 0 ELSE 1 END,
                    LENGTH(nome) ASC
                LIMIT ?";

        $produtos = $db->fetchAll($sql, [
            $tenantId,
            $filialId,
            '%' . $query . '%',
            $query,
            $limit,
        ]);

        if (empty($produtos)) {
            $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($words as $word) {
                if (strlen($word) < 3) {
                    continue;
                }
                $produtos = $db->fetchAll($sql, [
                    $tenantId,
                    $filialId,
                    '%' . $word . '%',
                    $word,
                    $limit,
                ]);
                if (!empty($produtos)) {
                    break;
                }
            }
        }

        if (empty($produtos)) {
            return ['success' => true, 'message' => 'Nenhum produto encontrado com esse nome.', 'produtos' => []];
        }

        $formatted = [];
        foreach ($produtos as $p) {
            $emPromo = !empty($p['em_promocao']) && (float) ($p['preco_promocional'] ?? 0) > 0;
            $precoEfetivo = $emPromo ? (float) $p['preco_promocional'] : (float) $p['preco_normal'];
            $formatted[] = [
                'id' => (int) $p['id'],
                'nome' => $p['nome'],
                'preco_normal' => (float) $p['preco_normal'],
                'preco_promocional' => (float) ($p['preco_promocional'] ?? 0),
                'em_promocao' => $emPromo,
                'preco_efetivo' => $precoEfetivo,
                'disponivel' => $ignoreStock ? true : ((float) ($p['estoque_atual'] ?? 0) > 0),
            ];
        }

        if ($ignoreStock) {
            return [
                'success' => true,
                'produtos' => $formatted,
                'ignore_stock' => true,
                'message' => 'Estoque desativado para este atendimento — todos os produtos podem ser vendidos.',
            ];
        }

        return ['success' => true, 'produtos' => $formatted];
    }

    private static function expandQuery(string $query): string
    {
        $q = mb_strtolower(trim($query));
        $shortcuts = [
            'lat' => 'coca lata',
            'lata' => 'coca lata',
            'ks' => 'coca ks',
            '600' => 'coca 600',
            '2l' => 'coca 2l',
            '2 l' => 'coca 2l',
            'zero' => 'coca zero',
        ];

        if (isset($shortcuts[$q])) {
            return $shortcuts[$q];
        }

        if (strlen($q) <= 4 && !str_contains($q, ' ')) {
            return 'coca ' . $q;
        }

        return $query;
    }
}
