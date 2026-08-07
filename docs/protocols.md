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

Seed: `sql/seed_protocol_elet.sql` — código `UKS_ELET` con 19 pasos (reglamento → registro → OpenPay → pago → correos UKS → CSV → ID/clave → aplicación → constancia → CENNI/SEP).

## Cómo usarlo en admin

1. Ejecuta en MariaDB:
   - `sql/migration_protocol_steps.sql`
   - `sql/seed_protocol_elet.sql`
2. **Protocolos** → edita o crea → agrega pasos con fase y responsable.
3. En la ficha de la **certificación**, asigna el protocolo (ej. `UKS_ELET`).
4. **Casos** → Abrir caso (alumno + certificación) → ver timeline y marcar pasos hechos.

## Próximos pasos (producto)

- Portal del alumno con el mismo timeline y acciones por responsable.
- Automatizar pasos `system` (OpenPay) y recordatorios por `trigger_days_after_exam`.
- Adjuntos por paso (reglamento firmado, CSV, capturas).
