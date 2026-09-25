# Kanban: columnas configurables, popup de detalle y descripción IA — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Popup de detalle centrado con edición completa y generación de descripción por IA, y columnas Kanban configurables por proyecto en los tableros Personal y Freelance.

**Architecture:** Nueva tabla `task_board_columns` (por proyecto + set default por usuario) y flag desnormalizado `project_tasks.is_done` para que dashboards/progreso dejen de comparar strings de status. El frontend consume columnas por props de Inertia y las gestiona inline; el detalle se edita en un `TaskDetailDialog` compartido que persiste por `fetch` JSON con actualización optimista (sin visitas Inertia). La IA usa un resolver de provider por usuario y un agente dedicado que devuelve bloques Yoopta.

**Tech Stack:** Laravel 12 · Inertia v2 · React 19 · Tailwind v4 · @dnd-kit · laravel/ai ^0.11 · Pest 4 · Wayfinder

**Spec:** `docs/superpowers/specs/2026-09-22-kanban-columnas-detalle-ia-design.md`

## Global Constraints

- PHP 8.4, Laravel 12, Pest 4. Tests: `php artisan test --compact --filter=<name>`.
- Formato PHP: `vendor/bin/pint --dirty --format agent` antes de cerrar cada task.
- Frontend: `npx eslint <file>`, `npx tsc --noEmit` (errores preexistentes en auth/settings se ignoran, no en archivos tocados).
- Convenciones: Eloquent `casts()`, Wayfinder (`@/actions`, `@/routes`), sin `DB::` salvo transacciones (patrón existente en `GroceryController`/`ProjectTaskController`), Form Requests, Tailwind tokens `bg-background`/`bg-card`/`border-border` (Freelance aún usa hex; el componente nuevo usa tokens).
- Status en `project_tasks.status` = `key` de `task_board_columns`. Keys iniciales: Personal `Pending / In Progress / Done`; Freelance `To Do / In Progress / Done`.
- `is_done` se mantiene en 2 puntos: cambio de status de tarea y toggle `is_done` de columna.
- El editor Yoopta NO reacciona a cambios externos de `value` → remount con `key`.
- No commitear salvo pedido explícito.

---

### Task 1: Resolver de provider IA por usuario

**Files:**
- Create: `app/Ai/Support/AiProviderResolver.php`
- Test: `tests/Feature/Ai/AiProviderResolverTest.php`

**Interfaces:**
- Produces: `AiProviderResolver::for(User $user): array{provider: ?string, model: ?string}` — usado por Tasks 2 y 3.

- [ ] **Step 1: Escribir el test**

```php
<?php

use App\Ai\Support\AiProviderResolver;
use App\Models\User;

uses(RefreshDatabase::class);

it('returns user provider when url and key are configured', function () {
    $user = User::factory()->create([
        'ai_enabled' => true,
        'ai_provider_url' => 'https://api.deepseek.com/v1',
        'ai_provider_key' => 'sk-test',
        'ai_model' => 'deepseek-chat',
    ]);

    expect(AiProviderResolver::for($user))->toBe(['user', 'deepseek-chat']);
});

it('falls back to default model when ai_model is empty', function () {
    $user = User::factory()->create([
        'ai_enabled' => true,
        'ai_provider_url' => 'https://api.deepseek.com/v1',
        'ai_provider_key' => 'sk-test',
        'ai_model' => '',
    ]);

    expect(AiProviderResolver::for($user))->toBe(['user', 'gpt-4o-mini']);
});

it('returns nulls when user provider is not configured', function () {
    $user = User::factory()->create(['ai_enabled' => true]);

    expect(AiProviderResolver::for($user))->toBe([null, null]);
});

it('returns nulls when ai is disabled', function () {
    $user = User::factory()->create([
        'ai_enabled' => false,
        'ai_provider_url' => 'https://api.deepseek.com/v1',
        'ai_provider_key' => 'sk-test',
    ]);

    expect(AiProviderResolver::for($user))->toBe([null, null]);
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --compact --filter=AiProviderResolverTest`
Expected: FAIL (class not found)

- [ ] **Step 3: Implementar**

