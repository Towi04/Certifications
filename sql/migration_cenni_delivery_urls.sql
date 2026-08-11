-- Campos CENNI de entrega al alumno (descarga + consulta SEP)
-- Se aplica también en runtime vía CatalogRepository::ensureInventoryAndResultColumns()

ALTER TABLE certification_cases
  ADD COLUMN IF NOT EXISTS cenni_download_url VARCHAR(512) NULL AFTER cenni_notes,
  ADD COLUMN IF NOT EXISTS cenni_sep_url VARCHAR(512) NULL AFTER cenni_download_url;
