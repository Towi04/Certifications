-- Firma digital de reglamento: PDF de evidencia + imagen de firma
ALTER TABLE certification_cases
  ADD COLUMN IF NOT EXISTS regulation_signed_pdf_path VARCHAR(255) NULL AFTER regulation_signer_name,
  ADD COLUMN IF NOT EXISTS regulation_signature_path VARCHAR(255) NULL AFTER regulation_signed_pdf_path,
  ADD COLUMN IF NOT EXISTS regulation_signature_mode VARCHAR(16) NULL AFTER regulation_signature_path;
