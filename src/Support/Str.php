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

    /**
     * Normaliza una URL externa a http(s). Devuelve '' si está vacía o no es usable.
     * Evita que valores relativos (p. ej. "enlace_score_report") se resuelvan contra /alumno/.
     */
    public static function externalUrl(?string $value): string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $url) === 1) {
            if (str_starts_with($url, '//')) {
                $url = 'https:' . $url;
            }

            return $url;
        }
        // Dominio o host sin esquema (ej. results.itepexam.com/foo)
        if (preg_match('~^[a-z0-9][a-z0-9.-]+\.[a-z]{2,}([/:?].*)?$~i', $url) === 1) {
            return 'https://' . $url;
        }

        return '';
    }
}
