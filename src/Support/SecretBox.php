<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\Env;
use RuntimeException;

/**
 * Cifrado simétrico para secretos admin (p. ej. contraseñas de portales de proveedores).
 * Formato: v1:base64(iv[12] + tag[16] + ciphertext)
 */
final class SecretBox
{
    public static function encrypt(string $plaintext): string
    {
        $key = self::key();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false || $tag === '') {
            throw new RuntimeException('No se pudo cifrar el secreto.');
        }

        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }
        if (!str_starts_with($payload, 'v1:')) {
            // Compatibilidad: valores legacy en texto plano (no deberían existir).
            return $payload;
        }

        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Secreto cifrado inválido.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('No se pudo descifrar el secreto. ¿Cambió APP_KEY?');
        }

        return $plain;
    }

    private static function key(): string
    {
        $appKey = Env::get('APP_KEY', '') ?? '';
        if (strlen($appKey) < 16) {
            throw new RuntimeException('Define una APP_KEY larga en el .env para guardar contraseñas.');
        }

        return hash('sha256', $appKey, true);
    }
}
