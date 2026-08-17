# Protocolos — flujo de certificación

## Qué es un protocolo

Un **protocolo** no es solo un texto de procedimiento. Es el **flujo ordenado de pasos** que hay que seguir para presentar una certificación (o un curso), agrupados en:

1. **Pre-examen** — antes del día de aplicación  
2. **Durante el examen** — la aplicación  
3. **Post-examen** — resultados, CENNI, SEP, etc.

Cada paso tiene un **responsable**:

| Código | Quién |
|--------|--------|
| `student` | Alumno |
| `admin` | Administrador Doceo |
| `tr` | Teacher Referral |
| `student_or_tr` | Alumno o TR |
| `provider` | Certificadora (ej. UKS) |
| `sep` | SEP |
| `system` | Sistema (OpenPay, etc.) |

Las certificaciones y los cursos pueden tener un `protocol_id`. Al abrir un **caso**, el alumno queda en el paso 1 y el admin (luego el alumno) puede avanzar el flujo.

## Ejemplo: ELET (UKS)

Seed: `sql/seed_protocol_elet.sql` — código `UKS_ELET` (reglamento → registro → pago → solicitud UKS con CSV + reglamento + comprobante Doceo→UKS → folio/clave → examen → agradecimiento → monitoreo CENNI en UKS).

También se siembran:

| Código | Uso |
|--------|-----|
| `UKS_ELET` | Examen + monitoreo CENNI (alumno sube docs en UKS, 15 días) |
| `UKS_EXAM` | Mismo flujo hasta el examen; sin CENNI |
| `UKS_CENNI` | Producto aparte si venció el plazo de docs en UKS (Doceo gestiona) |

En la certificación ELET puedes vincular el producto CENNI tardío (`cenni_late_certification_id`).

## Trámites SEP (no es un proveedor de examen)

CENNI y Red CONOCER son trámites que **Doceo realiza ante la SEP**. A veces van incluidos en un examen; otras se cobran aparte como productos propios.

Se modelan como proveedor especial `TRAMITES_SEP` con `org_kind = tramites` (no certificadora):

| Código producto / protocolo | Uso |
|-----------------------------|-----|
| `SEP_CENNI` | Trámite CENNI independiente (estrella) |
| `SEP_CONOCER` | Trámite Red CONOCER independiente (estrella) |

Siguen existiendo los flags `cenni_*` / `conocer_*` en cada certificación de examen (incluido / fee / elegible). El producto bajo Trámites SEP cubre la venta standalone.

Migración / seed runtime: `sql/migration_tramites_sep_cambridge.sql` y `CatalogRepository::ensureCambridgeAndSepSchemaAndSeeds()`.

## Cambridge — modalidades y protocolos

| Protocolo | Uso |
|-----------|-----|
| `CAMBRIDGE_ONLINE` | Examen desde casa |
| `CAMBRIDGE_PRESENCIAL` | Examen en sede (digital o papel) |

| Modalidad | Agenda | Antelación típica |
|-----------|--------|-------------------|
| `online` (+ protocolo Online) | Fecha preferida lun–vie 9–18 (sin hora fija) | 10 días |
| `online_venue` | Fechas publicadas (`exam_sittings`) — sábados, digital en sede | ~2 semanas |
| `paper` | Fechas publicadas (`exam_sittings`) — sábados, papel | ~8 semanas |

La distinción digital vs papel es **modalidad**, no protocolo. Un examen “online” en computadora en sede usa modalidad `online_venue` + protocolo Presencial.

**Documentos obligatorios antes de agendar** (ambos protocolos Cambridge):
1. Firma digital del reglamento en la ficha del alumno.
2. Subida de **INE en PDF (ambos lados en un solo archivo)** o pasaporte PDF (`POST /alumno/caso/upload-id-doc`).
3. Luego el alumno agenda (`POST /alumno/caso/schedule`). En adquisición la fecha queda diferida (`schedule_deferred`).

