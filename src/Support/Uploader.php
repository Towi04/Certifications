<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Uploader
{
    private const ALLOWED_EXT = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'svg',
    ];

    private const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        'application/pdf',
    ];

    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     */
    public static function store(array $file, string $subdir = 'assets'): string
    {
        [$tmp, $ext, $original] = self::validateUpload($file);

        $base = dirname(__DIR__, 2) . '/storage/uploads/' . trim($subdir, '/');
        if (!is_dir($base) && !mkdir($base, 0755, true) && !is_dir($base)) {
            throw new RuntimeException('No se pudo crear la carpeta de uploads.');
        }

        $safeName = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($original));
        // Normalizar extensión de imágenes redimensionadas a png/jpg
        $dest = $base . '/' . $safeName;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('No se pudo guardar el archivo.');
        }

        return 'uploads/' . trim($subdir, '/') . '/' . $safeName;
    }

    /**
     * Guarda imagen y la redimensiona (máx. ancho/alto) para no romper el UI.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     */
    public static function storeImage(array $file, string $subdir = 'assets', int $maxWidth = 800, int $maxHeight = 800): string
    {
        [$tmp, $ext] = self::validateUpload($file);
        if (!in_array($ext, self::IMAGE_EXT, true)) {
            throw new RuntimeException('Solo se permiten imágenes JPG, PNG, GIF o WEBP.');
        }

        $base = dirname(__DIR__, 2) . '/storage/uploads/' . trim($subdir, '/');
        if (!is_dir($base) && !mkdir($base, 0755, true) && !is_dir($base)) {
            throw new RuntimeException('No se pudo crear la carpeta de uploads.');
        }

        $outExt = in_array($ext, ['png', 'gif', 'webp'], true) ? 'png' : 'jpg';
        $safeName = bin2hex(random_bytes(8)) . '_img.' . $outExt;
        $dest = $base . '/' . $safeName;

        if (!self::resizeImageToFile($tmp, $dest, $maxWidth, $maxHeight, $outExt)) {
            // Fallback: guardar original si GD no está disponible
            if (!move_uploaded_file($tmp, $dest)) {
                // tmp may still be readable
                if (!@copy($tmp, $dest)) {
                    throw new RuntimeException('No se pudo guardar la imagen.');
                }
            }
        }

        return 'uploads/' . trim($subdir, '/') . '/' . $safeName;
    }

    public static function absolutePath(string $relative): ?string
    {
        $relative = str_replace('\\', '/', $relative);
        $relative = ltrim($relative, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }
        if (!str_starts_with($relative, 'uploads/')) {
            return null;
        }

        $full = dirname(__DIR__, 2) . '/storage/' . $relative;
        $realBase = realpath(dirname(__DIR__, 2) . '/storage/uploads');
        $realFile = realpath($full);
        if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase)) {
            return null;
        }

        return $realFile;
    }

    public static function delete(string $relative): void
    {
        $path = self::absolutePath($relative);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     * @return array{0:string,1:string,2:string} tmp, ext, original
     */
    private static function validateUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir el archivo (código ' . (int) ($file['error'] ?? 0) . ').');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $original = (string) ($file['name'] ?? 'file');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new RuntimeException('Archivo de subida inválido.');
        }

        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            throw new RuntimeException('Extensión no permitida. Usa: ' . implode(', ', self::ALLOWED_EXT));
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Tipo MIME no permitido: ' . $mime);
        }

        if ((int) ($file['size'] ?? 0) > 8 * 1024 * 1024) {
            throw new RuntimeException('El archivo supera 8 MB.');
        }

        return [$tmp, $ext, $original];
    }

    private static function resizeImageToFile(string $source, string $dest, int $maxW, int $maxH, string $outExt): bool
    {
        if (!function_exists('imagecreatetruecolor')) {
            return false;
        }

        $info = @getimagesize($source);
        if ($info === false) {
            return false;
        }

        [$w, $h, $type] = $info;
        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_GIF => @imagecreatefromgif($source),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : false,
            default => false,
        };
        if ($src === false) {
            return false;
        }

        $scale = min($maxW / max($w, 1), $maxH / max($h, 1), 1.0);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        if ($dst === false) {
            imagedestroy($src);
            return false;
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $ok = $outExt === 'png'
            ? imagepng($dst, $dest, 6)
            : imagejpeg($dst, $dest, 85);

        imagedestroy($src);
        imagedestroy($dst);

        return (bool) $ok;
    }
}
