# Reparación de módulos y herramientas (chat IA + MCP) — Diseño

> Fecha: 2026-09-29 · Estado: aprobado · Alcance: chat IA, servidor MCP, módulo Proyectos (tipo/mover), aislamiento entre módulos y seguridad.

## Problema

Cinco síntomas reportados por el usuario:

1. **No hay forma de cambiar el tipo de proyecto.** `projects.type` (personal/freelance) es inmutable en la UI: `StorePersonalProjectRequest`/`UpdatePersonalProjectRequest` (`app/Http/Requests/Personal/`) no incluyen `type` y `$project->update($request->validated())` lo descarta; el módulo Freelance tampoco lo valida. La única vía es `PATCH /api/v1/freelance/projects/{id}`, no expuesta en ninguna pantalla.
2. **La IA crea mal el proyecto.** `ActionTool::createProject` (`app/Ai/Tools/ActionTool.php:96-98`) defaultea `personal` cuando el modelo omite `type` (schema opcional, `:448`); `FreelanceWriteTool` (`app/Mcp/Tools/FreelanceWriteTool.php:104`) defaultea `freelance`. No existe ninguna action `update_project` para corregirlo después.
3. **No hay forma de pasar de módulo.** No existe operación (web, chat ni MCP) para mover un proyecto entre Personal y Freelance; además los módulos no están aislados: el index/dashboard de Freelance no filtra `type=freelance` (`app/Http/Controllers/Freelance/ProjectController.php:21-32`, `FreelanceDashboardController.php:22-38`) y el listado de tareas personales incluye tareas freelance (`app/Http/Controllers/Personal/PersonalTaskController.php:22-37`). El campo "Módulo" del form freelance (`projects.module`) es una etiqueta de texto, no el tipo de módulo.
4. **Las tools del MCP y de los módulos están incompletas.** Los 4 tools MCP de Personal crashean siempre (`$request['...']` sobre `Laravel\Mcp\Request`, que no implementa ArrayAccess; `app/Mcp/Tools/Personal*Tool.php`); el resto de los write tools son create-only; falta el módulo Suplementos completo; hay divergencias de validación y efectos: macros de nutrición calculadas `quantity/100` en MCP vs `food->calorías * quantity` en web (`NutritionWriteTool.php:88-97`), FKs NOT NULL (`currency_id`, `income_source_id`) tratadas como opcionales, `status` escrito sin validar columna de tablero, `exists` sin scope de usuario.
5. **El chat dice "no puedo editar/escribir".** Tres causas independientes: (a) `MegalomaniacAgent::instructions()` hardcodea `ActionTool` (`app/Ai/Agents/MegalomaniacAgent.php:185-193`) aunque el `ToolRouter` no lo publique → el SDK responde "Tool 'ActionTool' does not exist" y el modelo se disculpa; (b) el picker "Auto" nunca se re-selecciona en el servidor: una vez persistida una policy manual, el router queda bypassado para siempre (`resources/js/pages/ai/thread.tsx:127-141`, `ChatService.php:285-299`); (c) `ActionTool` no tiene update/delete de proyectos, finanzas, grocery, meals ni suplementos, y las query tools de finance/grocery leen columnas inexistentes (`date`, `quantity`, `low_stock_threshold`) rompiendo el stream.

Además, seguridad adyacente detectada en el análisis: **IDOR** en `Freelance\ProjectController` (show/edit/update/destroy/upload/download sin chequeo de `user_id`, `:93-187`) y asignación de `project_id` ajeno o cross-type en `PersonalTaskController` (`:144-162`, `:192-198`).

## Objetivo

1. El chat IA y el MCP pueden **leer y escribir** en todos los módulos sin mentir ni crashear, con una sola fuente de verdad de la lógica de escritura.
2. Un proyecto puede **crearse con el tipo correcto** y **moverse Personal ↔ Freelance** desde UI, chat y MCP, con remapeo consistente del tablero.
3. Los módulos quedan **aislados por tipo** y las operaciones respetan ownership.
4. Los defectos de seguridad adyacentes quedan cerrados.

## Decisiones aprobadas

