# Varios modelos y proveedores — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir varios proveedores de IA por usuario con asignación por scope (superficie > módulo > global), fallback con circuit breaker, prompt personalizable en 3 capas y 7 asistentes por módulo.

**Architecture:** Registro de proveedores (`ai_providers`) + asignaciones por scope (`ai_scopes`) + salud (`ai_provider_health`). `AiScopeResolver` devuelve una `AiResolution` (cadena ordenada + bloque de prompt) y `AiRequestExecutor`/`AiStreamFailover` la ejecutan con fallback (eager para `prompt()`, pre-primer-evento para streaming). `AiPromptComposer` arma las capas editables sobre la base de `MegalomaniacAgent`. UI: `Settings → IA` en 3 pestañas + páginas `/ai/{module}`.

**Tech Stack:** Laravel 12, PHP 8.4, laravel/ai v0.11 (openai-compatible gateway), Inertia v2 + React 19, Tailwind v4, Wayfinder, Pest 4.

**Spec:** `docs/superpowers/specs/2026-09-30-multiple-models-interfaces-design.md` (el plan argumenta desde la spec; el executor lee ambos)

## Global Constraints

- PHP 8.4. Casts en método `casts()`; sin `DB::`; validación en Form Requests; rutas/acciones frontend vía Wayfinder (`@/actions`, `@/routes`); tokens Tailwind `bg-background`/`bg-card`/`border-border` — **cero hex nuevos hardcodeados**.
- Valores exactos de scope (enum `AiScope`): `global`, `surface:chat`, `surface:agents`, `surface:insights`, `surface:feed`, `surface:embeddings`, `module:gym`, `module:nutrition`, `module:grocery`, `module:finance`, `module:freelance`, `module:health`, `module:people`.
- Salud: solo se marca caído con `consecutive_failures >= 3` → `broken_until = now + min(2^consecutive_failures, 1440)` minutos; un éxito pone `consecutive_failures = 0` y limpia `broken_until`.
- Prompt: máximo **8.000 caracteres por capa**; separadores `## Personalización global` y `## Personalización: {Etiqueta}` en orden global → módulo → superficie; capas vacías se omiten.
- Fallback: solo **antes del primer evento entregado al consumidor** (flag `StreamableAgentResponse::hasYielded()`); error mid-stream no reintenta. Errores fallbackables: HTTP 400, 401, 402, 403, 408, 429, 5xx, timeout, conexión. El resto (p. ej. 404, 418) se superpone sin consumir la cadena.
- `users.ai_*` (BYO) se conservan como legacy — **no se dropean**; `users.ai_enabled` sigue siendo el switch maestro. El legacy `App\Ai\Support\AiProviderResolver` se elimina al final de la Task 9.
- Copy existente que no cambia (los tests lo fijan): `'Configura tu proveedor de IA en Settings → IA.'` (422), mensajes de `ChatController::errorMessageFor` (402 "saldo o cuota", 429 "limitando", 401/403 "API key", 404 "endpoint o modelo", 400 "rechaz", default "La generación se interrumpió").
- Todo cambio lleva test. Comandos: `php artisan test --compact --filter=NombreTest`, `vendor/bin/pint --dirty --format agent` tras tocar PHP, `npm run types` tras tocar TS, `npm run build` al final de tareas frontend.
- Commits: uno por task, mensaje convencional en inglés (`feat(ai): …`). Sigue el estilo de la rama: `git commit` directo en `main`.

## Review Focus

1. **Cadena por scope sin merge**: un usuario con solo `global` usa esa cadena en chats de módulo; una cadena de `module:*` reemplaza por completo a la de arriba. Test: `first non-empty chain wins and never merges` (Task 5).
2. **Half-open**: con todos los proveedores caídos, igual sale un request al de `broken_until` más cercano en vez de fallar con "sin proveedor". Test: `falls back to the soonest probe when every provider is broken` (Task 5).
3. **Error mid-stream no reinicia**: si el proveedor falla después de entregar eventos, no se genera una respuesta duplicada del backup. Test: `mid-stream failure does not retry and surfaces the original error` (Task 6/7).
4. **Mapeo de mensajes al envolver la excepción**: el copy exacto de `errorMessageFor` sale del original; `AiAllProvidersFailedException` debe exponerlo como `previous` y con una sola cadena el output es idéntico al de hoy. Test: `single provider chain keeps the exact error copy` (Task 7).
5. **Fidelidad de la migración de datos**: BYO → provider `Principal` con misma URL/modelo + fila `global` con `[id]`; usuario sin BYO → nada. Test: `migrates the BYO provider into the registry` (Task 2).

## File Structure

**Backend nuevo** (`app/Ai/Support/` salvo indicación):

| Archivo | Responsabilidad |
|---|---|
| `app/Ai/Enums/AiProtocol.php` | Enum de protocolo (hoy solo `openai_compatible`) |
| `app/Ai/Enums/AiScope.php` | Los 13 scopes + helpers (`label()`, `moduleKey()`, `fromModuleKey()`) |
| `app/Models/AiProvider.php` | Proveedor por usuario (key cifrada) |
| `app/Models/UserAiScope.php` | Fila por scope (`$table = 'ai_scopes'`; se llama `UserAiScope` para no chocar con el enum) |
| `app/Models/AiProviderHealth.php` | Estado del circuit breaker |
| `app/Ai/Support/ByoProviderMigrator.php` | Migración de datos reutilizable por test |
| `app/Ai/Support/AiPromptComposer.php` | Capas de prompt → bloque compuesto |
| `app/Ai/Support/AiHealthService.php` | Reglas de salud/backoff por proveedor |
| `app/Ai/Support/AiScopeResolver.php` | Jerarquía surface > module > global → `AiResolution` |
| `app/Ai/Support/AiResolution.php` | VO: `chain` + `promptBlock` + `primary()` |
| `app/Ai/Support/AiProviderConfigurator.php` | `wire()` → config `ai.providers.pm{id}` + headers |
| `app/Ai/Support/AiProviderErrors.php` | Clasificación de errores fallbackables |
| `app/Ai/Support/AiAllProvidersFailedException.php` | Excepción de cadena exhausta (guarda `previous`) |
| `app/Ai/Support/AiRequestExecutor.php` | Fallback eager para `prompt()` |
| `app/Ai/Support/AiStreamFailover.php` | Fallback de streaming vía `retry()` en el catch del controller |

**Frontend nuevo:** `resources/js/pages/ai/module.tsx` (wrapper de la superficie de chat), `resources/js/components/ai/module-ai-button.tsx` (botón en headers de módulo).

**Se modifican (los tasks detallan línea/scope):** `ChatService`, `ChatController`, `MegalomaniacAgent`, `AgentRunner`, `InsightService`, `AiInsightController`, `DigestAgent`, `FeedRanker`, `AiSettingsController`, `UserFactory`, `settings/ai.tsx`, `app-sidebar.tsx`, `thread.tsx`/`chat.tsx`, headers de 7 páginas de módulo.

**Se borran (Task 9):** `app/Ai/Support/AiProviderResolver.php`, `tests/Feature/Ai/AiProviderResolverTest.php`.

---
### Task 1: Schema — migraciones, enums, modelos y factories

**Files:**
- Create: `database/migrations/2026_09_30_000001_create_ai_providers_table.php`
- Create: `database/migrations/2026_09_30_000002_create_ai_scopes_table.php`
- Create: `database/migrations/2026_09_30_000003_create_ai_provider_health_table.php`
- Create: `database/migrations/2026_09_30_000004_add_module_to_agent_conversations_table.php`
- Create: `app/Ai/Enums/AiProtocol.php`, `app/Ai/Enums/AiScope.php`
- Create: `app/Models/AiProvider.php`, `app/Models/UserAiScope.php`, `app/Models/AiProviderHealth.php`
- Create: `database/factories/AiProviderFactory.php`, `database/factories/UserAiScopeFactory.php`
- Test: `tests/Feature/Ai/AiProviderModelTest.php`

**Interfaces:**
- Produces (los tasks siguientes dependen de esto):
  - `AiProtocol::OpenAiCompatible` (value `'openai_compatible'`)
  - `AiScope` backed enum string con helpers: `label(): string`, `section(): 'global'|'surface'|'module'`, `moduleKey(): ?string` (`ModuleGym → 'gym'`), `static fromModuleKey(string $key): self` (debe soportar `gym|nutrition|grocery|finance|freelance|health|people`)
  - `AiProvider`: casts `key => encrypted`, `enabled => boolean`; relaciones `health(): HasOne AiProviderHealth`, `scopeAssignments(): HasMany UserAiScope`
  - `UserAiScope`: `protected $table = 'ai_scopes'`; cast `provider_chain => array`; relación `user()`
  - `AiProviderHealth`: cast `broken_until => datetime`, `last_success_at/last_failure_at => datetime`

- [ ] **Step 1: Escribir el test fallido**

```php
// tests/Feature/Ai/AiProviderModelTest.php
test('provider key is encrypted at rest', function () {
    $user = User::factory()->create();
    AiProvider::factory()->for($user)->create(['key' => 'sk-plain']);

    $stored = DB::table('ai_providers')->value('key');
    expect($stored)->not->toBe('sk-plain')
        ->and(decrypt($stored))->toBe('sk-plain');
});

test('provider name is unique per user', function () {
    $user = User::factory()->create();
    AiProvider::factory()->for($user)->create(['name' => 'Principal']);

    AiProvider::factory()->for($user)->create(['name' => 'Principal']);
})->throws(\Illuminate\Database\QueryException::class);

test('scope chain casts to array and scope values are valid', function () {
    $user = User::factory()->create();
    $scope = UserAiScope::factory()->for($user)->create(['scope' => 'surface:chat', 'provider_chain' => [1, 2]]);

    expect($scope->fresh()->provider_chain)->toBe([1, 2])
        ->and(AiScope::tryFrom('module:people'))->not->toBeNull()
        ->and(AiScope::fromModuleKey('health')->value)->toBe('module:health');
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiProviderModelTest`
Expected: FAIL (clase `AiProvider` no existe / tabla no existe)

- [ ] **Step 3: Implementar migraciones + enums + modelos + factories**

Esquema exacto (spec §3.1–3.4; migraciones anónimas estilo `2026_09_25_000004_create_agent_definitions_table.php`):

