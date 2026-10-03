# Gym Module Rework Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rework del módulo de entrenamiento: selector de rutina con carga automática de ejercicios, fix del bug de workout activo que ignoraba la rutina, registro de entrenamientos pasados (backdate), repetir sesiones anteriores y progresión por ejercicio.

**Architecture:** Extiende `WorkoutSessionService` (una sola fuente de verdad para web/chat/MCP, patrón del spec 2026-09-29) con `repeat()`, `logPast()`, `progressionFor()` y una excepción de conflicto explícita. Rutas delgadas en `WorkoutController` + UI en `gym-routine.tsx` y `history.tsx`. Sin migraciones ni dependencias nuevas.

**Tech Stack:** Laravel 12 (PHP 8.4), Inertia v2 + React 19, Tailwind v4, Pest 4, wayfinder (backend), componentes `@/components/ui`, material-symbols.

**Spec:** `docs/superpowers/specs/2026-10-03-gym-rework-design.md`

## Global Constraints

- Sin dependencias nuevas, sin migraciones nuevas.
- Sin `DB::` facade: Eloquent queries + relaciones en servicios (convención del proyecto).
- `cast()` en vez de `$casts` (convención Laravel 12 del proyecto).
- Pest para tests; usar factories (`Routine::factory()`, `Workout::factory()`, `Exercise::factory()`, `PersonalRecord::factory()`).
- `vendor/bin/pint --dirty --format agent` después de cada tarea con PHP.
- Frontend: componentes `@/components/ui` existentes, paleta Ember (hexes ya usados en las páginas — NO tokenizar), `router.` de Inertia con URLs hardcodeadas como las páginas vecinas, fetch con `Accept: application/json` para endpoints JSON (patrón existente).
- Idioma de UI: el módulo gym mezcla español/inglés existente; textos nuevos en español, tracking de botones con texto como las páginas vecinas.
- No crear carpetas base nuevas en `resources/js/` (Boost: aprobación requerida) — los componentes de página se quedan inline en su page.

## Review Focus

1. Elegir una rutina con un workout activo → el backend responde 409 con el workout activo (nunca más lo ignora en silencio) y la UI muestra el dialog de conflicto. (Task 5 test 409, Task 6 dialog)
2. `logPast` con fecha futura → 422, no un workout "terminado en el futuro". (Task 5 rule `before_or_equal:now`)
3. `repeat` de una sesión rápida vacía (0 ejercicios) → nuevo workout activo vacío sin errores. (Task 2 test)
4. Progresión con 0 sesiones → estado vacío; con 1 sesión → sparkline de un punto sin crash. (Task 4 test, Task 6 UI states)
5. `GymDemoSeeder` corrido dos veces → sin duplicados (firstOrCreate + guard). (Task 8)

---

### Task 1: Excepción `WorkoutAlreadyActiveException` + cambio de contrato de `start()`

**Files:**
- Create: `app/Exceptions/WorkoutAlreadyActiveException.php`
- Modify: `app/Services/Gym/WorkoutSessionService.php`
- Test: `tests/Feature/Gym/WorkoutSessionServiceTest.php`

**Interfaces:**
- Produces: `WorkoutAlreadyActiveException extends Exception` con `public Workout $workout` (constructor promotion). `start()` ahora lanza está excepción si hay workout activo Y `$routineId !== null`.

- [ ] **Step 1: Write the failing test**

```php
it('throws with the active workout when starting a routine while one is active', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $active = Workout::factory()->create(['user_id' => $user->id]);

    try {
        sessions()->start($user, $routine->id);
        $this->fail('Expected WorkoutAlreadyActiveException');
    } catch (WorkoutAlreadyActiveException $e) {
        expect($e->workout->id)->toBe($active->id);
    }

    expect($active->refresh()->routine_id)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter='throws with the active workout'`
Expected: FAIL — `WorkoutAlreadyActiveException` no existe.

- [ ] **Step 3: Create `app/Exceptions/WorkoutAlreadyActiveException.php`**

```php
namespace App\Exceptions;

use App\Models\Workout;
use Exception;

class WorkoutAlreadyActiveException extends Exception
{
    public function __construct(public Workout $workout)
    {
        parent::__construct('There is already an active workout.');
    }
}
```

- [ ] **Step 4: Update `start()` en `WorkoutSessionService.php` (líneas 28-53)**

