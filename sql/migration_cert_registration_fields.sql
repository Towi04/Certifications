-- Campos de registro configurables por certificación
SET NAMES utf8mb4;

SET @db := DATABASE();
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'certifications' AND COLUMN_NAME = 'registration_fields_json'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE certifications ADD COLUMN registration_fields_json JSON NULL COMMENT ''off|optional|required por campo de adquisición'' AFTER value_points_json',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
