-- Flujo de adquisición alumno: firma de reglamento en el caso
-- Ejecutar después de migration_openpay_cenni.sql / migration_certification_docs.sql

ALTER TABLE certification_cases
  ADD COLUMN IF NOT EXISTS regulation_document_id BIGINT UNSIGNED NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS regulation_signed_at DATETIME NULL AFTER regulation_document_id,
  ADD COLUMN IF NOT EXISTS regulation_signer_name VARCHAR(190) NULL AFTER regulation_signed_at;

-- MariaDB antiguos sin IF NOT EXISTS en columnas: fallback seguro vía procedure simple no; usar checks en app.
