<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Config\Env;
use App\Database\Connection;
use PDOException;

final class HealthChecker
{
    /** @return list<array{name: string, ok: bool, message: string, meta?: array<string, mixed>}> */
    public function runAll(?string $smtpTestTo = null): array
    {
        return [
            $this->checkDatabase(),
            $this->checkMoodle(),
            $this->checkOpenPay(),
            $this->checkSmtp($smtpTestTo),
            $this->checkStorage(),
        ];
    }

    /** @return array{name: string, ok: bool, message: string, meta?: array<string, mixed>} */
    public function checkDatabase(): array
    {
        $name = 'MariaDB';
        if (!Env::isFilled('DB_NAME') || !Env::isFilled('DB_USER')) {
            return ['name' => $name, 'ok' => false, 'message' => 'Faltan DB_NAME o DB_USER en .env'];
        }
        if (!Env::isFilled('DB_PASS')) {
            return ['name' => $name, 'ok' => false, 'message' => 'DB_PASS está vacío. Complétalo en el .env del servidor.'];
        }

        try {
            $pdo = Connection::get();
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            return [
                'name' => $name,
                'ok' => true,
                'message' => "Conectado a {$dbName}",
                'meta' => ['version' => $version],
            ];
        } catch (PDOException | \Throwable $e) {
            Connection::reset();
            return ['name' => $name, 'ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array{name: string, ok: bool, message: string, meta?: array<string, mixed>} */
    public function checkMoodle(): array
    {
        $name = 'Moodle';
        if (!Env::isFilled('MOODLE_URL') || !Env::isFilled('MOODLE_TOKEN')) {
            return ['name' => $name, 'ok' => false, 'message' => 'Faltan MOODLE_URL o MOODLE_TOKEN en .env'];
        }

        try {
            $client = new MoodleClient();
            $courses = $client->getCourses();
            $count = count($courses);
            $sample = [];
            foreach (array_slice($courses, 0, 5) as $course) {
                if (!is_array($course)) {
                    continue;
                }
                $sample[] = [
                    'id' => $course['id'] ?? null,
                    'shortname' => isset($course['shortname']) ? (string) $course['shortname'] : null,
                    'fullname' => isset($course['fullname']) ? mb_substr((string) $course['fullname'], 0, 120) : null,
                ];
            }
            unset($courses);

            return [
                'name' => $name,
                'ok' => true,
                'message' => "OK — {$count} curso(s) visibles vía core_course_get_courses",
                'meta' => ['sample' => $sample],
            ];
        } catch (\Throwable $e) {
            return ['name' => $name, 'ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array{name: string, ok: bool, message: string, meta?: array<string, mixed>} */
    public function checkOpenPay(): array
    {
        $name = 'OpenPay';
        if (!Env::isFilled('OPENPAY_MERCHANT_ID') || !Env::isFilled('OPENPAY_PRIVATE_KEY')) {
            return [
                'name' => $name,
                'ok' => false,
                'message' => 'Faltan OPENPAY_MERCHANT_ID o OPENPAY_PRIVATE_KEY (usa la sk_ real de sandbox, no el placeholder).',
            ];
        }

        if (str_contains((string) Env::get('OPENPAY_PRIVATE_KEY'), 'MI_LLAVE')) {
            return [
                'name' => $name,
                'ok' => false,
                'message' => 'OPENPAY_PRIVATE_KEY todavía es un placeholder. Pega la llave secreta real del dashboard sandbox.',
            ];
        }

        try {
            $client = new OpenPayClient();
            $merchant = $client->getMerchant();
            return [
                'name' => $name,
                'ok' => true,
                'message' => 'Sandbox autenticado correctamente',
                'meta' => [
                    'id' => $merchant['id'] ?? Env::get('OPENPAY_MERCHANT_ID'),
                    'name' => $merchant['name'] ?? null,
                    'sandbox' => Env::getBool('OPENPAY_SANDBOX', true),
                ],
            ];
        } catch (\Throwable $e) {
            return ['name' => $name, 'ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** @return array{name: string, ok: bool, message: string, meta?: array<string, mixed>} */
    public function checkSmtp(?string $testTo = null): array
    {
        $name = 'SMTP';
        foreach (['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS'] as $key) {
            if (!Env::isFilled($key)) {
                return ['name' => $name, 'ok' => false, 'message' => "Falta {$key} en .env"];
            }
        }

        $to = $testTo ?: (Env::get('SMTP_FROM') ?: Env::get('SMTP_USER'));
        if ($to === null || $to === '') {
            return ['name' => $name, 'ok' => false, 'message' => 'No hay destinatario de prueba SMTP.'];
        }

        $meta = [
            'host' => Env::get('SMTP_HOST'),
            'port' => Env::get('SMTP_PORT'),
            'encryption' => Env::get('SMTP_ENCRYPTION', 'ssl'),
            'user' => Env::get('SMTP_USER'),
            'pass_len' => strlen((string) Env::get('SMTP_PASS', '')),
            'from' => Env::get('SMTP_FROM'),
            'to' => $to,
            'note' => 'Si pass_len no coincide con la longitud real de la clave en cPanel, el .env está mal parseado (usa comillas dobles).',
        ];

        try {
            $mailer = new Mailer();
            $mailer->send(
                $to,
                'Prueba PDV Instituto Doceo',
                "Este es un correo de prueba del panel de salud del PDV.\n\nFecha: " . date('c') . "\n"
            );

            $used = Mailer::lastEndpoint();
            if ($used !== null) {
                $meta['used_endpoint'] = $used;
            }

            return [
                'name' => $name,
                'ok' => true,
                'message' => "Correo de prueba enviado a {$to}"
                    . ($used ? ' vía ' . $used['host'] . ':' . $used['port'] . '/' . $used['encryption'] : ''),
                'meta' => $meta,
            ];
        } catch (\Throwable $e) {
            return [
                'name' => $name,
                'ok' => false,
                'message' => $e->getMessage(),
                'meta' => $meta,
            ];
        }
    }

    /** @return array{name: string, ok: bool, message: string, meta?: array<string, mixed>} */
    public function checkStorage(): array
    {
        $name = 'Storage';
        $base = dirname(__DIR__, 2) . '/storage';
        $dirs = [$base, $base . '/uploads', $base . '/logs'];

        foreach ($dirs as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['name' => $name, 'ok' => false, 'message' => "No se pudo crear {$dir}"];
            }
            if (!is_writable($dir)) {
                return ['name' => $name, 'ok' => false, 'message' => "Sin permiso de escritura en {$dir}"];
            }
        }

        $probe = $base . '/logs/health-probe.txt';
        if (file_put_contents($probe, date('c')) === false) {
            return ['name' => $name, 'ok' => false, 'message' => 'No se pudo escribir archivo de prueba.'];
        }

        return [
            'name' => $name,
            'ok' => true,
            'message' => 'Lectura/escritura OK en storage/',
        ];
    }
}
