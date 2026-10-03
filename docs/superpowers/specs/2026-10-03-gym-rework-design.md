# Gym Module Rework — Rutinas, Historial y Progresión — Design Spec

**Fecha:** 2026-10-03
**Estado:** Aprobado (brainstorming con el usuario, enfoque B)
**Alcance:** Rework del flujo de entrenamiento: selector de rutina en la sesión, carga automática de ejercicios, registro de entrenamientos pasados en el historial, repetir sesiones anteriores y progresión por ejercicio.

## Problema

El módulo gym funciona end-to-end en su flujo básico (verificado en vivo: crear rutina → USE ROUTINE → workout con ejercicios y series objetivo), pero el usuario reporta cuatro carencias:

1. **No se cargan las rutinas / no se pueden elegir en la sesión.** En `/fitness/gym` no existe ningún selector de rutina: solo aparece el CTA "Start a New Workout" o la rutina sugerida del día. La única vía para empezar con rutina es salir a `/fitness/routines` y hacer click en "USE ROUTINE".
2. **Bug: elegir una rutina no carga los ejercicios cuando ya hay un workout activo.** `WorkoutSessionService::start()` (`app/Services/Gym/WorkoutSessionService.php:30-32`) devuelve silenciosamente el workout activo existente SIN copiar la plantilla de la rutina cuando `routine_id` está presente. El usuario elige la rutina, el backend la ignora y la sesión sigue vacía (o con la sesión vieja). Confirmado en código y reproducido: la DB dev tenía un workout activo huérfano.
3. **No se pueden cargar/registrar entrenamientos anteriores.** No hay forma de (a) registrar un entrenamiento hecho en el pasado para que aparezca en el historial (backdate) ni (b) repetir una sesión anterior como punto de partida.
4. **No hay historial de ediciones / progresión.** Existe el PR timeline agregado, pero no hay una vista de evolución por ejercicio (peso máximo, 1RM, tonelaje) a lo largo de las sesiones.

Además, estado de datos del entorno dev: 0 rutinas y 1 solo ejercicio (`ut Press`, nombre corrupto) a pesar de que `ExerciseSeeder` define 37 ejercicios canónicos (`firstOrCreate`, idempotente).

## Decisiones

- **Enfoque B:** extender el servicio de dominio `WorkoutSessionService` (el mismo patrón del spec `2026-09-29-gym-module-completion-design.md`: una sola fuente de verdad para web, API, MCP y chat) + rutas delgadas + UI consistente con el sistema existente.
- **Conflicto de workout activo explícito:** `start()` con `routine_id` y workout activo existente lanza `WorkoutAlreadyActiveException` (nunca más ignora la rutina en silencio). Sin `routine_id` (sesión rápida) mantiene el comportamiento idempotente actual. Web mapea a HTTP 409 con el workout activo; chat/MCP traducen a error legible.
- **Repetir sesión (`repeat`)**: nuevo workout activo que copia ejercicios + series del workout origen con los valores del día (`completed=false`), hereda `routine_id` y notas. No evalúa PR.
- **Registrar entrenamiento pasado (`logPast`)**: workout ya finalizado con `started_at = ended_at` = fecha elegida por el usuario (pasada); con rutina copia la plantilla (targets / previous); sin rutina queda como sesión vacía. No evalúa PR (no pasa por `logSet`).
- **Progresión por ejercicio (`progressionFor`)**: consulta read-only que agrupa series por sesión terminada → `{workout_id, date, best_weight, best_1rm (Epley), volume, total_reps, completed_sets}`. Sin nueva tabla: computable desde `workouts/workout_exercises/workout_sets`.
- **Sin migraciones nuevas.**
- **UI con el sistema de diseño existente**: paleta Ember (`docs/design-tokens.md`), componentes existentes (`@/components/ui/dialog`, `select`, `input`, `button`), material-symbols, animate-in. Se respetan los hexes ya usados en las páginas del módulo (tokenización es FASE 3, fuera de alcance).
- **Sin librería de charts:** la progresión usa SVG inline (polyline + puntos + área), coherente con la estética del módulo.
- **Fuera de alcance (YAGNI):** `repeat`/`logPast` para chat/MCP/API v1 (el chat ya crea workouts con plantilla; se agregan cuando se pidan), versionado de rutinas, auditoría de ediciones, edición de series de workouts finalizados, rediseño de la página de rutinas.

## Arquitectura

### Backend — `app/Services/Gym/`

