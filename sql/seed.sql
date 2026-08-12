-- Semilla inicial Instituto Doceo PDV
-- Ejecutar después de schema.sql

SET NAMES utf8mb4;

INSERT INTO providers (code, name, org_kind, website_url, notes, is_active) VALUES
('IIE', 'IIE', 'certifier', NULL, 'Convenio distribuidor autorizado', 1),
('CAMBRIDGE', 'Cambridge', 'certifier', NULL, 'Certificaciones online (casa o presencial digital) y papel', 1),
('OXFORD_UP', 'Oxford University Press', 'certifier', NULL, NULL, 1),
('UKS', 'UKS', 'certifier', NULL, NULL, 1),
('ITEP', 'ITEP', 'certifier', NULL, 'Inventario de códigos / compra por adelantado', 1),
('CERTIPORT', 'Certiport', 'certifier', NULL, 'Cursos en XperienceEd / eThinking según examen', 1),
('OXFORD_ED', 'Oxford Education', 'certifier', NULL, NULL, 1),
('MICHIGAN', 'University of Michigan', 'certifier', NULL, NULL, 1),
('TRAMITES_SEP', 'Trámites SEP', 'tramites', NULL,
 'Trámites Doceo ante la SEP (CENNI, Red CONOCER). No es proveedor de examen.', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), org_kind = VALUES(org_kind);

INSERT INTO partner_tiers (code, name, sort_order, description, is_active) VALUES
('TIER_A', 'Convenio A', 1, 'Nivel Teacher Referral — editable/renombrable cada año', 1),
('TIER_B', 'Convenio B', 2, 'Nivel Teacher Referral — editable/renombrable cada año', 1),
('TIER_C', 'Convenio C', 3, 'Nivel Teacher Referral — editable/renombrable cada año', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, uses_inventory, is_active)
SELECT p.id, 'CAMBRIDGE_ONLINE_HOME', 'Cambridge — Online desde casa', 'online',
  '<p>Examen en línea desde casa (lun–vie 9:00–18:00). Agendar con 10 días de antelación. Documentos: reglamento PDF firmado + INE (ambos lados) o pasaporte. Correos: info del examen y, aparte, Username / Password / Institution ID MX143.</p>', 1, 0, 1
FROM providers p WHERE p.code = 'CAMBRIDGE'
ON DUPLICATE KEY UPDATE name = VALUES(name), procedure_html = VALUES(procedure_html);

INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, uses_inventory, is_active)
SELECT p.id, 'CAMBRIDGE_ONLINE_VENUE', 'Cambridge — Presencial digital', 'online',
  '<p>Examen en computadora en sede con supervisor (sábados). Inscripción ~2 semanas antes. Fechas publicadas por el proveedor. Documentos: reglamento + INE/pasaporte. Entrega de certificado en sede o envío (costo extra); CENNI opcional si aplica.</p>', 1, 0, 1
FROM providers p WHERE p.code = 'CAMBRIDGE'
ON DUPLICATE KEY UPDATE name = VALUES(name), procedure_html = VALUES(procedure_html);

INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, uses_inventory, is_active)
SELECT p.id, 'CAMBRIDGE_PAPER', 'Cambridge — Presencial en papel', 'paper',
  '<p>Examen en papel en sede (sábados). Inscripción ~8 semanas antes. Fechas publicadas por el proveedor. Documentos: reglamento + INE/pasaporte. Certificado en sede o paquetería; CENNI opcional si el examen lo permite.</p>', 1, 0, 1
FROM providers p WHERE p.code = 'CAMBRIDGE'
ON DUPLICATE KEY UPDATE name = VALUES(name), procedure_html = VALUES(procedure_html);

-- Compat: mantener códigos legacy apuntando a los mismos flujos
INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, uses_inventory, is_active)
SELECT p.id, 'CAMBRIDGE_ONLINE', 'Cambridge Online (legacy → desde casa)', 'online',
  '<p>Usar CAMBRIDGE_ONLINE_HOME. Protocolo legacy conservado por compatibilidad.</p>', 1, 0, 1
FROM providers p WHERE p.code = 'CAMBRIDGE'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, uses_inventory, is_active)
SELECT p.id, 'SEP_CENNI', 'Trámite CENNI ante SEP', 'other',
  '<p>Producto independiente: Doceo gestiona el trámite CENNI ante la SEP. Puede venderse solo o como complemento de un examen que lo permita.</p>', 0, 0, 1
FROM providers p WHERE p.code = 'TRAMITES_SEP'
ON DUPLICATE KEY UPDATE name = VALUES(name), procedure_html = VALUES(procedure_html);

INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, uses_inventory, is_active)
SELECT p.id, 'SEP_CONOCER', 'Red CONOCER ante SEP', 'other',
  '<p>Producto independiente: trámite Red CONOCER que Doceo realiza y cobra por separado.</p>', 0, 0, 1
FROM providers p WHERE p.code = 'TRAMITES_SEP'
ON DUPLICATE KEY UPDATE name = VALUES(name), procedure_html = VALUES(procedure_html);

INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, uses_inventory, is_active)
SELECT p.id, 'CERTIPORT_STANDARD', 'Certiport Agenda estándar', 'online',
  '<p>Protocolo Certiport: agenda, posible curso en plataforma externa y badges.</p>', 0, 0, 1
FROM providers p WHERE p.code = 'CERTIPORT'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, uses_inventory, is_active)
SELECT p.id, 'ITEP_INVENTORY', 'ITEP Inventario', 'inventory',
  '<p>Los códigos se compran por adelantado. Al vender se asigna un código del inventario.</p>', 0, 1, 1
FROM providers p WHERE p.code = 'ITEP'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Convenios vigentes ejemplo 2026 (precios se cargan al dar de alta certificaciones)
INSERT INTO agreements (partner_tier_id, name, year, valid_from, valid_to, is_current, notes)
SELECT t.id, CONCAT(t.name, ' 2026'), 2026, '2026-01-01', '2026-12-31', 1, 'Versión anual inicial — ajustar precios en admin'
FROM partner_tiers t
WHERE t.code IN ('TIER_A', 'TIER_B', 'TIER_C')
  AND NOT EXISTS (
    SELECT 1 FROM agreements a WHERE a.partner_tier_id = t.id AND a.year = 2026
  );

INSERT INTO courses (code, name, platform_type, moodle_course_id, access_notes, is_active) VALUES
('TOEFL_ITP_PREP', 'TOEFL ITP Preparation', 'moodle', 3, 'Curso en campus.institutodoceo.com', 1),
('TKT_MODULE_1', 'TKT Module 1', 'moodle', 2, 'Curso en campus.institutodoceo.com', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), moodle_course_id = VALUES(moodle_course_id);
