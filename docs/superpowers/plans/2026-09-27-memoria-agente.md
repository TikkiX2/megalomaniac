# Memoria del Agente (general + hilo) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memoria explícita del asistente con dos ámbitos (general por usuario + por hilo de chat), escrita por tools del agente y editable en una página `/ai/memory`.

**Architecture:** Tabla única `memories` con `scope` global|thread y FK cascade a `agent_conversations`. Un `MemoryCatalog` concentra hash/dedup/caps/inyección. Tres tools (`RememberMemoryTool`, `ForgetMemoryTool`, `PromoteMemoryTool`) en un grupo `memory` del `ToolCatalog`; en modo auto el grupo se agrega siempre. La inyección al prompt ocurre en `MegalomaniacAgent::instructions()` (general + hilo) y `RuntimeAgent::instructions()` (solo general). UI Inertia/React en `/ai/memory` + chip en el hilo.

**Tech Stack:** Laravel 12, PHP 8.4, Eloquent, Pest 4, Inertia v2, React 19, Tailwind v4 (tokens Ember), Wayfinder, laravel/ai v0.11.

**Spec:** `docs/superpowers/specs/2026-09-27-memoria-agente-design.md`

## Global Constraints

- PHP 8.4 / Laravel 12; usar `casts()`, Form Requests, Eloquent (nunca `DB::` salvo FTS existente); autorización por Policy.
- UUID7 en PKs del dominio chat (`Str::uuid7()` en `booted()`), `$guarded = []` (patrón `Skill`).
- Grok de tests: Pest, `uses(RefreshDatabase::class)` (ya global para `Feature` en `tests/Pest.php`).
- Comandos obligatorios antes de cerrar cada task: `vendor/bin/pint --dirty --format agent` y el test indicado.
- Textos de UI/errores en español (convención del repo).
- Tailwind con tokens del proyecto (`bg-card`, `border-border`, `text-muted-foreground`, `bg-primary`); no hex hardcodeados nuevos.
- Wayfinder: las rutas nuevas se consumen desde `@/routes/...`; regenerar con `php artisan wayfinder:generate`.
- UI: antes de construir la página (Task 6) es obligatorio usar las skills `ui-radar` (referencias reales) y `anti-ui-slop` (gap de estados + finish gate).
- No modificar `config/ai_tools.php`.
- No crear archivos fuera de los listados en cada task.

---

### Task 1: Datos — enum, migración, modelo, factory y relación en ChatThread

**Files:**
- Create: `app/Ai/Enums/MemoryScope.php`
- Create: `app/Ai/Memory/MemoryCatalog.php` (en esta task solo el `hashContent()` estático; la Task 2 completa el resto)
- Create: `database/migrations/<timestamp>_create_memories_table.php` (vía `make:migration`)
- Create: `app/Models/Memory.php`
- Create: `database/factories/MemoryFactory.php`
- Modify: `app/Models/ChatThread.php` (agregar `memories()`)
- Test: `tests/Feature/Ai/MemoryModelTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: `App\Ai\Enums\MemoryScope` (casos `Global`, `Thread`), `App\Models\Memory` (fillable total, casts `scope`/`metadata`), `MemoryFactory` con estados `global()` y `forThread(ChatThread $thread)`, `ChatThread::memories(): HasMany`, `MemoryCatalog::hashContent(string $content): string` (estático).

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/Ai/MemoryModelTest.php`:

```php
<?php

use App\Ai\Enums\MemoryScope;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;

test('a memory gets a uuid, casts scope and belongs to its user and thread', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $global = Memory::factory()->global()->create(['user_id' => $user->id]);
    $threadMemory = Memory::factory()->forThread($thread)->create(['user_id' => $user->id]);

    expect($global->id)->toBeString()->toHaveLength(36)
        ->and($global->scope)->toBe(MemoryScope::Global)
        ->and($global->thread_id)->toBeNull()
        ->and($global->user->is($user))->toBeTrue()
        ->and($threadMemory->scope)->toBe(MemoryScope::Thread)
        ->and($threadMemory->thread->is($thread))->toBeTrue()
        ->and(Memory::query()->forUser($user)->global()->count())->toBe(1)
        ->and(Memory::query()->forUser($user)->forThread($thread)->count())->toBe(1);
});

test('deleting a thread cascades its memories', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $memory = Memory::factory()->forThread($thread)->create(['user_id' => $user->id]);
    $global = Memory::factory()->global()->create(['user_id' => $user->id]);

    $thread->delete();

    expect(Memory::query()->whereKey($memory->id)->exists())->toBeFalse()
        ->and(Memory::query()->whereKey($global->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --compact tests/Feature/Ai/MemoryModelTest.php`
Expected: FAIL — `Class "App\Models\Memory" not found` / tabla `memories` inexistente.

- [ ] **Step 3: Crear el enum**

`app/Ai/Enums/MemoryScope.php`:

```php
<?php

namespace App\Ai\Enums;

enum MemoryScope: string
{
    case Global = 'global';
    case Thread = 'thread';
}
```

- [ ] **Step 4: Crear el catálogo mínimo (solo el hash)**

`app/Ai/Memory/MemoryCatalog.php`:

```php
<?php

namespace App\Ai\Memory;

class MemoryCatalog
{
    public static function hashContent(string $content): string
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($content)));

        return hash('sha256', $normalized ?? $content);
    }
}
```

- [ ] **Step 5: Crear la migración**

Run: `php artisan make:migration create_memories_table --no-interaction`

