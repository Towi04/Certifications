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
     * Marca pago recibido sin OpenPay (efectivo / transferencia / otro).
     * No envía solicitud al proveedor; eso queda en “Confirmar pago y solicitar” o “Enviar solicitud”.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $paymentFile
     * @return array{payment_confirmed_at: string, payment_method: string, moodle: ?array}
     */
    public function markPaymentReceived(
        int $caseId,
        string $method,
        ?array $paymentFile,
        ?string $note,
        ?int $userId
    ): array {
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }

        $allowed = ['cash', 'transfer', 'openpay', 'other'];
        $method = strtolower(trim($method));
        if (!in_array($method, $allowed, true)) {
            $method = 'other';
        }

        $this->repo->ensurePaymentMethodColumn();
        $now = date('Y-m-d H:i:s');
        $fields = [
            'payment_confirmed_at' => $now,
            'payment_method' => $method,
        ];

        if ($paymentFile && (int) ($paymentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $path = Uploader::store($paymentFile, 'cases/' . $caseId);
            $labels = [
                'cash' => 'Comprobante pago efectivo',
                'transfer' => 'Comprobante transferencia',
                'openpay' => 'Comprobante OpenPay',
                'other' => 'Comprobante de pago',
            ];
            $this->repo->addCaseAttachment($caseId, 'payment', $labels[$method] ?? 'Comprobante de pago', $path, $userId);
            $fields['payment_proof_path'] = $path;
        }

        $note = trim((string) ($note ?? ''));
        if ($note !== '') {
            $stamp = $now . ' Pago manual (' . $method . '): ' . $note;
            $prev = trim((string) ($case['notes'] ?? ''));
            $fields['notes'] = $prev !== '' ? ($prev . "\n" . $stamp) : $stamp;
        }

        $this->repo->updateCertificationCase($caseId, $fields);

        try {
            $this->repo->markCaseStepDoneByKeywords(
                $caseId,
                ['pago', 'payment', 'spei', 'abono'],
                $userId,
                'Pago marcado manualmente: ' . $method
            );
        } catch (\Throwable) {
        }

        $moodle = null;
        try {
            $enrol = new \App\Integrations\MoodleEnrolService($this->repo, new \App\Integrations\MoodleClient(), $this);
            $moodle = $enrol->ensureAccessForCase($caseId, $userId);
        } catch (\Throwable $e) {
            error_log('[PDV] Moodle enrol (mark payment) case #' . $caseId . ': ' . $e->getMessage());
            $moodle = ['error' => $e->getMessage()];
        }

        return [
            'payment_confirmed_at' => $now,
            'payment_method' => $method,
            'moodle' => $moodle,
        ];
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

        try {
            $this->repo->ensurePaymentMethodColumn();
            if (empty($case['payment_method'])) {
                $this->repo->updateCertificationCase($caseId, ['payment_method' => 'other']);
            }
        } catch (\Throwable) {
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

        $moodle = null;
        try {
            $enrol = new \App\Integrations\MoodleEnrolService($this->repo, new \App\Integrations\MoodleClient(), $this);
            $moodle = $enrol->ensureAccessForCase($caseId, $userId);
        } catch (\Throwable $e) {
            error_log('[PDV] Moodle enrol (confirm payment) case #' . $caseId . ': ' . $e->getMessage());
            $moodle = ['error' => $e->getMessage()];
        }

        return ['export' => $export, 'mailed' => $mailed, 'to' => $to, 'moodle' => $moodle];
    }

    /**
     * Sube comprobante (opcional), regenera exportación si aplica y envía plantilla de solicitud al proveedor.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $paymentFile
     * @return array{export: ?array{relative: string, absolute: string, filename: string, mime: string}, mailed: bool, to: ?string, template: ?string}
     */
    public function sendProviderRequest(int $caseId, ?array $paymentFile, ?int $userId, bool $regenerateExport = true): array
    {
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }

        if ($paymentFile && (int) ($paymentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $path = Uploader::store($paymentFile, 'cases/' . $caseId);
            $this->repo->addCaseAttachment($caseId, 'payment', 'Comprobante de pago', $path, $userId);
            $fields = ['payment_proof_path' => $path];
            if (empty($case['payment_confirmed_at'])) {
                $fields['payment_confirmed_at'] = date('Y-m-d H:i:s');
            }
            $this->repo->updateCertificationCase($caseId, $fields);
            $case['payment_proof_path'] = $path;
        }

        $export = null;
        $format = (string) ($case['export_format'] ?? 'none');
        if ($regenerateExport && $format !== '' && $format !== ProviderExportGenerator::FORMAT_NONE) {
            // Recargar caso por si cambiaron fechas
            $case = $this->repo->certificationCaseDetailed($caseId) ?? $case;
            $export = $this->exports->generate($format, $case);
            $this->repo->addCaseAttachment($caseId, 'export', $export['filename'], $export['relative'], $userId);
            $this->repo->updateCertificationCase($caseId, [
                'provider_export_path' => $export['relative'],
            ]);
        }

        $templateCode = trim((string) ($case['provider_request_template'] ?? ''));
        if ($templateCode === '') {
            throw new \RuntimeException(
                'Este protocolo no tiene “Plantilla solicitud a empresa”. Configúrala en Admin → Protocolos.'
            );
        }

        $result = $this->sendTemplate($caseId, $templateCode, $userId, true);
        $this->repo->updateCertificationCase($caseId, [
            'provider_request_sent_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'export' => $export,
            'mailed' => true,
            'to' => $result['to'],
            'template' => $templateCode,
        ];
    }

    /**
     * Guarda nueva fecha/hora de reagenda y notifica al proveedor.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $paymentFile
     * @return array{mailed: bool, to: ?string, template: ?string}
     */
    public function rescheduleAndNotifyProvider(
        int $caseId,
        string $newDate,
        string $newTime,
        ?string $reason,
        ?array $paymentFile,
        ?int $userId,
        bool $notifyProvider = true
    ): array {
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }
        $newDate = trim($newDate);
        $newTime = substr(trim($newTime), 0, 5);
        if ($newDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            throw new \RuntimeException('Indica la nueva fecha de examen (AAAA-MM-DD).');
        }
        if ($newTime === '' || !preg_match('/^\d{2}:\d{2}$/', $newTime)) {
            throw new \RuntimeException('Indica la nueva hora (HH:MM).');
        }

        $fields = [
            'reschedule_date' => $newDate,
            'reschedule_time' => $newTime,
        ];
        $notes = trim((string) ($case['notes'] ?? ''));
        $reason = trim((string) ($reason ?? ''));
        if ($reason !== '') {
            $stamp = date('Y-m-d H:i') . ' Reagenda: ' . $reason;
            $fields['notes'] = $notes !== '' ? ($notes . "\n" . $stamp) : $stamp;
        }
        $this->repo->updateCertificationCase($caseId, $fields);

        try {
            $this->repo->markCaseStepDoneByKeywords(
                $caseId,
                ['reagenda', 'reagendar', 'reprogram'],
                $userId,
                'Reagenda a ' . $newDate . ' ' . $newTime
            );
        } catch (\Throwable) {
        }

        if ($paymentFile && (int) ($paymentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $path = Uploader::store($paymentFile, 'cases/' . $caseId);
            $this->repo->addCaseAttachment($caseId, 'payment', 'Comprobante (reagenda)', $path, $userId);
            $this->repo->updateCertificationCase($caseId, ['payment_proof_path' => $path]);
        }

        if (!$notifyProvider) {
            return ['mailed' => false, 'to' => null, 'template' => null];
        }

        $this->ensureRescheduleMailTemplate();
        $case = $this->repo->certificationCaseDetailed($caseId) ?? $case;
        $templateCode = 'reagenda_solicitud';
        if (!$this->repo->mailTemplateByCode($templateCode)) {
            $templateCode = trim((string) ($case['provider_request_template'] ?? ''));
        }
        if ($templateCode === '') {
            throw new \RuntimeException(
                'No hay plantilla de reagenda ni de solicitud al proveedor. Configura el protocolo o crea “reagenda_solicitud”.'
            );
        }

        // Regenerar exportación con nueva fecha para el adjunto
        $format = (string) ($case['export_format'] ?? 'none');
        if ($format !== '' && $format !== ProviderExportGenerator::FORMAT_NONE) {
            $export = $this->exports->generate($format, $case);
            $this->repo->addCaseAttachment($caseId, 'export', $export['filename'], $export['relative'], $userId);
            $this->repo->updateCertificationCase($caseId, [
                'provider_export_path' => $export['relative'],
            ]);
        }

        $result = $this->sendTemplate($caseId, $templateCode, $userId, true);

        return ['mailed' => true, 'to' => $result['to'], 'template' => $templateCode];
    }

    public function ensureRescheduleMailTemplate(): void
    {
        if ($this->repo->mailTemplateByCode('reagenda_solicitud')) {
            return;
        }
        try {
            $this->repo->saveMailTemplate([
                'code' => 'reagenda_solicitud',
                'name' => 'Reagenda — Solicitud al proveedor',
                'audience' => 'provider',
                'to_mode' => 'provider',
                'to_fixed' => '',
                'cc_mode' => 'none',
                'cc_fixed' => '',
                'subject' => 'Reagenda {{Certificación}} — {{Nombre Completo}} — {{Fecha}}',
                'body_html' => '<p>¡Hola!</p>'
                    . '<p>Solicito <strong>reagendar</strong> el examen:</p>'
                    . '<p>Certificación: <strong>{{Certificación}}</strong><br>'
                    . 'Alumno: <strong>{{Nombre Completo}}</strong><br>'
                    . 'Nueva fecha: <strong>{{Fecha}}</strong><br>'
                    . 'Nueva hora: <strong>{{Hora}}</strong></p>'
                    . '<p>Adjunto exportación y comprobante cuando aplique.</p>'
                    . '<p>Instituto DOCEO<br>{{Contacto Doceo}}</p>',
                'attach_export' => 1,
                'is_active' => 1,
            ]);
        } catch (\Throwable) {
        }
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
        $bodyInner = $this->render((string) $tpl['body_html'], $tokens);
        $bodyHtml = $this->wrapBrandedHtml($bodyInner, $tokens);
        $bodyText = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $bodyInner)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

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

        // Adjuntar reglamento firmado (o original) si la plantilla lo pide
        if (!empty($tpl['attach_regulation'])) {
            $regAtt = $this->regulationAttachmentForCase($case);
            if ($regAtt !== null) {
                $attachments[] = $regAtt;
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

        $regUrls = $this->regulationUrlsForCase($case, $appUrl);

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
            'OpenPay Beneficiario' => (string) (Env::get('OPENPAY_BENEFICIARY_NAME', 'Instituto DOCEO') ?? 'Instituto DOCEO'),
            'OpenPay SPEI URL' => $appUrl . '/pago/spei?id=' . (int) ($case['id'] ?? 0),
            'Logo URL' => $appUrl . '/assets/brand/logo-doceo.png',
            'Escudo URL' => $appUrl . '/assets/brand/escudo.png',
            'CENNI Estatus' => $cenniLabel,
            'CENNI Folio Line' => $folio !== '' ? '<strong>Folio:</strong> ' . htmlspecialchars($folio, ENT_QUOTES, 'UTF-8') . '<br>' : '',
            'CENNI Notas Line' => $cenniNotes !== '' ? '<strong>Detalle:</strong> ' . htmlspecialchars($cenniNotes, ENT_QUOTES, 'UTF-8') . '<br>' : '',
            'CENNI Folio Suffix' => $folio !== '' ? ' (folio ' . htmlspecialchars($folio, ENT_QUOTES, 'UTF-8') . ')' : '',
            'App URL' => $appUrl,
            'Reglamento URL' => $regUrls['original_url'],
            'Reglamento Firmado URL' => $regUrls['signed_url'],
            'Reglamento Boton' => $regUrls['button_html'],
            'Reglamento Firmado Boton' => $regUrls['signed_button_html'],
        ];
    }

    /**
     * URLs del reglamento original y del PDF firmado para tokens de correo.
     *
     * @param array<string, mixed> $case
     * @return array{original_url:string,signed_url:string,button_html:string,signed_button_html:string,original_path:?string,signed_path:?string}
     */
    private function regulationUrlsForCase(array $case, string $appUrl): array
    {
        $signedRel = trim((string) ($case['regulation_signed_pdf_path'] ?? ''));
        $originalRel = '';
        $docId = (int) ($case['regulation_document_id'] ?? 0);
        if ($docId > 0) {
            $doc = $this->repo->document($docId);
            $originalRel = trim((string) ($doc['file_path'] ?? ''));
        }
        if ($originalRel === '') {
            $certId = (int) ($case['certification_id'] ?? 0);
            if ($certId > 0) {
                $doc = $this->repo->regulationDocumentForCertification($certId);
                $originalRel = trim((string) ($doc['file_path'] ?? ''));
            }
        }

        $media = static function (string $rel) use ($appUrl): string {
            if ($rel === '') {
                return '';
            }

            return $appUrl . '/media?f=' . rawurlencode($rel);
        };

        $signedUrl = $media($signedRel);
        $originalUrl = $media($originalRel);
        // Prefer signed for the generic button; fall back to original
        $primaryUrl = $signedUrl !== '' ? $signedUrl : $originalUrl;
        $primaryLabel = $signedUrl !== '' ? 'Descargar reglamento firmado' : 'Abrir reglamento';

        $button = static function (string $url, string $label): string {
            if ($url === '') {
                return '';
            }
            $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

            return '<p style="margin:16px 0;">'
                . '<a href="' . $safeUrl . '" target="_blank" rel="noopener" '
                . 'style="display:inline-block;background:#315285;color:#ffffff;text-decoration:none;'
                . 'padding:12px 18px;border-radius:8px;font-weight:700;font-size:14px;">'
                . $safeLabel . '</a></p>';
        };

        return [
            'original_url' => $originalUrl,
            'signed_url' => $signedUrl,
            'button_html' => $button($primaryUrl, $primaryLabel),
            'signed_button_html' => $button($signedUrl, 'Descargar reglamento firmado'),
            'original_path' => $originalRel !== '' ? $originalRel : null,
            'signed_path' => $signedRel !== '' ? $signedRel : null,
        ];
    }

    /**
     * @param array<string, mixed> $case
     * @return array{path:string,name:string,mime:string}|null
     */
    private function regulationAttachmentForCase(array $case): ?array
    {
        $signedRel = trim((string) ($case['regulation_signed_pdf_path'] ?? ''));
        if ($signedRel !== '') {
            $abs = Uploader::absolutePath($signedRel);
            if ($abs) {
                return [
                    'path' => $abs,
                    'name' => 'reglamento_firmado_' . basename($abs),
                    'mime' => 'application/pdf',
                ];
            }
        }

        $urls = $this->regulationUrlsForCase($case, '');
        $originalRel = (string) ($urls['original_path'] ?? '');
        if ($originalRel !== '') {
            $abs = Uploader::absolutePath($originalRel);
            if ($abs) {
                $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                return [
                    'path' => $abs,
                    'name' => 'reglamento_' . basename($abs),
                    'mime' => $ext === 'pdf' ? 'application/pdf' : 'application/octet-stream',
                ];
            }
        }

        return null;
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

    /** @return array<string, string> Clave token => descripción para el editor admin */
    public static function tokenHelp(): array
    {
        return [
            'Nombre' => 'Nombre(s) del alumno',
            'Apellido P' => 'Apellido paterno',
            'Apellido M' => 'Apellido materno',
            'Nombre Completo' => 'Nombre completo',
            'e-mail' => 'Correo del alumno',
            'Teléfono' => 'Teléfono',
            'Certificación' => 'Nombre de la certificación',
            'Fecha' => 'Fecha de examen (o reagenda)',
            'Hora' => 'Hora de examen',
            'Fecha2' => 'Fecha de reagenda',
            'Hora2' => 'Hora de reagenda',
            'Folio / ID' => 'Folio / ID del proveedor',
            'Clave' => 'Clave / password del examen',
            'Zoom' => 'URL de Zoom',
            'TOKEN' => 'URL de guía / prep',
            'CC' => 'Correo en copia (TR)',
            'iTEP Results' => 'URL de resultados',
            'Canceled' => 'Motivo de cancelación',
            'user' => 'Usuario Moodle',
            'password' => 'Contraseña Moodle',
            'Contacto Doceo' => 'Correo de contacto Doceo',
            'OpenPay CLABE' => 'CLABE SPEI',
            'OpenPay Banco' => 'Banco SPEI',
            'OpenPay Referencia' => 'Referencia / convenio',
            'OpenPay Monto' => 'Monto a pagar',
            'OpenPay Beneficiario' => 'Beneficiario SPEI',
            'OpenPay SPEI URL' => 'URL ficha SPEI Doceo',
            'CENNI Estatus' => 'Estatus CENNI legible',
            'CENNI Folio Line' => 'Línea HTML con folio CENNI',
            'CENNI Notas Line' => 'Línea HTML con notas CENNI',
            'CENNI Folio Suffix' => 'Sufijo con folio',
            'App URL' => 'URL del PDV',
            'Logo URL' => 'URL del logo Doceo',
            'Escudo URL' => 'URL del escudo',
            'Reglamento URL' => 'Link al PDF original del reglamento',
            'Reglamento Firmado URL' => 'Link al PDF de evidencia firmado por el alumno',
            'Reglamento Boton' => 'Botón HTML (firmado si existe; si no, original)',
            'Reglamento Firmado Boton' => 'Botón HTML solo del PDF firmado',
        ];
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

    /** @param array<string, string> $tokens */
    private function wrapBrandedHtml(string $innerHtml, array $tokens): string
    {
        $logo = htmlspecialchars((string) ($tokens['Logo URL'] ?? ''), ENT_QUOTES, 'UTF-8');
        $app = htmlspecialchars((string) ($tokens['App URL'] ?? ''), ENT_QUOTES, 'UTF-8');
        $brand = htmlspecialchars((string) (Env::get('APP_NAME', 'Instituto DOCEO') ?? 'Instituto DOCEO'), ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="es"><body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:24px 12px;">'
            . '<tr><td align="center">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #d7dde5;border-radius:12px;overflow:hidden;">'
            . '<tr><td style="padding:20px 24px;border-bottom:3px solid #315285;background:#ffffff;">'
            . ($logo !== ''
                ? '<a href="' . $app . '" style="text-decoration:none;"><img src="' . $logo . '" alt="' . $brand . '" width="220" style="display:block;border:0;max-width:220px;height:auto;"></a>'
                : '<strong style="color:#315285;font-size:20px;">' . $brand . '</strong>')
            . '<p style="margin:8px 0 0;font-size:11px;letter-spacing:0.08em;color:#315285;font-weight:700;">BE DIFFERENT, BE BETTER!</p>'
            . '</td></tr>'
            . '<tr><td style="padding:24px;font-size:15px;line-height:1.55;">' . $innerHtml . '</td></tr>'
            . '<tr><td style="padding:16px 24px;background:#315285;color:#ffffff;font-size:12px;">'
            . '<strong>' . $brand . '</strong><br>Certificaciones · ' . $app
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
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
