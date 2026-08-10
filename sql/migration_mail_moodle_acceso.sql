-- Plantilla de acceso Moodle (se envía al crear usuario / matricular tras el pago).
INSERT INTO mail_templates (code, name, audience, to_mode, to_fixed, cc_mode, cc_fixed, subject, body_html, attach_export, is_active)
VALUES (
  'moodle_acceso',
  'Moodle — Acceso al curso',
  'student',
  'student',
  NULL,
  'case_cc',
  NULL,
  'Acceso a tu curso en Campus Doceo — {{Certificación}}',
  '<p>¡Hola {{Nombre}}!</p>
<p>Ya tienes acceso a tu curso en la plataforma Moodle de Instituto DOCEO, ligado a <strong>{{Certificación}}</strong>.</p>
<p><strong>Usuario:</strong> {{user}}<br>
<strong>Contraseña:</strong> {{password}}</p>
<p>Si ya tenías cuenta y la contraseña aparece vacía, entra con tu usuario habitual y restablece la clave desde Campus si es necesario.</p>
<p>Campus: <a href="https://campus.institutodoceo.com">https://campus.institutodoceo.com</a></p>
<p>Instituto DOCEO<br>{{Contacto Doceo}}</p>',
  0,
  1
)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  subject = VALUES(subject),
  body_html = VALUES(body_html),
  is_active = VALUES(is_active);
