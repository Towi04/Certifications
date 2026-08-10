-- Inventario de códigos de examen (iTEP y similares) + resultados post-examen
CREATE TABLE IF NOT EXISTS inventory_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NULL,
  certification_id BIGINT UNSIGNED NULL,
  exam_id VARCHAR(120) NOT NULL COMMENT 'Examen ID / Folio que ve el alumno',
  access_code VARCHAR(190) NOT NULL COMMENT 'Contraseña / clave del examen',
  batch_label VARCHAR(190) NULL,
  status ENUM('available','assigned','void') NOT NULL DEFAULT 'available',
  assigned_case_id BIGINT UNSIGNED NULL,
  assigned_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_inventory_status (status, certification_id, provider_id),
  KEY idx_inventory_case (assigned_case_id),
  UNIQUE KEY uq_inventory_exam_code (exam_id, access_code),
  CONSTRAINT fk_inventory_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL,
  CONSTRAINT fk_inventory_cert FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE SET NULL,
  CONSTRAINT fk_inventory_case FOREIGN KEY (assigned_case_id) REFERENCES certification_cases(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE certification_cases
  ADD COLUMN score_url VARCHAR(512) NULL AFTER results_url,
  ADD COLUMN certificate_url VARCHAR(512) NULL AFTER score_url,
  ADD COLUMN exam_outcome VARCHAR(32) NULL DEFAULT 'pending'
    COMMENT 'pending|delivered|invalidated' AFTER certificate_url,
  ADD COLUMN invalidation_reason TEXT NULL AFTER exam_outcome,
  ADD COLUMN inventory_code_id BIGINT UNSIGNED NULL AFTER access_key;

-- Curso prep iTEP (ajustar moodle_course_id en admin)
INSERT INTO courses (code, name, platform_type, moodle_course_id, access_notes, is_active) VALUES
('ITEP_PREP', 'iTEP Preparation', 'moodle', NULL, 'Curso prep iTEP en campus.institutodoceo.com — asignar moodle_course_id', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Asegurar plantilla itep_data
INSERT INTO mail_templates (code, name, audience, to_mode, to_fixed, cc_mode, cc_fixed, subject, body_html, attach_export, is_active)
VALUES (
  'itep_data',
  'iTEP — Datos de acceso al alumno',
  'student',
  'student',
  NULL,
  'case_cc',
  NULL,
  'Datos de acceso iTEP — {{Nombre}}',
  '<p>¡Hola {{Nombre}}!</p><p>Tu examen <strong>{{Certificación}}</strong> ya tiene códigos de acceso.</p><p><strong>Examen ID:</strong> {{Folio / ID}}<br><strong>Contraseña:</strong> {{Clave}}</p><p>Fecha solicitada: {{Fecha}} {{Hora}}</p><p>Sigue la guía de aplicación iTEP e ingresa con estos datos.</p><p>Instituto DOCEO</p>',
  0,
  1
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  subject = VALUES(subject),
  body_html = VALUES(body_html),
  is_active = 1;

INSERT INTO mail_templates (code, name, audience, to_mode, to_fixed, cc_mode, cc_fixed, subject, body_html, attach_export, is_active)
VALUES (
  'itep_resultados',
  'iTEP — Resultados / certificado',
  'student',
  'student',
  NULL,
  'case_cc',
  NULL,
  'Resultados iTEP — {{Nombre}}',
  '<p>¡Hola {{Nombre}}!</p><p>Ya están disponibles tus resultados de <strong>{{Certificación}}</strong>.</p><p>{{Resultados Line}}{{Score Line}}{{Certificate Line}}</p><p>Instituto DOCEO</p>',
  0,
  1
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  subject = VALUES(subject),
  body_html = VALUES(body_html),
  is_active = 1;

INSERT INTO mail_templates (code, name, audience, to_mode, to_fixed, cc_mode, cc_fixed, subject, body_html, attach_export, is_active)
VALUES (
  'itep_invalidado',
  'iTEP — Examen invalidado',
  'student',
  'student',
  NULL,
  'case_cc',
  NULL,
  'Aviso sobre tu examen iTEP — {{Nombre}}',
  '<p>¡Hola {{Nombre}}!</p><p>Te informamos que tu examen <strong>{{Certificación}}</strong> fue marcado como invalidado.</p><p><strong>Motivo:</strong> {{Canceled}}</p><p>Si tienes dudas, responde a este correo o contacta a {{Contacto Doceo}}.</p><p>Instituto DOCEO</p>',
  0,
  1
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  subject = VALUES(subject),
  body_html = VALUES(body_html),
  is_active = 1;

UPDATE protocols
SET student_access_template = 'itep_data',
    export_format = 'none',
    uses_inventory = 1
WHERE code LIKE 'ITEP%' OR name LIKE '%iTEP%' OR name LIKE '%ITEP%';
