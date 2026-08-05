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