```php
<?php

namespace App\Ai\Support;

use App\Models\User;

class AiProviderResolver
{
    /**
     * @return array{provider: ?string, model: ?string}
     */
    public static function for(User $user): array
    {
        if (! $user->ai_enabled || ! $user->ai_provider_url || ! $user->ai_provider_key) {
            return [null, null];
        }

        return ['user', $user->ai_model ?: 'gpt-4o-mini'];
    }
}
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php artisan test --compact --filter=AiProviderResolverTest`
Expected: 4 passed

- [ ] **Step 5: Pint**

Run: `vendor/bin/pint --dirty --format agent`

---

### Task 2: Aplicar el resolver a los call sites existentes

**Files:**
- Modify: `app/Ai/Services/InsightService.php` (4 métodos)
- Modify: `app/Http/Controllers/AiInsightController.php` (nutrition, generateQuote)
- Modify: `app/Http/Controllers/Ai/AiFitnessController.php` (suggestMeal, generateRoutine)

**Interfaces:**
- Consumes: `AiProviderResolver::for()` (Task 1).
- Produces: todos los prompts existentes usan el provider del usuario cuando está configurado.

- [ ] **Step 1: Reemplazar cada llamada**

Patrón actual:
```php
$response = $agent->forUser($user)->prompt($prompt);
```
Nuevo patrón:
```php
[$provider, $model] = AiProviderResolver::for($user);

$response = $agent->forUser($user)->prompt($prompt, provider: $provider, model: $model);
```
Importar `use App\Ai\Support\AiProviderResolver;` en cada archivo. En `AiFitnessController` el `$user` sale de `$request->user()`; capturarlo en variable si no existe.

- [ ] **Step 2: Verificar suite existente**

Run: `php artisan test --compact --filter=MegalomaniacAgentTest`
Expected: passed

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint --dirty --format agent`

---

### Task 3: Agente, conversor y endpoint de descripción IA

**Files:**
- Create: `app/Ai/Agents/TaskDescriptionAgent.php`
- Create: `app/Ai/Support/MarkdownToYoopta.php`
- Modify: `app/Http/Controllers/AiInsightController.php` (nuevo método `generateTaskDescription`)
- Modify: `routes/web.php`
- Test: `tests/Unit/MarkdownToYooptaTest.php`, `tests/Feature/Ai/GenerateTaskDescriptionTest.php`

**Interfaces:**
- Consumes: `AiProviderResolver::for()`.
- Produces: `POST /ai/generate-task-description` → `{description: array|null, message: string|null}`; `MarkdownToYoopta::convert(string): array`.

- [ ] **Step 1: Test del conversor**

```php
<?php

use App\Ai\Support\MarkdownToYoopta;

it('converts paragraphs to yoopta blocks', function () {
    $blocks = MarkdownToYoopta::convert("Primer párrafo.\n\nSegundo párrafo.");

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['type'])->toBe('paragraph')
        ->and($blocks[0]['children'][0]['text'])->toBe('Primer párrafo.');
});

it('converts headings and bullets', function () {
    $blocks = MarkdownToYoopta::convert("# Título\n- Uno\n- Dos");

    expect($blocks[0]['type'])->toBe('heading')
        ->and($blocks[0]['children'][0]['text'])->toBe('Título')
        ->and($blocks[1]['type'])->toBe('bulleted-list')
        ->and($blocks[1]['children'][0]['text'])->toBe('Uno');
});

it('returns empty array for blank input', function () {
    expect(MarkdownToYoopta::convert("  \n "))->toBe([]);
});
```

- [ ] **Step 2: Correr (debe fallar)**

Run: `php artisan test --compact --filter=MarkdownToYooptaTest`
Expected: FAIL

- [ ] **Step 3: Implementar el conversor**

```php
<?php

namespace App\Ai\Support;

use Illuminate\Support\Str;

