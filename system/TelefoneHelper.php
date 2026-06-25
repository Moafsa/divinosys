<?php

namespace System;

class TelefoneHelper
{
    public static function getVariacoes($telefone): array
    {
        $telNormalizado = preg_replace('/[^0-9]/', '', (string) $telefone);
        if ($telNormalizado === '') {
            return [];
        }

        $base = $telNormalizado;
        if (str_starts_with($base, '55') && strlen($base) >= 12) {
            $base = substr($base, 2);
        }

        $variacoes = [$base, '55' . $base];

        if (strlen($base) === 11 && ($base[2] ?? '') === '9') {
            $semNove = substr($base, 0, 2) . substr($base, 3);
            $variacoes[] = $semNove;
            $variacoes[] = '55' . $semNove;
        } elseif (strlen($base) === 10 && ($base[2] ?? '') !== '9') {
            $comNove = substr($base, 0, 2) . '9' . substr($base, 2);
            $variacoes[] = $comNove;
            $variacoes[] = '55' . $comNove;
        }

        return array_values(array_unique(array_filter($variacoes)));
    }

    /** Chave para agrupar cadastros do mesmo telefone (??ltimos 8 d??gitos do celular). */
    public static function chaveAgrupamento($telefone): string
    {
        $digitos = preg_replace('/[^0-9]/', '', (string) $telefone);
        if ($digitos === '') {
            return '';
        }
        if (str_starts_with($digitos, '55') && strlen($digitos) > 11) {
            $digitos = substr($digitos, 2);
        }
        return strlen($digitos) >= 8 ? substr($digitos, -8) : $digitos;
    }

    /** Formato can??nico: 11 d??gitos com 9, sem c??digo do pa??s (ex: 54997092223) */
    public static function canonico($telefone): string
    {
        $variacoes = self::getVariacoes($telefone);
        if (empty($variacoes)) {
            return '';
        }

        foreach ($variacoes as $v) {
            if (!str_starts_with($v, '55') && strlen($v) === 11) {
                return $v;
            }
        }

        foreach ($variacoes as $v) {
            if (!str_starts_with($v, '55') && strlen($v) === 10 && ($v[2] ?? '') === '9') {
                return $v;
            }
        }

        foreach ($variacoes as $v) {
            if (!str_starts_with($v, '55') && strlen($v) === 10) {
                return substr($v, 0, 2) . '9' . substr($v, 2);
            }
        }

        $base = $variacoes[0];
        if (str_starts_with($base, '55') && strlen($base) > 2) {
            return substr($base, 2);
        }

        return $base;
    }

    public static function sqlMatchClause(string $column, array &$params): string
    {
        $placeholders = implode(',', array_fill(0, count($params), '?'));
        return "REGEXP_REPLACE(COALESCE({$column}, ''), '[^0-9]', '', 'g') IN ({$placeholders})";
    }
}
