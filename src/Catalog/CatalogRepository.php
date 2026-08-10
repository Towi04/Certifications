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
            'certificado' => 'Certificado',
            'constancia_certificado' => 'Constancia y Certificado',
            'certificado_diploma' => 'Certificado y Diploma',
            'constancia_certificado_diploma' => 'Constancia, Certificado y Diploma',
        ];
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    public static function decodeValuePoints(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_string($raw) && !str_starts_with(trim($raw), '[') && !str_starts_with(trim($raw), '{')) {
            // Texto plano: una viñeta por línea
            $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
            $out = [];
            foreach ($lines as $line) {
                $line = trim($line);
                $line = ltrim($line, "•\t-–—* ");
                if ($line !== '') {
                    $out[] = $line;
                }
            }
            return $out;
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $row) {
            if (is_string($row)) {
                $t = trim($row);
                if ($t !== '') {
                    $out[] = $t;
                }
                continue;
            }
            if (is_array($row)) {
                $t = trim((string) ($row['text'] ?? $row['point'] ?? ''));
                if ($t !== '') {
                    $out[] = $t;
                }
            }
        }
        return $out;
    }

    /** @param list<string>|string|null $points */
    public static function encodeValuePoints(array|string|null $points): ?string
    {
        if (is_string($points)) {
            $points = self::decodeValuePoints($points);
        }
        if ($points === null || $points === []) {
            return null;
        }
        $clean = [];
        foreach ($points as $p) {
            $t = trim((string) $p);
            if ($t !== '') {
                $clean[] = $t;
            }
        }
        if ($clean === []) {
            return null;
        }
        return json_encode(array_values($clean), JSON_UNESCAPED_UNICODE) ?: null;
    }

    /**
     * Catálogo de campos del formulario de adquisición por certificación.
     * locked=true → siempre required (cuenta / identidad mínima).
     *
     * @return array<string, array{label:string,locked?:bool,type?:string,default:string}>
     */
    public static function registrationFieldCatalog(): array
    {
        return [
            'first_name' => ['label' => 'Nombre(s)', 'locked' => true, 'type' => 'text', 'default' => 'required'],
            'last_name_p' => ['label' => 'Apellido paterno', 'locked' => true, 'type' => 'text', 'default' => 'required'],
            'last_name_m' => ['label' => 'Apellido materno', 'type' => 'text', 'default' => 'optional'],
            'email' => ['label' => 'Correo', 'locked' => true, 'type' => 'email', 'default' => 'required'],
            'phone' => ['label' => 'Teléfono', 'type' => 'tel', 'default' => 'optional'],
            'curp' => ['label' => 'CURP', 'type' => 'text', 'default' => 'off'],
            'birth_date' => ['label' => 'Fecha de nacimiento', 'type' => 'date', 'default' => 'off'],
            'sex' => ['label' => 'Sexo', 'type' => 'sex', 'default' => 'off'],
            'nationality' => ['label' => 'Nacionalidad', 'type' => 'text', 'default' => 'off'],
            'exam_date' => ['label' => 'Fecha preferida de examen', 'type' => 'date', 'default' => 'required'],
        ];
    }

    /** @return array<string, string> field => off|optional|required */
    public static function defaultRegistrationFields(): array
    {
        $out = [];
        foreach (self::registrationFieldCatalog() as $key => $meta) {
            $out[$key] = (string) ($meta['default'] ?? 'off');
        }

        return $out;
    }

    /** @return array<string, string> field => off|optional|required */
    public static function decodeRegistrationFields(mixed $raw): array
    {
        $defaults = self::defaultRegistrationFields();
        $decoded = [];
        if (is_string($raw) && $raw !== '') {
            $tmp = json_decode($raw, true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        } elseif (is_array($raw)) {
            $decoded = $raw;
        }
        $allowed = ['off', 'optional', 'required'];
        $out = $defaults;
        foreach ($defaults as $key => $fallback) {
            $mode = strtolower(trim((string) ($decoded[$key] ?? $fallback)));
            if (!in_array($mode, $allowed, true)) {
                $mode = $fallback;
            }
            $meta = self::registrationFieldCatalog()[$key] ?? [];
            if (!empty($meta['locked'])) {
                $mode = 'required';
            }
            $out[$key] = $mode;
        }

        return $out;
    }

    /** @param array<string, string>|string|null $fields */
    public static function encodeRegistrationFields(array|string|null $fields): ?string
    {
        if (is_string($fields)) {
            $fields = self::decodeRegistrationFields($fields);
        }
        if ($fields === null) {
            return null;
        }
        $normalized = self::decodeRegistrationFields($fields);
        // Si coincide con defaults, igual persistimos para que el admin vea lo guardado.
        return json_encode($normalized, JSON_UNESCAPED_UNICODE) ?: null;
    }

    public static function registrationFieldMode(array $fields, string $key): string
    {
        $fields = self::decodeRegistrationFields($fields);

        return $fields[$key] ?? 'off';
    }

    public static function registrationFieldEnabled(array $fields, string $key): bool
    {
        return self::registrationFieldMode($fields, $key) !== 'off';
    }

    public static function registrationFieldRequired(array $fields, string $key): bool
    {
        return self::registrationFieldMode($fields, $key) === 'required';
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
     * Reordena pasos del protocolo (1..n) y sincroniza sort_order en casos abiertos.
     *
     * @param list<int> $orderedStepIds IDs en el orden deseado
     */
    public function reorderProtocolSteps(int $protocolId, array $orderedStepIds): void
    {
        $existing = $this->protocolSteps($protocolId, false);
        if (!$existing) {
            return;
        }
        $existingIds = array_map(static fn (array $s): int => (int) $s['id'], $existing);
        $ordered = [];
        foreach ($orderedStepIds as $id) {
            $id = (int) $id;
            if ($id > 0 && in_array($id, $existingIds, true) && !in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }
        foreach ($existingIds as $id) {
            if (!in_array($id, $ordered, true)) {
                $ordered[] = $id;
            }
        }
        if (count($ordered) !== count($existingIds)) {
            throw new \RuntimeException('La lista de pasos no coincide con el protocolo.');
        }

        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare(
                'UPDATE protocol_steps SET sort_order = ? WHERE id = ? AND protocol_id = ?'
            );
            foreach ($ordered as $i => $stepId) {
                $upd->execute([$i + 1, $stepId, $protocolId]);
            }
            // Mantener el timeline de casos existentes alineado al nuevo orden del protocolo.
            $this->pdo->prepare(
                'UPDATE certification_case_steps cs
                 INNER JOIN protocol_steps ps ON ps.id = cs.protocol_step_id
                 SET cs.sort_order = ps.sort_order
                 WHERE ps.protocol_id = ?'
            )->execute([$protocolId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Mueve un paso una posición arriba o abajo dentro de su protocolo. */
    public function moveProtocolStep(int $protocolId, int $stepId, string $direction): void
    {
        $steps = $this->protocolSteps($protocolId, false);
        $ids = array_map(static fn (array $s): int => (int) $s['id'], $steps);
        $index = array_search($stepId, $ids, true);
        if ($index === false) {
            throw new \RuntimeException('Paso no encontrado en este protocolo.');
        }
        $dir = strtolower($direction);
        if ($dir === 'up') {
            if ($index === 0) {
                return;
            }
            [$ids[$index - 1], $ids[$index]] = [$ids[$index], $ids[$index - 1]];
        } elseif ($dir === 'down') {
            if ($index >= count($ids) - 1) {
                return;
            }
            [$ids[$index + 1], $ids[$index]] = [$ids[$index], $ids[$index + 1]];
        } else {
            throw new \InvalidArgumentException('Dirección inválida (usa up o down).');
        }
        $this->reorderProtocolSteps($protocolId, $ids);
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
            $cenniProcess = (string) ($cert['cenni_process'] ?? 'none');
            if ($cenniProcess === '' || $cenniProcess === '0') {
                // Compat si aún no corre la migración: inferir
                if ((int) ($cert['cenni_eligible'] ?? 0) === 1) {
                    $hayElet = stripos((string) ($cert['code'] ?? ''), 'ELET') !== false
                        || stripos((string) ($cert['name'] ?? ''), 'ELET') !== false
                        || stripos((string) ($cert['name'] ?? ''), 'ELeT') !== false;
                    $cenniProcess = $hayElet ? 'uks_external' : 'doceo_managed';
                } else {
                    $cenniProcess = 'none';
                }
            }
            $cenniStatus = match ($cenniProcess) {
                'uks_external' => 'awaiting_uks_upload',
                'doceo_managed' => 'awaiting_pdv_upload',
                default => 'none',
            };

            $firstStepId = (int) $steps[0]['id'];
            $stmt = $this->pdo->prepare(
                'INSERT INTO certification_cases
                 (certification_id, protocol_id, student_user_id, partner_id, student_email, student_name,
                  student_last_name_p, student_last_name_m, student_phone, student_curp, student_birth_date,
                  student_sex, student_nationality, exam_date, exam_time,
                  cc_email, cenni_status, status, current_step_id, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $certId,
                $protocolId,
                $data['student_user_id'] ?? null,
                $data['partner_id'] ?? null,
                $data['student_email'],
                $data['student_name'],
                $data['student_last_name_p'] ?? null,
                $data['student_last_name_m'] ?? null,
                $data['student_phone'] ?? null,
                $data['student_curp'] ?? null,
                $data['student_birth_date'] ?? null,
                $data['student_sex'] ?? null,
                $data['student_nationality'] ?? null,
                $data['exam_date'] ?? null,
                $data['exam_time'] ?? null,
                $data['cc_email'] ?? null,
                $cenniStatus,
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

    /**
     * Marca como hecho el primer paso cuyo título coincida (aunque no sea current)
     * y recalcula el paso current.
     *
     * @param list<string> $keywords
     */
    public function markCaseStepDoneByKeywords(int $caseId, array $keywords, ?int $completedBy = null, ?string $notes = null): bool
    {
        $steps = $this->certificationCaseSteps($caseId);
        $target = null;
        foreach ($steps as $step) {
            if (in_array(($step['status'] ?? ''), ['done', 'skipped'], true)) {
                continue;
            }
            $title = mb_strtolower((string) ($step['title'] ?? ''));
            foreach ($keywords as $kw) {
                $kw = mb_strtolower(trim($kw));
                if ($kw !== '' && str_contains($title, $kw)) {
                    $target = $step;
                    break 2;
                }
            }
        }
        if (!$target) {
            return false;
        }

        $this->pdo->prepare(
            'UPDATE certification_case_steps
             SET status = ?, completed_at = NOW(), completed_by = ?, notes = COALESCE(?, notes)
             WHERE id = ? AND case_id = ?'
        )->execute(['done', $completedBy, $notes, (int) $target['id'], $caseId]);

        $this->recomputeCaseCurrentStep($caseId);
        return true;
    }

    public function recomputeCaseCurrentStep(int $caseId): void
    {
        // Limpia currents huérfanos
        $this->pdo->prepare(
            "UPDATE certification_case_steps SET status = 'pending'
             WHERE case_id = ? AND status = 'current'"
        )->execute([$caseId]);

        $next = $this->pdo->prepare(
            "SELECT id, protocol_step_id FROM certification_case_steps
             WHERE case_id = ? AND status = 'pending'
             ORDER BY sort_order ASC, id ASC LIMIT 1"
        );
        $next->execute([$caseId]);
        $row = $next->fetch();
        if ($row) {
            $this->pdo->prepare(
                "UPDATE certification_case_steps SET status = 'current' WHERE id = ?"
            )->execute([(int) $row['id']]);
            $this->pdo->prepare(
                'UPDATE certification_cases SET current_step_id = ?, status = ?, updated_at = NOW() WHERE id = ?'
            )->execute([(int) $row['protocol_step_id'], 'in_progress', $caseId]);
        } else {
            $this->pdo->prepare(
                'UPDATE certification_cases SET status = ?, current_step_id = NULL, updated_at = NOW() WHERE id = ?'
            )->execute(['completed', $caseId]);
        }
    }

    /**
     * Completa el paso current si su título coincide con alguna palabra clave.
     *
     * @param list<string> $keywords
     */
    public function completeCurrentStepMatching(int $caseId, array $keywords, ?int $completedBy = null, ?string $notes = null): bool
    {
        return $this->markCaseStepDoneByKeywords($caseId, $keywords, $completedBy, $notes);
    }

    /** @return array<string, mixed>|null */
    public function regulationDocumentForCertification(int $certificationId): ?array
    {
        $this->ensureCertificationDocsTable();
        $stmt = $this->pdo->prepare(
            "SELECT d.*
             FROM certification_docs cd
             INNER JOIN documents d ON d.id = cd.document_id
             WHERE cd.certification_id = ? AND cd.stage = 'purchase'
               AND d.is_active = 1 AND d.doc_type = 'regulation'
             ORDER BY cd.id DESC
             LIMIT 1"
        );
        $stmt->execute([$certificationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function signCaseRegulation(int $caseId, string $signerName, ?int $documentId, ?int $userId): void
    {
        $this->ensureRegulationSignatureColumns();
        $signerName = trim($signerName);
        if ($signerName === '') {
            throw new \RuntimeException('Escribe tu nombre completo para firmar el reglamento.');
        }
        $this->updateCertificationCase($caseId, [
            'regulation_document_id' => $documentId,
            'regulation_signed_at' => date('Y-m-d H:i:s'),
            'regulation_signer_name' => $signerName,
        ]);
        $this->completeCurrentStepMatching(
            $caseId,
            ['reglamento', 'firmar'],
            $userId,
            'Reglamento firmado por el alumno: ' . $signerName
        );
    }

    private function ensureRegulationSignatureColumns(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $cols = [
            'regulation_document_id' => 'BIGINT UNSIGNED NULL',
            'regulation_signed_at' => 'DATETIME NULL',
            'regulation_signer_name' => 'VARCHAR(190) NULL',
        ];
        foreach ($cols as $name => $def) {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute(['certification_cases', $name]);
            if ((int) $stmt->fetchColumn() === 0) {
                $this->pdo->exec('ALTER TABLE certification_cases ADD COLUMN ' . $name . ' ' . $def);
            }
        }
        $done = true;
    }

    public function saveProtocol(array $data, ?int $id = null): int
    {
        $fields = [
            $data['provider_id'], $data['code'], $data['name'], $data['modality'], $data['procedure_html'],
            $data['requires_regulation_signature'], $data['requires_software'], $data['requires_zoom'],
            $data['requires_vm'], $data['uses_inventory'],
            $data['export_format'] ?? 'none',
            $data['provider_request_template'] ?? null,
            $data['student_access_template'] ?? null,
            $data['is_active'],
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE protocols SET provider_id=?, code=?, name=?, modality=?, procedure_html=?,
                 requires_regulation_signature=?, requires_software=?, requires_zoom=?, requires_vm=?,
                 uses_inventory=?, export_format=?, provider_request_template=?, student_access_template=?,
                 is_active=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO protocols (provider_id, code, name, modality, procedure_html,
             requires_regulation_signature, requires_software, requires_zoom, requires_vm, uses_inventory,
             export_format, provider_request_template, student_access_template, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);
        return (int) $this->pdo->lastInsertId();
    }

    public function certificationCaseDetailed(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, cert.name AS certification_name, cert.code AS certification_code,
                    cert.public_price, cert.cenni_eligible, cert.cenni_doc_type, cert.cenni_included,
                    cert.cenni_fee, cert.cenni_process,
                    pr.name AS protocol_name, pr.export_format, pr.provider_request_template,
                    pr.student_access_template, pr.provider_id, pr.requires_regulation_signature,
                    prov.code AS provider_code, prov.name AS provider_name,
                    prov.contact_email AS provider_contact_email,
                    pu.email AS partner_email, p.organization AS partner_organization
             FROM certification_cases c
             JOIN certifications cert ON cert.id = c.certification_id
             JOIN protocols pr ON pr.id = c.protocol_id
             LEFT JOIN providers prov ON prov.id = pr.provider_id
             LEFT JOIN partners p ON p.id = c.partner_id
             LEFT JOIN users pu ON pu.id = p.user_id
             WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function certificationCaseByOpenPayChargeId(string $chargeId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM certification_cases WHERE openpay_charge_id = ? LIMIT 1');
        $stmt->execute([$chargeId]);
        $row = $stmt->fetch();
        return $row ? $this->certificationCaseDetailed((int) $row['id']) : null;
    }

    public function certificationCaseByOpenPayOrderId(string $orderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM certification_cases WHERE openpay_order_id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch();
        return $row ? $this->certificationCaseDetailed((int) $row['id']) : null;
    }

    /** @param array<string, mixed> $data */
    public function logOpenPayWebhook(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO openpay_webhook_events
             (event_type, openpay_charge_id, order_id, case_id, payload_json, processed)
             VALUES (?,?,?,?,?,0)'
        );
        $stmt->execute([
            $data['event_type'] ?? null,
            $data['openpay_charge_id'] ?? null,
            $data['order_id'] ?? null,
            $data['case_id'] ?? null,
            $data['payload_json'] ?? '{}',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function attachOpenPayWebhookCase(int $eventId, int $caseId): void
    {
        $this->pdo->prepare('UPDATE openpay_webhook_events SET case_id = ? WHERE id = ?')
            ->execute([$caseId, $eventId]);
    }

    public function markOpenPayWebhookProcessed(int $eventId, bool $ok, ?string $error): void
    {
        $this->pdo->prepare(
            'UPDATE openpay_webhook_events SET processed = ?, error_message = ? WHERE id = ?'
        )->execute([$ok ? 1 : 0, $error, $eventId]);
    }

    /** @param array<string, mixed> $fields */
    public function updateCertificationCase(int $id, array $fields): void
    {
        $allowed = [
            'student_name', 'student_last_name_p', 'student_last_name_m', 'student_email', 'student_phone',
            'student_curp', 'student_birth_date', 'student_sex', 'student_nationality',
            'exam_date', 'exam_time', 'reschedule_date', 'reschedule_time',
            'folio_id', 'access_key', 'zoom_url', 'prep_doc_url', 'access_doc_url',
            'moodle_user', 'moodle_password',             'payment_proof_path', 'payment_confirmed_at',
            'provider_export_path', 'provider_request_sent_at', 'cancel_reason', 'results_url',
            'cc_email', 'notes', 'status', 'partner_id',
            'openpay_charge_id', 'openpay_order_id', 'openpay_clabe', 'openpay_bank', 'openpay_agreement',
            'openpay_reference', 'openpay_amount', 'openpay_status', 'openpay_due_at', 'openpay_paid_at',
            'openpay_pdf_url', 'cenni_status', 'cenni_folio', 'cenni_notes', 'cenni_status_updated_at',
            'regulation_document_id', 'regulation_signed_at', 'regulation_signer_name',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) {
                continue;
            }
            $sets[] = $col . ' = ?';
            $val = $fields[$col];
            $params[] = $val === '' ? null : $val;
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        $sql = 'UPDATE certification_cases SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function addCaseAttachment(int $caseId, string $kind, ?string $label, string $filePath, ?int $uploadedBy): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_attachments (case_id, kind, label, file_path, uploaded_by) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([$caseId, $kind, $label, $filePath, $uploadedBy]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function caseAttachments(int $caseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM case_attachments WHERE case_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    /** @param array<string, mixed> $data */
    public function logCaseMail(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_mail_log
             (case_id, template_code, to_email, cc_email, subject, attachment_path, status, error_message, sent_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['case_id'],
            $data['template_code'] ?? null,
            $data['to_email'],
            $data['cc_email'] ?? null,
            $data['subject'],
            $data['attachment_path'] ?? null,
            $data['status'] ?? 'sent',
            $data['error_message'] ?? null,
            $data['sent_by'] ?? null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function caseMailLog(int $caseId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM case_mail_log WHERE case_id = ? ORDER BY id DESC LIMIT ' . (int) $limit
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function mailTemplates(bool $onlyActive = false): array
    {
        try {
            $sql = 'SELECT * FROM mail_templates';
            if ($onlyActive) {
                $sql .= ' WHERE is_active = 1';
            }
            $sql .= ' ORDER BY name';
            return $this->pdo->query($sql)->fetchAll();
        } catch (\Throwable) {
            return [];
        }
    }

    public function mailTemplateByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mail_templates WHERE code = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        return $row ?: null;
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

    /** Actualiza solo costo Doceo y precio público (vista masiva). */
    public function updateCertificationPrices(int $certificationId, mixed $costPrice, mixed $publicPrice): void
    {
        $cost = is_string($costPrice) ? trim($costPrice) : $costPrice;
        $public = is_string($publicPrice) ? trim($publicPrice) : $publicPrice;
        $stmt = $this->pdo->prepare(
            'UPDATE certifications SET cost_price = ?, public_price = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([
            $cost === null || $cost === '' ? null : (float) $cost,
            $public === null || $public === '' ? null : (float) $public,
            $certificationId,
        ]);
    }

    /**
     * Certificaciones con precios por nivel y reglamento (stage purchase) para la matriz admin.
     *
     * @return list<array<string, mixed>>
     */
    public function certificationsPricingMatrix(?int $providerId = null, ?string $q = null): array
    {
        $filters = [];
        if ($providerId !== null && $providerId > 0) {
            $filters['provider_id'] = $providerId;
        }
        if ($q !== null && trim($q) !== '') {
            $filters['q'] = trim($q);
        }
        $items = $this->certifications($filters !== [] ? $filters : null);
        if ($items === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $items);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $tierMap = [];
        $stmt = $this->pdo->prepare(
            "SELECT certification_id, partner_tier_id, price
             FROM certification_tier_prices
             WHERE certification_id IN ($placeholders)"
        );
        $stmt->execute($ids);
        foreach ($stmt->fetchAll() as $row) {
            $cid = (int) $row['certification_id'];
            $tierMap[$cid][(int) $row['partner_tier_id']] = (float) $row['price'];
        }

        $regMap = [];
        try {
            $stmt = $this->pdo->prepare(
                "SELECT certification_id, document_id
                 FROM certification_docs
                 WHERE stage = 'purchase' AND certification_id IN ($placeholders)
                 ORDER BY id DESC"
            );
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) {
                $cid = (int) $row['certification_id'];
                if (!isset($regMap[$cid])) {
                    $regMap[$cid] = (int) $row['document_id'];
                }
            }
        } catch (\Throwable) {
            // tabla puede no existir hasta migrar
        }

        foreach ($items as &$item) {
            $cid = (int) $item['id'];
            $item['tier_prices'] = $tierMap[$cid] ?? [];
            $item['regulation_document_id'] = $regMap[$cid] ?? null;
        }
        unset($item);

        return $items;
    }

    /** @return list<array<string, mixed>> */
    public function regulationDocuments(?int $providerId = null): array
    {
        $sql = "SELECT d.*, p.name AS provider_name
                FROM documents d
                LEFT JOIN providers p ON p.id = d.provider_id
                WHERE d.is_active = 1 AND d.doc_type = 'regulation'";
        $params = [];
        if ($providerId !== null && $providerId > 0) {
            $sql .= ' AND (d.provider_id = ? OR d.provider_id IS NULL)';
            $params[] = $providerId;
        }
        $sql .= ' ORDER BY (d.provider_id IS NULL), d.title, d.version DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function setCertificationRegulationDocument(int $certificationId, ?int $documentId): void
    {
        $this->ensureCertificationDocsTable();
        $this->pdo->prepare(
            "DELETE FROM certification_docs WHERE certification_id = ? AND stage = 'purchase'"
        )->execute([$certificationId]);

        if ($documentId === null || $documentId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO certification_docs (certification_id, document_id, is_required, stage)
             VALUES (?,?,1,?)'
        );
        $stmt->execute([$certificationId, $documentId, 'purchase']);
    }

    /** Asigna el mismo reglamento a todas las certificaciones del proveedor. @return int filas afectadas */
    public function assignRegulationToProviderCertifications(int $providerId, int $documentId): int
    {
        $certs = $this->certifications(['provider_id' => $providerId]);
        $n = 0;
        foreach ($certs as $cert) {
            $this->setCertificationRegulationDocument((int) $cert['id'], $documentId);
            $n++;
        }
        return $n;
    }

    private function ensureCertificationDocsTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS certification_docs (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              certification_id BIGINT UNSIGNED NOT NULL,
              document_id BIGINT UNSIGNED NOT NULL,
              is_required TINYINT(1) NOT NULL DEFAULT 1,
              stage ENUM('purchase', 'exam', 'cenni', 'conocer', 'other') NOT NULL DEFAULT 'purchase',
              UNIQUE KEY uq_cert_doc (certification_id, document_id, stage),
              CONSTRAINT fk_cd_cert FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE CASCADE,
              CONSTRAINT fk_cd_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $done = true;
    }

    private function ensureRegistrationFieldsColumn(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute(['certifications', 'registration_fields_json']);
        if ((int) $stmt->fetchColumn() === 0) {
            $this->pdo->exec(
                "ALTER TABLE certifications ADD COLUMN registration_fields_json JSON NULL COMMENT 'Campos adquisición off|optional|required' AFTER value_points_json"
            );
        }
        $done = true;
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
        $this->ensureRegistrationFieldsColumn();
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
            $data['modality'], $data['short_description'],
            $data['value_points_json'] ?? null,
            $data['registration_fields_json'] ?? null,
            $data['description_html'], $data['syllabus_html'] ?? null,
            $data['duration_label'], $data['audience'],
            (int) ($data['is_level_exam'] ?? 0),
            $skillsJson,
            $scoreRangeSummary,
            $scoreRangesJson,
            $data['public_price'],
            $data['cost_price'] ?? null,
            $data['currency'] ?? 'MXN',
            $data['cenni_eligible'], $data['cenni_doc_type'], $data['cenni_included'], $data['cenni_fee'],
            $data['cenni_process'] ?? 'none',
            $data['conocer_eligible'], $data['conocer_fee'],
            (int) ($data['is_published'] ?? 0),
            (int) ($data['is_featured'] ?? 0),
            $data['sort_order'],
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE certifications SET provider_id=?, protocol_id=?, code=?, slug=?, name=?, modality=?,
                 short_description=?, value_points_json=?, registration_fields_json=?, description_html=?, syllabus_html=?, duration_label=?, audience=?,
                 is_level_exam=?, skills_json=?, score_range=?, score_ranges_json=?,
                 public_price=?, cost_price=?, currency=?, cenni_eligible=?, cenni_doc_type=?, cenni_included=?, cenni_fee=?,
                 cenni_process=?, conocer_eligible=?, conocer_fee=?, is_published=?, is_featured=?, sort_order=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO certifications (
                provider_id, protocol_id, code, slug, name, modality, short_description, value_points_json, registration_fields_json, description_html,
                syllabus_html, duration_label, audience, is_level_exam, skills_json, score_range, score_ranges_json,
                public_price, cost_price, currency, cenni_eligible, cenni_doc_type,
                cenni_included, cenni_fee, cenni_process, conocer_eligible, conocer_fee, is_published, is_featured, sort_order
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
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
        return $this->attachCertificationVisuals($stmt->fetchAll());
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
            return $this->attachCertificationVisuals($this->pdo->query($sql)->fetchAll());
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

        $rows = $this->attachCertificationVisuals($rows);

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

    /**
     * Adjunta logo de la certificación (exam_logo > badge > cover) a cada fila.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    public function attachCertificationVisuals(array $items): array
    {
        if ($items === []) {
            return [];
        }
        $ids = [];
        foreach ($items as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        $idList = array_keys($ids);
        if ($idList === []) {
            return $items;
        }

        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT owner_id, asset_type, file_path
             FROM product_assets
             WHERE owner_type = 'certification'
               AND owner_id IN ($placeholders)
               AND asset_type IN ('exam_logo', 'badge', 'cover')
             ORDER BY
               CASE asset_type
                 WHEN 'exam_logo' THEN 1
                 WHEN 'badge' THEN 2
                 ELSE 3
               END,
               sort_order, id"
        );
        $stmt->execute($idList);
        $logos = [];
        foreach ($stmt->fetchAll() as $asset) {
            $oid = (int) $asset['owner_id'];
            if (!isset($logos[$oid]) && !empty($asset['file_path'])) {
                $logos[$oid] = (string) $asset['file_path'];
            }
        }

        foreach ($items as &$row) {
            $cid = (int) ($row['id'] ?? 0);
            $row['exam_logo_path'] = $logos[$cid] ?? null;
        }
        unset($row);

        return $items;
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
