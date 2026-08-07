-- Cuentas con login o sitios sin login (usuario/contraseña opcionales)
-- Ejecuta en phpMyAdmin.

SET NAMES utf8mb4;

ALTER TABLE provider_accounts
  MODIFY username VARCHAR(190) NULL,
  MODIFY password_enc TEXT NULL;