- `ai_providers`: `id`, `foreignId('user_id')->constrained()->cascadeOnDelete()`, `string('name', 80)`, `string('protocol', 30)->default('openai_compatible')`, `text('url')`, `text('key')`, `string('model', 100)`, `string('embeddings_model', 100)->nullable()`, `boolean('enabled')->default(true)`, `integer('sort_order')->default(0)`, `timestamps`; `unique(['user_id','name'])`, `index(['user_id','enabled','sort_order'])`.
- `ai_scopes`: `id`, `foreignId('user_id')->constrained()->cascadeOnDelete()`, `string('scope', 30)`, `json('provider_chain')->nullable()`, `text('prompt')->nullable()`, `timestamps`; `unique(['user_id','scope'])`.
- `ai_provider_health`: `id`, `foreignId('user_id')->constrained()->cascadeOnDelete()`, `foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete()`, `unsignedInteger('consecutive_failures')->default(0)`, `timestamp('last_success_at')->nullable()`, `timestamp('last_failure_at')->nullable()`, `timestamp('broken_until')->nullable()`, `string('last_error', 255)->nullable()`, `timestamps`; `unique(['user_id','provider_id'])`.
- `agent_conversations` (tabla resuelta con `config('ai.conversations.tables.conversations', 'agent_conversations')`, patrón `AiMigration` como `2026_09_30_120600`): `string('module', 30)->nullable()` + index; `down()` lo dropea.
- `AiScope` enum: 13 casos con labels ES (`'Gimnasio'`, `'Nutrición'`, `'Grocery'`, `'Finanzas'`, `'Freelance'`, `'Salud'`, `'Personas'`, `'Chat'`, `'Agentes'`, `'Insights'`, `'Feed'`, `'Embeddings'`, `'Global'`).
- Factories: `AiProviderFactory` (`name => fake()->unique()->word()`, `url => 'https://api.example.com/v1'`, `key => 'sk-test'`, `model => 'test-model'`, user factory por defecto). `UserAiScopeFactory` (`scope => AiScope::Global->value`, `provider_chain => []`).

- [ ] **Step 4: Correr y verificar que pasa**

Run: `php artisan test --compact --filter=AiProviderModelTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Ai/Enums app/Models database/factories tests/Feature/Ai/AiProviderModelTest.php
git commit -m "feat(ai): provider registry schema with scopes and health tables"
```

---

### Task 2: Migración de datos del BYO + estado de factory

**Files:**
- Create: `app/Ai/Support/ByoProviderMigrator.php`
- Create: `database/migrations/2026_09_30_000005_migrate_byo_provider_into_registry.php`
- Modify: `database/factories/UserFactory.php:64-72` (`withAiProvider`)
- Test: `tests/Feature/Ai/AiProviderMigrationTest.php`

**Interfaces:**
- Consumes: Task 1 (`AiProvider`, `UserAiScope`).
- Produces: `ByoProviderMigrator::migrate(User $user): void` (idempotente); `UserFactory::withAiProvider()` que además **crea un `AiProvider` relacionado** (los ~60 tests existentes que lo usan quedan verdes sin tocarlos).

- [ ] **Step 1: Escribir el test fallido**

```php
// tests/Feature/Ai/AiProviderMigrationTest.php
test('migrates the BYO provider into the registry', function () {
    $user = User::factory()->create([
        'ai_enabled' => true,
        'ai_provider_url' => 'https://byo.example.com/v1',
        'ai_provider_key' => 'sk-byo',
        'ai_model' => 'my-model',
        'ai_embeddings_model' => 'embed-model',
    ]);

    ByoProviderMigrator::migrate($user);

    $provider = AiProvider::query()->where('user_id', $user->id)->sole();
    expect($provider->name)->toBe('Principal')
        ->and($provider->url)->toBe('https://byo.example.com/v1')
        ->and($provider->model)->toBe('my-model')
        ->and($provider->embeddings_model)->toBe('embed-model')
        ->and($provider->key)->toBe('sk-byo');

    $scope = UserAiScope::query()->where('user_id', $user->id)->where('scope', 'global')->sole();
    expect($scope->provider_chain)->toBe([$provider->id]);
});

test('creates nothing for a user without BYO credentials', function () {
    $user = User::factory()->create();
    ByoProviderMigrator::migrate($user);

    expect(AiProvider::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('withAiProvider factory state also creates a registry provider', function () {
    $user = User::factory()->withAiProvider('qa-model')->create();

    $provider = AiProvider::query()->where('user_id', $user->id)->sole();
    expect($provider->model)->toBe('qa-model')
        ->and($provider->url)->toBe('https://api.example.com/v1')
        ->and($user->ai_enabled)->toBeTrue();
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiProviderMigrationTest`
Expected: FAIL (`ByoProviderMigrator` no existe; `AiProvider` no creado por la factory)

- [ ] **Step 3: Implementar `ByoProviderMigrator` y la factory**

- `ByoProviderMigrator::migrate(User $user): void` — si `ai_provider_url` y `ai_provider_key` son null → return; si ya existe un `AiProvider` con `name='Principal'` para ese user → return (idempotente); si no, crea el provider (`model = $user->ai_model ?: 'gpt-4o-mini'`, `embeddings_model = $user->ai_embeddings_model`) y `UserAiScope::updateOrCreate(['user_id' =>…, 'scope' => 'global'], ['provider_chain' => [$provider->id]])`.
- Migración de datos: `User::query()->whereNotNull('ai_provider_url')->whereNotNull('ai_provider_key')->each(fn (User $u) => ByoProviderMigrator::migrate($u))`; `down()` no-op (no se dropea nada legacy).
- `UserFactory::withAiProvider(string $model = 'test-model')`: conserva el state actual de atributos y **encadena `->has(AiProvider::factory()->state([...]))`** con `url => 'https://api.example.com/v1'`, `key => 'sk-test'`, `model => $model` (Laravel setea `user_id` solo vía `has()`).

- [ ] **Step 4: Correr el test nuevo + la suite AI de regresión**

Run: `php artisan test --compact --filter=AiProviderMigrationTest && php artisan test --compact tests/Feature/Ai`
Expected: PASS (los tests existentes de `ChatStreamTest`/`AiProviderResolverTest` siguen verdes: sus usuarios ahora tienen provider en el registry)

- [ ] **Step 5: Commit**

```bash
git add app/Ai/Support/ByoProviderMigrator.php database/migrations database/factories/UserFactory.php tests/Feature/Ai/AiProviderMigrationTest.php
git commit -m "feat(ai): migrate BYO provider into the registry"
```

---
### Task 3: `AiPromptComposer` + capa editable en `MegalomaniacAgent`

**Files:**
- Create: `app/Ai/Support/AiPromptComposer.php`
- Modify: `app/Ai/Agents/MegalomaniacAgent.php` (propiedad `$personalizationBlock`, método `withPersonalization()`, inserción en `instructions()` ~línea 191-207)
- Test: `tests/Feature/Ai/AiPromptComposerTest.php`

**Interfaces:**
- Consumes: Task 1 (`UserAiScope`, `AiScope`).
- Produces (usan Task 7, 8, 10, 13):
  - `AiPromptComposer::personalizationBlock(User $user, ?string $moduleKey, ?string $surface): ?string` — capas `global` → `module:{key}` → `surface:{value}` en orden, con separadores `## Personalización global` / `## Personalización: {Etiqueta}` (label del enum o del módulo), `null` si no hay ninguna.
  - `AiPromptComposer::preview(?string $baseInstructions, ?string $block): string` — `base . ($block ? "\n\n".$block : '')`.
  - `AiPromptComposer::MAX_CHARS = 8000`.
  - `MegalomaniacAgent::withPersonalization(?string $block): static`.
- Etiquetas de módulo (para separadores, ES): `gym => 'Gimnasio'`, `nutrition => 'Nutrición'`, `grocery => 'Grocery'`, `finance => 'Finanzas'`, `freelance => 'Freelance'`, `health => 'Salud'`, `people => 'Personas'` (helper en el enum: `AiScope::fromModuleKey()->label()`).

- [ ] **Step 1: Escribir el test fallido**

```php
// tests/Feature/Ai/AiPromptComposerTest.php
test('composes global, module and surface layers in order', function () {
    $user = User::factory()->create();
    UserAiScope::factory()->for($user)->create(['scope' => 'global', 'prompt' => 'Siempre en español.']);
    UserAiScope::factory()->for($user)->create(['scope' => 'module:gym', 'prompt' => 'Céntrate en hipertrofia.']);
    UserAiScope::factory()->for($user)->create(['scope' => 'surface:chat', 'prompt' => 'Respuestas cortas.']);

    $block = (new AiPromptComposer)->personalizationBlock($user, 'gym', AiScope::SurfaceChat->value);

    expect($block)
        ->toContain('## Personalización global')
        ->toContain('## Personalización: Gimnasio')
        ->toContain('## Personalización: Chat')
        ->toBeLessThan(strpos($block, '## Personalización: Gimnasio') ? 99999 : 0) // orden abajo
        ->and(strpos($block, '## Personalización global'))
        ->toBeLessThan(strpos($block, '## Personalización: Gimnasio'))
        ->toBeLessThan(strpos($block, '## Personalización: Chat'));
});

test('skips empty layers and returns null when no layer exists', function () {
    $user = User::factory()->create();
    $composer = new AiPromptComposer;

    expect($composer->personalizationBlock($user, 'gym', 'surface:chat'))->toBeNull();

    UserAiScope::factory()->for($user)->create(['scope' => 'global', 'prompt' => '   ']);
    expect($composer->personalizationBlock($user, 'gym', 'surface:chat'))->toBeNull();
});

test('personalization lands after the base and before the skills blocks', function () {
    $user = User::factory()->create();
    $agent = (new MegalomaniacAgent($user))->withPersonalization("## Personalización global\n\nHablá en criollo.");

    $instructions = $agent->instructions();

    expect(strpos($instructions, 'Hablá en criollo.'))
        ->toBeLessThan(strpos($instructions, 'Skills disponibles'))
        ->toBeGreaterThan(strpos($instructions, 'sos el asistente personal') === false ? 0 : strpos($instructions, 'sos el asistente personal'));
});
```

Nota: el segundo assert del tercer test ajusta el offset exacto al string de apertura real de `instructions()` (leer el heredoc antes de fijarlo; el punto es: bloque de personalización **después** de la base y **antes** de skills/memoria).

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiPromptComposerTest`
Expected: FAIL (clase `AiPromptComposer` no existe)

- [ ] **Step 3: Implementar composer + `withPersonalization`**

- `personalizationBlock`: consulta las 3 filas de `ai_scopes` de una vez (`whereIn('scope', [...])`), itera `[global, module:{key}, surface:{valor}]` en ese orden exacto, arma `## {heading}\n\n{trim(prompt)}` unidos con `"\n\n"`, devuelve `null` si no quedó nada. La capa de superficie solo si `$surface` no es null; la de módulo solo si `$moduleKey` no es null.
- `MegalomaniacAgent`: propiedad `protected ?string $personalizationBlock = null;`, `withPersonalization(?string $block): static`. En `instructions()`: insertar `"\n\n### Personalización del usuario\n".$this->personalizationBlock` inmediatamente después del heredoc base y **antes** de `writeInstructions()`; si el bloque es null, no agregar nada.
- El preview no vive en el composer como "build completo": el caller arma `preview($agent->instructions(), $block)` — `preview()` solo concatena (la UI en Task 13 lo usa).

