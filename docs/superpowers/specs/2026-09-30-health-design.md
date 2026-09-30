# Diseño — Módulo Salud

> Spec de diseño del módulo **Salud** de Megalomaniac Pro.
> Estado: aprobado en diseño (2026-09-30). Plan de Fase 1: `docs/superpowers/plans/2026-09-30-health-module-core.md`.
> Commit sugerido: `docs(health): add health module design spec`

## 1. Alcance

Salud es el quinto pilar de la app: el **expediente médico personal y familiar** — condiciones
y diagnósticos, medicación y tomas, mediciones y signos vitales, síntomas, profesionales,
estudios con resultados y archivos, consultas, y chats de salud categorizados.

Decisiones de alcance aprobadas:

1. **Expediente completo**, no un MVP.
2. **Titular + familiares**: todo cuelga del usuario dueño; cada registro puede vincularse
   opcionalmente a una `Person` (referencia suave `person_id` nullable, `nullOnDelete`), igual
   que `clients.person_id`. Nada de pacientes como entidad principal.
3. **El agente maneja todo el módulo**: chat (con tarjeta de aprobación por acción) y MCP
   (`health-write`, sin gate — paridad del repo) cubren condiciones, medicación, tomas,
   mediciones, síntomas, profesionales, estudios/resultados y consultas. La API v1 permite todo.
4. **Chats de salud categorizados**: hilos con `category` + vínculo al contexto.
5. **Estudios con archivos**: medialibrary + páginas-imagen de PDFs vinculadas.
6. **Importación de PDFs** con texto indexado y rasterizado para visión del modelo.
7. **Fase 4 completa**: recordatorios de tomas + adherencia, catálogo de síntomas + episodios,
   red flags y cruces con Gym.

Ubicación, siguiendo el blueprint probado en People:

```
app/Http/Controllers/Health/          # web (Inertia)
app/Http/Controllers/Api/V1/Health*   # API v1
app/Models/Health*.php
app/Health/Enums/                     # enums PHP del dominio
app/Services/Health/HealthService.php # escritura compartida web/API/MCP/chat
routes/health.php                     # route file por dominio
resources/js/pages/health/
resources/js/layouts/health-layout.tsx
app/Mcp/Tools/Health{Read,Write,Log}Tool.php
app/Ai/Tools/Health{Query,Action}Tool.php
```

### Fuera de alcance (YAGNI)

- Cifrado de datos médicos en reposo.
- Integración con wearables / Apple Health / Google Fit.
- Seguros, facturación y reintegros.
- Diagnósticos o recomendaciones clínicas generadas por IA: el agente **registra y consulta,
  nunca diagnostica** (instrucción explícita en las tools).

## 2. Modelo de datos

### Fase 1 (6 tablas + hilos)

Todas con `user_id`, `person_id` nullable (`constrained('people')->nullOnDelete()`) e índices
`user_id + person_id`.

#### `health_conditions`

```
id, user_id, person_id
kind (enum)        # condition | diagnosis | allergy | surgery | family_history
name
status (enum)      # suspected | active | resolved | in_remission
severity (enum, nullable)   # mild | moderate | severe
diagnosed_at (date, nullable)
provider_id (nullable, health_professionals)
notes (text)
```

La miopatía "en estudio" es `status=suspected`; el hipotiroidismo, `active`.

#### `health_medications`

```
id, user_id, person_id
name, dose_amount (decimal, nullable), dose_unit (string, nullable), route (string, nullable)
frequency_text (string, nullable)     # "cada 24 h", "1-0-0"
started_at (date, nullable), ended_at (date, nullable)
is_active (bool, default true)
condition_id (nullable), prescriber_id (nullable, health_professionals)
notes
```

#### `health_medication_intakes`

```
id, user_id, medication_id
taken_at (timestamp)
status (enum)   # taken | skipped
notes
```

Registro manual simple en Fase 1 (sin horarios). La Fase 4 agrega `health_medication_schedules`
y calcula adherencia sobre estas tomas.

#### `health_measurements`