class MarkdownToYoopta
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function convert(string $markdown): array
    {
        $blocks = [];
        $lines = preg_split('/\R/', trim($markdown)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            [$type, $text] = match (true) {
                (bool) preg_match('/^#{1,6}\s+(.*)$/', $line, $m) => ['heading', $m[1]],
                (bool) preg_match('/^[-*]\s+(.*)$/', $line, $m) => ['bulleted-list', $m[1]],
                (bool) preg_match('/^\d+[.)]\s+(.*)$/', $line, $m) => ['numbered-list', $m[1]],
                default => ['paragraph', $line],
            };

            $blocks[] = [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'children' => [['text' => $text]],
            ];
        }

        return $blocks;
    }
}
```

- [ ] **Step 4: Correr el conversor**

Run: `php artisan test --compact --filter=MarkdownToYooptaTest`
Expected: 3 passed

- [ ] **Step 5: Crear el agente**

Revisar `app/Ai/Agents/MegalomaniacAgent.php` como referencia de namespace/contratos y crear:

```php
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class TaskDescriptionAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Eres un redactor técnico. Escribes descripciones de tareas claras y accionables en español.

        Reglas:
        - Devuelve SOLO el contenido de la descripción, sin saludos ni explicaciones.
        - Usa Markdown simple: párrafos, títulos con # y listas con -.
        - Máximo 200 palabras.
        - Incluye contexto, alcance y criterios de aceptación si el prompt lo permite.
        PROMPT;
    }
}
```

- [ ] **Step 6: Test del endpoint**

```php
<?php

use App\Ai\Agents\TaskDescriptionAgent;
use App\Models\User;

uses(RefreshDatabase::class);

it('generates a description as yoopta blocks', function () {
    TaskDescriptionAgent::fake(["# Objetivo\nImplementar el login."]);

    $user = User::factory()->create(['ai_enabled' => true]);

    $this->actingAs($user)
        ->postJson('/ai/generate-task-description', [
            'prompt' => 'Login con email y password',
            'title' => 'Implementar login',
        ])
        ->assertOk()
        ->assertJsonPath('description.0.type', 'heading')
        ->assertJsonPath('description.0.children.0.text', 'Objetivo')
        ->assertJsonPath('message', null);
});

it('returns message when ai is disabled', function () {
    $user = User::factory()->create(['ai_enabled' => false]);

    $this->actingAs($user)
        ->postJson('/ai/generate-task-description', ['prompt' => 'algo'])
        ->assertOk()
        ->assertJsonPath('description', null);
});