- [ ] **Step 4: Correr y verificar que pasa**

Run: `php artisan test --compact --filter=AiPromptComposerTest && php artisan test --compact --filter=MegalomaniacAgentTest`
Expected: PASS (la posición nueva no rompe `MegalomaniacAgentTest`)

- [ ] **Step 5: Commit**

```bash
git add app/Ai/Support/AiPromptComposer.php app/Ai/Agents/MegalomaniacAgent.php tests/Feature/Ai/AiPromptComposerTest.php
git commit -m "feat(ai): composable prompt layers for global, module and surface"
```

---

### Task 4: `AiHealthService` — circuit breaker por proveedor

**Files:**
- Create: `app/Ai/Support/AiHealthService.php`
- Test: `tests/Feature/Ai/AiHealthServiceTest.php`

**Interfaces:**
- Consumes: Task 1 (`AiProviderHealth`, `AiProvider`).
- Produces (usan Task 5, 6, 10):
  - `markSuccess(AiProvider $provider): void` — `consecutive_failures = 0`, `broken_until = null`, `last_success_at = now`, guarda.
  - `recordFailure(AiProvider $provider, string $error): void` — `consecutive_failures++`, `last_failure_at = now`, `last_error = Str::limit($error, 255)`; si `consecutive_failures >= 3` → `broken_until = now()->addMinutes(min(2 ** consecutive_failures, 1440))`.
  - `isBroken(AiProvider $provider): bool` — `broken_until !== null && broken_until->isFuture()`.
  - `statusFor(AiProvider $provider): ?AiProviderHealth` (con caché por request: `array $cache` keyed por `provider_id`).

- [ ] **Step 1: Escribir el test fallido**

```php
// tests/Feature/Ai/AiHealthServiceTest.php
test('three consecutive failures open the circuit', function () {
    $provider = AiProvider::factory()->create();
    $health = new AiHealthService;

    $health->recordFailure($provider, '401');
    $health->recordFailure($provider, '401');
    expect($health->isBroken($provider))->toBeFalse();

    $health->recordFailure($provider, '401');
    expect($health->isBroken($provider))->toBeTrue()
        ->and($health->statusFor($provider)->broken_until->diffInMinutes(now()))->toBeGreaterThanOrEqual(7)
        ->toBeLessThanOrEqual(8); // 2^3 = 8 min
});

test('backoff is capped at 1440 minutes and success resets the circuit', function () {
    $provider = AiProvider::factory()->create();
    $health = new AiHealthService;

    foreach (range(1, 12) as $i) { $health->recordFailure($provider, 'fail '.$i); }
    expect($health->statusFor($provider)->broken_until->diffInMinutes(now()))->toBeLessThanOrEqual(1440);

    $health->markSuccess($provider);
    expect($health->isBroken($provider))->toBeFalse()
        ->and($health->statusFor($provider)->consecutive_failures)->toBe(0);
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiHealthServiceTest`
Expected: FAIL (clase no existe)

- [ ] **Step 3: Implementar `AiHealthService`**

FirstOrCreate por `[user_id, provider_id]` (los dos primeros asserts de reglas ya fijan el comportamiento); caché por request en `private array $cache` para que isBroken/record no dupliquen queries.

- [ ] **Step 4: Correr y verificar que pasa**

Run: `php artisan test --compact --filter=AiHealthServiceTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Ai/Support/AiHealthService.php tests/Feature/Ai/AiHealthServiceTest.php
git commit -m "feat(ai): provider health circuit breaker with exponential backoff"
```

---

### Task 5: `AiScopeResolver` + `AiResolution`

**Files:**
- Create: `app/Ai/Support/AiScopeResolver.php`, `app/Ai/Support/AiResolution.php`
- Test: `tests/Feature/Ai/AiScopeResolverTest.php`

**Interfaces:**
- Consumes: Task 1 (`AiScope`, `AiProvider`, `UserAiScope`), Task 3 (`AiPromptComposer`), Task 4 (`AiHealthService`).
- Produces (usan Tasks 6–10):
  - `AiResolution`:
    - `__construct(public readonly array $chain, public readonly ?string $promptBlock)`
    - `primary(): ?AiProvider` (`$this->chain[0] ?? null`)
    - `isEmpty(): bool`
  - `AiScopeResolver` (resuelto del container): `resolve(User $user, AiScope $surface, ?string $moduleKey = null, ?string $sessionId = null): AiResolution`
    - Cadena de lookup: `surface:{surface}` → (si `$moduleKey`) `module:{key}` → `global` → implícita (`AiProvider` `enabled` por `sort_order`). **Primera fila con `provider_chain` no vacía gana la cadena completa** (sin merge).
    - Filtro de salud: quita `enabled=false` y `isBroken()`; si **todos** quedan fuera → mitad abierta: el de `broken_until` más cercano (los `broken_until` null van primero).
    - `promptBlock = composer->personalizationBlock($user, $moduleKey, $surface->value)`.
    - Caché de filas por request en la instancia (el resolver es singleton por app).

- [ ] **Step 1: Escribir el test fallido**

```php
// tests/Feature/Ai/AiScopeResolverTest.php
test('first non-empty chain wins and never merges', function () {
    $user = User::factory()->create();
    [$a, $b, $c] = AiProvider::factory()->count(3)->for($user)->create();
    UserAiScope::factory()->for($user)->create(['scope' => 'global', 'provider_chain' => [$c->id]]);
    UserAiScope::factory()->for($user)->create(['scope' => 'module:gym', 'provider_chain' => [$a->id, $b->id]]);

    $resolver = app(AiScopeResolver::class);
    $resolution = $resolver->resolve($user, AiScope::SurfaceChat, 'gym');

    expect($resolution->chain->pluck('id')->all())->toBe([$a->id, $b->id]); // sin $c
    expect($resolver->resolve($user, AiScope::SurfaceChat, 'finance')->chain->pluck('id')->all())->toBe([$c->id]);
});

test('surface beats module even when both are set', function () {
    $user = User::factory()->create();
    [$a, $b] = AiProvider::factory()->count(2)->for($user)->create();
    UserAiScope::factory()->for($user)->create(['scope' => 'surface:chat', 'provider_chain' => [$b->id]]);
    UserAiScope::factory()->for($user)->create(['scope' => 'module:gym', 'provider_chain' => [$a->id]]);

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceChat, 'gym');

    expect($resolution->chain->pluck('id')->all())->toBe([$b->id]);
});

test('implicit chain orders enabled providers by sort_order and skips disabled', function () {
    $user = User::factory()->create();
    $second = AiProvider::factory()->for($user)->create(['sort_order' => 2, 'enabled' => true]);
    $first = AiProvider::factory()->for($user)->create(['sort_order' => 1, 'enabled' => true]);
    AiProvider::factory()->for($user)->create(['sort_order' => 0, 'enabled' => false]);

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceAgents);

    expect($resolution->chain->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('falls back to the soonest probe when every provider is broken', function () {
    $user = User::factory()->create();
    $far = AiProvider::factory()->for($user)->create(['sort_order' => 1]);
    $near = AiProvider::factory()->for($user)->create(['sort_order' => 2]);
    $health = new AiHealthService;
    foreach (range(1, 4) as $i) { $health->recordFailure($far, 'x'); }   // broken ~16 min
    foreach (range(1, 3) as $i) { $health->recordFailure($near, 'x'); }  // broken 8 min

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceChat);

    expect($resolution->primary()->id)->toBe($near->id);
});

test('attaches the composed prompt block to the resolution', function () {
    $user = User::factory()->create();
    AiProvider::factory()->for($user)->create();
    UserAiScope::factory()->for($user)->create(['scope' => 'global', 'prompt' => 'Global layer.']);

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceChat);

    expect($resolution->promptBlock)->toContain('## Personalización global');
});
```

