<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Helpers de presentación para product_assets (imagen / PDF / YouTube).
 */
final class ProductAssetView
{
    public static function isYoutube(array $asset): bool
    {
        return YoutubeUrl::isYoutubeAssetType((string) ($asset['asset_type'] ?? ''))
            || (YoutubeUrl::looksLikeUrl((string) ($asset['file_path'] ?? ''))
                && YoutubeUrl::videoId((string) ($asset['file_path'] ?? '')) !== null
                && (string) ($asset['asset_type'] ?? '') === 'youtube');
    }

    public static function isImage(array $asset): bool
    {
        if (self::isYoutube($asset)) {
            return false;
        }
        $path = (string) ($asset['file_path'] ?? '');

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $path);
    }

    public static function isPdf(array $asset): bool
    {
        if (self::isYoutube($asset)) {
            return false;
        }
        $path = (string) ($asset['file_path'] ?? '');

        return (bool) preg_match('/\.pdf$/i', $path);
    }

    /** ¿Entra al lightbox flotante (imagen o YouTube)? */
    public static function isLightboxable(array $asset): bool
    {
        return self::isYoutube($asset) || self::isImage($asset);
    }

    public static function mediaHref(array $asset): string
    {
        $path = (string) ($asset['file_path'] ?? '');
        if (self::isYoutube($asset)) {
            return YoutubeUrl::normalize($path);
        }

        return '/media?f=' . rawurlencode($path);
    }

    public static function thumbSrc(array $asset): ?string
    {
        if (self::isYoutube($asset)) {
            return YoutubeUrl::thumbnailUrl((string) ($asset['file_path'] ?? ''));
        }
        if (self::isImage($asset)) {
            return '/media?f=' . rawurlencode((string) $asset['file_path']);
        }

        return null;
    }

    /** @return array{type:string,src:string,title:string} */
    public static function lightboxPayload(array $asset): array
    {
        $title = trim((string) ($asset['title'] ?? '')) ?: (string) ($asset['asset_type'] ?? 'Visual');
        if (self::isYoutube($asset)) {
            $embed = YoutubeUrl::embedUrl((string) ($asset['file_path'] ?? '')) ?? '';

            return ['type' => 'youtube', 'src' => $embed, 'title' => $title];
        }

        return [
            'type' => 'image',
            'src' => '/media?f=' . rawurlencode((string) ($asset['file_path'] ?? '')),
            'title' => $title,
        ];
    }

    /** Etiquetas admin en español. */
    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'provider_logo' => 'Logo proveedor',
            'exam_logo' => 'Logo del examen',
            'course_logo' => 'Logo del curso',
            'certificate_sample' => 'Muestra de certificado',
            'badge' => 'Insignia / badge',
            'syllabus_pdf' => 'Syllabus (PDF)',
            'regulation_pdf' => 'Reglamento (PDF)',
            'cover' => 'Portada / imagen',
            'youtube' => 'Video YouTube',
            'other' => 'Otro',
            default => $type,
        };
    }
}
