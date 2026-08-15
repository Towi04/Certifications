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

Admin: **Proveedores → Fechas de aplicación** para cargar las 3–4 fechas del proveedor. Si no hay fechas, el alumno puede adquirir y agendar después.

Protocolos obsoletos (`CAMBRIDGE_ONLINE_HOME`, `CAMBRIDGE_ONLINE_VENUE`, `CAMBRIDGE_PAPER`) se desactivan y las certs migran a los dos códigos anteriores.

## Cómo usarlo en admin

1. Ejecuta en MariaDB:
   - `sql/migration_protocol_steps.sql`
   - `sql/seed_protocol_elet.sql`
   - `sql/migration_tramites_sep_cambridge.sql` (Trámites SEP + fechas Cambridge)
2. **Protocolos** → edita o crea → agrega pasos con fase y responsable.
3. En la ficha de la **certificación**, asigna el protocolo (ej. `UKS_ELET`, `CAMBRIDGE_ONLINE` o `CAMBRIDGE_PRESENCIAL`) y la modalidad.
4. **Casos** → Abrir caso (alumno + certificación) → ver timeline y marcar pasos hechos.

## Próximos pasos (producto)

- Portal del alumno con el mismo timeline y acciones por responsable.
- Automatizar pasos `system` (OpenPay) y recordatorios por `trigger_days_after_exam`.
- Adjuntos por paso (reglamento firmado, CSV, capturas).
- Cambridge: subida de reglamento PDF + INE/pasaporte como gate antes de agendar; envío/paquetería y CENNI opcional en checkout.
- Flujo completo Red CONOCER (hoy flags + producto; falta protocolo operativo detallado).