Nota: `AiProvider::factory()->count(3)->for($user)->create()` devuelve colección — desempaquetar con `->values()`. Si el destructurado de colecciones incomoda, usar `[$a, $b, $c] = AiProvider::factory()->count(3)->for($user)->create()->all();`.

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiScopeResolverTest`
Expected: FAIL (clases no existen)

- [ ] **Step 3: Implementar `AiResolution` y `AiScopeResolver`**

Como está en el bloque Interfaces. `resolve()`:
1. Filas: `UserAiScope` de `$user->id` con `whereIn('scope', [surface, module, global])` (cacheadas en la instancia).
2. Primera fila con `provider_chain` no vacía → `AiProvider::whereIn('id', chain)->get()` **respetando el orden del array** (`sortBy(fn($p) => array_search($p->id, $chain))`).
3. Sin filas con cadena → implícita: `enabled` por `sort_order`.
4. `filtered = chain->filter(enabled && !isBroken)`; si vacío → half-open: ordenar por `broken_until ?? now (primero)` y quedarse con el primero (si también hay sin broken_until porque solo estaban deshabilitados…: si la cadena original no está vacía, el half-open toma `->sortBy('broken_until')` sobre los no-bloqueados-deshabilitados — ojo: **deshabilitado nunca entra**; si tras filtrar deshabilitados no queda ningún candidato → cadena vacía).
5. `promptBlock` vía composer.

- [ ] **Step 4: Correr y verificar que pasa**

Run: `php artisan test --compact --filter=AiScopeResolverTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Ai/Support/AiScopeResolver.php app/Ai/Support/AiResolution.php tests/Feature/Ai/AiScopeResolverTest.php
git commit -m "feat(ai): scope resolver with hierarchy and health-aware fallback chain"
```

---
### Task 6: Configuración por intento + clasificación de errores + executor eager + failover de streaming

Es el corazón del failover. Dos caminos: **eager** (`prompt()` se ejecuta al llamar → el executor itera la cadena internamente) y **streaming** (laravel/ai es perezoso: el error recién sale al consumir en `streamResponse()` → un objeto de failover decide ahí).

**Files:**
- Create: `app/Ai/Support/AiProviderConfigurator.php`, `app/Ai/Support/AiProviderErrors.php`, `app/Ai/Support/AiAllProvidersFailedException.php`, `app/Ai/Support/AiRequestExecutor.php`, `app/Ai/Support/AiStreamFailover.php`
- Test: `tests/Feature/Ai/AiRequestExecutorTest.php`, `tests/Feature/Ai/AiStreamFailoverTest.php`

**Interfaces:**
- Consumes: Tasks 4–5.
- Produces (usan Tasks 7–10):
  - `AiProviderConfigurator::wire(AiProvider $provider, ?string $sessionId = null): string` — escribe `config(['ai.providers.pm{'.$provider->id.'}.url' => …, '.key' => …, '.headers' => …])` y devuelve la clave `'pm{id}'` (que el gateway openai-compatible de laravel/ai acepta como `provider:`). Headers: `User-Agent: megalomaniac-pro/1.0`; si la URL host es `opencode.ai` o `*.opencode.ai` → `x-opencode-session = $sessionId ?? 'user-{id}'` (lógica que hoy vive en `AiProviderResolver::configureUserProvider` — moverla aquí).
  - `AiProviderErrors::isFallbackable(Throwable $e): bool` — `true` si: `RequestException` con status en `[400,401,402,403,408,429]` o `>= 500`; o instance de `InsufficientCreditsException`/`RateLimitedException`/`ProviderOverloadedException`/`ProviderConnectionException`; o mensaje/exception de timeout/conexión (`Illuminate\Http\Client\ConnectionException`, `GuzzleException`, `Symfony\Component\...ConnectException`). **Todo lo demás → `false`** (404, 418, errores de app).
  - `AiAllProvidersFailedException extends RuntimeException`:
    - `__construct(private array $errorsByProvider, private Throwable $last)` (`$errorsByProvider`: `nome => mensagem`)
    - `lastException(): Throwable`, `errors(): array<string,string>` — y `parent::__construct($last->getMessage(), 0, $last)` para que el original quede como `previous`.
  - `AiRequestExecutor::execute(User $user, AiResolution $resolution, callable $attempt, ?string $sessionId = null): mixed`
    - `$attempt(string $providerKey, string $model, AiProvider $provider): mixed`
    - Itera `$resolution->chain`: `wire()` → `$attempt()`; éxito → `health->markSuccess` + return; catch → `health->recordFailure` + si `!isFallbackable($e)` → **throw $e**; si no hay siguiente → throw `AiAllProvidersFailedException($errors, $e)`. Cadena vacía → `AiAllProvidersFailedException(['' => 'Sin proveedores disponibles.'], new RuntimeException('empty chain'))`.
  - `AiStreamFailover`:
    - `__construct(private AiProvider $current, private AiResolution $resolution, private AiHealthService $health, private AiProviderErrors $errors, private Closure $rebuild)` — `rebuild: fn(array $triedProviderIds): StreamableAgentResponse`
    - `retry(Throwable $e, StreamableAgentResponse $failed): ?StreamableAgentResponse`
    - `lastError(): ?AiAllProvidersFailedException`, `usedFallback(): bool`, `current(): AiProvider`

- [ ] **Step 1: Escribir los tests fallidos**

```php
// tests/Feature/Ai/AiRequestExecutorTest.php
test('tries the next provider when the first fails with a fallbackable error', function () {
    $user = User::factory()->create();
    [$a, $b] = AiProvider::factory()->count(2)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a, $b]), null);
    $tried = [];

    $result = (new AiRequestExecutor)->execute($user, $resolution, function (string $key, string $model, AiProvider $provider) use (&$tried) {
        $tried[] = $provider->id;
        if ($provider->id === $a->id) {
            throw new RequestException(Http::response(['error' => ['message' => 'Unauthorized']], 401));
        }

        return 'ok-from-'.$provider->id;
    });

    expect($tried)->toBe([$a->id, $b->id])
        ->and($result)->toBe('ok-from-'.$b->id)
        ->and((new AiHealthService)->statusFor($a)->consecutive_failures)->toBe(1);
});

test('exhausted chain throws AiAllProvidersFailedException keeping the last error as previous', function () {
    $user = User::factory()->create();
    [$a] = AiProvider::factory()->count(1)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a]), null);

    (new AiRequestExecutor)->execute($user, $resolution, function () {
        throw new RequestException(Http::response(['error' => ['message' => 'Unauthorized']], 401));
    });
})->throws(AiAllProvidersFailedException::class);

test('non-fallbackable errors surface immediately without consuming the chain', function () {
    $user = User::factory()->create();
    [$a, $b] = AiProvider::factory()->count(2)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a, $b]), null);
    $tried = [];

    expect(fn () => (new AiRequestExecutor)->execute($user, $resolution, function ($k, $m, $p) use (&$tried) {
        $tried[] = $p->id;
        throw new RequestException(Http::response(['error' => ['message' => 'nope']], 404));
    }))->toThrow(RequestException::class);

    expect($tried)->toBe([$a->id]); // 404 no consume la cadena
});
```

```php
// tests/Feature/Ai/AiStreamFailoverTest.php
test('retries before any event reached the consumer', function () {
    $user = User::factory()->create();
    [$a, $b] = AiProvider::factory()->count(2)->for($user)->create()->all();
    $resolution = new AiResolution(collect([$a, $b]), null);
    $rebuildTried = null;

    $failover = new AiStreamFailover(
        $a,
        $resolution,
        new AiHealthService,
        app(AiProviderErrors::class),
        function (array $tried) use (&$rebuildTried) { $rebuildTried = $tried; return $this->fakeResponse(yielded: false); },
    );

    $failed = $this->fakeResponse(yielded: false);   // StreamableAgentResponse sin eventos
    $retry = $failover->retry(new RequestException(Http::response([], 429)), $failed);

    expect($retry)->not->toBeNull()
        ->and($rebuildTried)->toBe([$a->id])
        ->and($failover->usedFallback())->toBeTrue();
});

test('mid-stream failure does not retry and surfaces the original error', function () {
    // ... mismo setup pero $failed = fakeResponse(yielded: true)
    expect($failover->retry($exception, $failed))->toBeNull()
        ->and($failover->usedFallback())->toBeFalse();
});

test('exhausted chain records lastError with per-provider detail', function () {
    // cadena de 1, retry devuelve null y lastError() es AiAllProvidersFailedException con previous = la excepción original
});
```

Helper del test: `fakeResponse(bool $yielded)` construye un `StreamableAgentResponse` con `hasYielded` en el estado deseado — para eso agregar un **test seam mínimo**: en vez de mockear la clase, crear la respuesta real e iterarla contra un generator controlado, o (más simple y permitido) exponer en el test `Closure::bind` para setear la propiedad `hasYielded`. Si la segunda vía resulta frágil, alternativa aceptable: constructor de test vía `new StreamableAgentResponse('id', fn () => yield new TextDelta('hola'))` y consumir/ no consumir para fijar el flag.

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php artisan test --compact --filter="AiRequestExecutorTest|AiStreamFailoverTest"`
Expected: FAIL (clases no existen)

- [ ] **Step 3: Implementar configurador, errores, excepción, executor y failover**

Signatures exactas del bloque Interfaces. Detalles:
- `AiRequestExecutor::execute` se inyecta `AiHealthService` y `AiProviderErrors` por constructor.
- `AiStreamFailover::retry`:
  1. `if ($failed->hasYielded()) return null;`
  2. `health->recordFailure($current, $e->getMessage())`.
  3. `if (! errors->isFallbackable($e)) return null;`
  4. Acumular `$errorsByProvider[$current->name] = message`; `$tried[] = $current->id`.
  5. Siguiente en `chain` (por id, después del actual) → si no hay: `$lastError = new AiAllProvidersFailedException($errors, $e); return null`.
  6. `$current = $next; return $rebuild($tried);` (si el rebuild lanza, dejarlo propagar: es error de app, no de proveedor).
- `AiProviderErrors` con helpers privados `requestStatus(Throwable): ?int` (busca `RequestException` en la cadena de `previous` también — el executor envuelve).

- [ ] **Step 4: Correr y verificar que pasan**

Run: `php artisan test --compact --filter="AiRequestExecutorTest|AiStreamFailoverTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Ai/Support/AiProviderConfigurator.php app/Ai/Support/AiProviderErrors.php app/Ai/Support/AiAllProvidersFailedException.php app/Ai/Support/AiRequestExecutor.php app/Ai/Support/AiStreamFailover.php tests/Feature/Ai/AiRequestExecutorTest.php tests/Feature/Ai/AiStreamFailoverTest.php
git commit -m "feat(ai): provider failover executor for eager and streaming paths"
```

---
### Task 7: Corte del chat — resolver, failover de streaming y mensajes honestos

**Files:**
- Modify: `app/Ai/Services/ChatService.php` (`isConfigured` ~línea 74, `streamTurn` 121-179, `decide` 239-276, `configureUserProvider` 108-114, `createThread` 96-106; nuevos miembros públicos `lastFailover`, `lastProviderName`, `lastFallbackUsed`)
- Modify: `app/Http/Controllers/Ai/ChatController.php` (`streamResponse` 302-431, `errorMessageFor` 501-517)
- Test: `tests/Feature/Ai/ChatFailoverTest.php` (nuevo) + regresión `ChatStreamTest`

**Interfaces:**
- Consumes: Tasks 5–6 (`AiScopeResolver::resolve`, `AiRequestExecutor`, `AiStreamFailover`, `AiProviderConfigurator::wire`), `AiPromptComposer` (vía resolution).
- Produces (usan Tasks 8–10, 14):
  - `ChatService::isConfigured(User $user): bool` → `$user->ai_enabled && AiProvider::query()->where('user_id', …)->where('enabled', true)->exists()`.
  - `ChatService::streamTurn(…)` nuevos atributos públicos tras llamar: `?AiStreamFailover $lastFailover`, `?string $lastProviderName`, `bool $lastFallbackUsed`.
  - `streamTurn(..., array $skipProviderIds = [])` — parámetro final; la resolución excluye esos ids de la cadena y arranca con el siguiente (lo usa el rebuild del failover).
  - `ChatController::streamResponse(StreamableAgentResponse $stream, ChatThread $thread, …)` — misma firma; consume `$this->service->lastFailover` interno.

- [ ] **Step 1: Escribir el test fallido**

