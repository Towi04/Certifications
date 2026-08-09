-- Flujo de adquisición del alumno: firma de reglamento + link de pago OpenPay
-- Ejecutar en phpMyAdmin sobre insti241_pdv (ignora "Duplicate column" si ya se aplicó)

SET NAMES utf8mb4;

ALTER TABLE certification_cases
  ADD COLUMN payment_link_url VARCHAR(500) NULL AFTER payment_proof_path,
  ADD COLUMN payment_link_id VARCHAR(120) NULL AFTER payment_link_url,
  ADD COLUMN regulation_signed_at DATETIME NULL AFTER payment_confirmed_at,
  ADD COLUMN regulation_signer_name VARCHAR(190) NULL AFTER regulation_signed_at;