it('validates prompt', function () {
    $user = User::factory()->create(['ai_enabled' => true]);

    $this->actingAs($user)
        ->postJson('/ai/generate-task-description', [])
        ->assertUnprocessable();
});
```

- [ ] **Step 7: Correr (debe fallar)**

Run: `php artisan test --compact --filter=GenerateTaskDescriptionTest`
Expected: FAIL (404)

- [ ] **Step 8: Implementar el endpoint**

En `routes/web.php` (grupo auth, junto a los otros `/ai`):
```php
Route::post('ai/generate-task-description', [AiInsightController::class, 'generateTaskDescription'])->name('ai.generate-task-description');
```

En `AiInsightController`:
```php
public function generateTaskDescription(Request $request): JsonResponse
{
    $validated = $request->validate([
        'prompt' => 'required|string|max:2000',
        'title' => 'nullable|string|max:255',
        'context' => 'nullable|string|max:255',
    ]);

    $user = $request->user();

    if (! $user->ai_enabled) {
        return response()->json(['description' => null, 'message' => 'AI not configured.']);
    }

    $prompt = "Tarea: {$validated['title']}\n";
    if (! empty($validated['context'])) {
        $prompt .= "Contexto: {$validated['context']}\n";
    }
    $prompt .= "Escribe la descripción con este pedido: {$validated['prompt']}";

    [$provider, $model] = AiProviderResolver::for($user);

    $response = (new TaskDescriptionAgent)->prompt($prompt, provider: $provider, model: $model);

    $blocks = MarkdownToYoopta::convert($response->text);

    if ($blocks === []) {
        return response()->json(['description' => null, 'message' => 'Could not generate a description.']);
    }

    return response()->json(['description' => $blocks, 'message' => null]);
}
```

- [ ] **Step 9: Correr todo**

Run: `php artisan test --compact --filter="GenerateTaskDescriptionTest|MarkdownToYooptaTest|AiProviderResolverTest"`
Expected: 10 passed

- [ ] **Step 10: Wayfinder + Pint**

Run: `php artisan wayfinder:generate && vendor/bin/pint --dirty --format agent`

---

### Task 4: Migraciones (tabla, is_done, normalización + seed)

**Files:**
- Create: `database/migrations/2026_09_22_000001_create_task_board_columns_table.php`
- Create: `database/migrations/2026_09_22_000002_add_is_done_to_project_tasks_table.php`
- Create: `database/migrations/2026_09_22_000003_normalize_task_statuses_and_seed_columns.php`

**Interfaces:**
- Produces: tabla `task_board_columns`, columna `project_tasks.is_done` (indexada), datos normalizados y sembrados.

- [ ] **Step 1: Migración de tabla**

```php
Schema::create('task_board_columns', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
    $table->string('key', 50);
    $table->string('label', 80);
    $table->string('color', 20)->default('slate');
    $table->integer('sort_order')->default(0);
    $table->boolean('is_done')->default(false);
    $table->timestamps();
    $table->unique(['user_id', 'project_id', 'key']);
});
```

- [ ] **Step 2: Migración is_done**

```php
Schema::table('project_tasks', function (Blueprint $table) {
    $table->boolean('is_done')->default(false)->after('is_archived')->index();
});
```

- [ ] **Step 3: Migración de datos**

Para cada proyecto existente: crear 3 columnas (Personal: `Pending/In Progress/Done`; Freelance: `To Do/In Progress/Done`) con `user_id = project.user_id`. Para cada usuario con tareas o proyectos: set default (`project_id = null`) con las mismas 3 columnas. Normalizar `project_tasks.status`:
- `Pending`, `To Do`, `pendiente`, `Todo` → primera key del scope.
- `In Progress`, `in_progress`, `En Progreso`, `en_progreso`, `Review` → segunda key.
- `Done`, `done`, `Completed`, `completed`, `Completada`, `completada` → tercera key.
- Desconocido → primera key.

`is_done = true` para tareas cuya key mapea a la tercera columna (la de done). Implementar con `DB::table` en el `up()` (las migraciones sí pueden usar `DB::`).

- [ ] **Step 4: Correr migraciones**

Run: `php artisan migrate`
Expected: sin errores.

- [ ] **Step 5: Verificar datos**

Run: `php artisan tinker --execute 'echo \App\Models\ProjectTask::where("is_done", true)->count() . "/" . \App\Models\ProjectTask::count();'`
Expected: 9/49 (los `Done` actuales)

---

### Task 5: Modelo, relaciones y servicio

**Files:**
- Create: `app/Models/TaskBoardColumn.php`
- Modify: `app/Models/Project.php` (`boardColumns()`)
- Modify: `app/Models/User.php` (`boardColumns()`)
- Create: `app/Services/TaskBoardColumnService.php`
- Test: `tests/Feature/TaskBoardColumnServiceTest.php`

**Interfaces:**
- Produces: `TaskBoardColumnService::forProject(?Project $project, User $user): Collection`, `::defaultColumnsFor(User): Collection`, `::seedFor(Project): void`, `::propagateIsDone(TaskBoardColumn): void`, `::statusKeys(?Project, User): array`, `::firstStatusKey(?Project, User): string`.

- [ ] **Step 1: Test del servicio**

```php
<?php

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskBoardColumn;
use App\Models\User;
use App\Services\TaskBoardColumnService;

uses(RefreshDatabase::class);

it('creates default columns lazily for a user', function () {
    $user = User::factory()->create();

    $columns = TaskBoardColumnService::defaultColumnsFor($user);

    expect($columns)->toHaveCount(3)
        ->and($columns->pluck('key')->all())->toBe(['Pending', 'In Progress', 'Done'])
        ->and($columns->firstWhere('key', 'Done')->is_done)->toBeTrue();
});

it('seeds personal and freelance projects with their vocabularies', function () {
    $user = User::factory()->create();
    $personal = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);
    $freelance = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance']);

    TaskBoardColumnService::seedFor($personal);
    TaskBoardColumnService::seedFor($freelance);

    expect($personal->boardColumns()->pluck('key')->all())->toBe(['Pending', 'In Progress', 'Done'])
        ->and($freelance->boardColumns()->pluck('key')->all())->toBe(['To Do', 'In Progress', 'Done']);
});

