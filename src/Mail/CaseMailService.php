<?php

declare(strict_types=1);

namespace App\Mail;

use App\Catalog\CatalogRepository;
use App\Config\Env;
use App\Exports\ProviderExportGenerator;
use App\Integrations\Mailer;
use App\Support\Uploader;

final class CaseMailService
{
    public function __construct(
        private readonly CatalogRepository $repo,
        private readonly Mailer $mailer = new Mailer(),
        private readonly ProviderExportGenerator $exports = new ProviderExportGenerator(),
    ) {
    }

    /**
     * Confirma pago (opcional subir comprobante), genera exportación y envía solicitud al proveedor.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $paymentFile
     * @return array{export: ?array{relative: string, absolute: string, filename: string, mime: string}, mailed: bool, to: ?string}
     */
    public function confirmPaymentAndRequestProvider(int $caseId, ?array $paymentFile, ?int $userId): array
    {
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }

        if ($paymentFile && (int) ($paymentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $path = Uploader::store($paymentFile, 'cases/' . $caseId);
            $this->repo->addCaseAttachment($caseId, 'payment', 'Comprobante de pago', $path, $userId);
            $this->repo->updateCertificationCase($caseId, [
                'payment_proof_path' => $path,
                'payment_confirmed_at' => date('Y-m-d H:i:s'),
            ]);
            $case['payment_proof_path'] = $path;
            $case['payment_confirmed_at'] = date('Y-m-d H:i:s');
        } elseif (empty($case['payment_confirmed_at'])) {
            $this->repo->updateCertificationCase($caseId, [
                'payment_confirmed_at' => date('Y-m-d H:i:s'),
            ]);
            $case['payment_confirmed_at'] = date('Y-m-d H:i:s');
        }

        $format = (string) ($case['export_format'] ?? 'none');
        $export = null;
        if ($format !== '' && $format !== ProviderExportGenerator::FORMAT_NONE) {
            $export = $this->exports->generate($format, $case);
            $this->repo->addCaseAttachment($caseId, 'export', $export['filename'], $export['relative'], $userId);
            $this->repo->updateCertificationCase($caseId, [
                'provider_export_path' => $export['relative'],
            ]);
            $case['provider_export_path'] = $export['relative'];
        }

        $templateCode = trim((string) ($case['provider_request_template'] ?? ''));
        $mailed = false;
        $to = null;
        if ($templateCode !== '') {
            $result = $this->sendTemplate($caseId, $templateCode, $userId, true);
            $mailed = true;
            $to = $result['to'];
            $this->repo->updateCertificationCase($caseId, [
                'provider_request_sent_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return ['export' => $export, 'mailed' => $mailed, 'to' => $to];
    }

    /**
     * Solo regenera el archivo de exportación del caso.
     *
     * @return array{relative: string, absolute: string, filename: string, mime: string}
     */
    public function regenerateExport(int $caseId, ?int $userId): array
    {
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }
        $format = (string) ($case['export_format'] ?? 'none');
        if ($format === '' || $format === ProviderExportGenerator::FORMAT_NONE) {
            throw new \RuntimeException('Este protocolo no tiene formato de exportación configurado.');
        }
        $export = $this->exports->generate($format, $case);
        $this->repo->addCaseAttachment($caseId, 'export', $export['filename'], $export['relative'], $userId);
        $this->repo->updateCertificationCase($caseId, [
            'provider_export_path' => $export['relative'],
        ]);

        return $export;
    }

    /**
     * @return array{to: string, subject: string}
     */
    public function sendTemplate(int $caseId, string $templateCode, ?int $userId, bool $forceAttachExport = false): array
    {
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }
        $tpl = $this->repo->mailTemplateByCode($templateCode);
        if (!$tpl || !(int) ($tpl['is_active'] ?? 0)) {
            throw new \RuntimeException('Plantilla de correo no encontrada: ' . $templateCode);
        }

        $tokens = $this->tokens($case);
        $subject = $this->render((string) $tpl['subject'], $tokens);
        $bodyHtml = $this->render((string) $tpl['body_html'], $tokens);
        $bodyText = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $bodyHtml)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $to = $this->resolveTo($tpl, $case);
        $cc = $this->resolveCc($tpl, $case);

        $attachments = [];
        $attachExport = $forceAttachExport || !empty($tpl['attach_export']);
        $exportRel = (string) ($case['provider_export_path'] ?? '');
        if ($attachExport && $exportRel !== '') {
            $abs = dirname(__DIR__, 2) . '/storage/' . ltrim($exportRel, '/');
            if (is_file($abs)) {
                $attachments[] = [
                    'path' => $abs,
                    'name' => basename($abs),
                    'mime' => str_ends_with(strtolower($abs), '.csv')
                        ? 'text/csv'
                        : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ];
            }
        }

        // Adjuntar comprobante de pago si es solicitud a proveedor
        if (($tpl['audience'] ?? '') === 'provider' && !empty($case['payment_proof_path'])) {
            $payAbs = Uploader::absolutePath((string) $case['payment_proof_path']);
            if ($payAbs) {
                $attachments[] = [
                    'path' => $payAbs,
                    'name' => 'comprobante_pago_' . basename($payAbs),
                    'mime' => str_ends_with(strtolower($payAbs), '.pdf') ? 'application/pdf' : 'application/octet-stream',
                ];
            }
        }

        try {
            $this->mailer->send($to, $subject, $bodyText, [
                'cc' => $cc,
                'html' => true,
                'body_html' => $bodyHtml,
                'attachments' => $attachments,
            ]);
            $this->repo->logCaseMail([
                'case_id' => $caseId,
                'template_code' => $templateCode,
                'to_email' => $to,
                'cc_email' => $cc,
                'subject' => $subject,
                'attachment_path' => $exportRel !== '' ? $exportRel : null,
                'status' => 'sent',
                'error_message' => null,
                'sent_by' => $userId,
            ]);
        } catch (\Throwable $e) {
            $this->repo->logCaseMail([
                'case_id' => $caseId,
                'template_code' => $templateCode,
                'to_email' => $to,
                'cc_email' => $cc,
                'subject' => $subject,
                'attachment_path' => $exportRel !== '' ? $exportRel : null,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'sent_by' => $userId,
            ]);
            throw $e;
        }

        return ['to' => $to, 'subject' => $subject];
    }

