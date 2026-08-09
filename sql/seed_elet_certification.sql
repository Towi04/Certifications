-- Certificación pública ELET (UKS) para la vitrina
SET NAMES utf8mb4;

INSERT INTO certifications (
  provider_id, protocol_id, code, slug, name, short_description, description_html,
  modality, audience, public_price, currency, cenni_eligible, is_published, is_featured
)
SELECT p.id, pr.id, 'ELET', 'elet-uks', 'ELET (UKS)',
  'Examen de inglés ELET con trámite CENNI.',
  '<p>Examen ELET aplicado en línea con supervisión. Incluye seguimiento de certificado y CENNI.</p>',
  'online', 'Alumnos y público general', 1850.00, 'MXN', 1, 1, 1
FROM providers p
CROSS JOIN protocols pr
WHERE p.code = 'UKS' AND pr.code = 'UKS_ELET'
AND NOT EXISTS (SELECT 1 FROM certifications c WHERE c.slug = 'elet-uks');
