# Reglamentos PDF compatibles con FPDI (sin object streams / xref comprimido)

Estos archivos se usan como respaldo cuando el hosting no puede ejecutar `qpdf`
(p. ej. `proc_open` deshabilitado o sin binario).

| Archivo | Origen |
|---|---|
| `uks-doceo-fpdi.pdf` | Reglamento UKS/ELET Doceo (versión FPDI-safe del PDF comprimido de clientes) |

Al firmar o regenerar, si el PDF en `storage` aún es incompatible, se copia
esta versión sobre el archivo del documento (queda `.pre-fpdi.bak`).
