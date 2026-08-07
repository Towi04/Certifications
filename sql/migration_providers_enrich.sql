-- Enriquecer proveedores: convenios PDF versionados + columnas de contacto/autorización
-- 1) Ejecuta sql/migration_providers_enrich_columns.sql (ignora "Duplicate column name")
-- 2) Luego este archivo (crea provider_agreements)

SET NAMES utf8mb4;

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
