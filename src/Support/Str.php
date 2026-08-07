<?php

declare(strict_types=1);

namespace App\Support;

final class Str
{
    public static function slug(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-') ?: 'item';
    }

    public static function money(?float $amount, string $currency = 'MXN'): string
    {
        if ($amount === null) {
            return '—';
        }

        return '$' . number_format($amount, 2) . ' ' . $currency;
    }
}
