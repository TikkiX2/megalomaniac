# Gym Module Completion — Design Spec

**Fecha:** 2026-09-29
**Estado:** Aprobado (brainstorming con el usuario, enfoque C)
**Alcance elegido por el usuario:** Módulo completo — chat + UI + datos + seguridad.

## Problema

El agente del chat in-app no puede escribir entrenamientos. Causas verificadas:

1. `ActionTool::logSet` (`app/Ai/Tools/ActionTool.php:177`) crea `WorkoutSet` sin `set_number`, columna `NOT NULL` sin default (`database/migrations/2026_02_08_005147_create_gym_tables.php:63`) → error SQL.
2. `ActionTool` no tiene acción `add_exercise` → el workout creado por chat queda vacío y no hay `workout_exercise_id` para loguear series.
3. El router de tools (`config/ai_tools.php` + `app/Ai/Tools/ToolRouter.php`) no reconoce verbos como "escribir/añadir" → el grupo `actions` no se activa y el modelo responde que no puede.
4. `WorkoutQueryTool` (`app/Ai/Tools/WorkoutQueryTool.php:32`) filtra `exercises.name`, columna inexistente (el nombre vive en `Exercise`) → el filtro por ejercicio rompe.

Además, el módulo tiene huecos fuera del chat:

- Lógica de entrenamiento duplicada en 4 superficies (web `Gym\WorkoutController`, API v1, MCP `WorkoutWriteTool`, chat `ActionTool`) con reglas divergentes: la copia de plantilla de rutina solo existe en web; `POST /api/v1/workouts` desde rutina devuelve workout vacío; MCP ignora `routine_id`.
- IDOR en rutas web Gym (`Gym\WorkoutController::show/update/addExercise/logSet/destroy` y `Gym\RoutineController::show/update/destroy` no verifican dueño); `routine_id` se valida con `exists:routines,id` sin scope de usuario en web, API y MCP.
- API v1 sin los endpoints de detalle planificados (`POST workouts/{workout}/exercises`, `POST workout-exercise/{workoutExercise}/sets`).
- Sin `personal_records`: el "PR" se calcula en cliente y no hay timeline (FASE 4 del spec de diseño rojo).
- Sin unique `(workout_exercise_id, set_number)`: `updateOrCreate` puede duplicar bajo concurrencia.
- UI con botones muertos (`gym-routine.tsx:331-337` video/delete sin handler); no se puede borrar workout-exercise ni serie desde la UI.
- `app/Models/RoutineExercise.php` es una clase muerta (sin relaciones ni uso; el pivot real es `Routine::exercises()` belongsToMany).
- Biblioteca de ejercicios vacía en instalación limpia (sin seeder).

## Decisiones

- **Enfoque C:** servicio de dominio Gym + adaptadores finos, y tool de escritura de gym propio en el grupo `workout` del chat (no depender de la lista de verbos para gym).
- Una sola fuente de verdad: web, API v1, MCP y chat llaman a los mismos servicios.
- Authorization con Policies (Laravel 12 auto-discovery) en web; queries scopeadas al usuario en API/MCP/servicios.
- PR server-side en tabla `personal_records` (cada mejora inserta fila → timeline).
- Volume Chart ya existe (dashboard "Volumen semanal", history columna volumen): **fuera de alcance**.
- No se cambia el contrato de serialización de `WorkoutSetResource` (weight/rpe como string decimal) para no romper tests existentes.

## Arquitectura

### Servicios de dominio — `app/Services/Gym/`

**`WorkoutSessionService`**

| Método | Contrato |
|---|---|
| `activeFor(User $user): ?Workout` | Último workout con `ended_at` null del usuario. |
| `start(User $user, ?int $routineId = null, ?string $startedAt = null, ?string $notes = null): Workout` | Valida pertenencia de rutina; si hay workout activo lo devuelve sin crear; copia plantilla de rutina (ejercicios con `order` del pivot) y prellena sets desde el último workout terminado o targets del pivot (`target_sets` default 3, `target_weight`, `target_reps`); `completed = false`. |
| `addExercise(User $user, Workout $workout, ?int $exerciseId = null, ?string $exerciseName = null): WorkoutExercise` | Find-or-create `Exercise` por nombre (o por id); `order = max+1`. Error si faltan ambos. |
| `logSet(User $user, WorkoutExercise $workoutExercise, array $data): WorkoutSet` | `set_number` auto (`max+1`) si no viene; `updateOrCreate`; tras guardar, si `completed` y hay `weight`/`reps`, evalúa PR. |
| `finish(User $user, Workout $workout, ?string $endedAt = null, ?string $notes = null): Workout` | Setea `ended_at` (default `now()`) y notas. |
| `removeExercise(User $user, WorkoutExercise $workoutExercise): void` | Borra el workout-exercise (cascade sets). |
| `removeSet(User $user, WorkoutSet $set): void` | Borra la serie. |
| `delete(User $user, Workout $workout): void` | Borra workout. |

