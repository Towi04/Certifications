-- Activación de cuentas por correo + email_verified_at
-- Ejecuta en phpMyAdmin. Ignora errores de “Duplicate column/table” si ya corriste partes.

SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN email_verified_at DATETIME NULL AFTER must_change_password;

-- Usuarios ya activos se consideran verificados
UPDATE users
SET email_verified_at = COALESCE(created_at, NOW())
WHERE is_active = 1 AND email_verified_at IS NULL;

CREATE TABLE IF NOT EXISTS account_activations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_account_activations_token (token),
  KEY idx_account_activations_user (user_id),
  CONSTRAINT fk_account_activations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
