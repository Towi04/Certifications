-- Instituto Doceo PDV — esquema inicial (Fase 0/1)
-- Importar en phpMyAdmin sobre la base insti241_pdv

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(64) NULL,
  username VARCHAR(190) NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(190) NOT NULL,
  first_name VARCHAR(120) NULL,
  last_name VARCHAR(120) NULL,
  role ENUM('admin', 'assistant', 'manager', 'partner', 'student') NOT NULL DEFAULT 'student',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  email_verified_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_resets_email (email),
  INDEX idx_password_resets_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_activations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_account_activations_token (token),
  KEY idx_account_activations_user (user_id),
  CONSTRAINT fk_account_activations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS providers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  website_url VARCHAR(255) NULL,
  brand_website_url VARCHAR(255) NULL,
  registration_fields_json JSON NULL COMMENT 'Campos disponibles para adquisición (elegibles en cada certificación)',
  logo_path VARCHAR(255) NULL,
  logo_icon_path VARCHAR(255) NULL,
  logo_full_path VARCHAR(255) NULL,
  contact_name VARCHAR(190) NULL,
  contact_email VARCHAR(190) NULL,
  contact_phone VARCHAR(64) NULL,
  contact_whatsapp VARCHAR(64) NULL,
  auth_proof_type ENUM('none', 'url', 'document') NOT NULL DEFAULT 'none',
  auth_proof_url VARCHAR(255) NULL,
  auth_proof_path VARCHAR(255) NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_providers_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS provider_agreements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(190) NOT NULL,
  year SMALLINT NULL,
  file_path VARCHAR(255) NOT NULL,
  signed_on DATE NULL,
  notes TEXT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_provider_agreements_provider (provider_id),
  CONSTRAINT fk_provider_agreements_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_contacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  role VARCHAR(64) NOT NULL DEFAULT 'general',
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(64) NULL,
  whatsapp VARCHAR(64) NULL,
  notes VARCHAR(255) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_provider_contacts_provider (provider_id),
  CONSTRAINT fk_provider_contacts_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_venues (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  venue_type ENUM('fixed', 'subcentro') NOT NULL DEFAULT 'fixed',
  name VARCHAR(190) NOT NULL,
  address_line VARCHAR(255) NULL,
  address_line2 VARCHAR(255) NULL,
  neighborhood VARCHAR(120) NULL,
  city VARCHAR(120) NOT NULL,
  state VARCHAR(120) NULL,
  postal_code VARCHAR(32) NULL,
  country VARCHAR(120) NOT NULL DEFAULT 'México',
  contact_name VARCHAR(190) NULL,
  contact_phone VARCHAR(64) NULL,
  contact_email VARCHAR(190) NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_provider_venues_provider (provider_id),
  KEY idx_provider_venues_type (venue_type),
  CONSTRAINT fk_provider_venues_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_provider_notes_provider (provider_id),
  CONSTRAINT fk_provider_notes_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  CONSTRAINT fk_provider_notes_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(190) NOT NULL,
  portal_url VARCHAR(255) NULL,
  username VARCHAR(190) NULL,
  password_enc TEXT NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_provider_accounts_provider (provider_id),
  CONSTRAINT fk_provider_accounts_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS protocols (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NULL,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  modality ENUM('online', 'paper', 'hybrid', 'inventory', 'other') NOT NULL DEFAULT 'online',
  procedure_html MEDIUMTEXT NULL COMMENT 'Resumen opcional; el flujo real vive en protocol_steps',
  requires_regulation_signature TINYINT(1) NOT NULL DEFAULT 0,
  requires_software TINYINT(1) NOT NULL DEFAULT 0,
  requires_zoom TINYINT(1) NOT NULL DEFAULT 0,
  requires_vm TINYINT(1) NOT NULL DEFAULT 0,
  uses_inventory TINYINT(1) NOT NULL DEFAULT 0,
  export_format VARCHAR(64) NOT NULL DEFAULT 'none' COMMENT 'none|uks_csv|toefl_xlsx|linguaskill_xlsx|…',
  provider_request_template VARCHAR(64) NULL,
  student_access_template VARCHAR(64) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_protocols_code (code),
  CONSTRAINT fk_protocols_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS partner_tiers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_partner_tiers_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agreements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_tier_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  year SMALLINT NOT NULL,
  valid_from DATE NOT NULL,
  valid_to DATE NULL,
  pdf_path VARCHAR(255) NULL,
  notes TEXT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_agreements_tier_year (partner_tier_id, year),
  CONSTRAINT fk_agreements_tier FOREIGN KEY (partner_tier_id) REFERENCES partner_tiers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partners (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  partner_tier_id BIGINT UNSIGNED NULL,
  current_agreement_id BIGINT UNSIGNED NULL,
  organization VARCHAR(190) NULL,
  phone VARCHAR(64) NULL,
  shipping_address_line VARCHAR(255) NULL,
  shipping_address_line2 VARCHAR(255) NULL,
  shipping_neighborhood VARCHAR(120) NULL,
  shipping_city VARCHAR(120) NULL,
  shipping_state VARCHAR(120) NULL,
  shipping_postal_code VARCHAR(32) NULL,
  shipping_country VARCHAR(120) NOT NULL DEFAULT 'México',
  signed_agreement_path VARCHAR(255) NULL,
  requires_invoice TINYINT(1) NOT NULL DEFAULT 0,
  tax_status_path VARCHAR(255) NULL,
  logo_path VARCHAR(255) NULL,
  nda_accepted_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_partners_user (user_id),
  CONSTRAINT fk_partners_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_partners_tier FOREIGN KEY (partner_tier_id) REFERENCES partner_tiers(id) ON DELETE SET NULL,
  CONSTRAINT fk_partners_agreement FOREIGN KEY (current_agreement_id) REFERENCES agreements(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_agreement_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id BIGINT UNSIGNED NOT NULL,
  agreement_id BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  reason VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  CONSTRAINT fk_paa_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE,
  CONSTRAINT fk_paa_agreement FOREIGN KEY (agreement_id) REFERENCES agreements(id) ON DELETE CASCADE,
  CONSTRAINT fk_paa_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  protocol_id BIGINT UNSIGNED NULL,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  platform_type ENUM('moodle', 'xperienceed', 'ethinking', 'external', 'internal', 'none') NOT NULL DEFAULT 'moodle',
  external_url VARCHAR(255) NULL,
  moodle_course_id INT UNSIGNED NULL,
  access_months TINYINT UNSIGNED NOT NULL DEFAULT 6 COMMENT 'Meses de acceso al otorgar desde el sistema',
  prorroga_price DECIMAL(12,2) NULL COMMENT 'Costo de prórroga (siempre +6 meses)',
  access_notes TEXT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_courses_code (code),
  KEY idx_courses_protocol (protocol_id),
  CONSTRAINT fk_courses_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  provider_group_id BIGINT UNSIGNED NULL,
  protocol_id BIGINT UNSIGNED NULL,
  code VARCHAR(64) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  name VARCHAR(255) NOT NULL,
  modality ENUM('online', 'paper') NOT NULL DEFAULT 'online',
  short_description TEXT NULL,
  value_points_json JSON NULL COMMENT 'Viñetas de valor agregado Doceo (por qué con nosotros)',
  registration_fields_json JSON NULL COMMENT 'Campos del formulario de adquisición: off|optional|required',
  description_html MEDIUMTEXT NULL,
  syllabus_html MEDIUMTEXT NULL,
  duration_label VARCHAR(120) NULL,
  audience VARCHAR(255) NULL,
  is_level_exam TINYINT(1) NOT NULL DEFAULT 0,
  skills_json JSON NULL,
  score_range VARCHAR(120) NULL,
  score_ranges_json JSON NULL,
  public_price DECIMAL(12,2) NULL,
  cost_price DECIMAL(12,2) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MXN',
  cenni_eligible TINYINT(1) NOT NULL DEFAULT 0,
  cenni_doc_type ENUM(
    'none',
    'constancia',
    'certificado',
    'constancia_certificado',
    'certificado_diploma',
    'constancia_certificado_diploma'
  ) NOT NULL DEFAULT 'none',
  cenni_included TINYINT(1) NOT NULL DEFAULT 0,
  cenni_fee DECIMAL(12,2) NULL,
  cenni_process ENUM('none', 'uks_external', 'doceo_managed') NOT NULL DEFAULT 'none',
  conocer_eligible TINYINT(1) NOT NULL DEFAULT 0,
  conocer_fee DECIMAL(12,2) NULL,
  features_json JSON NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_certifications_code (code),
  UNIQUE KEY uq_certifications_slug (slug),
  KEY idx_certifications_provider (provider_id),
  KEY idx_certifications_group (provider_group_id),
  KEY idx_certifications_featured (is_featured),
  CONSTRAINT fk_certifications_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_certifications_group FOREIGN KEY (provider_group_id) REFERENCES provider_groups(id) ON DELETE SET NULL,
  CONSTRAINT fk_certifications_protocol FOREIGN KEY (protocol_id) REFERENCES protocols(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Precios TR por nivel (fuente de verdad). agreement_prices se conserva por compatibilidad histórica.
CREATE TABLE IF NOT EXISTS certification_tier_prices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certification_id BIGINT UNSIGNED NOT NULL,
  partner_tier_id BIGINT UNSIGNED NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cert_tier (certification_id, partner_tier_id),
  CONSTRAINT fk_ctp_cert FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_ctp_tier FOREIGN KEY (partner_tier_id) REFERENCES partner_tiers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agreement_prices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  agreement_id BIGINT UNSIGNED NOT NULL,
  certification_id BIGINT UNSIGNED NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'MXN',
  UNIQUE KEY uq_agreement_cert (agreement_id, certification_id),
  CONSTRAINT fk_ap_agreement FOREIGN KEY (agreement_id) REFERENCES agreements(id) ON DELETE CASCADE,
  CONSTRAINT fk_ap_cert FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certification_courses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certification_id BIGINT UNSIGNED NOT NULL,
  course_id BIGINT UNSIGNED NOT NULL,
  relation_type ENUM('included', 'sold_separate', 'bundle_discount') NOT NULL DEFAULT 'included',
  bundle_price DECIMAL(12,2) NULL,
  notes VARCHAR(255) NULL,
  UNIQUE KEY uq_cert_course (certification_id, course_id),
  CONSTRAINT fk_cc_cert FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_cc_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_assets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type ENUM('provider', 'certification', 'course', 'agreement') NOT NULL,
  owner_id BIGINT UNSIGNED NOT NULL,
  asset_type ENUM('provider_logo', 'exam_logo', 'certificate_sample', 'badge', 'syllabus_pdf', 'regulation_pdf', 'cover', 'other') NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  title VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_assets_owner (owner_type, owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL COMMENT 'Clave estable para tokens de correo ({{Link CODE}})',
  label VARCHAR(190) NOT NULL,
  url VARCHAR(1024) NOT NULL,
  link_type ENUM('study_material','software','exam_portal','other') NOT NULL DEFAULT 'other',
  scope_type ENUM('provider','group','certification') NOT NULL DEFAULT 'provider',
  provider_group_id BIGINT UNSIGNED NULL,
  certification_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_provider_link_code (provider_id, code),
  KEY idx_provider_links_provider (provider_id),
  KEY idx_provider_links_group (provider_group_id),
  KEY idx_provider_links_cert (certification_id),
  CONSTRAINT fk_provider_links_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NULL,
  scope_type ENUM('provider','group','certification') NOT NULL DEFAULT 'provider'
    COMMENT 'Aplica a toda la empresa, un grupo o una certificación',
  provider_group_id BIGINT UNSIGNED NULL,
  certification_id BIGINT UNSIGNED NULL,
  code VARCHAR(64) NOT NULL,
  title VARCHAR(190) NOT NULL,
  version VARCHAR(64) NOT NULL DEFAULT '1.0',
  doc_type ENUM(
    'regulation', 'form', 'checklist', 'instructions',
    'export_template', 'student', 'provider_ops', 'other'
  ) NOT NULL DEFAULT 'other',
  file_path VARCHAR(255) NULL,
  share_token VARCHAR(64) NULL,
  body_html MEDIUMTEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_documents_code (code),
  UNIQUE KEY uq_documents_share_token (share_token),
  KEY idx_documents_provider (provider_id),
  KEY idx_documents_group (provider_group_id),
  KEY idx_documents_cert (certification_id),
  CONSTRAINT fk_documents_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certification_docs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certification_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 1,
  stage ENUM('purchase', 'exam', 'cenni', 'conocer', 'other') NOT NULL DEFAULT 'purchase',
  UNIQUE KEY uq_cert_doc (certification_id, document_id, stage),
  CONSTRAINT fk_cd_cert FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_cd_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Caso: un alumno siguiendo el protocolo de una certificación
CREATE TABLE IF NOT EXISTS certification_cases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certification_id BIGINT UNSIGNED NOT NULL,
  protocol_id BIGINT UNSIGNED NOT NULL,
  student_user_id BIGINT UNSIGNED NULL,
  partner_id BIGINT UNSIGNED NULL COMMENT 'TR tránsito, si hay',
  student_email VARCHAR(190) NOT NULL,
  student_name VARCHAR(190) NOT NULL,
  student_last_name_p VARCHAR(120) NULL,
  student_last_name_m VARCHAR(120) NULL,
  student_phone VARCHAR(64) NULL,
  student_curp VARCHAR(32) NULL,
  student_birth_date DATE NULL,
  student_sex VARCHAR(16) NULL,
  student_nationality VARCHAR(64) NULL,
  exam_date DATE NULL,
  exam_time VARCHAR(32) NULL,
  exam_extraordinary TINYINT(1) NOT NULL DEFAULT 0,
  exam_extraordinary_fee DECIMAL(12,2) NULL,
  reschedule_date DATE NULL,
  reschedule_time VARCHAR(32) NULL,
  folio_id VARCHAR(120) NULL,
  access_key VARCHAR(120) NULL,
  inventory_code_id BIGINT UNSIGNED NULL,
  zoom_url VARCHAR(512) NULL,
  prep_doc_url VARCHAR(512) NULL,
  access_doc_url VARCHAR(512) NULL,
  moodle_user VARCHAR(120) NULL,
  moodle_password VARCHAR(120) NULL,
  payment_proof_path VARCHAR(255) NULL,
  payment_proof_share_token VARCHAR(64) NULL,
  payment_confirmed_at DATETIME NULL,
  payment_method VARCHAR(32) NULL COMMENT 'cash|transfer|openpay|other',
  provider_export_path VARCHAR(255) NULL,
  provider_export_share_token VARCHAR(64) NULL,
  provider_request_sent_at DATETIME NULL,
  cancel_reason TEXT NULL,
  results_url VARCHAR(512) NULL,
  score_url VARCHAR(512) NULL,
  certificate_url VARCHAR(512) NULL,
  exam_outcome VARCHAR(32) NULL DEFAULT 'pending' COMMENT 'pending|delivered|invalidated',
  invalidation_reason TEXT NULL,
  cc_email VARCHAR(190) NULL,
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
  cenni_status VARCHAR(40) NOT NULL DEFAULT 'none',
  cenni_folio VARCHAR(120) NULL,
  cenni_notes TEXT NULL,
  cenni_status_updated_at DATETIME NULL,
  status ENUM('in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'in_progress',
  current_step_id BIGINT UNSIGNED NULL,
  notes TEXT NULL,
  regulation_document_id BIGINT UNSIGNED NULL,
  regulation_signed_at DATETIME NULL,
  regulation_signer_name VARCHAR(190) NULL,
  regulation_signed_pdf_path VARCHAR(255) NULL,
  regulation_signature_path VARCHAR(255) NULL,
  regulation_signature_mode VARCHAR(16) NULL,
  registration_extra_json JSON NULL,
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

-- Inventario de códigos de examen (iTEP y similares)
CREATE TABLE IF NOT EXISTS inventory_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NULL,
  certification_id BIGINT UNSIGNED NULL,
  exam_id VARCHAR(120) NOT NULL COMMENT 'Examen ID / Folio que ve el alumno',
  access_code VARCHAR(190) NOT NULL COMMENT 'Contraseña / clave del examen',
  batch_label VARCHAR(190) NULL,
  status ENUM('available','assigned','void') NOT NULL DEFAULT 'available',
  assigned_case_id BIGINT UNSIGNED NULL,
  assigned_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_inventory_status (status, certification_id, provider_id),
  KEY idx_inventory_case (assigned_case_id),
  UNIQUE KEY uq_inventory_exam_code (exam_id, access_code),
  CONSTRAINT fk_inventory_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL,
  CONSTRAINT fk_inventory_cert FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE SET NULL,
  CONSTRAINT fk_inventory_case FOREIGN KEY (assigned_case_id) REFERENCES certification_cases(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS mail_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL,
  name VARCHAR(190) NOT NULL,
  audience ENUM('student', 'provider', 'internal', 'other') NOT NULL DEFAULT 'student',
  to_mode ENUM('student', 'provider', 'fixed', 'manual') NOT NULL DEFAULT 'student',
  to_fixed VARCHAR(255) NULL,
  cc_mode ENUM('none', 'tr', 'fixed', 'case_cc') NOT NULL DEFAULT 'none',
  cc_fixed VARCHAR(255) NULL,
  subject VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  attach_export TINYINT(1) NOT NULL DEFAULT 0,
  attach_regulation TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mail_templates_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(64) NOT NULL,
  label VARCHAR(190) NULL,
  file_path VARCHAR(255) NOT NULL,
  share_token VARCHAR(64) NULL,
  uploaded_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_case_attachments_share_token (share_token),
  KEY idx_case_attachments_case (case_id),
  CONSTRAINT fk_case_attachments_case FOREIGN KEY (case_id) REFERENCES certification_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_case_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS case_mail_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  case_id BIGINT UNSIGNED NOT NULL,
  template_code VARCHAR(64) NULL,
  to_email VARCHAR(255) NOT NULL,
  cc_email VARCHAR(255) NULL,
  subject VARCHAR(255) NOT NULL,
  attachment_path VARCHAR(255) NULL,
  status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
  error_message TEXT NULL,
  sent_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_case_mail_log_case (case_id),
  CONSTRAINT fk_case_mail_log_case FOREIGN KEY (case_id) REFERENCES certification_cases(id) ON DELETE CASCADE,
  CONSTRAINT fk_case_mail_log_user FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS openpay_webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(64) NULL,
  openpay_charge_id VARCHAR(64) NULL,
  order_id VARCHAR(100) NULL,
  case_id BIGINT UNSIGNED NULL,
  payload_json MEDIUMTEXT NOT NULL,
  processed TINYINT(1) NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_openpay_webhook_charge (openpay_charge_id),
  KEY idx_openpay_webhook_order (order_id),
  KEY idx_openpay_webhook_case (case_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