```
id, user_id, person_id
type (enum)     # weight | blood_pressure | heart_rate | glucose | temperature | oxygen_saturation | waist
value (decimal)
secondary_value (decimal, nullable)   # diastólica para blood_pressure
unit (string)
measured_at (timestamp)
notes
```

Al registrar una medición `weight` se actualiza `users.weight` (sync unidireccional en el
servicio, dentro de la misma transacción; al editar/borrar, se recalcula al último peso
restante). Sin observers espejo.

#### `health_symptoms`

```
id, user_id, person_id
symptom (string)           # "calambre", "orina oscura", ...
severity (enum)            # mild | moderate | severe
occurred_at (timestamp)
notes
```

La Fase 4 agrega catálogo (`health_symptom_catalog`) y episodios (`health_symptom_episodes`),
más una columna nullable `catalog_id` en esta tabla.

#### `health_professionals`

```
id, user_id
type (enum)     # professional | center
name, specialty (nullable), phone (nullable), email (nullable), address (nullable)
notes
is_active (bool, default true)
```

### Fase 2 (estudios)

#### `health_studies`

```
id, user_id, person_id
type (enum)       # lab | imaging | report | other
title
performed_at (date, nullable)
provider_id (nullable), condition_id (nullable)
notes
```

+ medialibrary: colección `attachments` (PDFs, fotos, informes) y colección `pages`
(páginas-imagen rasterizadas de PDFs, `singleFile` por página no aplica: varias imágenes).

#### `health_study_results`

```
id, study_id
analyte (string), value (string), unit (string, nullable)
reference_range (string, nullable)
flag (enum, nullable)   # low | normal | high | unknown
sort_order (int), notes (nullable)
```

La evolución por analito (TSH, CK...) se grafica con SVG propio — sin librerías nuevas.

### Fase 3 (consultas)

#### `health_appointments`

```
id, user_id, person_id
provider_id (nullable)
title
scheduled_at (datetime)
status (enum)   # scheduled | completed | cancelled | no_show
notes
```

+ medialibrary `attachments`.

### Fase 4 (vigilancia)

```
health_medication_schedules: id, user_id, medication_id, time (time), days (json),
                             starts_at/ends_at (date, nullable), is_active (bool)
health_symptom_catalog:      id, user_id, name, severity_default (enum, nullable), notes
health_symptom_episodes:     id, user_id, person_id, catalog_id (nullable), started_at,
                             ended_at (nullable), severity (enum), notes
health_symptoms.catalog_id   (migración nullable)
```

### Chats de salud (infra de chat, aditiva)

```
agent_conversations (chat_threads):
  category (string 30, default 'general', index)      # general | salud
  context_type / context_id (nullable morph, index)   # health_condition | health_study | person
```

Los hilos de salud llevan `tools_policy = {mode: 'manual', groups: ['health']}` fijo.
El rail global badgea los de salud y `/health/chats` los lista filtrados por categoría y vínculo.

## 3. Superficies

### Web (`/health/*`, `routes/health.php`, middleware `auth,verified`)

- **Panel** (`health.dashboard`): medicación activa, últimas mediciones, síntomas recientes,
  condiciones activas y accesos rápidos.
- **Condiciones**, **Medicación** (+ tomas), **Mediciones**, **Síntomas**, **Profesionales**,
  **Chats de salud**.
- Páginas PascalCase (`Index.tsx`, `Form.tsx`) como freelance/personal. Layout
  `health-layout.tsx` envolviendo `main-layout`. Sidebar: grupo "Salud".
- Autorización: policies por modelo (auto-discovery), patrón People.
- Sync de peso visible: aviso "Actualizó tu peso de perfil" al guardar.

### API v1 (`/api/v1/health/*`)

Controllers en `Api/V1/`, Form Requests en `app/Http/Requests/Api/`, Resources planos.
Nombres de ruta con prefijo `api.` (lección de la colisión People) y `whereNumber` donde aplique.
CRUD completo + endpoints de eventos (`POST health/measurements`, `POST health/symptoms`,
`POST health/medications/{medication}/intakes`).

### MCP

