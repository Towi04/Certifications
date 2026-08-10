-- Links fijos del proveedor para alumnos (material, software, etc.) con alcance
SET NAMES utf8mb4;

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
