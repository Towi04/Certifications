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
        $host = Env::require('SMTP_HOST');
        $port = (int) (Env::get('SMTP_PORT', '465') ?? '465');
        $user = Env::require('SMTP_USER');
        $pass = Env::require('SMTP_PASS');
        $from = Env::get('SMTP_FROM', $user) ?? $user;
        $fromName = Env::get('SMTP_FROM_NAME', 'Instituto Doceo') ?? 'Instituto Doceo';
        $encryption = strtolower(Env::get('SMTP_ENCRYPTION', 'ssl') ?? 'ssl');

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
        if ($fp === false) {
            throw new \RuntimeException("No se pudo conectar a SMTP: {$errstr} ({$errno})");
        }

        stream_set_timeout($fp, 30);

        try {
            $this->expect($fp, 220);
            $this->command($fp, 'EHLO pdv.institutodoceo.com', 250);

            if ($encryption === 'tls') {
                $this->command($fp, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('No se pudo negociar STARTTLS.');
                }
                $this->command($fp, 'EHLO pdv.institutodoceo.com', 250);
            }

            $this->command($fp, 'AUTH LOGIN', 334);
            $this->command($fp, base64_encode($user), 334);
            $this->command($fp, base64_encode($pass), 235);

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
            fclose($fp);
        }
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
