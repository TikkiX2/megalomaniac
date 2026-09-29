# Diseño — Módulo People

> Spec de diseño del módulo **People** de Megalomaniac Pro.
> Estado: aprobado en diseño; revisión 2 verificada contra el repo (2026-09-29).
> Commit sugerido: `docs(people): add people module design spec`
>
> **Revisión 2 — correcciones tras verificar el código real:**
> - Sin aprobación MCP: ninguna tool MCP del repo tiene gate; `ApprovalRequest` es solo de Integraciones. People escribirá por el servicio compartido, igual que el resto (la aprobación vive en las action tools del chat).
> - No se promete auditoría en "Actividad" (MCP no audita; esa página es de integraciones).
> - Enums PHP en `app/People/Enums/` (no existe `app/Enums/`); columnas `enum` en BD + cast.
> - Avatar vía medialibrary, colección `avatar` (`singleFile`); sin columna `avatar_path`.
> - `clients.person_id` no existe: se agrega por migración.
> - `person_socials.network` es string libre, no enum: el propio argumento de la spec ("sumar una red debe ser un INSERT, no una migración") pide no fijar valores.
> - `person_field_values` usa `person_id` (el polimorfismo llega con Salud, YAGNI); `taggables` sí es polimórfico desde el inicio.
> - Superficie chat: el asistente usa `app/Ai/Tools` + `ToolCatalog` (grupos por dominio con servicios compartidos, ver `docs/modules/tools.md`). Se agrega grupo `people` (`PeopleQueryTool` + `PeopleActionTool` aprobable).
> - Fase 1 implementable = F1–F6 (núcleo). Fases 2–4 tendrán su propio plan.

## 1. Alcance y nombre

People es el cuarto pilar de la app: la agenda social del usuario — personas, vínculos,
fechas clave, interacciones, compromisos y regalos.

**El módulo se llama `People`, no `Personas` ni `Personal`.** El nombre `Personal` ya existe
en el repo (`app/Http/Controllers/Personal/`, `Api/V1/PersonalTaskController`,
`Api/V1/PersonalProjectController`, `PersonalTaskReadTool`, `PersonalProjectReadTool`) y un
módulo de personas en español produce rutas y tools indistinguibles de las de tareas
personales.

Ubicación, siguiendo las convenciones existentes:

```
app/Http/Controllers/People/          # web (Inertia)
app/Http/Controllers/Api/V1/Person*.php
app/Models/Person.php, PersonInteraction.php, ...
app/Services/People/PeopleService.php # escritura compartida web/API/MCP/chat
app/People/Enums/                     # enums PHP del dominio
routes/people.php                     # route file por dominio, como routes/agents.php
resources/js/pages/people/
resources/js/layouts/people-layout.tsx
app/Mcp/Tools/People{Read,Write,Log}Tool.php
app/Ai/Tools/People{Query,Action}Tool.php
```

### Límite con Freelance (`Client`)

`Client` (cliente de trabajo: proyecto, pago, cotización) y `Person` (vínculo personal) **no se
fusionan ni se sincronizan**. La misma persona puede ser ambas cosas y se vinculan con una
referencia suave opcional (`clients.person_id`, nullable), nunca duplicando datos ni con un
observer que los mantenga espejados.

### Fuera de alcance (YAGNI)

- Import/export de contactos (vCard/CSV).
- Sincronización con agenda del sistema operativo.
- Detección automática de duplicados.

## 2. Modelo de datos

13 tablas. Nombres en inglés, siguiendo `Gym`, `Nutrition`, `Grocery`, `Finance`.

### `people`

```
id, user_id
first_name, last_name
nickname                  # cómo le dice realmente
birthday (date, nullable)
email, phone, whatsapp
address, city, country
company, job_title, website
how_we_met (text)
closeness (enum)          # inner_circle | close | friend | acquaintance
relationship_status (enum, nullable)
preferred_contact_channel (enum, nullable)
is_favorite (bool)
is_archived (bool)
last_contacted_at (timestamp)   # desnormalizado
notes (text)
```