Reemplazar el contenido del archivo generado en `database/migrations/`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 16);
            $table->uuid('thread_id')->nullable();
            $table->text('content');
            $table->string('source', 16)->default('agent');
            $table->char('content_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('thread_id')->references('id')->on('agent_conversations')->cascadeOnDelete();
            $table->index(['user_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
```

- [ ] **Step 6: Crear el modelo**

`app/Models/Memory.php`:

```php
<?php

namespace App\Models;

use App\Ai\Enums\MemoryScope;
use Database\Factories\MemoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Memory extends Model
{
    /** @use HasFactory<MemoryFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Memory $memory): void {
            if (blank($memory->id)) {
                $memory->id = (string) Str::uuid7();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scope' => MemoryScope::class,
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('user_id', $user->getKey());
    }

    public function scopeGlobal(Builder $query): void
    {
        $query->where('scope', MemoryScope::Global->value)->whereNull('thread_id');
    }

    public function scopeForThread(Builder $query, ChatThread $thread): void
    {
        $query->where('scope', MemoryScope::Thread->value)->where('thread_id', $thread->getKey());
    }
}
```

- [ ] **Step 7: Crear la factory**

`database/factories/MemoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'user_id' => User::factory(),
            'scope' => MemoryScope::Global,
            'thread_id' => null,
            'content' => fake()->sentence(6),
            'source' => 'user',
            'content_hash' => fn (array $attributes): string => MemoryCatalog::hashContent($attributes['content']),
            'metadata' => null,
        ];
    }

    public function global(): static
    {
        return $this->state(fn () => ['scope' => MemoryScope::Global, 'thread_id' => null]);
    }

    public function forThread(ChatThread $thread): static
    {
        return $this->state(fn () => [
            'scope' => MemoryScope::Thread,
            'thread_id' => $thread->getKey(),
        ]);
    }
}
```

Nota: la factory importa `MemoryCatalog` en `Step 7`; el archivo mínimo ya existe desde el Step 4.

- [ ] **Step 8: Agregar la relación en ChatThread**

En `app/Models/ChatThread.php`, después de `messages()`:

```php
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class, 'thread_id');
    }
```

(`Memory` está en el mismo namespace `App\Models`, no requiere import.)

- [ ] **Step 9: Correr el test y verificar que pasa**

Run: `php artisan test --compact tests/Feature/Ai/MemoryModelTest.php`
Expected: PASS (2 tests).

- [ ] **Step 10: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Enums/MemoryScope.php app/Ai/Memory/MemoryCatalog.php database/migrations/*_create_memories_table.php app/Models/Memory.php app/Models/ChatThread.php database/factories/MemoryFactory.php tests/Feature/Ai/MemoryModelTest.php
git commit -m "feat(ai): add memories table, model and thread relation"
```

---

### Task 2: MemoryCatalog — hash, dedup, caps, promote e inyección

**Files:**
- Modify: `app/Ai/Memory/MemoryCatalog.php` (reemplazar el stub mínimo de la Task 1 por la versión completa)
- Test: `tests/Feature/Ai/MemoryCatalogTest.php`

**Interfaces:**
- Consumes: `MemoryScope`, `Memory`, `ChatThread`, `User` (Task 1).
- Produces:
  - `MemoryCatalog::hashContent(string $content): string`
  - `remember(User $user, string $content, MemoryScope $scope, ?ChatThread $thread = null, string $source = 'agent'): Memory`
  - `update(Memory $memory, string $content): Memory`
  - `forget(Memory $memory): void`
  - `promote(Memory $memory): Memory`
  - `candidatesFor(User $user, ?ChatThread $thread, string $query): Collection`
  - `blockFor(User $user, ?ChatThread $thread): ?string`
  - Constantes: `MAX_CONTENT = 500`, `MAX_GLOBAL = 100`, `MAX_THREAD = 50`, `INJECTION_BUDGET = 8000`.

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/Ai/MemoryCatalogTest.php`:

```php
<?php

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

function memoryThread(User $user): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
}

test('remember normalizes content and deduplicates by hash', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    $first = $catalog->remember($user, '  Prefiere   entrenar a la mañana ', MemoryScope::Global);
    $second = $catalog->remember($user, 'prefiere entrenar a la mañana', MemoryScope::Global);

    expect($second->id)->toBe($first->id)
        ->and($second->content)->toBe('prefiere entrenar a la mañana')
        ->and(Memory::query()->forUser($user)->count())->toBe(1)
        ->and($first->content_hash)->toBe(MemoryCatalog::hashContent('prefiere entrenar a la mañana'));
});

test('remember enforces the global and thread caps', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    Memory::factory()->count(MemoryCatalog::MAX_GLOBAL)->global()->create(['user_id' => $user->id]);

    expect(fn () => $catalog->remember($user, 'Una más', MemoryScope::Global))
        ->toThrow(ValidationException::class);

    $thread = memoryThread($user);
    Memory::factory()->count(MemoryCatalog::MAX_THREAD)->forThread($thread)->create(['user_id' => $user->id]);

    expect(fn () => $catalog->remember($user, 'Otra', MemoryScope::Thread, $thread))
        ->toThrow(ValidationException::class);
});

test('remember requires a thread for thread scope and rejects empty content', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    expect(fn () => $catalog->remember($user, 'x', MemoryScope::Thread))
        ->toThrow(ValidationException::class);

    expect(fn () => $catalog->remember($user, '   ', MemoryScope::Global))
        ->toThrow(ValidationException::class);
});

test('update rejects content that duplicates another memory in the same scope', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    $first = $catalog->remember($user, 'Primera', MemoryScope::Global);
    $second = $catalog->remember($user, 'Segunda', MemoryScope::Global);

    expect(fn () => $catalog->update($first, 'segunda'))
        ->toThrow(ValidationException::class);

    $catalog->update($first, 'Primera editada');

    expect($first->refresh()->content)->toBe('Primera editada')
        ->and($first->content_hash)->toBe(MemoryCatalog::hashContent('Primera editada'));
});

test('promote moves a thread memory to global with metadata', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $thread = memoryThread($user);

    $memory = $catalog->remember($user, 'El proyecto es X', MemoryScope::Thread, $thread);
    $catalog->promote($memory);

    $memory->refresh();

    expect($memory->scope)->toBe(MemoryScope::Global)
        ->and($memory->thread_id)->toBeNull()
        ->and($memory->metadata['promoted_at'] ?? null)->not->toBeNull();
});

test('candidatesFor searches visible memories by normalized content', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $thread = memoryThread($user);

    $global = $catalog->remember($user, 'Prefiere reportes cortos', MemoryScope::Global);
    $catalog->remember($user, 'Detalle del hilo: PR de press banca', MemoryScope::Thread, $thread);
    $catalog->remember($user, 'Otra memoria', MemoryScope::Thread, memoryThread($user));

    $matches = $catalog->candidatesFor($user, $thread, 'PR de press');

    expect($matches)->toHaveCount(1)
        ->and($matches->first()->id)->not->toBe($global->id);

    expect($catalog->candidatesFor($user, $thread, 'prefiere'))->toHaveCount(1)
        ->and($catalog->candidatesFor($user, $thread, 'inexistente'))->toHaveCount(0);
});

test('blockFor orders by recency and respects the injection budget', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    $oldest = $catalog->remember($user, str_repeat('a', 400), MemoryScope::Global);

    for ($i = 0; $i < 30; $i++) {
        $catalog->remember($user, str_repeat('b', 400).$i, MemoryScope::Global);
    }

    $newest = Memory::query()->forUser($user)->orderByDesc('updated_at')->orderByDesc('id')->first();

    $block = $catalog->blockFor($user, null);

    expect($block)->toContain('Memoria general del usuario')
        ->and($block)->toContain($newest->id)
        ->and($block)->toContain('memorias no mostradas')
        ->and($block)->not->toContain($oldest->id);
});

test('blockFor renders global and thread sections with ids', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $thread = memoryThread($user);

    $global = $catalog->remember($user, 'Dato general', MemoryScope::Global);
    $threadMemory = $catalog->remember($user, 'Dato del hilo', MemoryScope::Thread, $thread);

    $block = $catalog->blockFor($user, $thread);

    expect($block)->toContain('Memoria general del usuario')
        ->and($block)->toContain($global->id)
        ->and($block)->toContain('Memoria de este hilo')
        ->and($block)->toContain($threadMemory->id)
        ->and($catalog->blockFor(User::factory()->create(), null))->toBeNull();
});
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --compact tests/Feature/Ai/MemoryCatalogTest.php`
Expected: FAIL — `Class "App\Ai\Memory\MemoryCatalog" not found` (o falta el método en el stub mínimo).

- [ ] **Step 3: Completar el catálogo (reemplazar el stub)**

`app/Ai/Memory/MemoryCatalog.php` (versión completa):

```php
<?php

namespace App\Ai\Memory;

use App\Ai\Enums\MemoryScope;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MemoryCatalog
{
    public const MAX_CONTENT = 500;

    public const MAX_GLOBAL = 100;

    public const MAX_THREAD = 50;

    public const INJECTION_BUDGET = 8000;

    public static function hashContent(string $content): string
    {
        $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($content)));

        return hash('sha256', $normalized ?? $content);
    }

    public function remember(User $user, string $content, MemoryScope $scope, ?ChatThread $thread = null, string $source = 'agent'): Memory
    {
        $content = $this->validateContent($content);

        if ($scope === MemoryScope::Thread && $thread === null) {
            throw ValidationException::withMessages(['scope' => 'La memoria del hilo requiere un hilo.']);
        }

        $existing = $this->queryFor($user, $scope, $thread)
            ->where('content_hash', self::hashContent($content))
            ->first();

        if ($existing !== null) {
            $existing->forceFill(['content' => $content, 'source' => $source])->save();

            return $existing;
        }

        $max = $scope === MemoryScope::Global ? self::MAX_GLOBAL : self::MAX_THREAD;

        if ($this->queryFor($user, $scope, $thread)->count() >= $max) {
            throw ValidationException::withMessages([
                'content' => 'Límite de '.$max.' memorias alcanzado. Olvidá algunas antes de guardar más.',
            ]);
        }

        return Memory::create([
            'user_id' => $user->getKey(),
            'scope' => $scope,
            'thread_id' => $scope === MemoryScope::Thread ? $thread?->getKey() : null,
            'content' => $content,
            'source' => $source,
            'content_hash' => self::hashContent($content),
            'metadata' => null,
        ]);
    }

    public function update(Memory $memory, string $content): Memory
    {
        $content = $this->validateContent($content);
        $hash = self::hashContent($content);

        $duplicate = Memory::query()
            ->where('user_id', $memory->user_id)
            ->where('scope', $memory->scope->value)
            ->whereKeyNot($memory->getKey())
            ->where('content_hash', $hash)
            ->where(function (Builder $query) use ($memory): void {
                $memory->thread_id === null
                    ? $query->whereNull('thread_id')
                    : $query->where('thread_id', $memory->thread_id);
            })
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['content' => 'Ya existe una memoria igual en este ámbito.']);
        }

        $memory->forceFill(['content' => $content, 'content_hash' => $hash])->save();

        return $memory;
    }

    public function forget(Memory $memory): void
    {
        $memory->delete();
    }

    public function promote(Memory $memory): Memory
    {
        if ($memory->scope !== MemoryScope::Thread) {
            return $memory;
        }

        $globalCount = Memory::query()
            ->where('user_id', $memory->user_id)
            ->where('scope', MemoryScope::Global->value)
            ->count();

        if ($globalCount >= self::MAX_GLOBAL) {
            throw ValidationException::withMessages([
                'content' => 'Límite de '.self::MAX_GLOBAL.' memorias generales alcanzado.',
            ]);
        }

        $memory->forceFill([
            'scope' => MemoryScope::Global,
            'thread_id' => null,
            'metadata' => array_merge((array) $memory->metadata, ['promoted_at' => now()->toIso8601String()]),
        ])->save();

        return $memory;
    }

    /**
     * @return Collection<int, Memory>
     */
    public function candidatesFor(User $user, ?ChatThread $thread, string $query): Collection
    {
        $needle = mb_strtolower(trim($query));

        if ($needle === '') {
            return collect();
        }

        return Memory::query()
            ->forUser($user)
            ->where(function (Builder $builder) use ($thread): void {
                $builder->where('scope', MemoryScope::Global->value);

                if ($thread !== null) {
                    $builder->orWhere(fn (Builder $query) => $query
                        ->where('scope', MemoryScope::Thread->value)
                        ->where('thread_id', $thread->getKey()));
                }
            })
            ->whereRaw('LOWER(content) LIKE ?', ['%'.addcslashes($needle, '%_\\').'%'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    public function blockFor(User $user, ?ChatThread $thread): ?string
    {
        $budget = self::INJECTION_BUDGET;

        $sections = array_values(array_filter([
            $this->renderSection('Memoria general del usuario', $this->globalMemories($user), $budget),
            $thread === null ? null : $this->renderSection('Memoria de este hilo', $this->threadMemories($user, $thread), $budget),
        ]));

        return $sections === [] ? null : implode("\n\n", $sections);
    }

    /**
     * @return Collection<int, Memory>
     */
    public function globalMemories(User $user): Collection
    {
        return Memory::query()->forUser($user)->global()->orderByDesc('updated_at')->orderByDesc('id')->get();
    }

    /**
     * @return Collection<int, Memory>
     */
    public function threadMemories(User $user, ChatThread $thread): Collection
    {
        return Memory::query()->forUser($user)->forThread($thread)->orderByDesc('updated_at')->orderByDesc('id')->get();
    }

    /**
     * @param  Collection<int, Memory>  $memories
     */
    protected function renderSection(string $header, Collection $memories, int &$budget): ?string
    {
        if ($memories->isEmpty()) {
            return null;
        }

        $lines = [];
        $skipped = 0;

        foreach ($memories as $memory) {
            $line = '- ['.$memory->getKey().'] '.$memory->content;
            $cost = mb_strlen($line) + 1;

            if ($cost > $budget) {
                $skipped++;

                continue;
            }

            $budget -= $cost;
            $lines[] = $line;
        }

        if ($lines === []) {
            return $header.":\n(+".$skipped.' memorias no mostradas)';
        }

        $text = $header.":\n".implode("\n", $lines);

        if ($skipped > 0) {
            $text .= "\n(+".$skipped.' memorias no mostradas)';
        }

        return $text;
    }

    protected function validateContent(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            throw ValidationException::withMessages(['content' => 'La memoria no puede estar vacía.']);
        }

        if (mb_strlen($content) > self::MAX_CONTENT) {
            throw ValidationException::withMessages([
                'content' => 'Máximo '.self::MAX_CONTENT.' caracteres por memoria.',
            ]);
        }

        return $content;
    }

    protected function queryFor(User $user, MemoryScope $scope, ?ChatThread $thread): Builder
    {
        $query = Memory::query()->forUser($user)->where('scope', $scope->value);

        return $scope === MemoryScope::Thread
            ? $query->where('thread_id', $thread?->getKey())
            : $query->whereNull('thread_id');
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php artisan test --compact tests/Feature/Ai/MemoryCatalogTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Correr también el test de la Task 1 (regresión)**

Run: `php artisan test --compact tests/Feature/Ai/MemoryModelTest.php tests/Feature/Ai/MemoryCatalogTest.php`
Expected: PASS.

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Memory/MemoryCatalog.php tests/Feature/Ai/MemoryCatalogTest.php
git commit -m "feat(ai): add memory catalog with dedup, caps and prompt blocks"
```

---

### Task 3: Tools del agente — remember, forget, promote + ToolCatalog

**Files:**
- Create: `app/Ai/Tools/RememberMemoryTool.php`
- Create: `app/Ai/Tools/ForgetMemoryTool.php`
- Create: `app/Ai/Tools/PromoteMemoryTool.php`
- Modify: `app/Ai/Tools/ToolCatalog.php` (grupo `memory`, `toolsFor(..., ?ChatThread $thread = null)`)
- Modify: `app/Ai/Agents/MegalomaniacAgent.php:227-230` (pasar `$this->thread` a `toolsFor`)
- Test: `tests/Feature/Ai/MemoryToolsTest.php`
- Test: `tests/Feature/Ai/MegalomaniacAgentTest.php` (actualizar conteo/orden de tools)

**Interfaces:**
- Consumes: `MemoryCatalog` (Task 2), `Memory`, `MemoryScope`, `ChatThread`.
- Produces:
  - `RememberMemoryTool::__construct(User $user, MemoryCatalog $catalog, ?ChatThread $thread = null)`
  - `ForgetMemoryTool::__construct(User $user, MemoryCatalog $catalog, ?ChatThread $thread = null)`
  - `PromoteMemoryTool::__construct(User $user, MemoryCatalog $catalog)`
  - `ToolCatalog::toolsFor(User $user, array $groups, ?ChatThread $thread = null): array<int, Tool>` y grupo `memory` en `ToolCatalog::groups()`.
  - Los tool names que ve el modelo son los basenames: `RememberMemoryTool`, `ForgetMemoryTool`, `PromoteMemoryTool`.

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/Ai/MemoryToolsTest.php`:

```php
<?php

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Ai\Tools\ForgetMemoryTool;
use App\Ai\Tools\PromoteMemoryTool;
use App\Ai\Tools\RememberMemoryTool;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Laravel\Ai\Tools\Request;

function memoryToolThread(User $user): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
}

test('remember tool saves global memories and reports thread-scope misuse', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $tool = new RememberMemoryTool($user, $catalog);

    $result = json_decode((string) $tool->handle(new Request([
        'content' => 'Prefiere reportes cortos',
        'scope' => 'global',
    ])), true);

    expect($result['success'])->toBeTrue()
        ->and(Memory::query()->forUser($user)->sole()->content)->toBe('Prefiere reportes cortos');

    $error = (string) $tool->handle(new Request([
        'content' => 'Detalle del hilo',
        'scope' => 'thread',
    ]));

    expect($error)->toContain('hilo');
});

test('remember tool returns the cap error as tool text', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    Memory::factory()->count(MemoryCatalog::MAX_GLOBAL)->global()->create(['user_id' => $user->id]);

    $tool = new RememberMemoryTool($user, $catalog);

    $result = (string) $tool->handle(new Request([
        'content' => 'Una más',
        'scope' => 'global',
    ]));

    expect($result)->toContain('Límite');
});

test('forget tool deletes by id and resolves unique queries', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $thread = memoryToolThread($user);

    $byId = $catalog->remember($user, 'Memoria uno', MemoryScope::Global);
    $byQuery = $catalog->remember($user, 'Memoria dos', MemoryScope::Global);

    $tool = new ForgetMemoryTool($user, $catalog, $thread);

    (string) $tool->handle(new Request(['id' => $byId->id]));

    expect(Memory::query()->whereKey($byId->id)->exists())->toBeFalse();

    $result = (string) $tool->handle(new Request(['query' => 'memoria dos']));

    expect($result)->toContain('borrada')
        ->and(Memory::query()->whereKey($byQuery->id)->exists())->toBeFalse();
});

test('forget tool lists candidates when the query is ambiguous', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);

    $catalog->remember($user, 'Proyecto alpha', MemoryScope::Global);
    $catalog->remember($user, 'Proyecto beta', MemoryScope::Global);

    $tool = new ForgetMemoryTool($user, $catalog);

    $result = (string) $tool->handle(new Request(['query' => 'proyecto']));

    expect($result)->toContain('Proyecto alpha')
        ->and($result)->toContain('Proyecto beta')
        ->and(Memory::query()->forUser($user)->count())->toBe(2);
});

test('promote tool moves thread memories to global and rejects globals', function () {
    $user = User::factory()->create();
    $catalog = app(MemoryCatalog::class);
    $thread = memoryToolThread($user);

    $threadMemory = $catalog->remember($user, 'Del hilo', MemoryScope::Thread, $thread);
    $globalMemory = $catalog->remember($user, 'Global', MemoryScope::Global);

    $tool = new PromoteMemoryTool($user, $catalog);

    (string) $tool->handle(new Request(['id' => $threadMemory->id]));

    expect($threadMemory->refresh()->scope)->toBe(MemoryScope::Global);

    $result = (string) $tool->handle(new Request(['id' => $globalMemory->id]));

    expect($result)->toContain('hilo');
});
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --compact tests/Feature/Ai/MemoryToolsTest.php`
Expected: FAIL — tools inexistentes.

- [ ] **Step 3: Implementar `RememberMemoryTool`**

`app/Ai/Tools/RememberMemoryTool.php`:

```php
<?php

namespace App\Ai\Tools;

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RememberMemoryTool implements Tool
{
    public function __construct(
        protected User $user,
        protected MemoryCatalog $catalog,
        protected ?ChatThread $thread = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Guarda un hecho o preferencia en la memoria del usuario (scope "global") o de esta conversación (scope "thread"). Usala cuando el usuario pida recordar algo o cuando aparezca un dato estable que convenga retener. No guardes credenciales, secretos ni datos efímeros.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()
                ->required()
                ->description('La memoria, en una sola idea y en el idioma del usuario (máx. 500 caracteres)'),
            'scope' => $schema->string()
                ->enum(['global', 'thread'])
                ->required()
                ->description('global = preferencias/objetivos/hechos duraderos del usuario; thread = detalles situacionales de esta conversación'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $scope = MemoryScope::tryFrom((string) ($request['scope'] ?? 'global')) ?? MemoryScope::Global;

        try {
            $memory = $this->catalog->remember(
                $this->user,
                (string) ($request['content'] ?? ''),
                $scope,
                $this->thread,
            );
        } catch (ValidationException $exception) {
            return 'No se pudo guardar: '.implode(' ', array_merge(...array_values($exception->errors())));
        }

        return json_encode([
            'success' => true,
            'id' => $memory->id,
            'scope' => $memory->scope->value,
            'message' => 'Memoria guardada.',
        ]);
    }
}
```

- [ ] **Step 4: Implementar `ForgetMemoryTool`**

`app/Ai/Tools/ForgetMemoryTool.php`:

```php
<?php

namespace App\Ai\Tools;

use App\Ai\Memory\MemoryCatalog;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ForgetMemoryTool implements Tool
{
    public function __construct(
        protected User $user,
        protected MemoryCatalog $catalog,
        protected ?ChatThread $thread = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Borra una memoria del usuario por id (preferido) o por texto (query). Si la búsqueda devuelve varias coincidencias, repetí la llamada con el id correcto.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('Id de la memoria a borrar (preferido)'),
            'query' => $schema->string()->description('Texto para encontrar la memoria cuando no tenés el id'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $id = trim((string) ($request['id'] ?? ''));

        if ($id !== '') {
            $memory = Memory::query()->forUser($this->user)->whereKey($id)->first();

            if ($memory === null) {
                return 'No encontré esa memoria.';
            }

            $this->catalog->forget($memory);

            return 'Memoria borrada.';
        }

        $query = trim((string) ($request['query'] ?? ''));

        if ($query === '') {
            return 'Indicá un id o un texto para buscar la memoria.';
        }

        $candidates = $this->catalog->candidatesFor($this->user, $this->thread, $query);

        if ($candidates->isEmpty()) {
            return 'No encontré memorias que coincidan con «'.$query.'».';
        }

        if ($candidates->count() === 1) {
            $memory = $candidates->first();

            $this->catalog->forget($memory);

            return 'Memoria borrada: «'.$memory->content.'».';
        }

        return 'Varias memorias coinciden; repetí con id: '.$candidates
            ->map(fn (Memory $memory): string => $memory->id.' — '.$memory->content)
            ->implode(' | ');
    }
}
```

- [ ] **Step 5: Implementar `PromoteMemoryTool`**

`app/Ai/Tools/PromoteMemoryTool.php`:

```php
<?php

namespace App\Ai\Tools;

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PromoteMemoryTool implements Tool
{
    public function __construct(
        protected User $user,
        protected MemoryCatalog $catalog,
    ) {}

    public function description(): Stringable|string
    {
        return 'Promueve una memoria del hilo a la memoria general del usuario, para que valga en todas las conversaciones. Solo aplica a memorias con scope "thread".';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->required()
                ->description('Id de la memoria del hilo a promover'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $memory = Memory::query()
            ->forUser($this->user)
            ->whereKey(trim((string) ($request['id'] ?? '')))
            ->first();

        if ($memory === null) {
            return 'No encontré esa memoria.';
        }

        if ($memory->scope !== MemoryScope::Thread) {
            return 'Solo se pueden promover memorias de un hilo.';
        }

        try {
            $this->catalog->promote($memory);
        } catch (ValidationException $exception) {
            return 'No se pudo promover: '.implode(' ', array_merge(...array_values($exception->errors())));
        }

        return json_encode(['success' => true, 'message' => 'Memoria promovida a general.']);
    }
}
```

- [ ] **Step 6: Registrar el grupo y el hilo en `ToolCatalog`**

En `app/Ai/Tools/ToolCatalog.php`:

Agregar import: `use App\Ai\Memory\MemoryCatalog;` y `use App\Models\ChatThread;`.

En `groups()`, después del grupo `skills`:

```php
            'memory' => ['label' => 'Memoria', 'tools' => [RememberMemoryTool::class, ForgetMemoryTool::class, PromoteMemoryTool::class]],
```

Cambiar la firma de `toolsFor` y `make`:

```php
    public static function toolsFor(User $user, array $groups, ?ChatThread $thread = null): array
    {
        if (in_array('*', $groups, true)) {
            $groups = self::allGroups();
        }

        $tools = [];

        foreach (self::groups() as $key => $group) {
            if (! in_array($key, $groups, true)) {
                continue;
            }

            foreach ($group['tools'] as $class) {
                $tools[] = self::make($user, $class, $thread);
            }
        }

        return $tools;
    }

    /**
     * @param  class-string<Tool>  $class
     */
    private static function make(User $user, string $class, ?ChatThread $thread = null): Tool
    {
        return match ($class) {
            IntegrationCatalogTool::class => new IntegrationCatalogTool($user),
            IntegrationCallTool::class => new IntegrationCallTool($user, app(IntegrationExecutor::class)),
            ManageAgentsTool::class => new ManageAgentsTool($user, app(AgentDefinitionService::class)),
            LoadSkillTool::class => new LoadSkillTool($user, app(SkillCatalog::class)),
            RememberMemoryTool::class => new RememberMemoryTool($user, app(MemoryCatalog::class), $thread),
            ForgetMemoryTool::class => new ForgetMemoryTool($user, app(MemoryCatalog::class), $thread),
            PromoteMemoryTool::class => new PromoteMemoryTool($user, app(MemoryCatalog::class)),
            default => new $class($user),
        };
    }
```

En `app/Ai/Agents/MegalomaniacAgent.php`, cambiar `tools()`:

```php
        return [
            ...ToolCatalog::toolsFor($this->user, $this->toolGroups, $this->thread),
            new AskUserTool,
        ];
```

- [ ] **Step 7: Actualizar el test de tools del agente**

En `tests/Feature/Ai/MegalomaniacAgentTest.php`, reemplazar el test `megalomaniac agent has correct tools`:

```php
test('megalomaniac agent has correct tools', function () {
    $user = User::factory()->create();
    $agent = new MegalomaniacAgent($user);

    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(16);
    expect($tools[0])->toBeInstanceOf(TaskQueryTool::class);
    expect($tools[1])->toBeInstanceOf(WorkoutQueryTool::class);
    expect($tools[2])->toBeInstanceOf(FinanceQueryTool::class);
    expect($tools[3])->toBeInstanceOf(NutritionQueryTool::class);
    expect($tools[4])->toBeInstanceOf(GroceryQueryTool::class);
    expect($tools[5])->toBeInstanceOf(ActionTool::class);
    expect($tools[6])->toBeInstanceOf(IntegrationCatalogTool::class);
    expect($tools[7])->toBeInstanceOf(IntegrationCallTool::class);
    expect($tools[8])->toBeInstanceOf(ManageAgentsTool::class);
    expect($tools[9])->toBeInstanceOf(LoadSkillTool::class);
    expect($tools[10])->toBeInstanceOf(RememberMemoryTool::class);
    expect($tools[11])->toBeInstanceOf(ForgetMemoryTool::class);
    expect($tools[12])->toBeInstanceOf(PromoteMemoryTool::class);
    expect($tools[13])->toBeInstanceOf(WebSearchTool::class);
    expect($tools[14])->toBeInstanceOf(WebFetchTool::class);
    expect($tools[15])->toBeInstanceOf(AskUserTool::class);
});
```

Agregar los imports de los tres tools al inicio del archivo:

```php
use App\Ai\Tools\ForgetMemoryTool;
use App\Ai\Tools\PromoteMemoryTool;
use App\Ai\Tools\RememberMemoryTool;
```

- [ ] **Step 8: Correr los tests**

Run: `php artisan test --compact tests/Feature/Ai/MemoryToolsTest.php tests/Feature/Ai/MegalomaniacAgentTest.php`
Expected: PASS (5 + el resto de MegalomaniacAgentTest).

- [ ] **Step 9: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Tools/RememberMemoryTool.php app/Ai/Tools/ForgetMemoryTool.php app/Ai/Tools/PromoteMemoryTool.php app/Ai/Tools/ToolCatalog.php app/Ai/Agents/MegalomaniacAgent.php tests/Feature/Ai/MemoryToolsTest.php tests/Feature/Ai/MegalomaniacAgentTest.php
git commit -m "feat(ai): add memory tools and register the memory tool group"
```

---

### Task 4: Disponibilidad en auto + inyección al prompt

**Files:**
- Modify: `app/Ai/Services/ChatService.php:326-340` (`normalizeToolPolicy` agrega `memory` en auto)
- Modify: `app/Ai/Agents/MegalomaniacAgent.php` (`memoryEnabled()` + inyección en `instructions()`)
- Modify: `app/Ai/Agents/RuntimeAgent.php` (bloque general en `instructions()`)
- Test: `tests/Feature/Ai/MemoryInjectionTest.php`
- Test: `tests/Feature/Ai/ChatToolsPolicyTest.php` (actualizar expectativas de auto)

**Interfaces:**
- Consumes: `MemoryCatalog::blockFor()` (Task 2), grupo `memory` (Task 3).
- Produces: en modo auto `policy['groups']` siempre incluye `memory`; `MegalomaniacAgent::instructions()` incluye bloques "Memoria general del usuario" / "Memoria de este hilo" + guía cuando el grupo está activo; `RuntimeAgent::instructions()` incluye el bloque general.

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/Ai/MemoryInjectionTest.php`:

```php
<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Agents\RuntimeAgent;
use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Ai\Tools\RememberMemoryTool;
use App\Models\AgentDefinition;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;

test('megalomaniac agent injects global and thread memories with ids', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $catalog = app(MemoryCatalog::class);
    $global = $catalog->remember($user, 'Prefiere entrenar a la mañana', MemoryScope::Global);
    $threadMemory = $catalog->remember($user, 'Objetivo del hilo: PR de press banca', MemoryScope::Thread, $thread);

    $instructions = (new MegalomaniacAgent($user, ['*'], $thread))->instructions();

    expect($instructions)->toContain('Memoria general del usuario')
        ->and($instructions)->toContain($global->id)
        ->and($instructions)->toContain('Memoria de este hilo')
        ->and($instructions)->toContain($threadMemory->id)
        ->and($instructions)->toContain('nunca guardes credenciales');
});

test('memory is neither injected nor available when the group is disabled', function () {
    $user = User::factory()->create();
    app(MemoryCatalog::class)->remember($user, 'Dato general', MemoryScope::Global);

    $agent = new MegalomaniacAgent($user, ['tasks']);

    expect($agent->instructions())->not->toContain('Memoria general');

    $classes = collect(iterator_to_array($agent->tools()))->map(fn ($tool): string => $tool::class);

    expect($classes)->not->toContain(RememberMemoryTool::class);
});

test('agent without thread only injects global memory', function () {
    $user = User::factory()->create();
    app(MemoryCatalog::class)->remember($user, 'Dato general', MemoryScope::Global);

    $instructions = (new MegalomaniacAgent($user))->instructions();

    expect($instructions)->toContain('Memoria general del usuario')
        ->and($instructions)->not->toContain('Memoria de este hilo');
});

test('runtime agent injects the global memory block', function () {
    $user = User::factory()->create();
    $definition = AgentDefinition::factory()->create(['user_id' => $user->id]);

    app(MemoryCatalog::class)->remember($user, 'El usuario prefiere reportes cortos', MemoryScope::Global);

    $instructions = (new RuntimeAgent($definition))->instructions();

    expect($instructions)->toContain('El usuario prefiere reportes cortos');
});

test('auto tool policy always includes the memory group', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $policy = app(App\Ai\Services\ChatService::class)
        ->prepareToolPolicy($thread, '¿Qué tareas tengo pendientes?');

    expect($policy['mode'])->toBe('auto')
        ->and($policy['groups'])->toContain('memory')
        ->and($policy['groups'])->toContain('tasks');

    $manual = app(App\Ai\Services\ChatService::class)
        ->prepareToolPolicy($thread, '¿Qué tareas tengo pendientes?', ['mode' => 'manual', 'groups' => ['tasks']]);

    expect($manual['groups'])->toBe(['tasks']);
});
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --compact tests/Feature/Ai/MemoryInjectionTest.php`
Expected: FAIL — no hay bloque de memoria ni `memory` en auto.

- [ ] **Step 3: Agregar `memory` al modo auto en `ChatService`**

En `app/Ai/Services/ChatService.php`, `normalizeToolPolicy()` (línea ~339), reemplazar el `return` final:

```php
        $groups = ToolRouter::route($message);

        if (! in_array('memory', $groups, true)) {
            $groups[] = 'memory';
        }

        return ['mode' => 'auto', 'groups' => $groups];
```

- [ ] **Step 4: Inyectar en `MegalomaniacAgent`**

En `app/Ai/Agents/MegalomaniacAgent.php`:

Agregar import: `use App\Ai\Memory\MemoryCatalog;`.

En `instructions()`, después del bloque de skills disponibles (antes de `return $instructions;`):

```php
        if ($this->memoryEnabled()) {
            $memory = app(MemoryCatalog::class)->blockFor($this->user, $this->thread);

            if ($memory !== null) {
                $instructions .= "\n\n".$memory;
            }

            $instructions .= "\n\nSobre tu memoria: guardá hechos y preferencias duraderas del usuario en la memoria general (scope \"global\") y detalles situacionales de esta conversación en la del hilo (scope \"thread\"). Usá entradas cortas de una sola idea, preferí actualizar o borrar antes que duplicar, y nunca guardes credenciales, secretos ni datos de pago.";
        }
```

Agregar el helper al lado de `skillsToolEnabled()`:

```php
    protected function memoryEnabled(): bool
    {
        return in_array('*', $this->toolGroups, true) || in_array('memory', $this->toolGroups, true);
    }
```

- [ ] **Step 5: Inyectar en `RuntimeAgent`**

En `app/Ai/Agents/RuntimeAgent.php`:

Agregar import: `use App\Ai\Memory\MemoryCatalog;`.

Reemplazar `instructions()`:

```php
    public function instructions(): string
    {
        $context = json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $memory = app(MemoryCatalog::class)->blockFor($this->definition->user, null);
        $memorySection = $memory === null ? '' : "\n{$memory}\n";

        return <<<TEXT
        Sos el agente "{$this->definition->name}" del cockpit personal Megalomaniac.
        {$this->definition->instructions}
        {$memorySection}
        Contexto de tus ejecuciones recientes (JSON):
        {$context}

        Reglas:
        - Trabajá solo con los datos reales del usuario (usá las herramientas disponibles).
        - No inventes datos; si falta información, decilo en el informe.
        - Las acciones de escritura quedan pendientes de aprobación del usuario; mencionalo si proponés alguna.
        - Cuando corras como agente programado, respondé SOLO con un objeto JSON válido con esta forma:
          {"report": "informe en markdown", "suggestions": "[{\\"title\\": \\"...\\", \\"content\\": \\"...\\"}]", "notify": "mensaje corto opcional"}
          (hasta 3 sugerencias; "suggestions" y "notify" pueden ser cadenas vacías).
        TEXT;
    }
```

**Ojo con la indentación del heredoc:** `<<<TEXT` con la línea de cierre indentada exige la misma indentación en todas las líneas (PHP 7.3+ flexible heredoc). Mantené exactamente la estructura actual del archivo y solo insertá `{$memorySection}`.

- [ ] **Step 6: Actualizar `ChatToolsPolicyTest`**

En `tests/Feature/Ai/ChatToolsPolicyTest.php`, en el test `routes automatically when no policy is given` cambiar:

```php
    expect($content)->toContain('"mode":"auto"')
        ->and($content)->toContain('"groups":["tasks","memory"]');
```

- [ ] **Step 7: Correr los tests**

Run: `php artisan test --compact tests/Feature/Ai/MemoryInjectionTest.php tests/Feature/Ai/ChatToolsPolicyTest.php tests/Feature/Ai/ToolRouterTest.php`
Expected: PASS.

- [ ] **Step 8: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Services/ChatService.php app/Ai/Agents/MegalomaniacAgent.php app/Ai/Agents/RuntimeAgent.php tests/Feature/Ai/MemoryInjectionTest.php tests/Feature/Ai/ChatToolsPolicyTest.php
git commit -m "feat(ai): inject memory into agent instructions and enable it in auto mode"
```

---

### Task 5: Backend de la página — policy, requests, controller, rutas y conteo en el hilo

**Files:**
- Create: `app/Policies/MemoryPolicy.php`
- Create: `app/Http/Requests/Memories/StoreMemoryRequest.php`
- Create: `app/Http/Requests/Memories/UpdateMemoryRequest.php`
- Create: `app/Http/Controllers/Ai/MemoryController.php`
- Modify: `routes/web.php` (grupo AI, después de `ai.models`)
- Modify: `app/Http/Resources/ChatThreadResource.php` (`memories_count`)
- Modify: `app/Http/Controllers/Ai/ChatController.php:66-75` (`loadCount('memories')`)
- Test: `tests/Feature/Ai/MemoryPageTest.php`
- Test: `tests/Feature/Ai/ChatThreadTest.php` (cascade)

**Interfaces:**
- Consumes: `MemoryCatalog`, `Memory` (Tasks 1-2).
- Produces:
  - Rutas `ai.memory.index|store|update|destroy|promote` en `/ai/memory`.
  - Props Inertia de `ai/memory`: `memories` (id, scope, thread_id, content, source, updated_at), `threads` (id, title), `limits` (max_content, max_global, max_thread), `selected_thread` (?string).
  - `ChatThreadResource` emite `memories_count` cuando viene contado con `withCount`.

- [ ] **Step 1: Escribir el test que falla**

`tests/Feature/Ai/MemoryPageTest.php`:

```php
<?php

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Memory;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the memory page renders with scopes, threads and limits', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    $catalog = app(MemoryCatalog::class);
    $catalog->remember($user, 'Dato general', MemoryScope::Global);
    $catalog->remember($user, 'Dato del hilo', MemoryScope::Thread, $thread);

    $this->actingAs($user)
        ->get(route('ai.memory.index', ['thread' => $thread->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ai/memory')
            ->has('memories', 2)
            ->where('selected_thread', $thread->id)
            ->where('limits.max_content', MemoryCatalog::MAX_CONTENT)
            ->where('limits.max_global', MemoryCatalog::MAX_GLOBAL)
            ->where('limits.max_thread', MemoryCatalog::MAX_THREAD)
            ->has('threads', 1));
});

test('memories can be created, edited, promoted and deleted from the page', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->from(route('ai.memory.index'))
        ->post(route('ai.memory.store'), [
            'content' => 'Prefiere entrenar a la mañana',
            'scope' => 'global',
        ])
        ->assertRedirect(route('ai.memory.index'));

    $global = Memory::query()->forUser($user)->sole();

    expect($global->source)->toBe('user');

    $this->actingAs($user)
        ->from(route('ai.memory.index', ['thread' => $thread->id]))
        ->post(route('ai.memory.store'), [
            'content' => 'El objetivo es un PR de press banca',
            'scope' => 'thread',
            'thread_id' => $thread->id,
        ])
        ->assertRedirect(route('ai.memory.index', ['thread' => $thread->id]));

    $threadMemory = Memory::query()->forUser($user)->forThread($thread)->sole();

    $this->actingAs($user)
        ->from(route('ai.memory.index'))
        ->patch(route('ai.memory.update', $global), ['content' => 'Prefiere entrenar temprano'])
        ->assertRedirect(route('ai.memory.index'));

    expect($global->refresh()->content)->toBe('Prefiere entrenar temprano');

    $this->actingAs($user)
        ->from(route('ai.memory.index'))
        ->post(route('ai.memory.promote', $threadMemory))
        ->assertRedirect(route('ai.memory.index'));

    $threadMemory->refresh();

    expect($threadMemory->scope)->toBe(MemoryScope::Global)
        ->and($threadMemory->thread_id)->toBeNull();

    $this->actingAs($user)
        ->from(route('ai.memory.index'))
        ->delete(route('ai.memory.destroy', $global))
        ->assertRedirect(route('ai.memory.index'));

    expect(Memory::query()->whereKey($global->id)->exists())->toBeFalse();
});

test('memory page validates content, scope and thread ownership', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $intruder->getMorphClass(),
        'participant_id' => $intruder->getKey(),
    ]);

    $this->actingAs($user)
        ->post(route('ai.memory.store'), ['content' => '', 'scope' => 'global'])
        ->assertSessionHasErrors('content');

    $this->actingAs($user)
        ->post(route('ai.memory.store'), [
            'content' => str_repeat('a', MemoryCatalog::MAX_CONTENT + 1),
            'scope' => 'global',
        ])
        ->assertSessionHasErrors('content');

    $this->actingAs($user)
        ->post(route('ai.memory.store'), [
            'content' => 'Dato',
            'scope' => 'thread',
            'thread_id' => $thread->id,
        ])
        ->assertSessionHasErrors('thread_id');
});

test('another user cannot touch memories or read them from the page', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $memory = Memory::factory()->global()->create(['user_id' => $intruder->id]);

    $this->actingAs($user)
        ->get(route('ai.memory.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('memories', 0));

    $this->actingAs($user)
        ->patch(route('ai.memory.update', $memory), ['content' => 'Hack'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('ai.memory.destroy', $memory))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('ai.memory.promote', $memory))
        ->assertForbidden();
});

test('deleting a thread deletes its memories and the show prop counts them', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    Memory::factory()->count(2)->forThread($thread)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('thread.memories_count', 2));

    $this->actingAs($user)
        ->delete(route('ai.chat.destroy', $thread))
        ->assertRedirect(route('ai.chat.index'));

    expect(Memory::query()->where('thread_id', $thread->id)->count())->toBe(0);
});
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --compact tests/Feature/Ai/MemoryPageTest.php`
Expected: FAIL — `Route [ai.memory.index] not defined`.

- [ ] **Step 3: Crear la Policy**

`app/Policies/MemoryPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MemoryPolicy
{
    public function view(User $user, Memory $memory): Response
    {
        return $memory->user_id === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Memory $memory): bool
    {
        return $memory->user_id === $user->getKey();
    }

    public function delete(User $user, Memory $memory): bool
    {
        return $memory->user_id === $user->getKey();
    }
}
```

- [ ] **Step 4: Crear los Form Requests**

`app/Http/Requests/Memories/StoreMemoryRequest.php`:

```php
<?php

namespace App\Http\Requests\Memories;

use App\Ai\Enums\MemoryScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:500'],
            'scope' => ['required', 'string', Rule::enum(MemoryScope::class)],
            'thread_id' => [
                'required_if:scope,thread',
                'nullable',
                'uuid',
                Rule::exists('agent_conversations', 'id')
                    ->where('participant_type', $this->user()->getMorphClass())
                    ->where('participant_id', $this->user()->getKey()),
            ],
        ];
    }
}
```

`app/Http/Requests/Memories/UpdateMemoryRequest.php`:

```php
<?php

