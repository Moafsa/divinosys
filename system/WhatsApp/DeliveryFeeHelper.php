<?php

namespace System\WhatsApp;

class DeliveryFeeHelper
{
    public static function getConfig($db, int $tenantId, int $filialId): array
    {
        $filial = $db->fetch(
            'SELECT taxa_delivery_fixa, usar_calculo_distancia FROM filiais WHERE id = ? AND tenant_id = ?',
            [$filialId, $tenantId]
        );

        $bairros = $db->fetchAll(
            'SELECT bairro, taxa FROM taxa_entrega_bairros
             WHERE tenant_id = ? AND filial_id = ? AND ativo = true
             ORDER BY bairro ASC',
            [$tenantId, $filialId]
        ) ?: [];

        return [
            'taxa_fixa' => (float) ($filial['taxa_delivery_fixa'] ?? 0),
            'usar_calculo_distancia' => !empty($filial['usar_calculo_distancia']),
            'bairros' => $bairros,
        ];
    }

    public static function calculateFee($db, int $tenantId, int $filialId, string $endereco): array
    {
        $config = self::getConfig($db, $tenantId, $filialId);
        $enderecoLower = mb_strtolower(trim($endereco));

        if ($enderecoLower === '') {
            return [
                'success' => false,
                'message' => 'Endereço de entrega é obrigatório para delivery.',
            ];
        }

        $taxa = (float) $config['taxa_fixa'];
        $bairroEncontrado = null;

        foreach ($config['bairros'] as $row) {
            $bairro = mb_strtolower(trim((string) ($row['bairro'] ?? '')));
            if ($bairro !== '' && str_contains($enderecoLower, $bairro)) {
                $taxa = (float) $row['taxa'];
                $bairroEncontrado = $row['bairro'];
                break;
            }
        }

        if ($taxa <= 0 && !empty($config['bairros'])) {
            return [
                'success' => false,
                'message' => 'Não encontrei o bairro no endereço. Informe rua, número e bairro. Bairros atendidos: ' .
                    implode(', ', array_column($config['bairros'], 'bairro')),
                'bairros' => $config['bairros'],
            ];
        }

        return [
            'success' => true,
            'taxa' => round($taxa, 2),
            'bairro' => $bairroEncontrado,
            'taxa_fixa' => (float) $config['taxa_fixa'],
            'bairros' => $config['bairros'],
        ];
    }

    public static function bairrosTexto($db, int $tenantId, int $filialId): string
    {
        $config = self::getConfig($db, $tenantId, $filialId);
        if (empty($config['bairros'])) {
            return 'Taxa fixa de entrega: R$ ' . number_format($config['taxa_fixa'], 2, ',', '.');
        }

        $lines = [];
        foreach ($config['bairros'] as $b) {
            $lines[] = $b['bairro'] . ' — R$ ' . number_format((float) $b['taxa'], 2, ',', '.');
        }

        return "Taxas por bairro:\n" . implode("\n", $lines);
    }
}
