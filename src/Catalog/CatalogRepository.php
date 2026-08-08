<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Database\Connection;
use App\Support\Uploader;
use PDO;

final class CatalogRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Connection::get();
    }

    /** @return list<array<string, mixed>> */
    public function providers(bool $onlyActive = false): array
    {
        $sql = 'SELECT p.*,
                       (SELECT COUNT(*) FROM certifications c WHERE c.provider_id = p.id) AS certifications_count
                FROM providers p';
        if ($onlyActive) {
            $sql .= ' WHERE p.is_active = 1';
        }
        $sql .= ' ORDER BY p.name';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function provider(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM providers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveProvider(array $data, ?int $id = null): int
    {
        $fields = [
            $data['code'],
            $data['name'],
            $data['website_url'],
            $data['brand_website_url'] ?? null,
            $data['logo_path'] ?? $data['logo_icon_path'] ?? null,
            $data['logo_icon_path'] ?? null,
            $data['logo_full_path'] ?? null,
            $data['auth_proof_type'] ?? 'none',
            $data['auth_proof_url'] ?? null,
            $data['auth_proof_path'] ?? null,
            $data['is_active'] ?? 1,
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE providers SET code=?, name=?, website_url=?, brand_website_url=?, logo_path=?,
                 logo_icon_path=?, logo_full_path=?,
                 auth_proof_type=?, auth_proof_url=?, auth_proof_path=?, is_active=?
                 WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO providers (
                code, name, website_url, brand_website_url, logo_path, logo_icon_path, logo_full_path,
                auth_proof_type, auth_proof_url, auth_proof_path, is_active
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);
        return (int) $this->pdo->lastInsertId();
    }

    public function setProviderActive(int $id, bool $active): void
    {
        $this->pdo->prepare('UPDATE providers SET is_active = ? WHERE id = ?')
            ->execute([$active ? 1 : 0, $id]);
    }

    public function providerIconPath(?array $provider): ?string
    {
        if (!$provider) {
            return null;
        }
        return $provider['logo_icon_path'] ?? $provider['logo_path'] ?? null;
    }

    public function providerFullLogoPath(?array $provider): ?string
    {
        if (!$provider) {
            return null;
        }
        return $provider['logo_full_path'] ?? $provider['logo_icon_path'] ?? $provider['logo_path'] ?? null;
    }

    /** @return list<array<string, mixed>> */
    public function providerContacts(int $providerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM provider_contacts WHERE provider_id = ? ORDER BY is_primary DESC, role, name'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll();
    }

    public function ensureLegacyContactMigrated(int $providerId): void
    {
        if ($this->providerContacts($providerId)) {
            return;
        }
        $p = $this->provider($providerId);
        if (!$p || empty($p['contact_name'])) {
            return;
        }
        $this->addProviderContact([
            'provider_id' => $providerId,
            'role' => 'general',
            'name' => $p['contact_name'],
            'email' => $p['contact_email'] ?? null,
            'phone' => $p['contact_phone'] ?? null,
            'whatsapp' => $p['contact_whatsapp'] ?? null,
            'notes' => null,
            'is_primary' => 1,
        ]);
    }

    public function providerContact(int $providerId, int $contactId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM provider_contacts WHERE id = ? AND provider_id = ?'
        );
        $stmt->execute([$contactId, $providerId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function addProviderContact(array $data): int
    {
        if (!empty($data['is_primary'])) {
            $this->pdo->prepare(
                'UPDATE provider_contacts SET is_primary = 0 WHERE provider_id = ?'
            )->execute([$data['provider_id']]);
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO provider_contacts (provider_id, role, name, email, phone, whatsapp, notes, is_primary)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['provider_id'],
            $data['role'],
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['whatsapp'],
            $data['notes'],
            !empty($data['is_primary']) ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateProviderContact(int $contactId, array $data): void
    {
        if (!empty($data['is_primary'])) {
            $this->pdo->prepare(
                'UPDATE provider_contacts SET is_primary = 0 WHERE provider_id = ?'
            )->execute([$data['provider_id']]);
        }
        $stmt = $this->pdo->prepare(
            'UPDATE provider_contacts SET
                role=?, name=?, email=?, phone=?, whatsapp=?, notes=?, is_primary=?
             WHERE id=? AND provider_id=?'
        );
        $stmt->execute([
            $data['role'],
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['whatsapp'],
            $data['notes'],
            !empty($data['is_primary']) ? 1 : 0,
            $contactId,
            $data['provider_id'],
        ]);
    }

    public function deleteProviderContact(int $providerId, int $contactId): void
    {
        $this->pdo->prepare(
            'DELETE FROM provider_contacts WHERE id = ? AND provider_id = ?'
        )->execute([$contactId, $providerId]);
    }

    public function setCertificationPublished(int $certificationId, bool $published): void
    {
        $this->pdo->prepare(
            'UPDATE certifications SET is_published = ? WHERE id = ?'
        )->execute([$published ? 1 : 0, $certificationId]);
    }

    public function certificationBelongsToProvider(int $certificationId, int $providerId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM certifications WHERE id = ? AND provider_id = ? LIMIT 1'
        );
        $stmt->execute([$certificationId, $providerId]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function providerVenues(int $providerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM provider_venues
             WHERE provider_id = ?
             ORDER BY is_active DESC, venue_type ASC, state ASC, city ASC, name ASC'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll();
    }

    public function providerVenue(int $providerId, int $venueId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM provider_venues WHERE id = ? AND provider_id = ?'
        );
        $stmt->execute([$venueId, $providerId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function addProviderVenue(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO provider_venues (
                provider_id, venue_type, name, address_line, address_line2, neighborhood, city, state,
                postal_code, country, contact_name, contact_phone, contact_email, notes, is_active
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['provider_id'],
            $data['venue_type'] ?? 'fixed',
            $data['name'],
            $data['address_line'],
            $data['address_line2'],
            $data['neighborhood'],
            $data['city'],
            $data['state'],
            $data['postal_code'],
            $data['country'],
            $data['contact_name'],
            $data['contact_phone'],
            $data['contact_email'],
            $data['notes'],
            $data['is_active'] ?? 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateProviderVenue(int $venueId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE provider_venues SET
                venue_type=?, name=?, address_line=?, address_line2=?, neighborhood=?, city=?, state=?,
                postal_code=?, country=?, contact_name=?, contact_phone=?, contact_email=?, notes=?
             WHERE id=? AND provider_id=?'
        );
        $stmt->execute([
            $data['venue_type'] ?? 'fixed',
            $data['name'],
            $data['address_line'],
            $data['address_line2'],
            $data['neighborhood'],
            $data['city'],
            $data['state'],
            $data['postal_code'],
            $data['country'],
            $data['contact_name'],
            $data['contact_phone'],
            $data['contact_email'],
            $data['notes'],
            $venueId,
            $data['provider_id'],
        ]);
    }

    public function setProviderVenueActive(int $providerId, int $venueId, bool $active): void
    {
        $this->pdo->prepare(
            'UPDATE provider_venues SET is_active = ? WHERE id = ? AND provider_id = ?'
        )->execute([$active ? 1 : 0, $venueId, $providerId]);
    }

    public function deleteProviderVenue(int $providerId, int $venueId): void
    {
        $this->pdo->prepare(
            'DELETE FROM provider_venues WHERE id = ? AND provider_id = ?'
        )->execute([$venueId, $providerId]);
    }

    /** @return list<array<string, mixed>> */
    public function providerNotes(int $providerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, u.name AS author_name, u.email AS author_email
             FROM provider_notes n
             LEFT JOIN users u ON u.id = n.created_by
             WHERE n.provider_id = ?
             ORDER BY n.created_at DESC'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll();
    }

    public function addProviderNote(int $providerId, string $body, ?int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO provider_notes (provider_id, body, created_by) VALUES (?,?,?)'
        );
        $stmt->execute([$providerId, $body, $userId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteProviderNote(int $providerId, int $noteId): void
    {
        $this->pdo->prepare(
            'DELETE FROM provider_notes WHERE id = ? AND provider_id = ?'
        )->execute([$noteId, $providerId]);
    }

    /** @return list<array<string, mixed>> */
    public function providerAccounts(int $providerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM provider_accounts
             WHERE provider_id = ?
             ORDER BY is_active DESC, label ASC, id ASC'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll();
    }

    public function providerAccount(int $providerId, int $accountId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM provider_accounts WHERE id = ? AND provider_id = ?'
        );
        $stmt->execute([$accountId, $providerId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function addProviderAccount(array $data): int
    {
        $enc = $data['password_enc'] ?? null;
        if ($enc === '') {
            $enc = null;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO provider_accounts
                (provider_id, label, portal_url, username, password_enc, notes, is_active)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['provider_id'],
            $data['label'],
            $data['portal_url'],
            $data['username'],
            $enc,
            $data['notes'],
            $data['is_active'] ?? 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateProviderAccount(int $accountId, array $data): void
    {
        if (array_key_exists('password_enc', $data) && $data['password_enc'] !== null) {
            $enc = $data['password_enc'] === '' ? null : $data['password_enc'];
            $stmt = $this->pdo->prepare(
                'UPDATE provider_accounts SET
                    label=?, portal_url=?, username=?, password_enc=?, notes=?, is_active=?
                 WHERE id=? AND provider_id=?'
            );
            $stmt->execute([
                $data['label'],
                $data['portal_url'],
                $data['username'],
                $enc,
                $data['notes'],
                $data['is_active'] ?? 1,
                $accountId,
                $data['provider_id'],
            ]);
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE provider_accounts SET
                label=?, portal_url=?, username=?, notes=?, is_active=?
             WHERE id=? AND provider_id=?'
        );
        $stmt->execute([
            $data['label'],
            $data['portal_url'],
            $data['username'],
            $data['notes'],
            $data['is_active'] ?? 1,
            $accountId,
            $data['provider_id'],
        ]);
    }

    public function deleteProviderAccount(int $providerId, int $accountId): void
    {
        $this->pdo->prepare(
            'DELETE FROM provider_accounts WHERE id = ? AND provider_id = ?'
        )->execute([$accountId, $providerId]);
    }

    /** @return list<array<string, mixed>> */
    public function providerAgreements(int $providerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM provider_agreements WHERE provider_id = ? ORDER BY is_current DESC, year DESC, id DESC'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll();
    }

    public function addProviderAgreement(array $data): int
    {
        // Varios convenios pueden quedar vigentes a la vez (p. ej. ITEP supervisor + centro,
        // o UKS base + extensión CENEVAL). No se descontinúan los anteriores al subir uno nuevo.
        $stmt = $this->pdo->prepare(
            'INSERT INTO provider_agreements (provider_id, label, year, file_path, signed_on, notes, is_current)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['provider_id'],
            $data['label'],
            $data['year'],
            $data['file_path'],
            $data['signed_on'],
            $data['notes'],
            !empty($data['is_current']) ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setProviderAgreementActive(int $providerId, int $agreementId, bool $active): void
    {
        $this->pdo->prepare(
            'UPDATE provider_agreements SET is_current = ? WHERE id = ? AND provider_id = ?'
        )->execute([$active ? 1 : 0, $agreementId, $providerId]);
    }

    public function deleteProviderAgreement(int $providerId, int $agreementId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM provider_agreements WHERE id = ? AND provider_id = ?'
        );
        $stmt->execute([$agreementId, $providerId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $this->pdo->prepare('DELETE FROM provider_agreements WHERE id = ?')->execute([$agreementId]);
        return $row;
    }

    /** @return array<string, string> */
    public static function certificationSkills(): array
    {
        return [
            'listening' => 'Listening (comprensión auditiva)',
            'reading' => 'Reading (comprensión de lectura)',
            'writing' => 'Writing (expresión escrita)',
            'speaking' => 'Speaking (expresión oral)',
            'use_of_english' => 'Use of English / Grammar',
            'vocabulary' => 'Vocabulary',
        ];
    }

    /** @return array<string, string> */
    public static function cenniDocTypes(): array
    {
        return [
            'constancia' => 'Constancia',
            'constancia_certificado' => 'Constancia, Certificado',
            'constancia_certificado_diploma' => 'Constancia, Certificado y Diploma',
        ];
    }

    /**
     * @param mixed $raw
     * @return list<array{min: string, max: string, label: string}>
     */
    public static function decodeScoreRanges(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $min = trim((string) ($row['min'] ?? ''));
            $max = trim((string) ($row['max'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($min === '' && $max === '' && $label === '') {
                continue;
            }
            $out[] = ['min' => $min, 'max' => $max, 'label' => $label];
        }
        return $out;
    }

    /** @param list<array{min?: string, max?: string, label?: string}> $ranges */
    public static function encodeScoreRanges(array $ranges): ?string
    {
        $clean = self::decodeScoreRanges($ranges);
        if ($clean === []) {
            return null;
        }
        return json_encode($clean, JSON_UNESCAPED_UNICODE) ?: null;
    }

    /** Resumen legible para listados / fallback de score_range. */
    public static function formatScoreRangesSummary(array $ranges): ?string
    {
        $parts = [];
        foreach (self::decodeScoreRanges($ranges) as $r) {
            $span = trim($r['min'] . ($r['min'] !== '' && $r['max'] !== '' ? ' – ' : '') . $r['max']);
            if ($span !== '' && $r['label'] !== '') {
                $parts[] = $span . ' = ' . $r['label'];
            } elseif ($r['label'] !== '') {
                $parts[] = $r['label'];
            } elseif ($span !== '') {
                $parts[] = $span;
            }
        }
        return $parts !== [] ? implode('; ', $parts) : null;
    }

    /** @return array<string, string> */
    public static function modalities(): array
    {
        return [
            'paper' => 'Paper',
            'online' => 'Online',
        ];
    }

    /** @return array<string, string> */
    public static function courseRelationTypes(): array
    {
        return [
            'included' => 'Incluido (gratis con la certificación)',
            'sold_separate' => 'Vendido por separado',
            'bundle_discount' => 'Bundle con descuento',
        ];
    }

    /** Crea una certificación mínima (solo nombre) ligada al proveedor. */
    public function createCertificationStub(int $providerId, string $name): int
    {
        $baseSlug = \App\Support\Str::slug($name);
        $slug = $baseSlug;
        $codeBase = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($baseSlug)) ?: 'CERT');
        $codeBase = substr($codeBase, 0, 48);
        $code = $codeBase;
        $n = 1;
        while ($this->certificationCodeExists($code) || $this->certificationSlugExists($slug)) {
            $n++;
            $slug = $baseSlug . '-' . $n;
            $code = substr($codeBase, 0, 40) . '_' . $n;
        }

        return $this->saveCertification([
            'provider_id' => $providerId,
            'protocol_id' => null,
            'code' => $code,
            'slug' => $slug,
            'name' => $name,
            'modality' => 'online',
            'short_description' => null,
            'description_html' => null,
            'syllabus_html' => null,
            'duration_label' => null,
            'audience' => null,
            'is_level_exam' => 0,
            'skills_json' => null,
            'score_range' => null,
            'score_ranges_json' => null,
            'public_price' => null,
            'cost_price' => null,
            'currency' => 'MXN',
            'cenni_eligible' => 0,
            'cenni_doc_type' => 'none',
            'cenni_included' => 0,
            'cenni_fee' => null,
            'conocer_eligible' => 0,
            'conocer_fee' => null,
            'is_published' => 0,
            'sort_order' => 0,
        ]);
    }

    /** @param list<string> $names */
    public function createCertificationStubs(int $providerId, array $names): int
    {
        $created = 0;
        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $this->createCertificationStub($providerId, $name);
            $created++;
        }
        return $created;
    }

    private function certificationCodeExists(string $code, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM certifications WHERE code = ? AND id <> ? LIMIT 1');
            $stmt->execute([$code, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM certifications WHERE code = ? LIMIT 1');
            $stmt->execute([$code]);
        }
        return (bool) $stmt->fetchColumn();
    }

    private function certificationSlugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM certifications WHERE slug = ? AND id <> ? LIMIT 1');
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM certifications WHERE slug = ? LIMIT 1');
            $stmt->execute([$slug]);
        }
        return (bool) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function certificationsByProvider(int $providerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, code, slug, name, is_published, sort_order
             FROM certifications WHERE provider_id = ?
             ORDER BY sort_order, name'
        );
        $stmt->execute([$providerId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function protocols(bool $onlyActive = false): array
    {
        $sql = 'SELECT pr.*, p.name AS provider_name,
                (SELECT COUNT(*) FROM protocol_steps ps WHERE ps.protocol_id = pr.id AND ps.is_active = 1) AS steps_count
                FROM protocols pr LEFT JOIN providers p ON p.id = pr.provider_id';
        if ($onlyActive) {
            $sql .= ' WHERE pr.is_active = 1';
        }
        $sql .= ' ORDER BY pr.name';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function protocol(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pr.*, p.name AS provider_name FROM protocols pr
             LEFT JOIN providers p ON p.id = pr.provider_id WHERE pr.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, string> */
    public static function protocolPhases(): array
    {
        return [
            'pre_exam' => 'Pre-examen',
            'during_exam' => 'Durante el examen',
            'post_exam' => 'Post-examen',
        ];
    }

    /** @return array<string, string> */
    public static function protocolResponsibles(): array
    {
        return [
            'student' => 'Alumno',
            'admin' => 'Administrador',
            'tr' => 'Teacher Referral (TR)',
            'student_or_tr' => 'Alumno o TR',
            'provider' => 'Certificadora',
            'sep' => 'SEP',
            'system' => 'Sistema (OpenPay, etc.)',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function protocolSteps(int $protocolId, bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM protocol_steps WHERE protocol_id = ?';
        if ($onlyActive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$protocolId]);
        return $stmt->fetchAll();
    }

    public function protocolStep(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocol_steps WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveProtocolStep(array $data, ?int $id = null): int
    {
        $fields = [
            $data['protocol_id'],
            $data['sort_order'],
            $data['phase'],
            $data['title'],
            $data['description'],
            $data['responsible'],
            $data['trigger_days_after_exam'],
            $data['is_active'],
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE protocol_steps SET protocol_id=?, sort_order=?, phase=?, title=?, description=?,
                 responsible=?, trigger_days_after_exam=?, is_active=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO protocol_steps
             (protocol_id, sort_order, phase, title, description, responsible, trigger_days_after_exam, is_active)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteProtocolStep(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM protocol_steps WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function nextProtocolStepOrder(int $protocolId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM protocol_steps WHERE protocol_id = ?');
        $stmt->execute([$protocolId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Abre un caso de certificación y clona los pasos del protocolo como pendientes.
     * El primer paso queda en status=current.
     */
    public function openCertificationCase(array $data): int
    {
        $certId = (int) $data['certification_id'];
        $cert = $this->certification($certId);
        if (!$cert) {
            throw new \RuntimeException('Certificación no encontrada.');
        }
        $protocolId = (int) ($data['protocol_id'] ?? ($cert['protocol_id'] ?? 0));
        if ($protocolId <= 0) {
            throw new \RuntimeException('La certificación no tiene protocolo asignado.');
        }
        $steps = $this->protocolSteps($protocolId, true);
        if ($steps === []) {
            throw new \RuntimeException('El protocolo no tiene pasos activos.');
        }

        $this->pdo->beginTransaction();
        try {
            $firstStepId = (int) $steps[0]['id'];
            $stmt = $this->pdo->prepare(
                'INSERT INTO certification_cases
                 (certification_id, protocol_id, student_user_id, partner_id, student_email, student_name,
                  exam_date, status, current_step_id, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $certId,
                $protocolId,
                $data['student_user_id'] ?? null,
                $data['partner_id'] ?? null,
                $data['student_email'],
                $data['student_name'],
                $data['exam_date'] ?? null,
                'in_progress',
                $firstStepId,
                $data['notes'] ?? null,
            ]);
            $caseId = (int) $this->pdo->lastInsertId();

            $ins = $this->pdo->prepare(
                'INSERT INTO certification_case_steps
                 (case_id, protocol_step_id, sort_order, status) VALUES (?,?,?,?)'
            );
            foreach ($steps as $i => $step) {
                $ins->execute([
                    $caseId,
                    (int) $step['id'],
                    (int) $step['sort_order'],
                    $i === 0 ? 'current' : 'pending',
                ]);
            }

            $this->pdo->commit();
            return $caseId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function certificationCases(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, cert.name AS certification_name, cert.code AS certification_code,
                    pr.name AS protocol_name, ps.title AS current_step_title, ps.sort_order AS current_step_order
             FROM certification_cases c
             JOIN certifications cert ON cert.id = c.certification_id
             JOIN protocols pr ON pr.id = c.protocol_id
             LEFT JOIN protocol_steps ps ON ps.id = c.current_step_id
             ORDER BY c.updated_at DESC, c.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function certificationCase(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, cert.name AS certification_name, cert.code AS certification_code,
                    pr.name AS protocol_name
             FROM certification_cases c
             JOIN certifications cert ON cert.id = c.certification_id
             JOIN protocols pr ON pr.id = c.protocol_id
             WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function certificationCaseSteps(int $caseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cs.*, ps.title, ps.description, ps.phase, ps.responsible, ps.trigger_days_after_exam
             FROM certification_case_steps cs
             JOIN protocol_steps ps ON ps.id = cs.protocol_step_id
             WHERE cs.case_id = ?
             ORDER BY cs.sort_order ASC, cs.id ASC'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    /** Marca el paso actual como done y avanza al siguiente. */
    public function completeCaseStep(int $caseId, int $caseStepId, ?int $completedBy = null, ?string $notes = null): void
    {
        $case = $this->certificationCase($caseId);
        if (!$case || $case['status'] !== 'in_progress') {
            throw new \RuntimeException('Caso no encontrado o no está en progreso.');
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE certification_case_steps
                 SET status = ?, completed_at = NOW(), completed_by = ?, notes = COALESCE(?, notes)
                 WHERE id = ? AND case_id = ? AND status = ?'
            );
            $stmt->execute(['done', $completedBy, $notes, $caseStepId, $caseId, 'current']);
            if ($stmt->rowCount() === 0) {
                throw new \RuntimeException('El paso no está activo o ya fue completado.');
            }

            $next = $this->pdo->prepare(
                'SELECT id, protocol_step_id FROM certification_case_steps
                 WHERE case_id = ? AND status = ? ORDER BY sort_order ASC, id ASC LIMIT 1'
            );
            $next->execute([$caseId, 'pending']);
            $nextRow = $next->fetch();

            if ($nextRow) {
                $this->pdo->prepare(
                    'UPDATE certification_case_steps SET status = ? WHERE id = ?'
                )->execute(['current', (int) $nextRow['id']]);
                $this->pdo->prepare(
                    'UPDATE certification_cases SET current_step_id = ?, updated_at = NOW() WHERE id = ?'
                )->execute([(int) $nextRow['protocol_step_id'], $caseId]);
            } else {
                $this->pdo->prepare(
                    'UPDATE certification_cases SET status = ?, current_step_id = NULL, updated_at = NOW() WHERE id = ?'
                )->execute(['completed', $caseId]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function saveProtocol(array $data, ?int $id = null): int
    {
        $fields = [
            $data['provider_id'], $data['code'], $data['name'], $data['modality'], $data['procedure_html'],
            $data['requires_regulation_signature'], $data['requires_software'], $data['requires_zoom'],
            $data['requires_vm'], $data['uses_inventory'], $data['is_active'],
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE protocols SET provider_id=?, code=?, name=?, modality=?, procedure_html=?,
                 requires_regulation_signature=?, requires_software=?, requires_zoom=?, requires_vm=?,
                 uses_inventory=?, is_active=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO protocols (provider_id, code, name, modality, procedure_html,
             requires_regulation_signature, requires_software, requires_zoom, requires_vm, uses_inventory, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function courses(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM courses';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function course(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM courses WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveCourse(array $data, ?int $id = null): int
    {
        $fields = [
            $data['protocol_id'] ?? null,
            $data['code'], $data['name'], $data['platform_type'], $data['external_url'],
            $data['moodle_course_id'], $data['access_notes'], $data['description'], $data['is_active'],
        ];
        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE courses SET protocol_id=?, code=?, name=?, platform_type=?, external_url=?, moodle_course_id=?,
                 access_notes=?, description=?, is_active=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO courses (protocol_id, code, name, platform_type, external_url, moodle_course_id, access_notes, description, is_active)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function partnerTiers(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM partner_tiers';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, name';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function partnerTierById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM partner_tiers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function savePartnerTier(array $data, ?int $id = null): int
    {
        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE partner_tiers SET code=?, name=?, sort_order=?, description=?, is_active=? WHERE id=?'
            );
            $stmt->execute([
                $data['code'], $data['name'], $data['sort_order'], $data['description'], $data['is_active'], $id,
            ]);
            return $id;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO partner_tiers (code, name, sort_order, description, is_active) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([
            $data['code'], $data['name'], $data['sort_order'], $data['description'], $data['is_active'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function agreements(): array
    {
        return $this->pdo->query(
            'SELECT a.*, t.name AS tier_name FROM agreements a
             JOIN partner_tiers t ON t.id = a.partner_tier_id
             ORDER BY a.year DESC, t.sort_order, a.name'
        )->fetchAll();
    }

    public function agreement(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM agreements WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveAgreement(array $data, ?int $id = null): int
    {
        if (!empty($data['is_current'])) {
            $this->pdo->prepare(
                'UPDATE agreements SET is_current = 0 WHERE partner_tier_id = ?'
            )->execute([$data['partner_tier_id']]);
        }

        $fields = [
            $data['partner_tier_id'], $data['name'], $data['year'], $data['valid_from'],
            $data['valid_to'], $data['notes'], $data['is_current'],
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE agreements SET partner_tier_id=?, name=?, year=?, valid_from=?, valid_to=?, notes=?, is_current=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO agreements (partner_tier_id, name, year, valid_from, valid_to, notes, is_current)
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function agreementPrices(int $agreementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ap.*, c.name AS certification_name, c.code AS certification_code
             FROM agreement_prices ap
             JOIN certifications c ON c.id = ap.certification_id
             WHERE ap.agreement_id = ?
             ORDER BY c.name'
        );
        $stmt->execute([$agreementId]);
        return $stmt->fetchAll();
    }

    public function upsertAgreementPrice(int $agreementId, int $certificationId, float $price, string $currency = 'MXN'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO agreement_prices (agreement_id, certification_id, price, currency)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE price = VALUES(price), currency = VALUES(currency)'
        );
        $stmt->execute([$agreementId, $certificationId, $price, $currency]);
    }

    /** @return array<int, float> partner_tier_id => price */
    public function certificationTierPrices(int $certificationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT partner_tier_id, price FROM certification_tier_prices WHERE certification_id = ?'
        );
        $stmt->execute([$certificationId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['partner_tier_id']] = (float) $row['price'];
        }
        return $out;
    }

    /** @param array<int|string, mixed> $prices tier_id => price string/float/null */
    public function saveCertificationTierPrices(int $certificationId, array $prices): void
    {
        $upsert = $this->pdo->prepare(
            'INSERT INTO certification_tier_prices (certification_id, partner_tier_id, price)
             VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE price = VALUES(price)'
        );
        $delete = $this->pdo->prepare(
            'DELETE FROM certification_tier_prices WHERE certification_id = ? AND partner_tier_id = ?'
        );

        foreach ($prices as $tierId => $raw) {
            $tierId = (int) $tierId;
            if ($tierId < 1) {
                continue;
            }
            $value = is_string($raw) ? trim($raw) : $raw;
            if ($value === null || $value === '') {
                $delete->execute([$certificationId, $tierId]);
                continue;
            }
            $upsert->execute([$certificationId, $tierId, (float) $value]);
        }
    }

    public function certificationTierPrice(int $certificationId, int $partnerTierId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ctp.*, \'MXN\' AS currency
             FROM certification_tier_prices ctp
             WHERE ctp.certification_id = ? AND ctp.partner_tier_id = ?
             LIMIT 1'
        );
        $stmt->execute([$certificationId, $partnerTierId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function certifications(?array $filters = null): array
    {
        $sql = 'SELECT c.*, p.name AS provider_name, pr.name AS protocol_name
                FROM certifications c
                JOIN providers p ON p.id = c.provider_id
                LEFT JOIN protocols pr ON pr.id = c.protocol_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['provider_id'])) {
            $sql .= ' AND c.provider_id = ?';
            $params[] = $filters['provider_id'];
        }
        if (isset($filters['is_published']) && $filters['is_published'] !== '') {
            $sql .= ' AND c.is_published = ?';
            $params[] = (int) $filters['is_published'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (c.name LIKE ? OR c.code LIKE ? OR c.slug LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql .= ' ORDER BY p.name, c.sort_order, c.name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function certification(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, p.name AS provider_name, p.brand_website_url AS provider_brand_website,
                    p.code AS provider_code, pr.name AS protocol_name,
                    pr.procedure_html AS protocol_procedure_html,
                    pr.requires_regulation_signature, pr.requires_software, pr.requires_zoom,
                    pr.requires_vm, pr.uses_inventory
             FROM certifications c
             JOIN providers p ON p.id = c.provider_id
             LEFT JOIN protocols pr ON pr.id = c.protocol_id
             WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function certificationBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, p.name AS provider_name, p.brand_website_url AS provider_brand_website,
                    p.code AS provider_code, pr.name AS protocol_name,
                    pr.procedure_html AS protocol_procedure_html,
                    pr.requires_regulation_signature, pr.requires_software, pr.requires_zoom,
                    pr.requires_vm, pr.uses_inventory
             FROM certifications c
             JOIN providers p ON p.id = c.provider_id
             LEFT JOIN protocols pr ON pr.id = c.protocol_id
             WHERE c.slug = ?'
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveCertification(array $data, ?int $id = null): int
    {
        $skillsJson = $data['skills_json'] ?? null;
        if (is_array($skillsJson)) {
            $skillsJson = json_encode(array_values($skillsJson), JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        $scoreRangesJson = $data['score_ranges_json'] ?? null;
        if (is_array($scoreRangesJson)) {
            $scoreRangesJson = self::encodeScoreRanges($scoreRangesJson);
        }
        $scoreRangeSummary = $data['score_range'] ?? null;
        if ($scoreRangeSummary === null && $scoreRangesJson !== null) {
            $scoreRangeSummary = self::formatScoreRangesSummary(
                self::decodeScoreRanges($scoreRangesJson)
            );
        }

        $fields = [
            $data['provider_id'], $data['protocol_id'], $data['code'], $data['slug'], $data['name'],
            $data['modality'], $data['short_description'], $data['description_html'], $data['syllabus_html'] ?? null,
            $data['duration_label'], $data['audience'],
            (int) ($data['is_level_exam'] ?? 0),
            $skillsJson,
            $scoreRangeSummary,
            $scoreRangesJson,
            $data['public_price'],
            $data['cost_price'] ?? null,
            $data['currency'] ?? 'MXN',
            $data['cenni_eligible'], $data['cenni_doc_type'], $data['cenni_included'], $data['cenni_fee'],
            $data['conocer_eligible'], $data['conocer_fee'],
            (int) ($data['is_published'] ?? 0),
            (int) ($data['is_featured'] ?? 0),
            $data['sort_order'],
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE certifications SET provider_id=?, protocol_id=?, code=?, slug=?, name=?, modality=?,
                 short_description=?, description_html=?, syllabus_html=?, duration_label=?, audience=?,
                 is_level_exam=?, skills_json=?, score_range=?, score_ranges_json=?,
                 public_price=?, cost_price=?, currency=?, cenni_eligible=?, cenni_doc_type=?, cenni_included=?, cenni_fee=?,
                 conocer_eligible=?, conocer_fee=?, is_published=?, is_featured=?, sort_order=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO certifications (
                provider_id, protocol_id, code, slug, name, modality, short_description, description_html,
                syllabus_html, duration_label, audience, is_level_exam, skills_json, score_range, score_ranges_json,
                public_price, cost_price, currency, cenni_eligible, cenni_doc_type,
                cenni_included, cenni_fee, conocer_eligible, conocer_fee, is_published, is_featured, sort_order
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);
        return (int) $this->pdo->lastInsertId();
    }

    public function allocateCertificationCodeSlug(string $name, ?int $excludeId = null): array
    {
        $baseSlug = \App\Support\Str::slug($name);
        $slug = $baseSlug;
        $codeBase = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($baseSlug)) ?: 'CERT');
        $codeBase = substr($codeBase, 0, 48);
        $code = $codeBase;
        $n = 1;
        while ($this->certificationCodeExists($code, $excludeId) || $this->certificationSlugExists($slug, $excludeId)) {
            $n++;
            $slug = $baseSlug . '-' . $n;
            $code = substr($codeBase, 0, 40) . '_' . $n;
        }
        return ['code' => $code, 'slug' => $slug];
    }

    /** @return list<array<string, mixed>> */
    public function certificationCourses(int $certificationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cc.*, co.name AS course_name, co.platform_type, co.external_url, co.moodle_course_id
             FROM certification_courses cc
             JOIN courses co ON co.id = cc.course_id
             WHERE cc.certification_id = ?'
        );
        $stmt->execute([$certificationId]);
        return $stmt->fetchAll();
    }

    public function attachCertificationCourse(
        int $certificationId,
        int $courseId,
        string $relationType = 'included',
        ?float $bundlePrice = null,
        ?string $notes = null
    ): void {
        $allowed = ['included', 'sold_separate', 'bundle_discount'];
        if (!in_array($relationType, $allowed, true)) {
            $relationType = 'included';
        }
        if ($relationType !== 'bundle_discount') {
            $bundlePrice = null;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO certification_courses (certification_id, course_id, relation_type, bundle_price, notes)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE relation_type = VALUES(relation_type),
               bundle_price = VALUES(bundle_price), notes = VALUES(notes)'
        );
        $stmt->execute([$certificationId, $courseId, $relationType, $bundlePrice, $notes]);
    }

    public function detachCertificationCourse(int $certificationId, int $courseId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM certification_courses WHERE certification_id = ? AND course_id = ?'
        );
        $stmt->execute([$certificationId, $courseId]);
    }

    /** @return list<array<string, mixed>> */
    public function partners(): array
    {
        return $this->pdo->query(
            'SELECT p.*, u.email, u.name AS user_name, u.role, u.is_active AS user_active,
                    t.name AS tier_name, a.name AS agreement_name
             FROM partners p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN partner_tiers t ON t.id = p.partner_tier_id
             LEFT JOIN agreements a ON a.id = p.current_agreement_id
             ORDER BY u.name, u.email'
        )->fetchAll();
    }

    public function partner(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.email, u.name AS user_name, u.first_name, u.last_name, u.username, u.role,
                    u.phone AS user_phone, u.is_active AS user_active,
                    t.name AS tier_name, a.name AS agreement_name
             FROM partners p
             JOIN users u ON u.id = p.user_id
             LEFT JOIN partner_tiers t ON t.id = p.partner_tier_id
             LEFT JOIN agreements a ON a.id = p.current_agreement_id
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function currentAgreementForTier(int $tierId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM agreements
             WHERE partner_tier_id = ? AND is_current = 1
             ORDER BY year DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$tierId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function savePartner(array $data, ?int $id = null, ?int $createdBy = null): int
    {
        $this->pdo->beginTransaction();
        try {
            $userId = (int) $data['user_id'];
            $this->pdo->prepare(
                "UPDATE users SET role = 'partner' WHERE id = ? AND role NOT IN ('admin', 'assistant', 'manager')"
            )->execute([$userId]);

            $previousAgreementId = null;
            if ($id) {
                $existing = $this->partner($id);
                $previousAgreementId = $existing['current_agreement_id'] ?? null;
            }

            $fields = [
                $userId,
                $data['partner_tier_id'],
                $data['current_agreement_id'],
                $data['organization'] ?? null,
                $data['phone'] ?? null,
                $data['shipping_address_line'] ?? null,
                $data['shipping_address_line2'] ?? null,
                $data['shipping_neighborhood'] ?? null,
                $data['shipping_city'] ?? null,
                $data['shipping_state'] ?? null,
                $data['shipping_postal_code'] ?? null,
                $data['shipping_country'] ?? 'México',
                $data['signed_agreement_path'] ?? null,
                (int) ($data['requires_invoice'] ?? 0),
                $data['tax_status_path'] ?? null,
                $data['logo_path'] ?? null,
                $data['notes'] ?? null,
            ];

            if ($id) {
                $stmt = $this->pdo->prepare(
                    'UPDATE partners SET user_id=?, partner_tier_id=?, current_agreement_id=?,
                     organization=?, phone=?,
                     shipping_address_line=?, shipping_address_line2=?, shipping_neighborhood=?,
                     shipping_city=?, shipping_state=?, shipping_postal_code=?, shipping_country=?,
                     signed_agreement_path=?, requires_invoice=?, tax_status_path=?, logo_path=?,
                     notes=? WHERE id=?'
                );
                $stmt->execute([...$fields, $id]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO partners (
                        user_id, partner_tier_id, current_agreement_id, organization, phone,
                        shipping_address_line, shipping_address_line2, shipping_neighborhood,
                        shipping_city, shipping_state, shipping_postal_code, shipping_country,
                        signed_agreement_path, requires_invoice, tax_status_path, logo_path, notes
                     ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute($fields);
                $id = (int) $this->pdo->lastInsertId();
            }

            $newAgreementId = $data['current_agreement_id'] ?? null;
            if ($newAgreementId && (int) $newAgreementId !== (int) ($previousAgreementId ?? 0)) {
                if ($previousAgreementId) {
                    $this->pdo->prepare(
                        'UPDATE partner_agreement_assignments SET ended_at = NOW()
                         WHERE partner_id = ? AND agreement_id = ? AND ended_at IS NULL'
                    )->execute([$id, $previousAgreementId]);
                }
                $this->pdo->prepare(
                    'INSERT INTO partner_agreement_assignments (partner_id, agreement_id, reason, created_by)
                     VALUES (?,?,?,?)'
                )->execute([
                    $id,
                    $newAgreementId,
                    $data['assignment_reason'] ?? 'Asignación admin',
                    $createdBy,
                ]);
            }

            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function partnerAssignmentHistory(int $partnerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT paa.*, a.name AS agreement_name, a.year, t.name AS tier_name, u.name AS created_by_name
             FROM partner_agreement_assignments paa
             JOIN agreements a ON a.id = paa.agreement_id
             JOIN partner_tiers t ON t.id = a.partner_tier_id
             LEFT JOIN users u ON u.id = paa.created_by
             WHERE paa.partner_id = ?
             ORDER BY paa.assigned_at DESC'
        );
        $stmt->execute([$partnerId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function assets(string $ownerType, int $ownerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM product_assets WHERE owner_type = ? AND owner_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$ownerType, $ownerId]);
        return $stmt->fetchAll();
    }

    public function asset(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM product_assets WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveAsset(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO product_assets (owner_type, owner_id, asset_type, file_path, title, sort_order)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['owner_type'],
            $data['owner_id'],
            $data['asset_type'],
            $data['file_path'],
            $data['title'],
            $data['sort_order'] ?? 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteAsset(int $id): ?array
    {
        $asset = $this->asset($id);
        if (!$asset) {
            return null;
        }
        $this->pdo->prepare('DELETE FROM product_assets WHERE id = ?')->execute([$id]);
        return $asset;
    }

    /** @return list<string> */
    public static function assetTypesFor(string $ownerType): array
    {
        return match ($ownerType) {
            'provider' => ['provider_logo', 'other'],
            'certification' => [
                'exam_logo', 'certificate_sample', 'badge', 'syllabus_pdf', 'regulation_pdf', 'cover', 'other',
            ],
            'course' => ['cover', 'other'],
            'agreement' => ['regulation_pdf', 'other'],
            default => ['other'],
        };
    }

    public function findPartnerByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, t.name AS tier_name, t.code AS tier_code, a.id AS agreement_id, a.name AS agreement_name
             FROM partners p
             LEFT JOIN partner_tiers t ON t.id = p.partner_tier_id
             LEFT JOIN agreements a ON a.id = COALESCE(
                 p.current_agreement_id,
                 (SELECT ag.id FROM agreements ag
                  WHERE ag.partner_tier_id = p.partner_tier_id AND ag.is_current = 1
                  ORDER BY ag.year DESC LIMIT 1)
             )
             WHERE p.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function publishedCertificationsForPartner(?int $partnerTierId, ?array $filters = null): array
    {
        $sql = 'SELECT c.*, p.name AS provider_name, ctp.price AS partner_price, \'MXN\' AS partner_currency
                FROM certifications c
                JOIN providers p ON p.id = c.provider_id
                LEFT JOIN certification_tier_prices ctp
                  ON ctp.certification_id = c.id AND ctp.partner_tier_id = ?
                WHERE c.is_published = 1';
        $params = [$partnerTierId];

        if (!empty($filters['provider_id'])) {
            $sql .= ' AND c.provider_id = ?';
            $params[] = $filters['provider_id'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (c.name LIKE ? OR c.code LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }
        if (isset($filters['cenni']) && $filters['cenni'] !== '') {
            $sql .= ' AND c.cenni_eligible = ?';
            $params[] = (int) $filters['cenni'];
        }

        $sql .= ' ORDER BY p.name, c.sort_order, c.name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Productos estrella para la vitrina pública (orden preferido).
     *
     * @return list<array<string, mixed>>
     */
    public function publicFeaturedCertifications(): array
    {
        $sql = 'SELECT c.*, p.name AS provider_name, p.code AS provider_code,
                       p.logo_icon_path, p.logo_full_path, p.logo_path
                FROM certifications c
                JOIN providers p ON p.id = c.provider_id
                WHERE c.is_published = 1 AND c.is_featured = 1
                ORDER BY
                  CASE
                    WHEN UPPER(c.code) LIKE \'%ELET%\' OR UPPER(c.name) LIKE \'%ELET%\' THEN 1
                    WHEN UPPER(c.code) LIKE \'%ITEP%\' OR UPPER(c.name) LIKE \'%ITEP%\' THEN 2
                    WHEN UPPER(c.code) LIKE \'%TOEFL%\' OR UPPER(c.name) LIKE \'%TOEFL%\' THEN 3
                    WHEN UPPER(c.code) LIKE \'%LINGUA%\' OR UPPER(c.name) LIKE \'%LINGUA%\' THEN 4
                    WHEN UPPER(c.code) LIKE \'%EXCEL%\' OR UPPER(c.name) LIKE \'%EXCEL%\' THEN 5
                    ELSE 50
                  END,
                  c.sort_order, c.name';
        try {
            return $this->pdo->query($sql)->fetchAll();
        } catch (\Throwable) {
            // Columna is_featured aún no migrada
            return [];
        }
    }

    /**
     * Catálogo público agrupado por proveedor (excluye estrellas si $excludeFeatured).
     *
     * @return list<array{provider_id:int, provider_name:string, provider_code:?string, logo:?string, items:list<array<string,mixed>>}>
     */
    public function publicCatalogGroupedByProvider(bool $excludeFeatured = true): array
    {
        $sql = 'SELECT c.*, p.name AS provider_name, p.code AS provider_code,
                       p.logo_icon_path, p.logo_full_path, p.logo_path
                FROM certifications c
                JOIN providers p ON p.id = c.provider_id
                WHERE c.is_published = 1';
        if ($excludeFeatured) {
            $sql .= ' AND (c.is_featured = 0 OR c.is_featured IS NULL)';
        }
        $sql .= ' ORDER BY p.name, c.sort_order, c.name';

        try {
            $rows = $this->pdo->query($sql)->fetchAll();
        } catch (\Throwable) {
            $rows = $this->pdo->query(
                'SELECT c.*, p.name AS provider_name, p.code AS provider_code,
                        p.logo_icon_path, p.logo_full_path, p.logo_path
                 FROM certifications c
                 JOIN providers p ON p.id = c.provider_id
                 WHERE c.is_published = 1
                 ORDER BY p.name, c.sort_order, c.name'
            )->fetchAll();
        }

        $groups = [];
        foreach ($rows as $row) {
            $pid = (int) $row['provider_id'];
            if (!isset($groups[$pid])) {
                $groups[$pid] = [
                    'provider_id' => $pid,
                    'provider_name' => (string) $row['provider_name'],
                    'provider_code' => $row['provider_code'] ?? null,
                    'logo' => $row['logo_icon_path'] ?? $row['logo_full_path'] ?? $row['logo_path'] ?? null,
                    'items' => [],
                ];
            }
            $groups[$pid]['items'][] = $row;
        }

        return array_values($groups);
    }

    /** @return list<array<string, mixed>> */
    public function publicCourses(): array
    {
        try {
            return $this->pdo->query(
                'SELECT c.*, p.name AS protocol_name
                 FROM courses c
                 LEFT JOIN protocols p ON p.id = c.protocol_id
                 WHERE c.is_active = 1
                 ORDER BY c.name'
            )->fetchAll();
        } catch (\Throwable) {
            return $this->pdo->query(
                'SELECT * FROM courses WHERE is_active = 1 ORDER BY name'
            )->fetchAll();
        }
    }

    /** @return list<array<string, mixed>> */
    public function casesForStudentUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, cert.name AS certification_name, cert.code AS certification_code, cert.slug AS certification_slug,
                    pr.name AS protocol_name, ps.title AS current_step_title, ps.sort_order AS current_step_order,
                    ps.phase AS current_step_phase
             FROM certification_cases c
             JOIN certifications cert ON cert.id = c.certification_id
             JOIN protocols pr ON pr.id = c.protocol_id
             LEFT JOIN protocol_steps ps ON ps.id = c.current_step_id
             WHERE c.student_user_id = ?
             ORDER BY c.updated_at DESC, c.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function counts(): array
    {
        return [
            'providers' => (int) $this->pdo->query('SELECT COUNT(*) FROM providers')->fetchColumn(),
            'certifications' => (int) $this->pdo->query('SELECT COUNT(*) FROM certifications')->fetchColumn(),
            'published' => (int) $this->pdo->query('SELECT COUNT(*) FROM certifications WHERE is_published = 1')->fetchColumn(),
            'courses' => (int) $this->pdo->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
            'partners' => (int) $this->pdo->query('SELECT COUNT(*) FROM partners')->fetchColumn(),
            'agreements' => (int) $this->pdo->query('SELECT COUNT(*) FROM agreements')->fetchColumn(),
            'protocols' => (int) $this->pdo->query('SELECT COUNT(*) FROM protocols')->fetchColumn(),
            'cases' => $this->safeCount('certification_cases'),
            'documents' => $this->safeCount('documents'),
            'tiers' => (int) $this->pdo->query('SELECT COUNT(*) FROM partner_tiers')->fetchColumn(),
            'users' => (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        ];
    }

    /** @return array<string, string> */
    public static function documentTypes(): array
    {
        return [
            'regulation' => 'Reglamento',
            'form' => 'Formulario',
            'checklist' => 'Checklist',
            'instructions' => 'Instrucciones',
            'other' => 'Otro',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function documents(?int $providerId = null, bool $onlyActive = false): array
    {
        $sql = 'SELECT d.*, p.name AS provider_name
                FROM documents d
                LEFT JOIN providers p ON p.id = d.provider_id
                WHERE 1=1';
        $params = [];
        if ($providerId !== null && $providerId > 0) {
            $sql .= ' AND d.provider_id = ?';
            $params[] = $providerId;
        }
        if ($onlyActive) {
            $sql .= ' AND d.is_active = 1';
        }
        $sql .= ' ORDER BY p.name IS NULL, p.name, d.title, d.version DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function document(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.*, p.name AS provider_name
             FROM documents d
             LEFT JOIN providers p ON p.id = d.provider_id
             WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function saveDocument(array $data, ?int $id = null): int
    {
        $fields = [
            $data['provider_id'],
            $data['code'],
            $data['title'],
            $data['version'],
            $data['doc_type'],
            $data['file_path'],
            $data['body_html'] ?? null,
            $data['is_active'],
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE documents SET provider_id=?, code=?, title=?, version=?, doc_type=?,
                 file_path=?, body_html=?, is_active=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO documents (provider_id, code, title, version, doc_type, file_path, body_html, is_active)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteDocument(int $id): void
    {
        $doc = $this->document($id);
        if (!$doc) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM documents WHERE id = ?');
        $stmt->execute([$id]);
        if (!empty($doc['file_path'])) {
            Uploader::delete((string) $doc['file_path']);
        }
    }

    private function safeCount(string $table): int
    {
        try {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