namespace App\Http\Requests\Memories;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:500'],
        ];
    }
}
```

- [ ] **Step 5: Crear el controller**

`app/Http/Controllers/Ai/MemoryController.php`:

```php
<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Enums\MemoryScope;
use App\Ai\Memory\MemoryCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Memories\StoreMemoryRequest;
use App\Http\Requests\Memories\UpdateMemoryRequest;
use App\Models\ChatThread;
use App\Models\Memory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemoryController extends Controller
{
    public function __construct(protected MemoryCatalog $catalog) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $threads = ChatThread::query()
            ->forUser($user)
            ->active()
            ->withMessages()
            ->ordered()
            ->get(['id', 'title']);

        $selected = $request->query('thread');
        $selectedThread = is_string($selected) ? $threads->firstWhere('id', $selected)?->id : null;

        return Inertia::render('ai/memory', [
            'memories' => Memory::query()
                ->forUser($user)
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (Memory $memory): array => [
                    'id' => $memory->id,
                    'scope' => $memory->scope->value,
                    'thread_id' => $memory->thread_id,
                    'content' => $memory->content,
                    'source' => $memory->source,
                    'updated_at' => $memory->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'threads' => $threads->map(fn (ChatThread $thread): array => [
                'id' => $thread->id,
                'title' => $thread->title,
            ])->values()->all(),
            'limits' => [
                'max_content' => MemoryCatalog::MAX_CONTENT,
                'max_global' => MemoryCatalog::MAX_GLOBAL,
                'max_thread' => MemoryCatalog::MAX_THREAD,
            ],
            'selected_thread' => $selectedThread,
        ]);
    }

    public function store(StoreMemoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $scope = MemoryScope::from($data['scope']);

        $thread = $scope === MemoryScope::Thread
            ? ChatThread::query()->forUser($request->user())->findOrFail($data['thread_id'])
            : null;

        $this->catalog->remember($request->user(), $data['content'], $scope, $thread, source: 'user');

        return back()->with('success', 'Memoria guardada.');
    }

    public function update(UpdateMemoryRequest $request, Memory $memory): RedirectResponse
    {
        $this->authorize('update', $memory);

        $this->catalog->update($memory, $request->validated()['content']);

        return back()->with('success', 'Memoria actualizada.');
    }

    public function destroy(Request $request, Memory $memory): RedirectResponse
    {
        $this->authorize('delete', $memory);

        $this->catalog->forget($memory);

        return back()->with('success', 'Memoria eliminada.');
    }

    public function promote(Request $request, Memory $memory): RedirectResponse
    {
        $this->authorize('update', $memory);

        $this->catalog->promote($memory);

        return back()->with('success', 'Memoria promovida a general.');
    }
}
```

- [ ] **Step 6: Registrar las rutas**

En `routes/web.php`, dentro del grupo `auth/verified`, después de `Route::get('ai/models', ...)`:

```php
    // AI Memory
    Route::get('ai/memory', [MemoryController::class, 'index'])->name('ai.memory.index');
    Route::post('ai/memory', [MemoryController::class, 'store'])->name('ai.memory.store');
    Route::patch('ai/memory/{memory}', [MemoryController::class, 'update'])->name('ai.memory.update');
    Route::delete('ai/memory/{memory}', [MemoryController::class, 'destroy'])->name('ai.memory.destroy');
    Route::post('ai/memory/{memory}/promote', [MemoryController::class, 'promote'])->name('ai.memory.promote');
