-- Cursos vendibles con protocolo (compra standalone)
-- También corre CatalogRepository::ensureCourseCommerceAndProtocols()

SET NAMES utf8mb4;

ALTER TABLE courses
  ADD COLUMN slug VARCHAR(190) NULL AFTER code,
  ADD COLUMN public_price DECIMAL(12,2) NULL AFTER description,
  ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'MXN' AFTER public_price,
  ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;

ALTER TABLE courses
  ADD UNIQUE KEY uq_courses_slug (slug);

ALTER TABLE certification_cases
  ADD COLUMN course_id BIGINT UNSIGNED NULL AFTER certification_id;

ALTER TABLE certification_cases
  MODIFY COLUMN certification_id BIGINT UNSIGNED NULL;

ALTER TABLE certification_cases
  ADD KEY idx_cases_course (course_id);

-- Protocolos de curso (provider_id NULL = interno Doceo / multi-proveedor)
INSERT INTO protocols (provider_id, code, name, modality, procedure_html,
  requires_regulation_signature, uses_inventory, provider_request_template, student_access_template, is_active)
VALUES
(NULL, 'COURSE_MOODLE', 'Curso · Campus Moodle Doceo', 'other',
 '<p>Registro, pago y alta automática en campus.institutodoceo.com al confirmar el pago.</p>',
 0, 0, NULL, 'moodle_acceso', 1),
(NULL, 'COURSE_ETHINKING', 'Curso · eThinking', 'other',
 '<p>Registro, pago, compra/solicitud al proveedor eThinking con comprobante Doceo, y envío de accesos al alumno cuando estén listos.</p>',
 0, 0, 'curso_solicitud_proveedor', 'curso_acceso_externo', 1),
(NULL, 'COURSE_XPERIENCEED', 'Curso · XperienceEd', 'other',
 '<p>Registro, pago, solicitud al proveedor XperienceEd y envío de accesos al alumno cuando el proveedor habilite la cuenta.</p>',
 0, 0, 'curso_solicitud_proveedor', 'curso_acceso_externo', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  procedure_html = VALUES(procedure_html),
  provider_request_template = VALUES(provider_request_template),
  student_access_template = VALUES(student_access_template),
  is_active = 1;
