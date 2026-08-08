# Exportaciones a proveedores (UKS / TOEFL / Cambridge)

El PDV genera los archivos que antes llenabas a mano:

| Formato | Archivo | Uso |
|---------|---------|-----|
| `uks_csv` | CSV Matrícula + apellidos + nombre + correo | Subir al portal UKS (también se puede adjuntar al correo de solicitud) |
| `toefl_xlsx` | Excel inscripción candidatos | Se adjunta al correo de solicitud TOEFL |
| `linguaskill_xlsx` | Excel CS Linguaskill | Subir al portal Cambridge |

Plantillas de referencia (blanco): `resources/provider_templates/`.

## Flujo operativo

1. Abrir / completar el **caso** con datos del alumno (nombre, apellidos, nacimiento, sexo, nacionalidad, fecha/hora).
2. En el protocolo, configurar `export_format` + plantilla de solicitud.
3. En el caso: **Confirmar pago y solicitar al proveedor** → sube comprobante → genera archivo → envía correo (si hay plantilla) con adjuntos.
4. Cuando la empresa responde: capturar Folio/Clave/Zoom/token en el caso y enviar plantilla de acceso al alumno (CC al TR).

Migración: `sql/migration_case_ops_exports.sql`.