**`RoutineService`**

| Método | Contrato |
|---|---|
| `create(User $user, array $data): Routine` | `name`, `focus`, `scheduled_date`, `status`; `exercises` opcional: array de `{exercise_id|name, target_sets, target_reps, target_weight, notes}` → attach al pivot con `order` incremental. |
| `update(User $user, Routine $routine, array $data): Routine` | Metadatos; si viene `exercises`, reemplaza el pivot (sync). |
| `delete(User $user, Routine $routine): void` | Borra rutina. |

**`PersonalRecordService`**

| Método | Contrato |
|---|---|
| `evaluate(User $user, WorkoutSet $set): void` | Con `completed = true` y `weight`/`reps` presentes, compara contra el mejor histórico por `(user, exercise)` para tipos `weight` (mayor peso), `reps` (más reps en ese peso) y `one_rm` (Epley: `weight * (1 + reps/30)`); inserta fila en `personal_records` por cada tipo superado. |
| `timeline(User $user, int $limit = 50)` | Últimos registros con ejercicio. |
| `bestFor(User $user, Exercise $exercise): array` | Mejor por tipo (para badge `is_pr` en la UI). |

### Policies — `app/Policies/`

- `WorkoutPolicy`: `view/update/delete` → `$user->id === $workout->user_id`.
- `RoutinePolicy`: igual sobre `$routine->user_id`.
- `Exercise` sigue siendo librería global (sin `user_id`): sin policy de ownership.

Validación de pertenencia de `routine_id` en web, API, MCP y chat: `Rule::exists('routines', 'id')->where('user_id', $user->id)`.

### Adaptadores

- **Web** (`app/Http/Controllers/Gym/`): thin controllers; `authorize()` con policies; rutas nuevas `DELETE gym/workout-exercises/{workoutExercise}` y `DELETE gym/workout-sets/{workoutSet}`; `history` agrega prop `personalRecords`.
- **API v1** (`app/Http/Controllers/Api/V1/WorkoutController.php` + `routes/api.php`): añade `POST workouts/{workout}/exercises` y `POST workout-exercises/{workoutExercise}/sets`; `POST /workouts` usa `WorkoutSessionService` (copia plantilla); `StoreWorkoutRequest` con `routine_id` scopeado.
- **MCP** (`app/Mcp/Tools/WorkoutWriteTool.php`): delega en servicios; acciones nuevas `finish_workout`, `create_routine`, `add_routine_exercise`; `app/Mcp/Tools/WorkoutReadTool.php` expone `exercise_id` por ejercicio y agrega `routines` al resultado.
- **Chat** (ver abajo).

### Chat

- **`app/Ai/Tools/GymActionTool.php`** (nuevo, `Approvable` como `ActionTool`) con acciones:
  - `create_workout` (`routine_id?`, `started_at?`, `notes?`) → devuelve workout + ejercicios creados con `workout_exercise_id`.
  - `add_exercise` (`workout_id`, `exercise_id|exercise_name`, `order?`).
  - `log_set` (`workout_exercise_id`, `set_number?`, `weight?`, `reps?`, `rpe?`, `completed?`).
  - `finish_workout` (`workout_id`, `ended_at?`, `notes?`).
  - `create_routine` (`name`, `focus?`, `scheduled_date?`, `exercises[]` con `{name|exercise_id, target_sets, target_reps, target_weight, notes}` — `JsonSchema` soporta `array()->items(object)`).
  - `add_routine_exercise` (`routine_id`, `exercise_id|exercise_name`, `target_*`).
  - `update_routine` (`routine_id`, `name?`, `focus?`, `scheduled_date?`, `status?`).
  - `needsApproval` con etiqueta humana por acción.
