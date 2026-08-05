<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Config\Env;

final class OpenPayClient
{
    public function request(string $method, string $path, ?array $body = null): array
    {
        $merchantId = Env::require('OPENPAY_MERCHANT_ID');
        $privateKey = Env::require('OPENPAY_PRIVATE_KEY');
        $base = rtrim(Env::get('OPENPAY_API_BASE', 'https://sandbox-api.openpay.mx/v1') ?? '', '/');

        $url = $base . '/' . $merchantId;
        if ($path !== '') {
            $url .= '/' . ltrim($path, '/');
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('No se pudo iniciar cURL para OpenPay.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $headers[] = 'X-Forwarded-For: ' . $clientIp;

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => $privateKey . ':',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new \RuntimeException("cURL OpenPay: {$error}");
        }

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw new \RuntimeException("OpenPay respuesta inválida (HTTP {$status}).");
        }

        if ($status >= 400) {
            $description = (string) ($decoded['description'] ?? $decoded['message'] ?? 'error');
            $code = (string) ($decoded['error_code'] ?? $status);
            throw new \RuntimeException("OpenPay [{$code}]: {$description}");
        }

        return $decoded;
    }

    public function getMerchant(): array
    {
        // GET /v1/{merchantId} autentica con la llave privada
        return $this->request('GET', '');
    }
}