`closeness` es el eje que decide a quién recordarle al usuario y a quién no molestarlo.
`last_contacted_at` está desnormalizado de forma deliberada (evita un `MAX(occurred_at)` en
cada listado) y se mantiene con un observer (`#[ObservedBy]`, patrón del repo), no con un
provider.

El avatar no es columna: `Person implements HasMedia`, colección `avatar` con `singleFile()`.
El modelo expone `avatar_url` (append) desde medialibrary.

### `person_relations` (Fase 2)

```
id, user_id
person_id, related_person_id
type (enum)   # partner | parent | child | sibling | friend | roommate | colleague | ex | other
```

**Una fila por relación, no dos.** La inversa se deriva (`partner` es simétrico; `parent` se lee
como `child` desde el otro lado). Con dos filas simétricas cualquier edición puede dejar el par
desincronizado.

### `person_key_dates`

```
id, user_id, person_id
type (enum)   # birthday | anniversary | graduation | memorial | custom
label, date
remind_days_before (tinyint, default 7)
is_recurring_annually (bool)
```

`remind_days_before` es una propiedad de la fecha, no un recordatorio independiente.

### `person_interactions`

```
id, user_id, person_id
channel (enum)   # in_person | call | video | message | email | other
occurred_at (timestamp)
title
notes (text)
```

Al guardar una interacción se actualiza `people.last_contacted_at` (observer `saved`/`deleted`
recalcula el `MAX(occurred_at)`).

### `social_commitments` (Fase 3)

```
id, user_id
person_id (nullable)
person_group_id (nullable)
title, description
starts_at, ends_at, location
rsvp (enum)   # pending | going | maybe | declined
notes
```

`person_id` es nullable: un asado al que lo invitaron antes de saber quién va es un compromiso
válido sin persona asociada. `person_group_id` permite invitar a un grupo entero.

### `person_gift_ideas` (Fase 3)

```
id, user_id
person_id (nullable)
title, description, url
price_estimate (decimal, nullable), currency
occasion (enum, nullable)   # birthday | anniversary | holiday | just_because | custom
status (enum)               # idea | bought | given | discarded
is_surprise (bool)
purchased_at (date, nullable)
```

### `tags` y `taggables` (Fase 3)

```
tags:      id, user_id, name, slug, color
taggables: tag_id, taggable_type, taggable_id
```

Pivote **polimórfico**: una misma etiqueta sirve para una persona y para un compromiso. Con un
`person_tag` quedarían dos sistemas de etiquetas que no se hablan.

### `person_groups` y `person_person_group` (Fase 3)

```
person_groups:        id, user_id, name, description, color, cover_path
person_person_group:  person_id, person_group_id, role (nullable)
```

### `person_socials`

```
id, person_id, network (string), handle, url
```

Tabla y no columnas fijas, y `network` como **string libre**, no enum: la lista de redes
envejece y sumar una nueva no debe requerir ni migración ni siquiera un INSERT — el usuario
escribe la red que usa.

### `person_field_definitions` y `person_field_values` (Fase 4)

```
person_field_definitions:
  id, user_id, key, label
  type          # text | textarea | number | date | boolean | select | multiselect
  options (json, para select/multiselect)
  is_required, is_active, sort_order

person_field_values:
  id, person_field_definition_id, person_id
  value (json)
```

`value` es **json** y no una columna por tipo: con `value_text`, `value_number`, `value_date`...
se tienen siete columnas casi siempre nulas y queries con `COALESCE`. Límite aceptado: no se
indexa ni ordena por valor, y los campos personalizados se usan para mostrar y filtrar, no para
ordenar listados.

`taggables` es polimórfico desde el inicio para que el módulo Salud reuse el sistema sin
migración. `person_field_values` queda con `person_id` en Fase 4; se polimorfiza cuando Salud
lo necesite (una migración barata, YAGNI hoy).

### Distinción tags vs. grupos