```

Agregar el import junto a los demás controllers AI:

```php
use App\Http\Controllers\Ai\MemoryController;
```

- [ ] **Step 7: Exponer `memories_count`**

En `app/Http/Controllers/Ai/ChatController.php`, en `show()`, antes de `Inertia::render`:

```php
        $thread->loadCount('memories');
```

En `app/Http/Resources/ChatThreadResource.php`, agregar al array:

```php
            'memories_count' => $this->whenCounted('memories'),
```

- [ ] **Step 8: Extender el test de borrado de hilo**

En `tests/Feature/Ai/ChatThreadTest.php`, dentro del test `thread can be deleted with its messages`, agregar una memoria y su aserción:

```php
    Memory::factory()->forThread($thread)->create(['user_id' => $user->id]);
```

antes del `$this->actingAs($user)->delete(...)`, y después:

```php
    expect(Memory::query()->where('thread_id', $thread->id)->count())->toBe(0);
```

Agregar el import: `use App\Models\Memory;` (la factory ya setea el scope).

- [ ] **Step 9: Regenerar Wayfinder**

Run: `php artisan wayfinder:generate`
Expected: aparece `resources/js/routes/ai/memory/index.ts` con `index`, `store`, `update`, `destroy`, `promote`.

- [ ] **Step 10: Correr los tests**

Run: `php artisan test --compact tests/Feature/Ai/MemoryPageTest.php tests/Feature/Ai/ChatThreadTest.php`
Expected: PASS.

- [ ] **Step 11: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/MemoryPolicy.php app/Http/Requests/Memories app/Http/Controllers/Ai/MemoryController.php routes/web.php app/Http/Resources/ChatThreadResource.php app/Http/Controllers/Ai/ChatController.php tests/Feature/Ai/MemoryPageTest.php tests/Feature/Ai/ChatThreadTest.php resources/js/routes resources/js/actions resources/js/wayfinder
git commit -m "feat(ai): add memory page backend, policy and thread memory count"
```

