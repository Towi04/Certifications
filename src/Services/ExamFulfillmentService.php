<?php

declare(strict_types=1);

namespace App\Services;

use App\Catalog\CatalogRepository;
use App\Integrations\MoodleEnrolService;
use App\Mail\CaseMailService;

/**
 * Post-pago iTEP (y protocolos con inventario):
 * Moodle enrol + mail, asignar código del inventario + mail de acceso.
 */
final class ExamFulfillmentService
{
    public function __construct(
        private readonly CatalogRepository $repo,
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
     * @return array{
     *   moodle: ?array,
     *   inventory: ?array{assigned:bool,exam_id?:string,access_code?:string,error?:string},
     *   access_mail: ?array{sent:bool,template?:string,to?:string,error?:string}
     * }
     */
    public function fulfillAfterPayment(int $caseId, ?int $actorUserId = null): array
    {
        $this->repo->ensureInventoryAndResultColumns();
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }

        $moodle = null;
        try {
            $moodle = $this->moodle()->ensureAccessForCase($caseId, $actorUserId);
        } catch (\Throwable $e) {
            $moodle = ['error' => $e->getMessage()];
            error_log('[PDV] Moodle fulfill case #' . $caseId . ': ' . $e->getMessage());
        }

        $inventory = null;
        $usesInventory = !empty($case['uses_inventory']);
        if ($usesInventory) {
            try {
                $inventory = $this->repo->assignInventoryCodeToCase($caseId, $actorUserId);
            } catch (\Throwable $e) {
                $inventory = ['assigned' => false, 'error' => $e->getMessage()];
                error_log('[PDV] Inventory assign case #' . $caseId . ': ' . $e->getMessage());
            }
        }

        $accessMail = null;
        $template = trim((string) ($case['student_access_template'] ?? ''));
        if ($template === '' && $usesInventory) {
            $template = 'itep_data';
        }
        // Enviar acceso al examen si hay folio/clave (recién asignado o previo)
        $case = $this->repo->certificationCaseDetailed($caseId) ?? $case;
        $hasCreds = trim((string) ($case['access_key'] ?? '')) !== ''
            || trim((string) ($case['folio_id'] ?? '')) !== '';
        if ($template !== '' && $hasCreds) {
            try {
                $sent = $this->mailer()->sendTemplate($caseId, $template, $actorUserId);
                $accessMail = [
                    'sent' => true,
                    'template' => $template,
                    'to' => $sent['to'] ?? null,
                ];
                try {
                    $this->repo->markCaseStepDoneByKeywords(
                        $caseId,
                        ['acceso', 'código', 'codigo', 'itep', 'credencial'],
                        $actorUserId,
                        'Datos de acceso enviados (' . $template . ')'
                    );
                } catch (\Throwable) {
                }
            } catch (\Throwable $e) {
                $accessMail = [
                    'sent' => false,
                    'template' => $template,
                    'error' => $e->getMessage(),
                ];
            }
        } elseif ($template !== '' && !$hasCreds) {
            $accessMail = [
                'sent' => false,
                'template' => $template,
                'error' => 'Sin folio/clave aún; no se envió plantilla de acceso.',
            ];
        }

        return [
            'moodle' => $moodle,
            'inventory' => $inventory,
            'access_mail' => $accessMail,
        ];
    }

    /**
     * Asigna un código de inventario al caso (solo si aún no tiene).
     * Envía correo de acceso al alumno.
     *
     * @return array{inventory: array<string,mixed>, access_mail: ?array<string,mixed>}
     */
    public function assignInventoryCode(int $caseId, ?int $actorUserId = null): array
    {
        $this->repo->ensureInventoryAndResultColumns();
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }
        if (empty($case['uses_inventory'])) {
            throw new \RuntimeException('Este protocolo no usa inventario de códigos.');
        }
        if (!empty($case['inventory_code_id']) || (
            trim((string) ($case['folio_id'] ?? '')) !== ''
            && trim((string) ($case['access_key'] ?? '')) !== ''
        )) {
            throw new \RuntimeException(
                'Este caso ya tiene folio/clave. Usa “Reenviar acceso” para notificar al alumno sin asignar un código nuevo.'
            );
        }

        $inventory = $this->repo->assignInventoryCodeToCase($caseId, $actorUserId);
        if (empty($inventory['assigned'])) {
            throw new \RuntimeException((string) ($inventory['error'] ?? 'No se pudo asignar código.'));
        }

        return [
            'inventory' => $inventory,
            'access_mail' => $this->sendAccessMail($caseId, $actorUserId),
        ];
    }

    /**
     * Reenvía al alumno el correo con folio/clave ya asignados (no toma código nuevo).
     *
     * @return array{sent:bool,template?:string,to?:string,error?:string}
     */
    public function resendAccessMail(int $caseId, ?int $actorUserId = null): array
    {
        $this->repo->ensureInventoryAndResultColumns();
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            throw new \RuntimeException('Caso no encontrado.');
        }
        $hasCreds = trim((string) ($case['access_key'] ?? '')) !== ''
            || trim((string) ($case['folio_id'] ?? '')) !== '';
        if (!$hasCreds) {
            throw new \RuntimeException(
                'Aún no hay folio/clave. Usa “Asignar código” o captura Folio y Clave manualmente.'
            );
        }

        $mail = $this->sendAccessMail($caseId, $actorUserId);
        if (empty($mail['sent'])) {
            throw new \RuntimeException((string) ($mail['error'] ?? 'No se pudo enviar el correo de acceso.'));
        }

        return $mail;
    }

    /**
     * @return array{sent:bool,template?:string,to?:string,error?:string}
     */
    private function sendAccessMail(int $caseId, ?int $actorUserId): array
    {
        $case = $this->repo->certificationCaseDetailed($caseId);
        if (!$case) {
            return ['sent' => false, 'error' => 'Caso no encontrado.'];
        }
        $template = trim((string) ($case['student_access_template'] ?? ''));
        if ($template === '') {
            $template = !empty($case['uses_inventory']) ? 'itep_data' : 'moodle_acceso';
        }
        $hasCreds = trim((string) ($case['access_key'] ?? '')) !== ''
            || trim((string) ($case['folio_id'] ?? '')) !== '';
        if (!$hasCreds && $template === 'itep_data') {
            return [
                'sent' => false,
                'template' => $template,
                'error' => 'Sin folio/clave aún; no se envió plantilla de acceso.',
            ];
        }
        try {
            $this->repo->ensureInventoryAndResultColumns();
            $sent = $this->mailer()->sendTemplate($caseId, $template, $actorUserId);
            try {
                $this->repo->markCaseStepDoneByKeywords(
                    $caseId,
                    ['acceso', 'código', 'codigo', 'itep', 'credencial'],
                    $actorUserId,
                    'Datos de acceso enviados (' . $template . ')'
                );
            } catch (\Throwable) {
            }

            return [
                'sent' => true,
                'template' => $template,
                'to' => $sent['to'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'sent' => false,
                'template' => $template,
                'error' => $e->getMessage(),
            ];
        }
    }
}
