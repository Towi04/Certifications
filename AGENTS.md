# AGENTS.md

## Cursor Cloud specific instructions

PDV Certificaciones is a lightweight custom-MVC **PHP 8 + MariaDB** web app (public catalog/store, `/admin` panel, `/partner` and `/alumno` portals). There is **no dependency manager** (no Composer/npm), no build step, and no test suite in the repo. See `README.md` and `docs/setup.md` for the full (Neubox/cPanel) setup; the notes below cover only what is non-obvious for running it locally in this VM.

### Services

| Service | Required | How to run |
|---|---|---|
| MariaDB (`insti241_pdv`) | Yes | `sudo service mariadb start` (not started automatically on boot) |
| PHP dev web server | Yes | see command below |
| Moodle / OpenPay / SMTP | No | External SaaS; only exercised by `/admin/salud` and email flows. Left unconfigured (blank tokens in `.env`). |

### Running the app (dev)

The PHP built-in server does **not** apply `public/.htaccess`, so a small router script is required to route non-file requests through `public/index.php` while still serving static assets. A persistent router lives at `/home/ubuntu/pdv_router.php`. Start the server (bind `0.0.0.0` so the Desktop browser can reach it):

```bash
sudo service mariadb start
php -S 0.0.0.0:8000 -t /workspace/public /home/ubuntu/pdv_router.php
```

Then open `http://localhost:8000/`. If `/home/ubuntu/pdv_router.php` is ever missing, recreate it:

```php
<?php
$docroot = $_SERVER['DOCUMENT_ROOT'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($docroot . $path)) { return false; }
require $docroot . '/index.php';
```

### Environment / secrets

- Config is read from a git-ignored `.env` at the repo root (loaded by `src/Config/Env.php`). It is **not** in the repo; it persists via the VM snapshot. If absent, `cp .env.example .env` and set `DB_*`. Local DB credentials: `DB_HOST=127.0.0.1`, `DB_NAME=insti241_pdv`, `DB_USER=insti241_manager`, `DB_PASS=localdevpass`.
- `APP_DEBUG=true` is set locally so exceptions render in the browser (otherwise errors only go to `storage/logs/php-error.log`).
- The first admin user is auto-created on the first `/login` from `ADMIN_EMAIL`/`ADMIN_USERNAME`/`ADMIN_PASSWORD` when the `users` table is empty. Local login: user `admin` / password `CambiarYa123!`.

### Database

Schema/seed/migrations are plain `.sql` files in `sql/` (no migration runner). They were imported once into `insti241_pdv` and persist in the snapshot. To re-import into a fresh DB, run `sql/schema.sql` then `sql/seed.sql`, then the `migration_*.sql` files (order in `docs/setup.md`) and `sql/seed_protocol_elet.sql`, using `mysql ... --force` (the `migration_*` files intentionally re-add columns already present in `schema.sql`, so "Duplicate column" errors are expected and safe to ignore on a fresh DB).

### Gotchas

- `storage/uploads`, `storage/logs`, `storage/sessions` must be writable by the web-server user (they are owned by `ubuntu`, which runs the dev server).
- `public/debug_login.php`, `public/fix_login_once.php`, `public/set_admin_password.php` are throwaway debug helpers (git-ignored) — do not rely on or deploy them.
