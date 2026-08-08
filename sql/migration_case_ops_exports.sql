-- Mesa de casos operativa + plantillas de correo + exportaciones a proveedores
-- Ejecutar en insti241_pdv (phpMyAdmin / CLI)

SET NAMES utf8mb4;

-- Formato de exportación ligado al protocolo (extensible: adobe, office, glcc…)
ALTER TABLE protocols
  ADD COLUMN export_format VARCHAR(64) NOT NULL DEFAULT 'none'
    COMMENT 'none|uks_csv|toefl_xlsx|linguaskill_xlsx|…'
    AFTER uses_inventory,
  ADD COLUMN provider_request_template VARCHAR(64) NULL
    COMMENT 'Código de mail_templates para solicitar examen al proveedor'
    AFTER export_format,
  ADD COLUMN student_access_template VARCHAR(64) NULL
    COMMENT 'Código de mail_templates para datos de acceso al alumno'
    AFTER provider_request_template;

ALTER TABLE certification_cases
  ADD COLUMN student_last_name_p VARCHAR(120) NULL AFTER student_name,
  ADD COLUMN student_last_name_m VARCHAR(120) NULL AFTER student_last_name_p,
  ADD COLUMN student_phone VARCHAR(64) NULL AFTER student_last_name_m,
  ADD COLUMN student_curp VARCHAR(32) NULL AFTER student_phone,
  ADD COLUMN student_birth_date DATE NULL AFTER student_curp,
  ADD COLUMN student_sex VARCHAR(16) NULL AFTER student_birth_date,
  ADD COLUMN student_nationality VARCHAR(64) NULL AFTER student_sex,
  ADD COLUMN exam_time VARCHAR(32) NULL AFTER exam_date,
  ADD COLUMN reschedule_date DATE NULL AFTER exam_time,
  ADD COLUMN reschedule_time VARCHAR(32) NULL AFTER reschedule_date,
  ADD COLUMN folio_id VARCHAR(120) NULL AFTER reschedule_time,
  ADD COLUMN access_key VARCHAR(120) NULL AFTER folio_id,
  ADD COLUMN zoom_url VARCHAR(512) NULL AFTER access_key,
  ADD COLUMN prep_doc_url VARCHAR(512) NULL AFTER zoom_url,
  ADD COLUMN access_doc_url VARCHAR(512) NULL AFTER prep_doc_url,
  ADD COLUMN moodle_user VARCHAR(120) NULL AFTER access_doc_url,
  ADD COLUMN moodle_password VARCHAR(120) NULL AFTER moodle_user,
  ADD COLUMN payment_proof_path VARCHAR(255) NULL AFTER moodle_password,
  ADD COLUMN payment_confirmed_at DATETIME NULL AFTER payment_proof_path,
  ADD COLUMN provider_export_path VARCHAR(255) NULL AFTER payment_confirmed_at,
  ADD COLUMN provider_request_sent_at DATETIME NULL AFTER provider_export_path,
  ADD COLUMN cancel_reason TEXT NULL AFTER provider_request_sent_at,
  ADD COLUMN results_url VARCHAR(512) NULL AFTER cancel_reason,
  ADD COLUMN cc_email VARCHAR(190) NULL AFTER results_url;

CREATE TABLE IF NOT EXISTS mail_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  audience ENUM('student', 'provider', 'internal', 'other') NOT NULL DEFAULT 'student',
  to_mode ENUM('student', 'provider', 'fixed', 'manual') NOT NULL DEFAULT 'student',
  to_fixed VARCHAR(255) NULL,
  cc_mode ENUM('none', 'tr', 'fixed', 'case_cc') NOT NULL DEFAULT 'none',
  cc_fixed VARCHAR(255) NULL,
  subject VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  attach_export TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Adjuntar exportación del caso si existe',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mail_templates_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(64) NOT NULL COMMENT 'payment|regulation|ine|curp|cenni|terms|export|other',
  label VARCHAR(190) NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_case_attachments_case (case_id),
  CONSTRAINT fk_case_attachments_case FOREIGN KEY (case_id) REFERENCES certification_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_case_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_mail_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id BIGINT UNSIGNED NOT NULL,
  template_code VARCHAR(64) NULL,
  to_email VARCHAR(255) NOT NULL,
  cc_email VARCHAR(255) NULL,
  subject VARCHAR(255) NOT NULL,
  attachment_path VARCHAR(255) NULL,
  status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
  error_message TEXT NULL,
  sent_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_case_mail_log_case (case_id),
  CONSTRAINT fk_case_mail_log_case FOREIGN KEY (case_id) REFERENCES certification_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_case_mail_log_user FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plantillas iniciales (FormMule → sistema)
INSERT INTO mail_templates (code, name, audience, to_mode, to_fixed, cc_mode, cc_fixed, subject, body_html, attach_export, is_active)
VALUES
('confirmacion_datos', 'Confirmación de datos', 'student', 'student', NULL, 'fixed', 'info@institutodoceo.com',
 'Confirmación de Datos',
 '<p>¡Felicidades {{Nombre}}!</p><p>Estas muy cerca de certificarte.</p><p>Tu examen quedó agendado para el día <strong>{{Fecha}}</strong> a las <strong>{{Hora}}</strong>. Una vez confirmada la fecha con el supervisor, te enviaremos un correo con tus datos de acceso.</p><p><strong>Nombre:</strong> {{Nombre Completo}}<br><strong>E-mail:</strong> {{e-mail}}<br><strong>Teléfono:</strong> {{Teléfono}}<br><strong>Certificación:</strong> {{Certificación}}</p><p>Te deseamos mucho éxito en tu examen.</p><p>Instituto DOCEO<br>info@institutodoceo.com</p>',
 0, 1),
