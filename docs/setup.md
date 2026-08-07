# Guía de instalación — Neubox / Instituto Doceo

## 1. Despliegue del código

El subdomain **https://pdv.institutodoceo.com** debe apuntar a la carpeta del repositorio.

Opciones de Document Root:

- **Recomendado:** Document Root = carpeta `public/`
- **Alternativa:** Document Root = raíz del repo (usa `index.php` + `.htaccess` de la raíz)

El deploy por **webhook** (sin GitHub Actions) es el mecanismo actual. No se usa el viejo workflow FTP.

## 2. Archivo `.env`

1. En cPanel → Administrador de archivos, ve a la raíz del proyecto.
2. Si no existe `.env`, copia `.env.example` y renómbralo a `.env`.
3. Completa estos valores (los demás ya traen defaults útiles):

| Variable | Qué poner |
|---|---|
| `DB_PASS` | Contraseña de `insti241_manager` |
| `MOODLE_TOKEN` | Token del webservice (el mismo que ya funciona en `test_moodle.php`) |
| `OPENPAY_PRIVATE_KEY` | Llave secreta sandbox real (`sk_…`), no el placeholder |
| `SMTP_PASS` | Contraseña de `certificaciones@institutodoceo.com` |
| `APP_KEY` | Cadena larga aleatoria |
| `ADMIN_PASSWORD` | Contraseña inicial del admin (cámbiala después) |

Valores ya prellenados (puedes dejarlos):

- `DB_NAME=insti241_pdv` / `DB_USER=insti241_manager`
- `MOODLE_URL=https://campus.institutodoceo.com/webservice/rest/server.php`
- `OPENPAY_MERCHANT_ID` + `OPENPAY_PUBLIC_KEY` (sandbox)
- SMTP host/puerto/usuario

## 3. Base de datos

1. cPanel → phpMyAdmin → base `insti241_pdv`
2. Importa en orden:
   - `sql/schema.sql`
   - `sql/seed.sql`
3. El primer login con `ADMIN_EMAIL` o `ADMIN_USERNAME` / `ADMIN_PASSWORD` creará el usuario admin si la tabla `users` está vacía. Puedes entrar con `admin` si usas `ADMIN_USERNAME=admin`.

Si la base ya existía antes de sedes/subcentros, ejecuta también en phpMyAdmin:

- `sql/migration_providers_v2.sql` (si aún no tienes contactos/sedes nuevas)
- `sql/migration_venues_subcentros.sql` (tipo `fixed` vs `subcentro` + dirección opcional)
- `sql/migration_certifications_form.sql` (examen de nivel, habilidades, rango, CENNI/modalidad)
- `sql/migration_cert_tier_prices_ranges.sql` (rangos múltiples, precios por nivel TR, costo Doceo, CENNI Constancia+Certificado)
- `sql/migration_provider_brand_website.sql` (sitio del convenio vs sitio de la marca pública)
- `sql/migration_provider_accounts.sql` (cuentas/portales con usuario y contraseña cifrada)
- `sql/migration_provider_accounts_sites.sql` (permite sitios sin usuario/contraseña)
- `sql/migration_admin_users_roles.sql` (roles Administrador/Asistente/Gestor/Partner TR + teléfono/nombre)

### Subida de PDFs de convenio

Si ves “archivo demasiado grande (código 1)”, el hosting limita `upload_max_filesize`. El repo incluye `public/.user.ini` con 20M; en Neubox también puedes subirlo en MultiPHP INI Editor.

## 4. Moodle

- Servicio externo con función `core_course_get_courses` (ya verificada).
- Más adelante (enrol): añadir `core_user_create_users`, `core_user_get_users_by_field`, `enrol_manual_enrol_users`.
- Cuando el panel de salud esté en verde, **elimina** `test_moodle.php` del servidor.

## 5. OpenPay (sandbox)

- Dashboard sandbox → copiar **llave privada** a `OPENPAY_PRIVATE_KEY`.
- La prueba de salud hace `GET /v1/{merchantId}` con autenticación básica (private key).

## 6. SMTP

- Host `mail.institutodoceo.com`, puerto **465**, SSL, auth.
- En Salud del sistema usa el botón **Probar SMTP** (envía un correo real a `SMTP_FROM`).

## 7. Verificación

1. Abre https://pdv.institutodoceo.com/
2. Entra en `/login`
3. Ve a `/admin/salud`
4. Semáforos verdes: MariaDB, Moodle, OpenPay, Storage
5. Probar SMTP aparte

## Reset de contraseña admin (importante)

La columna `password_hash` **no** acepta la contraseña en texto plano. Si la editaste en phpMyAdmin como texto normal, el login fallará.

Para regenerar el hash correctamente:

1. En el `.env` del servidor pon (**comillas dobles** si hay `*` `-` `#` `!`):
   - `ADMIN_EMAIL=admin@institutodoceo.com`
   - `ADMIN_PASSWORD="TuNuevaClaveSegura"`
   - `ADMIN_RESET_PASSWORD=true`
2. Abre `/login` (verás el correo y la **longitud** de la clave leída del `.env`).
3. Entra con **ese mismo correo** y **la misma clave**.
4. **Vuelve a poner** `ADMIN_RESET_PASSWORD=false` en el `.env`.

Si falla, el error ahora dice si la clave del formulario no coincide con la del `.env` (compara longitudes) o si usaste otro correo.

## Problemas frecuentes

| Síntoma | Qué revisar |
|---|---|
| “No se encontró .env” | Crear `.env` en la raíz del proyecto (junto a `src/`) |
| Login: error de tabla | Importar `schema.sql` |
| Contraseña incorrecta tras editar en phpMyAdmin | Usar `ADMIN_RESET_PASSWORD=true` (ver arriba) |
| Moodle access exception | Función no agregada al servicio externo |
| OpenPay 1002 / invalid key | `OPENPAY_PRIVATE_KEY` incorrecta o aún placeholder |
| SMTP auth fail | Contraseña del correo o puerto/encryption |
| CSS no carga | Document Root / rewrite de `assets/` |

## 8. Catálogo Teacher Referral (Fase 1)

1. Entra como admin → `/admin`.
2. **Proveedores:** crea cada casa certificadora con contacto, logo, convenio PDF (versiones) y prueba de autorización opcional (enlace o documento). Agrega ahí solo el **nombre** de cada certificación.
3. Completa el detalle de cada certificación en **Certificaciones**.
4. Crea o edita el **convenio anual TR** (niveles partner) y asigna precios.
5. En **Partners**, asigna un usuario a nivel + convenio.
6. Ese usuario entra a `/partner`.

Si ya tenías la BD creada, importa también (en orden, ignora “Duplicate column”):
- `sql/migration_providers_enrich_columns.sql`
- `sql/migration_providers_enrich.sql`
- `sql/migration_providers_v2.sql` (logos duales, contactos, sedes, notas)

Archivos se guardan en `storage/uploads/` y se sirven por `/media?f=…`.