| # | Decisión |
|---|---|
| 1 | Arquitectura **A**: action tools modulares por módulo + **servicios de dominio compartidos** entre chat y MCP. Gym no cambia de arquitectura (sus tools ya usan `WorkoutSessionService`/`RoutineService`); en Fase 2 solo se completa su cobertura de acciones. |
| 2 | Alcance **total en fases**, incluidos los fixes de seguridad. |
| 3 | `type` editable desde UI (Form + acción "Mover a…" en Show), chat y MCP. `create_project` exige `type`: si el modelo lo omite, el schema lo obliga a preguntar (AskUserTool) en vez de asumir un default. |
| 4 | Aislamiento por tipo en índices/dashboards; se deja de crear el cliente "Personal" automático en proyectos personales. |
| 5 | Se **retira `ActionTool`** monolítico; sus tests migran a los action tools por módulo. El grupo `actions` pasa a ser alias de todas las action tools. |
| 6 | Cambios de datos históricos (cliente "Personal" huérfano) solo por **comando artisan manual**, nunca automático. |

## Arquitectura

### Servicios de dominio (fuente de verdad)

Patrón existente `app/Services/Gym/*`:

| Servicio | Responsabilidad |
|---|---|
| `Services/Projects/ProjectService` | create/update/archive/delete de proyectos con validación por tipo, ownership y provisión de defaults (cliente/currency) |
| `Services/Projects/ProjectTypeService` | `changeType()`: valida requisitos del destino, re-siembra columnas del tablero, remapea `status`/`is_done` de tareas, ajusta `client_id`; transaccional |
| `Services/Tasks/TaskService` | create/update/move/complete/delete de `ProjectTask` (personal, freelance y sueltas); sincroniza columna, `is_done` y `sort_order` vía `TaskBoardColumnService` |
| `Services/Finance/FinanceService` | purchases/incomes/debts (create/update/delete) + pagos de deuda; ownership y FKs |
| `Services/Nutrition/NutritionService` | meal logs/items, foods, upsert por fecha+tipo; macros correctos (×quantity, paridad web) |
| `Services/Grocery/GroceryService` | items (create/update/delete/consume/restock) + price history |
| `Services/Supplement/SupplementService` | suplementos (CRUD) y logs de toma |
| `Services/Freelance/FreelanceService` | clientes y cotizaciones (create/update/delete/convert) |

Reglas transversales: todo scope por `user_id`, `exists`/lookups con ownership, transacciones en operaciones multi-tabla, errores como excepciones de dominio (`InvalidArgumentException`/`ModelNotFoundException` mapeadas a respuestas accionables por las tools).

### Chat IA

- Nuevos action tools: `TaskActionTool`, `ProjectActionTool`, `FinanceActionTool`, `NutritionActionTool`, `GroceryActionTool`, `SupplementActionTool`, `FreelanceActionTool`; queries nuevas `FreelanceQueryTool`, `SupplementQueryTool`. Todos `Approvable` con etiqueta por acción (se conserva el flujo de aprobación actual).
- `ToolCatalog`: cada grupo de módulo agrupa su query + action (como `workout`); `actions` = alias de todas las action tools; `toolsFor()` dedupea por clase. `AskUserTool` sigue siempre disponible.
- `ToolRouter` + `config/ai_tools.php`: se amplían keywords de módulo y write verbs (ES/EN) y el fallback incluye los grupos de datos (lectura) para que un mensaje ambiguo no pierda los módulos.
- `MegalomaniacAgent::instructions()`: instrucciones de escritura condicionadas a los grupos realmente publicitados; nunca menciona tools ausentes. `RuntimeAgent` + wizard de agentes: mapping completo de grupos; en hilos con agente custom el picker del chat queda en modo "definido por el agente".
- Picker (`ToolsPicker`/`thread.tsx`/`chat.tsx`): elegir "Auto" envía `tools_policy={mode:'auto',groups:[]}` y se persiste; al pasar a manual, el set inicial parte de los grupos resueltos por el router (no solo `memory`). Labels que distinguen solo-consulta de lectura-escritura y feedback del set activo usando el evento SSE `tools` (hoy parseado y no renderizado).

### MCP

