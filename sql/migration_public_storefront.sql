-- Vitrina pública: productos estrella
SET @col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'certifications' AND COLUMN_NAME = 'is_featured'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE certifications ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published, ADD KEY idx_certifications_featured (is_featured)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Marcar estrellas por código/nombre (si ya existen en catálogo)
UPDATE certifications SET is_featured = 1
WHERE is_published = 1
  AND (
    code LIKE '%ELET%' OR name LIKE '%ELET%'
    OR code LIKE '%ITEP%' OR name LIKE '%ITEP%'
    OR code LIKE '%TOEFL%' OR name LIKE '%TOEFL%'
    OR code LIKE '%LINGUASKILL%' OR name LIKE '%Linguaskill%' OR name LIKE '%LINGUA SKILL%'
    OR code LIKE '%EXCEL%' OR name LIKE '%Excel%'
  );