Admin: **Proveedores → Fechas de aplicación** para cargar las 3–4 fechas del proveedor. Si no hay fechas, el alumno puede adquirir y agendar después.

Protocolos obsoletos (`CAMBRIDGE_ONLINE_HOME`, `CAMBRIDGE_ONLINE_VENUE`, `CAMBRIDGE_PAPER`) se desactivan y las certs migran a los dos códigos anteriores.

## Cursos vendibles (protocolos por plataforma)

Los cursos pueden venderse **solos** (sin certificación). Cada uno lleva un protocolo según plataforma:

| Protocolo | Plataforma | Tras el pago |
|-----------|------------|--------------|
| `COURSE_MOODLE` | Campus Doceo | Alta automática Moodle (`fulfill_after_payment`) |
| `COURSE_ETHINKING` | eThinking | Admin solicita/compra al proveedor (`request_provider`) y luego envía accesos |
| `COURSE_XPERIENCEED` | XperienceEd | Igual: solicitar al proveedor y enviar accesos al alumno |

Flujo alumno: `/curso?slug=…` → `/adquirir-curso` (formulario) → caso → pago → acceso.

En admin: **Cursos** → precio, publicado, plataforma y protocolo (auto si se deja vacío). Migración: `sql/migration_course_protocols.sql` + `ensureCourseCommerceAndProtocols()`.

## Campos flexibles (nuevos proveedores sin programar)

Para agregar una certificación de un proveedor nuevo **sin tocar código**:

1. **Proveedores → Campos**
   - Marca campos built-in (CURP, teléfono…).
   - Agrega campos personalizados: texto, URL o **archivo** (INE, reglamento, etc.).
   - Define **datos de acceso** que llenará el admin (folio, clave, links, archivos).
   - Usa **Aplicar a certificaciones** (todas o por grupo) para no repetir ficha por ficha.
2. **Certificaciones → Adquisición**
   - Elige off / opcional / obligatorio por campo.
   - Activa los slots de acceso que esa cert usa.
3. **Protocolos → Acciones**
   - Botón **Activar paquete estándar** (pago, solicitar proveedor, cumplir, enviar accesos).
4. **Correos**
   - Tokens de documentos: `{{Doc CODIGO URL}}` / `{{Doc CODIGO Boton}}`
   - Tokens de links: `{{Link CODIGO …}}`
   - Tokens de adjuntos del caso: `{{Adjunto custom_ine URL}}`
   - **Envío de prueba** en la plantilla (a tu correo, sin cambiar el contacto del proveedor).

Migración: `sql/migration_flexible_fields.sql` (+ `FlexibleFieldService::ensureAccessFieldsColumn()` en runtime).

## Cómo usarlo en admin

1. Ejecuta en MariaDB:
   - `sql/migration_protocol_steps.sql`
   - `sql/seed_protocol_elet.sql`
   - `sql/migration_tramites_sep_cambridge.sql` (Trámites SEP + fechas Cambridge)
   - `sql/migration_course_protocols.sql` (cursos vendibles)
2. **Protocolos** → edita o crea → agrega pasos con fase y responsable.
3. En la ficha de la **certificación**, asigna el protocolo (ej. `UKS_ELET`, `CAMBRIDGE_ONLINE` o `CAMBRIDGE_PRESENCIAL`) y la modalidad.
4. En **Cursos**, asigna protocolo Moodle/eThinking/XperienceEd, precio y “Publicado”.
5. **Casos** → Abrir caso (alumno + certificación o curso) → ver timeline y marcar pasos hechos.

## Próximos pasos (producto)

- Portal del alumno con el mismo timeline y acciones por responsable.
- Automatizar pasos `system` (OpenPay) y recordatorios por `trigger_days_after_exam`.
- Adjuntos por paso (reglamento firmado, CSV, capturas).
- Cambridge: envío/paquetería y CENNI opcional en checkout.
- Flujo completo Red CONOCER (hoy flags + producto; falta protocolo operativo detallado).
- Integraciones API eThinking / XperienceEd (hoy solicitud manual por correo).