**Nueva excepción `WorkoutAlreadyActiveException`** (en `app/Exceptions/` o `app/Services/Gym/Exceptions/`): transporta el workout activo (`public function __construct(public Workout $workout)`).

**`WorkoutSessionService` — contratos:**

| Método | Contrato |
|---|---|
| `start(User $user, ?int $routineId = null, ?string $startedAt = null, ?string $notes = null): Workout` | **Cambio de contrato:** si existe workout activo **y** `$routineId` no es null → lanza `WorkoutAlreadyActiveException` con el activo. Si no hay `routine_id` y existe activo → devuelve el activo (idempotente, igual que hoy). Sin activo → crea y copia plantilla si hay rutina (comportamiento actual). |
| `repeat(User $user, Workout $source): Workout` | Verifica dueño del origen. Crea workout activo con `routine_id = $source->routine_id`, `notes = $source->notes`, `started_at = now()`. Copia cada `WorkoutExercise` (exercise_id, order) y sus `WorkoutSet` (set_number, weight, reps, rpe) con `completed = false`. No evalúa PR. |
| `logPast(User $user, ?int $routineId, string $startedAt, ?string $notes = null): Workout` | Verifica dueño de rutina si viene. Crea workout **finalizado**: `started_at = ended_at = $startedAt`, `notes`. Si hay rutina → `copyRoutineTemplate()` (recién creado). No evalúa PR. |
| `progressionFor(User $user, Exercise $exercise): Collection` | Read-only: `WorkoutSet` con `whereHas(workoutExercise.exercise_id = exercise)` y `whereHas(workout.user_id = user, ended_at != null)`, agrupado por workout (ordenado por started_at). Por sesión: `best_weight` (max weight), `best_1rm` (max `weight * (1 + reps/30)`), `volume` (sum `weight*reps`), `total_reps` (sum reps), `completed_sets` (count completed). |

`copyRoutineTemplate()` se mantiene privado e intacto (ya copia ejercicios + `target_sets` series con peso/reps desde previous o targets del pivot).

### Rutas — `routes/web.php` (grupo `gym`)

| Ruta | Método | Controlador |
|---|---|---|
| `POST gym/workouts/{workout}/repeat` | `repeat` | `WorkoutController@repeat`: `authorize('view', $workout)`; JSON 201 con `loadWorkoutWithHistory()`; redirección para Inertia. |
| `POST gym/workouts/log-past` | `logPast` | `WorkoutController@logPast`: valida `routine_id` (nullable, `Rule::exists('routines','id')->where('user_id', …)`), `started_at` (required, date), `notes` (nullable). JSON 201 con la sesión; redirección Inertia. |
| `GET gym/exercises/{exercise}/progression` | `progression` | `WorkoutController@progression`: JSON de `progressionFor()`. |

`WorkoutController@store`: el `WorkoutAlreadyActiveException` se traduce a `response()->json(['message' => …, 'active_workout' => $exception->workout], 409)` para JSON y `abort(409)`/flash para Inertia (el frontend captura el error por el payload). El resto del controller no cambia.

### Frontend

**`resources/js/pages/fitness/gym-routine.tsx`:**

1. **Selector de rutina en la sesión.**
   - Toolbar: botón "Rutina" (icon `edit_calendar` / `fitness_center`) junto al botón "Historial".
   - Estado vacío (sin workout activo y sin sugerida): el CTA principal pasa a ser **"Elegir rutina"**; queda un CTA secundario "Sesión rápida" (el flujo actual `POST /gym/workouts`).
   - Modal (`Dialog` existente) "Elegir rutina": se abre con `POST gym/workouts {routine_id}` → la sesión arranca con ejercicios y series cargados (el backend ya los copia); el modal se nutre de la prop `routines` ya presente en la página.
   - Cards de rutina: nombre, focus, badge día agendado, N ejercicios → preview expandible con ejercicios y targets (`3 × 8-12`, peso).
   - Empty state del modal: "Todavía no tenés rutinas" + CTA a `/fitness/routines`.
2. **Dialog de conflicto activo.** Si el POST responde 409 (workout activo), se abre un dialog: "Ya tenés un entrenamiento activo" con dos acciones: **Continuar el activo** (descarta el intento) / **Terminar y empezar la rutina** (PATCH `ended_at` al activo y re-POST de la rutina).
3. **Modal de progresión por ejercicio.** El icono `history` de cada card (hoy span inerte) abre un `Dialog`:
   - Fetch lazy al abrir: `GET /gym/exercises/{exercise_id}/progression` (prop no inflada).
   - Sparkline SVG inline: mejor peso por sesión terminada, polyline + puntos + área, fechas en el eje.
   - Tabla compacta de sesiones: fecha · peso máx · 1RM (Epley) · tonelaje · sets completados.
   - Estados: skeleton pulsante (loading), "Sin sesiones previas" (empty), error con retry.

