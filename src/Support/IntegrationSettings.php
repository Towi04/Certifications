<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\Env;

final class IntegrationSettings
{
    /**
     * Resumen de configuración de integraciones (sin secretos).
     *
     * @return list<array{group:string,key:string,label:string,configured:bool,hint:string}>
     */
    public static function checklist(): array
    {
        return [
            self::row('App', 'APP_URL', 'URL pública', true, Env::get('APP_URL', '') ?? ''),
            self::row('App', 'APP_KEY', 'Clave de app', Env::isFilled('APP_KEY'), 'Cadena aleatoria larga'),
            self::row('MariaDB', 'DB_HOST', 'Host DB', Env::isFilled('DB_HOST'), Env::get('DB_HOST', '') ?? ''),
            self::row('MariaDB', 'DB_NAME', 'Base de datos', Env::isFilled('DB_NAME'), Env::get('DB_NAME', '') ?? ''),
            self::row('MariaDB', 'DB_USER', 'Usuario DB', Env::isFilled('DB_USER'), Env::get('DB_USER', '') ?? ''),
            self::row('MariaDB', 'DB_PASS', 'Password DB', Env::isFilled('DB_PASS'), 'Solo en .env del servidor'),
            self::row('Moodle', 'MOODLE_URL', 'URL webservice', Env::isFilled('MOODLE_URL'), Env::get('MOODLE_URL', '') ?? ''),
            self::row('Moodle', 'MOODLE_TOKEN', 'Token WS', Env::isFilled('MOODLE_TOKEN'), 'No se muestra'),
            self::row('OpenPay', 'OPENPAY_MERCHANT_ID', 'Merchant ID', Env::isFilled('OPENPAY_MERCHANT_ID'), self::mask(Env::get('OPENPAY_MERCHANT_ID'))),
            self::row('OpenPay', 'OPENPAY_PUBLIC_KEY', 'Llave pública', Env::isFilled('OPENPAY_PUBLIC_KEY'), self::mask(Env::get('OPENPAY_PUBLIC_KEY'))),
            self::row('OpenPay', 'OPENPAY_PRIVATE_KEY', 'Llave privada', Env::isFilled('OPENPAY_PRIVATE_KEY'), 'No se muestra'),
            self::row('OpenPay', 'OPENPAY_SANDBOX', 'Sandbox', true, Env::getBool('OPENPAY_SANDBOX', true) ? 'true' : 'false'),
            self::row('SMTP', 'SMTP_HOST', 'Host correo', Env::isFilled('SMTP_HOST'), Env::get('SMTP_HOST', '') ?? ''),
            self::row('SMTP', 'SMTP_USER', 'Usuario SMTP', Env::isFilled('SMTP_USER'), Env::get('SMTP_USER', '') ?? ''),
            self::row('SMTP', 'SMTP_PASS', 'Password SMTP', Env::isFilled('SMTP_PASS'), 'No se muestra'),
            self::row('SMTP', 'SMTP_FROM', 'From', Env::isFilled('SMTP_FROM'), Env::get('SMTP_FROM', '') ?? ''),
            self::row('Admin', 'ADMIN_EMAIL', 'Email admin bootstrap', Env::isFilled('ADMIN_EMAIL'), Env::get('ADMIN_EMAIL', '') ?? ''),
            self::row('Admin', 'ADMIN_RESET_PASSWORD', 'Reset password activo', true, Env::getBool('ADMIN_RESET_PASSWORD', false) ? 'true (cámbialo a false)' : 'false'),
        ];
    }

    /** @return array{group:string,key:string,label:string,configured:bool,hint:string} */
    private static function row(string $group, string $key, string $label, bool $configured, string $hint): array
    {
        return [
            'group' => $group,
            'key' => $key,
            'label' => $label,
            'configured' => $configured,
            'hint' => $hint,
        ];
    }

    private static function mask(?string $value): string
    {
        if ($value === null || $value === '') {
            return '(vacío)';
        }
        $len = strlen($value);
        if ($len <= 8) {
            return str_repeat('•', $len);
        }

        return substr($value, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($value, -4);
    }
}
