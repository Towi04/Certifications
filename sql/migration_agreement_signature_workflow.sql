-- Flujo de firma de convenios TR por versión/nivel:
-- publicar → asignar a partners del nivel → plazo → soft-block → admin confirma.
SET NAMES utf8mb4;

SET @db := DATABASE();

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'agreements' AND COLUMN_NAME = 'published_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE agreements ADD COLUMN published_at DATETIME NULL AFTER is_current',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'agreements' AND COLUMN_NAME = 'sign_deadline_days'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE agreements ADD COLUMN sign_deadline_days INT UNSIGNED NOT NULL DEFAULT 15 AFTER published_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'access_restricted'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE partners ADD COLUMN access_restricted TINYINT(1) NOT NULL DEFAULT 0 AFTER signed_agreement_path',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'restriction_reason'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE partners ADD COLUMN restriction_reason VARCHAR(255) NULL AFTER access_restricted',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'partner_agreement_assignments' AND COLUMN_NAME = 'signature_status'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE partner_agreement_assignments ADD COLUMN signature_status ENUM(''pending'',''submitted'',''approved'',''rejected'',''expired'') NOT NULL DEFAULT ''pending'' AFTER reason',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'partner_agreement_assignments' AND COLUMN_NAME = 'deadline_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE partner_agreement_assignments ADD COLUMN deadline_at DATETIME NULL AFTER signature_status',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'partner_agreement_assignments' AND COLUMN_NAME = 'signed_path'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE partner_agreement_assignments ADD COLUMN signed_path VARCHAR(255) NULL AFTER deadline_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'partner_agreement_assignments' AND COLUMN_NAME = 'submitted_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE partner_agreement_assignments ADD COLUMN submitted_at DATETIME NULL AFTER signed_path',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'partner_agreement_assignments' AND COLUMN_NAME = 'approved_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE partner_agreement_assignments ADD COLUMN approved_at DATETIME NULL AFTER submitted_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'partner_agreement_assignments' AND COLUMN_NAME = 'approved_by'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE partner_agreement_assignments ADD COLUMN approved_by BIGINT UNSIGNED NULL AFTER approved_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'partner_agreement_assignments' AND COLUMN_NAME = 'reject_reason'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE partner_agreement_assignments ADD COLUMN reject_reason TEXT NULL AFTER approved_by',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'partner_agreement_assignments' AND COLUMN_NAME = 'notified_at'
);
SET @sql := IF(@exists = 0,
  'ALTER TABLE partner_agreement_assignments ADD COLUMN notified_at DATETIME NULL AFTER reject_reason',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Asignaciones históricas con PDF en partner: considerarlas ya aprobadas.
UPDATE partner_agreement_assignments paa
JOIN partners p ON p.id = paa.partner_id
SET paa.signature_status = 'approved',
    paa.signed_path = COALESCE(paa.signed_path, p.signed_agreement_path),
    paa.approved_at = COALESCE(paa.approved_at, paa.assigned_at)
WHERE paa.ended_at IS NULL
  AND paa.signature_status = 'pending'
  AND p.signed_agreement_path IS NOT NULL
  AND p.signed_agreement_path <> '';