---

### Task 6: Página Memoria (UI)

**Files:**
- Create: `resources/js/types/memory.ts`
- Create: `resources/js/pages/ai/memory.tsx`
- Modify: `resources/js/components/app-sidebar.tsx` (ítem "Memoria")

**Interfaces:**
- Consumes: rutas `@/routes/ai/memory` (Task 5), props del controller (Task 5).
- Produces: página Inertia `ai/memory` con segmented General|Por hilo, alta, edición inline, promover, borrado con confirmación, buscador, contadores y empty states.

- [ ] **Step 1: Gate de diseño (obligatorio)**

Antes de escribir el componente:
1. Invocá la skill `ui-radar` y buscá referencias reales de "memory manager / settings CRUD list" (patrones de fila, acciones, segmented control, empty states).
2. Invocá `anti-ui-slop` y definí el design contract + el checklist de estados para esta página: loading, vacío por ámbito, error de validación, límite alcanzado, contenido largo (500 chars), confirmación destructiva, overflow mobile.

Dejá registrado en el commit o PR el resultado del gate.

- [ ] **Step 2: Crear los tipos**

`resources/js/types/memory.ts`:

```ts
export type MemoryScopeName = 'global' | 'thread';

export interface MemoryRow {
    id: string;
    scope: MemoryScopeName;
    thread_id: string | null;
    content: string;
    source: 'agent' | 'user';
    updated_at: string | null;
}

export interface ThreadOption {
    id: string;
    title: string | null;
}

export interface MemoryLimits {
    max_content: number;
    max_global: number;
    max_thread: number;
}
```

