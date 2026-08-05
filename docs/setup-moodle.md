# Moodle — campus.institutodoceo.com

## Endpoint

`https://campus.institutodoceo.com/webservice/rest/server.php`

## Servicio externo

- Activar REST.
- Autorizar usuario del token.
- Función mínima actual: `core_course_get_courses` (usada por el panel de salud).

## Funciones futuras (enrol / usuarios)

Cuando se automatice la asignación de cursos:

- `core_user_create_users`
- `core_user_get_users_by_field`
- `enrol_manual_enrol_users`

Asignar capacidades al usuario del token (`webservice/rest:use`, create users, enrol manual, view courses).

## Seguridad

- El token vive solo en `.env` (`MOODLE_TOKEN`), nunca en Git.
- Retirar `test_moodle.php` del servidor tras validar `/admin/salud`.
