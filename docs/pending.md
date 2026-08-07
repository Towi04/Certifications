# Pendientes de producto (recordatorios)

## Registro / paper-based Cambridge

- Al registrarse a un examen paper-based, el Teacher Referral o alumno elige **estado/subcentro**.
- La dirección exacta del lugar (prestado/rentado) se define **por aplicación**, no en el catálogo del subcentro.
- Al confirmar el registro, enviar al alumno la dirección definida para esa aplicación.
- Sedes fijas (ej. 2 en CDMX) sí tienen dirección completa desde el admin de proveedores.

Estado actual: admin ya distingue `sede fija` vs `subcentro` (solo ciudad/estado).

## Proveedor: convenio vs marca pública

- Campo `code` = **Convenio con** (Creative Solutions, ETC Iberoamérica, Lingua Franca…). Solo admin.
- Campo `name` = **Certificaciones de** (Cambridge, Certiport, TOEFL/IIE…). Lo que ven alumnos y TR.
- Campo `website_url` = sitio del **convenio** (solo admin).
- Campo `brand_website_url` = sitio de la **marca que certifica** (público para alumnos/TR).
- No exponer el convenio/intermediario en catálogo público ni partner.
- Pestaña **Cuentas**: portales con login o **sitios sin login** (usuario vacío). Contraseñas cifradas (`APP_KEY`). Solo admin.
- Revelar/copiar contraseña de portal exige confirmar la **contraseña del sistema** del admin.
