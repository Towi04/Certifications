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
        $sql = 'SELECT * FROM providers';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name';
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
        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE providers SET code=?, name=?, website_url=?, notes=?, is_active=? WHERE id=?'
            );
            $stmt->execute([
                $data['code'], $data['name'], $data['website_url'], $data['notes'], $data['is_active'], $id,
            ]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO providers (code, name, website_url, notes, is_active) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([
            $data['code'], $data['name'], $data['website_url'], $data['notes'], $data['is_active'],
        ]);
        return (int) $this->pdo->lastInsertId();
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
        $fields = [
            $data['provider_id'], $data['protocol_id'], $data['code'], $data['slug'], $data['name'],
            $data['modality'], $data['short_description'], $data['description_html'], $data['syllabus_html'],
            $data['duration_label'], $data['audience'], $data['public_price'], $data['currency'],
            $data['cenni_eligible'], $data['cenni_doc_type'], $data['cenni_included'], $data['cenni_fee'],
            $data['conocer_eligible'], $data['conocer_fee'], $data['is_published'], $data['sort_order'],
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE certifications SET provider_id=?, protocol_id=?, code=?, slug=?, name=?, modality=?,
                 short_description=?, description_html=?, syllabus_html=?, duration_label=?, audience=?,
                 public_price=?, currency=?, cenni_eligible=?, cenni_doc_type=?, cenni_included=?, cenni_fee=?,
                 conocer_eligible=?, conocer_fee=?, is_published=?, sort_order=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);
            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO certifications (
                provider_id, protocol_id, code, slug, name, modality, short_description, description_html,
                syllabus_html, duration_label, audience, public_price, currency, cenni_eligible, cenni_doc_type,
                cenni_included, cenni_fee, conocer_eligible, conocer_fee, is_published, sort_order
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);
        return (int) $this->pdo->lastInsertId();
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

    public function savePartner(array $data, ?int $id = null): int
    {
        $this->pdo->beginTransaction();
        try {
            $userId = (int) $data['user_id'];
            $this->pdo->prepare(
                "UPDATE users SET role = 'partner' WHERE id = ? AND role <> 'admin'"
            )->execute([$userId]);

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

            $this->pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
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
        ];
    }
}
