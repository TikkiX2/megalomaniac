# Módulo Gym

Models: Exercise, Routine, Workout, WorkoutExercise, WorkoutSet, PersonalRecord (la plantilla de rutina vive en el pivote `routine_exercises`, sin modelo propio)

Rutas: `gym/exercises`, `gym/routines`, `gym/workouts`, `gym/workouts/{id}/exercises`, `gym/workout-exercises/{id}/sets`, `fitness/gym`, `fitness/routines`, `fitness/gym-routine`

Pages: `fitness/gym-routine.tsx` (694 líneas), `fitness/routines.tsx`, `fitness/dashboard.tsx`, `layouts/gym-layout.tsx`

Flujo diario: crear workout → añadir exercise desde quick library → log sets (kg/reps/RPE) → complete → finish → timer → volume/sets footer.

QA diario: verificar timer, previous set, add Set, finish disabled sin workout, suggestedRoutine CTA, quick library filter.

Feature plan: Volume Chart (chart-1 rojizo), Streaks heatmap (PR Timeline ya implementado, ver abajo).

## Escritura por agente (chat/MCP/API)

Tres superficies delegando en los mismos servicios que la web: `WorkoutSessionService` (workouts/series/PRs) y `RoutineService` (rutinas).

### Chat — `GymActionTool` (grupo `workout`)

Todas las acciones requieren aprobación explícita del usuario antes de ejecutarse.

- `create_workout` — inicia workout; con `routine_id` copia la plantilla (ejercicios + series objetivo, con previous set si existe).
- `add_exercise` — por `exercise_id` o `exercise_name` (crea el ejercicio si no existe).
- `log_set` — registra serie; `set_number` auto-incremental si se omite; evalúa PRs al completar.
- `finish_workout` — cierra el workout (`ended_at` y notas).
- `create_routine` — rutina en batch con `exercises[]` (name/exercise_id + targets).
- `add_routine_exercise` — añade un ejercicio a una rutina existente.
- `update_routine` — actualiza nombre/focus/scheduled_date/status.

### MCP

- `workout-write` — mismas acciones equivalentes: `create_workout`, `add_exercise`, `log_set`, `finish_workout`, `create_routine`, `add_routine_exercise`.
- `workout-read` — historial (`days`/`limit`) con IDs de workouts y workout_exercises, más catálogo de rutinas con targets; sirve para encadenar escrituras.

### API v1 (Sanctum)

- `POST /api/v1/workouts/{workout}/exercises` — añade ejercicio (id o nombre).
- `POST /api/v1/workout-exercises/{workoutExercise}/sets` — registra serie.
- `PATCH /api/v1/routines/{routine}` / `DELETE /api/v1/routines/{routine}` — actualiza/borra rutina.

### Personal Records (PR)

- Tabla `personal_records`: `user_id`, `exercise_id`, `workout_set_id`, `type`, `value`, `reps`, `weight`, `achieved_at`.
- Tipos evaluados al loguear una serie completada: `weight` (mejor peso), `one_rm` (Epley: `weight × (1 + reps / 30)`), `reps` (mejor reps al mismo peso).
- UI: badge de trofeo en sets PR del workout y PR Timeline en `/fitness/history` (`personalRecords`, últimos 20).

## Rework 2026-10-03 (rutinas, historial y progresión)

### Rutas nuevas

| Ruta | Método | Descripción |
|---|---|---|
| `gym/workouts/{workout}/repeat` | POST | Repite un workout anterior: crea un workout activo copiando ejercicios y sets (`completed=false`). JSON 201 / redirect Inertia. |
| `gym/workouts/log-past` | POST | Registra un entrenamiento pasado ya finalizado (`ended_at = started_at`). Valida `routine_id` del usuario, `started_at` (`required\|date\|before_or_equal:now`) y `notes`; copia la plantilla de la rutina. |
| `gym/exercises/{exercise}/progression` | GET | Progresión por ejercicio: una entrada por sesión terminada, ordenada por fecha — `best_weight`, `best_1rm` (Epley), `volume`, `total_reps`, `completed_sets`. JSON. |
| `gym/workouts` (con `routine_id`) | POST | Con workout activo responde **409** con `active_workout` (JSON) o error `workout` (Inertia); ya no arranca en silencio ignorando la rutina. |

### Flujo del picker (`gym-routine.tsx`)

Botón "Rutina" en el toolbar abre el picker: card por rutina (nombre, badge de día, nº de ejercicios) con preview expandible (`ejercicio · series × reps · kg objs.`); "Empezar con esta rutina" → `POST gym/workouts` con `routine_id` (copia la plantilla a la sesión activa). Con workout activo: dialog de conflicto "Ya tenés un entrenamiento activo" (continuar el activo, o terminarlo y empezar la rutina). Cada ejercicio tiene botón de historial → modal de progresión (sparkline + tabla). Botón "Historial" → `/fitness/history`.

### GymDemoSeeder

Datos demo para el usuario `test@example.com` (el que crea `DatabaseSeeder`), para QA de rutinas/historial/progresión. Correr tras el seed base:

```bash
php artisan db:seed --class=ExerciseSeeder    # restaura la librería canónica (37 ejercicios, idempotente)
php artisan db:seed --class=GymDemoSeeder
```

Crea 2 rutinas — "Pecho y Tríceps" y "Pierna y Core" (esta con `scheduled_date` = día actual) — con sus ejercicios y targets (3×8-12), y 2 workouts finalizados (hace 3 y 7 días) con 2-3 sets completados cada ejercicio y pesos distintos. Idempotente (firstOrCreate + guard): si `test@example.com` ya tiene rutinas y workouts, el seeder no hace nada, por lo que puede correrse sin duplicar datos.
