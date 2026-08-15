-- Hora de aplicación en fechas presenciales Cambridge
ALTER TABLE exam_sittings
  ADD COLUMN exam_time VARCHAR(16) NULL COMMENT 'Hora de aplicación (presencial)' AFTER exam_date;
