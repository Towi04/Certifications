<?php

declare(strict_types=1);

namespace App\Catalog;

use PDO;

final class ProviderSetupRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \App\Database\Connection::get();
    }

    public function ensureProviderSetupSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS provider_groups (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  provider_id BIGINT UNSIGNED NOT NULL,
                  code VARCHAR(64) NOT NULL,
                  name VARCHAR(190) NOT NULL,
                  description TEXT NULL,
                  sort_order INT NOT NULL DEFAULT 0,
                  is_active TINYINT(1) NOT NULL DEFAULT 1,
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                  UNIQUE KEY uq_provider_group_code (provider_id, code),
                  KEY idx_provider_groups_provider (provider_id),
                  CONSTRAINT fk_provider_groups_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable) {
        }

        $this->ensureColumn('certifications', 'provider_group_id', 'BIGINT UNSIGNED NULL', 'provider_id');
        $this->ensureColumn(
            'providers',
            'registration_fields_json',
            "JSON NULL COMMENT 'Campos disponibles para adquisición (elegibles en cada certificación)'",
            'brand_website_url'
        );

        $this->ensureColumn(
            'documents',
            'scope_type',
            "ENUM('provider','group','certification') NOT NULL DEFAULT 'provider' COMMENT 'Alcance: empresa, grupo o certificación'",
            'provider_id'
        );
        $this->ensureColumn('documents', 'provider_group_id', 'BIGINT UNSIGNED NULL', 'scope_type');
        $this->ensureColumn('documents', 'certification_id', 'BIGINT UNSIGNED NULL', 'provider_group_id');
        $this->ensureColumn('documents', 'share_token', 'VARCHAR(64) NULL', 'file_path');

        $this->ensureDocumentTypeColumn();
        $this->ensureShareTokenIndex();
        $this->ensureProviderLinksTable();
        $this->pruneEmptyDefaultGroups();
        $this->backfillDocumentShareTokens();

        $done = true;
    }

    /** @return array<string, string> */
    public static function providerLinkTypes(): array
    {
        return [
            'study_material' => 'Material de estudio',
            'software' => 'Software / descarga',
            'exam_portal' => 'Portal de examen',
            'other' => 'Otro',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function providerLinks(int $providerId, bool $onlyActive = false): array
    {
        $this->ensureProviderSetupSchema();
        $sql = 'SELECT l.*,
                       pg.name AS group_name,
                       c.name AS certification_name
                FROM provider_links l
                LEFT JOIN provider_groups pg ON pg.id = l.provider_group_id
                LEFT JOIN certifications c ON c.id = l.certification_id
                WHERE l.provider_id = ?';
        if ($onlyActive) {
            $sql .= ' AND l.is_active = 1';
        }
        $sql .= ' ORDER BY l.sort_order, l.label';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$providerId]);

        return $stmt->fetchAll();
    }

    public function providerLink(int $id): ?array
    {
        $this->ensureProviderSetupSchema();
        $stmt = $this->pdo->prepare(
            'SELECT l.*,
                    pg.name AS group_name,
                    c.name AS certification_name
             FROM provider_links l
             LEFT JOIN provider_groups pg ON pg.id = l.provider_group_id
             LEFT JOIN certifications c ON c.id = l.certification_id
             WHERE l.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function saveProviderLink(array $data, ?int $id = null): int
    {
        $this->ensureProviderSetupSchema();
        $scopeType = (string) ($data['scope_type'] ?? 'provider');
        if (!in_array($scopeType, ['provider', 'group', 'certification'], true)) {
            $scopeType = 'provider';
        }
        $linkType = (string) ($data['link_type'] ?? 'other');
        if (!isset(self::providerLinkTypes()[$linkType])) {
            $linkType = 'other';
        }
        $providerGroupId = isset($data['provider_group_id']) && $data['provider_group_id'] !== ''
            ? (int) $data['provider_group_id']
            : null;
        $certificationId = isset($data['certification_id']) && $data['certification_id'] !== ''
            ? (int) $data['certification_id']
            : null;
        if ($scopeType === 'provider') {
            $providerGroupId = null;
            $certificationId = null;
        } elseif ($scopeType === 'group') {
            $certificationId = null;
            if ($providerGroupId === null || $providerGroupId < 1) {
                throw new \RuntimeException('Selecciona un grupo para el alcance.');
            }
        } else {
            $providerGroupId = null;
            if ($certificationId === null || $certificationId < 1) {
                throw new \RuntimeException('Selecciona una certificación para el alcance.');
            }
        }

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        $code = preg_replace('/[^A-Z0-9_]+/', '_', $code) ?? '';
        $code = trim($code, '_');
        if ($code === '') {
            $label = trim((string) ($data['label'] ?? ''));
            $code = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '_', $label) ?? '');
            $code = trim($code, '_') ?: ('LINK_' . substr(md5($label . microtime()), 0, 8));
        }
        $url = trim((string) ($data['url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('URL inválida.');
        }
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new \RuntimeException('La etiqueta es obligatoria.');
        }

        $fields = [
            (int) $data['provider_id'],
            $code,
            $label,
            $url,
            $linkType,
            $scopeType,
            $providerGroupId,
            $certificationId,
            $data['notes'] ?? null,
            (int) ($data['sort_order'] ?? 0),
            (int) ($data['is_active'] ?? 1),
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE provider_links
                 SET provider_id=?, code=?, label=?, url=?, link_type=?, scope_type=?,
                     provider_group_id=?, certification_id=?, notes=?, sort_order=?, is_active=?
                 WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);

            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO provider_links
             (provider_id, code, label, url, link_type, scope_type, provider_group_id, certification_id, notes, sort_order, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteProviderLink(int $id): void
    {
        $this->ensureProviderSetupSchema();
        $this->pdo->prepare('DELETE FROM provider_links WHERE id = ?')->execute([$id]);
    }

    /**
     * Links aplicables a una certificación (empresa + grupo + certificación).
     *
     * @return list<array<string, mixed>>
     */
    public function providerLinksForCertification(int $certificationId, bool $onlyActive = true): array
    {
        $this->ensureProviderSetupSchema();
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.provider_id, c.provider_group_id
             FROM certifications c WHERE c.id = ?'
        );
        $stmt->execute([$certificationId]);
        $cert = $stmt->fetch();
        if (!$cert) {
            return [];
        }

        $providerId = (int) $cert['provider_id'];
        $groupId = isset($cert['provider_group_id']) ? (int) $cert['provider_group_id'] : 0;

        $sql = 'SELECT l.*,
                       pg.name AS group_name,
                       c.name AS certification_name
                FROM provider_links l
                LEFT JOIN provider_groups pg ON pg.id = l.provider_group_id
                LEFT JOIN certifications c ON c.id = l.certification_id
                WHERE l.provider_id = ?
                  AND (
                    (l.scope_type = \'provider\')
                    OR (l.scope_type = \'certification\' AND l.certification_id = ?)
                    OR (l.scope_type = \'group\' AND l.provider_group_id = ? AND ? > 0)
                  )';
        if ($onlyActive) {
            $sql .= ' AND l.is_active = 1';
        }
        $sql .= ' ORDER BY l.sort_order, l.label';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$providerId, $certificationId, $groupId, $groupId]);

        return $stmt->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function allProviderLinks(bool $onlyActive = true): array
    {
        $this->ensureProviderSetupSchema();
        $sql = 'SELECT l.*, p.name AS provider_name, p.code AS provider_code
                FROM provider_links l
                JOIN providers p ON p.id = l.provider_id';
        if ($onlyActive) {
            $sql .= ' WHERE l.is_active = 1';
        }
        $sql .= ' ORDER BY p.name, l.sort_order, l.label';
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll();
    }

    private function ensureProviderLinksTable(): void
    {
        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS provider_links (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  provider_id BIGINT UNSIGNED NOT NULL,
                  code VARCHAR(64) NOT NULL COMMENT 'Clave estable para tokens de correo ({{Link CODE}})',
                  label VARCHAR(190) NOT NULL,
                  url VARCHAR(1024) NOT NULL,
                  link_type ENUM('study_material','software','exam_portal','other') NOT NULL DEFAULT 'other',
                  scope_type ENUM('provider','group','certification') NOT NULL DEFAULT 'provider',
                  provider_group_id BIGINT UNSIGNED NULL,
                  certification_id BIGINT UNSIGNED NULL,
                  notes TEXT NULL,
                  sort_order INT NOT NULL DEFAULT 0,
                  is_active TINYINT(1) NOT NULL DEFAULT 1,
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                  UNIQUE KEY uq_provider_link_code (provider_id, code),
                  KEY idx_provider_links_provider (provider_id),
                  KEY idx_provider_links_group (provider_group_id),
                  KEY idx_provider_links_cert (certification_id),
                  CONSTRAINT fk_provider_links_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable) {
        }
    }

    /** @return list<array<string, mixed>> */
    public function providerGroups(int $providerId, bool $onlyActive = false): array
    {
        $this->ensureProviderSetupSchema();
        $sql = 'SELECT g.*,
                       (SELECT COUNT(*) FROM certifications c WHERE c.provider_group_id = g.id) AS certifications_count
                FROM provider_groups g
                WHERE g.provider_id = ?';
        if ($onlyActive) {
            $sql .= ' AND g.is_active = 1';
        }
        $sql .= ' ORDER BY g.sort_order, g.name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$providerId]);

        return $stmt->fetchAll();
    }

    public function providerGroup(int $id): ?array
    {
        $this->ensureProviderSetupSchema();
        $stmt = $this->pdo->prepare(
            'SELECT g.*,
                    (SELECT COUNT(*) FROM certifications c WHERE c.provider_group_id = g.id) AS certifications_count
             FROM provider_groups g WHERE g.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function saveProviderGroup(array $data, ?int $id = null): int
    {
        $this->ensureProviderSetupSchema();
        $fields = [
            (int) $data['provider_id'],
            trim((string) $data['code']),
            trim((string) $data['name']),
            $data['description'] ?? null,
            (int) ($data['sort_order'] ?? 0),
            (int) ($data['is_active'] ?? 1),
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE provider_groups SET provider_id=?, code=?, name=?, description=?, sort_order=?, is_active=? WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);

            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO provider_groups (provider_id, code, name, description, sort_order, is_active)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute($fields);

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteProviderGroup(int $id): void
    {
        $this->ensureProviderSetupSchema();
        $this->pdo->prepare('UPDATE certifications SET provider_group_id = NULL WHERE provider_group_id = ?')
            ->execute([$id]);
        $this->pdo->prepare('DELETE FROM provider_groups WHERE id = ?')->execute([$id]);
    }

    public function setCertificationGroup(int $certId, ?int $groupId): void
    {
        $this->ensureProviderSetupSchema();
        $this->pdo->prepare('UPDATE certifications SET provider_group_id = ? WHERE id = ?')
            ->execute([$groupId, $certId]);
    }

    /**
     * Asigna certificaciones al grupo. Las del proveedor que estaban en el grupo y no están en la lista quedan sin grupo.
     *
     * @param list<int> $certIds
     */
    public function assignCertificationsToGroup(int $providerId, int $groupId, array $certIds): void
    {
        $this->ensureProviderSetupSchema();
        $group = $this->providerGroup($groupId);
        if (!$group || (int) $group['provider_id'] !== $providerId) {
            return;
        }

        $certIds = array_values(array_unique(array_map('intval', $certIds)));
        $certIds = array_values(array_filter($certIds, static fn (int $id): bool => $id > 0));

        $this->pdo->prepare(
            'UPDATE certifications SET provider_group_id = NULL
             WHERE provider_id = ? AND provider_group_id = ?'
        )->execute([$providerId, $groupId]);

        if ($certIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($certIds), '?'));
        $params = [$groupId, $providerId, ...$certIds];
        $this->pdo->prepare(
            "UPDATE certifications SET provider_group_id = ?
             WHERE provider_id = ? AND id IN ($placeholders)"
        )->execute($params);
    }

    /** @return list<array{key:string,label:string,type:string,source:string}> */
    public function getProviderRegistrationFields(int $providerId): array
    {
        $this->ensureProviderSetupSchema();
        $stmt = $this->pdo->prepare('SELECT registration_fields_json FROM providers WHERE id = ?');
        $stmt->execute([$providerId]);
        $raw = $stmt->fetchColumn();
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizeProviderRegistrationFields($decoded);
    }

    /** @param list<array<string,mixed>> $fields */
    public function saveProviderRegistrationFields(int $providerId, array $fields): void
    {
        $this->ensureProviderSetupSchema();
        $normalized = $this->normalizeProviderRegistrationFields($fields);
        $json = $normalized === [] ? null : json_encode($normalized, JSON_UNESCAPED_UNICODE);
        $this->pdo->prepare('UPDATE providers SET registration_fields_json = ? WHERE id = ?')
            ->execute([$json, $providerId]);
    }

    /**
     * Campos bloqueados (builtin) + campos personalizados del proveedor.
     *
     * @return list<array{key:string,label:string,type:string,source:string}>
     */
    public function availableFieldsForCertification(int $providerId): array
    {
        $fields = [];
        foreach (CatalogRepository::registrationFieldCatalog() as $key => $meta) {
            if (!empty($meta['locked'])) {
                $fields[] = [
                    'key' => $key,
                    'label' => (string) ($meta['label'] ?? $key),
                    'type' => (string) ($meta['type'] ?? 'text'),
                    'source' => 'builtin',
                ];
            }
        }

        $seen = array_flip(array_column($fields, 'key'));
        foreach ($this->getProviderRegistrationFields($providerId) as $row) {
            $key = $row['key'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $fields[] = $row;
        }

        return $fields;
    }

    private function ensureColumn(string $table, string $column, string $definition, ?string $after = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            if ((int) $stmt->fetchColumn() > 0) {
                return;
            }
            $afterClause = $after !== null ? ' AFTER ' . $after : '';
            $this->pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}{$afterClause}");
        } catch (\Throwable) {
        }
    }

    private function ensureDocumentTypeColumn(): void
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'doc_type'"
            );
            $columnType = $stmt->fetchColumn();
            if (!is_string($columnType) || $columnType === '') {
                return;
            }

            $required = array_keys(CatalogRepository::documentTypes());
            if (str_starts_with(strtolower($columnType), 'enum')) {
                preg_match_all("/'([^']+)'/", $columnType, $matches);
                $values = $matches[1] ?? [];
                $missing = array_values(array_diff($required, $values));
                if ($missing === []) {
                    return;
                }
                foreach ($missing as $value) {
                    $values[] = $value;
                }
                $enum = "ENUM('" . implode("','", array_map('addslashes', $values)) . "') NOT NULL DEFAULT 'other'";
                try {
                    $this->pdo->exec("ALTER TABLE documents MODIFY COLUMN doc_type {$enum}");
                } catch (\Throwable) {
                    $this->pdo->exec(
                        "ALTER TABLE documents MODIFY COLUMN doc_type VARCHAR(32) NOT NULL DEFAULT 'other'"
                    );
                }
            }
        } catch (\Throwable) {
        }
    }

    private function ensureShareTokenIndex(): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
            );
            $stmt->execute(['documents', 'uq_documents_share_token']);
            if ((int) $stmt->fetchColumn() > 0) {
                return;
            }
            $this->pdo->exec('CREATE UNIQUE INDEX uq_documents_share_token ON documents (share_token)');
        } catch (\Throwable) {
        }
    }

    /**
     * Elimina grupos auto-creados DEFAULT vacíos.
     * Ya no se crean automáticamente: “toda la empresa” es alcance provider, no un grupo.
     */
    private function pruneEmptyDefaultGroups(): void
    {
        try {
            $hasLinks = false;
            try {
                $stmt = $this->pdo->query(
                    "SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provider_links'"
                );
                $hasLinks = (int) $stmt->fetchColumn() > 0;
            } catch (\Throwable) {
                $hasLinks = false;
            }

            $sql = "DELETE g FROM provider_groups g
                    WHERE g.code = 'DEFAULT'
                      AND NOT EXISTS (SELECT 1 FROM certifications c WHERE c.provider_group_id = g.id)
                      AND NOT EXISTS (SELECT 1 FROM documents d WHERE d.provider_group_id = g.id)";
            if ($hasLinks) {
                $sql .= ' AND NOT EXISTS (SELECT 1 FROM provider_links l WHERE l.provider_group_id = g.id)';
            }
            $this->pdo->exec($sql);
        } catch (\Throwable) {
        }
    }

    private function backfillDocumentShareTokens(): void
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT id FROM documents WHERE share_token IS NULL OR share_token = ''"
            );
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $docId) {
                $token = bin2hex(random_bytes(16));
                $this->pdo->prepare('UPDATE documents SET share_token = ? WHERE id = ?')
                    ->execute([$token, (int) $docId]);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param list<array<string,mixed>> $raw
     * @return list<array{key:string,label:string,type:string,source:string}>
     */
    private function normalizeProviderRegistrationFields(array $raw): array
    {
        $catalogKeys = array_flip(array_keys(CatalogRepository::registrationFieldCatalog()));
        $out = [];
        $seen = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            $source = (string) ($row['source'] ?? 'custom');
            if (!in_array($source, ['builtin', 'custom'], true)) {
                $source = 'custom';
            }

            if ($source === 'builtin') {
                if ($key === '' || !isset($catalogKeys[$key])) {
                    continue;
                }
                $catalogMeta = CatalogRepository::registrationFieldCatalog()[$key] ?? null;
                if (!is_array($catalogMeta) || !empty($catalogMeta['locked'])) {
                    continue;
                }
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = [
                    'key' => $key,
                    'label' => (string) ($catalogMeta['label'] ?? $key),
                    'type' => (string) ($catalogMeta['type'] ?? 'text'),
                    'source' => 'builtin',
                ];
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            if ($key === '' || isset($catalogKeys[$key])) {
                $key = 'custom_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($label));
                $key = trim($key, '_') ?: ('custom_' . substr(md5($label), 0, 8));
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $type = (string) ($row['type'] ?? 'text');
            if (!in_array($type, ['text', 'textarea', 'date', 'number', 'tel', 'email', 'time', 'sex'], true)) {
                $type = 'text';
            }
            $out[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'source' => 'custom',
            ];
        }

        return $out;
    }
}
