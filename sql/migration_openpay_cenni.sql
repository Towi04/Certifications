-- OpenPay SPEI (CLABE única) + seguimiento CENNI (ELET externo vs Doceo)
SET NAMES utf8mb4;

-- Cómo se gestiona el CENNI por producto
ALTER TABLE certifications
  ADD COLUMN cenni_process ENUM('none', 'uks_external', 'doceo_managed') NOT NULL DEFAULT 'none'
    COMMENT 'none=sin CENNI; uks_external=alumno sube en UKS (ELET); doceo_managed=docs en PDV'
    AFTER cenni_fee;

UPDATE certifications
SET cenni_process = CASE
  WHEN cenni_eligible = 0 OR cenni_doc_type = 'none' THEN 'none'
  WHEN UPPER(CONCAT(IFNULL(code,''),' ',IFNULL(name,''))) LIKE '%ELET%' THEN 'uks_external'
  ELSE 'doceo_managed'
END;

ALTER TABLE certification_cases
  ADD COLUMN openpay_charge_id VARCHAR(64) NULL AFTER payment_confirmed_at,
  ADD COLUMN openpay_order_id VARCHAR(100) NULL AFTER openpay_charge_id,
  ADD COLUMN openpay_clabe VARCHAR(32) NULL AFTER openpay_order_id,
  ADD COLUMN openpay_bank VARCHAR(120) NULL AFTER openpay_clabe,
  ADD COLUMN openpay_agreement VARCHAR(64) NULL AFTER openpay_bank,
  ADD COLUMN openpay_reference VARCHAR(120) NULL AFTER openpay_agreement,
  ADD COLUMN openpay_amount DECIMAL(12,2) NULL AFTER openpay_reference,
  ADD COLUMN openpay_status VARCHAR(32) NULL AFTER openpay_amount,
  ADD COLUMN openpay_due_at DATETIME NULL AFTER openpay_status,
  ADD COLUMN openpay_paid_at DATETIME NULL AFTER openpay_due_at,
  ADD COLUMN openpay_pdf_url VARCHAR(512) NULL AFTER openpay_paid_at,
  ADD COLUMN cenni_status VARCHAR(40) NOT NULL DEFAULT 'none'
    COMMENT 'none|awaiting_uks_upload|awaiting_pdv_upload|docs_in_review|docs_rejected|sep_pending|issued'
    AFTER openpay_pdf_url,
  ADD COLUMN cenni_folio VARCHAR(120) NULL AFTER cenni_status,
  ADD COLUMN cenni_notes TEXT NULL AFTER cenni_folio,
  ADD COLUMN cenni_status_updated_at DATETIME NULL AFTER cenni_notes;

CREATE TABLE IF NOT EXISTS openpay_webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(64) NULL,
  openpay_charge_id VARCHAR(64) NULL,
  order_id VARCHAR(100) NULL,
  case_id BIGINT UNSIGNED NULL,
  payload_json MEDIUMTEXT NOT NULL,
  processed TINYINT(1) NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_openpay_webhook_charge (openpay_charge_id),
  KEY idx_openpay_webhook_order (order_id),
  KEY idx_openpay_webhook_case (case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mail_templates (code, name, audience, to_mode, to_fixed, cc_mode, cc_fixed, subject, body_html, attach_export, is_active)
VALUES
('pago_clabe', 'Pago — CLABE SPEI OpenPay', 'student', 'student', NULL, 'case_cc', NULL,
 'Datos para tu pago — {{Certificación}}',
 '<p>¡Hola {{Nombre}}!</p><p>Para pagar tu certificación <strong>{{Certificación}}</strong> realiza una transferencia SPEI con estos datos:</p><p><strong>Beneficiario:</strong> {{OpenPay Beneficiario}}<br><strong>Banco:</strong> {{OpenPay Banco}}<br><strong>CLABE:</strong> {{OpenPay CLABE}}<br><strong>Convenio / referencia:</strong> {{OpenPay Referencia}}<br><strong>Monto:</strong> ${{OpenPay Monto}} MXN</p><p><a href="{{OpenPay SPEI URL}}">Ver / imprimir ficha SPEI Doceo</a></p><p>El pago se confirma automáticamente. Cuando OpenPay lo registre te enviaremos un correo.</p><p>Instituto DOCEO</p>',
 0, 1),
('pago_confirmado', 'Pago confirmado (OpenPay)', 'student', 'student', NULL, 'case_cc', NULL,
 'Pago confirmado — {{Certificación}}',
 '<p>¡Hola {{Nombre}}!</p><p>Ya recibimos tu pago de <strong>${{OpenPay Monto}} MXN</strong> para <strong>{{Certificación}}</strong>.</p><p>Continuaremos con la solicitud del examen ante la certificadora. Te avisaremos cuando tengas tus datos de acceso.</p><p>Instituto DOCEO</p>',
 0, 1),
('cenni_seguimiento', 'CENNI — actualización de estatus', 'student', 'student', NULL, 'case_cc', NULL,
 'Actualización de tu trámite CENNI — {{Certificación}}',
 '<p>¡Hola {{Nombre}}!</p><p>Te informamos el avance de tu trámite CENNI para <strong>{{Certificación}}</strong>:</p><p><strong>Estatus:</strong> {{CENNI Estatus}}<br>{{CENNI Folio Line}}{{CENNI Notas Line}}</p><p>Aunque UKS o la SEP también te puedan notificar, desde Instituto Doceo damos seguimiento para que sepas que todo va en orden.</p><p>Instituto DOCEO</p>',
 0, 1),
('cenni_emitido', 'CENNI emitido — agradecimiento', 'student', 'student', NULL, 'case_cc', NULL,
 'Tu CENNI ya fue emitido — ¡gracias por confiar en Doceo!',
 '<p>¡Hola {{Nombre}}!</p><p>¡Excelentes noticias! Tu documento CENNI para <strong>{{Certificación}}</strong> ya fue emitido{{CENNI Folio Suffix}}.</p><p>Gracias por tu preferencia. Te invitamos a seguir explorando nuestra plataforma para adquirir otra certificación cuando lo necesites:</p><p><a href="{{App URL}}">{{App URL}}</a></p><p>Instituto DOCEO<br>info@institutodoceo.com</p>',
 0, 1)
ON DUPLICATE KEY UPDATE subject=VALUES(subject), body_html=VALUES(body_html), name=VALUES(name);

-- Casos ELET existentes: estatus inicial de seguimiento externo
UPDATE certification_cases c
JOIN certifications cert ON cert.id = c.certification_id
SET c.cenni_status = 'awaiting_uks_upload'
WHERE cert.cenni_process = 'uks_external'
  AND (c.cenni_status IS NULL OR c.cenni_status = 'none' OR c.cenni_status = '');

UPDATE certification_cases c
JOIN certifications cert ON cert.id = c.certification_id
SET c.cenni_status = 'awaiting_pdv_upload'
WHERE cert.cenni_process = 'doceo_managed'
  AND (c.cenni_status IS NULL OR c.cenni_status = 'none' OR c.cenni_status = '');