- [ ] **Step 3: Crear la página**

`resources/js/pages/ai/memory.tsx`:

```tsx
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { BrainCircuit, CornerUpLeft, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useMemo, useState, type FormEvent } from 'react';
import { destroy, promote, store, update } from '@/routes/ai/memory';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import MainLayout from '@/layouts/main-layout';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import type { MemoryLimits, MemoryRow, MemoryScopeName, ThreadOption } from '@/types/memory';

interface MemoryPageProps {
    memories: MemoryRow[];
    threads: ThreadOption[];
    limits: MemoryLimits;
    selected_thread: string | null;
    flash?: { success?: string | null };
}

function SourceBadge({ source }: { source: MemoryRow['source'] }) {
    return (
        <Badge variant="outline" className="border-border text-[10px] text-muted-foreground">
            {source === 'agent' ? 'Agente' : 'Usuario'}
        </Badge>
    );
}

function MemoryCard({
    memory,
    maxContent,
    onPromote,
    onDelete,
}: {
    memory: MemoryRow;
    maxContent: number;
    onPromote: (memory: MemoryRow) => void;
    onDelete: (memory: MemoryRow) => void;
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ content: memory.content });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.patch(update.url(memory.id), {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    };

    return (
        <Card className="border-border bg-card">
            <CardContent className="space-y-3 p-4">
                {editing ? (
                    <form onSubmit={submit} className="space-y-2">
                        <Textarea
                            autoFocus
                            rows={3}
                            value={form.data.content}
                            maxLength={maxContent}
                            onChange={(event) => form.setData('content', event.target.value)}
                            className="bg-background text-sm"
                            aria-label="Editar memoria"
                        />
                        <div className="flex items-center justify-between">
                            <span className="text-[10px] text-muted-foreground">
                                {form.data.content.length}/{maxContent}
                            </span>
                            <div className="flex items-center gap-2">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        form.setData('content', memory.content);
                                        setEditing(false);
                                    }}
                                >
                                    Cancelar
                                </Button>
                                <Button type="submit" size="sm" disabled={form.processing} className="bg-primary font-bold">
                                    Guardar
                                </Button>
                            </div>
                        </div>
                        <InputError message={form.errors.content} />
                    </form>
                ) : (
                    <>
                        <p className="text-sm whitespace-pre-wrap text-foreground">{memory.content}</p>
                        <div className="flex flex-wrap items-center gap-2 text-[10px] text-muted-foreground">
                            <SourceBadge source={memory.source} />
                            <span>
                                {memory.updated_at ? new Date(memory.updated_at).toLocaleString() : '—'}
                            </span>
                            <div className="ml-auto flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 text-xs"
                                    onClick={() => {
                                        form.setData('content', memory.content);
                                        setEditing(true);
                                    }}
                                >
                                    <Pencil className="mr-1 h-3 w-3" />
                                    Editar
                                </Button>
                                {memory.scope === 'thread' && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 text-xs"
                                        onClick={() => onPromote(memory)}
                                    >
                                        <CornerUpLeft className="mr-1 h-3 w-3" />
                                        Pasar a general
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-7 text-xs text-destructive hover:text-destructive"
                                    onClick={() => onDelete(memory)}
                                >
                                    <Trash2 className="mr-1 h-3 w-3" />
                                    Borrar
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

export default function MemoryPage() {
    const { memories, threads, limits, selected_thread, flash } = usePage<SharedData & MemoryPageProps>().props;

    const [scope, setScope] = useState<MemoryScopeName>(selected_thread ? 'thread' : 'global');
    const [threadId, setThreadId] = useState<string | null>(selected_thread ?? threads[0]?.id ?? null);
    const [search, setSearch] = useState('');
    const [deleteTarget, setDeleteTarget] = useState<MemoryRow | null>(null);

    const createForm = useForm<{ content: string; thread_id: string | null }>({ content: '', thread_id: null });

    const globalRows = useMemo(() => memories.filter((memory) => memory.scope === 'global'), [memories]);
    const threadRows = useMemo(
        () => memories.filter((memory) => memory.scope === 'thread' && memory.thread_id === threadId),
        [memories, threadId],
    );

    const rows = (scope === 'global' ? globalRows : threadRows).filter((memory) =>
        memory.content.toLowerCase().includes(search.trim().toLowerCase()),
    );

    const used = scope === 'global' ? globalRows.length : threadRows.length;
    const max = scope === 'global' ? limits.max_global : limits.max_thread;
    const atLimit = used >= max;

    const submitCreate = (event: FormEvent) => {
        event.preventDefault();

        createForm
            .transform((data) => ({
                ...data,
                scope,
                thread_id: scope === 'thread' ? threadId : null,
            }))
            .post(store.url(), {
                preserveScroll: true,
                onSuccess: () => createForm.reset('content'),
            });
    };

    const confirmDelete = () => {
        if (deleteTarget === null) return;

        router.delete(destroy.url(deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    return (
        <MainLayout>
            <Head title="Memoria" />

            <div className="mx-auto w-full max-w-3xl space-y-6 p-4 sm:p-6">
                <Heading
                    variant="small"
                    title="Memoria"
                    description="Lo que el asistente recuerda: hechos generales que valen en todos los hilos y detalles propios de cada conversación."
                />

                {flash?.success && (
                    <div className="rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                        {flash.success}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-3">
                    <div className="flex rounded-lg border border-border bg-card p-1">
                        {(['global', 'thread'] as const).map((option) => (
                            <button
                                key={option}
                                type="button"
                                onClick={() => setScope(option)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                                    scope === option
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {option === 'global' ? 'General' : 'Por hilo'}
                            </button>
                        ))}
                    </div>

                    {scope === 'thread' && (
                        <Select value={threadId ?? undefined} onValueChange={(value) => setThreadId(value)}>
                            <SelectTrigger className="w-64 border-border bg-card text-sm">
                                <SelectValue placeholder="Elegí un hilo" />
                            </SelectTrigger>
                            <SelectContent className="border-border bg-card">
                                {threads.map((thread) => (
                                    <SelectItem key={thread.id} value={thread.id}>
                                        {thread.title ?? 'Hilo sin título'}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    <span className={cn('text-xs', atLimit ? 'text-destructive' : 'text-muted-foreground')}>
                        {used}/{max}
                    </span>

                    <div className="relative ml-auto">
                        <Search className="absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Buscar…"
                            className="h-8 w-48 border-border bg-card pl-7 text-sm"
                        />
                    </div>
                </div>

                <Card className="border-border bg-card">
                    <CardContent className="p-4">
                        <form onSubmit={submitCreate} className="space-y-2">
                            <Textarea
                                rows={2}
                                value={createForm.data.content}
                                maxLength={limits.max_content}
                                onChange={(event) => createForm.setData('content', event.target.value)}
                                placeholder={
                                    scope === 'global'
                                        ? 'Ej: prefiere entrenar a la mañana'
                                        : 'Ej: el objetivo de este hilo es un PR de press banca'
                                }
                                disabled={atLimit || (scope === 'thread' && threadId === null)}
                                className="bg-background text-sm"
                                aria-label="Nueva memoria"
                            />
                            <div className="flex items-center justify-between">
                                <span className="text-[10px] text-muted-foreground">
                                    {createForm.data.content.length}/{limits.max_content}
                                </span>
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={atLimit || createForm.processing || (scope === 'thread' && threadId === null)}
                                    className="bg-primary font-bold"
                                >
                                    <Plus className="mr-1 h-3.5 w-3.5" />
                                    Guardar memoria
                                </Button>
                            </div>
                            {atLimit && (
                                <p className="text-xs text-destructive">
                                    Límite alcanzado: borrá o promové memorias para guardar más.
                                </p>
                            )}
                            <InputError message={createForm.errors.content} />
                            <InputError message={createForm.errors.thread_id} />
                        </form>
                    </CardContent>
                </Card>

                {scope === 'thread' && threads.length === 0 ? (
                    <Card className="border-border bg-card">
                        <CardContent className="py-8 text-center text-sm text-muted-foreground">
                            Todavía no hay hilos de chat. La memoria por hilo se activa cuando existe una conversación.
                        </CardContent>
                    </Card>
                ) : rows.length === 0 ? (
                    <Card className="border-border bg-card">
                        <CardContent className="flex flex-col items-center gap-2 py-8 text-center text-sm text-muted-foreground">
                            <BrainCircuit className="h-5 w-5" />
                            {search.trim() !== ''
                                ? 'Ninguna memoria coincide con la búsqueda.'
                                : scope === 'global'
                                  ? 'Todavía no hay memoria general. El asistente la va a ir guardando, o agregala vos.'
                                  : 'Este hilo todavía no tiene memoria propia.'}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {rows.map((memory) => (
                            <MemoryCard
                                key={memory.id}
                                memory={memory}
                                maxContent={limits.max_content}
                                onPromote={(row) => router.post(promote.url(row.id), {}, { preserveScroll: true })}
                                onDelete={setDeleteTarget}
                            />
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={deleteTarget !== null} onOpenChange={(open) => !open && setDeleteTarget(null)}>
                <DialogContent className="border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Borrar memoria</DialogTitle>
                        <DialogDescription>
                            {deleteTarget?.content} — esta acción no se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button variant="ghost" onClick={() => setDeleteTarget(null)}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={confirmDelete}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            <Trash2 className="mr-1 h-3.5 w-3.5" />
                            Borrar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
```

