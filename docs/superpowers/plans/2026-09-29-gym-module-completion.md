# Gym Module Completion — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Completar el módulo de entrenamientos: servicios de dominio compartidos, escritura completa desde el chat, endpoints web/API/MCP, PR timeline en BD/UI, y cierre del IDOR de las rutas web Gym.

**Architecture:** Un `app/Services/Gym/` (WorkoutSessionService, RoutineService, PersonalRecordService, ExerciseResolver) como única fuente de verdad; web/API/MCP/chat son adaptadores finos. Policies de Laravel para ownership en web; chat con `GymQueryTool` + `GymActionTool` (aprobable) dentro del grupo `workout`.

**Tech Stack:** Laravel 12, PHP 8.4, Eloquent, Pest 4, laravel/ai ^0.11, laravel/mcp ^0.9.4, Sanctum, Inertia v2 + React 19 + Wayfinder.

**Spec:** `docs/superpowers/specs/2026-09-29-gym-module-completion-design.md`

## Global Constraints

- PHP 8.4, Laravel 12, Pest 4. Tests con `RefreshDatabase` (ya aplicado globalmente a `tests/Feature` por `tests/Pest.php`).
- Eloquent: usar método `casts()` (no propiedad `$casts`) en modelos nuevos/tocados. Sin `DB::` en código de app (solo migraciones pueden usar `DB::table`).
- API v1: Form Requests en `app/Http/Requests/Api/`; Resources en `app/Http/Resources/`.
- No romper contratos existentes: `WorkoutSetResource` serializa `weight`/`rpe` como string decimal (`"50.00"`) — hay tests que lo afirman.
- Ember palette: no introducir colores nuevos en UI; usar `bg-primary`, `border-[#3e2121]`, `text-[#e8b4b4]` como los archivos existentes.
- Correr tests por archivo con `php artisan test --compact <path>`; antes de cerrar cada tarea con tests que toquen varias suites, usar el filtro correspondiente.
- Al final de cada tarea con cambios PHP: `vendor/bin/pint --dirty --format agent`.
- Cada tarea termina en un commit propio (el mensaje propuesto está en el último paso de cada tarea).
- Ejecutar en orden: las tareas posteriores asumen las firmas de las anteriores.

## File Structure

**Nuevos**
- `database/migrations/2026_09_29_100000_add_gym_indexes_and_unique.php` — dedupe + unique + índices.
- `database/migrations/2026_09_29_100100_create_personal_records_table.php` — tabla PR.
- `app/Models/PersonalRecord.php`
- `database/factories/PersonalRecordFactory.php`
- `database/seeders/ExerciseSeeder.php`
- `app/Services/Gym/ExerciseResolver.php`
- `app/Services/Gym/WorkoutSessionService.php`
- `app/Services/Gym/PersonalRecordService.php`
- `app/Services/Gym/RoutineService.php`
- `app/Policies/WorkoutPolicy.php`
- `app/Policies/RoutinePolicy.php`
- `app/Ai/Tools/GymQueryTool.php` (reemplaza a `WorkoutQueryTool.php`)
- `app/Ai/Tools/GymActionTool.php`
- `tests/Feature/Gym/GymDataTest.php`
- `tests/Feature/Gym/WorkoutSessionServiceTest.php`
- `tests/Feature/Gym/PersonalRecordServiceTest.php`
- `tests/Feature/Gym/RoutineServiceTest.php`
- `tests/Feature/Gym/GymPolicyTest.php`
- `tests/Feature/Gym/WorkoutWebTest.php`
- `tests/Feature/Gym/RoutineWebTest.php`
- `tests/Feature/Mcp/WorkoutToolsTest.php`
- `tests/Feature/Ai/GymQueryToolTest.php`
- `tests/Feature/Ai/GymActionToolTest.php`

**Modificados**
- `app/Models/Workout.php`, `WorkoutSet.php`, `WorkoutExercise.php` — `casts()`.
- `database/seeders/DatabaseSeeder.php` — llamar `ExerciseSeeder`.
- `app/Http/Controllers/Gym/WorkoutController.php` — servicios + policies + endpoints delete + `is_pr`/`best_weight` + prop `personalRecords`.
- `app/Http/Controllers/Gym/RoutineController.php` — `RoutineService` + policies.
- `app/Http/Controllers/Gym/ExerciseController.php` — `app(WorkoutController::class)` en vez de `new`.
- `app/Http/Controllers/Api/V1/WorkoutController.php` — servicio + endpoints addExercise/logSet.
- `app/Http/Controllers/Api/V1/RoutineController.php` — servicio + update/destroy.
- `app/Http/Requests/Api/StoreWorkoutRequest.php` — `routine_id` scopeado.
- `app/Http/Requests/Api/StoreRoutineRequest.php` — (revisar) sin cambios de contrato.
- `app/Http/Requests/Api/UpdateRoutineRequest.php` — nuevo (si no existe patrón inline, usar Form Request).
- `routes/web.php` — DELETE workout-exercises/workout-sets.
- `routes/api.php` — endpoints nuevos + routines update/destroy.
- `app/Mcp/Tools/WorkoutWriteTool.php` — servicios + acciones nuevas.
- `app/Mcp/Tools/WorkoutReadTool.php` — `exercise_id` + rutinas.
- `app/Ai/Tools/ToolCatalog.php` — grupo workout con ambas tools.
- `config/ai_tools.php` — verbos + keywords.
- `app/Ai/Agents/MegalomaniacAgent.php` — instrucciones gym.
- `resources/js/pages/fitness/gym-routine.tsx` — video/delete/badge.
- `resources/js/pages/fitness/history.tsx` — PR timeline.
- `resources/js/lib/chat-tools.ts` — labels.
- Tests existentes a actualizar: `tests/Feature/Ai/ToolCatalogTest.php`, `tests/Feature/Ai/ToolRouterTest.php`, `tests/Feature/Ai/MegalomaniacAgentTest.php`, `tests/Feature/Agents/AgentRunnerTest.php`, `tests/Feature/Ai/ChatApprovalTest.php`.

**Eliminados**
- `app/Models/RoutineExercise.php` (clase muerta).
- `app/Ai/Tools/WorkoutQueryTool.php` (renombrado a `GymQueryTool`).

---

### Task 1: Base de datos — índices, unique y `personal_records`

**Files:**
- Create: `database/migrations/2026_09_29_100000_add_gym_indexes_and_unique.php`
- Create: `database/migrations/2026_09_29_100100_create_personal_records_table.php`
- Create: `app/Models/PersonalRecord.php`
- Create: `database/factories/PersonalRecordFactory.php`
- Create: `database/seeders/ExerciseSeeder.php`
- Modify: `app/Models/Workout.php`, `app/Models/WorkoutSet.php`, `app/Models/WorkoutExercise.php` (casts)
- Modify: `database/seeders/DatabaseSeeder.php`
- Delete: `app/Models/RoutineExercise.php`
- Test: `tests/Feature/Gym/GymDataTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: tabla `personal_records`; modelo `App\Models\PersonalRecord` con fillable `user_id, exercise_id, workout_set_id, type, value, reps, weight, achieved_at` y relaciones `user()`, `exercise()`, `workoutSet()`; factory `PersonalRecordFactory`; seeder `ExerciseSeeder`.

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Gym/GymDataTest.php`:

```php
<?php

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Database\QueryException;

uses(RefreshDatabase::class);

it('enforces one set per set_number inside a workout exercise', function () {
    $workoutExercise = WorkoutExercise::factory()->create();

    WorkoutSet::factory()->create([
        'workout_exercise_id' => $workoutExercise->id,
        'set_number' => 1,
    ]);

    expect(fn () => WorkoutSet::factory()->create([
        'workout_exercise_id' => $workoutExercise->id,
        'set_number' => 1,
    ]))->toThrow(QueryException::class);
});

it('seeds a canonical exercise library', function () {
    $this->seed(ExerciseSeeder::class);

    expect(Exercise::count())->toBeGreaterThanOrEqual(30);
    expect(Exercise::where('name', 'Press banca')->exists())->toBeTrue();
});

it('casts workout set reps to integer', function () {
    $set = WorkoutSet::factory()->create(['reps' => '10']);

    expect($set->refresh()->reps)->toBeInt()->toBe(10);
});

it('stores personal records with exercise relation', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);
    $set = $workoutExercise->sets()->create(['set_number' => 1, 'weight' => 80, 'reps' => 5, 'completed' => true]);

    $record = PersonalRecord::factory()->create([
        'user_id' => $user->id,
        'exercise_id' => $workoutExercise->exercise_id,
        'workout_set_id' => $set->id,
        'type' => 'weight',
        'value' => 80,
        'weight' => 80,
        'reps' => 5,
    ]);

    expect($record->exercise)->toBeInstanceOf(Exercise::class)
        ->and($record->workoutSet->id)->toBe($set->id)
        ->and((float) $record->value)->toBe(80.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Gym/GymDataTest.php`
Expected: FAIL — `QueryException` no se lanza (no hay unique), `personal_records` no existe.

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_09_29_100000_add_gym_indexes_and_unique.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('workout_sets')
            ->select('workout_exercise_id', 'set_number', DB::raw('MAX(id) as keep_id'))
            ->groupBy('workout_exercise_id', 'set_number')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('workout_sets')
                ->where('workout_exercise_id', $duplicate->workout_exercise_id)
                ->where('set_number', $duplicate->set_number)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('workout_sets', function (Blueprint $table) {
            $table->unique(['workout_exercise_id', 'set_number'], 'workout_sets_exercise_set_unique');
        });

        Schema::table('workouts', function (Blueprint $table) {
            $table->index(['user_id', 'started_at'], 'workouts_user_started_index');
        });

        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->index(['workout_id', 'order'], 'workout_exercises_workout_order_index');
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->index('name', 'exercises_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sets', function (Blueprint $table) {
            $table->dropUnique('workout_sets_exercise_set_unique');
        });

        Schema::table('workouts', function (Blueprint $table) {
            $table->dropIndex('workouts_user_started_index');
        });

        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropIndex('workout_exercises_workout_order_index');
        });

        Schema::table('exercises', function (Blueprint $table) {
            $table->dropIndex('exercises_name_index');
        });
    }
};
```

`database/migrations/2026_09_29_100100_create_personal_records_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_set_id')->nullable()->constrained('workout_sets')->nullOnDelete();
            $table->string('type');
            $table->decimal('value', 8, 2);
            $table->integer('reps')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->dateTime('achieved_at');
            $table->timestamps();

            $table->index(['user_id', 'exercise_id', 'achieved_at'], 'personal_records_user_exercise_achieved_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_records');
    }
};
```

- [ ] **Step 4: Create model + factory**

`app/Models/PersonalRecord.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exercise_id',
        'workout_set_id',
        'type',
        'value',
        'reps',
        'weight',
        'achieved_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'weight' => 'decimal:2',
            'reps' => 'integer',
            'achieved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function workoutSet(): BelongsTo
    {
        return $this->belongsTo(WorkoutSet::class);
    }
}
```

`database/factories/PersonalRecordFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalRecord>
 */
class PersonalRecordFactory extends Factory
{
    protected $model = PersonalRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exercise_id' => Exercise::factory(),
            'workout_set_id' => null,
            'type' => 'weight',
            'value' => $this->faker->randomFloat(2, 20, 120),
            'reps' => $this->faker->numberBetween(1, 12),
            'weight' => $this->faker->randomFloat(2, 20, 120),
            'achieved_at' => now(),
        ];
    }
}
```

- [ ] **Step 5: Convert casts and delete dead model**

En `app/Models/Workout.php`, reemplazar:

```php
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
```

por:

```php
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
```

En `app/Models/WorkoutSet.php`, reemplazar:

```php
    protected $casts = [
        'completed' => 'boolean',
        'weight' => 'decimal:2',
        'rpe' => 'decimal:1',
    ];
```

por:

```php
    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'weight' => 'decimal:2',
            'rpe' => 'decimal:1',
            'reps' => 'integer',
        ];
    }
```

En `app/Models/WorkoutExercise.php`, agregar antes de `workout()`:

```php
    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }
```

Borrar `app/Models/RoutineExercise.php`:

```bash
rm app/Models/RoutineExercise.php
```

- [ ] **Step 6: Create the exercise seeder**

`database/seeders/ExerciseSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const EXERCISES = [
        ['Press banca', 'Chest', 'Compound'],
        ['Press banca inclinado', 'Chest', 'Compound'],
        ['Press banca declinado', 'Chest', 'Compound'],
        ['Aperturas con mancuernas', 'Chest', 'Isolation'],
        ['Fondos en paralelas', 'Chest', 'Bodyweight'],
        ['Press militar', 'Shoulders', 'Compound'],
        ['Elevaciones laterales', 'Shoulders', 'Isolation'],
        ['Elevaciones frontales', 'Shoulders', 'Isolation'],
        ['Pájaros con mancuernas', 'Shoulders', 'Isolation'],
        ['Press Arnold', 'Shoulders', 'Compound'],
        ['Dominadas', 'Back', 'Bodyweight'],
        ['Remo con barra', 'Back', 'Compound'],
        ['Remo con mancuerna', 'Back', 'Compound'],
        ['Jalón al pecho', 'Back', 'Machine'],
        ['Peso muerto', 'Back', 'Compound'],
        ['Pullover en polea', 'Back', 'Isolation'],
        ['Sentadilla', 'Legs', 'Compound'],
        ['Sentadilla frontal', 'Legs', 'Compound'],
        ['Prensa de piernas', 'Legs', 'Machine'],
        ['Zancadas', 'Legs', 'Compound'],
        ['Peso muerto rumano', 'Legs', 'Compound'],
        ['Curl femoral', 'Legs', 'Machine'],
        ['Extensión de cuádriceps', 'Legs', 'Machine'],
        ['Elevación de gemelos', 'Legs', 'Isolation'],
        ['Hip thrust', 'Legs', 'Compound'],
        ['Curl de bíceps con barra', 'Arms', 'Isolation'],
        ['Curl de bíceps alterno', 'Arms', 'Isolation'],
        ['Curl martillo', 'Arms', 'Isolation'],
        ['Curl predicador', 'Arms', 'Isolation'],
        ['Extensión de tríceps en polea', 'Arms', 'Isolation'],
        ['Press francés', 'Arms', 'Isolation'],
        ['Fondos en banco', 'Arms', 'Bodyweight'],
        ['Patada de tríceps', 'Arms', 'Isolation'],
        ['Plancha abdominal', 'Core', 'Bodyweight'],
        ['Crunch en polea', 'Core', 'Isolation'],
        ['Elevación de piernas colgado', 'Core', 'Bodyweight'],
        ['Rueda abdominal', 'Core', 'Bodyweight'],
    ];

    public function run(): void
    {
        foreach (self::EXERCISES as [$name, $muscleGroup, $type]) {
            Exercise::firstOrCreate(
                ['name' => $name],
                ['muscle_group' => $muscleGroup, 'type' => $type],
            );
        }
    }
}
```

En `database/seeders/DatabaseSeeder.php`, agregar tras `$this->call(PersonalSeeder::class);`:

```php
        $this->call(ExerciseSeeder::class);
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Gym/GymDataTest.php`
Expected: PASS (4 tests).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations database/seeders database/factories app/Models tests/Feature/Gym/GymDataTest.php
git commit -m "feat(gym): personal records table, gym indexes and exercise seeder"
```