| | Para qué sirve | Ejemplo |
|---|---|---|
| **Tags** | Filtrar y clasificar | `facultad`, `vecino` |
| **Groups** | Un conjunto sobre el que se actúa en bloque | Invitar a todo "Familia" |

Un tag no puede recibir una invitación; un grupo sí.

## 3. Superficies: API v1, MCP y chat

Los módulos nuevos **no crean superficies nuevas**: se suman al server MCP único
(`app/Mcp/Servers/MegalomaniacServer.php`), al grupo API v1 de `routes/api.php` con Sanctum y
al catálogo del chat (`ToolCatalog`).

**Escritura compartida:** web, API, MCP y chat escriben por `App\Services\People\PeopleService`
(convención vigente en `docs/modules/tools.md`), así las reglas de ownership y de dominio viven
en un solo lugar.

### API v1

Controllers planos en `app/Http/Controllers/Api/V1/` con Form Requests en
`app/Http/Requests/Api/` y API Resources en `app/Http/Resources/` (directorio plano, sin V1),
como el resto.

```
GET|POST|PATCH|DELETE   /api/v1/people
GET                     /api/v1/people/{person}      # incluye key dates y socials
GET|POST|PATCH|DELETE   /api/v1/people/{person}/interactions
GET|POST|PATCH|DELETE   /api/v1/people/{person}/key-dates
GET|POST|PATCH|DELETE   /api/v1/people/{person}/socials
GET                     /api/v1/people/upcoming      # próximas fechas clave
```

Los sub-recursos anidados llevan `person_id` en la ruta porque no tienen sentido sin la persona.

### Tools MCP

Tres tools nuevas en el server existente, siguiendo el patrón Read/Write/Log del repo
(sin gate de aprobación — paridad con el resto: `PersonalTaskWriteTool` y compañía escriben
directo, y el ownership se valida en el servicio):

| Tool | Alcance | Aprobación |
|---|---|---|
| `PeopleReadTool` | Expediente social: búsqueda y filtros (cercanía, favoritos, sin contacto hace N días), detalle de persona, próximas fechas clave. `#[IsReadOnly]` | Libre (sin aprobación) |
| `PeopleWriteTool` | Crear/editar/borrar persona, fechas clave y redes sociales | Sin gate (paridad) |
| `PeopleLogTool` | **Solo alta rápida de interacción.** No edita nada existente | Sin gate (paridad) |

`PeopleLogTool` no es "una tool sin aprobación": es una tool de alta rápida con alcance acotado
—solo crea eventos, nunca modifica— y por eso existe separada de `PeopleWriteTool`.

### Chat IA

El asistente del chat **no usa las tools MCP**: usa `app/Ai/Tools` con grupos de
`ToolCatalog`. Se agrega el grupo `people`:

| Tool | Alcance | Aprobación |
|---|---|---|
| `PeopleQueryTool` | Buscar personas, detalle, próximas fechas clave y vínculos que se enfrían | Libre |
| `PeopleActionTool` | Crear/editar persona, registrar interacción, agregar fecha clave | `Approvable` (tarjeta de aprobación del chat) |

### Política de escritura: diferencia deliberada con Salud

En People la escritura del agente es amplia; en Salud estará recortada a eventos autogenerados
(síntomas, peso, tomas de medicación), quedando estudios, diagnósticos y medicación solo por UI
web. No es incoherencia: un contacto mal cargado se corrige en diez segundos; un estudio clínico
mal cargado contamina el expediente.

## 4. Features

15 features + el constructor de campos = **16 slots**.

### Fase 1 — Núcleo de contactos (6)

| # | Feature | Qué resuelve |
|---|---|---|
| F1 | Ficha de persona | Todo el perfil en una pantalla: datos, redes, fechas clave |
| F2 | Listado, búsqueda y filtros | Por cercanía, favoritos, archivados, "sin contacto hace N"; búsqueda por nombre/alias |
| F3 | Timeline de interacciones | Historial global y por persona, agrupado por mes |
| F4 | Alta rápida de interacción | Registrar "hablamos hoy" en dos toques (`PeopleLogTool`) |
| F5 | Fechas clave | Alta y edición de cumpleaños, aniversarios y fechas propias |
| F6 | Calendario de fechas clave | Vista mensual con todas las fechas clave |

