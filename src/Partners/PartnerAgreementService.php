<?php

declare(strict_types=1);

namespace App\Partners;

use App\Catalog\CatalogRepository;
use App\Config\Env;
use App\Integrations\Mailer;
use PDO;

/**
 * Publicación de versiones de convenio TR, firma por partners y soft-block.
 */
final class PartnerAgreementService
{
    private CatalogRepository $catalog;
    private PDO $pdo;

    public function __construct(?CatalogRepository $catalog = null, ?PDO $pdo = null)
    {
        $this->catalog = $catalog ?? new CatalogRepository();
        $this->pdo = $pdo ?? \App\Database\Connection::get();
    }

    /**
     * Marca el convenio vigente del nivel, asigna a todos los partners del nivel,
     * restringe registro de alumnos y notifica por correo.
     *
     * @return array{assigned:int, notified:int, mail_errors:list<string>}
     */
    public function publishVersion(int $agreementId, ?int $adminUserId = null, ?int $deadlineDaysOverride = null): array
    {
        $this->catalog->ensureAgreementSignatureSchema();
        $agreement = $this->catalog->agreement($agreementId);
        if (!$agreement) {
            throw new \RuntimeException('Convenio no encontrado.');
        }
        if (empty($agreement['pdf_path'])) {
            throw new \RuntimeException('Sube el PDF del convenio (plantilla en blanco) antes de publicar.');
        }

        $tierId = (int) $agreement['partner_tier_id'];
        $days = $deadlineDaysOverride ?? (int) ($agreement['sign_deadline_days'] ?? 15);
        if ($days < 1) {
            $days = 15;
        }
        $deadlineAt = (new \DateTimeImmutable('now'))->modify('+' . $days . ' days')->format('Y-m-d 23:59:59');

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'UPDATE agreements SET is_current = 0 WHERE partner_tier_id = ? AND id <> ?'
            )->execute([$tierId, $agreementId]);

            $this->pdo->prepare(
                'UPDATE agreements
                 SET is_current = 1, published_at = NOW(), sign_deadline_days = ?
                 WHERE id = ?'
            )->execute([$days, $agreementId]);

            $partners = $this->catalog->partnersByTier($tierId);
            $assigned = 0;
            foreach ($partners as $partner) {
                $this->catalog->assignAgreementToPartner(
                    (int) $partner['id'],
                    $agreementId,
                    $deadlineAt,
                    'Publicación de nueva versión de convenio',
                    $adminUserId,
                    true
                );
                $assigned++;
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $notified = 0;
        $mailErrors = [];
        $freshPartners = $this->catalog->partnersByTier($tierId);
        foreach ($freshPartners as $partner) {
            try {
                $this->notifyPartnerToSign($partner, $agreement, $deadlineAt);
                $this->catalog->markAssignmentNotified((int) $partner['id'], $agreementId);
                $notified++;
            } catch (\Throwable $e) {
                $mailErrors[] = ($partner['email'] ?? 'partner#' . $partner['id']) . ': ' . $e->getMessage();
            }
        }

        return [
            'assigned' => $assigned,
            'notified' => $notified,
            'mail_errors' => $mailErrors,
        ];
    }

    /**
     * Alta de partner: asigna convenio vigente del nivel como pendiente de firma.
     */
    public function assignCurrentOnPartnerCreate(int $partnerId, int $tierId, ?int $adminUserId = null): void
    {
        $this->catalog->ensureAgreementSignatureSchema();
        $agreement = $this->catalog->currentAgreementForTier($tierId);
        if (!$agreement) {
            return;
        }
        $days = max(1, (int) ($agreement['sign_deadline_days'] ?? 15));
        $deadlineAt = (new \DateTimeImmutable('now'))->modify('+' . $days . ' days')->format('Y-m-d 23:59:59');
        $this->catalog->assignAgreementToPartner(
            $partnerId,
            (int) $agreement['id'],
            $deadlineAt,
            'Alta de partner TR — firma de convenio vigente',
            $adminUserId,
            true
        );

        $partner = $this->catalog->partner($partnerId);
        if ($partner) {
            try {
                $this->notifyPartnerToSign($partner, $agreement, $deadlineAt);
                $this->catalog->markAssignmentNotified($partnerId, (int) $agreement['id']);
            } catch (\Throwable) {
                // El alta no debe fallar por correo; el partner verá el aviso en el portal.
            }
        }
    }

    /** @param array<string, mixed> $partner @param array<string, mixed> $agreement */
    public function notifyPartnerToSign(array $partner, array $agreement, string $deadlineAt): void
    {
        $email = trim((string) ($partner['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Correo de partner inválido.');
        }

        $base = rtrim((string) (Env::get('APP_URL') ?: ''), '/');
        if ($base === '') {
            $base = 'https://pdv.institutodoceo.com';
        }
        $link = $base . '/partner/convenio';
        $name = trim((string) ($partner['user_name'] ?? $partner['first_name'] ?? 'Partner'));
        $agreementName = (string) ($agreement['name'] ?? 'Convenio');
        $tier = (string) ($partner['tier_name'] ?? '');
        $deadlineLabel = date('d/m/Y', strtotime($deadlineAt)) ?: $deadlineAt;

        $subject = 'Nueva versión de convenio TR — firma requerida';
        $body = "Hola {$name},\n\n"
            . "Hay una nueva versión del convenio Teacher Referral"
            . ($tier !== '' ? " (nivel {$tier})" : '')
            . ": {$agreementName}.\n\n"
            . "Debes descargar, firmar y subir el PDF firmado a más tardar el {$deadlineLabel}.\n"
            . "Hasta que el equipo Doceo confirme el documento, podrás entrar a tu cuenta pero no registrar alumnos.\n\n"
            . "Sube tu convenio firmado aquí:\n{$link}\n\n"
            . "Instituto Doceo\n";

        $html = '<p>Hola ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ',</p>'
            . '<p>Hay una nueva versión del convenio Teacher Referral'
            . ($tier !== '' ? ' (nivel <strong>' . htmlspecialchars($tier, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>)' : '')
            . ': <strong>' . htmlspecialchars($agreementName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>.</p>'
            . '<p>Debes descargar, firmar y subir el PDF firmado a más tardar el <strong>'
            . htmlspecialchars($deadlineLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</strong>.</p>'
            . '<p>Hasta que el equipo Doceo confirme el documento, podrás entrar a tu cuenta pero <strong>no registrar alumnos</strong>.</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Subir convenio firmado</a></p>'
            . '<p>Instituto Doceo</p>';

        (new Mailer())->send($email, $subject, $body, [
            'html' => true,
            'body_html' => $html,
        ]);
    }
}
