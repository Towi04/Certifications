-- Semilla inicial Instituto Doceo PDV
-- Ejecutar después de schema.sql

SET NAMES utf8mb4;

INSERT INTO providers (code, name, website_url, notes, is_active) VALUES
('IIE', 'IIE', NULL, 'Convenio distribuidor autorizado', 1),
('CAMBRIDGE', 'Cambridge', NULL, 'Certificaciones online y papel', 1),
('OXFORD_UP', 'Oxford University Press', NULL, NULL, 1),
('UKS', 'UKS', NULL, NULL, 1),
('ITEP', 'ITEP', NULL, 'Inventario de códigos / compra por adelantado', 1),
('CERTIPORT', 'Certiport', NULL, 'Cursos en XperienceEd / eThinking según examen', 1),
('OXFORD_ED', 'Oxford Education', NULL, NULL, 1),
('MICHIGAN', 'University of Michigan', NULL, NULL, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO partner_tiers (code, name, sort_order, description, is_active) VALUES
('TIER_A', 'Convenio A', 1, 'Nivel Teacher Referral — editable/renombrable cada año', 1),
('TIER_B', 'Convenio B', 2, 'Nivel Teacher Referral — editable/renombrable cada año', 1),
('TIER_C', 'Convenio C', 3, 'Nivel Teacher Referral — editable/renombrable cada año', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, uses_inventory, is_active)
SELECT p.id, 'CAMBRIDGE_ONLINE', 'Cambridge Online', 'online',
  '<p>Protocolo base Cambridge en línea: documentación, agenda y entrega de acceso.</p>', 1, 0, 1
FROM providers p WHERE p.code = 'CAMBRIDGE'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, uses_inventory, is_active)
SELECT p.id, 'CAMBRIDGE_PAPER', 'Cambridge Papel', 'paper',
  '<p>Protocolo base Cambridge en papel.</p>', 1, 0, 1
FROM providers p WHERE p.code = 'CAMBRIDGE'
ON DUPLICATE KEY UPDATE name = VALUES(name);

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