---

### Task 2: `ExerciseResolver` + `WorkoutSessionService` (sin PR)

**Files:**
- Create: `app/Services/Gym/ExerciseResolver.php`
- Create: `app/Services/Gym/WorkoutSessionService.php`
- Test: `tests/Feature/Gym/WorkoutSessionServiceTest.php`

**Interfaces:**
- Consumes: modelos `Workout`, `WorkoutExercise`, `WorkoutSet`, `Routine`, `Exercise`.
- Produces (usado por todas las tareas siguientes):
  - `ExerciseResolver::resolve(?int $exerciseId = null, ?string $exerciseName = null, array $attributes = []): Exercise`
  - `WorkoutSessionService::activeFor(User $user): ?Workout`
  - `WorkoutSessionService::start(User $user, ?int $routineId = null, ?string $startedAt = null, ?string $notes = null): Workout`
  - `WorkoutSessionService::addExercise(User $user, Workout $workout, ?int $exerciseId = null, ?string $exerciseName = null): WorkoutExercise`
  - `WorkoutSessionService::logSet(User $user, WorkoutExercise $workoutExercise, array $data): WorkoutSet`
  - `WorkoutSessionService::update(User $user, Workout $workout, array $attributes): Workout`
  - `WorkoutSessionService::finish(User $user, Workout $workout, ?string $endedAt = null, ?string $notes = null): Workout`
  - `WorkoutSessionService::removeExercise(User $user, WorkoutExercise $workoutExercise): void`
  - `WorkoutSessionService::removeSet(User $user, WorkoutSet $set): void`
  - `WorkoutSessionService::delete(User $user, Workout $workout): void`

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Gym/WorkoutSessionServiceTest.php`:

```php
<?php

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Auth\Access\AuthorizationException;

uses(RefreshDatabase::class);

function sessions(): WorkoutSessionService
{
    return app(WorkoutSessionService::class);
}

it('starts a workout and returns the active one afterwards', function () {
    $user = User::factory()->create();

    $workout = sessions()->start($user, null, now()->toIso8601String());

    expect($workout->user_id)->toBe($user->id)
        ->and($workout->ended_at)->toBeNull()
        ->and(sessions()->activeFor($user)->id)->toBe($workout->id)
        ->and(sessions()->start($user)->id)->toBe($workout->id);
});

it('copies the routine template and prefills sets from previous workouts', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $routine->exercises()->attach($exercise->id, [
        'order' => 1,
        'target_sets' => 2,
        'target_weight' => 60,
        'target_reps' => '8',
    ]);

    // Previous finished workout with a logged set.
    $previous = Workout::factory()->create(['user_id' => $user->id, 'ended_at' => now()]);
    $previousExercise = $previous->exercises()->create(['exercise_id' => $exercise->id, 'order' => 1]);
    $previousExercise->sets()->create(['set_number' => 1, 'weight' => 55, 'reps' => 9, 'completed' => true]);

    $workout = sessions()->start($user, $routine->id);

    expect($workout->exercises)->toHaveCount(1);

    $sets = $workout->exercises->first()->sets()->orderBy('set_number')->get();

    expect($sets)->toHaveCount(2)
        ->and($sets[0]->set_number)->toBe(1)
        ->and((float) $sets[0]->weight)->toBe(55.0)
        ->and($sets[0]->reps)->toBe(9)
        ->and((float) $sets[1]->weight)->toBe(60.0)
        ->and((float) $sets[1]->reps)->toBe(8.0)
        ->and($sets[1]->completed)->toBeFalse();
});

it('rejects starting from another users routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    expect(fn () => sessions()->start($user, $routine->id))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('adds an exercise by name, creating it once', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $first = sessions()->addExercise($user, $workout, null, 'Face pull');
    $second = sessions()->addExercise($user, $workout, null, 'face pull');

    expect($first->exercise_id)->toBe($second->exercise_id)
        ->and(Exercise::where('name', 'Face pull')->count())->toBe(1)
        ->and($first->order)->toBe(1)
        ->and($second->order)->toBe(2);
});

it('logs sets with automatic numbering and updates existing ones', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);

    $first = sessions()->logSet($user, $workoutExercise, ['weight' => 40, 'reps' => 10]);
    $second = sessions()->logSet($user, $workoutExercise, ['weight' => 45, 'reps' => 8]);

    expect($first->set_number)->toBe(1)
        ->and($second->set_number)->toBe(2);

    $updated = sessions()->logSet($user, $workoutExercise, ['set_number' => 1, 'weight' => 42]);

    expect($updated->id)->toBe($first->id)
        ->and((float) $updated->refresh()->weight)->toBe(42.0);
});

it('blocks writes on workouts that belong to another user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $other->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);
    $set = $workoutExercise->sets()->create(['set_number' => 1]);

    expect(fn () => sessions()->addExercise($user, $workout, 1))
        ->toThrow(AuthorizationException::class);

    expect(fn () => sessions()->logSet($user, $workoutExercise, []))
        ->toThrow(AuthorizationException::class);

    expect(fn () => sessions()->removeSet($user, $set))
        ->toThrow(AuthorizationException::class);

    expect(fn () => sessions()->delete($user, $workout))
        ->toThrow(AuthorizationException::class);
});

it('finishes, removes and deletes workout parts', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);
    $set = $workoutExercise->sets()->create(['set_number' => 1]);

    sessions()->removeSet($user, $set);
    expect($set->exists)->toBeFalse();

    sessions()->removeExercise($user, $workoutExercise);
    expect($workoutExercise->exists)->toBeFalse();

    sessions()->finish($user, $workout, null, 'done');
    expect($workout->refresh()->ended_at)->not->toBeNull()
        ->and($workout->notes)->toBe('done');

    sessions()->delete($user, $workout);
    expect($workout->exists)->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Gym/WorkoutSessionServiceTest.php`
Expected: FAIL — `Class "App\Services\Gym\WorkoutSessionService" not found`.

- [ ] **Step 3: Implement `ExerciseResolver`**

`app/Services/Gym/ExerciseResolver.php`:

```php
<?php

namespace App\Services\Gym;

use App\Models\Exercise;
use InvalidArgumentException;

class ExerciseResolver
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resolve(?int $exerciseId = null, ?string $exerciseName = null, array $attributes = []): Exercise
    {
        if ($exerciseId) {
            return Exercise::findOrFail($exerciseId);
        }

        if ($exerciseName === null || trim($exerciseName) === '') {
            throw new InvalidArgumentException('Either exercise_id or exercise_name is required.');
        }

        $name = trim($exerciseName);

        return Exercise::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first()
            ?? Exercise::create([
                'name' => $name,
                'muscle_group' => $attributes['muscle_group'] ?? null,
                'type' => $attributes['type'] ?? null,
            ]);
    }
}
```

- [ ] **Step 4: Implement `WorkoutSessionService`**

`app/Services/Gym/WorkoutSessionService.php`:

```php
<?php

namespace App\Services\Gym;

use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;

class WorkoutSessionService
{
    public function __construct(protected ExerciseResolver $resolver) {}

    public function activeFor(User $user): ?Workout
    {
        return $user->workouts()
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();
    }

    public function start(User $user, ?int $routineId = null, ?string $startedAt = null, ?string $notes = null): Workout
    {
        if ($active = $this->activeFor($user)) {
            return $active;
        }

        $routine = null;

        if ($routineId) {
            $routine = Routine::with('exercises')
                ->where('user_id', $user->id)
                ->findOrFail($routineId);
        }

        $workout = $user->workouts()->create([
            'routine_id' => $routineId,
            'started_at' => $startedAt ?? now(),
            'notes' => $notes,
        ]);

        if ($routine) {
            $this->copyRoutineTemplate($user, $workout, $routine);
        }

        return $workout;
    }

    public function addExercise(User $user, Workout $workout, ?int $exerciseId = null, ?string $exerciseName = null): WorkoutExercise
    {
        $this->assertOwnsWorkout($user, $workout);

        $exercise = $this->resolver->resolve($exerciseId, $exerciseName);

        $order = ($workout->exercises()->max('order') ?? 0) + 1;

        return $workout->exercises()->create([
            'exercise_id' => $exercise->id,
            'order' => $order,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function logSet(User $user, WorkoutExercise $workoutExercise, array $data): WorkoutSet
    {
        $this->assertOwnsWorkoutExercise($user, $workoutExercise);

        $setNumber = $data['set_number']
            ?? (($workoutExercise->sets()->max('set_number') ?? 0) + 1);

        return $workoutExercise->sets()->updateOrCreate(
            ['set_number' => $setNumber],
            Arr::only($data, ['weight', 'reps', 'rpe', 'completed']),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, Workout $workout, array $attributes): Workout
    {
        $this->assertOwnsWorkout($user, $workout);

        $workout->update(Arr::only($attributes, ['routine_id', 'started_at', 'ended_at', 'notes']));

        return $workout;
    }

    public function finish(User $user, Workout $workout, ?string $endedAt = null, ?string $notes = null): Workout
    {
        $attributes = ['ended_at' => $endedAt ?? now()];

        if ($notes !== null) {
            $attributes['notes'] = $notes;
        }

        return $this->update($user, $workout, $attributes);
    }

    public function removeExercise(User $user, WorkoutExercise $workoutExercise): void
    {
        $this->assertOwnsWorkoutExercise($user, $workoutExercise);

        $workoutExercise->delete();
    }

    public function removeSet(User $user, WorkoutSet $set): void
    {
        $this->assertOwnsWorkoutExercise($user, $set->workoutExercise);

        $set->delete();
    }

    public function delete(User $user, Workout $workout): void
    {
        $this->assertOwnsWorkout($user, $workout);

        $workout->delete();
    }

    private function copyRoutineTemplate(User $user, Workout $workout, Routine $routine): void
    {
        foreach ($routine->exercises as $exercise) {
            $workoutExercise = $workout->exercises()->create([
                'exercise_id' => $exercise->id,
                'order' => $exercise->pivot->order ?? 0,
            ]);

            $previousExercise = WorkoutExercise::whereHas('workout', fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereNotNull('ended_at'))
                ->where('exercise_id', $exercise->id)
                ->latest('id')
                ->with('sets')
                ->first();

            $targetSets = $exercise->pivot->target_sets ?? 3;

            for ($i = 1; $i <= $targetSets; $i++) {
                $previousSet = $previousExercise?->sets->firstWhere('set_number', $i);

                $workoutExercise->sets()->create([
                    'set_number' => $i,
                    'weight' => $previousSet?->weight ?? $exercise->pivot->target_weight,
                    'reps' => $previousSet?->reps ?? $exercise->pivot->target_reps,
                    'completed' => false,
                ]);
            }
        }
    }

    private function assertOwnsWorkout(User $user, Workout $workout): void
    {
        if ($workout->user_id !== $user->id) {
            throw new AuthorizationException('This workout does not belong to you.');
        }
    }

    private function assertOwnsWorkoutExercise(User $user, WorkoutExercise $workoutExercise): void
    {
        $this->assertOwnsWorkout($user, $workoutExercise->workout);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Gym/WorkoutSessionServiceTest.php`
Expected: PASS (7 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services tests/Feature/Gym/WorkoutSessionServiceTest.php app/Models
git commit -m "feat(gym): workout session service with template copy and ownership"
```

---

### Task 3: `PersonalRecordService` + hook en `logSet`

**Files:**
- Create: `app/Services/Gym/PersonalRecordService.php`
- Modify: `app/Services/Gym/WorkoutSessionService.php`
- Test: `tests/Feature/Gym/PersonalRecordServiceTest.php`

**Interfaces:**
- Consumes: `WorkoutSessionService::logSet`, modelos `PersonalRecord`, `Exercise`, `WorkoutSet`.
- Produces:
  - `PersonalRecordService::evaluate(User $user, WorkoutSet $set): void`
  - `PersonalRecordService::timeline(User $user, int $limit = 50): Collection<int, PersonalRecord>` (con `exercise` cargado)
  - `PersonalRecordService::bestWeightFor(User $user, Exercise $exercise): ?float`
  - `PersonalRecordService::annotateSets(Collection<int, WorkoutSet> $sets): void` (agrega `is_pr` booleano a cada set)

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Gym/PersonalRecordServiceTest.php`:

```php
<?php

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\Workout;
use App\Services\Gym\PersonalRecordService;
use App\Services\Gym\WorkoutSessionService;

uses(RefreshDatabase::class);

function recordService(): PersonalRecordService
{
    return app(PersonalRecordService::class);
}

function logSetFor(User $user, Exercise $exercise, array $data): App\Models\WorkoutSet
{
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id]);

    return app(WorkoutSessionService::class)->logSet($user, $workoutExercise, $data);
}

it('records weight and one rep max PRs when logging a completed set', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    $set = logSetFor($user, $exercise, ['weight' => 100, 'reps' => 5, 'completed' => true]);

    $records = PersonalRecord::where('user_id', $user->id)->where('exercise_id', $exercise->id)->get();

    expect($records)->toHaveCount(2)
        ->and($records->pluck('type')->all())->toContain('weight', 'one_rm')
        ->and($records->firstWhere('type', 'weight')->workout_set_id)->toBe($set->id)
        ->and((float) $records->firstWhere('type', 'one_rm')->value)->toBe(116.67);
});

it('does not record a PR when the set does not beat the best', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    logSetFor($user, $exercise, ['weight' => 100, 'reps' => 5, 'completed' => true]);
    logSetFor($user, $exercise, ['weight' => 90, 'reps' => 5, 'completed' => true]);

    expect(PersonalRecord::count())->toBe(2);
});

it('tracks rep PRs per weight and ignores incomplete sets', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();

    logSetFor($user, $exercise, ['weight' => 80, 'reps' => 8, 'completed' => true]);
    logSetFor($user, $exercise, ['weight' => 80, 'reps' => 10, 'completed' => true]);
    logSetFor($user, $exercise, ['weight' => 80, 'reps' => 12, 'completed' => false]);

    $repsRecords = PersonalRecord::where('type', 'reps')->get();

    // The first set at a weight only sets the rep baseline when it beats nothing;
    // the 10-rep set is the first true rep PR and the incomplete 12-rep set is ignored.
    expect($repsRecords)->toHaveCount(1)
        ->and((int) $repsRecords->first()->value)->toBe(10);
});

it('returns the timeline with exercises and annotations', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Sentadilla']);
    $set = logSetFor($user, $exercise, ['weight' => 120, 'reps' => 3, 'completed' => true]);

    $timeline = recordService()->timeline($user);

    expect($timeline)->not->toBeEmpty()
        ->and($timeline->first()->exercise->name)->toBe('Sentadilla');

    recordService()->annotateSets(collect([$set]));

    expect($set->is_pr)->toBeTrue()
        ->and(recordService()->bestWeightFor($user, $exercise))->toBe(120.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Gym/PersonalRecordServiceTest.php`
Expected: FAIL — `Class "App\Services\Gym\PersonalRecordService" not found`.

- [ ] **Step 3: Implement `PersonalRecordService`**

`app/Services/Gym/PersonalRecordService.php`:

```php
<?php

namespace App\Services\Gym;

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Support\Collection;

class PersonalRecordService
{
    private const EPSILON = 0.001;

    public function evaluate(User $user, WorkoutSet $set): void
    {
        if (! $set->completed || $set->weight === null || $set->reps === null) {
            return;
        }

        $workoutExercise = WorkoutExercise::with('exercise')->find($set->workout_exercise_id);

        if (! $workoutExercise?->exercise) {
            return;
        }

        $exercise = $workoutExercise->exercise;
        $weight = (float) $set->weight;
        $reps = (int) $set->reps;

        $this->beat($user, $exercise, $set, 'weight', $weight, $weight, $reps);
        $this->beat($user, $exercise, $set, 'one_rm', round($weight * (1 + $reps / 30), 2), $weight, $reps);

        $repsBest = WorkoutSet::query()
            ->whereHas('workoutExercise', function ($query) use ($user, $exercise) {
                $query->where('exercise_id', $exercise->id)
                    ->whereHas('workout', fn ($workoutQuery) => $workoutQuery->where('user_id', $user->id));
            })
            ->where('weight', $weight)
            ->where('completed', true)
            ->where('id', '!=', $set->id)
            ->max('reps');

        // A rep PR only exists when there is a previous completed set to beat at this weight.
        if ($repsBest !== null && $reps > (int) $repsBest) {
            $this->store($user, $exercise, $set, 'reps', $reps, $weight, $reps);
        }
    }

    /**
     * @return Collection<int, PersonalRecord>
     */
    public function timeline(User $user, int $limit = 50): Collection
    {
        return PersonalRecord::with('exercise')
            ->where('user_id', $user->id)
            ->latest('achieved_at')
            ->limit($limit)
            ->get();
    }

    public function bestWeightFor(User $user, Exercise $exercise): ?float
    {
        $best = PersonalRecord::query()
            ->where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->where('type', 'weight')
            ->max('value');

        return $best === null ? null : (float) $best;
    }

    /**
     * @param  Collection<int, WorkoutSet>  $sets
     */
    public function annotateSets(Collection $sets): void
    {
        $prSetIds = PersonalRecord::query()
            ->whereIn('workout_set_id', $sets->pluck('id'))
            ->pluck('workout_set_id')
            ->all();

        $sets->each(fn (WorkoutSet $set) => $set->setAttribute(
            'is_pr',
            in_array($set->id, $prSetIds, true),
        ));
    }

    private function beat(User $user, Exercise $exercise, WorkoutSet $set, string $type, float $value, float $weight, int $reps): void
    {
        $currentBest = PersonalRecord::query()
            ->where('user_id', $user->id)
            ->where('exercise_id', $exercise->id)
            ->where('type', $type)
            ->max('value');

        if ($currentBest === null || $value > (float) $currentBest + self::EPSILON) {
            $this->store($user, $exercise, $set, $type, $value, $weight, $reps);
        }
    }

    private function store(User $user, Exercise $exercise, WorkoutSet $set, string $type, float $value, float $weight, int $reps): void
    {
        PersonalRecord::create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
            'workout_set_id' => $set->id,
            'type' => $type,
            'value' => $value,
            'reps' => $reps,
            'weight' => $weight,
            'achieved_at' => $set->updated_at ?? now(),
        ]);
    }
}
```

- [ ] **Step 4: Wire it into `WorkoutSessionService::logSet`**

En `app/Services/Gym/WorkoutSessionService.php`, cambiar el constructor:

```php
    public function __construct(protected ExerciseResolver $resolver) {}
```

por:

```php
    public function __construct(
        protected ExerciseResolver $resolver,
        protected PersonalRecordService $records,
    ) {}
```

Y en `logSet`, reemplazar:

```php
        return $workoutExercise->sets()->updateOrCreate(
            ['set_number' => $setNumber],
            Arr::only($data, ['weight', 'reps', 'rpe', 'completed']),
        );
```

por:

```php
        $set = $workoutExercise->sets()->updateOrCreate(
            ['set_number' => $setNumber],
            Arr::only($data, ['weight', 'reps', 'rpe', 'completed']),
        );

