<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Catalog\CatalogRepository;
use App\Config\Env;
use PDO;

final class ActionRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? \App\Database\Connection::get();
    }

    public function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        // Marcar antes de seed/save para evitar recursión (seed → save → ensureSchema).
        $done = true;

        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS workflow_actions (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  code VARCHAR(64) NOT NULL,
                  name VARCHAR(190) NOT NULL,
                  description TEXT NULL,
                  handler ENUM(
                    'confirm_payment',
                    'request_provider',
                    'send_mail',
                    'send_student_access',
                    'fulfill_after_payment'
                  ) NOT NULL DEFAULT 'send_mail',
                  mail_template_code VARCHAR(64) NULL,
                  button_label VARCHAR(120) NULL,
                  show_as_button TINYINT(1) NOT NULL DEFAULT 1,
                  auto_triggers JSON NULL,
                  requires_json JSON NULL,
                  sort_order INT NOT NULL DEFAULT 0,
                  is_active TINYINT(1) NOT NULL DEFAULT 1,
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                  UNIQUE KEY uq_workflow_actions_code (code)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable) {
        }

        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS protocol_actions (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  protocol_id BIGINT UNSIGNED NOT NULL,
                  action_id BIGINT UNSIGNED NOT NULL,
                  sort_order INT NOT NULL DEFAULT 0,
                  is_active TINYINT(1) NOT NULL DEFAULT 1,
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  UNIQUE KEY uq_protocol_action (protocol_id, action_id),
                  KEY idx_protocol_actions_protocol (protocol_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable) {
        }

        try {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS case_action_runs (
                  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  case_id BIGINT UNSIGNED NOT NULL,
                  action_id BIGINT UNSIGNED NOT NULL,
                  trigger_source VARCHAR(64) NOT NULL DEFAULT 'button',
                  status ENUM('ok','failed','skipped') NOT NULL DEFAULT 'ok',
                  message TEXT NULL,
                  ran_by BIGINT UNSIGNED NULL,
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  KEY idx_case_action_runs_case (case_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable) {
        }

        $this->ensureCaseShareColumns();
        $this->seedDefaults();
    }

    /** @return array<string, string> */
    public static function handlers(): array
    {
        return [
            'confirm_payment' => 'Confirmar pago (+ link de comprobante)',
            'request_provider' => 'Solicitar examen al proveedor (mail + links)',
            'send_mail' => 'Enviar plantilla de correo',
            'send_student_access' => 'Enviar datos de acceso al alumno',
            'fulfill_after_payment' => 'Habilitar curso / inventario tras pago',
        ];
    }

    /** @return array<string, string> */
    public static function triggerOptions(): array
    {
        return [
            'payment_confirmed' => 'Al confirmar pago (manual u OpenPay)',
            'registration_complete' => 'Al completar registro / abrir caso',
            'access_data_ready' => 'Al tener folio/clave (o Moodle) capturados',
        ];
    }

    /** @return array<string, string> */
    public static function requireOptions(): array
    {
        return [
            'payment_confirmed' => 'Pago confirmado',
            'payment_proof' => 'Comprobante subido',
            'folio_id' => 'Folio / ID',
            'access_key' => 'Clave',
            'folio_or_moodle' => 'Folio+clave o usuario Moodle',
        ];
    }

    /** @return list<array<string, mixed>> */
    public function all(bool $onlyActive = false): array
    {
        $this->ensureSchema();
        $sql = 'SELECT * FROM workflow_actions';
        if ($onlyActive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order, name';

        return $this->pdo->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $stmt = $this->pdo->prepare('SELECT * FROM workflow_actions WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $this->ensureSchema();
        $stmt = $this->pdo->prepare('SELECT * FROM workflow_actions WHERE code = ?');
        $stmt->execute([trim($code)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function save(array $data, ?int $id = null): int
    {
        $this->ensureSchema();
        $handlers = array_keys(self::handlers());
        $handler = (string) ($data['handler'] ?? 'send_mail');
        if (!in_array($handler, $handlers, true)) {
            $handler = 'send_mail';
        }
        $code = strtolower(trim((string) ($data['code'] ?? '')));
        $code = preg_replace('/[^a-z0-9_]+/', '_', $code) ?? '';
        $code = trim($code, '_');
        if ($code === '') {
            throw new \RuntimeException('Código de acción obligatorio.');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new \RuntimeException('Nombre obligatorio.');
        }

        $triggers = $data['auto_triggers'] ?? [];
        if (!is_array($triggers)) {
            $triggers = [];
        }
        $requires = $data['requires_json'] ?? [];
        if (!is_array($requires)) {
            $requires = [];
        }

        $fields = [
            $code,
            $name,
            $data['description'] ?? null,
            $handler,
            trim((string) ($data['mail_template_code'] ?? '')) ?: null,
            trim((string) ($data['button_label'] ?? '')) ?: null,
            !empty($data['show_as_button']) ? 1 : 0,
            json_encode(array_values($triggers), JSON_UNESCAPED_UNICODE),
            json_encode(array_values($requires), JSON_UNESCAPED_UNICODE),
            (int) ($data['sort_order'] ?? 0),
            !empty($data['is_active']) ? 1 : 0,
        ];

        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE workflow_actions
                 SET code=?, name=?, description=?, handler=?, mail_template_code=?, button_label=?,
                     show_as_button=?, auto_triggers=?, requires_json=?, sort_order=?, is_active=?
                 WHERE id=?'
            );
            $stmt->execute([...$fields, $id]);

            return $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO workflow_actions
             (code, name, description, handler, mail_template_code, button_label, show_as_button, auto_triggers, requires_json, sort_order, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute($fields);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->ensureSchema();
        $this->pdo->prepare('DELETE FROM workflow_actions WHERE id = ?')->execute([$id]);
    }

    /** @return list<array<string, mixed>> */
    public function protocolActions(int $protocolId, bool $onlyActive = true): array
    {
        $this->ensureSchema();
        $sql = 'SELECT pa.id AS protocol_action_id, pa.protocol_id, pa.sort_order AS protocol_sort,
                       pa.is_active AS protocol_action_active, a.*
                FROM protocol_actions pa
                JOIN workflow_actions a ON a.id = pa.action_id
                WHERE pa.protocol_id = ?';
        if ($onlyActive) {
            $sql .= ' AND pa.is_active = 1 AND a.is_active = 1';
        }
        $sql .= ' ORDER BY pa.sort_order, a.sort_order, a.name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$protocolId]);

        return $stmt->fetchAll();
    }

    /** @param list<int> $actionIds ordered */
    public function setProtocolActions(int $protocolId, array $actionIds): void
    {
        $this->ensureSchema();
        $this->pdo->prepare('DELETE FROM protocol_actions WHERE protocol_id = ?')->execute([$protocolId]);
        $actionIds = array_values(array_unique(array_map('intval', $actionIds)));
        $stmt = $this->pdo->prepare(
            'INSERT INTO protocol_actions (protocol_id, action_id, sort_order, is_active) VALUES (?,?,?,1)'
        );
        $order = 0;
        foreach ($actionIds as $actionId) {
            if ($actionId < 1) {
                continue;
            }
            $stmt->execute([$protocolId, $actionId, $order++]);
        }
    }

    public function logRun(int $caseId, int $actionId, string $source, string $status, ?string $message, ?int $ranBy): void
    {
        $this->ensureSchema();
        $this->pdo->prepare(
            'INSERT INTO case_action_runs (case_id, action_id, trigger_source, status, message, ran_by)
             VALUES (?,?,?,?,?,?)'
        )->execute([$caseId, $actionId, $source, $status, $message, $ranBy]);
    }

    public function hasSuccessfulAutoRun(int $caseId, int $actionId, string $trigger): bool
    {
        $this->ensureSchema();
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM case_action_runs
             WHERE case_id = ? AND action_id = ? AND trigger_source = ? AND status = 'ok'"
        );
        $stmt->execute([$caseId, $actionId, $trigger]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @return list<string> */
    public function decodeJsonList(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_map('strval', $raw));
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    private function ensureCaseShareColumns(): void
    {
        foreach (
            [
                'payment_proof_share_token' => 'payment_proof_path',
                'provider_export_share_token' => 'provider_export_path',
            ] as $column => $after
        ) {
            try {
                $stmt = $this->pdo->prepare(
                    'SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
                );
                $stmt->execute(['certification_cases', $column]);
                if ((int) $stmt->fetchColumn() > 0) {
                    continue;
                }
                $this->pdo->exec(
                    "ALTER TABLE certification_cases ADD COLUMN {$column} VARCHAR(64) NULL AFTER {$after}"
                );
            } catch (\Throwable) {
            }
        }
    }

    private function seedDefaults(): void
    {
        try {
            $count = (int) $this->pdo->query('SELECT COUNT(*) FROM workflow_actions')->fetchColumn();
            if ($count > 0) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO workflow_actions
             (code, name, description, handler, mail_template_code, button_label, show_as_button, auto_triggers, requires_json, sort_order, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,?,1)'
        );

        $defaults = [
            ['confirm_payment', 'Confirmar pago', 'Marca pago recibido y genera link del comprobante.', 'confirm_payment', null, 'Confirmar pago', 1, '[]', '[]', 10],
            ['request_provider', 'Solicitar examen al proveedor', 'Exportación + correo al proveedor con links.', 'request_provider', null, 'Solicitar examen', 1, '[]', '["payment_confirmed"]', 20],
            ['fulfill_after_payment', 'Habilitar curso / inventario', 'Moodle/inventario tras pago.', 'fulfill_after_payment', null, 'Habilitar curso', 1, '["payment_confirmed"]', '["payment_confirmed"]', 30],
            ['send_student_access', 'Enviar datos de acceso al alumno', 'Correo con folio/clave/zoom o Moodle.', 'send_student_access', null, 'Enviar accesos', 1, '["access_data_ready"]', '["folio_or_moodle"]', 40],
            ['send_confirmacion_datos', 'Correo confirmación de datos', 'Plantilla confirmacion_datos.', 'send_mail', 'confirmacion_datos', 'Enviar confirmación', 1, '["registration_complete"]', '[]', 5],
        ];

        foreach ($defaults as $row) {
            try {
                $stmt->execute($row);
            } catch (\Throwable) {
            }
        }
    }
}
