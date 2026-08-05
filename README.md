# PDV Certificaciones — Instituto Doceo

Sistema PHP + MariaDB para catálogo, Teacher Referral y automatización de certificaciones.

## Fase 0 (actual)

- App PHP ligera en Neubox
- `.env` / `.env.example` para secretos
- Panel **Salud del sistema**: MariaDB, Moodle, OpenPay sandbox, SMTP, storage
- Esquema SQL base (proveedores, protocolos, convenios anuales, cursos multiplataforma)

## Requisitos servidor

- PHP 8.1+ (8.2/8.3 recomendado) con extensiones: `pdo_mysql`, `curl`, `mbstring`, `openssl`
- MariaDB / MySQL
- `mod_rewrite` (Apache) o equivalente

## Instalación en Neubox

Ver [docs/setup.md](docs/setup.md).

Resumen:

1. Despliega el repo en el subdomain `pdv.institutodoceo.com` (webhook).
2. Copia `.env.example` → `.env` y completa contraseñas/tokens.
3. Importa `sql/schema.sql` y `sql/seed.sql` en phpMyAdmin (`insti241_pdv`).
4. Entra a `/login` con `ADMIN_USERNAME` o `ADMIN_EMAIL` y `ADMIN_PASSWORD` del `.env`.
   - Si usas `ADMIN_USERNAME=admin`, el usuario puede ser `admin`.
5. Abre `/admin/salud` y verifica semáforos.

## Estructura

```
public/          # front controller + assets (ideal Document Root)
src/             # PHP de la aplicación
views/           # plantillas
sql/             # schema + seed
storage/         # uploads y logs (no público)
docs/            # guías
.env.example     # plantilla de secretos
```

## Seguridad

- Nunca subas `.env` con secretos reales a Git (está en `.gitignore`).
- Borra `test_moodle.php` del servidor cuando ya uses el panel de salud.
- Cambia `ADMIN_PASSWORD` y `APP_KEY` en producción.
