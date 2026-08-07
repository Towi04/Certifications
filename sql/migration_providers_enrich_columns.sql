-- Fallback para MariaDB/MySQL que no soportan ADD COLUMN IF NOT EXISTS
-- Si la migración anterior falla, ejecuta estos ALTER uno por uno e ignora “Duplicate column”.

ALTER TABLE providers ADD COLUMN contact_name VARCHAR(190) NULL AFTER logo_path;
ALTER TABLE providers ADD COLUMN contact_email VARCHAR(190) NULL AFTER contact_name;
ALTER TABLE providers ADD COLUMN contact_phone VARCHAR(64) NULL AFTER contact_email;
ALTER TABLE providers ADD COLUMN contact_whatsapp VARCHAR(64) NULL AFTER contact_phone;
ALTER TABLE providers ADD COLUMN auth_proof_type ENUM('none', 'url', 'document') NOT NULL DEFAULT 'none' AFTER contact_whatsapp;
ALTER TABLE providers ADD COLUMN auth_proof_url VARCHAR(255) NULL AFTER auth_proof_type;
ALTER TABLE providers ADD COLUMN auth_proof_path VARCHAR(255) NULL AFTER auth_proof_url;
