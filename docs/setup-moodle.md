# Moodle — campus.institutodoceo.com

## Endpoint

`https://campus.institutodoceo.com/webservice/rest/server.php`

## Servicio externo

- Activar REST.
- Autorizar usuario del token.
- Funciones requeridas por el PDV:
  - `core_course_get_courses` (salud)
  - `core_user_get_users_by_field`
  - `core_user_create_users`
  - `enrol_manual_enrol_users`

Asignar capacidades al usuario del token (`webservice/rest:use`, crear usuarios, enrol manual, ver cursos).

## Alta automática tras el pago

Cuando OpenPay confirma el pago (o el admin usa “Confirmar pago”):

1. Busca cursos ligados a la certificación (`certification_courses`) con `platform_type=moodle` y `moodle_course_id`.
2. Si el alumno no tiene usuario Moodle (por e-mail), lo crea y guarda `moodle_user` / `moodle_password` en el caso.
3. Si ya existe, solo lo matricula en el/los cursos.
4. Si existe la plantilla `moodle_acceso`, envía el correo con las credenciales.

También puedes forzar la sync desde el caso: **Sincronizar Moodle**.

Migración de plantilla: `sql/migration_mail_moodle_acceso.sql`.

## Seguridad

- El token vive solo en `.env` (`MOODLE_TOKEN`), nunca en Git.
- Retirar `test_moodle.php` del servidor tras validar `/admin/salud`.
