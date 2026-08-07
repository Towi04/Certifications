-- Proveedores v2: logos duales, contactos, sedes, notas
-- Ejecuta en phpMyAdmin. Si una columna ya existe, ignora el error Duplicate column.

SET NAMES utf8mb4;

ALTER TABLE providers ADD COLUMN logo_icon_path VARCHAR(255) NULL AFTER logo_path;
ALTER TABLE providers ADD COLUMN logo_full_path VARCHAR(255) NULL AFTER logo_icon_path;

UPDATE providers
SET logo_icon_path = logo_path
WHERE logo_icon_path IS NULL AND logo_path IS NOT NULL;

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
