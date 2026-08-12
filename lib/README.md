# Librerías PDF (vendored)

- **FPDF 1.8.6** — https://github.com/Setasign/FPDF (licencia permissive / FPDF)
- **FPDI 2.6.0** — https://github.com/Setasign/FPDI (licencia MIT / Setasign)
- **qpdf 12.x (binario Linux x86_64)** — https://github.com/qpdf/qpdf (licencia Apache-2.0)

Usadas por `App\Support\PdfDocumentMerger` para anexar la hoja de firma
digital al PDF original del reglamento.

Los reglamentos subidos desde Word/Acrobat suelen ser PDF 1.5+ con
*object streams* / xref comprimido. El parser gratuito de FPDI no los lee;
`App\Support\PdfCompatNormalizer` los reescribe con `lib/qpdf/bin/qpdf`
(o el `qpdf` del sistema / Ghostscript) antes de unir páginas.
