-- Certificaciones: rangos de puntaje múltiples, costo Doceo, precios por nivel TR, CENNI
-- Ejecuta en phpMyAdmin. Ignora errores de “Duplicate column/table” si ya corriste partes.

SET NAMES utf8mb4;

-- Rangos de puntaje (JSON: [{min,max,label}, ...])
ALTER TABLE certifications
  ADD COLUMN score_ranges_json JSON NULL AFTER score_range;

-- Costo interno para Doceo (MXN)
ALTER TABLE certifications
  ADD COLUMN cost_price DECIMAL(12,2) NULL AFTER public_price;

-- Ampliar ENUM CENNI con "Constancia, Certificado"
ALTER TABLE certifications
  MODIFY cenni_doc_type ENUM(
    'none',
    'constancia',
    'constancia_certificado',
    'constancia_certificado_diploma'
  ) NOT NULL DEFAULT 'none';

-- Precios por nivel TR en cada certificación (siempre MXN)
CREATE TABLE IF NOT EXISTS certification_tier_prices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certification_id BIGINT UNSIGNED NOT NULL,
  partner_tier_id BIGINT UNSIGNED NOT NULL,
  price DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cert_tier (certification_id, partner_tier_id),
  CONSTRAINT fk_ctp_cert FOREIGN KEY (certification_id) REFERENCES certifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_ctp_tier FOREIGN KEY (partner_tier_id) REFERENCES partner_tiers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrar precios existentes de convenios al nivel TR correspondiente (si aún no hay)
INSERT INTO certification_tier_prices (certification_id, partner_tier_id, price)
SELECT ap.certification_id, a.partner_tier_id, MIN(ap.price)
FROM agreement_prices ap
JOIN agreements a ON a.id = ap.agreement_id
GROUP BY ap.certification_id, a.partner_tier_id
ON DUPLICATE KEY UPDATE price = LEAST(certification_tier_prices.price, VALUES(price));