**`resources/js/pages/fitness/history.tsx`:**

4. **"Agregar entrenamiento anterior".** Botón en toolbar → modal: selector de rutina (o "Sesión rápida"), `datetime-local` (max = ahora, para backdate), notas → `POST gym/workouts/log-past` → refresh del listado (`router.reload` / re-visit).
5. **"Repetir" + detalle por fila.**
   - Acción "Repetir" por fila → `POST gym/workouts/{id}/repeat` → navega a `/fitness/gym` con la sesión precargada.
   - Filas expandibles (chevron): detalle ejercicios × series con valores del día y badge de sets completados; usa los datos ya presentes en `workouts[].exercises[].sets` (cero requests extra).

Consistencia: `Dialog`, `Select`, `Input`, `Button`, `Label` existentes; paleta Ember; hexes ya usados en estas páginas; motion solo con los `animate-in` existentes; `aria-label` en botones solo-icono (pendiente conocido del QA).

## Manejo de errores

- `WorkoutAlreadyActiveException` → web: 409 (JSON con `active_workout`) / flash para Inertia; chat/MCP: mensaje legible ("ya tenés un entrenamiento activo") vía `Response::error` (patrón de `GymActionTool`).
- `repeat`/`logPast` sobre recursos ajenos: `AuthorizationException` → 403 web / mensaje legible en las demás superficies (no expuestas por ahora).
- `progressionFor` de ejercicio inexistente: 404 del route-model binding (estándar).

## Datos

- **Sin migraciones.**
- **Entorno dev:** `php artisan db:seed --class=ExerciseSeeder` restaura los 37 ejercicios canónicos (firstOrCreate, idempotente).
- **`GymDemoSeeder`** (nuevo, ejecutado manualmente, NO desde `DatabaseSeeder`): para `test@example.com` crea 2 rutinas de ejemplo (una agendada para el día actual del run) + 2 workouts pasados finalizados con series → habilita QA de picker, repetir, backdate y progresión. Documentar el uso en `docs/modules/gym.md`.

## Testing (Pest, TDD)

- `tests/Feature/Gym/WorkoutSessionServiceTest.php` (nuevos casos):
  - `start()` + `routine_id` + workout activo → lanza `WorkoutAlreadyActiveException`; el activo queda intacto y sin tocarse.
  - `start()` sin rutina + activo → devuelve el activo (regresión).
  - `repeat()`: copia ejercicios/orden/series con peso·reps·RPE preservados y `completed=false`; hereda `routine_id`/notas; workout ajeno → AuthorizationException.
  - `logPast()`: `started_at = ended_at` = fecha elegida; con rutina copia template; sin rutina queda vacío; **0 filas** en `personal_records` (no evalúa PR).
  - `progressionFor()`: agrupa por sesión terminada correctamente (best_weight, best_1rm, volume, total_reps, completed_sets); ignora workouts sin `ended_at`.
- `tests/Feature/Gym/WorkoutWebTest.php`: `repeat` 201 + 403 ajeno; `log-past` 422 sin `started_at` + 201; `progression` 200 JSON; `store` con conflicto → 409.
- Cierre: `php artisan test --compact` completo, `vendor/bin/pint --dirty --format agent`, `npm run build`, eslint sobre archivos tocados, QA Playwright diario según AGENTS.md (flujo: Landing → Login → Gym → elegir rutina → ejercicios cargados → Finish → History → agregar entrenamiento anterior → repetir → progresión).

## Fases

1. Servicio: `WorkoutAlreadyActiveException` + cambio de contrato en `start()` + `repeat()` + `logPast()` + `progressionFor()` + tests de dominio.
2. Web: rutas nuevas + mapeo 409 + tests web.
3. UI `gym-routine.tsx`: picker + dialog de conflicto + modal de progresión + estados.
4. UI `history.tsx`: agregar entrenamiento anterior + repetir + filas expandibles.
5. Datos: `GymDemoSeeder` + re-seed de ejercicios + docs `gym.md`.
6. QA final: suite completa, pint, build, eslint, Playwright.