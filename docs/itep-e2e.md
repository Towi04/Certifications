# Prueba E2E iTEP (pago → accesos → resultados → CENNI)

Script: `php bin/itep-e2e-test.php`

## Qué cubre

1. Abre un caso de **iTEP Academic-Plus** (`doceo_managed` + inventario).
2. Confirma pago → asigna código de inventario + correos `pago_confirmado` / `itep_data` (+ `moodle_acceso`).
3. Publica score report + certificate → `itep_resultados` (incluye guía CENNI).
4. Simula subida de INE / CURP / solicitud CENNI.
5. Rechazo admin → plantilla `cenni_docs_rechazados`.
6. Emisión CENNI → `cenni_emitido` con folio, CURP, link de descarga y SEP.

## Correos de prueba

En `.env`:

```env
MAIL_OVERRIDE_TO=towisexy@gmail.com
SMTP_TRANSPORT=log   # escribe en storage/logs/mail/ (sin SMTP)
# En producción, con SMTP_PASS real:
# SMTP_TRANSPORT=auto
# MAIL_OVERRIDE_TO=towisexy@gmail.com
```

`MAIL_OVERRIDE_TO` fuerza el destinatario de **todos** los envíos (alumno y proveedor).
