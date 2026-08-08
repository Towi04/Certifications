-- Documentos administrativos: reglamentos y archivos para alumnos
-- Relacionados a un proveedor, con versión

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'provider_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE documents ADD COLUMN provider_id BIGINT UNSIGNED NULL AFTER id',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'version'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE documents ADD COLUMN version VARCHAR(64) NOT NULL DEFAULT ''1.0'' AFTER title',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'is_active'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE documents ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER body_html',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'updated_at'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE documents ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ampliar tipos de documento si el ENUM es el viejo
ALTER TABLE documents
  MODIFY COLUMN doc_type ENUM('regulation', 'form', 'checklist', 'instructions', 'other')
  NOT NULL DEFAULT 'other';

SET @fk := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND CONSTRAINT_NAME = 'fk_documents_provider'
);
SET @sql := IF(@fk = 0,
  'ALTER TABLE documents ADD CONSTRAINT fk_documents_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND INDEX_NAME = 'idx_documents_provider'
);
SET @sql := IF(@idx = 0,
  'ALTER TABLE documents ADD KEY idx_documents_provider (provider_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