### Fase 2 — Relación (5)

| # | Feature | Qué resuelve |
|---|---|---|
| F7 | Vínculos entre personas | Quién es pareja/hermano/madre de quién, desde una sola fila |
| F8 | Mapa de vínculos | Vista de constelación: círculos y cómo se conectan |
| F9 | Recordatorios anticipados | Aviso `remind_days_before` días antes de cada fecha |
| F10 | Nudges de "sin contacto hace X" | Cola de vínculos que se enfrían, ponderada por `closeness` |
| F11 | Panel de círculos | Distribución inner/close/friend/acquaintance y su evolución |

### Fase 3 — Vida social (4)

| # | Feature | Qué resuelve |
|---|---|---|
| F12 | Compromisos y RSVP | Próximos eventos, pendientes de confirmar, con lugar y notas |
| F13 | Grupos | CRUD + invitar a un grupo entero a un compromiso |
| F14 | Etiquetas | CRUD con color, filtrar por tag en todo el módulo |
| F15 | Banco de ideas de regalo | Ideas por persona y ocasión, con estado idea → comprado → entregado |

### Fase 4 — Modelado propio (1)

| # | Feature | Qué resuelve |
|---|---|---|
| F16 | Constructor de campos personalizados | Definir campos propios (texto, número, fecha, selección...), con validación y render dinámico |

### Notas de planificación

- **F16 va última y es la más pesada.** No es "un CRUD más": es UI de definición, validación
  dinámica y render dinámico del formulario de persona. Está última para que no bloquee las otras 15.
- **Puente con Freelance:** `clients.person_id` + selector en la ficha de cliente. Se implementa
  como apéndice de F1, no como feature propia.
- **F8/F11 sin dependencias nuevas por ahora:** el repo no tiene librería de grafos ni charts;
  agregar una requiere aprobación. La Fase 2 decidirá SVG/CSS propio vs. dependencia.

## 5. Testing

Pest 4, siguiendo las convenciones del repo (`php artisan test --compact`), con factories y
seeders para cada modelo nuevo, como exige `AGENTS.md`:

- Feature tests por controller web, por controller de Api/V1 y por tool MCP
  (`tests/Feature/Mcp/PeopleToolsTest.php`, patrón `MegalomaniacServer::actingAs`).
- Tests de observer: guardar una interacción actualiza `last_contacted_at`; borrar la última lo
  recalcula.
- Tests de servicio: ownership (otro usuario → "not found"), ventanas de próximas fechas.
- Tests de tools del chat: `PeopleQueryToolTest` y `PeopleActionToolTest` (aprobación + ejecución).
- Tests de campos personalizados (Fase 4): validación dinámica por tipo.
- Test de política MCP: `PeopleLogTool` no expone edición de entidades existentes.

## 6. Riesgos

| Riesgo | Mitigación |
|---|---|
| 13 tablas + 16 features es grande para un plan | Fases ejecutables de a tandas; Fase 1 (F1–F6) primero |
| F16 (constructor de campos) se come la fase 4 | Va última; las 15 features no dependen de ella |
| Colisión de nombres con `Personal` | Prefijo `Person*`, route file propio `people.php`, grupo de chat `people` |
| Etiquetas y grupos solapados | Contrato explícito en sección 2: tags filtran, groups actúan |
| `Client` ↔ `Person` duplicando datos | Referencia suave (`clients.person_id`), sin observer de espejo |
| ToolCatalog/config/chat-tools compartidos con el trabajo de Gym en curso | Coordinar merges; reaplicar ediciones sobre el estado actual antes de commitear |

## 7. Próximo paso

Plan de implementación de **Fase 1 — Núcleo de contactos**: ver
`docs/superpowers/plans/2026-09-29-people-module-core.md`. El módulo **Salud** tiene su propia
spec y plan, y se aborda después, reusando el patrón probado acá.