Agregar `use App\Exceptions\WorkoutAlreadyActiveException;`. En `start()`: después del primer `if ($active = $this->activeFor($user))`, si `$routineId !== null` lanzar `new WorkoutAlreadyActiveException($active)`; si no, `return $active` (comportamiento actual).

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=WorkoutSessionServiceTest`
Expected: PASS — el test nuevo + los existentes (el test idempotente `starts a workout and returns the active one afterwards` ya cubre el caso sin rutina).

- [ ] **Step 6: Commit**

```bash
git add app/Exceptions/WorkoutAlreadyActiveException.php app/Services/Gym/WorkoutSessionService.php tests/Feature/Gym/WorkoutSessionServiceTest.php
git commit -m "feat(gym): explicit conflict when starting a routine with an active workout"
```

---

### Task 2: `repeat()` — repetir un workout anterior

**Files:**
- Modify: `app/Services/Gym/WorkoutSessionService.php`
- Test: `tests/Feature/Gym/WorkoutSessionServiceTest.php`

**Interfaces:**
- Produces: `repeat(User $user, Workout $source): Workout` — nuevo workout activo (`routine_id` y `notes` heredados, `started_at` = now), copia cada `WorkoutExercise` (exercise_id, order) y sus `WorkoutSet` (set_number, weight, reps, rpe) con `completed = false`. Lanza `AuthorizationException` si el origen es de otro usuario (reusar `assertOwnsWorkout`).

- [ ] **Step 1: Write the failing tests**

```php
it('repeats a previous workout copying exercises and sets', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $exercise = Exercise::factory()->create();
    $source = Workout::factory()->create([
        'user_id' => $user->id, 'routine_id' => $routine->id,
        'ended_at' => now(), 'notes' => 'dia de pierna',
    ]);
    $we = $source->exercises()->create(['exercise_id' => $exercise->id, 'order' => 2]);
    $we->sets()->create(['set_number' => 1, 'weight' => 60, 'reps' => 10, 'rpe' => 8, 'completed' => true]);
    $we->sets()->create(['set_number' => 2, 'weight' => 65, 'reps' => 8, 'rpe' => 9, 'completed' => true]);

    $copy = sessions()->repeat($user, $source);

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->routine_id)->toBe($routine->id)
        ->and($copy->notes)->toBe('dia de pierna')
        ->and($copy->ended_at)->toBeNull();

    $newWe = $copy->exercises()->first();
    expect($newWe->exercise_id)->toBe($exercise->id)
        ->and($newWe->order)->toBe(2);

    $sets = $newWe->sets()->orderBy('set_number')->get();
    expect($sets)->toHaveCount(2)
        ->and($sets[0]->set_number)->toBe(1)
        ->and((float) $sets[0]->weight)->toBe(60.0)
        ->and($sets[0]->reps)->toBe(10)
        ->and((float) $sets[0]->rpe)->toBe(8.0)
        ->and($sets[0]->completed)->toBeFalse()
        ->and($sets[1]->set_number)->toBe(2)
        ->and((float) $sets[1]->weight)->toBe(65.0);
});

it('repeats an empty quick session without errors', function () {
    $user = User::factory()->create();
    $source = Workout::factory()->create(['user_id' => $user->id, 'ended_at' => now()]);

    $copy = sessions()->repeat($user, $source);

    expect($copy->exercises)->toHaveCount(0);
});

