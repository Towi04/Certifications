# OpenPay SPEI + seguimiento CENNI

## CLABE única por alumno

Al abrir un caso (vitrina o admin) el sistema crea un cargo OpenPay `method=bank_account` y guarda:

- CLABE, banco, convenio/referencia, monto, charge id, PDF SPEI

Monto = `public_price` + fee CENNI (si aplica y no está incluido).

Webhook (configurar en el dashboard OpenPay):

```
POST https://pdv.institutodoceo.com/webhooks/openpay
```

Eventos: `charge.succeeded` (y `spei.received`) → marca el caso como pagado y envía plantilla `pago_confirmado`.

Migración: `sql/migration_openpay_cenni.sql` (después de `migration_case_ops_exports.sql`).

## CENNI: dos caminos

| `cenni_process` | Producto típico | Docs del alumno |
|-----------------|-----------------|-----------------|
| `uks_external` | ELET | En plataforma UKS (enlace/QR de la constancia). Doceo solo monitorea y avisa. |
| `doceo_managed` | iTEP, Oxford, etc. | Subida en portal alumno → Doceo gestiona ante SEP |
| `none` | Sin CENNI | — |

En el caso admin puedes actualizar estatus (`awaiting_uks_upload` → … → `issued`) y notificar al alumno. Al marcar `issued` se usa la plantilla `cenni_emitido` (agradecimiento + invitación a la plataforma).
