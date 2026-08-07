<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Database\Connection;
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
                'UPDATE providers SET code=?, name=?, website_url=?, logo_path=?,
                 logo_icon_path=?, logo_full_path=?,
                 auth_proof_type=?, auth_proof_url=?, auth_proof_path=?, is_active=?
                 WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO providers (
                code, name, website_url, logo_path, logo_icon_path, logo_full_path,
                auth_proof_type, auth_proof_url, auth_proof_path, is_active
             ) VALUES (?,?,?,?,?,?,?,?,?,?)'
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
        if (!empty($data['is_current'])) {
            $this->pdo->prepare(
                'UPDATE provider_agreements SET is_current = 0 WHERE provider_id = ?'
            )->execute([$data['provider_id']]);
        }

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
            $data['is_current'] ? 1 : 0,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setCurrentProviderAgreement(int $providerId, int $agreementId): void
    {
        $this->pdo->prepare(
            'UPDATE provider_agreements SET is_current = 0 WHERE provider_id = ?'
        )->execute([$providerId]);
        $this->pdo->prepare(
            'UPDATE provider_agreements SET is_current = 1 WHERE id = ? AND provider_id = ?'
        )->execute([$agreementId, $providerId]);
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
            'constancia_certificado_diploma' => 'Constancia, Certificado y Diploma',
        ];
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
            'public_price' => null,
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
        $sql = 'SELECT pr.*, p.name AS provider_name FROM protocols pr LEFT JOIN providers p ON p.id = pr.provider_id';
        if ($onlyActive) {
            $sql .= ' WHERE pr.is_active = 1';
        }
        $sql .= ' ORDER BY pr.name';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function protocol(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM protocols WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
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
            $data['code'], $data['name'], $data['platform_type'], $data['external_url'],
            $data['moodle_course_id'], $data['access_notes'], $data['description'], $data['is_active'],
        ];
        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE courses SET code=?, name=?, platform_type=?, external_url=?, moodle_course_id=?,
                 access_notes=?, description=?, is_active=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO courses (code, name, platform_type, external_url, moodle_course_id, access_notes, description, is_active)
             VALUES (?,?,?,?,?,?,?,?)'
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
            'SELECT c.*, p.name AS provider_name, p.code AS provider_code, pr.name AS protocol_name,
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
            'SELECT c.*, p.name AS provider_name, p.code AS provider_code, pr.name AS protocol_name,
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

        $fields = [
            $data['provider_id'], $data['protocol_id'], $data['code'], $data['slug'], $data['name'],
            $data['modality'], $data['short_description'], $data['description_html'], $data['syllabus_html'],
            $data['duration_label'], $data['audience'],
            (int) ($data['is_level_exam'] ?? 0),
            $skillsJson,
            $data['score_range'] ?? null,
            $data['public_price'], $data['currency'],
            $data['cenni_eligible'], $data['cenni_doc_type'], $data['cenni_included'], $data['cenni_fee'],
            $data['conocer_eligible'], $data['conocer_fee'], $data['is_published'], $data['sort_order'],
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE certifications SET provider_id=?, protocol_id=?, code=?, slug=?, name=?, modality=?,
                 short_description=?, description_html=?, syllabus_html=?, duration_label=?, audience=?,
                 is_level_exam=?, skills_json=?, score_range=?,
                 public_price=?, currency=?, cenni_eligible=?, cenni_doc_type=?, cenni_included=?, cenni_fee=?,
                 conocer_eligible=?, conocer_fee=?, is_published=?, sort_order=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO certifications (
                provider_id, protocol_id, code, slug, name, modality, short_description, description_html,
                syllabus_html, duration_label, audience, is_level_exam, skills_json, score_range,
                public_price, currency, cenni_eligible, cenni_doc_type,
                cenni_included, cenni_fee, conocer_eligible, conocer_fee, is_published, sort_order
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
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
            'SELECT p.*, u.email, u.name AS user_name, u.role
             FROM partners p
             JOIN users u ON u.id = p.user_id
             WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function usersAvailableForPartner(?int $currentUserId = null): array
    {
        $sql = 'SELECT u.id, u.email, u.name, u.role
                FROM users u
                LEFT JOIN partners p ON p.user_id = u.id
                WHERE (p.id IS NULL OR u.id = ?)
                  AND u.role IN (\'partner\', \'student\', \'admin\')
                ORDER BY u.name, u.email';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$currentUserId ?? 0]);
        return $stmt->fetchAll();
    }

    public function savePartner(array $data, ?int $id = null, ?int $createdBy = null): int
    {
        $this->pdo->beginTransaction();
        try {
            $userId = (int) $data['user_id'];
            $this->pdo->prepare(
                "UPDATE users SET role = 'partner' WHERE id = ? AND role <> 'admin'"
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
                $data['organization'],
                $data['phone'],
                $data['notes'],
            ];

            if ($id) {
                $stmt = $this->pdo->prepare(
                    'UPDATE partners SET user_id=?, partner_tier_id=?, current_agreement_id=?,
                     organization=?, phone=?, notes=? WHERE id=?'
                );
                $stmt->execute([...$fields, $id]);
            } else {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO partners (user_id, partner_tier_id, current_agreement_id, organization, phone, notes)
                     VALUES (?,?,?,?,?,?)'
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
    public function publishedCertificationsForPartner(?int $agreementId, ?array $filters = null): array
    {
        $sql = 'SELECT c.*, p.name AS provider_name, ap.price AS partner_price, ap.currency AS partner_currency
                FROM certifications c
                JOIN providers p ON p.id = c.provider_id
                LEFT JOIN agreement_prices ap ON ap.certification_id = c.id AND ap.agreement_id = ?
                WHERE c.is_published = 1';
        $params = [$agreementId];

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
            'tiers' => (int) $this->pdo->query('SELECT COUNT(*) FROM partner_tiers')->fetchColumn(),
        ];
    }
}
