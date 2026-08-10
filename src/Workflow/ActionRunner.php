<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Catalog\CatalogRepository;
use App\Mail\CaseMailService;
use App\Services\ExamFulfillmentService;
use App\Support\Uploader;

final class ActionRunner
{
    public function __construct(
        private readonly CatalogRepository $repo = new CatalogRepository(),
        private readonly ActionRepository $actions = new ActionRepository(),
        private readonly CaseMailService $mailer = new CaseMailService(),
    ) {
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $paymentFile
     * @return array{ok:bool,message:string,result?:array<string,mixed>}
     */
    public function run(int $caseId, int $actionId, string $source = 'button', ?int $userId = null, ?array $paymentFile = null): array
    {
        $this->actions->ensureSchema();
        $action = $this->actions->find($actionId);
        if (!$action || !(int) ($action['is_active'] ?? 0)) {
            return ['ok' => false, 'message' => 'Acción no encontrada o inactiva.'];
        }

        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            return ['ok' => false, 'message' => 'Caso no encontrado.'];
        }

        $missing = $this->missingRequirements($action, $case);
        if ($missing !== []) {
            $msg = 'Faltan requisitos: ' . implode(', ', $missing);
            $this->actions->logRun($caseId, $actionId, $source, 'skipped', $msg, $userId);

            return ['ok' => false, 'message' => $msg];
        }

        try {
            $result = $this->dispatch($action, $case, $userId, $paymentFile);
            $message = (string) ($result['message'] ?? 'Acción ejecutada.');
            $this->actions->logRun($caseId, $actionId, $source, 'ok', $message, $userId);

            return ['ok' => true, 'message' => $message, 'result' => $result];
        } catch (\Throwable $e) {
            $this->actions->logRun($caseId, $actionId, $source, 'failed', $e->getMessage(), $userId);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Ejecuta acciones del protocolo con el trigger indicado (una vez por acción/trigger).
     *
     * @return list<array{action:string,ok:bool,message:string}>
     */
    public function runTriggers(int $caseId, string $trigger, ?int $userId = null): array
    {
        $this->actions->ensureSchema();
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            return [];
        }
        $protocolId = (int) ($case['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            return [];
        }

        $out = [];
        foreach ($this->actions->protocolActions($protocolId, true) as $action) {
            $triggers = $this->actions->decodeJsonList($action['auto_triggers'] ?? null);
            if (!in_array($trigger, $triggers, true)) {
                continue;
            }
            $actionId = (int) $action['id'];
            if ($this->actions->hasSuccessfulAutoRun($caseId, $actionId, $trigger)) {
                continue;
            }
            $result = $this->run($caseId, $actionId, $trigger, $userId);
            $out[] = [
                'action' => (string) $action['code'],
                'ok' => $result['ok'],
                'message' => $result['message'],
            ];
        }

        return $out;
    }

    /**
     * Acciones del protocolo visibles como botón y listas para el caso.
     *
     * @return list<array<string, mixed>>
     */
    public function buttonsForCase(array $case): array
    {
        $this->actions->ensureSchema();
        $protocolId = (int) ($case['protocol_id'] ?? 0);
        if ($protocolId < 1) {
            return [];
        }
        $buttons = [];
        foreach ($this->actions->protocolActions($protocolId, true) as $action) {
            if (!(int) ($action['show_as_button'] ?? 0)) {
                continue;
            }
            $missing = $this->missingRequirements($action, $case);
            $buttons[] = [
                'id' => (int) $action['id'],
                'code' => (string) $action['code'],
                'label' => trim((string) ($action['button_label'] ?? '')) ?: (string) $action['name'],
                'handler' => (string) $action['handler'],
                'enabled' => $missing === [],
                'blocked_by' => $missing,
                'needs_payment_file' => ($action['handler'] ?? '') === 'confirm_payment'
                    || ($action['handler'] ?? '') === 'request_provider',
            ];
        }

        return $buttons;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $case
     * @return list<string>
     */
    public function missingRequirements(array $action, array $case): array
    {
        $requires = $this->actions->decodeJsonList($action['requires_json'] ?? null);
        $labels = ActionRepository::requireOptions();
        $missing = [];
        foreach ($requires as $req) {
            $ok = match ($req) {
                'payment_confirmed' => !empty($case['payment_confirmed_at']),
                'payment_proof' => trim((string) ($case['payment_proof_path'] ?? '')) !== '',
                'folio_id' => trim((string) ($case['folio_id'] ?? '')) !== '',
                'access_key' => trim((string) ($case['access_key'] ?? '')) !== '',
                'folio_or_moodle' => (
                    (trim((string) ($case['folio_id'] ?? '')) !== '' && trim((string) ($case['access_key'] ?? '')) !== '')
                    || trim((string) ($case['moodle_user'] ?? '')) !== ''
                ),
                default => true,
            };
            if (!$ok) {
                $missing[] = $labels[$req] ?? $req;
            }
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $case
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $paymentFile
     * @return array<string, mixed>
     */
    private function dispatch(array $action, array $case, ?int $userId, ?array $paymentFile): array
    {
        $caseId = (int) $case['id'];
        $handler = (string) ($action['handler'] ?? '');

        return match ($handler) {
            'confirm_payment' => $this->handleConfirmPayment($caseId, $paymentFile, $userId),
            'request_provider' => $this->handleRequestProvider($caseId, $paymentFile, $userId),
            'fulfill_after_payment' => $this->handleFulfill($caseId, $userId),
            'send_student_access' => $this->handleStudentAccess($caseId, $action, $case, $userId),
            'send_mail' => $this->handleSendMail($caseId, $action, $userId),
            default => throw new \RuntimeException('Handler de acción no soportado: ' . $handler),
        };
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $paymentFile
     * @return array<string, mixed>
     */
    private function handleConfirmPayment(int $caseId, ?array $paymentFile, ?int $userId): array
    {
        $result = $this->mailer->markPaymentReceived(
            $caseId,
            'other',
            $paymentFile,
            null,
            $userId
        );
        $this->repo->persistCaseFileShareTokens($caseId);

        return [
            'message' => 'Pago confirmado'
                . (!empty($result['payment_confirmed_at']) ? ' (' . $result['payment_confirmed_at'] . ')' : '')
                . '.',
            'payment' => $result,
        ];
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $paymentFile
     * @return array<string, mixed>
     */
    private function handleRequestProvider(int $caseId, ?array $paymentFile, ?int $userId): array
    {
        $case = $this->repo->certificationCaseDetailed($caseId);
        if ($paymentFile && (int) ($paymentFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $out = $this->mailer->confirmPaymentAndRequestProvider($caseId, $paymentFile, $userId);
        } elseif ($case && empty($case['payment_confirmed_at'])) {
            $out = $this->mailer->confirmPaymentAndRequestProvider($caseId, null, $userId);
        } else {
            $out = $this->mailer->sendProviderRequest($caseId, $paymentFile, $userId, true);
        }
        $this->repo->persistCaseFileShareTokens($caseId);

        $msg = 'Solicitud al proveedor';
        if (!empty($out['mailed'])) {
            $msg .= ' enviada a ' . ($out['to'] ?? '');
        } elseif (!empty($out['mail_skip'])) {
            $msg .= ': ' . $out['mail_skip'];
        }
        if (!empty($out['links_only']) && is_array($out['links_only'])) {
            $msg .= ' · links: ' . implode(', ', $out['links_only']);
        }

        return ['message' => $msg, 'mail' => $out];
    }

    /** @return array<string, mixed> */
    private function handleFulfill(int $caseId, ?int $userId): array
    {
        $fulfill = (new ExamFulfillmentService($this->repo, $this->mailer))->fulfillAfterPayment($caseId, $userId);

        return ['message' => 'Curso/inventario procesado.', 'fulfill' => $fulfill];
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $case
     * @return array<string, mixed>
     */
    private function handleStudentAccess(int $caseId, array $action, array $case, ?int $userId): array
    {
        $tpl = trim((string) ($action['mail_template_code'] ?? ''));
        if ($tpl === '') {
            $tpl = trim((string) ($case['student_access_template'] ?? ''));
        }
        if ($tpl === '') {
            throw new \RuntimeException(
                'Sin plantilla de acceso. Configúrala en la acción o en el protocolo (Plantilla datos de acceso).'
            );
        }
        $sent = $this->mailer->sendTemplate($caseId, $tpl, $userId, false);

        return [
            'message' => 'Acceso enviado a ' . ($sent['to'] ?? '') . ' (plantilla ' . $tpl . ').',
            'mail' => $sent,
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private function handleSendMail(int $caseId, array $action, ?int $userId): array
    {
        $tpl = trim((string) ($action['mail_template_code'] ?? ''));
        if ($tpl === '') {
            throw new \RuntimeException('La acción de correo no tiene plantilla configurada.');
        }
        $sent = $this->mailer->sendTemplate($caseId, $tpl, $userId, false);

        return [
            'message' => 'Correo “' . $tpl . '” enviado a ' . ($sent['to'] ?? '') . '.',
            'mail' => $sent,
        ];
    }
}