```php
// tests/Feature/Ai/ChatFailoverTest.php
test('chat falls back to the backup provider before any content reaches the browser', function () {
    $user = User::factory()->withAiProvider()->create();          // provider A (api.example.com)
    $backup = AiProvider::factory()->for($user)->create(['url' => 'https://backup.example.com/v1', 'sort_order' => 1]);

    Http::fake([
        'api.example.com/*'   => Http::response(['error' => ['message' => 'Unauthorized']], 401),
        'backup.example.com/*' => Http::response([
            'choices' => [['delta' => ['content' => 'Hola desde el backup'], 'finish_reason' => null]],
        ], 200, ['Content-Type' => 'text/event-stream']),
    ]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'Hola'])->streamedContent();

    expect($content)
        ->toContain('"type":"thread"')
        ->toContain('Hola desde el backup')
        ->not->toContain('"type":"error"')
        ->and(Http::recorded()->count())->toBeGreaterThan(1);
});

test('single provider chain keeps the exact error copy', function () {
    $user = User::factory()->withAiProvider()->create();
    Http::fake(['*' => Http::response(['error' => ['message' => 'Unauthorized']], 401)]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'Hola'])->streamedContent();

    expect($content)->toContain('API key')->toContain('[DONE]');   // copy exacto de errorMessageFor
});

test('all providers failed surfaces an honest message and reports per-provider detail', function () {
    $user = User::factory()->withAiProvider()->create();
    AiProvider::factory()->for($user)->create(['url' => 'https://backup.example.com/v1', 'sort_order' => 1]);
    Http::fake(['*' => Http::response(['error' => ['message' => 'Unauthorized']], 401)]);

    $content = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => 'Hola'])->streamedContent();

    expect($content)
        ->toContain('"type":"error"')
        ->toContain('API key')            // el copy sale del original (previous)
        ->toContain('proveedor');         // + nota de que falló la cadena completa
});
```

El `Http::fake` de dos hosts replica cómo hace hoy `ChatStreamTest` (el gateway usa `Http` vía config de url/key por provider). Si el primer intento no marca el host esperado, verificar con `Http::recorded()` la URL real y ajustar el pattern (el wire de Task 6 pone `url` en `ai.providers.pm{id}.url` → `api.example.com` con la factory).

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=ChatFailoverTest`
Expected: FAIL (hoy no hay fallback: con A en 401 el stream termina en error aunque exista B)

- [ ] **Step 3: Implementar el corte en ChatService**

- `streamTurn` (y `decide`) reemplazan `[$provider, $defaultModel] = AiProviderResolver::for(...)` por:
  1. `$resolution = $this->resolver->resolve($user, AiScope::SurfaceChat, $moduleKey, $thread->id)` (`$moduleKey = $thread->module` — llega en Task 8; por ahora `null` salvo `category='salud'` de Task 8).
  2. `$resolution->isEmpty()` → `throw new RuntimeException('El proveedor de IA no está configurado.')` (copy actual de `streamTurn` cuando no está configurado).
  3. `$primary = $resolution->primary()`; `$model` explícito (param) gana sobre `$primary->model`.
  4. `$agent->withPersonalization($resolution->promptBlock)`.
  5. Build del intento vía closure local `build(AiProvider $p): StreamableAgentResponse` que hace `AiProviderConfigurator::wire($p, $thread->id)` y `continue(...)->stream($message, attachments:…, provider: "pm{$p->id}", model: $model ?: $p->model)`.
  6. `$this->lastFailover = new AiStreamFailover($primary, $resolution, $this->health, $this->errors, fn (array $tried) => $this->rebuild(...))` y `return $build($primary)`.
  - `rebuild(array $tried)`: re-resuelve con `skipProviderIds = $tried`, toma el nuevo primary, re-wire, re-build (re-ejecuta `forceWebSearch` solo si `forceWeb` estaba activo — duplicar la búsqueda es aceptable en este path rarísimo).
  - Setear `lastProviderName`/`lastFallbackUsed` después del build exitoso (`$this->lastFallbackUsed = $failover->usedFallback()` al final del turno — el controller lo lee tras el loop).
- `configureUserProvider()` (público, usado por `decide` y quizás tests): ahora delega a `AiProviderConfigurator::wire` con el primary del scope chat; si no hay provider, no-op.
- `decide()`: mismo patrón (resolve + failover).
- Inyecciones por constructor: `AiScopeResolver`, `AiRequestExecutor`, `AiHealthService`, `AiProviderErrors`.

- [ ] **Step 4: Implementar el loop de failover en `ChatController::streamResponse`**

Dentro del closure, envolver el `foreach ($stream as $event)` existente:

```php
$stream = $stream;                     // capturado
$failover = $this->service->lastFailover;
while (true) {
    try {
        foreach ($stream as $event) {
            // … cuerpo existente intacto …
        }
        break;
    } catch (Throwable $exception) {
        $retry = $failover?->retry($exception, $stream);
        if ($retry !== null) {
            $stream = $retry;
            continue;
        }
        report($failover?->lastError ?? $exception);
        echo 'data: '.json_encode([
            'type' => 'error',
            'message' => $this->errorMessageFor($failover?->lastError?->lastException() ?? $exception),
            'recoverable' => false,
            // cuando hubo cadena >1 y se agotó, anexar " (Fallaron N proveedores.)"
        ])."\n\n";
        flush();
        break;
    }
}
```

- `errorMessageFor` gana un primer caso: `AiAllProvidersFailedException $e` → delega en `errorMessageFor($e->lastException())` (así el copy de 401/429/400 queda idéntico al de hoy; el suffix ` (Fallaron N proveedores.)` solo si `$failover->resolution` tenía > 1 candidato — con cadena de 1 el output byte a byte es el actual).
- Tras el loop exitoso: emitir evento `{'type':'meta','provider': lastProviderName, 'fallback': lastFallbackUsed}` y persistir en el meta del último mensaje assistant `['ai' => ['provider'=>…, 'model'=>…, 'fallback'=>bool]]` (patrón de `storeReasoning`).

- [ ] **Step 5: Correr tests nuevos + regresión**

Run: `php artisan test --compact --filter=ChatFailoverTest && php artisan test --compact tests/Feature/Ai/ChatStreamTest.php tests/Feature/Ai/ChatRegenerateTest.php tests/Feature/Ai/ChatApprovalTest.php`
Expected: PASS — los 5 tests de error copy de `ChatStreamTest` (402/429/401/400/418) quedan **sin cambios** y verdes.

- [ ] **Step 6: Commit**

```bash
git add app/Ai/Services/ChatService.php app/Http/Controllers/Ai/ChatController.php tests/Feature/Ai/ChatFailoverTest.php
git commit -m "feat(ai): chat resolves scopes and fails over before first content"
```

---
### Task 8: Hilos por módulo + rutas `/ai/{module}` + prompt por módulo en el chat

**Files:**
- Modify: `app/Ai/Services/ChatService.php` (`createThread` — nuevo parámetro `?string $module = null`; resolución de scope con `$moduleKey` del hilo; `threadsFor`)
- Modify: `app/Http/Controllers/Ai/ChatController.php` (nuevo método `module()`, prop `moduleKey` en `index`/`show`, validación de `module` en el envío)
- Modify: `app/Http/Requests/SendChatMessageRequest.php` (acepta `module`)
- Modify: `routes/web.php` (~línea 186-201, grupo `ai`)
- Modify: `resources/js/pages/ai/chat.tsx`, `resources/js/pages/ai/thread.tsx` (prop `moduleKey` mínima)
- Test: `tests/Feature/Ai/AiModuleChatTest.php`

**Interfaces:**
- Consumes: Tasks 5, 7.
- Produces (usan Task 14):
  - Rutas: `GET ai/{module}` → `ChatController::module` (`->where('module', 'gym|nutrition|grocery|finance|freelance|health|people')`, **nombre** `ai.module`), definida **después** de `ai/chat` y del resto de rutas literales del grupo para no capturarlas.
  - `ChatController::module(Request $request, string $module): Response` → `Inertia::render('ai/module', ['module' => $module, 'threads' => …, 'suggestions' => …])` — `threads` = `threadsFor($user, $module)` (gym/nutrition/… filtran `module = ?`; `health` filtra `category = 'salud'` para no partir el historial).
  - `POST ai/chat` acepta `module` (opcional, validado `in:` la lista) → `createThread(…, module: $module)`.
  - Scope resuelto en `streamTurn`/`decide`: `AiScope::SurfaceChat` + `$moduleKey` = `AiScope::fromModuleKey($thread->module)->moduleKey()` (o el de `category='salud'` → `'health'` cuando `category === 'salud'`).

- [ ] **Step 1: Escribir el test fallido**

```php
// tests/Feature/Ai/AiModuleChatTest.php
test('sending from a module route creates a thread tagged with the module', function () {
    $user = User::factory()->withAiProvider()->create();
    MegalomaniacAgent::fake(['OK']);

    $this->actingAs($user)->post(route('ai.chat.send'), ['message' => '¿Cómo voy?', 'module' => 'gym'])
        ->assertOk();

    expect(ChatThread::query()->sole()->module)->toBe('gym');
});

test('module page lists only its threads', function () {
    $user = User::factory()->withAiProvider()->create();
    $gym = ChatThread::query()->create(['id' => (string) Str::uuid7(), 'participant_type' => $user->getMorphClass(), 'participant_id' => $user->id, 'title' => 'Gym', 'agent' => 'megalomaniac', 'module' => 'gym', 'category' => 'general']);
    $fin = ChatThread::query()->create([/* … igual pero module finance, con mensajes … */]);
    // darle un mensaje al thread de finanzas para que pase withMessages()

    $this->actingAs($user)->get('/ai/finance')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/module')
            ->where('module', 'finance')
            ->where('threads.0.id', $fin->id));
});

test('health module threads come from the existing salud category', function () {
    $user = User::factory()->withAiProvider()->create();
    $salud = ChatThread::query()->create([/* … category = 'salud', con mensaje … */]);

    $this->actingAs($user)->get('/ai/health')
        ->assertInertia(fn (Assert $page) => $page->component('ai/module')->where('threads.0.id', $salud->id));
});

