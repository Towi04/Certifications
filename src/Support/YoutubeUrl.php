<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Normaliza enlaces de YouTube para guardarlos y embeberlos.
 */
final class YoutubeUrl
{
    private const ID_PATTERN = '[A-Za-z0-9_-]{11}';

    public static function isYoutubeAssetType(string $assetType): bool
    {
        return $assetType === 'youtube';
    }

    public static function looksLikeUrl(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://'));
    }

    /**
     * Extrae el ID de video (11 chars) o null.
     */
    public static function videoId(string $urlOrId): ?string
    {
        $raw = trim($urlOrId);
        if ($raw === '') {
            return null;
        }
        // Guion al final del set para que no forme rango inválido.
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $raw) === 1) {
            return $raw;
        }

        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{11})~i',
            '~youtube\.com/embed/([A-Za-z0-9_-]{11})~i',
            '~youtube\.com/shorts/([A-Za-z0-9_-]{11})~i',
            '~youtube\.com/live/([A-Za-z0-9_-]{11})~i',
            '~youtube\.com/watch\?[^#]*\bv=([A-Za-z0-9_-]{11})~i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $raw, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    /** URL canónica para guardar en file_path. */
    public static function normalize(string $urlOrId): string
    {
        $id = self::videoId($urlOrId);
        if ($id === null) {
            throw new \RuntimeException(
                'Enlace de YouTube no válido. Usa un URL completo (youtube.com o youtu.be).'
            );
        }

        return 'https://www.youtube.com/watch?v=' . $id;
    }

    public static function embedUrl(string $urlOrId): ?string
    {
        $id = self::videoId($urlOrId);

        return $id !== null ? 'https://www.youtube.com/embed/' . $id . '?rel=0' : null;
    }

    public static function thumbnailUrl(string $urlOrId): ?string
    {
        $id = self::videoId($urlOrId);

        return $id !== null ? 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg' : null;
    }
}
