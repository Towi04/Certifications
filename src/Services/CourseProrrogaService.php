<?php

declare(strict_types=1);

namespace App\Services;

use App\Catalog\CatalogRepository;
use App\Integrations\MoodleEnrolService;
use App\Integrations\OpenPayClient;
use App\Mail\CaseMailService;
use App\Support\Uploader;

/**
 * Prórroga de acceso Moodle (+6 meses): SPEI OpenPay o comprobante + confirmación admin.
 */
final class CourseProrrogaService
{
    public function __construct(
        private readonly CatalogRepository $repo,
        private readonly OpenPayClient $openPay = new OpenPayClient(),
        private readonly ?CaseMailService $mail = null,
        private readonly ?MoodleEnrolService $moodle = null,
    ) {
    }

    private function mailer(): CaseMailService
    {
        return $this->mail ?? new CaseMailService($this->repo);
    }

    private function moodle(): MoodleEnrolService
    {
        return $this->moodle ?? new MoodleEnrolService($this->repo, mail: $this->mailer());
    }

    /**
     * Crea (o reutiliza) una prórroga pendiente para la matrícula del caso.
     *
     * @return array<string,mixed>
     */
    public function startProrroga(int $caseId, int $enrolmentId): array
    {
        $this->repo->ensureCourseAccessTables();
        $enrol = $this->repo->caseMoodleEnrolment($enrolmentId);
        if (!$enrol || (int) $enrol['case_id'] !== $caseId) {
            throw new \RuntimeException('Matrícula no encontrada para este caso.');
        }
        $amount = (float) ($enrol['prorroga_price'] ?? 0);
        if ($amount <= 0) {
            throw new \RuntimeException(
                'El curso “' . ($enrol['course_name'] ?? '') . '” no tiene precio de prórroga. Configúralo en Admin → Cursos.'
            );
        }
        $id = $this->repo->createCourseProrroga(
            $caseId,
            $enrolmentId,
            $amount,
            MoodleEnrolService::PRORROGA_MONTHS
        );

        return $this->repo->courseProrroga($id) ?? ['id' => $id];
    }