('uks_solicitud', 'UKS — Solicitud de examen', 'provider', 'provider', 'relacionesconescuelas@uks.mx', 'none', NULL,
 'Solicitud de {{Certificación}} {{Fecha}}',
 '<p>¡Hola!</p><p>Por medio de la presente solicito:</p><p>Un examen: <strong>{{Certificación}}</strong><br>Para el día: <strong>{{Fecha}}</strong><br>A las: <strong>{{Hora}}</strong><br>Para el alumno: <strong>{{Nombre Completo}}</strong></p><p>Adjunto la plantilla CSV de registro y el comprobante de pago cuando aplique.</p><p>De antemano muchas gracias.<br>Instituto DOCEO</p>',
 1, 1),
('uks_data', 'UKS — Datos de acceso al alumno', 'student', 'student', NULL, 'case_cc', NULL,
 'Datos de acceso — {{Certificación}}',
 '<p>¡Hola {{Nombre}}!</p><p>Tu examen de <strong>{{Certificación}}</strong> está listo.</p><p><strong>Folio / ID:</strong> {{Folio / ID}}<br><strong>Clave:</strong> {{Clave}}<br><strong>Fecha:</strong> {{Fecha}} {{Hora}}</p><p>Prepárate con esta guía: <a href="{{TOKEN}}">{{TOKEN}}</a></p><p>Instituto DOCEO</p>',
 0, 1),
('toefl_solicitud', 'TOEFL — Solicitud de inscripción', 'provider', 'provider', NULL, 'none', NULL,
 'Solicitud TOEFL — {{Nombre Completo}} — {{Fecha}}',
 '<p>Buen día,</p><p>Adjunto el formato de inscripción de candidatos TOEFL para Instituto Doceo.</p><p>Alumno: <strong>{{Nombre Completo}}</strong><br>Fecha de examen: <strong>{{Fecha}}</strong> {{Hora}}<br>Correo: {{e-mail}}</p><p>Quedamos atentos a la confirmación y al enlace de Zoom.</p><p>Instituto DOCEO<br>{{Contacto Doceo}}</p>',
 1, 1),
('toefl_data', 'TOEFL — Datos de acceso al alumno', 'student', 'student', NULL, 'case_cc', NULL,
 'Datos de acceso TOEFL — {{Fecha}}',
 '<p>¡Hola {{Nombre}}!</p><p>Tu sesión TOEFL está confirmada.</p><p><strong>Fecha:</strong> {{Fecha}} {{Hora}}<br><strong>Zoom:</strong> <a href="{{Zoom}}">{{Zoom}}</a><br><strong>Folio / ID:</strong> {{Folio / ID}}<br><strong>Clave:</strong> {{Clave}}</p><p>Instituto DOCEO</p>',
 0, 1),
('itep_data', 'iTEP — Datos de acceso al alumno', 'student', 'student', NULL, 'case_cc', NULL,
 'Datos de acceso iTEP',
 '<p>¡Hola {{Nombre}}!</p><p>Sigue las instrucciones de la guía de aplicación iTEP e ingresa con:</p><p><strong>Examen ID:</strong> {{Folio / ID}}<br><strong>Contraseña:</strong> {{Clave}}</p><p>Instituto DOCEO</p>',
 0, 1),
('linguaskill_prep', 'Linguaskill / Cambridge — Prep (sin token)', 'student', 'student', NULL, 'case_cc', NULL,
 'Prepárate para tu examen — {{Certificación}}',
 '<p>¡Hola {{Nombre}}!</p><p>Mientras Cambridge asigna tu token (puede tardar varios días), ya puedes prepararte con esta guía:</p><p><a href="{{TOKEN}}">{{TOKEN}}</a></p><p>Cuando tengamos tus datos de acceso te enviaremos el mismo documento actualizado.</p><p>Instituto DOCEO</p>',
 0, 1)
ON DUPLICATE KEY UPDATE name=VALUES(name), subject=VALUES(subject), body_html=VALUES(body_html), attach_export=VALUES(attach_export);

-- Asignar formatos a protocolos existentes si existen
UPDATE protocols SET export_format = 'uks_csv', provider_request_template = 'uks_solicitud', student_access_template = 'uks_data'
WHERE code LIKE 'UKS%' OR code LIKE '%ELET%' OR name LIKE '%UKS%' OR name LIKE '%ELeT%' OR name LIKE '%ELET%';

UPDATE protocols SET export_format = 'none', provider_request_template = NULL, student_access_template = 'itep_data'
WHERE code LIKE 'ITEP%' OR name LIKE '%iTEP%' OR name LIKE '%ITEP%';

UPDATE protocols SET export_format = 'toefl_xlsx', provider_request_template = 'toefl_solicitud', student_access_template = 'toefl_data'
WHERE code LIKE 'TOEFL%' OR name LIKE '%TOEFL%';

UPDATE protocols SET export_format = 'linguaskill_xlsx', provider_request_template = NULL, student_access_template = 'linguaskill_prep'
WHERE code LIKE '%LINGUA%' OR code LIKE '%CAMBRIDGE%' OR name LIKE '%Linguaskill%' OR name LIKE '%Cambridge%';
