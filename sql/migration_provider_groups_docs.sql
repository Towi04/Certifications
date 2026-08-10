-- Grupos de certificaciones por proveedor + documentos con alcance + campos de adquisición del proveedor
SET NAMES utf8mb4;

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS provider_groups (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'certifications' AND COLUMN_NAME = 'provider_group_id'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE certifications ADD COLUMN provider_group_id BIGINT UNSIGNED NULL AFTER provider_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'providers' AND COLUMN_NAME = 'registration_fields_json'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE providers ADD COLUMN registration_fields_json JSON NULL COMMENT ''Campos disponibles para adquisición (elegibles en cada certificación)'' AFTER brand_website_url',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'scope_type'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE documents ADD COLUMN scope_type ENUM(''provider'',''group'',''certification'') NOT NULL DEFAULT ''provider'' COMMENT ''Aplica a toda la empresa, un grupo o una certificación'' AFTER provider_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'provider_group_id'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE documents ADD COLUMN provider_group_id BIGINT UNSIGNED NULL AFTER scope_type',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'certification_id'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE documents ADD COLUMN certification_id BIGINT UNSIGNED NULL AFTER provider_group_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'share_token'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE documents ADD COLUMN share_token VARCHAR(64) NULL AFTER file_path',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'documents' AND INDEX_NAME = 'uq_documents_share_token'
);
SET @sql := IF(@idx = 0,
  'CREATE UNIQUE INDEX uq_documents_share_token ON documents (share_token)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ampliar tipos de documento (si falla ENUM, runtime ensure puede pasar a VARCHAR)
ALTER TABLE documents MODIFY COLUMN doc_type ENUM(
  'regulation', 'form', 'checklist', 'instructions',
  'export_template', 'student', 'provider_ops', 'other'
) NOT NULL DEFAULT 'other';

-- Backfill histórico (ya no se usa): grupos DEFAULT no se crean automáticamente.
-- “Toda la empresa” = alcance provider en documentos/links; los grupos son subconjuntos opcionales.
-- INSERT INTO provider_groups ... DEFAULT omitido a propósito.

UPDATE documents d
SET d.scope_type = COALESCE(d.scope_type, 'provider'),
    d.share_token = COALESCE(NULLIF(d.share_token, ''), LOWER(HEX(RANDOM_BYTES(16))))
WHERE d.provider_id IS NOT NULL AND (d.share_token IS NULL OR d.share_token = '');