it('rejects repeating another users workout', function () {
    $user = User::factory()->create();
    $other = Workout::factory()->create();

    expect(fn () => sessions()->repeat($user, $other))
        ->toThrow(AuthorizationException::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter='repeats'`
Expected: FAIL — método `repeat()` no existe (undefined method).

- [ ] **Step 3: Implement `repeat()` en `WorkoutSessionService.php`**

Usar `$workout = $user->workouts()->create([...])` y luego un loop sobre `$source->exercises()->with('sets')->get()` creando workout-exercise + sets. `$source->routine_id` puede ser null (sesión rápida) — heredar tal cual.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Gym/WorkoutSessionServiceTest.php --compact`
Expected: PASS (3 tests nuevos + regresiones).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Gym/WorkoutSessionService.php tests/Feature/Gym/WorkoutSessionServiceTest.php
git commit -m "feat(gym): repeat a previous workout as a new session"
```

---

### Task 3: `logPast()` — registrar entrenamiento pasado

**Files:**
- Modify: `app/Services/Gym/WorkoutSessionService.php`
- Test: `tests/Feature/Gym/WorkoutSessionServiceTest.php`

**Interfaces:**
- Produces: `logPast(User $user, ?int $routineId, string $startedAt, ?string $notes = null): Workout` — workout **finalizado** (`started_at = ended_at = $startedAt`, notes), con template de rutina si `$routineId` (reusar `copyRoutineTemplate`, movimiento: hacer el método reutilizable tal como está, ya es privado y recibe `$user, $workout, $routine`). Rutina ajena → `ModelNotFoundException` (mismo patrón que `start()`).

- [ ] **Step 1: Write the failing tests**

```php
it('logs a past workout already finished at the chosen date', function () {
    $user = User::factory()->create();
    $date = now()->subDays(3)->setTime(18, 30);

    $workout = sessions()->logPast($user, null, $date->toIso8601String(), 'fue duro');

    expect($workout->started_at->toDateTimeString())->toBe($date->toDateTimeString())
        ->and($workout->ended_at->toDateTimeString())->toBe($date->toDateTimeString())
        ->and($workout->notes)->toBe('fue duro')
        ->and(PersonalRecord::count())->toBe(0);
});

it('logs a past workout copying the routine template', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $routine->exercises()->attach($exercise->id, ['order' => 1, 'target_sets' => 2, 'target_weight' => 60, 'target_reps' => '8']);

    $workout = sessions()->logPast($user, $routine->id, now()->subDays(2)->toIso8601String());

    expect($workout->exercises)->toHaveCount(1)
        ->and($workout->exercises->first()->sets()->count())->toBe(2)
        ->and($workout->ended_at)->not->toBeNull();
});

it('rejects logging a past workout with another users routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    expect(fn () => sessions()->logPast($user, $routine->id, now()->toIso8601String()))
        ->toThrow(ModelNotFoundException::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Gym/WorkoutSessionServiceTest.php --compact`
Expected: FAIL — método `logPast()` no existe.

- [ ] **Step 3: Implement `logPast()` en `WorkoutSessionService.php`**

```php
public function logPast(User $user, ?int $routineId, string $startedAt, ?string $notes = null): Workout
{
    $routine = null;

    if ($routineId) {
        $routine = Routine::with('exercises')
            ->where('user_id', $user->id)
            ->findOrFail($routineId);
    }

    $workout = $user->workouts()->create([
        'routine_id' => $routineId,
        'started_at' => $startedAt,
        'ended_at' => $startedAt,
        'notes' => $notes,
    ]);

    if ($routine) {
        $this->copyRoutineTemplate($user, $workout, $routine);
    }

    return $workout;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Gym/WorkoutSessionServiceTest.php --compact`
Expected: PASS (3 tests nuevos + regresiones).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Gym/WorkoutSessionService.php tests/Feature/Gym/WorkoutSessionServiceTest.php
git commit -m "feat(gym): log a past workout into history"
```

---

### Task 4: `progressionFor()` — evolución por ejercicio

**Files:**
- Modify: `app/Services/Gym/WorkoutSessionService.php`
- Test: `tests/Feature/Gym/WorkoutSessionServiceTest.php`

**Interfaces:**
- Produces: `progressionFor(User $user, Exercise $exercise): \Illuminate\Support\Collection` — ordenado por `started_at` asc, una entrada por workout terminado que contenga el ejercicio, shape: `{workout_id (int), date (string Y-m-d), best_weight (float), best_1rm (float, Epley redondeado 2), volume (float, sum weight*reps), total_reps (int), completed_sets (int)}`. Ignora workouts sin `ended_at`.

- [ ] **Step 1: Write the failing test**

```php
it('builds per-session progression for an exercise', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    $finished = Workout::factory()->create([
        'user_id' => $user->id,
        'started_at' => now()->subDays(2),
        'ended_at' => now()->subDays(2)->addHour(),
    ]);
    $we = $finished->exercises()->create(['exercise_id' => $exercise->id]);
    $we->sets()->create(['set_number' => 1, 'weight' => 100, 'reps' => 5, 'completed' => true]);
    $we->sets()->create(['set_number' => 2, 'weight' => 80, 'reps' => 12, 'completed' => true]);

    $open = Workout::factory()->create(['user_id' => $user->id, 'started_at' => now()]);
    $openWe = $open->exercises()->create(['exercise_id' => $exercise->id]);
    $openWe->sets()->create(['set_number' => 1, 'weight' => 999, 'reps' => 5, 'completed' => true]);

    $rows = sessions()->progressionFor($user, $exercise);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['workout_id'])->toBe($finished->id)
        ->and($rows[0]['best_weight'])->toBe(100.0)
        ->and($rows[0]['best_1rm'])->toBe(116.67)
        ->and($rows[0]['volume'])->toBe(1460.0)
        ->and($rows[0]['total_reps'])->toBe(17)
        ->and($rows[0]['completed_sets'])->toBe(2);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Gym/WorkoutSessionServiceTest.php --compact`
Expected: FAIL — método `progressionFor()` no existe.

- [ ] **Step 3: Implement `progressionFor()` en `WorkoutSessionService.php`**

Una query con joins: `WorkoutSet::query()->join('workout_exercises', …)->join('workouts', …)` con `where('workout_exercises.exercise_id', $exercise->id)`, `where('workouts.user_id', $user->id)`, `whereNotNull('workouts.ended_at')`, `orderBy('workouts.started_at')`; traer `workouts.id, workouts.started_at, workout_sets.weight, workout_sets.reps, workout_sets.completed` y agrupar en collection: `best_weight` = max de `(float) weight`, `best_1rm` = max de `round((float) weight * (1 + (int) reps / 30), 2)`, `volume` = sum de `(float) weight * (int) reps` (solo numéricos > 0), `total_reps` = sum de `(int) reps`, `completed_sets` = count completed. TODO el array final con valores casteados a float/int (el resultado de joins con decimales llega como string).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Gym/WorkoutSessionServiceTest.php --compact`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Gym/WorkoutSessionService.php tests/Feature/Gym/WorkoutSessionServiceTest.php
git commit -m "feat(gym): per-exercise progression query"
```

---

### Task 5: Rutas web + controlador + prop de rutinas en history

**Files:**
- Modify: `routes/web.php` (grupo `gym`, líneas 244-252)
- Modify: `app/Http/Controllers/Gym/WorkoutController.php`
- Test: `tests/Feature/Gym/WorkoutWebTest.php`

**Interfaces:**
- Consumes: `WorkoutAlreadyActiveException` (Task 1), `repeat()`, `logPast()`, `progressionFor()` (Tasks 2-4).
- Produces: rutas `POST gym/workouts/{workout}/repeat`, `POST gym/workouts/log-past`, `GET gym/exercises/{exercise}/progression`; `store()` responde 409 en conflicto; `history()` agrega prop `routines` (con `withCount('exercises')`).

- [ ] **Step 1: Write the failing tests**

```php
it('repeats a workout via web and blocks foreign ones', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();
    $source = Workout::factory()->create(['user_id' => $user->id, 'ended_at' => now()]);
    $we = $source->exercises()->create(['exercise_id' => $exercise->id]);
    $we->sets()->create(['set_number' => 1, 'weight' => 50, 'reps' => 10, 'completed' => true]);
    $other = Workout::factory()->create();

    $this->actingAs($user)->postJson("/gym/workouts/{$source->id}/repeat")
        ->assertCreated()
        ->assertJsonPath('exercises.0.sets.0.weight', '50.00');

    $this->actingAs($user)->postJson("/gym/workouts/{$other->id}/repeat")->assertForbidden();
});

it('logs a past workout via web with validation', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/gym/workouts/log-past', ['started_at' => now()->subDay()->toIso8601String()])
        ->assertCreated()
        ->assertJsonPath('ended_at', now()->subDay()->toIso8601String());

    $this->actingAs($user)->postJson('/gym/workouts/log-past', ['started_at' => now()->addDay()->toIso8601String()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['started_at']);

    $this->actingAs($user)->postJson('/gym/workouts/log-past', ['started_at' => now()->subDay()->toIso8601String(), 'routine_id' => Routine::factory()->create()->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['routine_id']);
});

it('returns 409 when starting a routine with an active workout', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $active = Workout::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->postJson('/gym/workouts', ['routine_id' => $routine->id])
        ->assertStatus(409)
        ->assertJsonPath('active_workout.id', $active->id);
});

it('exposes a routines list on the history page', function () {
    $user = User::factory()->create();
    Routine::factory()->create(['user_id' => $user->id, 'name' => 'Pecho']);

    $this->actingAs($user)->get('/fitness/history')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('fitness/history')
            ->has('routines', 1)
            ->where('routines.0.name', 'Pecho'));
});

it('serves the progression payload for an exercise', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id, 'ended_at' => now()]);
    $we = $workout->exercises()->create(['exercise_id' => $exercise->id]);
    $we->sets()->create(['set_number' => 1, 'weight' => 100, 'reps' => 5, 'completed' => true]);

    $this->actingAs($user)->getJson("/gym/exercises/{$exercise->id}/progression")
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.best_weight', 100);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Gym/WorkoutWebTest.php --compact`
Expected: FAIL — rutas 404 (no declaradas).

- [ ] **Step 3: Declare las rutas en `routes/web.php` AL INICIO del grupo `gym` (ANTES de los apiResource, para que `GET exercises/{exercise}/progression` no capture `progression` como `{exercise}`)**

```php
Route::prefix('gym')->group(function () {
    Route::post('workouts/{workout}/repeat', [WorkoutController::class, 'repeat']);
    Route::post('workouts/log-past', [WorkoutController::class, 'logPast']);
    Route::get('exercises/{exercise}/progression', [WorkoutController::class, 'progression']);
    Route::apiResource('exercises', ExerciseController::class);
    // ... resto igual
});
```

- [ ] **Step 4: Implement `repeat()` y `logPast()` en `WorkoutController.php`**

`repeat(Request, Workout $workout)`: `$this->authorize('view', $workout);` → `$this->sessions->repeat($request->user(), $workout)` → `wantsJson()` ? `response()->json($this->loadWorkoutWithHistory($workout), 201)` : `redirect()->back()`.
`logPast(Request)`: validar `routine_id` (nullable, `Rule::exists('routines', 'id')->where('user_id', $request->user()->id)`), `started_at` (`required|date|before_or_equal:now`), `notes` (nullable); llamar servicio; responder 201 JSON con `loadWorkoutWithHistory` o redirect.
`progression(Request, Exercise $exercise)`: `return response()->json($this->sessions->progressionFor($request->user(), $exercise));`
En `store()`: envolver `$this->sessions->start(...)` en try/catch de `WorkoutAlreadyActiveException` → JSON: 409 `{message, active_workout: loadWorkoutWithHistory($e->workout)}`; Inertia (no JSON): `back()->withErrors(['workout' => 'Ya tienes un entrenamiento activo.'])` (el error llega en `page.props.errors.workout`).

- [ ] **Step 5: Agregar prop `routines` en `history()`**

`$request->user()->routines()->withCount('exercises')->get()` — junto a las props existentes `workouts` y `personalRecords`.

- [ ] **Step 6: Run tests (file completo)**

Run: `php artisan test tests/Feature/Gym/WorkoutWebTest.php --compact`
Expected: PASS — tests nuevos + regresiones (incluido `rejects a routine from another user when starting a workout`, que debe seguir 422 para rutina ajena y 409 solo para conflicto activo con rutina propia).

Nota: el test existente `rejects a routine from another user...` usa una rutina de OTRO usuario sin workout activo → sigue siendo 422 exitoso tras el cambio.

- [ ] **Step 7: Pint + Commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/web.php app/Http/Controllers/Gym/WorkoutController.php tests/Feature/Gym/WorkoutWebTest.php
git commit -m "feat(gym): repeat, log-past and progression routes with conflict 409"
```

---

### Task 6: UI `gym-routine.tsx` — picker de rutina, dialog de conflicto, modal de progresión

**Files:**
- Modify: `resources/js/pages/fitness/gym-routine.tsx`

**Interfaces:**
- Consumes: rutas `POST gym/workouts`, `POST gym/workouts/{id}/repeat` (no), `PATCH gym/workouts/{id}` (existente), `GET gym/exercises/{id}/progression` (JSON), prop `routines` (ya existe en la página: `any[]` con `exercises`).

- [ ] **Step 1: Types + estado**

Agregar interfaces `RoutineOption { id: number; name: string; focus: string; scheduled_date: string | null; exercises: { name: string; pivot?: { target_sets?: number; target_reps?: string; target_weight?: string } }[] }` y `ProgressionRow { workout_id: number; date: string; best_weight: number; best_1rm: number; volume: number; total_reps: number; completed_sets: number }`. Estado nuevo: `routinePickerOpen`, `conflictWorkout: Workout | null` (workout activo del 409), `progressionFor: { exerciseName: string; rows: ProgressionRow[]; loading: boolean; error: string } | null`.

- [ ] **Step 2: Picker — detonadores**

Toolbar: botón "Rutina" (`edit_calendar`) junto a "Historial" → abre el picker (también visible con workout activo: permite "empezar otra" → dispara el conflicto si hay activo). Estado vacío (sin `activeWorkout` y sin `suggestedRoutine`): reemplazar el CTA único por dos — primario "Elegir rutina" (abre picker) y secundario "Sesión rápida" (mantiene `router.post('/gym/workouts')` actual).

- [ ] **Step 3: Picker — modal**

`Dialog open={routinePickerOpen}` con lista de rutinas (de `routines`): card por rutina (nombre, focus, badge día, `N ejercicios`) con preview expandible (primer click selecciona/expande → lista `name · target_sets × target_reps · target_weight kg`); CTA por rutina "Empezar con esta rutina" → `router.post('/gym/workouts', { routine_id }, { onSuccess: () => { setRoutinePickerOpen(false); router.visit('/fitness/gym', { preserveScroll: false }); } })`. Empty state: "Todavía no tenés rutinas" + link a `/fitness/routines`. Skeleton mientras carga no aplica (prop ya está).

- [ ] **Step 4: Dialog de conflicto**

En el `onError` del POST del picker: si `(page.props.errors as any).workout` está presente (conflicto 409 Inertia), resolver el workout activo con `fetch('/gym/workouts', { headers: { Accept: 'application/json' } })` → primera entrada con `ended_at === null` → `setConflictWorkout(...)`; abrir `Dialog` "Ya tenés un entrenamiento activo" con 2 acciones:
- "Continuar el activo" → cierra el dialog y navega a `/fitness/gym`.
- "Terminar y empezar la rutina" → `PATCH /gym/workouts/{activeWorkout.id}` `{ended_at: new Date().toISOString()}`; onSuccess → `POST /gym/workouts {routine_id}` → onSuccess → `router.visit('/fitness/gym')`.

- [ ] **Step 5: Modal de progresión**

Icono `history` de cada card (hoy `<span title="View History">`) → `<button>` que abre el modal progresión con el `exercise_id`/nombre. Al abrir: `fetch('/gym/exercises/${id}/progression', { headers: { Accept: 'application/json' } })` (patrón fetch existente en la app); estados `loading` (skeleton pulsante `animate-pulse`), `error` (mensaje + "Reintentar"), `empty` ("Sin sesiones previas para este ejercicio"). Con datos: sparkline SVG inline de `best_weight` por fila (polyline + puntos con `fill` `#EF4444`, área con `fillOpacity` bajo, labels de fecha cada N puntos en `<text>` chico `#E8B4B4`) + tabla compacta debajo (Fecha · Peso máx · 1RM · Tonelaje · Sets completados; números `tabular-nums`).

- [ ] **Step 6: Build + revisión visual**

Run: `npm run build`
Expected: build OK. Con `php artisan serve` y el navegador (patrón QA del repo): crear una rutina si no hay, abrir picker, empezar rutina, verificar ejercicios cargados en la sesión; abrir progresión en un ejercicio con historial y en uno sin historial (estados).

- [ ] **Step 7: Lint + Commit**

```bash
npx eslint resources/js/pages/fitness/gym-routine.tsx
git add resources/js/pages/fitness/gym-routine.tsx
git commit -m "feat(gym): routine picker, active-workout conflict dialog and exercise progression modal"
```

---

### Task 7: UI `history.tsx` — agregar entrenamiento anterior, repetir, filas expandibles

**Files:**
- Modify: `resources/js/pages/fitness/history.tsx`

**Interfaces:**
- Consumes: prop `routines` (Task 5: `{id, name, focus, scheduled_date, exercises_count}`), rutas `POST gym/workouts/log-past`, `POST gym/workouts/{id}/repeat`.

- [ ] **Step 1: Modal "Agregar entrenamiento anterior"**

Botón en el toolbar (junto a "Volver a Entrenamiento") con icono `history_edu` y texto "Agregar entrenamiento anterior". `Dialog` con: `Select` de rutinas (opciones: cada rutina + "Sesión rápida" como valor vacío), `Input type="datetime-local"` (max = fecha-hora actual, default hoy), `Input`/`Textarea` notas → `router.post('/gym/workouts/log-past', { routine_id, started_at, notes }, { onSuccess: () => { cerrar modal; router.reload({ only: ['workouts'] }); } })`. Mostrar `errors` de validación bajo cada campo.

- [ ] **Step 2: Acción "Repetir" por fila**

En cada fila, columna/acción nueva: botón icono `refresh` + title "Repetir este entrenamiento" → confirm() → `router.post('/gym/workouts/${w.id}/repeat', {}, { onSuccess: () => router.visit('/fitness/gym') })`.

- [ ] **Step 3: Filas expandibles**

Chevron en cada fila (rotación al expandir, estado local por id): al expandir, detalle con cada ejercicio (nombre) y sus sets (`N · weight kg × reps · rpe`, badge "completado" si `completed`) — usa `w.exercises[].sets` ya presente en props, sin requests. Empty extiendo sin cambios.

- [ ] **Step 4: Build + revisión visual**

Run: `npm run build`
Expected: build OK. Navegador: registrar un entrenamiento pasado (aparece en historial en la fecha elegida), repetir una fila (navega a la sesión con series cargadas), expandir filas.

- [ ] **Step 5: Lint + Commit**

```bash
npx eslint resources/js/pages/fitness/history.tsx
git add resources/js/pages/fitness/history.tsx
git commit -m "feat(gym): history log-past modal, repeat action and expandable rows"
```

---

### Task 8: Datos — re-seed de ejercicios, `GymDemoSeeder` y docs

**Files:**
- Create: `database/seeders/GymDemoSeeder.php`
- Modify: `docs/modules/gym.md`

**Interfaces:**
- Consumes: `ExerciseSeeder` (existente, firstOrCreate), usuarios de `DatabaseSeeder` (`test@example.com`).

- [ ] **Step 1: Restaurar la librería canónica en dev**

Run: `php artisan db:seed --class=ExerciseSeeder`
Expected: `Exercise::count() === 37` (firstOrCreate, idempotente).

- [ ] **Step 2: Crear `GymDemoSeeder.php`**

`run()`: buscar `User::where('email', 'test@example.com')->firstOrFail()`; `Routine::firstOrCreate(['user_id' => $user->id, 'name' => 'Pecho y Tríceps'])` con ejercicios del seeder (ej. "Press banca", "Aperturas con mancuernas", "Press francés") y targets (3×8-12, 60kg); `Routine::firstOrCreate(['user_id' => $user->id, 'name' => 'Pierna y Core'])` con "Sentadilla", "Prensa de piernas", "Plancha abdominal" y `scheduled_date` = `now()->format('l')`. Si `Workout::where('user_id', $user->id)->count() === 0`: 2 workouts finalizados (hace 3 y 7 días) con ejercicios de esas rutinas y 2-3 sets cada uno (`completed=true`, pesos distintos para que la progresión muestre evolución). Guard al inicio: `if (Routine::where('user_id', $user->id)->exists() && Workout::where('user_id', $user->id)->exists()) { return; }` (idempotente para el Review Focus 5).

- [ ] **Step 3: Documentar en `docs/modules/gym.md`**

Sección "Rework 2026-10-03": rutas nuevas (`repeat`, `log-past`, `progression`), flujo del picker, y uso de `GymDemoSeeder` (correr tras `db:seed`: `php artisan db:seed --class=GymDemoSeeder`).

- [ ] **Step 4: Commit**

```bash
git add database/seeders/GymDemoSeeder.php docs/modules/gym.md
git commit -m "feat(gym): demo seeder for QA data and module docs"
```

---

### Task 9: QA final

**Files:** ninguno (verificación).

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: todos los tests pasan (suite actual 1060+ + nuevos).

- [ ] **Step 2: Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes (o aplica formato y re-commit si hizo cambios).

- [ ] **Step 3: Build + eslint**

Run: `npm run build` y `npx eslint resources/js/pages/fitness/gym-routine.tsx resources/js/pages/fitness/history.tsx`
Expected: build OK; eslint con solo los errores preexistentes conocidos (16 en gym-routine, patrón documentado en docs/qa).

- [ ] **Step 4: QA Playwright (flujo AGENTS.md del módulo gym)**

Correr `php artisan serve :8010` + navegador: Landing → Login (`test@example.com`/`password`) → Gym: "Elegir rutina" → ejercicios + series cargados → Finish Workout → History: "Agregar entrenamiento anterior" (lanza en fecha pasada) → "Repetir" una fila → progresión de un ejercicio con y sin historial. Verificar: 409 dialog con workout activo; rutinas visibles; historial paginado; sin errores de consola.