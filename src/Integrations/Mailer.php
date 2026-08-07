<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Config\Env;

/**
 * Cliente SMTP mínimo (SSL/TLS) sin dependencias Composer.
 * En Neubox/cPanel, mail.dominio:465 desde el mismo servidor a menudo
 * responde 535 aunque la contraseña sea correcta; localhost suele funcionar.
 */
final class Mailer
{
    /** @var array{host: string, port: int, encryption: string}|null */
    private static ?array $lastEndpoint = null;

    /** @return array{host: string, port: int, encryption: string}|null */
    public static function lastEndpoint(): ?array
    {
        return self::$lastEndpoint;
    }

    public function send(string $to, string $subject, string $bodyText): void
    {
        $user = trim(Env::require('SMTP_USER'));
        $pass = Env::require('SMTP_PASS');
        $from = trim(Env::get('SMTP_FROM', $user) ?? $user);
        $fromName = Env::get('SMTP_FROM_NAME', 'Instituto Doceo') ?? 'Instituto Doceo';

        $attempts = $this->endpoints();
        $errors = [];

        foreach ($attempts as $endpoint) {
            $label = $endpoint['host'] . ':' . $endpoint['port'] . '/' . $endpoint['encryption'];
            try {
                $this->sendVia(
                    $endpoint['host'],
                    $endpoint['port'],
                    $endpoint['encryption'],
                    $user,
                    $pass,
                    $from,
                    $fromName,
                    $to,
                    $subject,
                    $bodyText
                );
                self::$lastEndpoint = $endpoint;

                return;
            } catch (\Throwable $e) {
                $errors[] = $label . ' → ' . $e->getMessage();
            }
        }

        $passLen = strlen($pass);
        throw new \RuntimeException(
            'SMTP: todos los endpoints fallaron (user=' . $user . ', pass_len=' . $passLen . '). '
            . 'Comprueba que la contraseña tenga exactamente ' . $passLen . ' caracteres (como en cPanel/webmail). '
            . "Intentos:\n- " . implode("\n- ", $errors)
        );
    }

    /**
     * Endpoints a probar: el configurado y variantes típicas de hosting compartido.
     *
     * @return list<array{host: string, port: int, encryption: string}>
     */
    private function endpoints(): array
    {
        $host = trim(Env::require('SMTP_HOST'));
        $port = (int) (Env::get('SMTP_PORT', '465') ?? '465');
        $encryption = strtolower(trim(Env::get('SMTP_ENCRYPTION', 'ssl') ?? 'ssl'));
        if ($encryption !== 'tls' && $encryption !== 'none') {
            $encryption = 'ssl';
        }

        $candidates = [
            ['host' => $host, 'port' => $port, 'encryption' => $encryption],
            ['host' => 'localhost', 'port' => $port, 'encryption' => $encryption],
            ['host' => '127.0.0.1', 'port' => $port, 'encryption' => $encryption],
            ['host' => $host, 'port' => 587, 'encryption' => 'tls'],
            ['host' => 'localhost', 'port' => 587, 'encryption' => 'tls'],
            ['host' => '127.0.0.1', 'port' => 587, 'encryption' => 'tls'],
            ['host' => 'localhost', 'port' => 465, 'encryption' => 'ssl'],
            ['host' => $host, 'port' => 465, 'encryption' => 'ssl'],
        ];

        $seen = [];
        $out = [];
        foreach ($candidates as $c) {
            $key = $c['host'] . '|' . $c['port'] . '|' . $c['encryption'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $c;
        }

        return $out;
    }

    private function sendVia(
        string $host,
        int $port,
        string $encryption,
        string $user,
        string $pass,
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $bodyText
    ): void {
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
        $scheme = match ($encryption) {
            'ssl' => 'ssl://',
            default => 'tcp://',
        };
        $remote = $scheme . $host . ':' . $port;

        $contexts = [
            stream_context_create([
                'ssl' => [
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'peer_name' => $host,
                    'allow_self_signed' => false,
                ],
            ]),
            // Cert del servidor compartido suele no coincidir con mail.dominio / localhost.
            stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]),
        ];

        $fp = false;
        $errno = 0;
        $errstr = '';
        foreach ($contexts as $context) {
            $fp = @stream_socket_client(
                $remote,
                $errno,
                $errstr,
                20,
                STREAM_CLIENT_CONNECT,
                $context
            );
            if ($fp !== false) {
                break;
            }
        }

        if ($fp === false) {
            throw new \RuntimeException("No se pudo conectar a {$remote}: {$errstr} ({$errno})");
        }

        stream_set_timeout($fp, 20);
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
     * @param resource $fp
     * @return resource
     */
    private function authenticate($fp, string $user, string $pass, string $host, int $port, string $encryption)
    {
        try {
            $plain = base64_encode("\0{$user}\0{$pass}");
            $this->command($fp, 'AUTH PLAIN ' . $plain, 235);

            return $fp;
        } catch (\RuntimeException) {
            // seguir con LOGIN
        }

        $newFp = $this->connect($host, $port, $encryption);
        fclose($fp);
        $fp = $newFp;

        $this->command($fp, 'AUTH LOGIN', 334);
        $this->command($fp, base64_encode($user), 334);
        $this->command($fp, base64_encode($pass), 235);

        return $fp;
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