- Fix estructural: `$request->get()` en los tools de Personal (+ default de `limit` correcto).
- Los `*-WriteTool` delegan en los servicios; enums de `action` ampliados con `update`/`delete` (y `move`/`archive`/`consume`/`restock`/`pay` según módulo); schemas con los campos que hoy faltan (proyecto: `type`, `client_id`, `area`, `module`, `budget`, `hourly_rate`, `estimated_hours`, `tags`, `color`, `icon`, etc.).
- Reads corregidos: deudas vencidas fuera de la ventana, límites por fecha, `low_stock` con `current_stock`/`target_stock`, tasks personales incluyendo las de proyectos personales.
- Suplementos: `supplement-read`/`supplement-write` nuevos.
- Server instructions actualizadas.

### Proyectos: tipo y movimiento

`ProjectTypeService::changeType(Project, 'personal'|'freelance', User, ?int $clientId)`:

1. Valida: a freelance requiere cliente real (no el auto "Personal"); a personal limpia `client_id` si es el auto "Personal".
2. Reutiliza/añade columnas del tablero del tipo destino (`PERSONAL_DEFAULTS`/`FREELANCE_DEFAULTS`) y remapea tareas: por `is_done` → columna done; resto → primera columna no-done equivalente; actualiza `status` e `is_done`.
3. Todo en transacción; devuelve el proyecto con relaciones frescas.

Expuesto en: `Personal\PersonalProjectController@update`, `Freelance\ProjectController@update`, `Api\V1\ProjectController@update`, `ProjectActionTool`, `PersonalProjectWriteTool`, `FreelanceWriteTool`.

### Aislamiento y seguridad

- Freelance index/dashboard filtran `type=freelance`; tareas personales se listan con su proyecto cuando corresponde y excluyen freelance.
- `PersonalProjectPolicy` (dead) se reemplaza por `ProjectPolicy` aplicada en Personal, Freelance y API (`view/update/delete` por `user_id`); Freelance deja de permitir acceso por ID.
- `TaskService` valida `project_id` (owner + tipo) en store/update/move; los `exists` de MCP/AI usan scope de usuario.
- `finances`/`freelance` MCP: FKs con ownership y defaults equivalentes a la web; `incomes.notes` se elimina del schema/validación MCP (la tabla no tiene columna y el form web no lo usa).

## UI (anti-ui-slop + ui-radar)

- Selector "Módulo/Tipo" en `personal/projects/Form.tsx` y `freelance/projects/Form.tsx` con aviso del remapeo; al pasar a freelance exige cliente.
- Acción "Mover a Freelance/Personal" en los Show (confirmación, usa el servicio).
- Chat: picker corregido, labels claros, badge/estado de grupos activos.
- `ui-radar` para el patrón de select/menú; `anti-ui-slop` como finish gate de las pantallas tocadas (focus/hover/disabled, táctil ≥44px, responsive, tokens Ember). Sin rediseño de sidebar (verificada funcional).

## Fases

- **Fase 0 (hotfix):** crash MCP Personal + smoke tests de los 14 tools; router/prompt/picker-auto; query tools rotas; macros de nutrición; FKs requeridas; try/catch en tools.
- **Fase 1:** `ProjectTypeService` + tipo/mover + aislamiento + `ProjectPolicy`/ownership + security tests + UI de tipo/mover; `create_project`/`update_project` en chat y MCP.
- **Fase 2:** cobertura update/delete y módulos restantes (finance, nutrition, grocery, supplements, freelance, gym, tasks) sobre servicios compartidos; reads MCP.
- **Fase 3:** QA Playwright (`docs/qa/playwright-report.md`), docs (`docs/modules/mcp.md`, `docs/modules/tools.md`) y suite completa.

## Testing

Pest por fase: servicios (unit/feature), action tools con aprobación, tools MCP (smoke de los 14 + flujos por módulo), router/policy auto, `ProjectTypeService` (remapeo de columnas), aislamiento y seguridad (403/404/IDOR), regresión de macros. Verificación: `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`, `npm run types`, `npm run lint`, QA Playwright final.

## Fuera de alcance

- Rediseño de la sidebar o de la navegación (verificada funcional); solo se arreglan links internos si entran en el cambio.
- Features de UI nuevas (kanban avanzado, UI de cotizaciones); solo se exponen operaciones existentes a las tools.
- Migración automática de datos históricos del cliente "Personal" (queda como comando manual).
- Transporte MCP stdio y cambios en MCPs custom (ya diseñados en su spec).