Notas de tipos: si `Select`/`onValueChange` exige `string` (no `string | null`), usá `onValueChange={(value) => setThreadId(value)}` como está; `value={threadId ?? undefined}` evita el error de tipo de Radix.

- [ ] **Step 4: Agregar el ítem al sidebar**

En `resources/js/components/app-sidebar.tsx`:

1. Agregar `BrainCircuit` al import de lucide (línea 2).
2. Insertar después del ítem de "Fuentes":

```tsx
                        <SidebarMenuItem>
                            <SidebarMenuButton asChild tooltip="Memoria" isActive={window.location.pathname.startsWith('/ai/memory')}>
                                <Link href="/ai/memory" prefetch>
                                    <BrainCircuit className="h-4 w-4" />
                                    <span>Memoria</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
```

- [ ] **Step 5: Verificar tipos y build**

Run: `npm run types`
Expected: 0 errores.

Run: `npm run build`
Expected: build OK (incluye la generación de Wayfinder).

- [ ] **Step 6: Smoke con el navegador (Playwright MCP)**

Con el server de dev corriendo (`php artisan serve --port=8010` + Vite, usuario `test@example.com/password`):
1. Abrir `/ai/memory` → ver el segmented General | Por hilo.
2. Crear una memoria general → aparece en la lista; recargar → persiste.
3. Editar el contenido inline → se actualiza.
4. Cambiar a "Por hilo", elegir un hilo y crear una memoria del hilo.
5. Promoverla → desaparece de la vista del hilo y aparece en General.
6. Borrar una memoria → desaparece; sin errores en consola.
7. Mobile 390×844: el formulario y las filas no desbordan.