test('module scope takes part in provider resolution for the chat turn', function () {
    $user = User::factory()->withAiProvider()->create();                       // global: A
    $gymProvider = AiProvider::factory()->for($user)->create(['url' => 'https://gym.example.com/v1', 'sort_order' => 5]);
    UserAiScope::factory()->for($user)->create(['scope' => 'module:gym', 'provider_chain' => [$gymProvider->id]]);

    $thread = ChatThread::query()->create([/* … module = 'gym' … */]);
    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceChat, 'gym');

    expect($resolution->primary()->id)->toBe($gymProvider->id);
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiModuleChatTest`
Expected: FAIL (ruta/module inexistente)

- [ ] **Step 3: Implementar rutas, controller, ChatService y request**

Como en el bloque Interfaces. Detalles:
- `module()` reutiliza la misma lógica de props que `index()` (rail de hilos agrupado Hoy/Fijados, buscador) filtrando por módulo; el componente es nuevo (`ai/module`) pero **recibe las mismas props** que `ai/chat` + `module`.
- `thread->module` se setea en `createThread` y **no** se cambia después (el hilo nace en un módulo).
- `category='salud'` en `streamTurn`: `$moduleKey = $thread->module ?? ($thread->category === 'salud' ? 'health' : null)`.

- [ ] **Step 4: Implementar mínimo en frontend**

- `pages/ai/module.tsx`: por ahora un wrapper que importa y reutiliza el default export de `ai/chat.tsx` pasándole `module` (mismo componente, distinto título/empty state llega en Task 14 — acá solo tiene que renderizar y poder enviar). Si `chat.tsx` no acepta props nuevas, agregar `module?: string | null` al props type y reenviarlo en el POST (los chips de sugerencias vacías los agrega Task 14).
- `npm run types` debe quedar en 0.

- [ ] **Step 5: Correr tests + regresión**

Run: `php artisan test --compact --filter=AiModuleChatTest && php artisan test --compact tests/Feature/Ai`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Ai/Services/ChatService.php app/Http/Controllers/Ai/ChatController.php app/Http/Requests routes/web.php resources/js/pages/ai tests/Feature/Ai/AiModuleChatTest.php
git commit -m "feat(ai): module-scoped assistant threads and routes"
```

---

### Task 9: Corte del resto de callers + eliminación del resolver legacy

**Files:**
- Modify: `app/Ai/Agents/AgentRunner.php:37-61` (scope `surface:agents` + executor eager)
- Modify: `app/Ai/Services/InsightService.php` (5 métodos → `surface:insights` + módulo)
- Modify: `app/Http/Controllers/AiInsightController.php` (nutrition → `module:nutrition`; generateQuote/generateTaskDescription → `module:freelance`; pasan por executor)
- Modify: `app/Feed/DigestAgent.php:84-95` (`surface:feed`), `app/Feed/FeedRanker.php:37,103` (embeddings → `surface:embeddings`; scoring → `surface:feed`)
- Delete: `app/Ai/Support/AiProviderResolver.php`, `tests/Feature/Ai/AiProviderResolverTest.php`
- Test: `tests/Feature/Ai/AiCallerScopesTest.php`

**Interfaces:**
- Consumes: Tasks 5–6.
- Produces: todos los callers resuelven con `app(AiScopeResolver::class)->resolve($user, AiScope::Surface{X}, {moduleKey})` y ejecutan con `app(AiRequestExecutor::class)->execute(...)`; los intentos usan `AiProviderConfigurator::wire($provider, $sessionId)` y `provider: 'pm{id}'`.

Mapeo exacto de scopes por caller:

| Caller | surface | moduleKey |
|---|---|---|
| `AgentRunner::run` | `SurfaceAgents` | `null` |
| `InsightService::generateWorkoutInsights` | `SurfaceInsights` | `gym` |
| `InsightService::generateFinanceInsights` | `SurfaceInsights` | `finance` |
| `InsightService::generateGroceryInsights` | `SurfaceInsights` | `grocery` |
| `InsightService::generateTaskInsights` | `SurfaceInsights` | `freelance` |
| `AiInsightController::nutrition` | `SurfaceInsights` | `nutrition` |
| `AiInsightController::generateQuote` | `SurfaceInsights` | `freelance` |
| `AiInsightController::generateTaskDescription` | `SurfaceInsights` | `freelance` |
| `DigestAgent` | `SurfaceFeed` | `null` |
| `FeedRanker` (scoring) | `SurfaceFeed` | `null` |
| `FeedRanker::ensureEmbeddings` | `SurfaceEmbeddings` | `null` (modelo = `embeddings_model` del provider; si es null → no embeddings, igual que hoy) |

- [ ] **Step 1: Escribir el test fallido**

```php
// tests/Feature/Ai/AiCallerScopesTest.php
test('insight callers resolve their module scope', function () {
    $user = User::factory()->withAiProvider()->create();
    $gymProvider = AiProvider::factory()->for($user)->create(['sort_order' => 5]);
    UserAiScope::factory()->for($user)->create(['scope' => 'module:gym', 'provider_chain' => [$gymProvider->id]]);

    $resolution = app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceInsights, 'gym');
    expect($resolution->primary()->id)->toBe($gymProvider->id);
});

test('agent runner falls back when the primary provider fails', function () {
    $user = User::factory()->withAiProvider()->create();
    $definition = /* AgentDefinition factory con enabled, schedule, instructions */;
    $backup = AiProvider::factory()->for($user)->create(['url' => 'https://backup.example.com/v1']);

    Http::fake([
        'api.example.com/*' => Http::response(['error' => ['message' => 'down']], 500),
        'backup.example.com/*' => Http::response(['choices' => [['message' => ['content' => '{"report":"ok","suggestions":null,"notify":null}']]]], 200),
    ]);

    $run = (new app(AgentRunner::class))->run($definition, 'manual');

    expect($run->status)->toBe('success')
        ->and((new AiHealthService)->statusFor(AiProvider::query()->where('user_id', $user->id)->where('url', 'like', 'https://api.example.com%')->first())->consecutive_failures)->toBe(1);
});

test('embeddings resolve the embeddings scope provider', function () {
    $user = User::factory()->withAiProvider()->create();   // sin embeddings_model
    expect(app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceEmbeddings)->primary()?->embeddings_model)->toBeNull();

    AiProvider::factory()->for($user)->update(['embeddings_model' => 'embed-1']);
    expect(app(AiScopeResolver::class)->resolve($user, AiScope::SurfaceEmbeddings)->primary()?->embeddings_model)->toBe('embed-1');
});
```

(Ajustar el fixture de `AgentDefinition` al factory existente — ver `tests/Feature/Agents/*`.)

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiCallerScopesTest`
Expected: FAIL (legacy resolver ignora scopes)

- [ ] **Step 3: Recortar cada caller**

En cada punto marcado en la tabla: resolver scope + `executor->execute($user, $resolution, fn ($key, $model, $provider) => $agent->prompt($prompt, provider: $key, model: $model ?: $provider->model, timeout: …))`. `AgentRunner`: el `try/catch` existente ya cubre la excepción de cadena exhausta → su `skip`/`failed` actual; no cambia el copy `'IA no configurada en Settings → IA.'` (ahora se dispara con `resolution->isEmpty()`). `FeedRanker::ensureEmbeddings` y `DigestAgent` mantienen sus fallbacks locales (embedding sin modelo → léxico; digest sin proveedor → determinístico).

- [ ] **Step 4: Borrar el legacy y correr la suite completa**

```bash
git rm app/Ai/Support/AiProviderResolver.php tests/Feature/Ai/AiProviderResolverTest.php
grep -rn "AiProviderResolver" app/ tests/ resources/   # debe volver 0
php artisan test --compact
```
Expected: 0 referencias; suite PASS salvo los 0 fallos esperados (si hay fallos preexistentes de Grocery/Nutrition documentados en el QA, no se tocan).

- [ ] **Step 5: Pint + Commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "feat(ai): route agents, insights, feed and embeddings through scopes"
```

---
### Task 10: Backend de Settings → IA (CRUD de proveedores, asignaciones, prompts, test de conexión)

**Files:**
- Create: `app/Http/Requests/Settings/StoreAiProviderRequest.php`, `UpdateAiProviderRequest.php`, `UpdateAiScopeRequest.php`
- Modify: `app/Http/Controllers/Settings/AiSettingsController.php` (edit + nuevos métodos)
- Modify: `routes/web.php` (rutas de settings de IA)
- Test: `tests/Feature/Settings/AiProviderSettingsTest.php`

**Interfaces:**
- Consumes: Tasks 3–6.
- Produces (usan Tasks 11–13):
  - `GET settings/ai` (`AiSettingsController::edit`) props:
    ```php
    [
      'ai' => ['ai_enabled' => bool, 'has_tavily_key' => bool, 'tavily_placeholder' => string],
      'providers' => [ ['id', 'name', 'protocol', 'url', 'model', 'embeddings_model', 'enabled', 'sort_order',
                        'has_key' => bool, 'health' => ['consecutive_failures', 'broken_until', 'last_error']|null],
                       … ] (orden sort_order, propios del usuario),
      'scopes' => [ ['scope' => 'global|surface:chat|…|module:people',
                     'chain' => [providerId, …],          // [] = hereda
                     'effective' => [providerId, …],       // resuelto hoy (con filtro de salud)
                     'prompt' => string|null,
                     'prompt_preview' => string|null,     // solo en el detalle de prompts; aquí null
                   ] × 13 ],
      'module_labels' => [...],
    ]
    ```
  - `POST settings/ai/providers` → `store` (201 + redirect con `preserveScroll`)
  - `PATCH settings/ai/providers/{provider}` → `update` (key en blanco = conservar)
  - `DELETE settings/ai/providers/{provider}` → `destroy`
  - `POST settings/ai/providers/{provider}/test` → JSON `{ok: bool, ms: int, error: ?string, provider: string}`
  - `PATCH settings/ai/scopes/{scope}` → `updateScope` — body `provider_chain` (array de ids propios, `[]` = heredar) y/o `prompt` (`nullable|string|max:8000`)
  - `POST settings/ai/prompts/preview` → JSON `{preview: string}` con el prompt final compuesto de un scope (scope de body).
  - `PATCH settings/ai` (el `update` actual) **se queda solo con** `ai_enabled` + `tavily_api_key` (los campos BYO salen del form).

- [ ] **Step 1: Escribir el test fallido**

```php
// tests/Feature/Settings/AiProviderSettingsTest.php
test('edit exposes providers, health and effective chains scoped to the user', function () {
    $me = User::factory()->withAiProvider()->create();
    $other = User::factory()->withAiProvider()->create();   // provider ajeno no debe aparecer

    $this->actingAs($me)->get(route('settings.ai.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->has('providers', 1)
            ->where('providers.0.name', 'Principal')
            ->count('scopes', 13));
});

test('creates, updates, tests and deletes a provider', function () {
    $user = User::factory()->create(['ai_enabled' => true]);

    $this->actingAs($user)->post(route('settings.ai.providers.store'), [
        'name' => 'Backup', 'protocol' => 'openai_compatible',
        'url' => 'https://backup.example.com/v1', 'key' => 'sk-b', 'model' => 'gpt-4o-mini',
    ])->assertRedirect();

    $provider = AiProvider::query()->where('user_id', $user->id)->where('name', 'Backup')->sole();

    Http::fake(['backup.example.com/*' => Http::response(['choices' => []], 200)]);
    $this->actingAs($user)->post(route('settings.ai.providers.test', $provider))
        ->assertOk()->assertJsonPath('ok', true);

    $this->actingAs($user)->patch(route('settings.ai.providers.update', $provider), ['model' => 'new-model', 'key' => ''])
        ->assertRedirect();
    expect($provider->fresh()->model)->toBe('new-model')->and($provider->fresh()->key)->toBe('sk-b'); // key vacía conserva

    $this->actingAs($user)->delete(route('settings.ai.providers.destroy', $provider))->assertRedirect();
    expect(AiProvider::query()->find($provider->id))->toBeNull();
});

test('cannot touch another users provider', function () {
    $a = User::factory()->create();
    $bProvider = AiProvider::factory()->create();   // de otro user

    $this->actingAs($a)->delete(route('settings.ai.providers.destroy', $bProvider))->assertNotFound();
    $this->actingAs($a)->post(route('settings.ai.providers.test', $bProvider))->assertNotFound();
});

test('updates a scope chain and validates provider ownership', function () {
    $user = User::factory()->create();
    [$mine] = AiProvider::factory()->count(1)->for($user)->create()->all();
    $foreign = AiProvider::factory()->create();

    $this->actingAs($user)->patch(route('settings.ai.scopes.update', 'module:gym'), ['provider_chain' => [$mine->id, $foreign->id]])
        ->assertSessionHasErrors('provider_chain');   // solo ids propios

    $this->actingAs($user)->patch(route('settings.ai.scopes.update', 'module:gym'), ['provider_chain' => [$mine->id]])
        ->assertRedirect();

    expect(UserAiScope::query()->where('user_id', $user->id)->where('scope', 'module:gym')->sole()->provider_chain)
        ->toBe([$mine->id]);
});

test('prompt layer validates the 8000 character limit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('settings.ai.scopes.update', 'global'), ['prompt' => str_repeat('x', 8001)])
        ->assertSessionHasErrors('prompt');

    $this->actingAs($user)->patch(route('settings.ai.scopes.update', 'global'), ['prompt' => str_repeat('x', 8000)])
        ->assertRedirect();
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiProviderSettingsTest`
Expected: FAIL (rutas/métodos inexistentes)

- [ ] **Step 3: Implementar Form Requests + controller + rutas**

- `StoreAiProviderRequest`: `name => required|string|max:80|unique:ai_providers,name` (unique scoping manual por usuario en `withValidator` o `Rule::unique()->where('user_id', auth()->id())`), `protocol => required|in:openai_compatible`, `url => required|string|url|max:2048`, `key => required|string|max:500`, `model => required|string|max:100`, `embeddings_model => nullable|string|max:100`, `enabled => boolean`, `sort_order => integer|min:0`.
- `UpdateAiProviderRequest`: mismos pero `name => unique ignore self`, `key => nullable` (**en blanco → no se toca**: `if (blank($validated['key'])) unset($validated['key'])` como el update actual).
- `UpdateAiScopeRequest`: `scope => required|in:{los 13 values}`, `provider_chain => array|max:20`, `provider_chain.* => integer|exists:ai_providers,id` + regla: cada id pertenece al usuario (422/`session errors`), `prompt => nullable|string|max:8000`. `provider_chain => []` válido (borrar fila o guardar `[]` → hereda: `updateOrCreate` con `provider_chain => []`).
- `updateScope` hace `UserAiScope::updateOrCreate(['user_id'=>…, 'scope'=>$scope], $validated)`.
- `test`: construye un `MegalomaniacAgent($user)` + `wire($provider)` + `prompt('Respondé solo con OK.', timeout: 15)` dentro de try/catch → `markSuccess`/`recordFailure` + `{ok, ms, error, provider}`.
- `edit`: props del bloque Interfaces (`scopes` siempre con las 13 filas materializadas: `AiScope::cases()` + fila de BD si existe; `effective` = `AiScopeResolver::resolve(...)` con la convención surface/module del scope — para scopes `surface:*`, resolve(surface) sin módulo; para `module:*`, resolve(SurfaceChat con ese module) como representación canónica — documentarlo en el docblock).
- Rutas con nombres: `settings.ai.providers.store|update|destroy|test`, `settings.ai.scopes.update`, `settings.ai.prompts.preview` — dentro del grupo de settings existente (ver `routes/settings.php` si existe o el grupo en `web.php`).

- [ ] **Step 4: Correr y verificar que pasan**

Run: `php artisan test --compact --filter=AiProviderSettingsTest && php artisan test --compact --filter=AiSettings`
Expected: PASS (el test viejo de `update` de settings sigue verde con los campos reducidos — si el form request viejo validaba `ai_provider_url`, ajustar sus tests: esas columnas dejan de venir del form)

- [ ] **Step 5: Regenerar Wayfinder + Commit**

Run: `php artisan wayfinder:generate` (o el script del package.json que lo dispare) → `npm run types`
Expected: 0 errores TS

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Settings app/Http/Controllers/Settings routes resources/js/routes tests/Feature/Settings/AiProviderSettingsTest.php
git commit -m "feat(ai): provider CRUD, scope assignments and prompt endpoints"
```

---

### Task 11: UI — esqueleto de pestañas + pestaña «Proveedores»

**Files:**
- Modify: `resources/js/pages/settings/ai.tsx` (reescritura de la página: fuera de tabs quedan `ai_enabled` + Tavily; dentro, 3 tabs; esta task = shell + tab Proveedores)
- Test: `tests/Feature/Settings/AiSettingsPageTest.php` (render + props) y verificación visual manual (build)

**Interfaces:**
- Consumes: Task 10 (props + endpoints), componentes `components/ui/{tabs,table,badge,sheet,textarea,select,spinner}` existentes, tokens del proyecto.
- Produces (usan Tasks 12–13): estructura de la página con `<Tabs defaultValue="providers">` y slots claros: `<TabsContent value="providers">` (esta task), `value="assignments"` (Task 12), `value="prompts"` (Task 13).

- [ ] **Step 1: Escribir el test de render fallido**

```php
// tests/Feature/Settings/AiSettingsPageTest.php
test('settings ai page renders the provider tab with health badges data', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)->get(route('settings.ai.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->where('providers.0.has_key', true)
            ->has('scopes', 13)
            ->where('ai.ai_enabled', true));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiSettingsPageTest`
Expected: PASS en realidad si el backend de Task 10 ya lo cumple — en ese caso este paso valida solo que la página sigue renderizando tras la reescritura; si el componente nuevo no existe aún, el assert de componente falla al final. Mantener el test como gate de la task.

- [ ] **Step 3: Reescribir `settings/ai.tsx`**

Estructura (patrón actual: `MainLayout` → `SettingsLayout` → `Heading` + `Form`):
- Card 1 (fuera de tabs): switch `ai_enabled` + descripción (como hoy).
- Card 2 (fuera de tabs): Tavily (copiar actual intacto).
- `<Tabs defaultValue="providers">` con `TabsList` de 3: `Proveedores`, `Asignaciones`, `Prompts`.
- **Tab Proveedores:**
  - `<Table>`: Nombre · Modelo · URL (`truncate max-w-[180px]`, `title` con la URL completa) · Estado (Badge: `OK` / `N fallas` / `caído hasta HH:MM` con `text-primary` cuando caído) · acciones.
  - Acciones: **Probar** (botón → `AiSettingsController.test` vía wayfinder + fetch con `X-XSRF-TOKEN` de `lib/csrf.ts`; spinner mientras; badge inline `✓ 240ms` o `✗ mensaje`) · **Editar** (`Sheet` con form: name, url, key (placeholder `•••••••• (guardada)` cuando `has_key`), model, embeddings_model) · habilitar/deshabilitar (checkbox en la fila → `update`) · eliminar (confirmación → `destroy`).
  - Empty state: ícono + "Todavía no tenés proveedores" + botón primario «Agregar proveedor» (abre el mismo Sheet en modo create).
- Sin hex nuevos; `material-symbols-outlined` para iconos como el resto de settings.

- [ ] **Step 4: Verificar**

Run: `npm run types && npm run build && php artisan test --compact --filter=AiSettingsPageTest`
Expected: 0 errores TS, build OK, test PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/settings/ai.tsx tests/Feature/Settings/AiSettingsPageTest.php
git commit -m "feat(ui): settings AI tab shell with provider health table"
```

---
### Task 12: UI — pestaña «Asignaciones»

**Files:**
- Modify: `resources/js/pages/settings/ai.tsx` (`<TabsContent value="assignments">`)
- Test: `tests/Feature/Settings/AiSettingsPageTest.php` (agregar caso)

**Interfaces:**
- Consumes: Task 10 (`scopes` prop con `chain`/`effective`/labels, endpoint `PATCH settings/ai/scopes/{scope}`), Task 11 (shell de tabs).
- Produces: fila de asignación persistente por scope.

- [ ] **Step 1: Escribir el test fallido (casos en el test de página existente)**

```php
test('assignments tab data lists global, surfaces and modules', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)->get(route('settings.ai.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->where('scopes.0.scope', 'global')
            ->where('scopes.1.scope', 'surface:chat')   // orden fijo = AiScope::cases()
            ->where('scopes.0.chain', []));             // sin filas → hereda implícita
});
```

- [ ] **Step 2: Correr y verificar**

Run: `php artisan test --compact --filter=AiSettingsPageTest`
Expected: PASS si el backend de Task 10 ya emite las 13 filas en orden de cases; si no, ajustar `edit()` en Task 10 (el gate es el orden).

- [ ] **Step 3: Implementar la tab**

- Tabla jerárquica de 3 grupos con headers `text-[10px] font-black uppercase tracking-widest text-muted-foreground` (patrón del proyecto):
  1. **Global** (1 fila).
  2. **Superficies**: chat, agentes, insights, feed, embeddings.
  3. **Módulos**: gimnasio, nutrición, grocery, finanzas, freelance, salud, personas.
- Cada fila: label del scope · control de cadena · estado · cadena efectiva.
- **Control de cadena**: `Select` con el primario (opción vacía = "Hereda de ↑" cuando el scope no es global; para global, vacío = "Todos los habilitados (sort_order)") + chips de backups con botones ↑ ↓ ✕ (agregar backup desde otro `Select` "…respaldo"). Estados locales en `useState` por fila; `PATCH` al cambiar (con `router.patch` de Inertia o el form de wayfinder, `preserveScroll`).
- Columna **Efectiva**: texto pequeño `Activo: {nombre} → {nombre}` desde `effective` (o `Activo: {nombre}` si hay 1).
- `surface:embeddings` y scopes sin prompt no muestran nada de prompts acá (solo cadena).

- [ ] **Step 4: Verificar**

Run: `npm run types && npm run build && php artisan test --compact --filter=AiSettingsPageTest`
Expected: todo OK. Smoke manual: cambiar primario en una fila → recargar → persiste.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/settings/ai.tsx tests/Feature/Settings/AiSettingsPageTest.php
git commit -m "feat(ui): scope assignment matrix with fallback chains"
```

---

### Task 13: UI — pestaña «Prompts»

**Files:**
- Modify: `resources/js/pages/settings/ai.tsx` (`<TabsContent value="prompts">`)
- Modify: `app/Http/Controllers/Settings/AiSettingsController.php` (preview — ya creado en Task 10; si no, agregarlo acá)
- Test: backend en `AiProviderSettingsTest` (preview) + `AiSettingsPageTest` (props)

**Interfaces:**
- Consumes: Tasks 3 (composer/preview), 10 (`prompt` en scopes + `POST settings/ai/prompts/preview`).

- [ ] **Step 1: Escribir el test fallido**

```php
// en AiProviderSettingsTest
test('prompt preview returns the composed string for a scope', function () {
    $user = User::factory()->create();
    AiProvider::factory()->for($user)->create();
    UserAiScope::factory()->for($user)->create(['scope' => 'global', 'prompt' => 'Capa global.']);

    $this->actingAs($user)->post(route('settings.ai.prompts.preview'), ['scope' => 'surface:chat'])
        ->assertOk()
        ->assertJsonPath('preview', fn ($v) => str_contains($v, '## Personalización global'));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiProviderSettingsTest`
Expected: FAIL (endpoint no existe o devuelve preview vacío)

- [ ] **Step 3: Implementar preview + tab**

- Backend: `preview` → `$agent = new MegalomaniacAgent($user); AiPromptComposer::preview($agent->instructions(), composer->personalizationBlock($user, $moduleKey, $surface))` — desglosar el scope elegido en (moduleKey, surface); la base es `instructions()` con contexto neutro (sin skills/memoria de turno): documentar en el docblock que runtime context no entra en la preview.
- Frontend: `Select` de scope (Global · 7 módulos · 4 superficies: chat/agentes/insights/feed — **sin embeddings**) · `Textarea` con contador `{n}/8000` (rojo al superar, submit deshabilitado) · botón **Vaciar capa** · `Collapsible` «Vista previa del prompt final» que llama al preview (fetch al change de scope y tras cada guardado, o botón "Actualizar vista previa" si se prefiere simple).

- [ ] **Step 4: Verificar**

Run: `php artisan test --compact --filter=AiProviderSettingsTest && npm run types && npm run build`
Expected: PASS / 0 / OK.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Settings/AiSettingsController.php resources/js/pages/settings/ai.tsx tests/Feature/Settings
git commit -m "feat(ui): prompt layer editor with composed preview"
```

---
### Task 14: Asistentes por módulo — sidebar, entry points y badge de proveedor

**Files:**
- Modify: `resources/js/pages/ai/module.tsx` (empty state con 3 chips de sugerencias por módulo, título del asistente)
- Create: `resources/js/components/ai/module-ai-button.tsx`
- Modify: `resources/js/components/app-sidebar.tsx` (sección «Asistente IA» con 8 ítems)
- Modify: `resources/js/pages/ai/chat.tsx` y `resources/js/pages/ai/thread.tsx` (badge de proveedor + indicador de fallback desde `meta` del último assistant y/o evento `type:meta`)
- Modify: 7 páginas de módulo (header): `fitness/gym-routine.tsx`, `fitness/nutrition.tsx`, `fitness/grocery.tsx`, `finance/dashboard.tsx`, `health/Dashboard.tsx`, `freelance/Dashboard.tsx`, `people/Index.tsx`
- Test: `tests/Feature/Ai/AiModuleChatTest.php` (agregar casos de props) + smoke visual

**Interfaces:**
- Consumes: Task 8 (ruta `ai.module`, prop `module`), Task 7 (evento `type:meta` y `meta.ai` del mensaje), props del chat existente.
- Produces: UI final de asistentes.

Chips de sugerencias por módulo (empty state, exactos):
- `gym`: «¿Cómo va mi semana?», «Sugerí un ejercicio para pecho», «Compará mi último PR»
- `nutrition`: «¿Cómo voy en proteína?», «Armame un menú para mañana», «¿Qué comí esta semana?»
- `grocery`: «¿Qué me falta comprar?», «Armame la lista del súper», «¿Venció algo?»
- `finance`: «¿Cómo voy este mes?», «¿Cuánto gasté en ocio?», «¿Qué deudas tengo?»
- `freelance`: «¿Qué proyectos tengo activos?», «Redactá una cotización», «¿Qué tareas vencen?»
- `health`: «¿Cómo están mis mediciones?», «¿Qué medicamento me queda?», «Resumí mis síntomas»
- `people`: «¿Con quién hablé hace días?», «¿Qué fechas tengo próximas?», «Resumí a {persona}» (la última solo si `people` tiene personas; si no, usar «¿Quiénes son mis contactos frecuentes?»)

- [ ] **Step 1: Escribir los tests fallidos de props**

```php
// agregado en AiModuleChatTest
test('module page passes its key and module-specific suggestions', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)->get('/ai/gym')
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/module')
            ->where('module', 'gym')
            ->has('suggestions', 3)
            ->where('suggestions.0', '¿Cómo va mi semana?'));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=AiModuleChatTest`
Expected: FAIL (prop `suggestions` inexistente)

- [ ] **Step 3: Implementar backend de suggestions**

Mapa `string $module => array<int, string>` en `ChatController::module()` (o en una constante `AiModule::SUGGESTIONS` en `app/Ai/Enums/`) + prop `suggestions`; el resto de props de `ai/module` idénticas a `index`.

- [ ] **Step 4: Implementar frontend**

- `ai/module.tsx`: `module` prop → heading («Asistente · Gimnasio»…), empty state con los 3 chips (al click → rellena el composer), botón «Volver al chat general».
- `module-ai-button.tsx`: `<Link href={'/ai/'+module}>` con ícono `smart_toy` + label «Asistente IA», variante `Button` ghost/sm — se inserta en el header de cada una de las 7 páginas (misma posición en todas: junto al título).
- Sidebar (`app-sidebar.tsx`): nueva sección **«Asistente IA»** al final: Chat (`/ai/chat`, ícono `SmartToy`), Gimnasio, Nutrición, Grocery, Finanzas, Freelance, Salud, Personas (íconos: Dumbbell, Utensils, ShoppingCart, Wallet, Briefcase, HeartPulse, Users — los mismos que ya usa la sección correspondiente). Activo con `pathname.startsWith('/ai/')` para que el chat general y los módulos se resalten.
- Badge en `thread.tsx`/`chat.tsx`: leer `meta.ai` del último mensaje assistant (persistido en Task 7) → chip ` {provider}` + si `fallback` → chip `Fallback: 2.º proveedor` con `text-primary`; además escuchar evento SSE `type:meta` para mostrarlo en vivo. Si el hilo tiene `module`, link «← Asistente de {módulo}» a `/ai/{module}`.

- [ ] **Step 5: Verificar**

Run: `php artisan test --compact --filter=AiModuleChatTest && npm run types && npm run build`
Expected: PASS / 0 / OK.

- [ ] **Step 6: Commit**

```bash
git add app/Ai/Enums resources/js tests/Feature/Ai/AiModuleChatTest.php
git commit -m "feat(ui): module assistant panels, sidebar section and provider badge"
```

---

### Task 15: Regresión total, QA crawl y estáticos

**Files:**
- Modify: `docs/qa/playwright-report.md` (sección nueva del crawl)
- Fixes que salgan de la corrida

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: **0 failed** (los fallos preexistentes documentados de Grocery/Nutrition/Supplement ya no existen — la última corrida registrada en el QA dio 0; si reaparecen, investigar antes de continuar).

- [ ] **Step 2: Estáticos**

```bash
vendor/bin/pint --dirty --format agent
npm run types        # 0
npm run build        # OK
npx eslint resources/js/pages/settings/ai.tsx resources/js/pages/ai resources/js/components/ai   # 0
```

- [ ] **Step 3: Crawl Playwright** (servidor en `:8010`, usuario `test@example.com/password`; proveedor QA falso en `:9998` como en crawls anteriores)

1. `GET /settings/ai` → 3 tabs; Proveedores: fila `Principal` con badge de estado, Probar → resultado inline; crear proveedor falso → aparece; editar key vacía → conserva.
2. Asignaciones: fila Global con "Todos los habilitados", poner cadena en `module:gym` (2 chips, reordenar ↑↓, quitar) → recargar → persiste; columna "Activo:" refleja la cadena.
3. Prompts: escribir capa global (contador), preview muestra `## Personalización global`, límite 8001 → error.
4. `/ai/gym` → título, 3 chips, enviar mensaje con el fake → stream OK; el hilo queda en la lista de `/ai/gym` y NO en `/ai/finance`.
5. Fallback en vivo: A en 500 + B ok → respuesta OK sin `type:error` y chip `Fallback` visible.
6. 7 rutas `/ai/{…}` + sidebar «Asistente IA» (8 ítems) + botón en los 7 headers de módulo → 0 console errors.
7. `/ai/chat` general regresión: enviar/regenerar/editar/citas → como en el crawl previo.

- [ ] **Step 4: Documentar el crawl**

Append al final de `docs/qa/playwright-report.md` con el patrón de las secciones previas (alcance, escenarios PASS/FAIL, bugs encontrados + fix, estáticos, datos de QA limpiados).

- [ ] **Step 5: Commit final**

```bash
git add docs/qa
git commit -m "docs(qa): crawl for scoped model providers and module assistants"
```

---

## Self-review del plan

- **Cobertura de spec**: §3 datos → Tasks 1–2 · §4 resolver/executor → Tasks 5–7 · §5 prompts → Tasks 3, 7, 8, 13 · §6.1 settings → Tasks 10–13 · §6.2 asistentes → Tasks 8, 14 · §7 rollout → orden de tasks · §8 testing → tests por task + Task 15. Sin huecos.
- **Granularidad de steps**: cada step deja un único artefacto verificable (test rojo → código → verde → commit); los bodies solo aparecen donde la firma no determina el algoritmo (backoff, clasificación de errores, orden de cadena).
- **Consistencia de tipos**: `AiScopeResolver::resolve(User, AiScope, ?string, ?string): AiResolution` y `AiResolution{chain, promptBlock, primary(), isEmpty()}` idénticos en tasks 5→6→7→9→10. `AiProviderErrors`/`AiHealthService` inyectados igual en executor y failover. `wire()` devuelve `pm{id}` y ese string es el `provider:` en todos los callers.
- **Review Focus**: los 5 riesgos tienen su test en el task dueño (Task 5 ×2, Task 6, Task 7, Task 2).
- **Proporción**: el plan cubre 15 tasks con señales, nombres y asserts; los únicos bloques de código extensos son fixtures de test y el snippet del loop de failover (decisión que el executor no puede tomar solo).

