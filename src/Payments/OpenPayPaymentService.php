<?php

declare(strict_types=1);

namespace App\Payments;

use App\Catalog\CatalogRepository;
use App\Config\Env;
use App\Integrations\OpenPayClient;
use App\Mail\CaseMailService;

final class OpenPayPaymentService
{
    public function __construct(
        private readonly CatalogRepository $repo,
        private readonly OpenPayClient $openPay = new OpenPayClient(),
        private readonly ?CaseMailService $mail = null,
    ) {
    }

    private function mailer(): CaseMailService
    {
        return $this->mail ?? new CaseMailService($this->repo);
    }

    /**
     * Crea (o regenera si aún no está pagado) una CLABE SPEI única para el caso.
     *
     * @return array<string, mixed> Campos OpenPay guardados en el caso
     */
    public function ensureSpeiCharge(int $caseId, bool $forceNew = false, bool $sendInstructions = true): array
    {
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }

        $status = strtolower((string) ($case['openpay_status'] ?? ''));
        if (!$forceNew && in_array($status, ['completed', 'paid'], true) && !empty($case['payment_confirmed_at'])) {
            return $this->openPaySnapshot($case);
        }
        if (!$forceNew && !empty($case['openpay_clabe']) && in_array($status, ['in_progress', 'charge_pending', ''], true)) {
            if ($sendInstructions) {
                try {
                    $this->mailer()->sendTemplate($caseId, 'pago_clabe', null);
                } catch (\Throwable) {
                }
            }

            return $this->openPaySnapshot($case);
        }

        $amount = $this->amountForCase($case);
        if ($amount <= 0) {
            throw new \RuntimeException('El monto a cobrar es 0. Revisa precio público / CENNI de la certificación.');
        }

        $orderId = 'PDV-CASE-' . $caseId . '-' . date('YmdHis');
        $name = trim((string) ($case['student_name'] ?? 'Alumno Doceo'));
        $email = trim((string) ($case['student_email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('El caso necesita un correo de alumno válido para OpenPay.');
        }

        $phone = preg_replace('/\D+/', '', (string) ($case['student_phone'] ?? '')) ?: '0000000000';
        $due = (new \DateTimeImmutable('+7 days'))->format('c');

        $charge = $this->openPay->createBankCharge([
            'amount' => $amount,
            'description' => mb_substr(
                'Certificación ' . (string) ($case['certification_code'] ?? '') . ' — caso #' . $caseId,
                0,
                250
            ),
            'order_id' => $orderId,
            'due_date' => $due,
            'customer' => [
                'name' => mb_substr($name, 0, 100),
                'email' => $email,
                'phone_number' => mb_substr($phone, 0, 15),
            ],
        ]);

        $pm = is_array($charge['payment_method'] ?? null) ? $charge['payment_method'] : [];
        $chargeId = (string) ($charge['id'] ?? '');
        $fields = [
            'openpay_charge_id' => $chargeId,
            'openpay_order_id' => (string) ($charge['order_id'] ?? $orderId),
            'openpay_clabe' => (string) ($pm['clabe'] ?? ''),
            'openpay_bank' => (string) ($pm['bank'] ?? 'BBVA Bancomer'),
            'openpay_agreement' => (string) ($pm['agreement'] ?? ''),
            'openpay_reference' => (string) ($pm['name'] ?? $pm['agreement'] ?? ''),
            'openpay_amount' => round((float) ($charge['amount'] ?? $amount), 2),
            'openpay_status' => (string) ($charge['status'] ?? 'in_progress'),
            'openpay_due_at' => $this->normalizeDate($charge['due_date'] ?? $due),
            'openpay_pdf_url' => $chargeId !== '' ? $this->openPay->speiPdfUrl($chargeId) : null,
        ];
        $this->repo->updateCertificationCase($caseId, $fields);

        if ($sendInstructions) {
            try {
                $this->mailer()->sendTemplate($caseId, 'pago_clabe', null);
            } catch (\Throwable) {
                // La CLABE ya quedó guardada; el correo se puede reenviar desde el caso.
            }
        }

        return $fields;
    }

    /**
     * Procesa webhook OpenPay (charge.succeeded → confirma pago del caso).
     *
     * @param array<string, mixed> $payload
     */
    public function handleWebhook(array $payload): array
    {
        $type = (string) ($payload['type'] ?? '');
        $tx = is_array($payload['transaction'] ?? null) ? $payload['transaction'] : [];
        $chargeId = (string) ($tx['id'] ?? '');
        $orderId = (string) ($tx['order_id'] ?? '');

        $eventId = $this->repo->logOpenPayWebhook([
            'event_type' => $type,
            'openpay_charge_id' => $chargeId !== '' ? $chargeId : null,
            'order_id' => $orderId !== '' ? $orderId : null,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}',
        ]);

        if ($type === 'verification') {
            $this->repo->markOpenPayWebhookProcessed($eventId, true, null);

            return ['ok' => true, 'type' => $type];
        }

        if ($type !== 'charge.succeeded' && $type !== 'spei.received') {
            $this->repo->markOpenPayWebhookProcessed($eventId, true, 'ignored:' . $type);

            return ['ok' => true, 'ignored' => $type];
        }

        try {
            $case = null;
            if ($chargeId !== '') {
                $case = $this->repo->certificationCaseByOpenPayChargeId($chargeId);
            }
            if (!$case && $orderId !== '') {
                $case = $this->repo->certificationCaseByOpenPayOrderId($orderId);
            }
            if (!$case) {
                throw new \RuntimeException('No hay caso ligado a charge/order OpenPay.');
            }

            $caseId = (int) $case['id'];
            $this->repo->attachOpenPayWebhookCase($eventId, $caseId);

            if (!empty($case['payment_confirmed_at']) && in_array(strtolower((string) ($case['openpay_status'] ?? '')), ['completed', 'paid'], true)) {
                $this->repo->markOpenPayWebhookProcessed($eventId, true, 'already_paid');

                return ['ok' => true, 'case_id' => $caseId, 'already_paid' => true];
            }

            $now = date('Y-m-d H:i:s');
            $this->repo->updateCertificationCase($caseId, [
                'openpay_status' => 'completed',
                'openpay_paid_at' => $now,
                'payment_confirmed_at' => $now,
            ]);

            try {
                $this->repo->markCaseStepDoneByKeywords($caseId, ['realiza el pago', 'pago del examen', 'el alumno realiza'], null, 'Pago confirmado por OpenPay');
            } catch (\Throwable) {
            }

            try {
                $this->mailer()->sendTemplate($caseId, 'pago_confirmado', null);
            } catch (\Throwable $mailErr) {
                // Pago ya confirmado; el reenvío puede hacerse manual.
                $this->repo->markOpenPayWebhookProcessed($eventId, true, 'paid_mail_failed:' . $mailErr->getMessage());

                return ['ok' => true, 'case_id' => $caseId, 'paid' => true, 'mail_error' => $mailErr->getMessage()];
            }

            $this->repo->markOpenPayWebhookProcessed($eventId, true, null);

            return ['ok' => true, 'case_id' => $caseId, 'paid' => true];
        } catch (\Throwable $e) {
            $this->repo->markOpenPayWebhookProcessed($eventId, false, $e->getMessage());
            throw $e;
        }
    }

