-- Adjuntar reglamento firmado (o original) en plantillas de correo
ALTER TABLE mail_templates
  ADD COLUMN attach_regulation TINYINT(1) NOT NULL DEFAULT 0
  COMMENT 'Adjuntar PDF del reglamento firmado (o original)'
  AFTER attach_export;
