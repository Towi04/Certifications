-- Trámites SEP (proveedor especial) + Cambridge: modalidades y fechas de aplicación
-- En producción también corre CatalogRepository::ensureCambridgeAndSepSchemaAndSeeds()
-- Si un ALTER falla por columna duplicada, ignóralo y continúa.

SET NAMES utf8mb4;

ALTER TABLE providers
  ADD COLUMN org_kind ENUM('certifier', 'tramites', 'internal') NOT NULL DEFAULT 'certifier'
  COMMENT 'certifier=proveedor de examen; tramites=CENNI/CONOCER Doceo; internal=uso interno'
  AFTER name;

ALTER TABLE certifications
  MODIFY COLUMN modality ENUM('online', 'online_home', 'online_venue', 'paper') NOT NULL DEFAULT 'online';

CREATE TABLE IF NOT EXISTS exam_sittings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id BIGINT UNSIGNED NOT NULL,
  certification_id BIGINT UNSIGNED NULL COMMENT 'NULL = aplica a todas las certs del proveedor con esa modalidad',
  modality ENUM('online_venue', 'paper') NOT NULL,
  exam_date DATE NOT NULL,
  registration_deadline DATE NOT NULL,
  label VARCHAR(190) NULL,
  venue_id BIGINT UNSIGNED NULL,
  capacity INT UNSIGNED NULL,
  notes TEXT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_exam_sitting_slot (provider_id, modality, exam_date, certification_id),
  KEY idx_exam_sittings_provider (provider_id, modality, exam_date),
  KEY idx_exam_sittings_deadline (registration_deadline, is_published, is_active),
  CONSTRAINT fk_exam_sittings_provider FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  CONSTRAINT fk_exam_sittings_cert FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_exam_sittings_venue FOREIGN KEY (venue_id) REFERENCES provider_venues(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE certification_cases
  ADD COLUMN exam_sitting_id BIGINT UNSIGNED NULL AFTER exam_time;

ALTER TABLE certification_cases
  ADD COLUMN schedule_deferred TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1=compró sin fecha; agendará cuando publiquen sittings'
    AFTER exam_sitting_id;

ALTER TABLE certification_cases
  ADD COLUMN institution_id VARCHAR(64) NULL
    COMMENT 'Ej. Cambridge Institution ID MX143'
    AFTER access_key;

INSERT INTO providers (code, name, org_kind, notes, is_active) VALUES
('TRAMITES_SEP', 'Trámites SEP', 'tramites',
 'Trámites que realiza Instituto Doceo ante la SEP (CENNI, Red CONOCER). No es un proveedor de examen.', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  org_kind = VALUES(org_kind),
  notes = VALUES(notes),
  is_active = 1;

UPDATE providers SET org_kind = 'tramites' WHERE code = 'TRAMITES_SEP';
