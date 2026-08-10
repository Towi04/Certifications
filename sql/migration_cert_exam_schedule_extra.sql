-- Hora de examen, aplicación extraordinaria y respuestas de campos custom
SET NAMES utf8mb4;
SET @db := DATABASE();

-- Casos: flag y fee de aplicación fuera de horario
SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'certification_cases' AND COLUMN_NAME = 'exam_extraordinary'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE certification_cases ADD COLUMN exam_extraordinary TINYINT(1) NOT NULL DEFAULT 0 AFTER exam_time',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'certification_cases' AND COLUMN_NAME = 'exam_extraordinary_fee'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE certification_cases ADD COLUMN exam_extraordinary_fee DECIMAL(12,2) NULL AFTER exam_extraordinary',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'certification_cases' AND COLUMN_NAME = 'registration_extra_json'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE certification_cases ADD COLUMN registration_extra_json JSON NULL AFTER regulation_signer_name',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
