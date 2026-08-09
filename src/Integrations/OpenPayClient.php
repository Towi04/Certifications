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
        return $this->request('GET', '');
    }

    /**
     * Cargo SPEI / transferencia bancaria → CLABE única por transacción.
     *
     * @param array{
     *   amount: float|int|string,
     *   description: string,
     *   order_id: string,
     *   customer: array{name: string, email: string, phone_number?: string},
     *   due_date?: string
     * } $data
     */
    public function createBankCharge(array $data): array
    {
        $payload = [
            'method' => 'bank_account',
            'amount' => round((float) $data['amount'], 2),
            'description' => mb_substr((string) $data['description'], 0, 250),
            'order_id' => mb_substr((string) $data['order_id'], 0, 100),
            'customer' => $data['customer'],
        ];
        if (!empty($data['due_date'])) {
            $payload['due_date'] = $data['due_date'];
        }

        return $this->request('POST', 'charges', $payload);
    }

    public function getCharge(string $chargeId): array
    {
        return $this->request('GET', 'charges/' . rawurlencode($chargeId));
    }

    public function merchantId(): string
    {
        return Env::require('OPENPAY_MERCHANT_ID');
    }

    /** Recibo PDF genérico OpenPay (sandbox o producción según API base). */
    public function speiPdfUrl(string $chargeId): string
    {
        $sandbox = str_contains(
            strtolower((string) (Env::get('OPENPAY_API_BASE', '') ?? '')),
            'sandbox'
        ) || Env::getBool('OPENPAY_SANDBOX', true);

        $host = $sandbox
            ? 'https://sandbox-dashboard.openpay.mx'
            : 'https://dashboard.openpay.mx';

        return $host . '/spei-pdf/' . $this->merchantId() . '/' . rawurlencode($chargeId);
    }
}
