-- Semilla protocolo ELET (UKS) — pasos del flujo real Instituto Doceo
-- Ejecutar después de migration_protocol_steps.sql (o schema completo)

SET NAMES utf8mb4;

INSERT INTO protocols (provider_id, code, name, modality, procedure_html, requires_regulation_signature, is_active)
SELECT p.id, 'UKS_ELET', 'ELET — UKS (pre / examen / post + CENNI)', 'online',
  '<p>Flujo ELET: reglamento, registro, pago OpenPay, gestión UKS, aplicación supervisada y trámite CENNI/SEP.</p>',
  1, 1
FROM providers p WHERE p.code = 'UKS'
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  procedure_html = VALUES(procedure_html),
  requires_regulation_signature = VALUES(requires_regulation_signature),
  is_active = 1;

-- Reemplazar pasos si el protocolo ya existía (idempotente para seed)
DELETE ps FROM protocol_steps ps
INNER JOIN protocols pr ON pr.id = ps.protocol_id
WHERE pr.code = 'UKS_ELET';

INSERT INTO protocol_steps (protocol_id, sort_order, phase, title, description, responsible, trigger_days_after_exam, is_active)
SELECT pr.id, s.sort_order, s.phase, s.title, s.description, s.responsible, s.trigger_days, 1
FROM protocols pr
CROSS JOIN (
  SELECT 1 AS sort_order, 'pre_exam' AS phase, 'Firmar el reglamento del examen' AS title,
    'El alumno firma el reglamento antes de continuar.' AS description,
    'student' AS responsible, NULL AS trigger_days
  UNION ALL SELECT 2, 'pre_exam', 'Llenar el registro del candidato',
    'Formulario con datos personales y elección de la fecha del examen. Puede hacerlo el alumno o el TR.',
    'student_or_tr', NULL
  UNION ALL SELECT 3, 'pre_exam', 'OpenPay genera el link de pago',
    'Se genera la CLABE / link único para rastrear el pago.',
    'system', NULL
  UNION ALL SELECT 4, 'pre_exam', 'El alumno realiza el pago',
    'Pago del examen (y CENNI si aplica) vía OpenPay.',
    'student', NULL
  UNION ALL SELECT 5, 'pre_exam', 'Solicitar el examen a UKS por correo',
    'El administrador envía a UKS: datos del alumno, fecha del examen y el reglamento firmado.',
    'admin', NULL
  UNION ALL SELECT 6, 'pre_exam', 'UKS habilita el examen en su plataforma',
    'La certificadora prepara el examen para la fecha solicitada.',
    'provider', NULL
  UNION ALL SELECT 7, 'pre_exam', 'Subir CSV del alumno a la plataforma UKS',
    'El administrador llena el archivo CSV con los datos del alumno y lo sube al registro de UKS.',
    'admin', NULL
  UNION ALL SELECT 8, 'pre_exam', 'UKS genera ID único y clave del día',
    'La plataforma UKS emite las credenciales de acceso al examen.',
    'provider', NULL
  UNION ALL SELECT 9, 'pre_exam', 'Enviar ID y clave del día al alumno',
    'Correo al alumno (con copia al TR si hay) incluyendo video tutorial y/o PDF de instrucciones.',
    'admin', NULL
  UNION ALL SELECT 10, 'during_exam', 'El alumno se conecta al examen',
    'Acceso en la fecha y hora acordadas con ID y clave del día.',
    'student', NULL
  UNION ALL SELECT 11, 'during_exam', 'Supervisar la aplicación y tomar capturas',
    'El administrador supervisa la sesión y documenta con capturas de pantalla.',
    'admin', NULL
  UNION ALL SELECT 12, 'during_exam', 'Constancia al finalizar el examen',
    'El alumno recibe su constancia al terminar la aplicación.',
    'provider', NULL
  UNION ALL SELECT 13, 'post_exam', 'Subir documentos CENNI en plataforma UKS (si aplica)',
    'Tras el ELET el alumno recibe constancia + enlace/QR. Sube INE, CURP y Solicitud CENNI en UKS (máx. 15 días). No se suben en Doceo. Doceo monitorea en UKS y puede informar avances.',
    'student', 15
  UNION ALL SELECT 14, 'post_exam', 'Recordatorio / monitoreo docs CENNI (UKS)',
    'Si a los 10 días no hay docs en UKS, el admin contacta al alumno o TR y actualiza el estatus en el caso Doceo.',
    'admin', 10
  UNION ALL SELECT 15, 'post_exam', 'UKS acepta o rechaza los documentos',
    'Revisión en plataforma UKS. El admin refleja el resultado en el estatus CENNI del caso (y puede avisar al alumno).',
    'provider', NULL
  UNION ALL SELECT 16, 'post_exam', 'Aviso de UKS sobre documentos',
    'UKS también notifica al alumno. Doceo puede reenviar seguimiento desde el caso.',
    'provider', NULL
  UNION ALL SELECT 17, 'post_exam', 'Espera de emisión CENNI por la SEP',
    'Trámite en curso. SEP notifica al alumno; Doceo marca sep_pending y puede informar.',
    'sep', NULL
  UNION ALL SELECT 18, 'post_exam', 'Registrar Folio CENNI (monitoreo UKS)',
    'Cuando UKS/SEP emiten el folio, el admin lo captura en el caso y notifica al alumno.',
    'admin', 15
  UNION ALL SELECT 19, 'post_exam', 'CENNI emitido — agradecimiento',
    'Correo de cierre: docs emitidos, gracias e invitación a adquirir otra certificación en la plataforma.',
    'student', 20
) AS s
WHERE pr.code = 'UKS_ELET';
