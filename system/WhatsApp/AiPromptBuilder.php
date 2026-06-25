<?php

namespace System\WhatsApp;

class AiPromptBuilder
{
    public static function defaultConfig(?string $businessName = null): array
    {
        return [
            'assistant_name' => 'Assistente',
            'business_name' => $businessName ?: 'nosso restaurante',
            'tone' => 'amigavel',
            'custom_instructions' => '',
            'ignore_stock' => false,
        ];
    }

    public static function normalizeConfig($raw, ?string $fallbackBusiness = null): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($raw)) {
            $raw = [];
        }

        foreach ($raw as $key => $value) {
            if ($key === 'ignore_stock') {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                unset($raw[$key]);
            }
        }

        $merged = array_merge(self::defaultConfig($fallbackBusiness), $raw);
        if (array_key_exists('ignore_stock', $raw)) {
            $merged['ignore_stock'] = filter_var($raw['ignore_stock'], FILTER_VALIDATE_BOOLEAN);
        }

        return $merged;
    }

    public static function build(array $config): string
    {
        $config = self::normalizeConfig($config);
        $name = trim((string) $config['assistant_name']);
        $business = trim((string) $config['business_name']);
        $tone = (string) ($config['tone'] ?? 'amigavel');
        $custom = trim((string) ($config['custom_instructions'] ?? ''));

        $toneMap = [
            'amigavel' => 'Tom amig??vel, acolhedor e pr??ximo ??? como um atendente simp??tico da casa.',
            'formal' => 'Tom formal e profissional, sempre educado e respeitoso.',
            'descontraido' => 'Tom leve e descontra??do, com linguagem casual mas respeitosa.',
            'profissional' => 'Tom profissional e objetivo, focado em resolver o pedido do cliente.',
        ];

        $prompt = "IDENTIDADE: Voc?? se chama {$name} e atende clientes pelo WhatsApp do {$business}.\n";
        $prompt .= 'TOM DE ATENDIMENTO: ' . ($toneMap[$tone] ?? $toneMap['amigavel']) . "\n";
        $prompt .= "REGRAS DE ATENDIMENTO:\n";
        $prompt .= "- Cumprimente pelo nome quando souber.\n";
        $prompt .= "- N??o diga que ?? 'Gerente Geral do DivinoSys' nem mencione sistemas internos.\n";
        $prompt .= "- Para card??pio, pre??os e promo????es, consulte as ferramentas ??? nunca invente valores.\n";
        $prompt .= "- Seja ??til com pedidos, hor??rios, promo????es e d??vidas sobre o restaurante.\n";

        if (!empty($config['ignore_stock'])) {
            $prompt .= "- ESTOQUE DESATIVADO: ignore quantidades em estoque ao informar produtos e ao lan??ar pedidos. N??o bloqueie itens por falta de estoque.\n";
        } else {
            $prompt .= "- ESTOQUE ATIVO: respeite o estoque ao informar disponibilidade e ao criar pedidos. N??o ofere??a nem lance produtos sem estoque suficiente.\n";
        }

        if ($custom !== '') {
            $prompt .= "\nINSTRU????ES ADICIONAIS DO ESTABELECIMENTO:\n" . $custom;
        }

        return $prompt;
    }

    public static function buildFromInstance(?array $instance, ?string $fallbackBusiness = null): string
    {
        $config = self::normalizeConfig($instance['ai_config'] ?? null, $fallbackBusiness);
        return self::build($config);
    }

    public static function ignoresStock(?array $instance): bool
    {
        $config = self::normalizeConfig($instance['ai_config'] ?? null);
        return !empty($config['ignore_stock']);
    }
}
