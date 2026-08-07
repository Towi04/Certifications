-- Protocolos como flujos de pasos (pre / durante / post examen)
-- + protocolo en cursos + casos de certificación con progreso

CREATE TABLE IF NOT EXISTS protocol_steps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  protocol_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  phase ENUM('pre_exam', 'during_exam', 'post_exam') NOT NULL DEFAULT 'pre_exam',
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  responsible ENUM(
    'student',
    'admin',
    'tr',
    'student_or_tr',
    'provider',
    'sep',
    'system'
  ) NOT NULL DEFAULT 'student',
  trigger_days_after_exam INT NULL COMMENT 'Días tras el examen para este paso (recordatorios/plazos)',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_protocol_steps_protocol (protocol_id, sort_order),
  CONSTRAINT fk_protocol_steps_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Protocolo también aplicable a cursos (preparación / paquetes)
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'protocol_id'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE courses ADD COLUMN protocol_id BIGINT UNSIGNED NULL AFTER id, ADD KEY idx_courses_protocol (protocol_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK courses → protocols (si aún no existe)
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courses' AND CONSTRAINT_NAME = 'fk_courses_protocol'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE courses ADD CONSTRAINT fk_courses_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Caso / inscripción: una certificación en curso para un alumno
CREATE TABLE IF NOT EXISTS certification_cases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certification_id BIGINT UNSIGNED NOT NULL,
  protocol_id BIGINT UNSIGNED NOT NULL,
  student_user_id BIGINT UNSIGNED NULL,
  partner_id BIGINT UNSIGNED NULL COMMENT 'TR asociado, si hay',
  student_email VARCHAR(190) NOT NULL,
  student_name VARCHAR(190) NOT NULL,
  exam_date DATE NULL,
  status ENUM('in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'in_progress',
  current_step_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cases_cert (certification_id),
  KEY idx_cases_student_user (student_user_id),
  KEY idx_cases_status (status),
  CONSTRAINT fk_cases_certification FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_cases_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cases_student_user FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_cases_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE SET NULL,
  CONSTRAINT fk_cases_current_step FOREIGN KEY (current_step_id) REFERENCES protocol_steps(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certification_case_steps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id BIGINT UNSIGNED NOT NULL,
  protocol_step_id BIGINT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('pending', 'current', 'done', 'skipped', 'blocked') NOT NULL DEFAULT 'pending',
  completed_at DATETIME NULL,
  completed_by BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  meta_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_case_step (case_id, protocol_step_id),
  KEY idx_case_steps_case (case_id, sort_order),
  CONSTRAINT fk_case_steps_case FOREIGN KEY (case_id) REFERENCES certification_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_case_steps_step FOREIGN KEY (protocol_step_id) REFERENCES protocol_steps(id) ON DELETE RESTRICT,
  CONSTRAINT fk_case_steps_user FOREIGN KEY (completed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
