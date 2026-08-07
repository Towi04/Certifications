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
        // No hacer trim de la contraseña: debe coincidir byte a byte con cPanel.
        $pass = Env::require('SMTP_PASS');
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
        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                // Neubox a veces presenta cert del hostname del servidor, no del dominio del correo.
                'allow_self_signed' => false,
            ],
        ]);

        $fp = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        // Si el certificado no coincide (común en hosting compartido), reintentar sin verify estricto.
        if ($fp === false && $encryption === 'ssl') {
            $relaxed = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]);
            $fp = @stream_socket_client(
                $remote,
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $relaxed
            );
        }

        if ($fp === false) {
            throw new \RuntimeException("No se pudo conectar a SMTP ({$remote}): {$errstr} ({$errno})");
        }

        stream_set_timeout($fp, 30);
        $this->expect($fp, 220);
        $this->command($fp, 'EHLO pdv.institutodoceo.com', 250);

        if ($encryption === 'tls') {
            $this->command($fp, 'STARTTLS', 220);
            $cryptoOk = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) {
                throw new \RuntimeException('No se pudo negociar STARTTLS.');
            }
            $this->command($fp, 'EHLO pdv.institutodoceo.com', 250);
        }

        return $fp;
    }

    /**
     * AUTH PLAIN primero (un solo round-trip); si el servidor no lo acepta, AUTH LOGIN.
     *
     * @param resource $fp
     * @return resource
     */
    private function authenticate($fp, string $user, string $pass, string $host, int $port, string $encryption)
    {
        $lastError = null;

        try {
            $plain = base64_encode("\0{$user}\0{$pass}");
            $this->command($fp, 'AUTH PLAIN ' . $plain, 235);

            return $fp;
        } catch (\RuntimeException $e) {
            $lastError = $e;
            // 504/535/503 → probar LOGIN en sesión nueva
        }

        $newFp = $this->connect($host, $port, $encryption);
        fclose($fp);
        $fp = $newFp;

        try {
            $this->command($fp, 'AUTH LOGIN', 334);
            $this->command($fp, base64_encode($user), 334);
            $this->command($fp, base64_encode($pass), 235);

            return $fp;
        } catch (\RuntimeException $loginError) {
            throw $this->wrapAuthError($loginError, $lastError);
        }
    }

    private function wrapAuthError(\RuntimeException $e, ?\RuntimeException $previousAttempt = null): \RuntimeException
    {
        $detail = $e->getMessage();
        $is535 = str_contains($detail, '535')
            || str_contains(strtolower($detail), 'incorrect authentication');

        if (!$is535) {
            return $e;
        }

        $user = trim((string) Env::get('SMTP_USER', ''));
        $passLen = strlen((string) Env::get('SMTP_PASS', ''));
        $host = trim((string) Env::get('SMTP_HOST', ''));
        $port = (string) (Env::get('SMTP_PORT', '465') ?? '465');

        $msg = 'SMTP: el servidor Exim rechazó la autenticación (535). '
            . 'El alta de usuario y “Probar SMTP” usan el mismo Mailer/.env — no es un fallo del formulario de usuarios. '
            . "Config leída: user={$user}, pass_len={$passLen}, host={$host}:{$port}. "
            . 'Reprueba en /admin/salud?smtp=1. Si falla igual: entra a webmail con esa cuenta; '
            . 'si webmail entra, prueba SMTP_HOST=localhost o puerto 587 con SMTP_ENCRYPTION=tls. '
            . 'Detalle: ' . $detail;

        if ($previousAttempt !== null) {
            $msg .= ' | Intento previo: ' . $previousAttempt->getMessage();
        }

        return new \RuntimeException($msg, 0, $e);
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

        if ($response === '') {
            throw new \RuntimeException("SMTP esperaba {$expectCode}, recibió: (sin respuesta / timeout)");
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectCode) {
            throw new \RuntimeException("SMTP esperaba {$expectCode}, recibió: " . trim($response));
        }
    }
}
