-- Catálogo de acciones reutilizables + asignación a protocolos + tokens de links en el caso
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS workflow_actions (
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
  auto_triggers JSON NULL COMMENT '["payment_confirmed","registration_complete","access_data_ready"]',
  requires_json JSON NULL COMMENT '["payment_confirmed","folio_id","access_key","payment_proof"]',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_workflow_actions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS protocol_actions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  protocol_id BIGINT UNSIGNED NOT NULL,
  action_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_protocol_action (protocol_id, action_id),
  KEY idx_protocol_actions_protocol (protocol_id),
  CONSTRAINT fk_protocol_actions_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE CASCADE,
  CONSTRAINT fk_protocol_actions_action FOREIGN KEY (action_id) REFERENCES workflow_actions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_action_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id BIGINT UNSIGNED NOT NULL,
  action_id BIGINT UNSIGNED NOT NULL,
  trigger_source VARCHAR(64) NOT NULL DEFAULT 'button',
  status ENUM('ok','failed','skipped') NOT NULL DEFAULT 'ok',
  message TEXT NULL,
  ran_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_case_action_runs_case (case_id),
  CONSTRAINT fk_case_action_runs_case FOREIGN KEY (case_id) REFERENCES certification_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_case_action_runs_action FOREIGN KEY (action_id) REFERENCES workflow_actions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'certification_cases' AND COLUMN_NAME = 'payment_proof_share_token'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE certification_cases ADD COLUMN payment_proof_share_token VARCHAR(64) NULL AFTER payment_proof_path',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'certification_cases' AND COLUMN_NAME = 'provider_export_share_token'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE certification_cases ADD COLUMN provider_export_share_token VARCHAR(64) NULL AFTER provider_export_path',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO workflow_actions (code, name, description, handler, mail_template_code, button_label, show_as_button, auto_triggers, requires_json, sort_order, is_active)
VALUES
('confirm_payment', 'Confirmar pago', 'Marca el pago como recibido (comprobante opcional) y genera link público del archivo.',
 'confirm_payment', NULL, 'Confirmar pago', 1, JSON_ARRAY(), JSON_ARRAY(), 10, 1),
('request_provider', 'Solicitar examen al proveedor', 'Genera exportación si aplica y envía plantilla al proveedor con links (sin adjuntos).',
 'request_provider', NULL, 'Solicitar examen', 1, JSON_ARRAY(), JSON_ARRAY('payment_confirmed'), 20, 1),
('fulfill_after_payment', 'Habilitar curso / inventario', 'Tras el pago: Moodle, inventario y correo de acceso si está configurado.',
 'fulfill_after_payment', NULL, 'Habilitar curso', 1, JSON_ARRAY('payment_confirmed'), JSON_ARRAY('payment_confirmed'), 30, 1),
('send_student_access', 'Enviar datos de acceso al alumno', 'Envía folio/clave/zoom (o Moodle) con la plantilla de acceso del protocolo o de la acción.',
 'send_student_access', NULL, 'Enviar accesos', 1, JSON_ARRAY('access_data_ready'), JSON_ARRAY('folio_or_moodle'), 40, 1),
('send_confirmacion_datos', 'Correo confirmación de datos', 'Envía plantilla confirmacion_datos al alumno.',
 'send_mail', 'confirmacion_datos', 'Enviar confirmación', 1, JSON_ARRAY('registration_complete'), JSON_ARRAY(), 5, 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  handler = VALUES(handler),
  button_label = VALUES(button_label),
  auto_triggers = VALUES(auto_triggers),
  requires_json = VALUES(requires_json);
