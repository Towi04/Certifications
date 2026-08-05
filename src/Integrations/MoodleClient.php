<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Config\Env;

final class MoodleClient
{
    public function call(string $function, array $params = []): array
    {
        $url = Env::require('MOODLE_URL');
        $token = Env::require('MOODLE_TOKEN');

        $payload = array_merge($params, [
            'wstoken' => $token,
            'wsfunction' => $function,
            'moodlewsrestformat' => 'json',
        ]);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('No se pudo iniciar cURL para Moodle.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new \RuntimeException("cURL Moodle: {$error}");
        }

        if ($raw === false || $raw === '') {
            throw new \RuntimeException("Respuesta vacía de Moodle (HTTP {$status}).");
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Moodle no devolvió JSON válido.');
        }

        if (isset($decoded['exception'])) {
            $message = (string) ($decoded['message'] ?? $decoded['exception']);
            $code = (string) ($decoded['errorcode'] ?? 'moodle_error');
            throw new \RuntimeException("Moodle [{$code}]: {$message}");
        }

        return $decoded;
    }

    /** @return list<array<string, mixed>> */
    public function getCourses(): array
    {
        $result = $this->call('core_course_get_courses');
        return array_values($result);
    }
}
