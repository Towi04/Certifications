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
            $path = $this->storePaymentProof($paymentFile, $caseId);
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
        $this->repo->persistCaseFileShareTokens($caseId);

        try {
            $this->repo->markCaseStepDoneByKeywords(
                $caseId,
                ['pago', 'payment', 'spei', 'abono'],
                $userId,
                'Pago marcado manualmente: ' . $method
            );
        } catch (\Throwable) {
        }

        $fulfill = null;
        try {
            $fulfill = (new \App\Services\ExamFulfillmentService($this->repo, $this))->fulfillAfterPayment($caseId, $userId);
        } catch (\Throwable $e) {
            error_log('[PDV] Fulfill (mark payment) case #' . $caseId . ': ' . $e->getMessage());
            $fulfill = ['moodle' => ['error' => $e->getMessage()]];
        }

        try {
            (new \App\Workflow\ActionRunner($this->repo, new \App\Workflow\ActionRepository(), $this))
                ->runTriggers($caseId, 'payment_confirmed', $userId);
        } catch (\Throwable $e) {
            error_log('[PDV] Action triggers payment_confirmed #' . $caseId . ': ' . $e->getMessage());
        }

        try {
            $this->sendTemplate($caseId, 'pago_confirmado', $userId);
        } catch (\Throwable $e) {
            error_log('[PDV] pago_confirmado (mark payment) case #' . $caseId . ': ' . $e->getMessage());
        }

        return [
            'payment_confirmed_at' => $now,
            'payment_method' => $method,
            'moodle' => $fulfill['moodle'] ?? null,
            'fulfill' => $fulfill,
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
        $this->repo->ensureUksFlowSchemaAndSeeds();
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }

        if (empty($case['payment_confirmed_at'])) {
            $this->repo->updateCertificationCase($caseId, [
                'payment_confirmed_at' => date('Y-m-d H:i:s'),
            ]);
            $case['payment_confirmed_at'] = date('Y-m-d H:i:s');
        }

        // Si el protocolo pide solicitud al proveedor (UKS), el archivo es Doceo→proveedor.
        // En otros flujos se trata como comprobante del alumno.
        if ($paymentFile && (int) ($paymentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $path = $this->storePaymentProof($paymentFile, $caseId);
            $asProviderProof = $this->protocolNeedsProviderPaymentProof($case)
                || trim((string) ($case['provider_request_template'] ?? '')) !== '';
            if ($asProviderProof) {
                $this->repo->addCaseAttachment($caseId, 'provider_payment', 'Comprobante Doceo → proveedor', $path, $userId);
                $case['provider_payment_proof_path'] = $path;
            } else {
                $this->repo->addCaseAttachment($caseId, 'payment', 'Comprobante de pago', $path, $userId);
                $this->repo->updateCertificationCase($caseId, ['payment_proof_path' => $path]);
                $case['payment_proof_path'] = $path;
            }
            $this->repo->persistCaseFileShareTokens($caseId);
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
        $this->repo->persistCaseFileShareTokens($caseId);

        $templateCode = trim((string) ($case['provider_request_template'] ?? ''));
        $mailed = false;
        $to = null;
        $mailSkip = null;
        $linksOnly = [];
        if ($templateCode !== '') {
            $case = $this->repo->certificationCaseDetailed($caseId) ?? $case;
            if ($this->protocolNeedsProviderPaymentProof($case)
                && trim((string) ($case['provider_payment_proof_path'] ?? '')) === '') {
                $mailSkip = 'Falta el comprobante Doceo → UKS. Súbelo al confirmar/solicitar (no uses el del alumno).';
            } else {
                $tpl = $this->repo->mailTemplateByCode($templateCode);
                if (!$tpl || !(int) ($tpl['is_active'] ?? 0)) {
                    $mailSkip = 'La plantilla “' . $templateCode . '” no existe o está inactiva en Admin → Correos.';
                } else {
                    $result = $this->sendTemplate($caseId, $templateCode, $userId, true);
                    $mailed = true;
                    $to = $result['to'];
                    $linksOnly = $result['links_only'] ?? [];
                    $this->repo->updateCertificationCase($caseId, [
                        'provider_request_sent_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        } else {
            $mailSkip = 'El protocolo no tiene “Plantilla solicitud a empresa”. Configúrala en Admin → Protocolos.';
        }

        $fulfill = null;
        try {
            $fulfill = (new \App\Services\ExamFulfillmentService($this->repo, $this))->fulfillAfterPayment($caseId, $userId);
        } catch (\Throwable $e) {
            error_log('[PDV] Fulfill (confirm payment) case #' . $caseId . ': ' . $e->getMessage());
            $fulfill = ['moodle' => ['error' => $e->getMessage()]];
        }

        return [
            'export' => $export,
            'mailed' => $mailed,
            'to' => $to,
            'template' => $templateCode !== '' ? $templateCode : null,
            'mail_skip' => $mailSkip,
            'links_only' => $linksOnly ?? [],
            'moodle' => $fulfill['moodle'] ?? null,
            'fulfill' => $fulfill,
        ];
    }

    /**
     * Sube comprobante (opcional), regenera exportación si aplica y envía plantilla de solicitud al proveedor.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $paymentFile
     * @return array{export: ?array{relative: string, absolute: string, filename: string, mime: string}, mailed: bool, to: ?string, template: ?string}
     */
    public function sendProviderRequest(int $caseId, ?array $paymentFile, ?int $userId, bool $regenerateExport = true): array
    {
        $this->repo->ensureUksFlowSchemaAndSeeds();
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }

        // Comprobante Doceo → proveedor (UKS), no el del alumno.
        if ($paymentFile && (int) ($paymentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $path = $this->storePaymentProof($paymentFile, $caseId);
            $this->repo->addCaseAttachment($caseId, 'provider_payment', 'Comprobante Doceo → proveedor', $path, $userId);
            $case['provider_payment_proof_path'] = $path;
        }

        $case = $this->repo->certificationCaseDetailed($caseId) ?? $case;
        if ($this->protocolNeedsProviderPaymentProof($case)
            && trim((string) ($case['provider_payment_proof_path'] ?? '')) === '') {
            throw new \RuntimeException(
                'Sube el comprobante de pago Doceo → UKS (el de nosotros al proveedor, no el del alumno).'
            );
        }

        $export = null;
        $format = (string) ($case['export_format'] ?? 'none');
        if ($regenerateExport && $format !== '' && $format !== ProviderExportGenerator::FORMAT_NONE) {
            $export = $this->exports->generate($format, $case);
            $this->repo->addCaseAttachment($caseId, 'export', $export['filename'], $export['relative'], $userId);
            $this->repo->updateCertificationCase($caseId, [
                'provider_export_path' => $export['relative'],
            ]);
        }
        $this->repo->persistCaseFileShareTokens($caseId);

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
        try {
            $this->repo->markCaseStepDoneByKeywords(
                $caseId,
                ['solicitar', 'uks', 'proveedor', 'correo'],
                $userId,
                'Solicitud enviada al proveedor (' . $templateCode . ')'
            );
        } catch (\Throwable) {
        }

        return [
            'export' => $export,
            'mailed' => true,
            'to' => $result['to'],
            'template' => $templateCode,
            'links_only' => $result['links_only'] ?? [],
            'attachments' => $result['attachments'] ?? 0,
        ];
    }

    /**
     * Agradecimiento post-examen UKS: cierra el contacto operativo del examen
     * y deja abierta la puerta a dudas CENNI (monitoreo en UKS).
     *
     * @return array{ok: bool, to?: ?string, error?: string}
     */
    public function sendUksPostExamThanks(int $caseId, ?int $userId = null): array
    {
        $this->repo->ensureUksFlowSchemaAndSeeds();
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            return ['ok' => false, 'error' => 'Caso no encontrado'];
        }
        try {
            $result = $this->sendTemplate($caseId, 'uks_post_examen', $userId, false);
            $now = date('Y-m-d H:i:s');
            $fields = [
                'exam_presented_at' => $case['exam_presented_at'] ?? $now,
                'post_exam_thanks_sent_at' => $now,
            ];
            if (empty($case['exam_presented_at'])) {
                $fields['exam_presented_at'] = $now;
            }
            try {
                $this->repo->updateCertificationCase($caseId, $fields + ['status' => 'examen_presentado']);
            } catch (\Throwable) {
                $this->repo->updateCertificationCase($caseId, $fields);
            }
            try {
                $this->repo->markCaseStepDoneByKeywords(
                    $caseId,
                    ['agradec', 'constancia', 'post', 'finaliz'],
                    $userId,
                    'Correo post-examen (uks_post_examen) enviado'
                );
            } catch (\Throwable) {
            }

            return ['ok' => true, 'to' => $result['to'] ?? null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @param array<string, mixed> $case */
    private function protocolNeedsProviderPaymentProof(array $case): bool
    {
        $code = strtoupper(trim((string) ($case['protocol_code'] ?? '')));
        if ($code !== '' && str_starts_with($code, 'UKS') && $code !== 'UKS_CENNI') {
            return true;
        }

        return (string) ($case['export_format'] ?? '') === 'uks_csv';
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
            $path = $this->storePaymentProof($paymentFile, $caseId);
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
     * @return array{to: string, subject: string, attachments: int, links_only: list<string>}
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

        $isProviderRequest = $forceAttachExport
            || ($tpl['audience'] ?? '') === 'provider'
            || ($tpl['to_mode'] ?? '') === 'provider';

        // Asegura links públicos antes de armar tokens (comprobante / exportación)
        $this->ensureCaseShareLinks($case);

        $tokens = $this->tokens($case);
        $subject = $this->render((string) $tpl['subject'], $tokens);
        $bodyInner = $this->render((string) $tpl['body_html'], $tokens);

        $to = $this->resolveTo($tpl, $case, $isProviderRequest);
        $cc = $this->resolveCc($tpl, $case);

        // Sin adjuntos MIME: en Neubox rompen la entrega. Solo enlaces públicos /c/{token}.
        $linkRows = [];
        $includeExportLink = $forceAttachExport || !empty($tpl['attach_export']);
        if ($includeExportLink && trim((string) ($tokens['Exportacion URL'] ?? '')) !== '') {
            $linkRows[] = [
                'label' => 'Archivo de exportación / registro',
                'url' => (string) $tokens['Exportacion URL'],
                'name' => 'exportacion',
            ];
        }
        if ($isProviderRequest && trim((string) ($tokens['Comprobante URL'] ?? '')) !== '') {
            $useProviderProof = trim((string) ($case['provider_payment_proof_path'] ?? '')) !== ''
                || trim((string) ($case['provider_payment_proof_share_token'] ?? '')) !== '';
            $linkRows[] = [
                'label' => $useProviderProof ? 'Comprobante Doceo → proveedor' : 'Comprobante de pago',
                'url' => (string) $tokens['Comprobante URL'],
                'name' => 'comprobante',
            ];
        }
        if (!empty($tpl['attach_regulation'])) {
            $regUrl = trim((string) ($tokens['Reglamento Firmado URL'] ?? ''));
            if ($regUrl === '') {
                $regUrl = trim((string) ($tokens['Reglamento URL'] ?? ''));
            }
            if ($regUrl !== '') {
                $linkRows[] = [
                    'label' => 'Reglamento',
                    'url' => $regUrl,
                    'name' => 'reglamento',
                ];
            }
        }

        if ($linkRows !== []) {
            $bodyInner .= $this->attachmentsLinkBlockHtml($linkRows, true);
        }

        $bodyHtml = $this->wrapBrandedHtml($bodyInner, $tokens);
        $bodyText = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $bodyInner)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $exportRel = trim((string) ($case['provider_export_path'] ?? ''));

        try {
            $this->mailer->send($to, $subject, $bodyText, [
                'cc' => $cc,
                'html' => true,
                'body_html' => $bodyHtml,
                'attachments' => [],
            ]);
            $endpoint = \App\Integrations\Mailer::lastEndpoint();
            $note = 'sin_adjuntos_mime; links=' . count($linkRows);
            if (is_array($endpoint) && !empty($endpoint['transport'])) {
                $note .= '; via=' . $endpoint['transport'];
            }
            $this->repo->logCaseMail([
                'case_id' => $caseId,
                'template_code' => $templateCode,
                'to_email' => $to,
                'cc_email' => $cc,
                'subject' => $subject,
                'attachment_path' => $exportRel !== '' ? $exportRel : null,
                'status' => 'sent',
                'error_message' => $note,
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

        return [
            'to' => $to,
            'subject' => $subject,
            'attachments' => 0,
            'links_only' => array_column($linkRows, 'label'),
        ];
    }

    /** @param array<string, mixed> $case */
    private function ensureCaseShareLinks(array &$case): void
    {
        $caseId = (int) ($case['id'] ?? 0);
        if ($caseId < 1) {
            return;
        }
        $providerPayRel = trim((string) ($case['provider_payment_proof_path'] ?? ''));
        if ($providerPayRel !== '') {
            $this->repo->ensureCaseFileShare($caseId, 'provider_payment', $providerPayRel, 'Comprobante Doceo → proveedor');
        }
        $payRel = trim((string) ($case['payment_proof_path'] ?? ''));
        if ($payRel !== '') {
            $this->repo->ensureCaseFileShare($caseId, 'payment', $payRel, 'Comprobante de pago');
        }
        $exportRel = trim((string) ($case['provider_export_path'] ?? ''));
        if ($exportRel !== '') {
            $this->repo->ensureCaseFileShare($caseId, 'export', $exportRel, 'Exportación proveedor');
        }
    }

    /**
     * @param list<array{label:string,url:string,name:string}> $rows
     */
    private function attachmentsLinkBlockHtml(array $rows, bool $oversizedNote): string
    {
        $html = '<hr style="border:none;border-top:1px solid #d7dde5;margin:24px 0 16px">'
            . '<p style="margin:0 0 8px;font-size:14px;color:' . MailBranding::BRAND_BLUE . ';"><strong>Archivos de esta solicitud</strong></p>'
            . '<p style="margin:0 0 12px;font-size:13px;color:' . MailBranding::BRAND_BLUE . ';">'
            . 'Descarga los archivos con estos enlaces (no van como adjunto del correo):</p>';
        foreach ($rows as $row) {
            $html .= MailBranding::button($row['label'], $row['url']);
        }
        unset($oversizedNote);

        return $html;
    }

    /**
     * Guarda comprobante: comprime imágenes para que el correo al proveedor no se descarte por tamaño.
     *
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     */
    private function storePaymentProof(array $file, int $caseId): string
    {
        $name = strtolower((string) ($file['name'] ?? ''));
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return Uploader::storeImage($file, 'cases/' . $caseId, 1600, 1600);
        }

        return Uploader::store($file, 'cases/' . $caseId);
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
        $cenniDownload = trim((string) ($case['cenni_download_url'] ?? ''));
        $cenniSep = trim((string) ($case['cenni_sep_url'] ?? ''));
        $appUrl = rtrim((string) (Env::get('APP_URL', 'https://pdv.institutodoceo.com') ?? 'https://pdv.institutodoceo.com'), '/');

        $regUrls = $this->regulationUrlsForCase($case, $appUrl);
        $linkTokens = $this->providerLinkTokensForCase($case);
        $fileLinks = $this->caseFileLinkTokens($case, $appUrl);
        $cenniDoc = $this->cenniInstructionTokens($case, $appUrl);

        return array_merge([
            'Nombre' => $nombre,
            'Apellido P' => $ap,
            'Apellido M' => $am,
            'Nombre Completo' => $completo,
            'e-mail' => (string) ($case['student_email'] ?? ''),
            'Teléfono' => (string) ($case['student_phone'] ?? ''),
            'CURP' => (string) ($case['student_curp'] ?? ''),
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
            'iTEP Results' => \App\Support\Str::externalUrl($case['results_url'] ?? ''),
            'Score URL' => \App\Support\Str::externalUrl($case['score_url'] ?? ''),
            'Certificate URL' => \App\Support\Str::externalUrl($case['certificate_url'] ?? ''),
            'Resultados Line' => self::linkLine('Resultados', (string) ($case['results_url'] ?? '')),
            'Score Line' => self::linkLine('Score report', (string) ($case['score_url'] ?? '')),
            'Certificate Line' => self::linkLine('Certificate', (string) ($case['certificate_url'] ?? '')),
            'Canceled' => trim((string) ($case['invalidation_reason'] ?? '')) !== ''
                ? (string) $case['invalidation_reason']
                : (string) ($case['cancel_reason'] ?? ''),
            'user' => (string) ($case['moodle_user'] ?? ''),
            'password' => (string) ($case['moodle_password'] ?? ''),
            'Contacto Doceo' => (string) (Env::get('DOCEO_CONTACT_EMAIL', 'info@institutodoceo.com') ?? 'info@institutodoceo.com'),
            'OpenPay CLABE' => (string) ($case['openpay_clabe'] ?? ''),
            'OpenPay Banco' => (string) ($case['openpay_bank'] ?? ''),
            'OpenPay Referencia' => (string) ($case['openpay_reference'] ?? $case['openpay_agreement'] ?? ''),
            'OpenPay Monto' => isset($case['openpay_amount']) ? number_format((float) $case['openpay_amount'], 2, '.', ',') : '',
            'OpenPay Beneficiario' => (string) (Env::get('OPENPAY_BENEFICIARY_NAME', 'Instituto DOCEO') ?? 'Instituto DOCEO'),
            'OpenPay SPEI URL' => $appUrl . '/pago/spei?id=' . (int) ($case['id'] ?? 0),
            'Logo URL' => MailBranding::logoUrl(),
            'Escudo URL' => $appUrl . '/assets/brand/escudo.png',
            'CENNI Estatus' => $cenniLabel,
            'CENNI Folio Line' => $folio !== '' ? '<strong>Folio:</strong> ' . htmlspecialchars($folio, ENT_QUOTES, 'UTF-8') . '<br>' : '',
            'CENNI Notas Line' => $cenniNotes !== ''
                ? '<strong>Detalle:</strong> ' . nl2br(htmlspecialchars($cenniNotes, ENT_QUOTES, 'UTF-8')) . '<br>'
                : '',
            'CENNI Folio Suffix' => $folio !== '' ? ' (folio ' . htmlspecialchars($folio, ENT_QUOTES, 'UTF-8') . ')' : '',
            'CENNI Descarga URL' => $cenniDownload,
            'CENNI Descarga Line' => self::linkLine('Descarga de tu CENNI', $cenniDownload),
            'CENNI SEP URL' => $cenniSep,
            'CENNI SEP Line' => self::linkLine('Consulta oficial SEP', $cenniSep),
            'CENNI Doc URL' => $cenniDoc['url'],
            'CENNI Tramite Line' => $cenniDoc['line'],
            'App URL' => $appUrl,
            'Reglamento URL' => $regUrls['original_url'],
            'Reglamento Firmado URL' => $regUrls['signed_url'],
            'Reglamento Boton' => $regUrls['button_html'],
            'Reglamento Firmado Boton' => $regUrls['signed_button_html'],
            'Comprobante URL' => $fileLinks['payment_url'],
            'Comprobante Boton' => $fileLinks['payment_button'],
            'Exportacion URL' => $fileLinks['export_url'],
            'Exportacion Boton' => $fileLinks['export_button'],
        ], $linkTokens);
    }

    /**
     * @param array<string, mixed> $case
     * @return array{url:string,line:string}
     */
    private function cenniInstructionTokens(array $case, string $appUrl): array
    {
        $empty = ['url' => '', 'line' => ''];
        $process = (string) ($case['cenni_process'] ?? 'none');
        $uksLine = '';
        if ($process === 'uks_external') {
            $uksLine = '<p>El trámite CENNI continúa ante UKS/SEP (tú subes los docs en su plataforma, máx. 15 días). '
                . 'Revisa también spam. Si tienes dudas o no te llega el aviso, contáctanos: podemos consultar el estatus en UKS.</p>';
        }

        $certId = (int) ($case['certification_id'] ?? 0);
        if ($certId <= 0) {
            return ['url' => '', 'line' => $uksLine];
        }
        try {
            $docs = $this->repo->certificationDocumentsByStage($certId, 'cenni');
        } catch (\Throwable) {
            return ['url' => '', 'line' => $uksLine];
        }
        if ($docs === []) {
            return ['url' => '', 'line' => $uksLine];
        }
        $doc = $docs[0];
        $path = trim((string) ($doc['file_path'] ?? ''));
        if ($path === '') {
            return ['url' => '', 'line' => $uksLine];
        }
        $title = trim((string) ($doc['title'] ?? 'Instrucciones trámite CENNI')) ?: 'Instrucciones trámite CENNI';
        $url = $appUrl . '/media?f=' . rawurlencode($path) . '&download=1&name=' . rawurlencode($title);
        $docLine = self::linkLine($title, $url);

        return [
            'url' => $url,
            'line' => trim($uksLine . $docLine),
        ];
    }

    /**
     * @param array<string, mixed> $case
     * @return array{payment_url:string,payment_button:string,export_url:string,export_button:string}
     */
    private function caseFileLinkTokens(array $case, string $appUrl): array
    {
        $empty = [
            'payment_url' => '',
            'payment_button' => '',
            'export_url' => '',
            'export_button' => '',
        ];
        $caseId = (int) ($case['id'] ?? 0);
        if ($caseId < 1) {
            return $empty;
        }

        $shareFromToken = static function (string $token) use ($appUrl): string {
            $token = trim($token);
            if ($token === '') {
                return '';
            }
            $base = rtrim($appUrl, '/');

            return $base . '/c/' . rawurlencode($token);
        };

        // Preferir comprobante Doceo → proveedor (UKS); si no hay, el del alumno.
        $paymentUrl = $shareFromToken((string) ($case['provider_payment_proof_share_token'] ?? ''));
        $paymentLabel = 'Descargar comprobante Doceo → proveedor';
        if ($paymentUrl === '') {
            $providerPayRel = trim((string) ($case['provider_payment_proof_path'] ?? ''));
            if ($providerPayRel !== '') {
                $att = $this->repo->ensureCaseFileShare($caseId, 'provider_payment', $providerPayRel, 'Comprobante Doceo → proveedor');
                if ($att) {
                    $paymentUrl = $this->repo->caseAttachmentShareUrl($att, $appUrl);
                    $token = trim((string) ($att['share_token'] ?? ''));
                    if ($token !== '') {
                        $this->repo->updateCertificationCase($caseId, ['provider_payment_proof_share_token' => $token]);
                    }
                }
            } else {
                $att = $this->repo->latestCaseAttachment($caseId, 'provider_payment');
                if ($att) {
                    $paymentUrl = $this->repo->caseAttachmentShareUrl($att, $appUrl);
                }
            }
        }
        if ($paymentUrl === '') {
            $paymentLabel = 'Descargar comprobante de pago';
            $paymentUrl = $shareFromToken((string) ($case['payment_proof_share_token'] ?? ''));
            if ($paymentUrl === '') {
                $payRel = trim((string) ($case['payment_proof_path'] ?? ''));
                if ($payRel !== '') {
                    $att = $this->repo->ensureCaseFileShare($caseId, 'payment', $payRel, 'Comprobante de pago');
                    if ($att) {
                        $paymentUrl = $this->repo->caseAttachmentShareUrl($att, $appUrl);
                        $token = trim((string) ($att['share_token'] ?? ''));
                        if ($token !== '') {
                            $this->repo->updateCertificationCase($caseId, ['payment_proof_share_token' => $token]);
                        }
                    }
                } else {
                    $att = $this->repo->latestCaseAttachment($caseId, 'payment');
                    if ($att) {
                        $paymentUrl = $this->repo->caseAttachmentShareUrl($att, $appUrl);
                    }
                }
            }
        }

        $exportUrl = $shareFromToken((string) ($case['provider_export_share_token'] ?? ''));
        if ($exportUrl === '') {
            $exportRel = trim((string) ($case['provider_export_path'] ?? ''));
            if ($exportRel !== '') {
                $att = $this->repo->ensureCaseFileShare($caseId, 'export', $exportRel, 'Exportación proveedor');
                if ($att) {
                    $exportUrl = $this->repo->caseAttachmentShareUrl($att, $appUrl);
                    $token = trim((string) ($att['share_token'] ?? ''));
                    if ($token !== '') {
                        $this->repo->updateCertificationCase($caseId, ['provider_export_share_token' => $token]);
                    }
                }
            } else {
                $att = $this->repo->latestCaseAttachment($caseId, 'export');
                if ($att) {
                    $exportUrl = $this->repo->caseAttachmentShareUrl($att, $appUrl);
                }
            }
        }

        return [
            'payment_url' => $paymentUrl,
            'payment_button' => self::linkButton($paymentLabel, $paymentUrl),
            'export_url' => $exportUrl,
            'export_button' => self::linkButton('Descargar exportación / registro', $exportUrl),
        ];
    }

    /**
     * Tokens dinámicos de links del proveedor aplicables al caso.
     *
     * @param array<string, mixed> $case
     * @return array<string, string>
     */
    private function providerLinkTokensForCase(array $case): array
    {
        $certId = (int) ($case['certification_id'] ?? 0);
        $tokens = [
            'Links Alumno' => '',
            'Links Estudio' => '',
            'Links Software' => '',
            'Links Examen' => '',
        ];
        if ($certId < 1) {
            return $tokens;
        }

        try {
            $links = $this->repo->providerLinksForCertification($certId, true);
        } catch (\Throwable) {
            return $tokens;
        }

        $byType = [
            'study_material' => [],
            'software' => [],
            'exam_portal' => [],
        ];
        $allLines = [];

        foreach ($links as $link) {
            $code = strtoupper(trim((string) ($link['code'] ?? '')));
            $label = trim((string) ($link['label'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            $type = (string) ($link['link_type'] ?? 'other');
            if ($code === '' || $url === '') {
                continue;
            }

            $tokens['Link ' . $code] = self::linkLine($label !== '' ? $label : $code, $url);
            $tokens['Link ' . $code . ' URL'] = $url;
            $tokens['Link ' . $code . ' Boton'] = self::linkButton($label !== '' ? $label : 'Abrir enlace', $url);

            $line = self::linkLine($label !== '' ? $label : $code, $url);
            $allLines[] = $line;
            if (isset($byType[$type])) {
                $byType[$type][] = $line;
            }
        }

        $tokens['Links Alumno'] = implode('', $allLines);
        $tokens['Links Estudio'] = implode('', $byType['study_material']);
        $tokens['Links Software'] = implode('', $byType['software']);
        $tokens['Links Examen'] = implode('', $byType['exam_portal']);

        return $tokens;
    }

    private static function linkButton(string $label, string $url): string
    {
        return MailBranding::button($label, $url);
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
            return MailBranding::button($label, $url);
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
                    'relative' => $signedRel,
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
                    'relative' => $originalRel,
                    'name' => 'reglamento_' . basename($abs),
                    'mime' => $ext === 'pdf' ? 'application/pdf' : 'application/octet-stream',
                ];
            }
        }

        return null;
    }

    /**
     * Actualiza estatus CENNI y notifica al alumno (siempre envía correo).
     *
     * @return array{status: string, mailed: bool, template:?string, to:?string}
     */
    public function updateCenniStatus(
        int $caseId,
        string $status,
        ?string $folio,
        ?string $notes,
        bool $notify,
        ?int $userId,
        ?string $downloadUrl = null,
        ?string $sepUrl = null,
        ?string $templateCode = null
    ): array {
        $allowed = array_keys(\App\Payments\OpenPayPaymentService::cenniStatuses());
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Estatus CENNI inválido.');
        }
        $this->repo->ensureInventoryAndResultColumns();
        $this->ensureCenniMailTemplates();
        $fields = [
            'cenni_status' => $status,
            'cenni_folio' => $folio,
            'cenni_notes' => $notes,
            'cenni_status_updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($downloadUrl !== null) {
            $fields['cenni_download_url'] = trim($downloadUrl) !== '' ? trim($downloadUrl) : null;
        }
        if ($sepUrl !== null) {
            $fields['cenni_sep_url'] = trim($sepUrl) !== '' ? trim($sepUrl) : null;
        }
        $this->repo->updateCertificationCase($caseId, $fields);

        if ($status === 'issued') {
            try {
                $this->repo->markCaseStepDoneByKeywords(
                    $caseId,
                    ['cenni', 'sep', 'constancia', 'certificado'],
                    $userId,
                    'CENNI emitido'
                );
            } catch (\Throwable) {
            }
        }

        $code = trim((string) $templateCode);
        if ($code === '') {
            $code = match ($status) {
                'issued' => 'cenni_emitido',
                'docs_rejected' => 'cenni_docs_rechazados',
                default => 'cenni_seguimiento',
            };
        }
        if ($code === 'cenni_docs_rechazados' && !$this->repo->mailTemplateByCode($code)) {
            $code = 'cenni_seguimiento';
        }
        $sent = $this->sendTemplate($caseId, $code, $userId);

        return [
            'status' => $status,
            'mailed' => true,
            'template' => $code,
            'to' => $sent['to'] ?? null,
        ];
    }

    /**
     * Avisa al alumno el resultado de la revisión de documentos CENNI.
     *
     * @return array{status:string,mailed:bool,template:?string,to:?string}
     */
    public function notifyCenniDocumentReview(int $caseId, ?int $userId): array
    {
        $this->ensureCenniMailTemplates();
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }
        $docs = $this->repo->caseCenniDocuments($caseId);
        if ($docs === []) {
            throw new \RuntimeException('El alumno aún no ha subido documentos CENNI.');
        }
        $rejected = [];
        $pending = [];
        foreach ($docs as $doc) {
            $st = (string) ($doc['review_status'] ?? '');
            if ($st === '' || $st === 'pending') {
                $pending[] = $doc;
            } elseif ($st === 'rejected') {
                $rejected[] = $doc;
            }
        }
        if ($pending !== []) {
            throw new \RuntimeException('Revisa todos los documentos (aprobar o rechazar) antes de avisar al alumno.');
        }

        if ($rejected !== []) {
            $notes = [];
            foreach ($rejected as $doc) {
                $label = trim((string) ($doc['label'] ?? $doc['kind'] ?? 'Documento'));
                $reason = trim((string) ($doc['review_notes'] ?? ''));
                $notes[] = $label . ($reason !== '' ? ': ' . $reason : '');
            }
            return $this->updateCenniStatus(
                $caseId,
                'docs_rejected',
                trim((string) ($case['cenni_folio'] ?? '')) ?: null,
                implode("\n", $notes),
                true,
                $userId,
                null,
                null,
                'cenni_docs_rechazados'
            );
        }

        return $this->updateCenniStatus(
            $caseId,
            'sep_pending',
            trim((string) ($case['cenni_folio'] ?? '')) ?: null,
            trim((string) ($case['cenni_notes'] ?? '')) ?: 'Documentos aprobados. Continuamos el trámite.',
            true,
            $userId,
            null,
            null,
            'cenni_seguimiento'
        );
    }

    /** Crea plantillas CENNI si no existen (no sobrescribe ediciones). */
    public function ensureCenniMailTemplates(): void
    {
        $defaults = [
            [
                'code' => 'cenni_seguimiento',
                'name' => 'CENNI — actualización de estatus',
                'subject' => 'Actualización de tu trámite CENNI — {{Certificación}}',
                'body_html' => '<p>Hola {{Nombre}},</p>'
                    . '<p>Avance de tu trámite CENNI para {{Certificación}}:</p>'
                    . '<p>Estatus: {{CENNI Estatus}}<br>{{CENNI Folio Line}}{{CENNI Notas Line}}</p>'
                    . '<p>Instituto DOCEO</p>',
            ],
            [
                'code' => 'cenni_docs_rechazados',
                'name' => 'CENNI — documentos por corregir',
                'subject' => 'Corrige tus documentos CENNI — {{Certificación}}',
                'body_html' => '<p>Hola {{Nombre}},</p>'
                    . '<p>Revisamos tus documentos CENNI de {{Certificación}} y necesitamos correcciones.</p>'
                    . '<p>{{CENNI Notas Line}}</p>'
                    . '<p>Entra a tu ficha de alumno y vuelve a subirlos.</p>'
                    . '<p>Instituto DOCEO</p>',
            ],
            [
                'code' => 'cenni_emitido',
                'name' => 'CENNI emitido — descarga y folio',
                'subject' => 'Tu CENNI ya está listo — {{Certificación}}',
                'body_html' => '<p>Hola {{Nombre}},</p>'
                    . '<p>Tu CENNI para {{Certificación}} ya fue emitido{{CENNI Folio Suffix}}.</p>'
                    . '<p>{{CENNI Folio Line}}{{CENNI Descarga Line}}{{CENNI SEP Line}}{{CENNI Notas Line}}</p>'
                    . '<p>Instituto DOCEO</p>',
            ],
        ];
        foreach ($defaults as $row) {
            if ($this->repo->mailTemplateByCode($row['code'])) {
                continue;
            }
            try {
                $this->repo->saveMailTemplate([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'audience' => 'student',
                    'to_mode' => 'student',
                    'to_fixed' => '',
                    'cc_mode' => 'case_cc',
                    'cc_fixed' => '',
                    'subject' => $row['subject'],
                    'body_html' => $row['body_html'],
                    'attach_export' => 0,
                    'is_active' => 1,
                ]);
            } catch (\Throwable) {
            }
        }
    }

    private static function linkLine(string $label, string $url): string
    {
        $url = \App\Support\Str::externalUrl($url);
        if ($url === '') {
            return '';
        }
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return '<strong>' . $safeLabel . ':</strong> <a href="' . $safeUrl . '" target="_blank" rel="noopener">'
            . $safeUrl . '</a><br>';
    }

    /**
     * Publica enlaces de resultados y notifica al alumno (plantilla itep_resultados u otra).
     *
     * @return array{mailed:bool,template:?string,to:?string}
     */
    public function deliverExamResults(
        int $caseId,
        string $resultsUrl,
        string $scoreUrl,
        string $certificateUrl,
        bool $notify,
        ?int $userId,
        string $templateCode = 'itep_resultados'
    ): array {
        $this->repo->ensureInventoryAndResultColumns();
        $this->ensureItepStudentResultTemplates();
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }

        $normalized = [
            'results_url' => \App\Support\Str::externalUrl($resultsUrl),
            'score_url' => \App\Support\Str::externalUrl($scoreUrl),
            'certificate_url' => \App\Support\Str::externalUrl($certificateUrl),
        ];
        $raw = [
            'results_url' => trim($resultsUrl),
            'score_url' => trim($scoreUrl),
            'certificate_url' => trim($certificateUrl),
        ];
        foreach ($raw as $key => $rawValue) {
            if ($rawValue !== '' && $normalized[$key] === '') {
                throw new \InvalidArgumentException(
                    'La URL debe ser completa (https://…). No uses textos como “enlace_score_report”.'
                );
            }
        }
        if ($normalized['score_url'] === '' && $normalized['certificate_url'] === '' && $normalized['results_url'] === '') {
            throw new \InvalidArgumentException('Indica al menos la URL de Score report o Certificate.');
        }

        $fields = [
            'results_url' => $normalized['results_url'] !== '' ? $normalized['results_url'] : null,
            'score_url' => $normalized['score_url'] !== '' ? $normalized['score_url'] : null,
            'certificate_url' => $normalized['certificate_url'] !== '' ? $normalized['certificate_url'] : null,
            'exam_outcome' => 'delivered',
            'invalidation_reason' => null,
        ];
        $this->repo->updateCertificationCase($caseId, $fields);
        try {
            $this->repo->markCaseStepDoneByKeywords(
                $caseId,
                ['resultado', 'score', 'certificado', 'certificate'],
                $userId,
                'Resultados publicados'
            );
        } catch (\Throwable) {
        }

        $tpl = trim($templateCode) !== '' ? trim($templateCode) : 'itep_resultados';
        $sent = $this->sendTemplate($caseId, $tpl, $userId);

        return ['mailed' => true, 'template' => $tpl, 'to' => $sent['to'] ?? null];
    }

    /**
     * Marca el examen como invalidado y notifica al alumno.
     *
     * @return array{mailed:bool,template:?string,to:?string}
     */
    public function invalidateExam(
        int $caseId,
        string $reason,
        bool $notify,
        ?int $userId,
        string $templateCode = 'itep_invalidado'
    ): array {
        $this->repo->ensureInventoryAndResultColumns();
        $this->ensureItepStudentResultTemplates();
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Indica el motivo de invalidación.');
        }
        $this->repo->updateCertificationCase($caseId, [
            'exam_outcome' => 'invalidated',
            'invalidation_reason' => $reason,
            'cancel_reason' => $reason,
        ]);
        try {
            $this->repo->markCaseStepDoneByKeywords(
                $caseId,
                ['invalid', 'resultado', 'examen'],
                $userId,
                'Examen invalidado: ' . $reason
            );
        } catch (\Throwable) {
        }

        $tpl = trim($templateCode) !== '' ? trim($templateCode) : 'itep_invalidado';
        $sent = $this->sendTemplate($caseId, $tpl, $userId);

        return ['mailed' => true, 'template' => $tpl, 'to' => $sent['to'] ?? null];
    }

    /** Crea plantillas iTEP de resultados/invalidación si no existen (no sobrescribe ediciones). */
    public function ensureItepStudentResultTemplates(): void
    {
        $defaults = [
            [
                'code' => 'itep_resultados',
                'name' => 'iTEP — Resultados / certificado',
                'subject' => 'Resultados iTEP — {{Nombre}}',
                'body_html' => '<p>Hola {{Nombre}},</p>'
                    . '<p>Ya están disponibles tus resultados de {{Certificación}}.</p>'
                    . '<p>{{Score Line}}{{Certificate Line}}</p>'
                    . '<p>También puedes verlos en tu ficha de alumno.</p>'
                    . '<p>Instituto DOCEO</p>',
            ],
            [
                'code' => 'itep_invalidado',
                'name' => 'iTEP — Examen invalidado',
                'subject' => 'Aviso sobre tu examen iTEP — {{Nombre}}',
                'body_html' => '<p>Hola {{Nombre}},</p>'
                    . '<p>Tu examen {{Certificación}} fue marcado como invalidado.</p>'
                    . '<p>Motivo: {{Canceled}}</p>'
                    . '<p>Si tienes dudas, responde a este correo o contacta a {{Contacto Doceo}}.</p>'
                    . '<p>Instituto DOCEO</p>',
            ],
            [
                'code' => 'itep_data',
                'name' => 'iTEP — Datos de acceso al alumno',
                'subject' => 'Datos de acceso iTEP — {{Nombre}}',
                'body_html' => '<p>Hola {{Nombre}},</p>'
                    . '<p>Tu examen {{Certificación}} ya tiene códigos de acceso.</p>'
                    . '<p>Examen ID: {{Folio / ID}}<br>Contraseña: {{Clave}}</p>'
                    . '<p>Instituto DOCEO</p>',
            ],
        ];
        foreach ($defaults as $row) {
            if ($this->repo->mailTemplateByCode($row['code'])) {
                continue;
            }
            try {
                $this->repo->saveMailTemplate([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'audience' => 'student',
                    'to_mode' => 'student',
                    'to_fixed' => '',
                    'cc_mode' => 'case_cc',
                    'cc_fixed' => '',
                    'subject' => $row['subject'],
                    'body_html' => $row['body_html'],
                    'attach_export' => 0,
                    'is_active' => 1,
                ]);
            } catch (\Throwable) {
            }
        }
    }

    /** @return array<string, string> Clave token => descripción para el editor admin */
    public static function tokenHelp(): array
    {
        $base = [
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
            'iTEP Results' => 'URL de resultados del examen',
            'Score URL' => 'URL del Score result',
            'Certificate URL' => 'URL del Certificate',
            'Resultados Line' => 'Línea HTML con link a resultados',
            'Score Line' => 'Línea HTML con link a Score',
            'Certificate Line' => 'Línea HTML con link a Certificate',
            'Canceled' => 'Motivo de invalidación / cancelación',
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
            'CENNI Descarga URL' => 'URL de descarga del CENNI',
            'CENNI Descarga Line' => 'Línea HTML con descarga del CENNI',
            'CENNI SEP URL' => 'URL consulta oficial SEP',
            'CENNI SEP Line' => 'Línea HTML con link SEP',
            'CENNI Doc URL' => 'URL del PDF de instrucciones CENNI',
            'CENNI Tramite Line' => 'Línea HTML con instrucciones CENNI',
            'CURP' => 'CURP del alumno',
            'App URL' => 'URL del PDV',
            'Logo URL' => 'URL del logo Doceo',
            'Escudo URL' => 'URL del escudo',
            'Reglamento URL' => 'Link al PDF original del reglamento',
            'Reglamento Firmado URL' => 'Link al PDF de evidencia firmado por el alumno',
            'Reglamento Boton' => 'Botón HTML (firmado si existe; si no, original)',
            'Reglamento Firmado Boton' => 'Botón HTML solo del PDF firmado',
            'Comprobante URL' => 'Link público de descarga del comprobante subido en el caso',
            'Comprobante Boton' => 'Botón HTML para descargar el comprobante',
            'Exportacion URL' => 'Link público de descarga del CSV/Excel de exportación',
            'Exportacion Boton' => 'Botón HTML para descargar la exportación',
            'Links Alumno' => 'Lista HTML de todos los links del proveedor aplicables al caso',
            'Links Estudio' => 'Lista HTML de links tipo material de estudio',
            'Links Software' => 'Lista HTML de links tipo software / descarga',
            'Links Examen' => 'Lista HTML de links tipo portal de examen',
        ];

        try {
            $repo = new CatalogRepository();
            $types = CatalogRepository::providerLinkTypes();
            foreach ($repo->allProviderLinks(true) as $link) {
                $code = strtoupper(trim((string) ($link['code'] ?? '')));
                if ($code === '') {
                    continue;
                }
                $label = trim((string) ($link['label'] ?? $code));
                $provider = trim((string) ($link['provider_name'] ?? $link['provider_code'] ?? ''));
                $typeLabel = $types[$link['link_type'] ?? ''] ?? ($link['link_type'] ?? '');
                $scope = (string) ($link['scope_type'] ?? 'provider');
                $hint = $label;
                if ($provider !== '') {
                    $hint .= ' · ' . $provider;
                }
                if ($typeLabel !== '') {
                    $hint .= ' · ' . $typeLabel;
                }
                $hint .= ' · alcance ' . $scope;
                $base['Link ' . $code] = 'Línea HTML: ' . $hint;
                $base['Link ' . $code . ' URL'] = 'URL cruda: ' . $hint;
                $base['Link ' . $code . ' Boton'] = 'Botón HTML: ' . $hint;
            }
        } catch (\Throwable) {
            $base['Link CODIGO'] = 'Línea HTML del link con ese código (alta en Proveedor → Links)';
            $base['Link CODIGO URL'] = 'URL cruda del link';
            $base['Link CODIGO Boton'] = 'Botón HTML del link';
        }

        return $base;
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
        $app = (string) ($tokens['App URL'] ?? MailBranding::appUrl());

        // Normaliza color de texto del cuerpo a azul institucional si viene sin estilos.
        $body = trim($innerHtml);
        if ($body !== '' && !str_starts_with(strtolower($body), '<table role="presentation"')) {
            $body = '<div style="color:' . MailBranding::BRAND_BLUE . ';font-family:Arial,sans-serif;font-size:15px;line-height:1.55;">'
                . $body
                . '</div>';
        }

        return MailBranding::wrap($body, $app);
    }

    /** @param array<string, mixed> $tpl @param array<string, mixed> $case */
    private function resolveTo(array $tpl, array $case, bool $forceProvider = false): string
    {
        $override = trim((string) (Env::get('MAIL_OVERRIDE_TO', '') ?? ''));
        if ($override !== '' && filter_var($override, FILTER_VALIDATE_EMAIL)) {
            return $override;
        }

        $mode = (string) ($tpl['to_mode'] ?? 'student');
        if ($mode === 'manual') {
            throw new \RuntimeException(
                'La plantilla tiene destinatario “Manual”: no se puede enviar automáticamente. '
                . 'Cámbiala a “Contacto del proveedor” o “Correo fijo” en Admin → Correos.'
            );
        }

        if ($mode === 'fixed' && !empty($tpl['to_fixed'])) {
            $fixed = trim((string) $tpl['to_fixed']);
            if ($fixed !== '' && filter_var($fixed, FILTER_VALIDATE_EMAIL)) {
                return $fixed;
            }
        }

        $wantProvider = $forceProvider || $mode === 'provider';
        if ($wantProvider) {
            // to_fixed en modo provider = override de pruebas / correo fijo del proveedor
            if ($mode === 'provider' && !empty($tpl['to_fixed'])) {
                $fixed = trim((string) $tpl['to_fixed']);
                if ($fixed !== '' && filter_var($fixed, FILTER_VALIDATE_EMAIL)) {
                    return $fixed;
                }
            }
            $email = trim((string) ($case['provider_contact_email'] ?? ''));
            if ($email === '') {
                $providerId = (int) ($case['provider_id'] ?? 0);
                if ($providerId > 0) {
                    foreach ($this->repo->providerContacts($providerId) as $contact) {
                        $candidate = trim((string) ($contact['email'] ?? ''));
                        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                            $email = $candidate;
                            if (!empty($contact['is_primary'])) {
                                break;
                            }
                        }
                    }
                }
            }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
            throw new \RuntimeException(
                'El proveedor no tiene correo de contacto. Configúralo en Admin → Proveedores '
                . '(correo principal o contacto primario), o pon un correo fijo en la plantilla (To = fijo / pruebas).'
            );
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
        $override = trim((string) (Env::get('MAIL_OVERRIDE_TO', '') ?? ''));
        if ($override !== '' && filter_var($override, FILTER_VALIDATE_EMAIL)) {
            return null; // evita CC duplicado hacia la misma bandeja de prueba
        }

        $mode = (string) ($tpl['cc_mode'] ?? 'none');
        return match ($mode) {
            'fixed' => trim((string) ($tpl['cc_fixed'] ?? '')) ?: null,
            'case_cc', 'tr' => trim((string) ($case['cc_email'] ?? $case['partner_email'] ?? '')) ?: null,
            default => null,
        };
    }
}
