-- Tipos CENNI adicionales + valor agregado Doceo por certificación

ALTER TABLE certifications
  MODIFY cenni_doc_type ENUM(
    'none',
    'constancia',
    'certificado',
    'constancia_certificado',
    'certificado_diploma',
    'constancia_certificado_diploma'
  ) NOT NULL DEFAULT 'none';

SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'certifications' AND COLUMN_NAME = 'value_points_json'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE certifications ADD COLUMN value_points_json JSON NULL AFTER short_description',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
