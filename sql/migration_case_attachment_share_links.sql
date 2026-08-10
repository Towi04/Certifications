-- Enlaces públicos de descarga para adjuntos de caso (comprobante, exportación)
SET NAMES utf8mb4;

SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'case_attachments' AND COLUMN_NAME = 'share_token'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE case_attachments ADD COLUMN share_token VARCHAR(64) NULL AFTER file_path',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'case_attachments' AND INDEX_NAME = 'uq_case_attachments_share_token'
);
SET @sql := IF(@idx = 0,
  'CREATE UNIQUE INDEX uq_case_attachments_share_token ON case_attachments (share_token)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
