-- Acceso Moodle limitado + prórroga (6 meses)
ALTER TABLE courses
  ADD COLUMN access_months TINYINT UNSIGNED NOT NULL DEFAULT 6
    COMMENT 'Meses de acceso al otorgar desde el sistema' AFTER moodle_course_id,
  ADD COLUMN prorroga_price DECIMAL(12,2) NULL
    COMMENT 'Costo de prórroga (siempre +6 meses)' AFTER access_months;

CREATE TABLE IF NOT EXISTS case_moodle_enrolments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  moodle_user_id INT UNSIGNED NULL,
  moodle_course_id INT UNSIGNED NOT NULL,
  access_starts_at DATETIME NOT NULL,
  access_ends_at DATETIME NOT NULL,
  status ENUM('active','suspended','expired') NOT NULL DEFAULT 'active',
  last_synced_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_case_course_enrol (case_id, course_id),
  KEY idx_cme_ends (status, access_ends_at),
  CONSTRAINT fk_cme_case FOREIGN KEY (case_id) REFERENCES certification_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_cme_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS course_prorrogas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id BIGINT UNSIGNED NOT NULL,
  case_moodle_enrolment_id BIGINT UNSIGNED NOT NULL,
  months TINYINT UNSIGNED NOT NULL DEFAULT 6,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MXN',
  status ENUM('pending','proof_uploaded','paid','cancelled') NOT NULL DEFAULT 'pending',
  payment_method VARCHAR(32) NULL COMMENT 'openpay|cash|transfer|other',
  payment_proof_path VARCHAR(255) NULL,
  payment_confirmed_at DATETIME NULL,
  openpay_charge_id VARCHAR(64) NULL,
  openpay_order_id VARCHAR(100) NULL,
  openpay_clabe VARCHAR(32) NULL,
  openpay_bank VARCHAR(120) NULL,
  openpay_agreement VARCHAR(64) NULL,
  openpay_reference VARCHAR(120) NULL,
  openpay_amount DECIMAL(12,2) NULL,
  openpay_status VARCHAR(32) NULL,
  openpay_due_at DATETIME NULL,
  openpay_paid_at DATETIME NULL,
  openpay_pdf_url VARCHAR(512) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_prorroga_charge (openpay_charge_id),
  KEY idx_prorroga_order (openpay_order_id),
  KEY idx_prorroga_case (case_id, status),
  CONSTRAINT fk_prorroga_case FOREIGN KEY (case_id) REFERENCES certification_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_prorroga_enrol FOREIGN KEY (case_moodle_enrolment_id)
    REFERENCES case_moodle_enrolments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