        if ($set->completed) {
            $this->records->evaluate($user, $set);
        }

        return $set;
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Gym/PersonalRecordServiceTest.php tests/Feature/Gym/WorkoutSessionServiceTest.php`
Expected: PASS (11 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services tests/Feature/Gym/PersonalRecordServiceTest.php
git commit -m "feat(gym): personal record detection wired into set logging"
```

---

### Task 4: `RoutineService`

**Files:**
- Create: `app/Services/Gym/RoutineService.php`
- Test: `tests/Feature/Gym/RoutineServiceTest.php`

**Interfaces:**
- Consumes: `ExerciseResolver`, modelos `Routine`, `Exercise`.
- Produces:
  - `RoutineService::create(User $user, array $data): Routine` — `$data['exercises']` es `array<int, array{id?:int, name?:string, muscle_group?:string, type?:string, target_sets?:int, target_reps?:string, target_weight?:string, notes?:string}>`
  - `RoutineService::update(User $user, Routine $routine, array $data): Routine`
  - `RoutineService::delete(User $user, Routine $routine): void`

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Gym/RoutineServiceTest.php`:

```php
<?php

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Services\Gym\RoutineService;
use Illuminate\Auth\Access\AuthorizationException;

uses(RefreshDatabase::class);

function routines(): RoutineService
{
    return app(RoutineService::class);
}

it('creates a routine with exercises by name and targets', function () {
    $user = User::factory()->create();
    $existing = Exercise::factory()->create(['name' => 'Press banca']);

    $routine = routines()->create($user, [
        'name' => 'Pecho pesado',
        'focus' => 'Chest',
        'exercises' => [
            ['id' => $existing->id, 'target_sets' => 4, 'target_reps' => '6', 'target_weight' => '80'],
            ['name' => 'Aperturas', 'target_sets' => 3],
        ],
    ]);

    expect($routine->user_id)->toBe($user->id)
        ->and($routine->exercises)->toHaveCount(2)
        ->and($routine->exercises->first()->pivot->target_sets)->toBe(4)
        ->and(Exercise::where('name', 'Aperturas')->exists())->toBeTrue();
});

it('updates metadata and replaces the exercise list', function () {
    $user = User::factory()->create();
    $routine = routines()->create($user, [
        'name' => 'Old',
        'exercises' => [['name' => 'Sentadilla']],
    ]);

    $updated = routines()->update($user, $routine, [
        'name' => 'New',
        'exercises' => [['name' => 'Peso muerto']],
    ]);

    expect($updated->name)->toBe('New')
        ->and($updated->exercises)->toHaveCount(1)
        ->and($updated->exercises->first()->name)->toBe('Peso muerto');
});

it('deletes only own routines', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $routine = routines()->create($user, ['name' => 'Mine']);

    expect(fn () => routines()->delete($other, $routine))
        ->toThrow(AuthorizationException::class);

    routines()->delete($user, $routine);
    expect($routine->exists)->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Gym/RoutineServiceTest.php`
Expected: FAIL — `Class "App\Services\Gym\RoutineService" not found`.

- [ ] **Step 3: Implement `RoutineService`**

`app/Services/Gym/RoutineService.php`:

```php
<?php

namespace App\Services\Gym;

use App\Models\Routine;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;

class RoutineService
{
    public function __construct(protected ExerciseResolver $resolver) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Routine
    {
        $routine = $user->routines()->create([
            'name' => $data['name'],
            'focus' => $data['focus'] ?? null,
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        if (! empty($data['exercises'])) {
            $this->attachExercises($routine, $data['exercises']);
        }

        return $routine;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, Routine $routine, array $data): Routine
    {
        $this->assertOwns($user, $routine);

        $routine->update(Arr::only($data, ['name', 'focus', 'scheduled_date', 'status']));

        if (array_key_exists('exercises', $data)) {
            $routine->exercises()->detach();
            $this->attachExercises($routine, $data['exercises'] ?? []);
        }

        return $routine->refresh();
    }

    public function delete(User $user, Routine $routine): void
    {
        $this->assertOwns($user, $routine);

        $routine->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $exercises
     */
    private function attachExercises(Routine $routine, array $exercises): void
    {
        foreach (array_values($exercises) as $index => $exerciseData) {
            $exercise = $this->resolver->resolve(
                $exerciseData['id'] ?? $exerciseData['exercise_id'] ?? null,
                $exerciseData['name'] ?? null,
                $exerciseData,
            );

            $routine->exercises()->attach($exercise->id, [
                'order' => $index + 1,
                'target_sets' => $exerciseData['target_sets'] ?? null,
                'target_reps' => $exerciseData['target_reps'] ?? null,
                'target_weight' => $exerciseData['target_weight'] ?? null,
                'notes' => $exerciseData['notes'] ?? null,
            ]);
        }
    }

    private function assertOwns(User $user, Routine $routine): void
    {
        if ($routine->user_id !== $user->id) {
            throw new AuthorizationException('This routine does not belong to you.');
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Gym/RoutineServiceTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services tests/Feature/Gym/RoutineServiceTest.php
git commit -m "feat(gym): routine service with exercise sync and ownership"
```

---

### Task 5: Policies `WorkoutPolicy` + `RoutinePolicy`

**Files:**
- Create: `app/Policies/WorkoutPolicy.php`
- Create: `app/Policies/RoutinePolicy.php`
- Test: `tests/Feature/Gym/GymPolicyTest.php`

**Interfaces:**
- Consumes: modelos `Workout`, `Routine`, `User`.
- Produces: autorización `view/update/delete` por dueño (auto-discovery Laravel 12: `App\Models\Workout` → `App\Policies\WorkoutPolicy`).

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Gym/GymPolicyTest.php`:

```php
<?php

use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;

uses(RefreshDatabase::class);

it('authorizes workout actions only for the owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('view', $workout))->toBeTrue()
        ->and($owner->can('update', $workout))->toBeTrue()
        ->and($owner->can('delete', $workout))->toBeTrue()
        ->and($other->can('view', $workout))->toBeFalse()
        ->and($other->can('update', $workout))->toBeFalse()
        ->and($other->can('delete', $workout))->toBeFalse();
});

it('authorizes routine actions only for the owner', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $owner->id]);

    expect($owner->can('view', $routine))->toBeTrue()
        ->and($owner->can('update', $routine))->toBeTrue()
        ->and($owner->can('delete', $routine))->toBeTrue()
        ->and($other->can('view', $routine))->toBeFalse()
        ->and($other->can('update', $routine))->toBeFalse()
        ->and($other->can('delete', $routine))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Gym/GymPolicyTest.php`
Expected: FAIL — sin policy, `can()` devuelve `false` para el dueño (o `true` si hay gate abierto — fallará la primera aserción).

- [ ] **Step 3: Implement the policies**

`app/Policies/WorkoutPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workout;

class WorkoutPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }

    public function delete(User $user, Workout $workout): bool
    {
        return $user->id === $workout->user_id;
    }
}
```

`app/Policies/RoutinePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Routine;
use App\Models\User;

class RoutinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Routine $routine): bool
    {
        return $user->id === $routine->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Routine $routine): bool
    {
        return $user->id === $routine->user_id;
    }

    public function delete(User $user, Routine $routine): bool
    {
        return $user->id === $routine->user_id;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Gym/GymPolicyTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies tests/Feature/Gym/GymPolicyTest.php
git commit -m "feat(gym): workout and routine ownership policies"
```

---

### Task 6: Web `Gym\WorkoutController` — servicio, policies, endpoints delete, `is_pr`, PR prop

**Files:**
- Modify: `app/Http/Controllers/Gym/WorkoutController.php`
- Modify: `app/Http/Controllers/Gym/ExerciseController.php` (solo la línea de `new WorkoutController`)
- Modify: `routes/web.php` (grupo gym: DELETE nuevos)
- Test: `tests/Feature/Gym/WorkoutWebTest.php`

**Interfaces:**
- Consumes: `WorkoutSessionService`, `PersonalRecordService`, policies.
- Produces:
  - Payload de workout activo con atributos dinámicos `is_pr` (bool) por set y `best_weight` (float|null) por workout-exercise.
  - Prop Inertia `personalRecords` en `fitness/history`.
  - Rutas web `DELETE gym/workout-exercises/{workoutExercise}` (name `workout-exercises.destroy`) y `DELETE gym/workout-sets/{workoutSet}` (name `workout-sets.destroy`), ambas JSON (`{"message": "..."}` 200) o redirect back.

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Gym/WorkoutWebTest.php`:

```php
<?php

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;

uses(RefreshDatabase::class);

it('blocks access to another users workout through web routes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $other->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);

    $this->actingAs($user)->getJson("/gym/workouts/{$workout->id}")->assertForbidden();
    $this->actingAs($user)->patchJson("/gym/workouts/{$workout->id}", ['notes' => 'x'])->assertForbidden();
    $this->actingAs($user)->postJson("/gym/workouts/{$workout->id}/exercises", ['exercise_id' => 1])->assertForbidden();
    $this->actingAs($user)->postJson("/gym/workout-exercises/{$workoutExercise->id}/sets", ['set_number' => 1])->assertForbidden();
    $this->actingAs($user)->deleteJson("/gym/workouts/{$workout->id}")->assertForbidden();
});

it('removes exercises and sets for the owner', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);
    $set = $workoutExercise->sets()->create(['set_number' => 1]);

    $this->actingAs($user)->deleteJson("/gym/workout-sets/{$set->id}")->assertOk();
    expect($set->exists)->toBeFalse();

    $this->actingAs($user)->deleteJson("/gym/workout-exercises/{$workoutExercise->id}")->assertOk();
    expect($workoutExercise->exists)->toBeFalse();
});

it('marks pr sets and best weight in the active workout payload', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => $exercise->id, 'order' => 1]);
    $set = $workoutExercise->sets()->create(['set_number' => 1, 'weight' => 100, 'reps' => 5, 'completed' => true]);

    PersonalRecord::factory()->create([
        'user_id' => $user->id,
        'exercise_id' => $exercise->id,
        'workout_set_id' => $set->id,
        'type' => 'weight',
        'value' => 100,
        'weight' => 100,
        'reps' => 5,
    ]);

    $response = $this->actingAs($user)->getJson("/gym/workouts/{$workout->id}");

    $response->assertOk()
        ->assertJsonPath('exercises.0.sets.0.is_pr', true)
        ->assertJsonPath('exercises.0.best_weight', 100);
});

it('exposes the personal record timeline on the history page', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Press banca']);
    $workout = Workout::factory()->create(['user_id' => $user->id, 'ended_at' => now()]);

    PersonalRecord::factory()->create([
        'user_id' => $user->id,
        'exercise_id' => $exercise->id,
        'type' => 'weight',
        'value' => 90,
        'weight' => 90,
        'reps' => 5,
    ]);

    $this->actingAs($user)->get('/fitness/history')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('fitness/history')
            ->has('personalRecords', 1)
            ->where('personalRecords.0.exercise.name', 'Press banca'));
});

