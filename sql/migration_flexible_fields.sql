-- Campos flexibles: slots de acceso configurables por proveedor / certificación
-- También se asegura en runtime con FlexibleFieldService::ensureAccessFieldsColumn()

ALTER TABLE providers
  ADD COLUMN IF NOT EXISTS access_fields_json JSON NULL
  COMMENT 'Catálogo de slots de acceso (folio, links, archivos) para el admin';

ALTER TABLE certifications
  ADD COLUMN IF NOT EXISTS access_fields_json JSON NULL
  COMMENT 'Slots de acceso activos en esta certificación';

ALTER TABLE certification_cases
  ADD COLUMN IF NOT EXISTS access_extra_json JSON NULL
  COMMENT 'Valores de slots de acceso no mapeados a columnas fijas';