it('propagates is_done to tasks when toggling a column', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);
    TaskBoardColumnService::seedFor($project);

    $task = ProjectTask::factory()->create(['project_id' => $project->id, 'status' => 'Pending', 'is_done' => false]);
    $column = $project->boardColumns()->where('key', 'Pending')->first();

    $column->update(['is_done' => true]);
    TaskBoardColumnService::propagateIsDone($column);

    expect($task->fresh()->is_done)->toBeTrue();
});
```

- [ ] **Step 2: Correr (debe fallar)**

Run: `php artisan test --compact --filter=TaskBoardColumnServiceTest`

- [ ] **Step 3: Implementar modelo + relaciones + servicio**

`TaskBoardColumn`: fillable `user_id, project_id, key, label, color, sort_order, is_done`; casts `is_done => bool`; `project()`, `user()`.

`Project::boardColumns(): HasMany` → `$this->hasMany(TaskBoardColumn::class)->orderBy('sort_order')->orderBy('id')`.
`User::boardColumns(): HasMany` → `$this->hasMany(TaskBoardColumn::class)->orderBy('sort_order')->orderBy('id')`.

Servicio (firma exacta):
```php
class TaskBoardColumnService
{
    public static function defaultColumnsFor(User $user): Collection; // lazy-create project_id=null
    public static function seedFor(Project $project): void;           // no-op si ya tiene columnas
    public static function statusKeys(?Project $project, User $user): array;
    public static function firstStatusKey(?Project $project, User $user): string;
    public static function propagateIsDone(TaskBoardColumn $column): void;
}
```
`propagateIsDone`: `ProjectTask::where('project_id', $column->project_id)->where('status', $column->key)->update(['is_done' => $column->is_done])`; si `project_id` es null, scope por `user_id`.

- [ ] **Step 4: Correr**

Run: `php artisan test --compact --filter=TaskBoardColumnServiceTest`
Expected: 3 passed

---

### Task 6: CRUD de columnas + rutas + requests

**Files:**
- Create: `app/Http/Controllers/TaskBoardColumnController.php`
- Create: `app/Http/Requests/TaskBoardColumn/StoreTaskBoardColumnRequest.php`
- Create: `app/Http/Requests/TaskBoardColumn/UpdateTaskBoardColumnRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TaskBoardColumnTest.php`

**Interfaces:**
- Consumes: `TaskBoardColumnService`.
- Produces: endpoints `POST /task-board-columns`, `PATCH /task-board-columns/{column}`, `PATCH /task-board-columns/reorder`, `DELETE /task-board-columns/{column}?move_to=key`.

- [ ] **Step 1: Tests (auth 403, crear, renombrar, is_done propaga, eliminar exige destino, reorder)**

Casos mínimos:
```php
it('creates a column for the authenticated user', ...);       // assertDatabaseHas task_board_columns
it('forbids editing another user column', ...);               // 403
it('requires move_to when deleting a column with tasks', ...); // 422
it('moves tasks and deletes column when move_to given', ...); // status actualizado
it('reorders columns', ...);                                  // sort_order 0..n
```
`move_to` debe validar que la key destino exista en el mismo scope.

- [ ] **Step 2: Correr (debe fallar)**

Run: `php artisan test --compact --filter=TaskBoardColumnTest`

- [ ] **Step 3: Implementar controller + requests + rutas**

`key` autogenerado: `Str::slug($label, '_')` (ej. "En Revisión" → `en_revision`), único en scope, fallback `column_{n}`.
`reorder` recibe `ordered_ids` (array de ints), valida pertenencia y actualiza `sort_order`.
Rutas dentro del grupo auth de `routes/web.php`:
```php
Route::post('task-board-columns', [TaskBoardColumnController::class, 'store'])->name('task-board-columns.store');
Route::patch('task-board-columns/reorder', [TaskBoardColumnController::class, 'reorder'])->name('task-board-columns.reorder');
Route::patch('task-board-columns/{column}', [TaskBoardColumnController::class, 'update'])->name('task-board-columns.update');
Route::delete('task-board-columns/{column}', [TaskBoardColumnController::class, 'destroy'])->name('task-board-columns.destroy');
```
Todos devuelven `back()` o JSON si `expectsJson()`.

- [ ] **Step 4: Correr + Pint + Wayfinder**

Run: `php artisan test --compact --filter=TaskBoardColumnTest && php artisan wayfinder:generate && vendor/bin/pint --dirty --format agent`

---

### Task 7: Seed automático al crear proyecto

**Files:**
- Modify: `app/Models/Project.php` (observer attribute o `booted()`)
- Create: `app/Observers/ProjectObserver.php`
- Test: `tests/Feature/ProjectColumnSeedTest.php`

- [ ] **Step 1: Test**

```php
it('seeds columns when a project is created', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    expect($project->boardColumns()->count())->toBe(3);
});
```
Cubrir `personal` y `freelance`.

- [ ] **Step 2: Implementar**

`#[ObservedBy([ProjectObserver::class])]` en `Project` (Laravel 12) o `static::created()` en `booted()`. El observer llama `TaskBoardColumnService::seedFor($project)`.
Registrar en `AppServiceProvider` solo si no se usa el atributo.

