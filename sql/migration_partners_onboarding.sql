-- Partners TR: domicilio, documentos, logo + cambio de contraseña obligatorio
-- Ejecuta en phpMyAdmin. Ignora errores de “Duplicate column” si ya corriste partes.

SET NAMES utf8mb4;

ALTER TABLE users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

ALTER TABLE partners
  ADD COLUMN shipping_address_line VARCHAR(255) NULL AFTER phone,
  ADD COLUMN shipping_address_line2 VARCHAR(255) NULL AFTER shipping_address_line,
  ADD COLUMN shipping_neighborhood VARCHAR(120) NULL AFTER shipping_address_line2,
  ADD COLUMN shipping_city VARCHAR(120) NULL AFTER shipping_neighborhood,
  ADD COLUMN shipping_state VARCHAR(120) NULL AFTER shipping_city,
  ADD COLUMN shipping_postal_code VARCHAR(32) NULL AFTER shipping_state,
  ADD COLUMN shipping_country VARCHAR(120) NOT NULL DEFAULT 'México' AFTER shipping_postal_code,
  ADD COLUMN signed_agreement_path VARCHAR(255) NULL AFTER shipping_country,
  ADD COLUMN requires_invoice TINYINT(1) NOT NULL DEFAULT 0 AFTER signed_agreement_path,
  ADD COLUMN tax_status_path VARCHAR(255) NULL AFTER requires_invoice,
  ADD COLUMN logo_path VARCHAR(255) NULL AFTER tax_status_path;
