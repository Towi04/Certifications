-- Usuarios del panel: roles de personal, teléfono, nombre/apellidos
-- Ejecuta en phpMyAdmin. Ignora errores de “Duplicate column” si ya corriste partes.

SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN first_name VARCHAR(120) NULL AFTER name,
  ADD COLUMN last_name VARCHAR(120) NULL AFTER first_name,
  ADD COLUMN phone VARCHAR(64) NULL AFTER email;

-- Ampliar ENUM de roles (mantener valores previos)
ALTER TABLE users
  MODIFY role ENUM(
    'admin',
    'assistant',
    'manager',
    'partner',
    'student'
  ) NOT NULL DEFAULT 'student';

-- Rellenar nombre/apellidos desde el name legado
UPDATE users
SET first_name = TRIM(SUBSTRING_INDEX(name, ' ', 1)),
    last_name = NULLIF(TRIM(SUBSTRING(name, LENGTH(SUBSTRING_INDEX(name, ' ', 1)) + 1)), '')
WHERE (first_name IS NULL OR first_name = '')
  AND name IS NOT NULL
  AND TRIM(name) <> '';