    /** @param array<string, mixed> $case */
    private function tokens(array $case): array
    {
        $nombre = trim((string) ($case['student_name'] ?? ''));
        $ap = trim((string) ($case['student_last_name_p'] ?? ''));
        $am = trim((string) ($case['student_last_name_m'] ?? ''));
        $completo = trim($nombre . ' ' . $ap . ' ' . $am);
        $fecha = (string) ($case['reschedule_date'] ?? $case['exam_date'] ?? '');
        $hora = (string) ($case['reschedule_time'] ?? $case['exam_time'] ?? '');
        $token = (string) ($case['access_doc_url'] ?? $case['prep_doc_url'] ?? Env::get('DOCEO_DEFAULT_PREP_URL', '') ?? '');
        $cenniStatusCode = (string) ($case['cenni_status'] ?? 'none');
        $cenniLabels = \App\Payments\OpenPayPaymentService::cenniStatuses();
        $cenniLabel = $cenniLabels[$cenniStatusCode] ?? $cenniStatusCode;
        $folio = trim((string) ($case['cenni_folio'] ?? ''));
        $cenniNotes = trim((string) ($case['cenni_notes'] ?? ''));
        $appUrl = rtrim((string) (Env::get('APP_URL', 'https://pdv.institutodoceo.com') ?? 'https://pdv.institutodoceo.com'), '/');

        return [
            'Nombre' => $nombre,
            'Apellido P' => $ap,
            'Apellido M' => $am,
            'Nombre Completo' => $completo,
            'e-mail' => (string) ($case['student_email'] ?? ''),
            'Teléfono' => (string) ($case['student_phone'] ?? ''),
            'Certificación' => (string) ($case['certification_name'] ?? ''),
            'Fecha' => $fecha,
            'Hora' => $hora,
            'Fecha2' => (string) ($case['reschedule_date'] ?? ''),
            'Hora2' => (string) ($case['reschedule_time'] ?? ''),
            'Folio / ID' => (string) ($case['folio_id'] ?? ''),
            'Clave' => (string) ($case['access_key'] ?? ''),
            'Zoom' => (string) ($case['zoom_url'] ?? ''),
            'TOKEN' => $token,
            'CC' => (string) ($case['cc_email'] ?? ''),
            'iTEP Results' => (string) ($case['results_url'] ?? ''),
            'Canceled' => (string) ($case['cancel_reason'] ?? ''),
            'user' => (string) ($case['moodle_user'] ?? ''),
            'password' => (string) ($case['moodle_password'] ?? ''),
            'Contacto Doceo' => (string) (Env::get('DOCEO_CONTACT_EMAIL', 'info@institutodoceo.com') ?? 'info@institutodoceo.com'),
            'OpenPay CLABE' => (string) ($case['openpay_clabe'] ?? ''),
            'OpenPay Banco' => (string) ($case['openpay_bank'] ?? ''),
            'OpenPay Referencia' => (string) ($case['openpay_reference'] ?? $case['openpay_agreement'] ?? ''),
            'OpenPay Monto' => isset($case['openpay_amount']) ? number_format((float) $case['openpay_amount'], 2, '.', ',') : '',
            'OpenPay Beneficiario' => (string) (Env::get('OPENPAY_BENEFICIARY_NAME', 'Instituto Doceo') ?? 'Instituto Doceo'),
            'CENNI Estatus' => $cenniLabel,
            'CENNI Folio Line' => $folio !== '' ? '<strong>Folio:</strong> ' . htmlspecialchars($folio, ENT_QUOTES, 'UTF-8') . '<br>' : '',
            'CENNI Notas Line' => $cenniNotes !== '' ? '<strong>Detalle:</strong> ' . htmlspecialchars($cenniNotes, ENT_QUOTES, 'UTF-8') . '<br>' : '',
            'CENNI Folio Suffix' => $folio !== '' ? ' (folio ' . htmlspecialchars($folio, ENT_QUOTES, 'UTF-8') . ')' : '',
            'App URL' => $appUrl,
        ];
    }

