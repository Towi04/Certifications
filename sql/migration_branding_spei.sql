-- Branding Doceo + ficha SPEI propia (beneficiario Instituto DOCEO)
-- Ejecutar después de migration_openpay_cenni.sql

UPDATE mail_templates
SET
  body_html = '<p>¡Hola {{Nombre}}!</p><p>Para pagar tu certificación <strong>{{Certificación}}</strong> realiza una transferencia SPEI con estos datos:</p><p><strong>Beneficiario:</strong> {{OpenPay Beneficiario}}<br><strong>Banco:</strong> {{OpenPay Banco}}<br><strong>CLABE:</strong> {{OpenPay CLABE}}<br><strong>Convenio / referencia:</strong> {{OpenPay Referencia}}<br><strong>Monto:</strong> ${{OpenPay Monto}} MXN</p><p><a href="{{OpenPay SPEI URL}}">Ver / imprimir ficha SPEI Doceo</a></p><p>El pago se confirma automáticamente. Cuando OpenPay lo registre te enviaremos un correo.</p><p>Instituto DOCEO</p>',
  updated_at = CURRENT_TIMESTAMP
WHERE code = 'pago_clabe';