it('rejects a routine from another user when starting a workout', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->postJson('/gym/workouts', ['routine_id' => $routine->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['routine_id']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Gym/WorkoutWebTest.php`
Expected: FAIL — sin autorización (200/201 en vez de 403), rutas DELETE no existen, sin `is_pr` ni `personalRecords`.

- [ ] **Step 3: Rewrite `Gym\WorkoutController`**

Reemplazar el contenido de `app/Http/Controllers/Gym/WorkoutController.php` por:

```php
<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use App\Services\Gym\PersonalRecordService;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutController extends Controller
{
    public function __construct(
        protected WorkoutSessionService $sessions,
        protected PersonalRecordService $records,
    ) {}

    public function index(Request $request)
    {
        return Workout::with('routine')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('started_at')
            ->get();
    }

    public function history(Request $request): Response
    {
        $workouts = Workout::with(['exercises.sets', 'routine'])
            ->where('user_id', $request->user()->id)
            ->whereNotNull('ended_at')
            ->orderByDesc('ended_at')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('fitness/history', [
            'workouts' => $workouts,
            'personalRecords' => $this->records->timeline($request->user(), 20),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'routine_id' => [
                'nullable',
                Rule::exists('routines', 'id')->where('user_id', $request->user()->id),
            ],
            'started_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if ($activeWorkout = $this->sessions->activeFor($request->user())) {
            if ($this->wantsJson($request)) {
                return response()->json($this->loadWorkoutWithHistory($activeWorkout));
            }

            return redirect()->back();
        }

        $workout = $this->sessions->start(
            $request->user(),
            $validated['routine_id'] ?? null,
            $validated['started_at'] ?? null,
            $validated['notes'] ?? null,
        );

        if ($this->wantsJson($request)) {
            return response()->json($this->loadWorkoutWithHistory($workout), 201);
        }

        return redirect()->back();
    }

    public function loadWorkoutWithHistory(Workout $workout)
    {
        $workout->load(['routine', 'exercises.sets', 'exercises.exercise']);

        foreach ($workout->exercises as $exercise) {
            $previous = WorkoutExercise::whereHas('workout', function ($query) use ($workout) {
                $query->where('user_id', $workout->user_id)
                    ->whereNotNull('ended_at')
                    ->where('workouts.id', '!=', $workout->id);
            })
                ->where('exercise_id', $exercise->exercise_id)
                ->latest('id')
                ->with('sets')
                ->first();

            $exercise->setAttribute(
                'previous',
                $previous ? $previous->sets->sortBy('set_number')->values() : null,
            );

            $exercise->setAttribute('best_weight', $exercise->exercise
                ? $this->records->bestWeightFor($workout->user, $exercise->exercise)
                : null);

            $this->records->annotateSets($exercise->sets);
        }

        return $workout;
    }

    public function show(Request $request, Workout $workout)
    {
        $this->authorize('view', $workout);

        return $this->loadWorkoutWithHistory($workout);
    }

    public function update(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'ended_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $this->sessions->update($request->user(), $workout, $validated);

        if ($this->wantsJson($request)) {
            return response()->json($this->loadWorkoutWithHistory($workout));
        }

        return redirect()->back();
    }

    public function addExercise(Request $request, Workout $workout)
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'exercise_id' => 'required|exists:exercises,id',
        ]);

        $workoutExercise = $this->sessions->addExercise(
            $request->user(),
            $workout,
            $validated['exercise_id'],
        );

        if ($this->wantsJson($request)) {
            return response()->json($workoutExercise->load(['exercise', 'sets']), 201);
        }

        return redirect()->back();
    }

    public function logSet(Request $request, WorkoutExercise $workoutExercise)
    {
        $this->authorize('update', $workoutExercise->workout);

        $validated = $request->validate([
            'set_number' => 'required|integer',
            'weight' => 'nullable|numeric',
            'reps' => 'nullable|integer',
            'rpe' => 'nullable|numeric',
            'completed' => 'nullable|boolean',
        ]);

        $set = $this->sessions->logSet($request->user(), $workoutExercise, $validated);

        if ($this->wantsJson($request)) {
            return response()->json($set, 200);
        }

        return redirect()->back();
    }

    public function removeExercise(Request $request, WorkoutExercise $workoutExercise): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $workoutExercise->workout);

        $this->sessions->removeExercise($request->user(), $workoutExercise);

        if ($this->wantsJson($request)) {
            return response()->json(['message' => 'Exercise removed']);
        }

        return redirect()->back();
    }

    public function removeSet(Request $request, WorkoutSet $workoutSet): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $workoutSet->workoutExercise->workout);

        $this->sessions->removeSet($request->user(), $workoutSet);

        if ($this->wantsJson($request)) {
            return response()->json(['message' => 'Set removed']);
        }

        return redirect()->back();
    }

    public function destroy(Request $request, Workout $workout): JsonResponse
    {
        $this->authorize('delete', $workout);

        $this->sessions->delete($request->user(), $workout);

        return response()->noContent();
    }

    private function wantsJson(Request $request): bool
    {
        return $request->wantsJson() && ! $request->header('X-Inertia');
    }
}
```

- [ ] **Step 4: Fix `ExerciseController` instantiation + add routes**

En `app/Http/Controllers/Gym/ExerciseController.php`, reemplazar:

```php
            $activeWorkout = (new WorkoutController)->loadWorkoutWithHistory($activeWorkout);
```

por:

```php
            $activeWorkout = app(WorkoutController::class)->loadWorkoutWithHistory($activeWorkout);
```

En `routes/web.php`, dentro del grupo `Route::prefix('gym')`, después de la línea de `workout-exercises/.../sets`, agregar:

```php
        Route::delete('workout-exercises/{workoutExercise}', [WorkoutController::class, 'removeExercise'])->name('workout-exercises.destroy');
        Route::delete('workout-sets/{workoutSet}', [WorkoutController::class, 'removeSet'])->name('workout-sets.destroy');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Gym/WorkoutWebTest.php tests/Feature/Gym/WorkoutTest.php tests/Feature/Gym/GymPolicyTest.php`
Expected: PASS. (El test existente `WorkoutTest.php` debe seguir verde.)

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gym routes/web.php tests/Feature/Gym/WorkoutWebTest.php
git commit -m "feat(gym): authorized web workout endpoints, deletes and PR payload"
```

---

### Task 7: Web `Gym\RoutineController` — servicio + policies

**Files:**
- Modify: `app/Http/Controllers/Gym/RoutineController.php`
- Test: `tests/Feature/Gym/RoutineWebTest.php`

**Interfaces:**
- Consumes: `RoutineService`, `RoutinePolicy`.
- Produces: `store/update/destroy` autorizados y delegados; contratos HTTP intactos (201/200/204).

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Gym/RoutineWebTest.php`:

```php
<?php

use App\Models\Routine;
use App\Models\User;

uses(RefreshDatabase::class);

it('blocks access to another users routine through web routes', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)->getJson("/gym/routines/{$routine->id}")->assertForbidden();
    $this->actingAs($user)->putJson("/gym/routines/{$routine->id}", ['name' => 'X'])->assertForbidden();
    $this->actingAs($user)->deleteJson("/gym/routines/{$routine->id}")->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Gym/RoutineWebTest.php`
Expected: FAIL — las rutas devuelven 200/200/204 en vez de 403.

- [ ] **Step 3: Refactor `RoutineController`**

Reemplazar los métodos `store`, `show`, `update`, `destroy` de `app/Http/Controllers/Gym/RoutineController.php` (mantener `index` y los `use` necesarios: agregar `App\Services\Gym\RoutineService`):

```php
    public function __construct(protected RoutineService $routines) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'focus' => 'nullable|string|max:255',
            'scheduled_date' => 'nullable|string|max:255',
            'exercises' => 'nullable|array',
            'exercises.*.id' => 'nullable|exists:exercises,id',
            'exercises.*.name' => 'required_without:exercises.*.id|string|max:255',
            'exercises.*.muscle_group' => 'nullable|string|max:255',
            'exercises.*.type' => 'nullable|string|max:255',
            'exercises.*.target_sets' => 'nullable|integer',
            'exercises.*.target_reps' => 'nullable|string',
            'exercises.*.target_weight' => 'nullable|string',
            'exercises.*.notes' => 'nullable|string',
        ]);

        $routine = $this->routines->create($request->user(), $validated);

        if ($request->wantsJson()) {
            return response()->json($routine->load('exercises'), 201);
        }

        return redirect()->back();
    }

    public function show(Request $request, Routine $routine)
    {
        $this->authorize('view', $routine);

        return $routine->load('exercises');
    }

    public function update(Request $request, Routine $routine)
    {
        $this->authorize('update', $routine);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'focus' => 'nullable|string|max:255',
            'scheduled_date' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,archived',
            'exercises' => 'nullable|array',
            'exercises.*.id' => 'nullable|exists:exercises,id',
            'exercises.*.name' => 'required_without:exercises.*.id|string|max:255',
            'exercises.*.muscle_group' => 'nullable|string|max:255',
            'exercises.*.type' => 'nullable|string|max:255',
            'exercises.*.target_sets' => 'nullable|integer',
            'exercises.*.target_reps' => 'nullable|string',
            'exercises.*.target_weight' => 'nullable|string',
            'exercises.*.notes' => 'nullable|string',
        ]);

        $routine = $this->routines->update($request->user(), $routine, $validated);

        if ($request->wantsJson()) {
            return response()->json($routine->load('exercises'), 200);
        }

        return redirect()->back();
    }

    public function destroy(Request $request, Routine $routine)
    {
        $this->authorize('delete', $routine);

        $this->routines->delete($request->user(), $routine);

        return response()->noContent();
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Gym/RoutineWebTest.php tests/Feature/Gym/RoutineTest.php tests/Feature/Gym/RoutineServiceTest.php`
Expected: PASS (el test existente `RoutineTest.php` debe seguir verde con 201/200/204).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gym/RoutineController.php tests/Feature/Gym/RoutineWebTest.php
git commit -m "refactor(gym): routine web controller via service and policies"
```

---

### Task 8: API v1 — servicio, endpoints de detalle y rutinas update/delete

**Files:**
- Modify: `app/Http/Requests/Api/StoreWorkoutRequest.php`
- Create: `app/Http/Requests/Api/UpdateRoutineRequest.php`
- Modify: `app/Http/Controllers/Api/V1/WorkoutController.php`
- Modify: `app/Http/Controllers/Api/V1/RoutineController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/GymApiWriteTest.php`

**Interfaces:**
- Consumes: `WorkoutSessionService`, `RoutineService`.
- Produces:
  - `POST /api/v1/workouts` — idempotente: si hay workout activo responde 200 con el activo; nuevo = 201; copia plantilla si viene `routine_id`.
  - `POST /api/v1/workouts/{workout}/exercises` — 201 `WorkoutExerciseResource` (`exercise_id` o `exercise_name`).
  - `POST /api/v1/workout-exercises/{workoutExercise}/sets` — 200 `WorkoutSetResource` (`set_number` opcional = auto).
  - `PATCH /api/v1/routines/{routine}` y `DELETE /api/v1/routines/{routine}` — con ownership.

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Api/GymApiWriteTest.php`:

```php
<?php

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;

uses(RefreshDatabase::class);

function tokenFor(User $user): string
{
    return $user->createToken('api-token')->plainTextToken;
}

it('copies the routine template when creating a workout via API', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $exercise = Exercise::factory()->create();
    $routine->exercises()->attach($exercise->id, ['order' => 1, 'target_sets' => 3]);

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson('/api/v1/workouts', ['routine_id' => $routine->id]);

    $response->assertCreated()
        ->assertJsonCount(1, 'data.exercises')
        ->assertJsonCount(3, 'data.exercises.0.sets');
});

it('returns the active workout instead of creating a second one', function () {
    $user = User::factory()->create();
    Workout::factory()->create(['user_id' => $user->id, 'ended_at' => null]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson('/api/v1/workouts', [])
        ->assertOk();

    expect(Workout::where('user_id', $user->id)->count())->toBe(1);
});

it('adds an exercise by name via API', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson("/api/v1/workouts/{$workout->id}/exercises", ['exercise_name' => 'Remo Pendlay']);

    $response->assertCreated()
        ->assertJsonPath('data.exercise.name', 'Remo Pendlay');

    expect(Exercise::where('name', 'Remo Pendlay')->exists())->toBeTrue();
});

it('logs sets with auto numbering via API', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson("/api/v1/workout-exercises/{$workoutExercise->id}/sets", ['weight' => 60, 'reps' => 8, 'completed' => true]);

    $response->assertOk()
        ->assertJsonPath('data.set_number', 1);
});

it('rejects a foreign routine id when creating a workout', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson('/api/v1/workouts', ['routine_id' => $routine->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['routine_id']);
});

it('blocks detail writes on another users workout', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $other->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => Exercise::factory()->create()->id]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson("/api/v1/workouts/{$workout->id}/exercises", ['exercise_name' => 'X'])
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->postJson("/api/v1/workout-exercises/{$workoutExercise->id}/sets", ['weight' => 1])
        ->assertForbidden();
});

