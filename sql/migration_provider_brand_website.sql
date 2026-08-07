-- Sitio del convenio (admin) vs sitio de la marca que certifica (público)
-- Ejecuta en phpMyAdmin. Ignora “Duplicate column” si ya existe.

SET NAMES utf8mb4;

ALTER TABLE providers
  ADD COLUMN brand_website_url VARCHAR(255) NULL AFTER website_url;