    /** @param array<string, mixed> $case */
    public function amountForCase(array $case): float
    {
        $price = (float) ($case['public_price'] ?? 0);
        $cenniProcess = (string) ($case['cenni_process'] ?? 'none');
        $cenniIncluded = (int) ($case['cenni_included'] ?? 0) === 1;
        $cenniFee = (float) ($case['cenni_fee'] ?? 0);
        if ($cenniProcess !== 'none' && !$cenniIncluded && $cenniFee > 0) {
            $price += $cenniFee;
        }

        return round($price, 2);
    }

    /** @param array<string, mixed> $case @return array<string, mixed> */
    private function openPaySnapshot(array $case): array
    {
        return [
            'openpay_charge_id' => $case['openpay_charge_id'] ?? null,
            'openpay_order_id' => $case['openpay_order_id'] ?? null,
            'openpay_clabe' => $case['openpay_clabe'] ?? null,
            'openpay_bank' => $case['openpay_bank'] ?? null,
            'openpay_agreement' => $case['openpay_agreement'] ?? null,
            'openpay_reference' => $case['openpay_reference'] ?? null,
            'openpay_amount' => $case['openpay_amount'] ?? null,
            'openpay_status' => $case['openpay_status'] ?? null,
            'openpay_due_at' => $case['openpay_due_at'] ?? null,
            'openpay_pdf_url' => $case['openpay_pdf_url'] ?? null,
        ];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable((string) $value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, string> */
    public static function cenniStatuses(): array
    {
        return [
            'none' => 'Sin CENNI',
            'awaiting_uks_upload' => 'Alumno debe subir docs en UKS',
            'awaiting_pdv_upload' => 'Alumno debe subir docs en Doceo',
            'docs_in_review' => 'Documentos en revisión',
            'docs_rejected' => 'Documentos rechazados / corrección',
            'sep_pending' => 'En trámite ante la SEP',
            'issued' => 'CENNI emitido',
        ];
    }

    /** @return array<string, string> */
    public static function cenniProcesses(): array
    {
        return [
            'none' => 'Sin trámite CENNI',
            'uks_external' => 'Externo UKS (ELET: alumno sube en UKS)',
            'doceo_managed' => 'Gestionado por Doceo (alumno sube docs aquí)',
        ];
    }
}
