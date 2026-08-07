-- Sedes fijas vs subcentros (Cambridge y similares)
-- Ejecuta en phpMyAdmin. Ignora “Duplicate column” si ya existe.

SET NAMES utf8mb4;

ALTER TABLE provider_venues
  ADD COLUMN venue_type ENUM('fixed', 'subcentro') NOT NULL DEFAULT 'fixed' AFTER provider_id;

ALTER TABLE provider_venues
  MODIFY address_line VARCHAR(255) NULL;

-- Si falló el ADD anterior por duplicate, el MODIFY igual es útil.