it('updates and deletes own routines via API', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $foreign = Routine::factory()->create(['user_id' => $other->id]);

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->patchJson("/api/v1/routines/{$routine->id}", ['name' => 'Updated'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated');

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->patchJson("/api/v1/routines/{$foreign->id}", ['name' => 'Nope'])
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.tokenFor($user))
        ->deleteJson("/api/v1/routines/{$routine->id}")
        ->assertNoContent();

    expect(Routine::whereKey($routine->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Api/GymApiWriteTest.php`
Expected: FAIL — endpoints no existen (404), `routine_id` ajeno pasa validación, etc.

- [ ] **Step 3: Scope `routine_id` in `StoreWorkoutRequest`**

Reemplazar `rules()` en `app/Http/Requests/Api/StoreWorkoutRequest.php`:

```php
    public function rules(): array
    {
        return [
            'routine_id' => [
                'nullable',
                Rule::exists('routines', 'id')->where('user_id', $this->user()->id),
            ],
            'started_at' => [
                'date',
                Rule::requiredIf(fn () => ! $this->has('routine_id')
                    && ! $this->user()?->workouts()->whereNull('ended_at')->exists()),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
```

Agregar el `use Illuminate\Validation\Rule;` al inicio del archivo.

- [ ] **Step 4: Refactor API `WorkoutController`**

Reemplazar `app/Http/Controllers/Api/V1/WorkoutController.php` por:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreWorkoutRequest;
use App\Http\Resources\WorkoutExerciseResource;
use App\Http\Resources\WorkoutResource;
use App\Http\Resources\WorkoutSetResource;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class WorkoutController extends Controller
{
    public function __construct(protected WorkoutSessionService $sessions) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $workouts = $request->user()
            ->workouts()
            ->with(['exercises.exercise', 'exercises.sets', 'routine'])
            ->latest()
            ->paginate(20);

        return WorkoutResource::collection($workouts);
    }

    public function store(StoreWorkoutRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($active = $this->sessions->activeFor($user)) {
            return (new WorkoutResource(
                $active->load(['exercises.exercise', 'exercises.sets', 'routine'])
            ))->response()->setStatusCode(200);
        }

        $workout = $this->sessions->start(
            $user,
            $request->validated('routine_id'),
            $request->validated('started_at'),
            $request->validated('notes'),
        );

        return (new WorkoutResource(
            $workout->load(['exercises.exercise', 'exercises.sets', 'routine'])
        ))->response()->setStatusCode(201);
    }

    public function show(Request $request, Workout $workout): WorkoutResource
    {
        $this->authorize('view', $workout);

        return new WorkoutResource(
            $workout->load(['exercises.exercise', 'exercises.sets', 'routine'])
        );
    }

    public function update(Request $request, Workout $workout): WorkoutResource
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'routine_id' => ['nullable', Rule::exists('routines', 'id')->where('user_id', $request->user()->id)],
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->sessions->update($request->user(), $workout, $validated);

        return new WorkoutResource(
            $workout->load(['exercises.exercise', 'exercises.sets', 'routine'])
        );
    }

    public function addExercise(Request $request, Workout $workout): JsonResponse
    {
        $this->authorize('update', $workout);

        $validated = $request->validate([
            'exercise_id' => ['nullable', 'integer', 'exists:exercises,id', 'required_without:exercise_name'],
            'exercise_name' => ['nullable', 'string', 'max:255', 'required_without:exercise_id'],
        ]);

        $workoutExercise = $this->sessions->addExercise(
            $request->user(),
            $workout,
            $validated['exercise_id'] ?? null,
            $validated['exercise_name'] ?? null,
        );

        return (new WorkoutExerciseResource($workoutExercise->load(['exercise', 'sets'])))
            ->response()
            ->setStatusCode(201);
    }

    public function logSet(Request $request, WorkoutExercise $workoutExercise): JsonResponse
    {
        $this->authorize('update', $workoutExercise->workout);

        $validated = $request->validate([
            'set_number' => ['nullable', 'integer', 'min:1'],
            'weight' => ['nullable', 'numeric'],
            'reps' => ['nullable', 'integer'],
            'rpe' => ['nullable', 'numeric'],
            'completed' => ['nullable', 'boolean'],
        ]);

        $set = $this->sessions->logSet($request->user(), $workoutExercise, $validated);

        return (new WorkoutSetResource($set))->response()->setStatusCode(200);
    }

    public function destroy(Request $request, Workout $workout): JsonResponse
    {
        $this->authorize('delete', $workout);

        $this->sessions->delete($request->user(), $workout);

        return response()->json(['message' => 'Workout deleted']);
    }
}
```

- [ ] **Step 5: Refactor API `RoutineController` + request**

Crear `app/Http/Requests/Api/UpdateRoutineRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'focus' => ['nullable', 'string', 'max:255'],
            'scheduled_date' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,archived'],
            'exercise_ids' => ['nullable', 'array'],
            'exercise_ids.*' => ['exists:exercises,id'],
        ];
    }
}
```

Reemplazar `app/Http/Controllers/Api/V1/RoutineController.php` por:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StoreRoutineRequest;
use App\Http\Requests\Api\UpdateRoutineRequest;
use App\Http\Resources\RoutineResource;
use App\Models\Routine;
use App\Services\Gym\RoutineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoutineController extends Controller
{
    public function __construct(protected RoutineService $routines) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $routines = $request->user()
            ->routines()
            ->with('exercises')
            ->latest()
            ->paginate(20);

        return RoutineResource::collection($routines);
    }

    public function store(StoreRoutineRequest $request): JsonResponse
    {
        $routine = $this->routines->create($request->user(), $this->routineData($request->validated()));

        return (new RoutineResource($routine->load('exercises')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateRoutineRequest $request, Routine $routine): RoutineResource
    {
        $this->authorize('update', $routine);

        $routine = $this->routines->update($request->user(), $routine, $this->routineData($request->validated()));

        return new RoutineResource($routine->load('exercises'));
    }

    public function destroy(Request $request, Routine $routine): JsonResponse
    {
        $this->authorize('delete', $routine);

        $this->routines->delete($request->user(), $routine);

        return response()->noContent();
    }

    /**
     * Map the API's exercise_ids contract onto the service's exercises array.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function routineData(array $validated): array
    {
        $exerciseIds = $validated['exercise_ids'] ?? null;
        unset($validated['exercise_ids']);

        if ($exerciseIds !== null) {
            $validated['exercises'] = collect($exerciseIds)
                ->map(fn ($id) => ['id' => $id])
                ->all();
        }

        return $validated;
    }
}
```

- [ ] **Step 6: Add API routes**

En `routes/api.php`, dentro del bloque `// Fitness`, después de `Route::delete('workouts/{workout}', ...)`:

```php
        Route::post('workouts/{workout}/exercises', [WorkoutController::class, 'addExercise']);
        Route::post('workout-exercises/{workoutExercise}/sets', [WorkoutController::class, 'logSet']);
```

Y después de `Route::post('routines', [RoutineController::class, 'store']);`:

```php
        Route::patch('routines/{routine}', [RoutineController::class, 'update']);
        Route::delete('routines/{routine}', [RoutineController::class, 'destroy']);
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Api/GymApiWriteTest.php tests/Feature/Api/WorkoutApiTest.php tests/Feature/Api/RoutineApiTest.php tests/Feature/Api/ExerciseApiTest.php`
Expected: PASS. Si `WorkoutApiTest` espera `assertJsonPath('data.exercises.0.sets.0.weight', '50.00')`, debe seguir verde (no se tocó el Resource).

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api app/Http/Requests/Api routes/api.php tests/Feature/Api/GymApiWriteTest.php
git commit -m "feat(api): gym detail endpoints, routine writes and scoped routine_id"
```

---

### Task 9: MCP tools — servicio, acciones nuevas y descubrimiento de IDs

**Files:**
- Modify: `app/Mcp/Tools/WorkoutWriteTool.php`
- Modify: `app/Mcp/Tools/WorkoutReadTool.php`
- Test: `tests/Feature/Mcp/WorkoutToolsTest.php`

**Interfaces:**
- Consumes: `WorkoutSessionService`, `RoutineService`.
- Produces:
  - `workout-write` acciones: `create_workout` (idempotente por workout activo), `add_exercise` (`exercise_id|exercise_name`), `log_set` (`set_number` opcional), `finish_workout`, `create_routine` (con `exercises[]`), `add_routine_exercise`.
  - `workout-read` devuelve `exercise_id` por ejercicio y un bloque `routines` (con `exercise_id` y targets).

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Mcp/WorkoutToolsTest.php`:

```php
<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\WorkoutReadTool;
use App\Mcp\Tools\WorkoutWriteTool;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;

uses(RefreshDatabase::class);

it('creates a workout and logs sets through the mcp server', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, ['action' => 'create_workout', 'notes' => 'MCP session'])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->has('workout')->etc();
        });

    $workout = Workout::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, [
            'action' => 'add_exercise',
            'workout_id' => $workout->id,
            'exercise_name' => 'Remo con barra',
        ])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($workout) {
            $json->where('workout_exercise.exercise.name', 'Remo con barra')
                ->where('workout_exercise.workout_id', $workout->id)
                ->etc();
        });

    $workoutExercise = $workout->exercises()->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, [
            'action' => 'log_set',
            'workout_exercise_id' => $workoutExercise->id,
            'weight' => 60,
            'reps' => 8,
            'completed' => true,
        ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('set.set_number', 1)->etc();
        });
});

it('finishes workouts and creates routines through mcp', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, ['action' => 'finish_workout', 'workout_id' => $workout->id])
        ->assertOk();

    expect($workout->refresh()->ended_at)->not->toBeNull();

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, [
            'action' => 'create_routine',
            'name' => 'Push day',
            'exercises' => [
                ['name' => 'Press banca', 'target_sets' => 4],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(function ($json) {
            $json->where('routine.name', 'Push day')->etc();
        });
});

it('refuses to write on another users workout via mcp', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $other->id]);

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutWriteTool::class, ['action' => 'finish_workout', 'workout_id' => $workout->id])
        ->assertHasErrors(['Workout not found or unauthorized']);
});

it('exposes exercise ids and routines in workout-read', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Sentadilla']);
    $routine = App\Models\Routine::factory()->create(['user_id' => $user->id, 'name' => 'Leg day']);
    $routine->exercises()->attach($exercise->id, ['order' => 1, 'target_sets' => 5]);
    $workout = Workout::factory()->create(['user_id' => $user->id, 'routine_id' => $routine->id]);
    $workout->exercises()->create(['exercise_id' => $exercise->id, 'order' => 1]);

    MegalomaniacServer::actingAs($user)
        ->tool(WorkoutReadTool::class, [])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($exercise) {
            $json->where('workouts.0.exercises.0.exercise_id', $exercise->id)
                ->where('routines.0.exercises.0.exercise_id', $exercise->id)
                ->etc();
        });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Mcp/WorkoutToolsTest.php`
Expected: FAIL — acciones nuevas no existen y `workout-read` no incluye `exercise_id`/`routines`.

- [ ] **Step 3: Rewrite `WorkoutWriteTool`**

Reemplazar el contenido de `app/Mcp/Tools/WorkoutWriteTool.php` por:

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\Gym\RoutineService;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class WorkoutWriteTool extends Tool
{
    protected string $name = 'workout-write';

    protected string $description = 'Create and update workouts, exercises, sets and routines for the authenticated user.';

    public function __construct(
        protected WorkoutSessionService $sessions,
        protected RoutineService $routines,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('Action: create_workout, add_exercise, log_set, finish_workout, create_routine, add_routine_exercise')
                ->enum(['create_workout', 'add_exercise', 'log_set', 'finish_workout', 'create_routine', 'add_routine_exercise'])
                ->required(),
            'routine_id' => $schema->integer()->description('Routine ID (create_workout, add_routine_exercise)'),
            'workout_id' => $schema->integer()->description('Workout ID (add_exercise, finish_workout)'),
            'workout_exercise_id' => $schema->integer()->description('Workout exercise ID (log_set)'),
            'exercise_id' => $schema->integer()->description('Existing exercise ID (add_exercise, add_routine_exercise)'),
            'exercise_name' => $schema->string()->description('Exercise name; created if missing (add_exercise, add_routine_exercise)'),
            'set_number' => $schema->integer()->description('Set number (log_set; auto-increments when omitted)'),
            'weight' => $schema->number()->description('Weight in kg (log_set)'),
            'reps' => $schema->integer()->description('Reps (log_set)'),
            'rpe' => $schema->number()->description('RPE 1-10 (log_set)'),
            'completed' => $schema->boolean()->description('Completed (log_set; default true)'),
            'started_at' => $schema->string()->description('ISO 8601 start datetime (create_workout)'),
            'ended_at' => $schema->string()->description('ISO 8601 end datetime (finish_workout)'),
            'notes' => $schema->string()->description('Notes (create_workout, finish_workout, routine exercise)'),
            'name' => $schema->string()->description('Routine name (create_routine)'),
            'focus' => $schema->string()->description('Routine focus (create_routine)'),
            'scheduled_date' => $schema->string()->description('Routine schedule (create_routine)'),
            'target_sets' => $schema->integer()->description('Target sets (add_routine_exercise)'),
            'target_reps' => $schema->string()->description('Target reps (add_routine_exercise)'),
            'target_weight' => $schema->string()->description('Target weight (add_routine_exercise)'),
            'exercises' => $schema->array()
                ->description('Routine exercises (create_routine)')
                ->items($schema->object([
                    'name' => $schema->string()->description('Exercise name; created if missing'),
                    'exercise_id' => $schema->integer()->description('Existing exercise ID'),
                    'target_sets' => $schema->integer(),
                    'target_reps' => $schema->string(),
                    'target_weight' => $schema->string(),
                    'notes' => $schema->string(),
                ])),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ((string) $request->get('action')) {
                'create_workout' => $this->createWorkout($request, $user),
                'add_exercise' => $this->addExercise($request, $user),
                'log_set' => $this->logSet($request, $user),
                'finish_workout' => $this->finishWorkout($request, $user),
                'create_routine' => $this->createRoutine($request, $user),
                'add_routine_exercise' => $this->addRoutineExercise($request, $user),
                default => Response::error('Invalid action: '.(string) $request->get('action')),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function createWorkout(Request $request, User $user): Response|ResponseFactory
    {
        if ($active = $this->sessions->activeFor($user)) {
            return Response::structured([
                'workout' => $active->load('exercises.sets'),
                'message' => 'An active workout already exists.',
            ]);
        }

        $workout = $this->sessions->start(
            $user,
            $request->get('routine_id') !== null ? (int) $request->get('routine_id') : null,
            $request->get('started_at'),
            $request->get('notes'),
        );

        return Response::structured([
            'workout' => $workout->load('exercises.sets'),
            'message' => 'Workout created successfully.',
        ]);
    }

    private function addExercise(Request $request, User $user): Response|ResponseFactory
    {
        $workout = Workout::where('user_id', $user->id)->find((int) $request->get('workout_id'));

        if (! $workout) {
            return Response::error('Workout not found or unauthorized.');
        }

        $workoutExercise = $this->sessions->addExercise(
            $user,
            $workout,
            $request->get('exercise_id') !== null ? (int) $request->get('exercise_id') : null,
            $request->get('exercise_name'),
        );

        return Response::structured([
            'workout_exercise' => $workoutExercise->load(['exercise', 'sets']),
            'message' => 'Exercise added to workout.',
        ]);
    }

    private function logSet(Request $request, User $user): Response|ResponseFactory
    {
        $workoutExercise = WorkoutExercise::whereHas('workout', fn ($query) => $query->where('user_id', $user->id))
            ->find((int) $request->get('workout_exercise_id'));

        if (! $workoutExercise) {
            return Response::error('Workout exercise not found or unauthorized.');
        }

        $data = array_filter([
            'set_number' => $request->get('set_number') !== null ? (int) $request->get('set_number') : null,
            'weight' => $request->get('weight'),
            'reps' => $request->get('reps'),
            'rpe' => $request->get('rpe'),
            'completed' => $request->get('completed', true),
        ], fn (mixed $value): bool => $value !== null);

        $set = $this->sessions->logSet($user, $workoutExercise, $data);

        return Response::structured([
            'set' => $set,
            'message' => 'Set logged successfully.',
        ]);
    }

    private function finishWorkout(Request $request, User $user): Response|ResponseFactory
    {
        $workout = Workout::where('user_id', $user->id)->find((int) $request->get('workout_id'));

        if (! $workout) {
            return Response::error('Workout not found or unauthorized.');
        }

        $workout = $this->sessions->finish($user, $workout, $request->get('ended_at'), $request->get('notes'));

        return Response::structured([
            'workout' => $workout,
            'message' => 'Workout finished.',
        ]);
    }

    private function createRoutine(Request $request, User $user): Response|ResponseFactory
    {
        $name = trim((string) $request->get('name'));

        if ($name === '') {
            return Response::error('Routine name is required.');
        }

        $routine = $this->routines->create($user, [
            'name' => $name,
            'focus' => $request->get('focus'),
            'scheduled_date' => $request->get('scheduled_date'),
            'exercises' => $request->get('exercises', []),
        ]);

        return Response::structured([
            'routine' => $routine->load('exercises'),
            'message' => 'Routine created.',
        ]);
    }

    private function addRoutineExercise(Request $request, User $user): Response|ResponseFactory
    {
        $routine = Routine::where('user_id', $user->id)->find((int) $request->get('routine_id'));

        if (! $routine) {
            return Response::error('Routine not found or unauthorized.');
        }

        $exercises = $routine->exercises->map(fn ($exercise) => [
            'id' => $exercise->id,
            'target_sets' => $exercise->pivot->target_sets,
            'target_reps' => $exercise->pivot->target_reps,
            'target_weight' => $exercise->pivot->target_weight,
            'notes' => $exercise->pivot->notes,
        ])->all();

        $exercises[] = array_filter([
            'id' => $request->get('exercise_id') !== null ? (int) $request->get('exercise_id') : null,
            'name' => $request->get('exercise_name'),
            'target_sets' => $request->get('target_sets') !== null ? (int) $request->get('target_sets') : null,
            'target_reps' => $request->get('target_reps'),
            'target_weight' => $request->get('target_weight'),
            'notes' => $request->get('notes'),
        ], fn (mixed $value): bool => $value !== null);

        $routine = $this->routines->update($user, $routine, ['exercises' => $exercises]);

        return Response::structured([
            'routine' => $routine->load('exercises'),
            'message' => 'Exercise added to routine.',
        ]);
    }
}
```

- [ ] **Step 4: Update `WorkoutReadTool`**

En `app/Mcp/Tools/WorkoutReadTool.php`:

1. Agregar `use App\Models\Routine;`.
2. Dentro del `map` de ejercicios, agregar `'exercise_id' => $ex->exercise_id,` justo después de `'id' => $ex->id,`.
3. Antes del `return Response::structured([...])`, agregar el bloque de rutinas y sumarlo al payload:

```php
        $routines = Routine::where('user_id', $user->id)
            ->with('exercises')
            ->orderBy('name')
            ->get()
            ->map(fn (Routine $routine) => [
                'id' => $routine->id,
                'name' => $routine->name,
                'focus' => $routine->focus,
                'scheduled_date' => $routine->scheduled_date,
                'status' => $routine->status,
                'exercises' => $routine->exercises->map(fn ($exercise) => [
                    'exercise_id' => $exercise->id,
                    'name' => $exercise->name,
                    'target_sets' => $exercise->pivot->target_sets,
                    'target_reps' => $exercise->pivot->target_reps,
                    'target_weight' => $exercise->pivot->target_weight,
                ])->all(),
            ]);
```

y en `Response::structured([...])` agregar la clave:

```php
            'routines' => $routines->all(),
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Mcp/WorkoutToolsTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Mcp tests/Feature/Mcp
git commit -m "feat(mcp): gym write actions via services and id discovery in workout-read"
```

---

### Task 10: Chat `GymActionTool`

**Files:**
- Create: `app/Ai/Tools/GymActionTool.php`
- Test: `tests/Feature/Ai/GymActionToolTest.php`

**Interfaces:**
- Consumes: `WorkoutSessionService`, `RoutineService`, modelos `Workout`, `WorkoutExercise`, `PersonalRecord`.
- Produces: tool aprobable `GymActionTool` con acciones `create_workout`, `add_exercise`, `log_set`, `finish_workout`, `create_routine`, `add_routine_exercise`, `update_routine`. Constructor: `__construct(User $user, WorkoutSessionService $sessions, RoutineService $routines)`. Devuelve JSON con `success` y payload.

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Ai/GymActionToolTest.php`:

```php
<?php

use App\Ai\Tools\GymActionTool;
use App\Models\Routine;
use App\Models\User;
use App\Services\Gym\RoutineService;
use App\Services\Gym\WorkoutSessionService;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function gymTool(User $user): GymActionTool
{
    return new GymActionTool($user, app(WorkoutSessionService::class), app(RoutineService::class));
}

it('creates a workout from a routine and returns workout exercise ids', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['user_id' => $user->id]);
    $routine->exercises()->attach(
        App\Models\Exercise::factory()->create()->id,
        ['order' => 1, 'target_sets' => 2],
    );

    $result = gymTool($user)->handle(new Request([
        'action' => 'create_workout',
        'routine_id' => $routine->id,
    ]));

    $data = json_decode($result, true);

    expect($data['success'])->toBeTrue()
        ->and($data['workout']['exercises'])->toHaveCount(1)
        ->and($data['workout']['exercises'][0]['id'])->toBeInt()
        ->and($data['workout']['exercises'][0]['sets'])->toHaveCount(2);
});

it('adds exercises by name, logging sets and detecting prs', function () {
    $user = User::factory()->create();

    gymTool($user)->handle(new Request(['action' => 'create_workout']));

    $workout = $user->workouts()->firstOrFail();

    $added = json_decode(gymTool($user)->handle(new Request([
        'action' => 'add_exercise',
        'workout_id' => $workout->id,
        'exercise_name' => 'Press banca',
    ])), true);

    expect($added['success'])->toBeTrue();

    $workoutExerciseId = $added['workout_exercise']['id'];

    $set = json_decode(gymTool($user)->handle(new Request([
        'action' => 'log_set',
        'workout_exercise_id' => $workoutExerciseId,
        'weight' => 100,
        'reps' => 5,
        'completed' => true,
    ])), true);

    expect($set['success'])->toBeTrue()
        ->and($set['set']['set_number'])->toBe(1)
        ->and($set['set']['is_pr'])->toBeTrue();

    $second = json_decode(gymTool($user)->handle(new Request([
        'action' => 'log_set',
        'workout_exercise_id' => $workoutExerciseId,
        'weight' => 90,
        'reps' => 5,
    ])), true);

    expect($second['set']['set_number'])->toBe(2);
});

it('creates a routine with exercises in one call and updates it', function () {
    $user = User::factory()->create();

    $created = json_decode(gymTool($user)->handle(new Request([
        'action' => 'create_routine',
        'name' => 'Piernas',
        'focus' => 'Legs',
        'exercises' => [
            ['name' => 'Sentadilla', 'target_sets' => 5, 'target_reps' => '5'],
            ['name' => 'Prensa', 'target_sets' => 3],
        ],
    ])), true);

    expect($created['success'])->toBeTrue()
        ->and($created['routine']['exercises'])->toHaveCount(2);

    $routineId = $created['routine']['id'];

    $updated = json_decode(gymTool($user)->handle(new Request([
        'action' => 'update_routine',
        'routine_id' => $routineId,
        'name' => 'Piernas v2',
    ])), true);

    expect($updated['success'])->toBeTrue()
        ->and($updated['routine']['name'])->toBe('Piernas v2');
});

it('returns readable errors and requires approval', function () {
    $user = User::factory()->create();

    $result = json_decode(gymTool($user)->handle(new Request([
        'action' => 'add_exercise',
        'workout_id' => 999,
        'exercise_name' => 'X',
    ])), true);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('not found');

    expect(gymTool($user)->needsApproval(new Request(['action' => 'log_set'])))
        ->toBeInstanceOf(Approval::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Ai/GymActionToolTest.php`
Expected: FAIL — `Class "App\Ai\Tools\GymActionTool" not found`.

- [ ] **Step 3: Implement `GymActionTool`**

`app/Ai/Tools/GymActionTool.php`:

```php
<?php

namespace App\Ai\Tools;

use App\Models\PersonalRecord;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Services\Gym\RoutineService;
use App\Services\Gym\WorkoutSessionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GymActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected WorkoutSessionService $sessions,
        protected RoutineService $routines,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create and update gym data on behalf of the user: start workouts (optionally from a routine, copying its template), add exercises by name or id, log sets, finish workouts, and create or extend routines with targets. Use this when the user asks to record, create or update training data.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus entrenamientos').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'create_workout' => 'iniciar un entrenamiento',
            'add_exercise' => 'añadir un ejercicio al entrenamiento',
            'log_set' => 'registrar una serie',
            'finish_workout' => 'terminar el entrenamiento',
            'create_routine' => 'crear una rutina',
            'add_routine_exercise' => 'añadir un ejercicio a la rutina',
            'update_routine' => 'actualizar la rutina',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'create_workout' => $this->createWorkout($request),
                'add_exercise' => $this->addExercise($request),
                'log_set' => $this->logSet($request),
                'finish_workout' => $this->finishWorkout($request),
                'create_routine' => $this->createRoutine($request),
                'add_routine_exercise' => $this->addRoutineExercise($request),
                'update_routine' => $this->updateRoutine($request),
                default => $this->error('Invalid action. Use: create_workout, add_exercise, log_set, finish_workout, create_routine, add_routine_exercise, update_routine'),
            };
        } catch (ModelNotFoundException|AuthorizationException|InvalidArgumentException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function createWorkout(Request $request): string
    {
        if ($active = $this->sessions->activeFor($this->user)) {
            return $this->success('An active workout already exists.', [
                'workout' => $this->workoutPayload($active),
            ]);
        }

        $workout = $this->sessions->start(
            $this->user,
            isset($request['routine_id']) ? (int) $request['routine_id'] : null,
            $request['started_at'] ?? null,
            $request['notes'] ?? null,
        );

        return $this->success('Workout created', [
            'workout' => $this->workoutPayload($workout),
        ]);
    }

    private function addExercise(Request $request): string
    {
        $workout = $this->findWorkout($request['workout_id'] ?? null);

        if (! $workout) {
            return $this->error('Workout not found');
        }

        $workoutExercise = $this->sessions->addExercise(
            $this->user,
            $workout,
            isset($request['exercise_id']) ? (int) $request['exercise_id'] : null,
            $request['exercise_name'] ?? null,
        );

        return $this->success('Exercise added', [
            'workout_exercise' => $workoutExercise->load('exercise')->toArray(),
        ]);
    }

    private function logSet(Request $request): string
    {
        $workoutExercise = WorkoutExercise::whereHas('workout', fn ($query) => $query->where('user_id', $this->user->id))
            ->find($request['workout_exercise_id'] ?? null);

        if (! $workoutExercise) {
            return $this->error('Workout exercise not found');
        }

        $set = $this->sessions->logSet($this->user, $workoutExercise, array_filter([
            'set_number' => isset($request['set_number']) ? (int) $request['set_number'] : null,
            'weight' => $request['weight'] ?? null,
            'reps' => $request['reps'] ?? null,
            'rpe' => $request['rpe'] ?? null,
            'completed' => $request['completed'] ?? true,
        ], fn (mixed $value): bool => $value !== null));

        return $this->success('Set logged', [
            'set' => $set->toArray() + [
                'is_pr' => PersonalRecord::where('workout_set_id', $set->id)->exists(),
            ],
        ]);
    }

    private function finishWorkout(Request $request): string
    {
        $workout = $this->findWorkout($request['workout_id'] ?? null);

        if (! $workout) {
            return $this->error('Workout not found');
        }

        $workout = $this->sessions->finish(
            $this->user,
            $workout,
            $request['ended_at'] ?? null,
            $request['notes'] ?? null,
        );

        return $this->success('Workout finished', [
            'workout' => $workout->toArray(),
        ]);
    }

    private function createRoutine(Request $request): string
    {
        $name = trim((string) ($request['name'] ?? ''));

        if ($name === '') {
            return $this->error('Routine name is required');
        }

        $routine = $this->routines->create($this->user, [
            'name' => $name,
            'focus' => $request['focus'] ?? null,
            'scheduled_date' => $request['scheduled_date'] ?? null,
            'exercises' => $request['exercises'] ?? [],
        ]);

        return $this->success('Routine created', [
            'routine' => $routine->load('exercises')->toArray(),
        ]);
    }

    private function addRoutineExercise(Request $request): string
    {
        $routine = $this->findRoutine($request['routine_id'] ?? null);

        if (! $routine) {
            return $this->error('Routine not found');
        }

        $exercises = $routine->exercises->map(fn ($exercise) => [
            'id' => $exercise->id,
            'target_sets' => $exercise->pivot->target_sets,
            'target_reps' => $exercise->pivot->target_reps,
            'target_weight' => $exercise->pivot->target_weight,
            'notes' => $exercise->pivot->notes,
        ])->all();

        $exercises[] = array_filter([
            'id' => isset($request['exercise_id']) ? (int) $request['exercise_id'] : null,
            'name' => $request['exercise_name'] ?? null,
            'target_sets' => isset($request['target_sets']) ? (int) $request['target_sets'] : null,
            'target_reps' => $request['target_reps'] ?? null,
            'target_weight' => $request['target_weight'] ?? null,
            'notes' => $request['notes'] ?? null,
        ], fn (mixed $value): bool => $value !== null);

        $routine = $this->routines->update($this->user, $routine, ['exercises' => $exercises]);

        return $this->success('Exercise added to routine', [
            'routine' => $routine->load('exercises')->toArray(),
        ]);
    }

    private function updateRoutine(Request $request): string
    {
        $routine = $this->findRoutine($request['routine_id'] ?? null);

        if (! $routine) {
            return $this->error('Routine not found');
        }

        $routine = $this->routines->update($this->user, $routine, array_filter([
            'name' => $request['name'] ?? null,
            'focus' => $request['focus'] ?? null,
            'scheduled_date' => $request['scheduled_date'] ?? null,
            'status' => $request['status'] ?? null,
        ], fn (mixed $value): bool => $value !== null));

        return $this->success('Routine updated', [
            'routine' => $routine->load('exercises')->toArray(),
        ]);
    }

    private function findWorkout(mixed $workoutId): ?Workout
    {
        if (! $workoutId) {
            return null;
        }

        return $this->user->workouts()->find((int) $workoutId);
    }

    private function findRoutine(mixed $routineId): ?Routine
    {
        if (! $routineId) {
            return null;
        }

        return $this->user->routines()->find((int) $routineId);
    }

    /**
     * @return array<string, mixed>
     */
    private function workoutPayload(Workout $workout): array
    {
        return $workout->load(['exercises.sets', 'exercises.exercise'])->toArray();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function success(string $message, array $payload = []): string
    {
        return json_encode(array_merge([
            'success' => true,
            'message' => $message,
        ], $payload), JSON_PRETTY_PRINT);
    }

    private function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create_workout', 'add_exercise', 'log_set', 'finish_workout', 'create_routine', 'add_routine_exercise', 'update_routine'])
                ->description('Action to perform')
                ->required(),
            'routine_id' => $schema->integer()->description('Routine ID (create_workout, add_routine_exercise, update_routine)'),
            'workout_id' => $schema->integer()->description('Workout ID (add_exercise, finish_workout)'),
            'workout_exercise_id' => $schema->integer()->description('Workout exercise ID (log_set; get it from create_workout/add_exercise/workout query)'),
            'exercise_id' => $schema->integer()->description('Existing exercise ID (add_exercise, add_routine_exercise)'),
            'exercise_name' => $schema->string()->description('Exercise name; created if missing (add_exercise, add_routine_exercise)'),
            'set_number' => $schema->integer()->description('Set number (log_set; auto-increments when omitted)'),
            'weight' => $schema->number()->description('Weight (log_set)'),
            'reps' => $schema->integer()->description('Reps (log_set)'),
            'rpe' => $schema->number()->description('RPE 1-10 (log_set)'),
            'completed' => $schema->boolean()->description('Completed (log_set; default true)'),
            'started_at' => $schema->string()->description('ISO 8601 start datetime (create_workout)'),
            'ended_at' => $schema->string()->description('ISO 8601 end datetime (finish_workout)'),
            'notes' => $schema->string()->description('Notes (create_workout, finish_workout)'),
            'name' => $schema->string()->description('Routine name (create_routine, update_routine)'),
            'focus' => $schema->string()->description('Routine focus (create_routine, update_routine)'),
            'scheduled_date' => $schema->string()->description('Routine scheduled date (create_routine, update_routine)'),
            'status' => $schema->string()->description('Routine status (update_routine)')->enum(['active', 'inactive', 'archived']),
            'target_sets' => $schema->integer()->description('Target sets (add_routine_exercise)'),
            'target_reps' => $schema->string()->description('Target reps (add_routine_exercise)'),
            'target_weight' => $schema->string()->description('Target weight (add_routine_exercise)'),
            'exercises' => $schema->array()
                ->description('Routine exercises, in order (create_routine)')
                ->items($schema->object([
                    'name' => $schema->string()->description('Exercise name; created if missing'),
                    'exercise_id' => $schema->integer()->description('Existing exercise ID'),
                    'target_sets' => $schema->integer(),
                    'target_reps' => $schema->string(),
                    'target_weight' => $schema->string(),
                    'notes' => $schema->string(),
                ])),
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Ai/GymActionToolTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Tools/GymActionTool.php tests/Feature/Ai/GymActionToolTest.php
git commit -m "feat(ai): gym action tool for chat writes"
```

---

### Task 11: Chat — `GymQueryTool`, catálogo, router y tests existentes

**Files:**
- Create: `app/Ai/Tools/GymQueryTool.php` (mover desde `WorkoutQueryTool.php`)
- Delete: `app/Ai/Tools/WorkoutQueryTool.php`
- Modify: `app/Ai/Tools/ToolCatalog.php`
- Modify: `config/ai_tools.php`
- Modify: `app/Ai/Agents/MegalomaniacAgent.php`
- Modify: `tests/Feature/Ai/ToolCatalogTest.php`, `tests/Feature/Ai/ToolRouterTest.php`, `tests/Feature/Ai/MegalomaniacAgentTest.php`, `tests/Feature/Agents/AgentRunnerTest.php`, `tests/Feature/Ai/ChatApprovalTest.php`
- Test: `tests/Feature/Ai/GymQueryToolTest.php`

**Interfaces:**
- Consumes: modelos `Workout`, `Exercise`, `Routine`; `GymActionTool`.
- Produces: `GymQueryTool::handle(Request): Stringable|string` con `resource` = `workouts|exercises|routines`; grupo `workout` = `[GymQueryTool, GymActionTool]`.

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Ai/GymQueryToolTest.php`:

```php
<?php

use App\Ai\Tools\GymQueryTool;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

it('returns workouts with exercise ids and filters by exercise name', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Press banca']);
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workout->exercises()->create(['exercise_id' => $exercise->id, 'order' => 1]);

    $data = json_decode((new GymQueryTool($user))->handle(new Request(['days' => 30])), true);

    expect($data)->toHaveCount(1)
        ->and($data[0]['exercises'][0]['exercise_id'])->toBe($exercise->id);

    $filtered = json_decode((new GymQueryTool($user))->handle(new Request(['exercise' => 'banca'])), true);

    expect($filtered)->toHaveCount(1);
});

it('lists the exercise library and routines with targets', function () {
    $user = User::factory()->create();
    $exercise = Exercise::factory()->create(['name' => 'Sentadilla']);
    $routine = $user->routines()->create(['name' => 'Leg day']);
    $routine->exercises()->attach($exercise->id, ['order' => 1, 'target_sets' => 5]);

    $library = json_decode((new GymQueryTool($user))->handle(new Request(['resource' => 'exercises', 'search' => 'senta'])), true);

    expect($library)->toHaveCount(1)
        ->and($library[0]['name'])->toBe('Sentadilla');

    $routines = json_decode((new GymQueryTool($user))->handle(new Request(['resource' => 'routines'])), true);

    expect($routines)->toHaveCount(1)
        ->and($routines[0]['exercises'][0]['exercise_id'])->toBe($exercise->id)
        ->and($routines[0]['exercises'][0]['target_sets'])->toBe(5);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Ai/GymQueryToolTest.php`
Expected: FAIL — clase no existe.

- [ ] **Step 3: Create `GymQueryTool` and delete the old one**

`app/Ai/Tools/GymQueryTool.php`:

```php
<?php

namespace App\Ai\Tools;

use App\Models\Exercise;
use App\Models\Routine;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GymQueryTool implements Tool
{
    public function __construct(protected User $user) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s gym data: workouts with exercises and sets, the exercise library, and routines with targets. Use resource=exercises to discover exercise names/ids before adding exercises, and resource=routines to discover routine ids.';
    }

    public function handle(Request $request): Stringable|string
    {
        $resource = $request['resource'] ?? 'workouts';

        return match ($resource) {
            'exercises' => $this->exercises($request),
            'routines' => $this->routines(),
            default => $this->workouts($request),
        };
    }

    private function workouts(Request $request): string
    {
        $query = Workout::with(['exercises.exercise', 'exercises.sets', 'routine'])
            ->where('user_id', $this->user->id);

        $query->where('started_at', '>=', now()->subDays((int) ($request['days'] ?? 30)));

        if (isset($request['exercise'])) {
            $query->whereHas('exercises.exercise', function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request['exercise'].'%');
            });
        }

        $workouts = $query->latest('started_at')->limit(10)->get();

        if ($workouts->isEmpty()) {
            return 'No workouts found matching the criteria.';
        }

        return json_encode($workouts->toArray(), JSON_PRETTY_PRINT);
    }

    private function exercises(Request $request): string
    {
        $exercises = Exercise::query()
            ->when($request['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->when($request['muscle_group'] ?? null, fn ($query, $group) => $query->where('muscle_group', $group))
            ->orderBy('name')
            ->limit(100)
            ->get();

        return json_encode($exercises->toArray(), JSON_PRETTY_PRINT);
    }

    private function routines(): string
    {
        $routines = Routine::with('exercises')
            ->where('user_id', $this->user->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Routine $routine) => [
                'id' => $routine->id,
                'name' => $routine->name,
                'focus' => $routine->focus,
                'scheduled_date' => $routine->scheduled_date,
                'status' => $routine->status,
                'exercises' => $routine->exercises->map(fn (Exercise $exercise) => [
                    'exercise_id' => $exercise->id,
                    'name' => $exercise->name,
                    'target_sets' => $exercise->pivot->target_sets,
                    'target_reps' => $exercise->pivot->target_reps,
                    'target_weight' => $exercise->pivot->target_weight,
                ])->all(),
            ]);

        return json_encode($routines->all(), JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()
                ->description('What to fetch: workouts (default), exercises (library), routines')
                ->enum(['workouts', 'exercises', 'routines']),
            'days' => $schema->integer()
                ->description('Days to look back for workouts (default: 30)')
                ->default(30),
            'exercise' => $schema->string()
                ->description('Filter workouts by exercise name (partial match)'),
            'search' => $schema->string()
                ->description('Filter the exercise library by name (resource=exercises)'),
            'muscle_group' => $schema->string()
                ->description('Filter the exercise library by muscle group (resource=exercises)'),
        ];
    }
}
```

Borrar el archivo viejo:

```bash
rm app/Ai/Tools/WorkoutQueryTool.php
```

- [ ] **Step 4: Wire catalog, config, instructions and existing tests**

En `app/Ai/Tools/ToolCatalog.php`:

- Reemplazar el use `WorkoutQueryTool` por `GymQueryTool` (agregar `use App\Services\Gym\RoutineService;` y `use App\Services\Gym\WorkoutSessionService;`).
- Cambiar el grupo:

```php
            'workout' => ['label' => 'Entrenamientos', 'tools' => [GymQueryTool::class, GymActionTool::class]],
```

- En `make()`, agregar el brazo antes de `default`:

```php
            GymActionTool::class => new GymActionTool($user, app(WorkoutSessionService::class), app(RoutineService::class)),
```

En `config/ai_tools.php`, en `write_verbs`, reemplazar `'anadi', 'suma',` por `'anad', 'suma',` y agregar al final de la lista: `'escrib', 'ingres', 'carg', 'hazme', 'prepara',`.

En el mismo archivo, en `keywords['workout']`, agregar al final: `'pesa', 'banca', 'dominada', 'curl',`.

En `app/Ai/Agents/MegalomaniacAgent.php`, dentro del heredoc de `instructions()`, después del párrafo de "When the user asks to perform an action...", agregar:

```
For training: start a workout (optionally from a routine so the template is copied),
add exercises by name (new ones are created automatically), log sets with weight/reps/rpe,
and finish the workout. To create a routine with several exercises, send all of them in the
"exercises" array in a single create_routine call.
```

Actualizar imports/índices en `tests/Feature/Ai/MegalomaniacAgentTest.php`:

- Reemplazar `use App\Ai\Tools\WorkoutQueryTool;` por:

```php
use App\Ai\Tools\GymActionTool;
use App\Ai\Tools\GymQueryTool;
```

- En `test('megalomaniac agent has correct tools')` reemplazar el bloque de aserciones por:

```php
    expect($tools)->toHaveCount(17);
    expect($tools[0])->toBeInstanceOf(TaskQueryTool::class);
    expect($tools[1])->toBeInstanceOf(GymQueryTool::class);
    expect($tools[2])->toBeInstanceOf(GymActionTool::class);
    expect($tools[3])->toBeInstanceOf(FinanceQueryTool::class);
    expect($tools[4])->toBeInstanceOf(NutritionQueryTool::class);
    expect($tools[5])->toBeInstanceOf(GroceryQueryTool::class);
    expect($tools[6])->toBeInstanceOf(ActionTool::class);
    expect($tools[7])->toBeInstanceOf(IntegrationCatalogTool::class);
    expect($tools[8])->toBeInstanceOf(IntegrationCallTool::class);
    expect($tools[9])->toBeInstanceOf(ManageAgentsTool::class);
    expect($tools[10])->toBeInstanceOf(LoadSkillTool::class);
    expect($tools[11])->toBeInstanceOf(RememberMemoryTool::class);
    expect($tools[12])->toBeInstanceOf(ForgetMemoryTool::class);
    expect($tools[13])->toBeInstanceOf(PromoteMemoryTool::class);
    expect($tools[14])->toBeInstanceOf(WebSearchTool::class);
    expect($tools[15])->toBeInstanceOf(WebFetchTool::class);
    expect($tools[16])->toBeInstanceOf(AskUserTool::class);
```

- En `test('workout query tool returns workouts')`, reemplazar `new WorkoutQueryTool($user)` por `new GymQueryTool($user)`.

Actualizar `tests/Feature/Agents/AgentRunnerTest.php`: reemplazar `use App\Ai\Tools\WorkoutQueryTool;` por `use App\Ai\Tools\GymQueryTool;` y `not->toContain(WorkoutQueryTool::class)` por `not->toContain(GymQueryTool::class)`.

Actualizar `tests/Feature/Ai/ChatApprovalTest.php`: reemplazar `->not->toContain('WorkoutQueryTool')` por `->not->toContain('GymQueryTool')`.

Actualizar `tests/Feature/Ai/ToolCatalogTest.php`:

- Imports: reemplazar `WorkoutQueryTool` por `GymQueryTool`, agregar `GymActionTool`.
- En `builds every tool for the wildcard`, actualizar:

```php
    expect($tools)->toHaveCount(16)
        ->toContain(TaskQueryTool::class, GymQueryTool::class, GymActionTool::class);
```

- En `lets the main agent be built with a tool subset`, actualizar el conteo:

```php
    $all = collect(iterator_to_array((new MegalomaniacAgent($user))->tools()))->count();
    expect($all)->toBe(17);
```

En `tests/Feature/Ai/ToolRouterTest.php`, agregar al final:

```php
it('routes gym messages to the workout group with its own write tool', function () {
    expect(ToolRouter::route('¿Cómo viene mi entrenamiento?'))->toContain('workout')
        ->and(ToolRouter::route('Escribí mi entrenamiento de pecho'))->toContain('workout')
        ->and(ToolRouter::route('Anadí press banca a mi rutina'))->toContain('workout')
        ->and(ToolRouter::route('Hazme una rutina de piernas'))->toContain('workout');
});
```

En `resources/js/lib/chat-tools.ts`, reemplazar `WorkoutQueryTool: 'Consultando entrenamientos',` por:

```ts
    GymQueryTool: 'Consultando entrenamientos',
    GymActionTool: 'Actualizando entrenamiento',
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Ai tests/Feature/Agents`
Expected: PASS. Si algún test de `ChatApprovalTest`/`AgentRunnerTest` mencionaba el nombre viejo, ya quedó actualizado.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai config resources/js/lib tests/Feature/Ai tests/Feature/Agents
git commit -m "feat(ai): gym query tool in workout group with discovery resources"
```

---

### Task 12: UI — botones muertos, borrado y PR timeline

**Files:**
- Modify: `resources/js/pages/fitness/gym-routine.tsx`
- Modify: `resources/js/pages/fitness/history.tsx`
- Verify: `npm run types`, `npm run build`

**Interfaces:**
- Consumes: `DELETE /gym/workout-exercises/{id}`, `DELETE /gym/workout-sets/{id}`, payloads con `is_pr` y `best_weight`, prop `personalRecords`.
- Produces: UI operativa (video/delete/badge) y timeline de PRs en historial.

- [ ] **Step 1: Update `gym-routine.tsx` interfaces and handlers**

En el bloque de interfaces (líneas 10-40), dejar:

```ts
interface Set {
    id: number;
    set_number: number;
    weight: string;
    reps: string;
    rpe: string;
    completed: boolean;
    is_pr?: boolean;
}

interface WorkoutExercise {
    id: number;
    exercise_id: number;
    exercise: {
        id: number;
        name: string;
        muscle_group: string;
        type: string;
        video_url?: string | null;
    };
    sets: Set[];
    previous: Set[] | null;
    best_weight?: number | null;
}
```

Después de `handleLogSet` (línea ~174), agregar:

```ts
    const handleRemoveExercise = (workoutExerciseId: number) => {
        router.delete(`/gym/workout-exercises/${workoutExerciseId}`, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updatedWorkout = (page.props as any).activeWorkout;
                if (updatedWorkout) setActiveWorkout(updatedWorkout);
            }
        });
    };

    const handleRemoveSet = (setId: number) => {
        router.delete(`/gym/workout-sets/${setId}`, {
            preserveScroll: true,
            onSuccess: (page) => {
                const updatedWorkout = (page.props as any).activeWorkout;
                if (updatedWorkout) setActiveWorkout(updatedWorkout);
            }
        });
    };
```

- [ ] **Step 2: Wire header buttons and PR badge**

En el render, reemplazar `const pr = getPrBadge(workoutExercise.previous);` por:

```ts
                                const pr = workoutExercise.best_weight ?? getPrBadge(workoutExercise.previous);
```

Reemplazar el bloque de botones (líneas ~330-337) por:

```tsx
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => workoutExercise.exercise.video_url && window.open(workoutExercise.exercise.video_url, '_blank', 'noopener')}
                                                disabled={!workoutExercise.exercise.video_url}
                                                title={workoutExercise.exercise.video_url ? 'Ver video' : 'Sin video'}
                                                className="p-2 text-[#e8b4b4] hover:text-white hover:bg-white/5 rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                            >
                                                <span className="material-symbols-outlined">videocam</span>
                                            </button>
                                            <button
                                                onClick={() => handleRemoveExercise(workoutExercise.id)}
                                                title="Eliminar ejercicio"
                                                className="p-2 text-[#e8b4b4] hover:text-red-400 hover:bg-red-400/10 rounded-lg transition-colors"
                                            >
                                                <span className="material-symbols-outlined">delete</span>
                                            </button>
                                        </div>
```

- [ ] **Step 3: Add delete-set column and PR trophy**

Cambiar las dos apariciones de `grid-cols-[30px_1fr_1fr_1fr_1fr_40px]` (header de sets y fila) por `grid-cols-[30px_1fr_1fr_1fr_1fr_40px_40px]`.

En el header, después del `<div className="text-center">...check...</div>`, agregar:

```tsx
                                            <div className="text-center"><span className="material-symbols-outlined text-sm">delete</span></div>
```

En la celda del número de set, reemplazar:

```tsx
                                                    <div className={`text-center font-black ${set.completed ? 'text-primary' : 'text-white'}`}>{set.set_number}</div>
```

por:

```tsx
                                                    <div className={`flex flex-col items-center font-black ${set.completed ? 'text-primary' : 'text-white'}`}>
                                                        <span>{set.set_number}</span>
                                                        {set.is_pr && (
                                                            <span className="material-symbols-outlined text-[12px] text-primary" title="Nuevo PR">emoji_events</span>
                                                        )}
                                                    </div>
```

Después del botón de check (cierra el `</button>` de la fila), agregar:

```tsx
                                                    <button
                                                        onClick={() => handleRemoveSet(set.id)}
                                                        title="Eliminar serie"
                                                        className="flex items-center justify-center h-9 w-full rounded-lg bg-[#3e2121] text-[#e8b4b4] hover:bg-red-400/10 hover:text-red-400 transition-all"
                                                    >
                                                        <span className="material-symbols-outlined text-lg">delete</span>
                                                    </button>
```

- [ ] **Step 4: Add PR timeline to `history.tsx`**

Agregar interfaces después de `interface Workout`:

```ts
interface PersonalRecordItem {
    id: number;
    type: string;
    value: string | number;
    reps: number | null;
    weight: string | number | null;
    achieved_at: string;
    exercise?: { name: string } | null;
}
```

Cambiar `interface Props` a:

```ts
interface Props {
    workouts: PaginatedWorkouts;
    personalRecords?: PersonalRecordItem[];
}
```

Agregar helper antes del componente:

```ts
function prLabel(type: string): string {
    if (type === 'one_rm') return '1RM';
    if (type === 'reps') return 'Reps';
    return 'Peso';
}
```

Cambiar la firma a `export default function History({ workouts, personalRecords }: Props) {`.

Después del bloque `{/* AI Analysis Section */} ...` (antes del `<div className="overflow-hidden rounded-2xl ...">` de la tabla), insertar:

```tsx
                {(personalRecords ?? []).length > 0 && (
                    <div className="bg-[#2b1a1a] border border-[#3e2121] rounded-2xl p-6 mb-6">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="h-10 w-10 rounded-xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary">
                                <span className="material-symbols-outlined">emoji_events</span>
                            </div>
                            <div>
                                <h3 className="text-lg font-black text-white">PR Timeline</h3>
                                <p className="text-[10px] font-black uppercase tracking-widest text-[#e8b4b4]">Récords personales</p>
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {(personalRecords ?? []).map((record) => (
                                <div key={record.id} className="rounded-xl border border-[#3e2121] bg-[#1c0f0f] p-4">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-bold text-white truncate">{record.exercise?.name ?? 'Ejercicio'}</span>
                                        <span className="rounded-full bg-primary/15 border border-primary/30 px-2 py-0.5 text-[10px] font-black uppercase tracking-widest text-primary">{prLabel(record.type)}</span>
                                    </div>
                                    <p className="mt-2 text-lg font-black text-white tabular-nums">
                                        {record.type === 'reps' ? `${record.reps} reps` : `${Number(record.value)} kg`}
                                        {record.type === 'one_rm' && <span className="ml-1 text-xs font-bold text-[#e8b4b4]">1RM est.</span>}
                                    </p>
                                    <p className="mt-1 text-[11px] font-medium text-[#e8b4b4]">{formatDate(record.achieved_at)}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
```

- [ ] **Step 5: Verify types and build**

Run: `npm run types`
Expected: sin errores nuevos.

Run: `npm run build`
Expected: build OK.

- [ ] **Step 6: Commit**

```bash
git add resources/js
git commit -m "feat(gym): wire exercise/set deletion, PR badges and PR timeline"
```

---

### Task 13: QA final

**Files:**
- Verify: suite completa, pint, build.
- Modify (docs): `docs/modules/gym.md`.

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: módulo verificado y documentado.

- [ ] **Step 1: Run the full suite**

Run: `php artisan test --compact`
Expected: PASS completo. Si algo falla por nombres viejos (`WorkoutQueryTool`), corregir la referencia y volver a correr.

- [ ] **Step 2: Pint on everything touched**

Run: `vendor/bin/pint --dirty --format agent`
Expected: "Fixed 0 files" o fixes aplicados; volver a correr la suite si cambió algo.

- [ ] **Step 3: Frontend checks**

Run: `npm run types && npm run build`
Expected: ambos OK.

- [ ] **Step 4: Update module docs**

En `docs/modules/gym.md`, agregar una sección "Escritura por agente (chat/MCP/API)" con:

- Acciones del chat (`GymActionTool`): create_workout (copia plantilla), add_exercise (por nombre), log_set (auto set_number), finish_workout, create_routine (batch), add_routine_exercise, update_routine.
- Endpoints API nuevos: `POST /api/v1/workouts/{workout}/exercises`, `POST /api/v1/workout-exercises/{workoutExercise}/sets`, `PATCH/DELETE /api/v1/routines/{routine}`.
- Acciones MCP equivalentes.
- PRs: tabla `personal_records`, tipos `weight`/`reps`/`one_rm`, timeline en historial.

- [ ] **Step 5: Manual QA (Playwright MCP en :8010)**

Seguir el flujo de AGENTS.md: Login (`test@example.com/password`) → `/fitness/gym` → iniciar workout desde rutina → añadir ejercicio por búsqueda → loguear series (verificar trofeo de PR cuando corresponda) → borrar una serie → borrar un ejercicio → Finish Workout → `/fitness/history` (verificar PR Timeline) → chat: pedir "escribí mi entrenamiento de pecho" y aprobar la acción → verificar en `/fitness/gym`.
Expected: sin errores de consola; todos los pasos funcionan.

- [ ] **Step 6: Commit final**

```bash
git add docs/modules/gym.md
git commit -m "docs(gym): document agent write flows and PR timeline"
```


---

### Task 10 (addendum): `ActionTool` también delega sus acciones de gym

**Ruling del controller (preflight):** `ActionTool::createWorkout` y `ActionTool::logSet` quedaron con los bugs originales (logSet sin `set_number` → SQL NOT NULL; createWorkout ignora `routine_id`). Como el router puede incluir `actions` y el modelo puede elegir `ActionTool` en vez de `GymActionTool`, ambos caminos deben funcionar.

**Files:**
- Modify: `app/Ai/Tools/ActionTool.php`
- Test: `tests/Feature/Ai/MegalomaniacAgentTest.php` (co-locado con los tests existentes de ActionTool)

**Interfaces:**
- `createWorkout` delega en `WorkoutSessionService::start($this->user, routine_id ?? null, started_at ?? null, notes ?? null)`; devuelve `{success, message, workout}` con `exercises.sets` cargados (para que el modelo obtenga `workout_exercise_id`). Agrega `routine_id` al schema.
- `logSet` resuelve el `workoutExercise` scopeado al usuario y delega en `WorkoutSessionService::logSet` (auto `set_number` si no viene); devuelve `{success, message, set}`. Agrega `set_number` al schema.
- No cambiar la firma del constructor (`__construct(protected User $user)`): resolver los servicios con `app(...)` dentro de los métodos (ToolCatalog lo construye con un solo argumento).

- [ ] **Step 1: Tests de regresión**

Agregar en `tests/Feature/Ai/MegalomaniacAgentTest.php`:

```php
test('action tool logs sets with automatic numbering', function () {
    $user = User::factory()->create();
    $workout = Workout::factory()->create(['user_id' => $user->id]);
    $workoutExercise = $workout->exercises()->create(['exercise_id' => \App\Models\Exercise::factory()->create()->id]);

    $tool = new ActionTool($user);
    $result = $tool->handle(new Request([
        'action' => 'log_set',
        'workout_exercise_id' => $workoutExercise->id,
        'weight' => 60,
        'reps' => 8,
        'completed' => true,
    ]));

    $data = json_decode($result, true);

    expect($data['success'])->toBeTrue()
        ->and($data['set']['set_number'])->toBe(1);
});

test('action tool copies the routine template when creating a workout', function () {
    $user = User::factory()->create();
    $routine = \App\Models\Routine::factory()->create(['user_id' => $user->id]);
    $routine->exercises()->attach(\App\Models\Exercise::factory()->create()->id, ['order' => 1, 'target_sets' => 2]);

    $tool = new ActionTool($user);
    $data = json_decode($tool->handle(new Request([
        'action' => 'create_workout',
        'routine_id' => $routine->id,
    ])), true);

    expect($data['success'])->toBeTrue()
        ->and($data['workout']['exercises'])->toHaveCount(1);
});
```

- [ ] **Step 2: RED**
Run: `php artisan test --compact tests/Feature/Ai/MegalomaniacAgentTest.php`
Expected: el primer test falla por `set_number` NOT NULL; el segundo porque no copia la plantilla.

- [ ] **Step 3: Implementación**

En `app/Ai/Tools/ActionTool.php`:
- `createWorkout`: reemplazar el cuerpo por delegación:
```php
    private function createWorkout(Request $request): string
    {
        $workout = app(WorkoutSessionService::class)->start(
            $this->user,
            $request['routine_id'] ?? null,
            $request['started_at'] ?? null,
            $request['notes'] ?? null,
        );

        return json_encode([
            'success' => true,
            'message' => 'Workout created',
            'workout' => $workout->load(['exercises.sets', 'exercises.exercise'])->toArray(),
        ], JSON_PRETTY_PRINT);
    }
```
- `logSet`: resolver el workoutExercise scopeado y delegar (mismo patrón que hoy pero llamando al servicio con `set_number` opcional):
```php
    private function logSet(Request $request): string
    {
        $workoutExercise = WorkoutExercise::whereHas('workout', fn ($q) => $q->where('user_id', $this->user->id))
            ->find($request['workout_exercise_id']);

        if (! $workoutExercise) {
            return json_encode(['success' => false, 'error' => 'Workout exercise not found']);
        }

        $set = app(WorkoutSessionService::class)->logSet($this->user, $workoutExercise, array_filter([
            'set_number' => $request['set_number'] ?? null,
            'weight' => $request['weight'] ?? null,
            'reps' => $request['reps'] ?? null,
            'rpe' => $request['rpe'] ?? null,
            'completed' => $request['completed'] ?? true,
        ], fn ($value) => $value !== null));

        return json_encode([
            'success' => true,
            'message' => 'Set logged',
            'set' => $set->toArray(),
        ], JSON_PRETTY_PRINT);
    }
```
- Agregar `use App\Services\Gym\WorkoutSessionService;`.
- En `schema()`: agregar `'routine_id' => $schema->integer()->description('Routine ID (for create_workout; copies the template)'),` y `'set_number' => $schema->integer()->description('Set number (for log_set; auto-increments when omitted)'),`.

- [ ] **Step 4: GREEN**
Run: `php artisan test --compact tests/Feature/Ai/MegalomaniacAgentTest.php tests/Feature/Ai/ChatApprovalTest.php tests/Feature/Ai/GymActionToolTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add app/Ai/Tools/ActionTool.php tests/Feature/Ai/MegalomaniacAgentTest.php
git commit -m "fix(ai): delegate ActionTool gym actions to the session service"
```
