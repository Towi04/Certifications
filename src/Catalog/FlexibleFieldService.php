<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Config\Env;
use App\Support\Str;
use PDO;

/**
 * Campos flexibles reutilizables: texto, archivo, URL, etc.
 * Se definen en el proveedor y se activan por certificación / grupo / todas.
 */
final class FlexibleFieldService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \App\Database\Connection::get();
    }

    /** @return array<string, string> */
    public static function studentFieldTypes(): array
    {
        return [
            'text' => 'Texto',
            'textarea' => 'Texto largo',
            'date' => 'Fecha',
            'number' => 'Número',
            'tel' => 'Teléfono',
            'email' => 'Correo',
            'url' => 'Enlace (URL)',
            'file' => 'Archivo (PDF/imagen)',
            'time' => 'Hora',
            'sex' => 'Sexo',
        ];
    }

    /** @return array<string, string> */
    public static function accessFieldTypes(): array
    {
        return [
            'text' => 'Texto',
            'password' => 'Clave / password',
            'url' => 'Enlace (URL)',
            'file' => 'Archivo',
        ];
    }

    /** Columnas fijas del caso que un slot de acceso puede mapear. */
    public static function accessBuiltinMaps(): array
    {
        return [
            'folio_id' => 'Folio / ID',
            'access_key' => 'Clave',
            'institution_id' => 'Institution ID',
            'zoom_url' => 'Zoom',
            'prep_doc_url' => 'Doc de preparación (URL)',
            'access_doc_url' => 'Doc de acceso (URL)',
            'moodle_user' => 'Usuario Moodle',
            'moodle_password' => 'Contraseña Moodle',
            'results_url' => 'URL resultados',
            'score_url' => 'URL score',
            'certificate_url' => 'URL certificado',
        ];
    }

    /** @return list<string> */
    public static function allowedStudentTypes(): array
    {
        return array_keys(self::studentFieldTypes());
    }

    /** @return list<string> */
    public static function allowedAccessTypes(): array
    {
        return array_keys(self::accessFieldTypes());
    }

    /**
     * @param list<array<string,mixed>> $raw
     * @return list<array{key:string,label:string,type:string,required:bool,maps_to:?string}>
     */
    public static function normalizeAccessFields(array $raw): array
    {
        $out = [];
        $seen = [];
        $maps = self::accessBuiltinMaps();
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!empty($row['delete'])) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                $key = 'access_' . preg_replace('/[^a-z0-9]+/', '_', strtolower(Str::slug($label)));
                $key = trim($key, '_') ?: ('access_' . substr(md5($label), 0, 8));
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $type = (string) ($row['type'] ?? 'text');
            if (!in_array($type, self::allowedAccessTypes(), true)) {
                $type = 'text';
            }
            $mapsTo = trim((string) ($row['maps_to'] ?? ''));
            if ($mapsTo !== '' && !isset($maps[$mapsTo])) {
                $mapsTo = '';
            }
            // Si el key coincide con una columna built-in, mapear automáticamente
            if ($mapsTo === '' && isset($maps[$key])) {
                $mapsTo = $key;
            }
            $out[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => !empty($row['required']),
                'maps_to' => $mapsTo !== '' ? $mapsTo : null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,maps_to:?string}>
     */
    public function getProviderAccessFields(int $providerId): array
    {
        $stmt = $this->pdo->prepare('SELECT access_fields_json FROM providers WHERE id = ?');
        $stmt->execute([$providerId]);
        $raw = $stmt->fetchColumn();
        if (!is_string($raw) || $raw === '') {
            return self::defaultAccessFields();
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::defaultAccessFields();
        }

        $normalized = self::normalizeAccessFields($decoded);

        return $normalized !== [] ? $normalized : self::defaultAccessFields();
    }

    public function saveProviderAccessFields(int $providerId, array $fields): void
    {
        $this->ensureAccessFieldsColumn();
        $normalized = self::normalizeAccessFields($fields);
        $json = $normalized === [] ? null : json_encode($normalized, JSON_UNESCAPED_UNICODE);
        $this->pdo->prepare('UPDATE providers SET access_fields_json = ? WHERE id = ?')
            ->execute([$json, $providerId]);
    }

    /**
     * @return list<array{key:string,label:string,type:string,required:bool,maps_to:?string}>
     */
    public static function decodeCertAccessFields(mixed $raw, ?array $providerFallback = null): array
    {
        $decoded = [];
        if (is_string($raw) && $raw !== '') {
            $tmp = json_decode($raw, true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        } elseif (is_array($raw)) {
            $decoded = $raw;
        }
        if ($decoded === [] && $providerFallback !== null) {
            return $providerFallback;
        }

        return self::normalizeAccessFields($decoded);
    }

    /** @return list<array{key:string,label:string,type:string,required:bool,maps_to:?string}> */
    public static function defaultAccessFields(): array
    {
        return [
            ['key' => 'folio_id', 'label' => 'Folio / ID', 'type' => 'text', 'required' => false, 'maps_to' => 'folio_id'],
            ['key' => 'access_key', 'label' => 'Clave', 'type' => 'password', 'required' => false, 'maps_to' => 'access_key'],
            ['key' => 'zoom_url', 'label' => 'Zoom', 'type' => 'url', 'required' => false, 'maps_to' => 'zoom_url'],
            ['key' => 'prep_doc_url', 'label' => 'Doc de preparación', 'type' => 'url', 'required' => false, 'maps_to' => 'prep_doc_url'],
        ];
    }

    /**
     * Copia modes + custom (+ access opcional) de una certificación a otras del mismo proveedor.
     *
     * @param list<int> $targetCertIds vacío = todas del proveedor (o del grupo si $groupId)
     * @return int número de certificaciones actualizadas
     */
    public function applyRegistrationConfigToCerts(
        int $providerId,
        array $config,
        ?int $groupId = null,
        array $targetCertIds = [],
        bool $includeAccess = true
    ): int {
        $this->ensureAccessFieldsColumn();
        $modes = is_array($config['modes'] ?? null) ? $config['modes'] : [];
        $custom = is_array($config['custom'] ?? null) ? $config['custom'] : [];
        $access = is_array($config['access'] ?? null) ? $config['access'] : [];
        $schedule = is_array($config['schedule'] ?? null) ? $config['schedule'] : null;

        $payload = [
            'modes' => $modes,
            'custom' => $custom,
        ];
        if ($schedule !== null) {
            $payload['schedule'] = $schedule;
        }
        $regJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $accessJson = $includeAccess && $access !== []
            ? json_encode(self::normalizeAccessFields($access), JSON_UNESCAPED_UNICODE)
            : null;

        $sql = 'SELECT id FROM certifications WHERE provider_id = ? AND is_active = 1';
        $params = [$providerId];
        if ($groupId !== null && $groupId > 0) {
            $sql .= ' AND provider_group_id = ?';
            $params[] = $groupId;
        }
        if ($targetCertIds !== []) {
            $ids = array_values(array_filter(array_map('intval', $targetCertIds), static fn (int $i): bool => $i > 0));
            if ($ids === []) {
                return 0;
            }
            $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($params, ...$ids);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($ids === []) {
            return 0;
        }

        $upd = $this->pdo->prepare(
            'UPDATE certifications SET registration_fields_json = ?'
            . ($includeAccess ? ', access_fields_json = ?' : '')
            . ' WHERE id = ?'
        );
        foreach ($ids as $id) {
            if ($includeAccess) {
                $upd->execute([$regJson, $accessJson, $id]);
            } else {
                $upd->execute([$regJson, $id]);
            }
        }

        return count($ids);
    }

    /**
     * Aplica el catálogo del proveedor (todos los campos en required/optional por defecto)
     * a certificaciones.
     */
    public function applyProviderCatalogDefaults(
        int $providerId,
        string $defaultMode = 'required',
        ?int $groupId = null,
        array $targetCertIds = []
    ): int {
        if (!in_array($defaultMode, ['optional', 'required'], true)) {
            $defaultMode = 'required';
        }
        $setup = new ProviderSetupRepository($this->pdo);
        $available = $setup->availableFieldsForCertification($providerId);
        $modes = [];
        $custom = [];
        foreach ($available as $af) {
            $key = (string) ($af['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $source = (string) ($af['source'] ?? 'builtin');
            if ($source === 'custom') {
                $custom[] = [
                    'key' => $key,
                    'label' => (string) ($af['label'] ?? $key),
                    'type' => (string) ($af['type'] ?? 'text'),
                    'mode' => $defaultMode,
                ];
            } else {
                $meta = CatalogRepository::registrationFieldCatalog()[$key] ?? [];
                $modes[$key] = !empty($meta['locked']) ? 'required' : $defaultMode;
            }
        }
        foreach (CatalogRepository::registrationFieldCatalog() as $key => $meta) {
            if (!empty($meta['locked'])) {
                $modes[$key] = 'required';
            }
        }
        $access = $this->getProviderAccessFields($providerId);

        return $this->applyRegistrationConfigToCerts(
            $providerId,
            ['modes' => $modes, 'custom' => $custom, 'access' => $access],
            $groupId,
            $targetCertIds,
            true
        );
    }

    public function ensureAccessFieldsColumn(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach (['providers', 'certifications'] as $table) {
            try {
                $stmt = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
                );
                $stmt->execute([$table, 'access_fields_json']);
                if ((int) $stmt->fetchColumn() > 0) {
                    continue;
                }
                $this->pdo->exec(
                    "ALTER TABLE {$table} ADD COLUMN access_fields_json JSON NULL
                     COMMENT 'Slots de acceso admin: folio, links, archivos'"
                );
            } catch (\Throwable) {
            }
        }
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute(['certification_cases', 'access_extra_json']);
            if ((int) $stmt->fetchColumn() === 0) {
                $this->pdo->exec(
                    "ALTER TABLE certification_cases ADD COLUMN access_extra_json JSON NULL
                     COMMENT 'Valores de slots de acceso no mapeados a columnas'"
                );
            }
        } catch (\Throwable) {
        }
    }

    public static function documentPublicUrl(?string $shareToken, ?string $appUrl = null): string
    {
        $token = trim((string) $shareToken);
        if ($token === '') {
            return '';
        }
        $base = rtrim($appUrl ?? (string) (Env::get('APP_URL', 'https://pdv.institutodoceo.com') ?? ''), '/');

        return $base . '/d/' . rawurlencode($token);
    }

    /**
     * Códigos de acción del paquete operativo estándar.
     *
     * @return list<string>
     */
    public static function standardActionCodes(): array
    {
        return [
            'confirm_payment',
            'request_provider',
            'fulfill_after_payment',
            'send_student_access',
        ];
    }
}