- **`app/Ai/Tools/WorkoutQueryTool.php` → `GymQueryTool.php`**: fix del filtro a `whereHas('exercises.exercise', ...)`; parámetro `resource`: `workouts` (default) | `exercises` (librería, filtro `search`/`muscle_group`) | `routines` (con ejercicios y targets). Siempre expone `exercise_id`, `workout_exercise_id`, `routine_id`.
- **`app/Ai/Tools/ToolCatalog.php`**: grupo `workout` → `[GymQueryTool::class, GymActionTool::class]`.
- **`config/ai_tools.php`**: escribir stems faltantes (`escrib`, `anad`, `ingres`, `carg`, `hazme`, `prepara`) y keywords de gym (`pesa`, `banca`, `dominada`, `curl`).
- **`app/Ai/Agents/MegalomaniacAgent.php`**: instrucciones del flujo gym (resolver ejercicio por nombre, encadenar create → add → log_set → finish).

### Datos

- Migración `add_indexes_and_unique_to_gym_tables`:
  - Dedupe de `workout_sets` duplicados por `(workout_exercise_id, set_number)` conservando el `id` mayor, luego unique.
  - Índices: `workouts(user_id, started_at)`, `workout_exercises(workout_id, order)`, `exercises(name)`.
- Migración `create_personal_records_table`: `user_id` FK cascade, `exercise_id` FK cascade, `workout_set_id` FK nullOnDelete, `type` string, `value` decimal(8,2), `reps` int nullable, `weight` decimal(8,2) nullable, `achieved_at` dateTime, timestamps; índice `(user_id, exercise_id, achieved_at)`.
- Modelo `PersonalRecord` con `casts()` y relaciones.
- Borrar `app/Models/RoutineExercise.php`.
- `casts()` en `Workout`, `WorkoutSet`, `WorkoutExercise`; `reps` int.
- Seeder `ExerciseSeeder` (~30-40 ejercicios canónicos) llamado desde `DatabaseSeeder`.

### UI

- `resources/js/pages/fitness/gym-routine.tsx`: conectar botón video (abre `video_url` si existe), borrar ejercicio del workout (DELETE), borrar serie (DELETE); badge PR desde `is_pr` server-side: cada set del payload web incluye `is_pr` (existe fila en `personal_records` con ese `workout_set_id`).
- `resources/js/pages/fitness/history.tsx`: sección PR timeline (ejercicio, peso×reps, 1RM, fecha) desde prop `personalRecords`.

## Manejo de errores

- Servicios lanzan `ModelNotFoundException`/`AuthorizationException` cuando el recurso no existe o no es del usuario; los adaptadores traducen a 404/403 (API/MCP) o `abort(403)` (web).
- Chat: las tools devuelven `Response::error`/JSON `{success:false, error}` legible para el modelo (patrón actual de `ActionTool`), nunca excepciones crudas.
- `start()` con workout activo devuelve el activo (idempotente) — mismo comportamiento que hoy.

## Testing

- Pest. Tests de dominio: `tests/Feature/Gym/WorkoutSessionServiceTest.php`, `RoutineServiceTest.php`, `PersonalRecordServiceTest.php`.
- Web: ownership (403) en workouts/rutinas; endpoints delete nuevos.
- API: endpoints nuevos, copia de plantilla, `routine_id` ajeno (422).
- Chat: `GymActionToolTest` (cada acción + aprobación + ownership), `GymQueryToolTest`, `ToolRouterTest` (verbos), regresión `ChatUnknownToolTest`/`ChatApprovalTest`.
- MCP: `tests/Feature/Mcp/WorkoutWriteToolTest.php` (hoy 0 tests).
- PR: detección y timeline.
- Cierre: `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`, `npm run build`, QA Playwright según AGENTS.md.

## Fuera de alcance

- Volume Chart (ya existe).
- Aprobación/auditoría de escrituras MCP directas (comportamiento documentado actual).
- Scopes por módulo en tokens Sanctum.
- Rediseño del chat o del selector de grupos del composer.

## Fases

1. Servicios de dominio + Policies + tests de dominio.
2. Migraciones (índices, PR) + seeder + limpieza de modelos + casts.
3. Adaptadores web/API/MCP + tests.
4. Chat tools (`GymQueryTool`, `GymActionTool`) + catálogo/router/instrucciones + tests.
5. UI (`gym-routine`, PR timeline en `history`) + build.
6. QA final (suite completa, pint, Playwright).
