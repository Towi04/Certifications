-- Asegura la tabla de vínculo certificación ↔ documento (reglamento).
-- La vista /admin/certifications/pricing también la crea al vuelo si falta.

CREATE TABLE IF NOT EXISTS certification_docs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certification_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  stage ENUM('purchase', 'exam', 'cenni', 'conocer', 'other') NOT NULL DEFAULT 'purchase',
  UNIQUE KEY uq_cert_doc (certification_id, document_id, stage),
  CONSTRAINT fk_cd_cert FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_cd_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