- [ ] **Step 3: Correr + Pint**

Run: `php artisan test --compact --filter=ProjectColumnSeedTest && vendor/bin/pint --dirty --format agent`

---

### Task 8: Semántica is_done en backend

**Files:**
- Modify: `app/Models/Project.php` (`getProgressAttribute`)
- Modify: `app/Http/Controllers/Freelance/FreelanceDashboardController.php`
- Modify: `app/Ai/Services/SuggestionService.php`
- Modify: `app/Ai/Services/InsightService.php`
- Modify: `app/Models/ProjectTask.php` (`scopePending`, `scopeByStatus`)
- Modify: `app/Mcp/Tools/PersonalTaskWriteTool.php`, `app/Mcp/Tools/FreelanceWriteTool.php`
- Modify: `app/Http/Requests/Api/StoreProjectTaskRequest.php`, `app/Http/Requests/Api/StorePersonalTaskRequest.php` (defaults por scope si aplica)
- Modify: `app/Http/Controllers/Personal/PersonalTaskController.php` (`move`, `store`)
- Modify: `app/Http/Controllers/Freelance/ProjectTaskController.php` (`move`, `store`)
- Test: adaptar `tests/Feature/Personal/PersonalFlowTest.php`, `tests/Feature/Freelance/*`, `tests/Feature/Ai/MegalomaniacAgentTest.php`

**Interfaces:**
- Consumes: `TaskBoardColumnService`.
- Produces: `move()` setea `is_done` según la columna destino y valida que `status` exista; stores usan `firstStatusKey`; queries usan `is_done`.

- [ ] **Step 1: Tests nuevos**

```php
it('rejects a move to a status that is not a column', ...);      // 422
it('sets is_done when moving to a done column', ...);            // assert true
it('excludes done tasks from dashboard pending counts', ...);    // dashboard prop
```
Adaptar asserts existentes que comparan `status` exacto si cambia el vocabulario (no debería: keys iniciales conservan los valores actuales).

- [ ] **Step 2: Implementar reemplazos**

- `getProgressAttribute`: `$done = $this->tasks()->where('is_done', true)->count();`
- Dashboard: `where('is_done', false)`.
- `SuggestionService`: overdue → `where('is_done', false)`.
- `InsightService`: excluir `is_done = true`.
- `scopePending` → `where('is_done', false)`.
- MCP/API stores: default `TaskBoardColumnService::firstStatusKey($project, $user)`.
- `move()`: validar `in_array($status, statusKeys)` (422 si no) y setear `is_done` con la columna destino.

- [ ] **Step 3: Correr suites**

Run: `php artisan test --compact tests/Feature/Personal tests/Feature/Freelance tests/Feature/Ai`
Expected: todo verde.

- [ ] **Step 4: Pint**

Run: `vendor/bin/pint --dirty --format agent`

---

### Task 9: Columnas por props a las páginas + tipos TS