| Tool | Alcance |
|---|---|
| `health-read` | Todo el expediente, con `resource` (conditions, medications, intakes, measurements, symptoms, professionals, summary) y filtros (person_id, active, type, days) |
| `health-write` | Escritura completa: condiciones, medicación, tomas, mediciones, síntomas, profesionales (y estudios/consultas en sus fases) |
| `health-log` | Alta rápida de eventos: medición, síntoma, toma. Nunca edita nada existente |

Sin gate de aprobación (paridad repo). Dominio errores como `Health<Model> not found.`

### Chat IA

| Tool | Alcance | Aprobación |
|---|---|---|
| `HealthQueryTool` | Consultar expediente: condiciones activas, medicación, últimas mediciones, síntomas, evolución, próximos estudios/consultas | Libre |
| `HealthActionTool` | **Todas** las escrituras del módulo (condiciones, medicación, tomas, mediciones, síntomas, profesionales, estudios, consultas) | `Approvable` con etiqueta por acción |

Grupo `health` en `ToolCatalog`, keywords en `config/ai_tools.php`, labels en `chat-tools.ts`,
instrucción "no diagnosticar" en la descripción de la tool.

## 4. Importación de PDFs (Fase 2)

1. **Dependencias (aprobadas)**: `smalot/pdfparser` (composer) + `poppler-utils` (`pdftoppm`)
   en la imagen Docker (`docker/Dockerfile.prod`). Rebuild vía `deploy.sh`.
2. **Indexado de texto**: si el PDF tiene capa de texto, se extrae y se indexa en el pipeline
   de documentos existente (`ChatAttachment` + `ChatDocumentChunk` + FTS) para preguntarle al chat.
3. **Rasterizado + visión**: cada página se convierte a imagen optimizada (JPEG, downscale,
   cap 50 páginas por importación) y el modelo multimodal propone `health_study` + grilla de
   resultados (`analyte/value/unit/reference_range/flag`).
4. **Revisión humana**: pantalla de importación con la propuesta editable; recién al confirmar
   se crean estudio + resultados + adjuntos. La IA nunca guarda directo.
5. **Límite de imágenes por mensaje**: 5 → **50**, con optimización agresiva y tope total de
   payload validado server-side.
6. Escaneados sin OCR: los lee el modelo como imágenes. Tesseract queda como mejora futura.

## 5. Testing

Pest 4, patrón People:

- Datos/enums/casts + factories y seeders por modelo (AGENTS.md).
- `HealthService`: ownership, sync de peso (create/update/delete recalculando), eventos.
- Web: flows por controller, 403 ajenos, componente Inertia y props.
- API v1: CRUD, validación 422, scoping, 201.
- MCP: `health-read/write/log`, scoping, y **test explícito de que `health-log` no edita**.
- Chat: query/action, aprobación (`Approval->reason`), ejecución de escrituras.
- Chats categorizados: creación con `category=salud`, `tools_policy` fijo, contexto morph,
  aislamiento por usuario, `/health/chats` solo lista salud.
- Sync de imágenes (F2): cap 50 y políticas de payload.

## 6. Riesgos

| Riesgo | Mitigación |
|---|---|
| Datos médicos sensibles sin cifrado ni auditoría MCP/chat | Fuera de alcance declarado; aprobación en chat como mitigación; API con Sanctum |
| Chat es infra compartida (otra sesión activa) | Migración aditiva nullable; cambios a rail/chat.tsx mínimos y secuenciales |
| Escritura total del agente puede cargar mal un dato clínico | Tarjeta de aprobación en chat con etiqueta por acción; edición inmediata en UI |
| Rasterizado pesado (PDFs grandes) | Cap de 50 páginas, downscale, JPEG, tope de payload; job en cola |
| Dependencias nuevas | Aprobadas: smalot + poppler; rebuild de imagen documentado |
| Volumen del módulo ("súper completo") | 4 fases ejecutables; F1 es el núcleo diario |

## 7. Próximo paso

Plan de Fase 1 (`2026-09-30-health-module-core.md`) y ejecución SDD. Las fases 2–4 tendrán su
propio spec complementario/plan cuando F1 esté cerrada.
