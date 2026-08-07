<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Config\Env;

/**
 * Cliente SMTP mínimo (SSL/TLS) sin dependencias Composer.
 * Suficiente para pruebas de salud y correos simples en Neubox.
 */
final class Mailer
{
    public function send(string $to, string $subject, string $bodyText): void
    {
        $host = trim(Env::require('SMTP_HOST'));
        $port = (int) (Env::get('SMTP_PORT', '465') ?? '465');
        $user = trim(Env::require('SMTP_USER'));
        $pass = trim(Env::require('SMTP_PASS'));
        $from = trim(Env::get('SMTP_FROM', $user) ?? $user);
        $fromName = Env::get('SMTP_FROM_NAME', 'Instituto Doceo') ?? 'Instituto Doceo';
        $encryption = strtolower(trim(Env::get('SMTP_ENCRYPTION', 'ssl') ?? 'ssl'));

        $fp = $this->connect($host, $port, $encryption);

        try {
            $fp = $this->authenticate($fp, $user, $pass, $host, $port, $encryption);

            $this->command($fp, 'MAIL FROM:<' . $from . '>', 250);
            $this->command($fp, 'RCPT TO:<' . $to . '>', 250);
            $this->command($fp, 'DATA', 354);

            $headers = [
                'From: ' . $this->encodeAddress($fromName, $from),
                'To: <' . $to . '>',
                'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                'Date: ' . date('r'),
            ];

            $data = implode("\r\n", $headers) . "\r\n\r\n" . $this->dotStuff($bodyText) . "\r\n.";
            $this->command($fp, $data, 250);
            $this->command($fp, 'QUIT', 221);
        } finally {
            if (is_resource($fp)) {
                fclose($fp);
            }
        }
    }

    /**
     * @return resource
     */
    private function connect(string $host, int $port, string $encryption)
    {
        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
        if ($fp === false) {
            throw new \RuntimeException("No se pudo conectar a SMTP: {$errstr} ({$errno})");
        }

        stream_set_timeout($fp, 30);
        $this->expect($fp, 220);
        $this->command($fp, 'EHLO pdv.institutodoceo.com', 250);

        if ($encryption === 'tls') {
            $this->command($fp, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('No se pudo negociar STARTTLS.');
            }
            $this->command($fp, 'EHLO pdv.institutodoceo.com', 250);
        }

        return $fp;
    }

    /**
     * AUTH LOGIN; si falla con 535, reconecta e intenta AUTH PLAIN.
     *
     * @param resource $fp
     * @return resource Socket autenticado (puede ser uno nuevo tras reconexión)
     */
    private function authenticate($fp, string $user, string $pass, string $host, int $port, string $encryption)
    {
        try {
            $this->command($fp, 'AUTH LOGIN', 334);
            $this->command($fp, base64_encode($user), 334);
            $this->command($fp, base64_encode($pass), 235);

            return $fp;
        } catch (\RuntimeException $loginError) {
            if (!$this->isCredentialRejection($loginError->getMessage())) {
                throw $this->wrapAuthError($loginError);
            }
        }

        // Misma sesión puede quedar inutilizable tras 535: reconectar.
        $newFp = $this->connect($host, $port, $encryption);
        fclose($fp);
        $fp = $newFp;

        try {
            $plain = base64_encode("\0{$user}\0{$pass}");
            $this->command($fp, 'AUTH PLAIN ' . $plain, 235);
        } catch (\RuntimeException $plainError) {
            throw $this->wrapAuthError($plainError);
        }

        return $fp;
    }

    private function isCredentialRejection(string $message): bool
    {
        return str_contains($message, '535')
            || str_contains(strtolower($message), 'incorrect authentication')
            || str_contains(strtolower($message), 'authentication failed');
    }

    private function wrapAuthError(\RuntimeException $e): \RuntimeException
    {
        $detail = $e->getMessage();
        if ($this->isCredentialRejection($detail) || str_contains($detail, '535')) {
            return new \RuntimeException(
                'SMTP: autenticación rechazada (535). Revisa SMTP_USER y SMTP_PASS en el .env del servidor '
                . '(correo completo + contraseña exacta de cPanel → Cuentas de correo). '
                . 'Prueba también en /admin/salud → Probar SMTP. Detalle: ' . $detail,
                0,
                $e
            );
        }

        return $e;
    }

    private function encodeAddress(string $name, string $email): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }

    private function dotStuff(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);
        return preg_replace('/^\./m', '..', $body) ?? $body;
    }

    /** @param resource $fp */
    private function command($fp, string $command, int $expectCode): void
    {
        fwrite($fp, $command . "\r\n");
        $this->expect($fp, $expectCode);
    }

    /** @param resource $fp */
    private function expect($fp, int $expectCode): void
    {
        $response = '';
        while (($line = fgets($fp, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectCode) {
            throw new \RuntimeException("SMTP esperaba {$expectCode}, recibió: " . trim($response));
        }
    }
}