**Files:**
- Modify: `app/Http/Controllers/Personal/PersonalTaskController.php` (`index`)
- Modify: `app/Http/Controllers/Freelance/ProjectController.php` (`show`)
- Modify: `resources/js/types/personal.ts`
- Test: `tests/Feature/Personal/PersonalFlowTest.php` (assert Inertia `boardColumns`)

**Interfaces:**
- Produces: prop `boardColumns` (array de `{id,key,label,color,sort_order,is_done,project_id}`) en `personal/tasks/Index` y `freelance/projects/Show`.

- [ ] **Step 1: Implementar**

`index`: `'boardColumns' => TaskBoardColumnService::forProject($projectFilter, $user)->values()`.
`show`: `'boardColumns' => $project->boardColumns()->get()`.
TS:
```ts
export interface BoardColumn {
    id: number;
    key: string;
    label: string;
    color: string;
    sort_order: number;
    is_done: boolean;
    project_id: number | null;
}
```
Corregir `PersonalTask.description` a `YooptaBlock[] | null`.

- [ ] **Step 2: Test + lint**

Run: `php artisan test --compact --filter=PersonalFlowTest && npx tsc --noEmit`

---

### Task 10: `TaskDetailDialog` + update JSON

**Files:**
- Create: `resources/js/components/tasks/TaskDetailDialog.tsx`
- Modify: `app/Http/Controllers/Personal/PersonalTaskController.php` (`update` → JSON si `expectsJson`)
- Modify: `app/Http/Controllers/Freelance/ProjectTaskController.php` (`update` → JSON si `expectsJson`)
- Test: `tests/Feature/Personal/PersonalFlowTest.php` (update JSON)

- [ ] **Step 1: Test backend**

```php
it('updates task via json', function () {
    $task = ProjectTask::factory()->create(['user_id' => $this->user->id]);
    $this->patchJson("/personal/tasks/{$task->id}", ['title' => 'Nuevo título'])->assertOk();
    expect($task->fresh()->title)->toBe('Nuevo título');
});
```
Equivalente freelance.

- [ ] **Step 2: Componente**

Props: `open`, `onOpenChange`, `task`, `columns: BoardColumn[]`, `variant: 'personal'|'freelance'`, `onSaved(task)`, `onDeleted(id)`, `extraFields?: ReactNode`.
Contenido: título (Input), descripción (`YooptaEditor` con `key` de remount), columna (Select de `columns`), prioridad, `due_date`, `start_date`, `estimated_time` (personal), `responsible`/`area`/tags (freelance), slot de propiedades.
Guardado: `fetch(PATCH url, JSON)` + CSRF; en éxito `onSaved({...task, ...data})`; en error mensaje inline. Eliminar con confirm.
Estados: `saving`, `error`, botón disabled; `DialogContent` con `max-h-[90vh] overflow-y-auto sm:max-w-2xl`.

- [ ] **Step 3: Lint**

Run: `npx eslint resources/js/components/tasks/TaskDetailDialog.tsx && npx tsc --noEmit`

---

### Task 11: Popup en ambos boards (reemplaza el Sheet)

**Files:**
- Modify: `resources/js/components/personal/views/TaskKanban.tsx` (click en tarjeta completa, sin conflicto con drag)
- Modify: `resources/js/components/freelance/TaskBoard.tsx` (click en tarjeta)
- Modify: `resources/js/pages/personal/tasks/Index.tsx` (reemplazar `Sheet` por `TaskDetailDialog`)
- Modify: `resources/js/pages/freelance/projects/Show.tsx` (pasar `boardColumns`)

**Interfaces:**
- Consumes: `TaskDetailDialog`, `boardColumns`.

- [ ] **Step 1: Implementar click vs drag**

En ambas tarjetas: `onClick` en el `Card` con guard `draggingRef` (set en `onDragStart`, clear en `requestAnimationFrame` tras `onDragEnd`); controles internos con `onClick={(e) => e.stopPropagation()}` además del `stopPropagation` de pointer/key.

- [ ] **Step 2: Reemplazar el Sheet**

`personal/tasks/Index.tsx`: quitar `Sheet*` y `YooptaEditor` readonly del drawer; usar `TaskDetailDialog` con `variant="personal"` y `TaskProperties` como slot. Actualizar `selectedTask` al guardar (o cerrar).