    /**
     * Actualiza estatus CENNI y opcionalmente notifica al alumno.
     *
     * @return array{status: string, mailed: bool}
     */
    public function updateCenniStatus(
        int $caseId,
        string $status,
        ?string $folio,
        ?string $notes,
        bool $notify,
        ?int $userId
    ): array {
        $allowed = array_keys(\App\Payments\OpenPayPaymentService::cenniStatuses());
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Estatus CENNI inválido.');
        }
        $this->repo->updateCertificationCase($caseId, [
            'cenni_status' => $status,
            'cenni_folio' => $folio,
            'cenni_notes' => $notes,
            'cenni_status_updated_at' => date('Y-m-d H:i:s'),
        ]);

        $mailed = false;
        if ($notify) {
            $code = $status === 'issued' ? 'cenni_emitido' : 'cenni_seguimiento';
            $this->sendTemplate($caseId, $code, $userId);
            $mailed = true;
        }

        return ['status' => $status, 'mailed' => $mailed];
    }

    /** @param array<string, string> $tokens */
    private function render(string $template, array $tokens): string
    {
        $out = $template;
        foreach ($tokens as $key => $value) {
            $out = str_replace(['{{' . $key . '}}', '<<' . $key . '>>'], $value, $out);
        }

        return $out;
    }

    /** @param array<string, mixed> $tpl @param array<string, mixed> $case */
    private function resolveTo(array $tpl, array $case): string
    {
        $mode = (string) ($tpl['to_mode'] ?? 'student');
        if ($mode === 'fixed' && !empty($tpl['to_fixed'])) {
            return (string) $tpl['to_fixed'];
        }
        if ($mode === 'provider') {
            if (!empty($tpl['to_fixed'])) {
                return (string) $tpl['to_fixed'];
            }
            $email = trim((string) ($case['provider_contact_email'] ?? ''));
            if ($email !== '') {
                return $email;
            }
            throw new \RuntimeException('El proveedor no tiene correo de contacto configurado.');
        }
        $email = trim((string) ($case['student_email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('El caso no tiene correo de alumno válido.');
        }

        return $email;
    }

    /** @param array<string, mixed> $tpl @param array<string, mixed> $case */
    private function resolveCc(array $tpl, array $case): ?string
    {
        $mode = (string) ($tpl['cc_mode'] ?? 'none');
        return match ($mode) {
            'fixed' => trim((string) ($tpl['cc_fixed'] ?? '')) ?: null,
            'case_cc', 'tr' => trim((string) ($case['cc_email'] ?? $case['partner_email'] ?? '')) ?: null,
            default => null,
        };
    }
}
