-- Certificaciones: nivel/habilidades, rango, modalidades y CENNI
-- Ejecuta en phpMyAdmin. Ignora errores de “Duplicate column” si ya corriste partes.

SET NAMES utf8mb4;

-- Normalizar modalidades antes de reducir el ENUM
UPDATE certifications
SET modality = 'online'
WHERE modality IN ('hybrid', 'other');

ALTER TABLE certifications
  MODIFY modality ENUM('online', 'paper') NOT NULL DEFAULT 'online';

-- Nuevos campos de ficha
ALTER TABLE certifications
  ADD COLUMN is_level_exam TINYINT(1) NOT NULL DEFAULT 0 AFTER audience,
  ADD COLUMN skills_json JSON NULL AFTER is_level_exam,
  ADD COLUMN score_range VARCHAR(120) NULL AFTER skills_json;

-- Ampliar ENUM CENNI temporalmente, migrar y dejar solo 3 valores
ALTER TABLE certifications
  MODIFY cenni_doc_type ENUM(
    'none',
    'constancia',
    'certificado',
    'diploma',
    'constancia_certificado_diploma'
  ) NOT NULL DEFAULT 'none';

UPDATE certifications
SET cenni_doc_type = 'constancia_certificado_diploma'
WHERE cenni_doc_type IN ('certificado', 'diploma');

ALTER TABLE certifications
  MODIFY cenni_doc_type ENUM(
    'none',
    'constancia',
    'constancia_certificado_diploma'
  ) NOT NULL DEFAULT 'none';

-- Derivar CENNI incluido desde el fee (0 o NULL = incluido)
UPDATE certifications
SET cenni_included = CASE
  WHEN cenni_eligible = 1 AND (cenni_fee IS NULL OR cenni_fee <= 0) THEN 1
  ELSE 0
END;
