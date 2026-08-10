-- Plantilla de reagenda al proveedor (solicitud de nueva fecha/hora).
INSERT INTO mail_templates (code, name, audience, to_mode, to_fixed, cc_mode, cc_fixed, subject, body_html, attach_export, is_active)
VALUES (
  'reagenda_solicitud',
  'Reagenda — Solicitud al proveedor',
  'provider',
  'provider',
  '',
  'none',
  '',
  'Reagenda {{Certificación}} — {{Nombre Completo}} — {{Fecha}}',
  '<p>¡Hola!</p><p>Solicito <strong>reagendar</strong> el examen:</p><p>Certificación: <strong>{{Certificación}}</strong><br>Alumno: <strong>{{Nombre Completo}}</strong><br>Nueva fecha: <strong>{{Fecha}}</strong><br>Nueva hora: <strong>{{Hora}}</strong></p><p>Adjunto exportación y comprobante cuando aplique.</p><p>Instituto DOCEO<br>{{Contacto Doceo}}</p>',
  1,
  1
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  audience = VALUES(audience),
  to_mode = VALUES(to_mode),
  subject = VALUES(subject),
  body_html = VALUES(body_html),
  attach_export = VALUES(attach_export),
  is_active = VALUES(is_active);