    /** @return array<string,mixed> Campos OpenPay de la prórroga */
    public function ensureSpeiCharge(int $prorrogaId, bool $forceNew = false): array
    {
        $prorroga = $this->repo->courseProrroga($prorrogaId);
        if (!$prorroga) {
            throw new \RuntimeException('Prórroga no encontrada.');
        }
        if (($prorroga['status'] ?? '') === 'paid') {
            return $this->snapshot($prorroga);
        }
        $status = strtolower((string) ($prorroga['openpay_status'] ?? ''));
        if (!$forceNew && !empty($prorroga['openpay_clabe'])
            && in_array($status, ['in_progress', 'charge_pending', ''], true)) {
            return $this->snapshot($prorroga);
        }

        $case = $this->repo->certificationCaseDetailed((int) $prorroga['case_id']);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }
        $amount = round((float) ($prorroga['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Monto de prórroga inválido.');
        }

        $orderId = 'PDV-EXT-' . $prorrogaId . '-' . date('YmdHis');
        $name = trim((string) ($case['student_name'] ?? 'Alumno Doceo'));
        $email = trim((string) ($case['student_email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('El caso necesita un correo válido para OpenPay.');
        }
        $phone = preg_replace('/\D+/', '', (string) ($case['student_phone'] ?? '')) ?: '0000000000';
        $due = (new \DateTimeImmutable('+7 days'))->format('c');
        $desc = 'Prórroga Moodle +' . (int) ($prorroga['months'] ?? 6) . ' meses — '
            . (string) ($prorroga['course_name'] ?? 'curso') . ' · caso #' . (int) $prorroga['case_id'];

        $charge = $this->openPay->createBankCharge([
            'amount' => $amount,
            'description' => mb_substr($desc, 0, 250),
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
            'payment_method' => 'openpay',
            'status' => 'pending',
        ];
        $this->repo->updateCourseProrroga($prorrogaId, $fields);

        return $fields;
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $file
     * @return array<string,mixed>
     */
    public function uploadProof(int $prorrogaId, ?array $file, string $method, ?string $note, ?int $userId): array
    {
        $prorroga = $this->repo->courseProrroga($prorrogaId);
        if (!$prorroga) {
            throw new \RuntimeException('Prórroga no encontrada.');
        }
        if (($prorroga['status'] ?? '') === 'paid') {
            throw new \RuntimeException('Esta prórroga ya está pagada.');
        }
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Selecciona el comprobante (PDF o imagen).');
        }
        $method = strtolower(trim($method));
        if (!in_array($method, ['cash', 'transfer', 'other'], true)) {
            $method = 'transfer';
        }
        $caseId = (int) $prorroga['case_id'];
        $path = Uploader::store($file, 'cases/' . $caseId . '/prorroga');
        $this->repo->addCaseAttachment(
            $caseId,
            'payment',
            'Comprobante prórroga Moodle (' . $method . ')',
            $path,
            $userId
        );
        $fields = [
            'payment_proof_path' => $path,
            'payment_method' => $method,
            'status' => 'proof_uploaded',
        ];
        if ($note !== null && trim($note) !== '') {
            $prev = trim((string) ($prorroga['notes'] ?? ''));
            $stamp = date('Y-m-d H:i:s') . ' ' . trim($note);
            $fields['notes'] = $prev !== '' ? ($prev . "\n" . $stamp) : $stamp;
        }
        $this->repo->updateCourseProrroga($prorrogaId, $fields);

        return $this->repo->courseProrroga($prorrogaId) ?? $fields;
    }

    /**
     * Confirma pago (admin o webhook) y extiende Moodle.
     *
     * @return array{prorroga_id:int,access_ends_at:string}
     */
    public function confirmPaid(int $prorrogaId, string $method = 'openpay', ?int $actorUserId = null): array
    {
        $prorroga = $this->repo->courseProrroga($prorrogaId);
        if (!$prorroga) {
            throw new \RuntimeException('Prórroga no encontrada.');
        }
        if (($prorroga['status'] ?? '') === 'paid') {
            return [
                'prorroga_id' => $prorrogaId,
                'access_ends_at' => (string) ($prorroga['access_ends_at'] ?? ''),
            ];
        }
        $now = date('Y-m-d H:i:s');
        $this->repo->updateCourseProrroga($prorrogaId, [
            'status' => 'paid',
            'payment_method' => $method,
            'payment_confirmed_at' => $now,
            'openpay_status' => $method === 'openpay' ? 'completed' : ($prorroga['openpay_status'] ?? null),
            'openpay_paid_at' => $method === 'openpay' ? $now : ($prorroga['openpay_paid_at'] ?? null),
        ]);

        $months = max(1, (int) ($prorroga['months'] ?? MoodleEnrolService::PRORROGA_MONTHS));
        $extended = $this->moodle()->extendEnrolment(
            (int) $prorroga['case_moodle_enrolment_id'],
            $months,
            $actorUserId
        );

        return [
            'prorroga_id' => $prorrogaId,
            'access_ends_at' => $extended['access_ends_at'],
        ];
    }

    /** @param array<string,mixed> $prorroga */
    private function snapshot(array $prorroga): array
    {
        return [
            'openpay_charge_id' => $prorroga['openpay_charge_id'] ?? null,
            'openpay_order_id' => $prorroga['openpay_order_id'] ?? null,
            'openpay_clabe' => $prorroga['openpay_clabe'] ?? null,
            'openpay_bank' => $prorroga['openpay_bank'] ?? null,
            'openpay_agreement' => $prorroga['openpay_agreement'] ?? null,
            'openpay_reference' => $prorroga['openpay_reference'] ?? null,
            'openpay_amount' => $prorroga['openpay_amount'] ?? $prorroga['amount'] ?? null,
            'openpay_status' => $prorroga['openpay_status'] ?? null,
            'openpay_due_at' => $prorroga['openpay_due_at'] ?? null,
            'openpay_pdf_url' => $prorroga['openpay_pdf_url'] ?? null,
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
}
