<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Config\Env;

/**
 * Envío de correo para Neubox/cPanel.
 *
 * Webmail (IMAP/Dovecot) puede aceptar la clave y Exim SMTP AUTH (465/587)
 * rechazarla con 535 desde PHP. En auto se usa primero mail() local
 * (sendmail/Exim sin AUTH), que es lo habitual en hosting compartido.
 */
final class Mailer
{
    /** @var array{transport: string, host?: string, port?: int, encryption?: string, auth_user?: string}|null */
    private static ?array $lastEndpoint = null;

    /** @return array{transport: string, host?: string, port?: int, encryption?: string, auth_user?: string}|null */
    public static function lastEndpoint(): ?array
    {
        return self::$lastEndpoint;
    }

    /** Huella segura de SMTP_PASS (sin revelar la clave). */
    public static function passwordFingerprint(): array
    {
        $pass = (string) Env::get('SMTP_PASS', '');
        $len = strlen($pass);
        $first = $len > 0 ? ord($pass[0]) : null;
        $last = $len > 0 ? ord($pass[$len - 1]) : null;

        return [
            'pass_len' => $len,
            'pass_first_ord' => $first,
            'pass_last_ord' => $last,
            'pass_sha1_8' => $len > 0 ? substr(sha1($pass), 0, 8) : null,
            'pass_has_space' => str_contains($pass, ' '),
            'pass_has_non_ascii' => $pass !== '' && !preg_match('/^[\x20-\x7E]+$/', $pass),
        ];
    }

    public function send(string $to, string $subject, string $bodyText): void
    {
        $transport = strtolower(trim(Env::get('SMTP_TRANSPORT', 'auto') ?? 'auto'));
        if (!in_array($transport, ['auto', 'smtp', 'mail'], true)) {
            $transport = 'auto';
        }

        $errors = [];

        if ($transport === 'mail' || $transport === 'auto') {
            try {
                $this->sendViaPhpMail($to, $subject, $bodyText);
                self::$lastEndpoint = ['transport' => 'mail'];

                return;
            } catch (\Throwable $e) {
                $errors[] = 'mail() → ' . $e->getMessage();
                if ($transport === 'mail') {
                    throw $e;
                }
            }
        }

        if ($transport === 'smtp' || $transport === 'auto') {
            try {
                $this->sendViaSmtp($to, $subject, $bodyText, $errors);

                return;
            } catch (\Throwable $e) {
                $errors[] = 'smtp → ' . $e->getMessage();
                if ($transport === 'smtp') {
                    throw $this->wrapFinalError($errors);
                }
            }
        }

        throw $this->wrapFinalError($errors);
    }

    /** @param list<string> $errors */
    private function wrapFinalError(array $errors): \RuntimeException
    {
        $fp = self::passwordFingerprint();

        return new \RuntimeException(
            'Correo: no se pudo enviar. '
            . 'Webmail (IMAP) ≠ SMTP AUTH: clave válida en webmail no garantiza 465/587 desde PHP. '
            . 'Tras muchos 535, revisa cPanel → cPHulk (desbloquea IP/cuenta) o restablece la clave del buzón. '
            . 'Puedes forzar envío local con SMTP_TRANSPORT=mail. '
            . 'Fingerprint: len=' . $fp['pass_len']
            . ', first_ord=' . ($fp['pass_first_ord'] ?? '-')
            . ', last_ord=' . ($fp['pass_last_ord'] ?? '-')
            . ', sha1_8=' . ($fp['pass_sha1_8'] ?? '-')
            . ". Intentos:\n- " . implode("\n- ", $errors)
        );
    }

    /** @param list<string> $errors */
    private function sendViaSmtp(string $to, string $subject, string $bodyText, array &$errors): void
    {
        $user = trim(Env::require('SMTP_USER'));
        $pass = Env::require('SMTP_PASS');
        $from = trim(Env::get('SMTP_FROM', $user) ?? $user);
        $fromName = Env::get('SMTP_FROM_NAME', 'Instituto Doceo') ?? 'Instituto Doceo';

        $userVariants = array_values(array_unique(array_filter([
            $user,
            str_contains($user, '@') ? explode('@', $user, 2)[0] : null,
        ])));

        foreach ($this->endpoints() as $endpoint) {
            $label = $endpoint['host'] . ':' . $endpoint['port'] . '/' . $endpoint['encryption'];
            foreach ($userVariants as $authUser) {
                $authLabel = $label . ' user=' . $authUser;
                try {
                    $this->sendViaSocket(
                        $endpoint['host'],
                        $endpoint['port'],
                        $endpoint['encryption'],
                        $authUser,
                        $pass,
                        $from,
                        $fromName,
                        $to,
                        $subject,
                        $bodyText
                    );
                    self::$lastEndpoint = [
                        'transport' => 'smtp',
                        'host' => $endpoint['host'],
                        'port' => $endpoint['port'],
                        'encryption' => $endpoint['encryption'],
                        'auth_user' => $authUser,
                    ];

                    return;
                } catch (\Throwable $e) {
                    $errors[] = $authLabel . ' → ' . $e->getMessage();
                }
            }
        }

        throw new \RuntimeException('SMTP AUTH falló en todos los endpoints.');
    }

    private function sendViaPhpMail(string $to, string $subject, string $bodyText): void
    {
        $user = trim((string) Env::get('SMTP_USER', ''));
        $from = trim((string) (Env::get('SMTP_FROM', $user !== '' ? $user : null) ?? 'certificaciones@institutodoceo.com'));
        $fromName = (string) (Env::get('SMTP_FROM_NAME', 'Instituto Doceo') ?? 'Instituto Doceo');

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFrom = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'From: ' . $encodedFrom,
            'Reply-To: ' . $from,
            'X-Mailer: Instituto-Doceo-PDV',
        ];

        $body = str_replace(["\r\n", "\r"], "\n", $bodyText);
        $body = str_replace("\n", "\r\n", $body);

        $params = '-f' . $from;
        $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers), $params);
        if ($ok !== true) {
            throw new \RuntimeException(
                'PHP mail() devolvió false. Revisa que From (' . $from
                . ') sea un buzón del dominio en Neubox.'
            );
        }
    }

    /**
     * Pocos endpoints (auto ya prioriza mail()). Evita timeouts en salud.
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
            ['host' => 'localhost', 'port' => 465, 'encryption' => 'ssl'],
            ['host' => $host, 'port' => 587, 'encryption' => 'tls'],
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

    private function sendViaSocket(
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
        $scheme = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $remote = $scheme . $host . ':' . $port;

        $contexts = [
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
                12,
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

        stream_set_timeout($fp, 12);
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
            // LOGIN
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