- [ ] **Step 3: Playwright**

Login → Personal → Kanban → click en tarjeta → popup centrado → editar título → Guardar → sin recarga y título actualizado. Repetir en Freelance.

---

### Task 12: UI de columnas en ambos boards

**Files:**
- Create: `resources/js/components/tasks/ColumnManager.tsx` (menú `⋯`, diálogo eliminar con destino, tile "+ Añadir")
- Modify: `resources/js/components/personal/views/TaskKanban.tsx`
- Modify: `resources/js/components/freelance/TaskBoard.tsx`

**Interfaces:**
- Consumes: endpoints de Task 6 vía Wayfinder (`@/actions/App/Http/Controllers/TaskBoardColumnController`).

- [ ] **Step 1: Implementar**

Grid con `style={{ gridTemplateColumns: \`repeat(${columns.length}, minmax(0, 1fr))\` }}` (o `auto-fit`) para N columnas.
Menú por columna: Renombrar (inline input), Color (paleta: amber, primary, emerald, sky, violet, slate), "Cuenta como completada" (switch), Mover ←/→ (`reorder`), Eliminar (si tiene tareas → diálogo con Select de destino; `move_to`).
Personal "Todos": columnas default ∪ extras presentes en `boardTasks` (extras sin menú).
Tras CRUD: `router.reload({ only: ['boardColumns'] })` o actualización local optimista.

- [ ] **Step 2: Playwright**

Añadir columna "Revisión" → drag de tarjeta a ella → PATCH 200 → renombrar → mover orden → eliminar con destino → tarea reubicada.

---

### Task 13: Sección IA en el popup

**Files:**
- Modify: `resources/js/components/tasks/TaskDetailDialog.tsx`

- [ ] **Step 1: Implementar**

Bloque "Descripción con IA": `Textarea` de prompt + botón "Generar" (spinner). `ai_enabled=false` (prop desde Inertia o estado del error del endpoint) → disabled + link `Settings → IA`.
Al generar: si el editor tiene contenido, `confirm('¿Reemplazar la descripción actual?')`; setear valor y **bump de `key`** del `YooptaEditor`; error inline.

- [ ] **Step 2: Playwright (sin provider)**

Abrir popup → escribir prompt → Generar → debe mostrar el mensaje de "AI not configured" (o el error del provider) sin romper la UI. Con provider configurado (manual del usuario) se verifica el happy path.

---

### Task 14: Limpieza + docs

**Files:**
- Modify: `resources/js/components/freelance/TaskBoard.tsx` (eliminar `STATUS_MAP`/`normalizeStatus` hardcodeados; usar columnas)
- Modify: `resources/js/components/personal/views/TaskKanban.tsx` (idem `columns`/`normalizeStatus`/`toBackendStatus`)
- Modify: `routes/web.php` (`Route::resource('projects.tasks', ...)->except(['create','edit','show'])`)
- Modify: `docs/modules/freelance.md`, `docs/qa/playwright-report-interactive.md`

- [ ] **Step 1: Implementar limpieza**

Fallback para status sin columna: bucket "Sin columna" al final (solo lectura) en vez de normalizar.

- [ ] **Step 2: Docs**

Actualizar pendientes del QA report (kanban drag/columns/detalle) y features del módulo.

- [ ] **Step 3: Verificación**

Run: `php artisan test --compact && npx tsc --noEmit && npx eslint resources/js && npm run build`

---

### Task 15: Verificación final e2e

- [ ] **Step 1: Suite completa + formatos**

Run: `vendor/bin/pint --dirty --format agent && php artisan test --compact && npx tsc --noEmit && npx eslint resources/js`

- [ ] **Step 2: Playwright de los 3 flujos** (`:8010`, `test@example.com/password`)

1. Popup: abrir/editar/guardar sin recarga en Personal y Freelance.
2. Columnas: crear/renombrar/reordenar/eliminar con destino; drag entre columnas custom.
3. IA: UI con error controlado; happy path si hay provider configurado.
4. Regresión: drag-and-drop y quick-move siguen funcionando; 0 errores de consola.

- [ ] **Step 3: Build**

Run: `npm run build`
