-- Cuentas de portales de proveedores (accesos admin)
-- Ejecuta en phpMyAdmin. Ignora “already exists” si ya corriste esto.

SET NAMES utf8mb4;

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