- [ ] **Step 7: Commit**

```bash
npm run format
git add resources/js/types/memory.ts resources/js/pages/ai/memory.tsx resources/js/components/app-sidebar.tsx
git commit -m "feat(ai): add memory management page and sidebar entry"
```

---

### Task 7: Chip de memoria en el hilo y aviso de borrado

**Files:**
- Modify: `resources/js/types/chat.ts` (`memories_count`)
- Modify: `resources/js/pages/ai/thread.tsx` (chip en el header + conteo en el diálogo de borrado)

**Interfaces:**
- Consumes: `thread.memories_count` (Task 5), ruta `@/routes/ai/memory`.
- Produces: chip `Memoria · N` que linkea a `/ai/memory?thread={id}` y aviso de borrado con conteo.

- [ ] **Step 1: Actualizar el tipo**

En `resources/js/types/chat.ts`, agregar a `ChatThread`:

```ts
    memories_count?: number;
```

- [ ] **Step 2: Agregar el chip y el aviso**

En `resources/js/pages/ai/thread.tsx`:

1. Imports: cambiar la primera línea a `import { Head, Link, router } from '@inertiajs/react';`, agregar `BrainCircuit` al import de `lucide-react` (queda `import { BrainCircuit, MoreHorizontal, Pin, PinOff, RotateCcw, Trash2 } from 'lucide-react';`) y agregar la ruta:

```tsx
import { index as memoryIndex } from '@/routes/ai/memory';
```

2. En el header, después del bloque del pin (`{thread.is_pinned && <Pin ... />}`), agregar:

```tsx
                    <Link
                        href={memoryIndex.url({ query: { thread: thread.id } })}
                        className="ml-1 flex shrink-0 items-center gap-1 rounded-md border border-border bg-card px-2 py-0.5 text-[10px] text-muted-foreground hover:text-foreground"
                        title="Ver la memoria de este hilo"
                    >
                        <BrainCircuit className="h-3 w-3" />
                        Memoria
                        {thread.memories_count ? ` · ${thread.memories_count}` : ''}
                    </Link>
```

3. En el diálogo de borrado, reemplazar la `DialogDescription`:

```tsx
                        <DialogDescription>
                            Se eliminarán “{thread.title}” y todos sus mensajes
                            {thread.memories_count
                                ? ` y ${thread.memories_count} ${thread.memories_count === 1 ? 'memoria' : 'memorias'} del hilo`
                                : ''}
                            . No se puede deshacer.
                        </DialogDescription>
```

- [ ] **Step 3: Verificar tipos**

Run: `npm run types`
Expected: 0 errores.

- [ ] **Step 4: Smoke con el navegador (Playwright MCP)**

1. Abrir un hilo con memorias → el chip muestra `Memoria · N`.
2. Click en el chip → abre `/ai/memory?thread=<id>` con el ámbito "Por hilo" preseleccionado y ese hilo elegido.
3. Abrir el diálogo de borrado del hilo → el texto incluye el conteo de memorias.
4. Sin errores de consola.

- [ ] **Step 5: Commit**

```bash
npm run format
git add resources/js/types/chat.ts resources/js/pages/ai/thread.tsx
git commit -m "feat(ai): link thread memory from chat and warn about it on delete"
```

---

### Task 8: Verificación final y QA

**Files:**
- Modify: `docs/qa/playwright-report.md` (agregar la corrida de memoria)

- [ ] **Step 1: Suite de tests afectada**

Run: `php artisan test --compact tests/Feature/Ai tests/Feature/Agents`
Expected: PASS completo.

- [ ] **Step 2: Suite completa (regresión)**

Run: `php artisan test --compact`
Expected: PASS (o solo fallas preexistentes ajenas a esta feature; documentarlas).

- [ ] **Step 3: Formato y tipos**

```bash
vendor/bin/pint --dirty --format agent
npm run types
npm run format:check
```

- [ ] **Step 4: Build de producción**

Run: `npm run build`
Expected: build OK.

- [ ] **Step 5: Crawl QA (convención del repo)**

Seguir el flujo del QA diario (`docs/qa/playwright-report.md`): Landing → Login → Dashboard → Chat IA (chip) → Memoria (CRUD en ambos ámbitos) → Agentes y resto de módulos. Alternativa sin proveedor IA real: el chat no es necesario para la página de memoria; alcanza con crear/editar/promover/borrar memorias a mano. Registrar el resultado en `docs/qa/playwright-report.md`.

- [ ] **Step 6: Commit final**

```bash
git add docs/qa/playwright-report.md
git commit -m "docs(qa): record memory page crawl"
```

---

## Self-Review (hecho)

- **Cobertura del spec:** §1 datos → Task 1; §2 catálogo → Task 2; §3 tools → Task 3; §4 inyección/auto → Task 4; §5 ciclo de vida/policy/validación → Tasks 1, 2, 5; §6 UI+sandbox → Tasks 5, 6, 7; §7 testing → cada task + Task 8.
- **Placeholders:** ninguno; todas las tasks traen código completo y comandos exactos.
- **Consistencia de tipos:** `MemoryScope` (Task 1) usado igual en Tasks 2-5; `MemoryCatalog` API (Task 2) consumida igual en Tasks 3-5; `blockFor` y `hashContent` con la misma firma en todos lados; props de la página (Task 5) coinciden con `MemoryPageProps` (Task 6).
- **Riesgo detectado y resuelto:** el modo auto del `ToolRouter` no incluía `memory`; se resuelve en Task 4 Step 3 y se cubre con test en `MemoryInjectionTest`/`ChatToolsPolicyTest`.
