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

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     */
    public static function store(array $file, string $subdir = 'assets'): string
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

        $base = dirname(__DIR__, 2) . '/storage/uploads/' . trim($subdir, '/');
        if (!is_dir($base) && !mkdir($base, 0755, true) && !is_dir($base)) {
            throw new RuntimeException('No se pudo crear la carpeta de uploads.');
        }

        $safeName = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($original));
        $dest = $base . '/' . $safeName;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('No se pudo guardar el archivo.');
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
}
