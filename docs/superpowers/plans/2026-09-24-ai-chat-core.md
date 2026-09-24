# AI Chat Core (estilo Perplexity) — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Página completa `/ai/chat` con hilos persistidos, streaming SSE real, markdown + citas, regenerate/edit y selector de modelo dinámico, asentando el modelo de datos para Specs B y C.

**Architecture:** Tablas del SDK (`agent_conversations`/`agent_conversation_messages`) + columnas de extensión propias; `ChatService` centraliza la lógica y `Ai\ChatController` expone Inertia (páginas) y SSE (envuelto con un evento `thread` inicial). Frontend Inertia v2 + React 19 con estado local para el stream y props del servidor como fuente de verdad al completar cada turno.

**Tech Stack:** PHP 8.4 · Laravel 12 · laravel/ai v0.11 · Inertia v2 · React 19 · Tailwind v4 · Radix · Wayfinder · Pest 4 · react-markdown + remark-gfm + rehype-highlight.

**Spec:** `docs/superpowers/specs/2026-09-24-ai-chat-core-design.md`

## Global Constraints

- PHP 8.4, Laravel 12, Pest 4. Tests: `php artisan test --compact --filter=<name>`.
- Formato PHP: `vendor/bin/pint --dirty --format agent` antes de cerrar cada task con PHP.
- Frontend: `npm run types`, `npx eslint <archivos> --fix`, `npm run build`.
- Wayfinder: `php artisan wayfinder:generate --with-form` después de cambiar rutas.
- Convenciones: Eloquent `casts()`, Form Requests, `$this->authorize`, sin `DB::` salvo transacciones, tokens Ember (`bg-background`, `bg-card`, `border-border`, `text-muted-foreground`, `bg-primary`, `text-primary`), sin hex nuevos.
- Eventos SSE del SDK (nombres y campos EXACTOS): `stream_start`, `text_start`, `text_delta` (campo `delta`), `text_end`, `reasoning_start|delta|end`, `tool_call` (`tool_id`, `tool_name`, `arguments`), `tool_result` (`tool_id`, `tool_name`, `result`, `successful`, `error`, `denied`), `citation` (`citation.title`, `citation.url`), `stream_end` (`reason`, `usage`), `error` (`message`, `recoverable`). Evento propio previo: `{"type":"thread","threadId":"<uuid>"}`. Cierre: `data: [DONE]`.
- Citas persistidas del SDK en `meta.citations` con forma `{url, title, start_index, end_index}`.
- Deps npm nuevas aprobadas: `react-markdown`, `remark-gfm`, `rehype-highlight`. Ninguna otra.
- Mensajes de commit en estilo del repo: `feat(ai): …` / `fix(ai): …` / `docs(ai): …`.
- **Commitear por task y stagear solo los archivos de la task** (el working tree tiene cambios ajenos sin commitear; no incluirlos).
- Rama de trabajo: `feat/ai-chat-core`.
- No usar `DB::table()` salvo la transacción permitida en `deleteThread` (patrón existente).
- Sin logs de prompts ni de contenido de chat en el servidor.

---

### Task 1: Migración + modelos `ChatThread`/`ChatMessage` + factories

**Files:**
- Create: `database/migrations/<timestamp>_add_chat_thread_columns_to_agent_conversations_table.php` (generar con `php artisan make:migration add_chat_thread_columns_to_agent_conversations_table --no-interaction`)
- Create: `app/Models/ChatThread.php`
- Create: `app/Models/ChatMessage.php`
- Create: `database/factories/ChatThreadFactory.php`
- Create: `database/factories/ChatMessageFactory.php`
- Test: `tests/Feature/Ai/ChatModelTest.php`

**Interfaces:**
- Produces: `ChatThread` (modelo, tabla `agent_conversations`): scopes `forUser(User)`, `active()`, `withMessages()`, `ordered()`, `search(string)`; `messages(): HasMany<ChatMessage>`; `belongsToUser(User): bool`; `isPinned(): bool`; casts `pinned_at`, `archived_at`.
- Produces: `ChatMessage` (tabla `agent_conversation_messages`): `citations(): array`, `isUser(): bool`.
- Produces: `ChatThreadFactory` con estados `pinned()`, `archived()`; `ChatMessageFactory` con estados `assistant()`, `withCitations(array)`.
- Consumes: nada (primera task).

- [ ] **Step 1: Escribir el test**

```php
<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('thread belongs to user through participant morph', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    expect($thread->belongsToUser($user))->toBeTrue();

    $other = User::factory()->create();

    expect($thread->belongsToUser($other))->toBeFalse();
});

test('for user scope returns only own threads', function () {
    $user = User::factory()->create();
    $own = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    ChatThread::factory()->create();

    expect(ChatThread::query()->forUser($user)->pluck('id')->all())->toBe([$own->id]);
});

test('active scope excludes archived threads', function () {
    $active = ChatThread::factory()->create();
    ChatThread::factory()->archived()->create();

    expect(ChatThread::query()->active()->pluck('id')->all())->toBe([$active->id]);
});

test('with messages scope excludes empty threads', function () {
    $withMessage = ChatThread::factory()->create();
    ChatMessage::factory()->create(['conversation_id' => $withMessage->id]);
    ChatThread::factory()->create();

    expect(ChatThread::query()->withMessages()->pluck('id')->all())->toBe([$withMessage->id]);
});

test('ordered scope puts pinned first then most recently updated', function () {
    $old = ChatThread::factory()->create(['updated_at' => now()->subDays(2)]);
    $recent = ChatThread::factory()->create(['updated_at' => now()->subDay()]);
    $pinned = ChatThread::factory()->pinned()->create(['updated_at' => now()->subWeek()]);

    expect(ChatThread::query()->ordered()->pluck('id')->all())
        ->toBe([$pinned->id, $recent->id, $old->id]);
});

test('search scope matches title substring', function () {
    $match = ChatThread::factory()->create(['title' => 'Rutina de hipertrofia']);
    ChatThread::factory()->create(['title' => 'Presupuesto mensual']);

    expect(ChatThread::query()->search('hiper')->pluck('id')->all())->toBe([$match->id]);
});

test('message citations are read from meta', function () {
    $message = ChatMessage::factory()
        ->assistant()
        ->withCitations([['url' => 'https://example.com', 'title' => 'Example', 'start_index' => 0, 'end_index' => 3]])
        ->create();

    expect($message->citations())->toHaveCount(1);
    expect($message->citations()[0]['url'])->toBe('https://example.com');
    expect($message->isUser())->toBeFalse();
});

test('message without citations returns empty array', function () {
    $message = ChatMessage::factory()->create();

    expect($message->citations())->toBe([]);
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --compact --filter=ChatModelTest`
Expected: FAIL (`Class "App\Models\ChatThread" not found`)

- [ ] **Step 3: Generar la migración y escribirla**

Run: `php artisan make:migration add_chat_thread_columns_to_agent_conversations_table --no-interaction`

```php
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    public function up(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->string('space_id', 36)->nullable()->index();
            $table->string('agent', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('mode', 20)->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->dropColumn(['space_id', 'agent', 'model', 'mode', 'pinned_at', 'archived_at']);
        });
    }
};
```

- [ ] **Step 4: Implementar los modelos**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Ai\Models\Conversation;

class ChatThread extends Conversation
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'pinned_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->where('participant_type', $user->getMorphClass())
            ->where('participant_id', $user->getKey());
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function scopeWithMessages(Builder $query): void
    {
        $query->whereHas('messages');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('pinned_at')->orderByDesc('updated_at');
    }

    public function scopeSearch(Builder $query, string $term): void
    {
        $query->where('title', 'like', '%'.addcslashes($term, '%_').'%');
    }

    public function belongsToUser(User $user): bool
    {
        return $this->participant_type === $user->getMorphClass()
            && (int) $this->participant_id === (int) $user->getKey();
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }
}
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Ai\Models\ConversationMessage;

class ChatMessage extends ConversationMessage
{
    use HasFactory;

    /**
     * @return array<int, array{url: string, title: ?string, start_index: ?int, end_index: ?int}>
     */
    public function citations(): array
    {
        return $this->meta['citations'] ?? [];
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }
}
```

- [ ] **Step 5: Implementar los factories**

```php
<?php

namespace Database\Factories;

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatThread>
 */
class ChatThreadFactory extends Factory
{
    protected $model = ChatThread::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'participant_type' => (new User)->getMorphClass(),
            'participant_id' => User::factory(),
            'title' => fake()->sentence(4),
            'agent' => 'megalomaniac',
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['pinned_at' => now()]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
```

```php
<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'conversation_id' => ChatThread::factory(),
            'participant_type' => (new User)->getMorphClass(),
            'participant_id' => User::factory(),
            'agent' => 'megalomaniac',
            'role' => 'user',
            'content' => fake()->paragraph(),
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => [],
        ];
    }

    public function assistant(): static
    {
        return $this->state(fn () => ['role' => 'assistant']);
    }

    public function withCitations(array $citations): static
    {
        return $this->state(fn () => ['meta' => ['citations' => $citations]]);
    }
}
```

- [ ] **Step 6: Correr el test (debe pasar)**

Run: `php artisan test --compact --filter=ChatModelTest`
Expected: 8 passed

- [ ] **Step 7: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*add_chat_thread_columns_to_agent_conversations_table.php app/Models/ChatThread.php app/Models/ChatMessage.php database/factories/ChatThreadFactory.php database/factories/ChatMessageFactory.php tests/Feature/Ai/ChatModelTest.php
git commit -m "feat(ai): add chat thread columns, models and factories"
```

---

### Task 2: `ChatService` + estado `withAiProvider` en `UserFactory`

**Files:**
- Create: `app/Ai/Services/ChatService.php`
- Modify: `database/factories/UserFactory.php` (añadir estado, al final de la clase)
- Test: `tests/Feature/Ai/ChatServiceTest.php`

**Interfaces:**
- Consumes: `ChatThread`, `ChatMessage`, factories (Task 1).
- Produces: `ChatService`: `isConfigured(User): bool`, `createThread(User, string, ?string $model = null): ChatThread`, `streamTurn(User, ChatThread, string, ?string $model = null): StreamableAgentResponse`, `regenerate(User, ChatThread): ?StreamableAgentResponse`, `dropLastExchange(ChatThread): ?string`, `editAndResend(User, ChatThread, string $messageId, string $content): ?StreamableAgentResponse`, `truncateFrom(ChatThread, ChatMessage): void`, `deleteThread(ChatThread): void`, `availableModels(User): array<int, string>`.
- Produces: `UserFactory::withAiProvider(string $model = 'test-model')` (usado por Tasks 3-5 y 9).

- [ ] **Step 1: Escribir el test**

```php
<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Services\ChatService;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('is configured requires enabled flag url and key', function () {
    $service = new ChatService;

    expect($service->isConfigured(User::factory()->create()))->toBeFalse();
    expect($service->isConfigured(User::factory()->withAiProvider()->create()))->toBeTrue();
    expect($service->isConfigured(User::factory()->withAiProvider()->create(['ai_enabled' => false])))->toBeFalse();
});

test('create thread stores participant title and agent', function () {
    $user = User::factory()->create();

    $thread = (new ChatService)->createThread($user, "  <b>Plan</b> de entrenamiento para la semana  ");

    expect($thread->belongsToUser($user))->toBeTrue();
    expect($thread->title)->toBe('Plan de entrenamiento para la semana');
    expect($thread->agent)->toBe('megalomaniac');
    expect($thread->model)->toBeNull();
});

test('create thread truncates long first messages', function () {
    $user = User::factory()->create();

    $thread = (new ChatService)->createThread($user, str_repeat('palabra ', 40));

    expect(strlen($thread->title))->toBeLessThanOrEqual(63);
});

test('drop last exchange removes assistant and user messages and returns text', function () {
    $thread = ChatThread::factory()->create();
    ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Pregunta']);
    ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id, 'content' => 'Respuesta']);

    $text = (new ChatService)->dropLastExchange($thread);

    expect($text)->toBe('Pregunta');
    expect($thread->messages()->count())->toBe(0);
});

test('drop last exchange returns null when nothing to drop', function () {
    $thread = ChatThread::factory()->create();

    expect((new ChatService)->dropLastExchange($thread))->toBeNull();
});

test('truncate from deletes the message and everything after it', function () {
    $thread = ChatThread::factory()->create();
    $first = ChatMessage::factory()->create(['conversation_id' => $thread->id]);
    ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id]);
    $third = ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    (new ChatService)->truncateFrom($thread, $first);

    expect($thread->messages()->pluck('id')->all())->toBe([]);
    expect(ChatMessage::query()->whereKey($third->id)->exists())->toBeFalse();
});

test('edit and resend rejects messages that are not user messages', function () {
    $thread = ChatThread::factory()->create();
    $assistant = ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id]);

    $result = (new ChatService)->editAndResend(
        User::factory()->withAiProvider()->create(),
        $thread,
        $assistant->id,
        'nuevo texto',
    );

    expect($result)->toBeNull();
});

test('available models returns endpoint list and caches it', function () {
    Http::fake([
        'api.example.com/v1/models' => Http::response([
            'data' => [['id' => 'model-a'], ['id' => 'model-b']],
        ]),
    ]);

    $user = User::factory()->withAiProvider()->create();
    $service = new ChatService;

    expect($service->availableModels($user))->toBe(['model-a', 'model-b']);
    expect($service->availableModels($user))->toBe(['model-a', 'model-b']);
    Http::assertSentCount(1);
});

test('available models falls back to configured model on failure', function () {
    Http::fake(['api.example.com/v1/models' => Http::response(null, 500)]);

    $user = User::factory()->withAiProvider('fallback-model')->create();

    expect((new ChatService)->availableModels($user))->toBe(['fallback-model']);
});

test('delete thread removes messages and thread', function () {
    $thread = ChatThread::factory()->create();
    ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    (new ChatService)->deleteThread($thread);

    expect(ChatThread::query()->whereKey($thread->id)->exists())->toBeFalse();
    expect(ChatMessage::query()->where('conversation_id', $thread->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --compact --filter=ChatServiceTest`
Expected: FAIL (`Class "App\Ai\Services\ChatService" not found`)

- [ ] **Step 3: Implementar el servicio**

```php
<?php

namespace App\Ai\Services;

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Support\AiProviderResolver;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StreamableAgentResponse;
use RuntimeException;
use Throwable;

class ChatService
{
    public function isConfigured(User $user): bool
    {
        return (bool) ($user->ai_enabled && $user->ai_provider_url && $user->ai_provider_key);
    }

    public function createThread(User $user, string $firstMessage, ?string $model = null): ChatThread
    {
        return ChatThread::create([
            'id' => (string) Str::uuid7(),
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->getKey(),
            'title' => Str::limit(trim(strip_tags($firstMessage)), 60, preserveWords: true),
            'agent' => 'megalomaniac',
            'model' => $model,
        ]);
    }

    public function streamTurn(User $user, ChatThread $thread, string $message, ?string $model = null): StreamableAgentResponse
    {
        if (! $this->isConfigured($user)) {
            throw new RuntimeException('El proveedor de IA no está configurado.');
        }

        [$provider, $defaultModel] = AiProviderResolver::for($user);

        return (new MegalomaniacAgent($user))
            ->continue($thread->id, as: $user)
            ->stream($message, provider: $provider, model: $model ?: $defaultModel);
    }

    public function regenerate(User $user, ChatThread $thread): ?StreamableAgentResponse
    {
        $content = $this->dropLastExchange($thread);

        return $content === null
            ? null
            : $this->streamTurn($user, $thread, $content, $thread->model);
    }

    public function dropLastExchange(ChatThread $thread): ?string
    {
        $last = $thread->messages()->orderByDesc('id')->first();

        if ($last?->role === 'assistant') {
            $last->delete();
        }

        $userMessage = $thread->messages()->orderByDesc('id')->first();

        if (! $userMessage instanceof ChatMessage || ! $userMessage->isUser()) {
            return null;
        }

        $content = $userMessage->content;
        $userMessage->delete();

        return $content;
    }

    public function editAndResend(User $user, ChatThread $thread, string $messageId, string $content): ?StreamableAgentResponse
    {
        $start = $thread->messages()->whereKey($messageId)->first();

        if (! $start instanceof ChatMessage || ! $start->isUser()) {
            return null;
        }

        $this->truncateFrom($thread, $start);

        return $this->streamTurn($user, $thread, $content, $thread->model);
    }

    public function truncateFrom(ChatThread $thread, ChatMessage $start): void
    {
        $messages = $thread->messages()->orderBy('id')->get();
        $index = $messages->search(fn (ChatMessage $message) => $message->id === $start->id);

        if ($index === false) {
            return;
        }

        $ids = $messages->slice($index)->pluck('id')->all();

        $thread->messages()->whereIn('id', $ids)->delete();
    }

    public function deleteThread(ChatThread $thread): void
    {
        DB::transaction(function () use ($thread): void {
            $thread->messages()->delete();
            $thread->delete();
        });
    }

    /**
     * @return array<int, string>
     */
    public function availableModels(User $user): array
    {
        $fallback = [$user->ai_model ?: 'gpt-4o-mini'];

        if (! $this->isConfigured($user)) {
            return $fallback;
        }

        return Cache::remember(
            "ai.models.{$user->getKey()}",
            now()->addMinutes(5),
            function () use ($user, $fallback): array {
                try {
                    $response = Http::withToken($user->ai_provider_key)
                        ->acceptJson()
                        ->timeout(5)
                        ->get(rtrim($user->ai_provider_url, '/').'/models');
                } catch (Throwable) {
                    return $fallback;
                }

                if (! $response->successful()) {
                    return $fallback;
                }

                $models = collect($response->json('data', []))
                    ->pluck('id')
                    ->filter(fn ($id) => is_string($id) && $id !== '')
                    ->unique()
                    ->values()
                    ->all();

                return $models === [] ? $fallback : $models;
            }
        );
    }
}
```

- [ ] **Step 4: Añadir el estado al `UserFactory`**

```php
    /**
     * Indicate that the user has a configured AI provider.
     */
    public function withAiProvider(string $model = 'test-model'): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_enabled' => true,
            'ai_provider_url' => 'https://api.example.com/v1',
            'ai_provider_key' => 'sk-test',
            'ai_model' => $model,
        ]);
    }
```

- [ ] **Step 5: Correr el test (debe pasar)**

Run: `php artisan test --compact --filter=ChatServiceTest`
Expected: 10 passed

- [ ] **Step 6: Verificar que no rompimos la suite de IA existente**

Run: `php artisan test --compact tests/Feature/Ai`
Expected: todos pasan

- [ ] **Step 7: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Ai/Services/ChatService.php database/factories/UserFactory.php tests/Feature/Ai/ChatServiceTest.php
git commit -m "feat(ai): add ChatService with thread, regenerate and model listing logic"
```

---

### Task 3: Policy + Requests + Resources + `ChatController@index/show` + rutas + páginas base

**Files:**
- Create: `app/Policies/ChatThreadPolicy.php`
- Create: `app/Http/Requests/Ai/SendChatMessageRequest.php`
- Create: `app/Http/Requests/Ai/EditChatMessageRequest.php`
- Create: `app/Http/Requests/Ai/UpdateChatThreadRequest.php`
- Create: `app/Http/Resources/ChatThreadResource.php`
- Create: `app/Http/Resources/ChatMessageResource.php`
- Create: `app/Http/Controllers/Ai/ChatController.php`
- Modify: `routes/web.php` (reemplazar líneas 5 y 177-178: import `AiChatController` → `Ai\ChatController`; rutas `ai/chat` y `ai/conversations`)
- Delete: `app/Http/Controllers/AiChatController.php`
- Create: `resources/js/types/chat.ts`
- Create: `resources/js/pages/ai/chat.tsx` (versión base; Task 7 la completa)
- Create: `resources/js/pages/ai/thread.tsx` (versión base; Task 8 la completa)
- Test: `tests/Feature/Ai/ChatIndexTest.php`
- Test: `tests/Feature/Ai/ChatThreadTest.php`

**Interfaces:**
- Consumes: `ChatService` (Task 2), `ChatThread`, `ChatMessage` (Task 1).
- Produces: rutas `ai.chat.index` (GET `/ai/chat`), `ai.chat.show` (GET `/ai/chat/{thread}`), `ai.chat.update` (PATCH), `ai.chat.destroy` (DELETE). Las rutas SSE (`ai.chat.send`, `ai.chat.regenerate`, `ai.chat.edit`) y `ai.models` llegan en Tasks 4-5.
- Produces: `ChatThreadResource` (`id`, `title`, `model`, `is_pinned`, `created_at`, `updated_at`), `ChatMessageResource` (`id`, `role`, `content`, `citations`, `created_at`).
- Produces: props Inertia: `threads`, `models`, `ai` (`enabled`, `configured`, `defaultModel`) y en `show` además `thread`, `messages`.
- Produces (frontend): `@/types/chat` con `ChatThread`, `ChatMessage`, `Citation`, `AiChatState`.

- [ ] **Step 1: Escribir los tests**

`tests/Feature/Ai/ChatIndexTest.php`:

```php
<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('chat index lists only own non empty threads ordered by pinned and recency', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
        'title' => 'Mío',
    ]);
    ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    $emptyThread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    ChatThread::factory()->create();

    $this->actingAs($user)
        ->get(route('ai.chat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/chat')
            ->has('threads', 1)
            ->where('threads.0.id', $thread->id)
            ->where('threads.0.title', 'Mío')
            ->where('ai.configured', true)
            ->has('models')
        );
});

test('chat index flags unconfigured provider', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ai.chat.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/chat')
            ->where('ai.configured', false)
        );
});
```

`tests/Feature/Ai/ChatThreadTest.php`:

```php
<?php

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function ownThread(User $user): ChatThread
{
    return ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
}

test('thread page renders own messages with citations', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ownThread($user);
    $userMessage = ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Hola']);
    ChatMessage::factory()->assistant()->withCitations([
        ['url' => 'https://laravel.com', 'title' => 'Laravel', 'start_index' => null, 'end_index' => null],
    ])->create(['conversation_id' => $thread->id, 'content' => 'Qué tal']);

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ai/thread')
            ->where('thread.id', $thread->id)
            ->has('messages', 2)
            ->where('messages.0.content', 'Hola')
            ->where('messages.1.citations.0.url', 'https://laravel.com')
        );
});

test('thread page returns 404 for another users thread', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ownThread(User::factory()->create());

    $this->actingAs($user)
        ->get(route('ai.chat.show', $thread))
        ->assertNotFound();
});

test('thread can be renamed and pinned', function () {
    $user = User::factory()->create();
    $thread = ownThread($user);

    $this->actingAs($user)
        ->patch(route('ai.chat.update', $thread), ['title' => 'Nuevo título', 'pinned' => true])
        ->assertRedirect();

    $thread->refresh();

    expect($thread->title)->toBe('Nuevo título');
    expect($thread->isPinned())->toBeTrue();
});

test('thread update validates title length', function () {
    $user = User::factory()->create();
    $thread = ownThread($user);

    $this->actingAs($user)
        ->patch(route('ai.chat.update', $thread), ['title' => str_repeat('a', 121)])
        ->assertSessionHasErrors('title');
});

test('another user cannot update or delete a thread', function () {
    $user = User::factory()->create();
    $thread = ownThread(User::factory()->create());

    $this->actingAs($user)
        ->patch(route('ai.chat.update', $thread), ['title' => 'Hack'])
        ->assertForbidden();

    $this->actingAs($user)
        ->delete(route('ai.chat.destroy', $thread))
        ->assertForbidden();
});

test('thread can be deleted with its messages', function () {
    $user = User::factory()->create();
    $thread = ownThread($user);
    ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    $this->actingAs($user)
        ->delete(route('ai.chat.destroy', $thread))
        ->assertRedirect(route('ai.chat.index'));

    expect(ChatThread::query()->whereKey($thread->id)->exists())->toBeFalse();
    expect(ChatMessage::query()->where('conversation_id', $thread->id)->exists())->toBeFalse();
});
```

- [ ] **Step 2: Correr los tests (deben fallar)**

Run: `php artisan test --compact --filter=ChatThreadTest`
Expected: FAIL (route `ai.chat.show` not defined)

- [ ] **Step 3: Implementar Policy y Requests**

```php
<?php

namespace App\Policies;

use App\Models\ChatThread;
use App\Models\User;

class ChatThreadPolicy
{
    public function view(User $user, ChatThread $thread): bool
    {
        return $thread->belongsToUser($user);
    }

    public function update(User $user, ChatThread $thread): bool
    {
        return $thread->belongsToUser($user);
    }

    public function delete(User $user, ChatThread $thread): bool
    {
        return $thread->belongsToUser($user);
    }
}
```

```php
<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class SendChatMessageRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:4000'],
            'thread_id' => ['nullable', 'string', 'size:36'],
            'model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class EditChatMessageRequest extends FormRequest
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
            'message_id' => ['required', 'string', 'size:36'],
            'content' => ['required', 'string', 'max:4000'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChatThreadRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:120'],
            'pinned' => ['sometimes', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Implementar Resources**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatThreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'model' => $this->model,
            'is_pinned' => $this->pinned_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'citations' => $this->citations(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5: Implementar el controlador (index/show/update/destroy)**

```php
<?php

namespace App\Http\Controllers\Ai;

use App\Ai\Services\ChatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\UpdateChatThreadRequest;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatThreadResource;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(protected ChatService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('ai/chat', [
            'threads' => ChatThreadResource::collection($this->threadsFor($user)),
            'models' => $this->service->availableModels($user),
            'ai' => $this->aiState($user),
        ]);
    }

    public function show(Request $request, ChatThread $thread): Response
    {
        $this->authorize('view', $thread);

        $user = $request->user();

        return Inertia::render('ai/thread', [
            'thread' => new ChatThreadResource($thread),
            'messages' => ChatMessageResource::collection(
                $thread->messages()->orderBy('id')->get()
            ),
            'threads' => ChatThreadResource::collection($this->threadsFor($user)),
            'models' => $this->service->availableModels($user),
            'ai' => $this->aiState($user),
        ]);
    }

    public function update(UpdateChatThreadRequest $request, ChatThread $thread): RedirectResponse
    {
        $this->authorize('update', $thread);

        $data = $request->validated();

        if (array_key_exists('title', $data)) {
            $thread->title = $data['title'];
        }

        if (array_key_exists('pinned', $data)) {
            $thread->pinned_at = $data['pinned'] ? now() : null;
        }

        $thread->save();

        return back();
    }

    public function destroy(Request $request, ChatThread $thread): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $this->service->deleteThread($thread);

        return to_route('ai.chat.index');
    }

    /**
     * @return Collection<int, ChatThread>
     */
    protected function threadsFor(User $user): Collection
    {
        return ChatThread::query()
            ->forUser($user)
            ->active()
            ->withMessages()
            ->ordered()
            ->limit(100)
            ->get();
    }

    /**
     * @return array{enabled: bool, configured: bool, defaultModel: ?string}
     */
    protected function aiState(User $user): array
    {
        return [
            'enabled' => (bool) $user->ai_enabled,
            'configured' => $this->service->isConfigured($user),
            'defaultModel' => $user->ai_model ?: null,
        ];
    }
}
```

- [ ] **Step 6: Actualizar rutas y borrar el controlador viejo**

En `routes/web.php`, sustituir el import `use App\Http\Controllers\AiChatController;` por `use App\Http\Controllers\Ai\ChatController;` y reemplazar las dos líneas de rutas del chat por:

```php
    // AI Chat
    Route::get('ai/chat', [ChatController::class, 'index'])->name('ai.chat.index');
    Route::get('ai/chat/{thread}', [ChatController::class, 'show'])->name('ai.chat.show');
    Route::patch('ai/chat/{thread}', [ChatController::class, 'update'])->name('ai.chat.update');
    Route::delete('ai/chat/{thread}', [ChatController::class, 'destroy'])->name('ai.chat.destroy');
```

Run: `rm app/Http/Controllers/AiChatController.php`
Run: `php artisan wayfinder:generate --with-form`

- [ ] **Step 7: Crear tipos y páginas base**

`resources/js/types/chat.ts`:

```ts
export interface ChatThread {
    id: string;
    title: string;
    model: string | null;
    is_pinned: boolean;
    created_at: string | null;
    updated_at: string | null;
}

export interface Citation {
    url: string;
    title: string | null;
    start_index?: number | null;
    end_index?: number | null;
}

export interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    citations: Citation[];
    created_at: string | null;
}

export interface AiChatState {
    enabled: boolean;
    configured: boolean;
    defaultModel: string | null;
}

export interface ToolActivity {
    id: string;
    name: string;
    status: 'running' | 'done' | 'failed';
}
```

`resources/js/pages/ai/chat.tsx` (base — Task 7 la completa):

```tsx
import { Head, Link } from '@inertiajs/react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import MainLayout from '@/layouts/main-layout';
import type { AiChatState, ChatThread } from '@/types/chat';

interface ChatIndexProps {
    threads: ChatThread[];
    models: string[];
    ai: AiChatState;
}

export default function ChatIndex({ threads, ai }: ChatIndexProps) {
    return (
        <MainLayout>
            <Head title="Chat IA" />
            <div className="mx-auto w-full max-w-3xl p-6">
                <h1 className="text-2xl font-black tracking-tight text-foreground">Chat IA</h1>

                {!ai.configured && (
                    <p className="mt-2 text-sm text-muted-foreground">
                        Configura tu proveedor de IA en{' '}
                        <Link href="/settings/ai" className="text-primary hover:underline">
                            Settings → IA
                        </Link>
                        .
                    </p>
                )}

                <ul className="mt-6 space-y-1">
                    {threads.map((thread) => (
                        <li key={thread.id}>
                            <Link
                                href={ChatController.show(thread.id).url}
                                className="block truncate rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground transition-colors hover:border-primary/40"
                            >
                                {thread.title}
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </MainLayout>
    );
}
```

`resources/js/pages/ai/thread.tsx` (base — Task 8 la completa):

```tsx
import { Head } from '@inertiajs/react';
import MainLayout from '@/layouts/main-layout';
import type { AiChatState, ChatMessage, ChatThread } from '@/types/chat';

interface ChatThreadProps {
    thread: ChatThread;
    messages: ChatMessage[];
    threads: ChatThread[];
    models: string[];
    ai: AiChatState;
}

export default function ChatThread({ thread, messages }: ChatThreadProps) {
    return (
        <MainLayout>
            <Head title={thread.title} />
            <div className="mx-auto w-full max-w-3xl p-6">
                <h1 className="truncate text-xl font-black tracking-tight text-foreground">{thread.title}</h1>

                <div className="mt-6 space-y-4">
                    {messages.map((message) => (
                        <div key={message.id} className="rounded-xl border border-border bg-card p-4">
                            <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                {message.role}
                            </p>
                            <p className="mt-1 whitespace-pre-wrap text-sm text-foreground">{message.content}</p>
                        </div>
                    ))}
                </div>
            </div>
        </MainLayout>
    );
}
```

- [ ] **Step 8: Correr los tests (deben pasar) y verificar frontend**

Run: `php artisan test --compact --filter=Chat`
Expected: ChatModelTest 8 passed, ChatServiceTest 10 passed, ChatIndexTest 2 passed, ChatThreadTest 7 passed

Run: `npm run types`
Expected: sin errores nuevos

- [ ] **Step 9: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/ChatThreadPolicy.php app/Http/Requests/Ai app/Http/Resources/ChatThreadResource.php app/Http/Resources/ChatMessageResource.php app/Http/Controllers/Ai/ChatController.php routes/web.php resources/js/types/chat.ts resources/js/pages/ai/chat.tsx resources/js/pages/ai/thread.tsx resources/js/actions resources/js/routes tests/Feature/Ai/ChatIndexTest.php tests/Feature/Ai/ChatThreadTest.php
git rm --cached app/Http/Controllers/AiChatController.php 2>/dev/null || true
git add -u app/Http/Controllers/AiChatController.php
git commit -m "feat(ai): add chat thread pages, policy, requests and resources"
```

---

### Task 4: Streaming — `send` / `regenerate` / `edit` (SSE envuelto)

**Files:**
- Modify: `app/Http/Controllers/Ai/ChatController.php` (añadir `send`, `regenerate`, `edit`, `streamResponse`)
- Modify: `routes/web.php` (añadir las 3 rutas SSE)
- Test: `tests/Feature/Ai/ChatStreamTest.php`
- Test: `tests/Feature/Ai/ChatRegenerateTest.php`
- Test: `tests/Feature/Ai/ChatEditTest.php`

**Interfaces:**
- Consumes: `ChatService` (Task 2), `SendChatMessageRequest`, `EditChatMessageRequest` (Task 3).
- Produces: rutas `ai.chat.send` (POST `/ai/chat`), `ai.chat.regenerate` (POST `/ai/chat/{thread}/regenerate`), `ai.chat.edit` (POST `/ai/chat/{thread}/edit`).
- Produces: contrato SSE: primer evento `{"type":"thread","threadId":"…"}`, luego eventos del SDK serializados, cierre `data: [DONE]`.

- [ ] **Step 1: Escribir los tests**

`tests/Feature/Ai/ChatStreamTest.php`:

```php
<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sending a message creates a thread and streams the reply', function () {
    MegalomaniacAgent::fake(['Hola mundo']);

    $user = User::factory()->withAiProvider()->create();

    $response = $this->actingAs($user)->post(route('ai.chat.send'), ['message' => '¿Qué tal?']);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('"type":"thread"');
    expect($content)->toContain('"type":"text_delta"');
    expect($content)->toContain('Hola mundo');
    expect($content)->toContain('[DONE]');

    $thread = ChatThread::query()->forUser($user)->first();

    expect($thread)->not->toBeNull();
    expect($thread->title)->toBe('¿Qué tal?');
    expect($thread->agent)->toBe('megalomaniac');
    expect($thread->messages()->count())->toBe(2);
});

test('sending to an existing thread continues it and stores the model override', function () {
    MegalomaniacAgent::fake(['Seguimos']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $response = $this->actingAs($user)->post(route('ai.chat.send'), [
        'message' => 'Continúa',
        'thread_id' => $thread->id,
        'model' => 'model-elegido',
    ]);

    $response->assertOk();
    $content = $response->streamedContent();

    expect($content)->toContain('"threadId":"'.$thread->id.'"');
    expect($thread->refresh()->model)->toBe('model-elegido');
    expect($thread->messages()->count())->toBe(2);
    expect($thread->fresh()->title)->toBe($thread->title);
});

test('sending to another users thread returns 404', function () {
    MegalomaniacAgent::fake(['No debería']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create();

    $this->actingAs($user)
        ->post(route('ai.chat.send'), ['message' => 'Hola', 'thread_id' => $thread->id])
        ->assertNotFound();

    MegalomaniacAgent::assertNeverPrompted();
});

test('sending without configured provider returns 422 json', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => 'Hola'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Configura tu proveedor de IA en Settings → IA.']);

    expect(ChatThread::query()->count())->toBe(0);
});

test('message is required and limited', function () {
    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');

    $this->actingAs($user)
        ->postJson(route('ai.chat.send'), ['message' => str_repeat('a', 4001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
});
```

`tests/Feature/Ai/ChatRegenerateTest.php`:

```php
<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('regenerate drops the last exchange and streams a new reply', function () {
    MegalomaniacAgent::fake(['Respuesta nueva']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Pregunta original']);
    ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id, 'content' => 'Respuesta vieja']);

    $response = $this->actingAs($user)->post(route('ai.chat.regenerate', $thread));

    $response->assertOk();
    expect($response->streamedContent())->toContain('Respuesta nueva');

    $messages = $thread->messages()->orderBy('id')->get();

    expect($messages)->toHaveCount(2);
    expect($messages[0]->content)->toBe('Pregunta original');
    expect($messages[1]->content)->toBe('Respuesta nueva');
});

test('regenerate without exchange returns 422', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);

    $this->actingAs($user)
        ->postJson(route('ai.chat.regenerate', $thread))
        ->assertStatus(422);
});
```

`tests/Feature/Ai/ChatEditTest.php`:

```php
<?php

use App\Ai\Agents\MegalomaniacAgent;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('editing a user message truncates from it and streams the new reply', function () {
    MegalomaniacAgent::fake(['Respuesta editada']);

    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    $first = ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Primera']);
    ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id, 'content' => 'Vieja']);
    $edited = ChatMessage::factory()->create(['conversation_id' => $thread->id, 'content' => 'Segunda']);

    $response = $this->actingAs($user)->post(route('ai.chat.edit', $thread), [
        'message_id' => $edited->id,
        'content' => 'Segunda corregida',
    ]);

    $response->assertOk();
    expect($response->streamedContent())->toContain('Respuesta editada');

    $messages = $thread->messages()->orderBy('id')->get()->pluck('content')->all();

    expect($messages)->toBe(['Primera', 'Vieja', 'Segunda corregida', 'Respuesta editada']);
});

test('editing an assistant message returns 422', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create([
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->getKey(),
    ]);
    $assistant = ChatMessage::factory()->assistant()->create(['conversation_id' => $thread->id]);

    $this->actingAs($user)
        ->postJson(route('ai.chat.edit', $thread), ['message_id' => $assistant->id, 'content' => 'x'])
        ->assertStatus(422);
});

test('editing another users thread returns 403', function () {
    $user = User::factory()->withAiProvider()->create();
    $thread = ChatThread::factory()->create();
    $message = ChatMessage::factory()->create(['conversation_id' => $thread->id]);

    $this->actingAs($user)
        ->postJson(route('ai.chat.edit', $thread), ['message_id' => $message->id, 'content' => 'x'])
        ->assertForbidden();
});
```

- [ ] **Step 2: Correr los tests (deben fallar)**

Run: `php artisan test --compact --filter=ChatStreamTest`
Expected: FAIL (route `ai.chat.send` not defined)

- [ ] **Step 3: Añadir los métodos al controlador**

Añadir a `ChatController` los siguientes métodos y los imports que falten (`App\Http\Requests\Ai\EditChatMessageRequest`, `App\Http\Requests\Ai\SendChatMessageRequest`, `Illuminate\Http\JsonResponse`, `Symfony\Component\HttpFoundation\StreamedResponse`, `Laravel\Ai\Responses\StreamableAgentResponse`):

```php
    public function send(SendChatMessageRequest $request): StreamedResponse
    {
        $user = $request->user();

        abort_unless(
            $this->service->isConfigured($user),
            422,
            'Configura tu proveedor de IA en Settings → IA.'
        );

        $threadId = $request->validated('thread_id');
        $model = $request->validated('model');

        if ($threadId !== null) {
            $thread = ChatThread::query()->forUser($user)->find($threadId);

            abort_if($thread === null, 404);

            $this->authorize('update', $thread);
        } else {
            $thread = $this->service->createThread($user, $request->validated('message'), $model);
        }

        if ($model !== null) {
            $thread->update(['model' => $model]);
        }

        return $this->streamResponse(
            $this->service->streamTurn($user, $thread, $request->validated('message'), $model ?? $thread->model),
            $thread,
        );
    }

    public function regenerate(Request $request, ChatThread $thread): StreamedResponse
    {
        $this->authorize('update', $thread);

        abort_unless(
            $this->service->isConfigured($request->user()),
            422,
            'Configura tu proveedor de IA en Settings → IA.'
        );

        $stream = $this->service->regenerate($request->user(), $thread);

        abort_if($stream === null, 422, 'No hay ningún intercambio que regenerar.');

        return $this->streamResponse($stream, $thread);
    }

    public function edit(EditChatMessageRequest $request, ChatThread $thread): StreamedResponse
    {
        $this->authorize('update', $thread);

        abort_unless(
            $this->service->isConfigured($request->user()),
            422,
            'Configura tu proveedor de IA en Settings → IA.'
        );

        $stream = $this->service->editAndResend(
            $request->user(),
            $thread,
            $request->validated('message_id'),
            $request->validated('content'),
        );

        abort_if($stream === null, 422, 'El mensaje indicado no se puede editar.');

        return $this->streamResponse($stream, $thread);
    }

    protected function streamResponse(StreamableAgentResponse $stream, ChatThread $thread): StreamedResponse
    {
        return response()->stream(function () use ($stream, $thread): void {
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
            }

            echo 'data: '.json_encode(['type' => 'thread', 'threadId' => $thread->id])."\n\n";
            flush();

            foreach ($stream as $event) {
                echo 'data: '.((string) $event)."\n\n";
                flush();
            }

            echo "data: [DONE]\n\n";
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
```

- [ ] **Step 4: Añadir las rutas SSE**

En `routes/web.php`, dentro del mismo bloque que las rutas del chat de la Task 3:

```php
    Route::post('ai/chat', [ChatController::class, 'send'])->middleware('throttle:30,1')->name('ai.chat.send');
    Route::post('ai/chat/{thread}/regenerate', [ChatController::class, 'regenerate'])->middleware('throttle:30,1')->name('ai.chat.regenerate');
    Route::post('ai/chat/{thread}/edit', [ChatController::class, 'edit'])->middleware('throttle:30,1')->name('ai.chat.edit');
```

- [ ] **Step 5: Correr los tests (deben pasar)**

Run: `php artisan test --compact --filter=ChatStreamTest`
Expected: 5 passed

Run: `php artisan test --compact --filter=ChatRegenerateTest`
Expected: 2 passed

Run: `php artisan test --compact --filter=ChatEditTest`
Expected: 3 passed

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Ai/ChatController.php routes/web.php tests/Feature/Ai/ChatStreamTest.php tests/Feature/Ai/ChatRegenerateTest.php tests/Feature/Ai/ChatEditTest.php
git commit -m "feat(ai): stream chat turns with regenerate and edit endpoints"
```

---

### Task 5: `GET ai/models` con refresco

**Files:**
- Modify: `app/Http/Controllers/Ai/ChatController.php` (añadir `models`)
- Modify: `routes/web.php` (añadir ruta)
- Test: `tests/Feature/Ai/ChatModelsTest.php`

**Interfaces:**
- Consumes: `ChatService::availableModels` (Task 2).
- Produces: ruta `ai.models` (GET `/ai/models`), JSON `{"models": string[]}`, query `?refresh=1` invalida cache.

- [ ] **Step 1: Escribir el test**

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('models endpoint returns provider models as json', function () {
    Http::fake([
        'api.example.com/v1/models' => Http::response([
            'data' => [['id' => 'model-a'], ['id' => 'model-b']],
        ]),
    ]);

    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)
        ->getJson(route('ai.models'))
        ->assertOk()
        ->assertJson(['models' => ['model-a', 'model-b']]);
});

test('models endpoint falls back to configured model', function () {
    Http::fake(['api.example.com/v1/models' => Http::response(null, 500)]);

    $user = User::factory()->withAiProvider('solo-este')->create();

    $this->actingAs($user)
        ->getJson(route('ai.models'))
        ->assertOk()
        ->assertJson(['models' => ['solo-este']]);
});

test('refresh query param bypasses the cache', function () {
    Http::fake([
        'api.example.com/v1/models' => Http::sequence()
            ->push(['data' => [['id' => 'viejo']]])
            ->push(['data' => [['id' => 'nuevo']]]),
    ]);

    $user = User::factory()->withAiProvider()->create();

    $this->actingAs($user)->getJson(route('ai.models'))->assertJson(['models' => ['viejo']]);
    $this->actingAs($user)->getJson(route('ai.models'))->assertJson(['models' => ['viejo']]);
    $this->actingAs($user)->getJson(route('ai.models', ['refresh' => 1]))->assertJson(['models' => ['nuevo']]);

    Http::assertSentCount(2);
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --compact --filter=ChatModelsTest`
Expected: FAIL (route `ai.models` not defined)

- [ ] **Step 3: Implementar el endpoint**

Añadir a `ChatController`:

```php
    public function models(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->boolean('refresh')) {
            Cache::forget("ai.models.{$user->getKey()}");
        }

        return response()->json([
            'models' => $this->service->availableModels($user),
        ]);
    }
```

Import añadido: `Illuminate\Support\Facades\Cache`.

Y en `routes/web.php`:

```php
    Route::get('ai/models', [ChatController::class, 'models'])->name('ai.models');
```

- [ ] **Step 4: Correr el test (debe pasar)**

Run: `php artisan test --compact --filter=ChatModelsTest`
Expected: 3 passed

- [ ] **Step 5: Regenerar Wayfinder, Pint y commit**

```bash
php artisan wayfinder:generate --with-form
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Ai/ChatController.php routes/web.php resources/js/actions resources/js/routes tests/Feature/Ai/ChatModelsTest.php
git commit -m "feat(ai): expose model list endpoint with cache refresh"
```

---

### Task 6: Frontend base — deps, parser SSE, hook de stream, fechas, markdown y citas

**Files:**
- Modify: `package.json`, `package-lock.json` (deps nuevas)
- Create: `resources/js/lib/chat-sse.ts`
- Create: `resources/js/hooks/use-chat-stream.ts`
- Create: `resources/js/lib/relative-date.ts`
- Create: `resources/js/lib/remark-citations.ts`
- Create: `resources/js/components/ai/chat/Markdown.tsx`
- Create: `resources/js/components/ai/chat/CodeBlock.tsx`
- Create: `resources/js/components/ai/chat/CitationChip.tsx`
- Create: `resources/js/components/ai/chat/SourcesPanel.tsx`
- Create: `resources/js/components/ai/chat/StreamStatus.tsx`
- Modify: `resources/css/app.css` (estilos `hljs` con tokens Ember, al final)

**Interfaces:**
- Consumes: `@/types/chat` (Task 3).
- Produces: `streamChatRequest(url, body, handlers, signal): Promise<void>` y `ChatStreamHandlers`; `useChatStream(options)` con `{status, text, citations, tools, error, start, stop, reset, clearError}`; `threadGroup()`/`THREAD_GROUP_ORDER`; `remarkCitations`; componentes `Markdown`, `CodeBlock`, `CitationChip`, `SourcesPanel({citations, activeIndex})`, `StreamStatus({thinking, tools})`.
- Consumido por Tasks 7-8.

- [ ] **Step 1: Instalar dependencias**

```bash
npm install react-markdown remark-gfm rehype-highlight
```

- [ ] **Step 2: Crear `lib/chat-sse.ts`**

```ts
import type { Citation } from '@/types/chat';

export interface ChatStreamHandlers {
    onThread?: (threadId: string) => void;
    onTextDelta?: (delta: string) => void;
    onCitation?: (citation: Citation) => void;
    onToolCall?: (tool: { id: string; name: string }) => void;
    onToolResult?: (tool: { id: string; name: string; successful: boolean }) => void;
    onError?: (message: string) => void;
}

interface StreamEvent {
    type?: string;
    threadId?: string;
    delta?: string;
    citation?: { url?: string; title?: string | null };
    tool_id?: string;
    tool_name?: string;
    successful?: boolean;
    message?: string;
}

function readCookie(name: string): string {
    const match = document.cookie.split('; ').find((cookie) => cookie.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.split('=').slice(1).join('=')) : '';
}

function dispatch(event: StreamEvent, handlers: ChatStreamHandlers): void {
    switch (event.type) {
        case 'thread':
            if (event.threadId) handlers.onThread?.(event.threadId);
            break;
        case 'text_delta':
            if (event.delta) handlers.onTextDelta?.(event.delta);
            break;
        case 'citation':
            if (event.citation?.url) {
                handlers.onCitation?.({
                    url: event.citation.url,
                    title: event.citation.title ?? null,
                });
            }
            break;
        case 'tool_call':
            if (event.tool_id) handlers.onToolCall?.({ id: event.tool_id, name: event.tool_name ?? 'tool' });
            break;
        case 'tool_result':
            if (event.tool_id) {
                handlers.onToolResult?.({
                    id: event.tool_id,
                    name: event.tool_name ?? 'tool',
                    successful: event.successful !== false,
                });
            }
            break;
        case 'error':
            handlers.onError?.(event.message ?? 'La generación falló.');
            break;
        default:
            break;
    }
}

export async function streamChatRequest(
    url: string,
    body: Record<string, unknown>,
    handlers: ChatStreamHandlers,
    signal?: AbortSignal,
): Promise<void> {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
        },
        body: JSON.stringify(body),
        signal,
    });

    if (!response.ok) {
        let message = 'No se pudo iniciar la respuesta.';

        try {
            const payload = await response.json();
            message = payload.message ?? message;
        } catch {
            // respuesta sin cuerpo JSON
        }

        throw new Error(message);
    }

    const reader = response.body?.getReader();

    if (!reader) {
        throw new Error('Tu navegador no soporta streaming.');
    }

    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { done, value } = await reader.read();

        if (done) break;

        buffer += decoder.decode(value, { stream: true });

        const parts = buffer.split('\n\n');
        buffer = parts.pop() ?? '';

        for (const part of parts) {
            const line = part.trim();

            if (!line.startsWith('data:')) continue;

            const payload = line.slice(5).trim();

            if (payload === '') continue;

            if (payload === '[DONE]') return;

            try {
                dispatch(JSON.parse(payload) as StreamEvent, handlers);
            } catch {
                // línea no JSON
            }
        }
    }
}
```

- [ ] **Step 3: Crear `hooks/use-chat-stream.ts`**

```ts
import { useCallback, useEffect, useRef, useState } from 'react';
import { streamChatRequest } from '@/lib/chat-sse';
import type { Citation, ToolActivity } from '@/types/chat';

export type ChatStreamStatus = 'idle' | 'streaming' | 'error';

interface UseChatStreamOptions {
    onThread?: (threadId: string) => void;
    onComplete?: (result: { text: string; citations: Citation[] }) => void;
}

export interface UseChatStreamResult {
    status: ChatStreamStatus;
    text: string;
    citations: Citation[];
    tools: ToolActivity[];
    error: string | null;
    start: (url: string, body: Record<string, unknown>) => Promise<void>;
    stop: () => void;
    reset: () => void;
    clearError: () => void;
}

export function useChatStream(options: UseChatStreamOptions = {}): UseChatStreamResult {
    const [status, setStatus] = useState<ChatStreamStatus>('idle');
    const [text, setText] = useState('');
    const [citations, setCitations] = useState<Citation[]>([]);
    const [tools, setTools] = useState<ToolActivity[]>([]);
    const [error, setError] = useState<string | null>(null);

    const abortRef = useRef<AbortController | null>(null);
    const optionsRef = useRef(options);
    const textRef = useRef('');
    const citationsRef = useRef<Citation[]>([]);

    useEffect(() => {
        optionsRef.current = options;
    });

    const stop = useCallback(() => {
        abortRef.current?.abort();
        abortRef.current = null;
        setStatus('idle');
    }, []);

    const reset = useCallback(() => {
        textRef.current = '';
        citationsRef.current = [];
        setText('');
        setCitations([]);
        setTools([]);
        setError(null);
        setStatus('idle');
    }, []);

    const clearError = useCallback(() => setError(null), []);

    const start = useCallback(async (url: string, body: Record<string, unknown>) => {
        const controller = new AbortController();
        abortRef.current = controller;

        textRef.current = '';
        citationsRef.current = [];

        setText('');
        setCitations([]);
        setTools([]);
        setError(null);
        setStatus('streaming');

        try {
            await streamChatRequest(
                url,
                body,
                {
                    onThread: (threadId) => optionsRef.current.onThread?.(threadId),
                    onTextDelta: (delta) => {
                        textRef.current += delta;
                        setText(textRef.current);
                    },
                    onCitation: (citation) => {
                        if (citationsRef.current.some((existing) => existing.url === citation.url)) return;

                        citationsRef.current = [...citationsRef.current, citation];
                        setCitations(citationsRef.current);
                    },
                    onToolCall: (tool) => {
                        setTools((previous) => [...previous, { ...tool, status: 'running' }]);
                    },
                    onToolResult: (result) => {
                        setTools((previous) =>
                            previous.map((tool) =>
                                tool.id === result.id
                                    ? { ...tool, status: result.successful ? 'done' : 'failed' }
                                    : tool,
                            ),
                        );
                    },
                    onError: (message) => setError(message),
                },
                controller.signal,
            );

            abortRef.current = null;
            setStatus('idle');
            optionsRef.current.onComplete?.({ text: textRef.current, citations: citationsRef.current });
        } catch (caught) {
            abortRef.current = null;

            if (caught instanceof DOMException && caught.name === 'AbortError') {
                setStatus('idle');
                return;
            }

            setError(caught instanceof Error ? caught.message : 'La generación falló.');
            setStatus('error');
        }
    }, []);

    return { status, text, citations, tools, error, start, stop, reset, clearError };
}
```

- [ ] **Step 4: Crear `lib/relative-date.ts` y `lib/remark-citations.ts`**

```ts
// relative-date.ts
export type ThreadGroup = 'Fijados' | 'Hoy' | 'Ayer' | 'Últimos 7 días' | 'Anteriores';

export const THREAD_GROUP_ORDER: ThreadGroup[] = ['Fijados', 'Hoy', 'Ayer', 'Últimos 7 días', 'Anteriores'];

export function threadGroup(updatedAt: string | null, isPinned: boolean): ThreadGroup {
    if (isPinned) return 'Fijados';

    if (!updatedAt) return 'Anteriores';

    const date = new Date(updatedAt);
    const now = new Date();
    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const startOfYesterday = new Date(startOfToday.getTime() - 86_400_000);
    const weekAgo = new Date(startOfToday.getTime() - 7 * 86_400_000);

    if (date >= startOfToday) return 'Hoy';
    if (date >= startOfYesterday) return 'Ayer';
    if (date >= weekAgo) return 'Últimos 7 días';

    return 'Anteriores';
}
```

```ts
// remark-citations.ts
import type { Plugin } from 'unified';

interface MarkdownNode {
    type: string;
    value?: string;
    url?: string;
    children?: MarkdownNode[];
}

function citationNodes(value: string, maxIndex: number): MarkdownNode[] {
    const nodes: MarkdownNode[] = [];
    const pattern = /\[(\d+)\]/g;
    let lastIndex = 0;

    for (const match of value.matchAll(pattern)) {
        const index = Number(match[1]);
        const position = match.index ?? 0;

        if (index < 1 || index > maxIndex) continue;

        if (position > lastIndex) {
            nodes.push({ type: 'text', value: value.slice(lastIndex, position) });
        }

        nodes.push({
            type: 'link',
            url: `#cite-${index}`,
            children: [{ type: 'text', value: match[0] }],
        });

        lastIndex = position + match[0].length;
    }

    if (lastIndex < value.length) {
        nodes.push({ type: 'text', value: value.slice(lastIndex) });
    }

    return nodes;
}

function walk(node: MarkdownNode, maxIndex: number): void {
    if (!node.children) return;

    const next: MarkdownNode[] = [];

    for (const child of node.children) {
        if (child.type === 'text' && typeof child.value === 'string' && /\[\d+\]/.test(child.value)) {
            next.push(...citationNodes(child.value, maxIndex));
        } else {
            walk(child, maxIndex);
            next.push(child);
        }
    }

    node.children = next;
}

export const remarkCitations: Plugin<[{ max: number }]> = (options) => (tree) => {
    walk(tree as unknown as MarkdownNode, options.max);
};
```

- [ ] **Step 5: Crear los componentes de render**

`CodeBlock.tsx`:

```tsx
import { useRef, useState, type ReactNode } from 'react';
import { Check, Copy } from 'lucide-react';
import { cn } from '@/lib/utils';

export function CodeBlock({ children, className }: { children: ReactNode; className?: string }) {
    const preRef = useRef<HTMLPreElement>(null);
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        const text = preRef.current?.textContent ?? '';

        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // clipboard no disponible
        }
    };

    return (
        <div className={cn('group relative my-3 overflow-hidden rounded-xl border border-border bg-background', className)}>
            <button
                type="button"
                onClick={copy}
                aria-label="Copiar código"
                className="absolute right-2 top-2 z-10 rounded-md border border-border bg-card p-1.5 text-muted-foreground opacity-0 transition-opacity hover:text-foreground focus-visible:opacity-100 group-hover:opacity-100"
            >
                {copied ? <Check className="h-3.5 w-3.5 text-primary" /> : <Copy className="h-3.5 w-3.5" />}
            </button>
            <pre ref={preRef} className="overflow-x-auto p-4 text-xs leading-relaxed">
                {children}
            </pre>
        </div>
    );
}
```

`CitationChip.tsx`:

```tsx
import { cn } from '@/lib/utils';
import type { Citation } from '@/types/chat';

interface CitationChipProps {
    index: number;
    citation?: Citation;
    onClick?: () => void;
}

export function CitationChip({ index, citation, onClick }: CitationChipProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={citation?.title ?? citation?.url ?? `Fuente ${index}`}
            aria-label={`Ver fuente ${index}`}
            className={cn(
                'mx-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary/15 px-1 align-super text-[10px] font-black text-primary',
                'transition-colors hover:bg-primary/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            )}
        >
            {index}
        </button>
    );
}
```

`SourcesPanel.tsx`:

```tsx
import { useEffect, useState } from 'react';
import { ExternalLink } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Citation } from '@/types/chat';

interface SourcesPanelProps {
    citations: Citation[];
    activeIndex?: number | null;
    className?: string;
}

function domain(url: string): string {
    try {
        return new URL(url).hostname.replace(/^www\./, '');
    } catch {
        return url;
    }
}

export function SourcesPanel({ citations, activeIndex = null, className }: SourcesPanelProps) {
    const [expanded, setExpanded] = useState(true);

    useEffect(() => {
        if (activeIndex === null) return;

        setExpanded(true);
        document.getElementById(`cite-${activeIndex}`)?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, [activeIndex]);

    if (citations.length === 0) return null;

    return (
        <div className={cn('mt-3 rounded-xl border border-border bg-card/60', className)}>
            <button
                type="button"
                onClick={() => setExpanded((previous) => !previous)}
                className="flex w-full items-center justify-between px-3 py-2 text-left"
                aria-expanded={expanded}
            >
                <span className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                    Fuentes · {citations.length}
                </span>
                <span className="text-xs text-muted-foreground">{expanded ? 'Ocultar' : 'Mostrar'}</span>
            </button>

            {expanded && (
                <ol className="grid gap-2 border-t border-border px-3 py-3 sm:grid-cols-2">
                    {citations.map((citation, position) => {
                        const index = position + 1;

                        return (
                            <li key={citation.url}>
                                <a
                                    id={`cite-${index}`}
                                    href={citation.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={cn(
                                        'flex h-full gap-2 rounded-lg border border-transparent p-2 transition-colors hover:border-border hover:bg-muted/40',
                                        activeIndex === index && 'border-primary/40 bg-primary/5',
                                    )}
                                >
                                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/15 text-[10px] font-black text-primary">
                                        {index}
                                    </span>
                                    <span className="min-w-0">
                                        <span className="block truncate text-xs font-medium text-foreground">
                                            {citation.title ?? domain(citation.url)}
                                        </span>
                                        <span className="mt-0.5 flex items-center gap-1 text-[10px] text-muted-foreground">
                                            {domain(citation.url)}
                                            <ExternalLink className="h-2.5 w-2.5" />
                                        </span>
                                    </span>
                                </a>
                            </li>
                        );
                    })}
                </ol>
            )}
        </div>
    );
}
```

`StreamStatus.tsx`:

```tsx
import { CheckCircle2, Loader2, XCircle } from 'lucide-react';
import type { ToolActivity } from '@/types/chat';

interface StreamStatusProps {
    thinking: boolean;
    tools: ToolActivity[];
}

const TOOL_LABELS: Record<string, string> = {
    WorkoutQueryTool: 'Consultando entrenamientos',
    FinanceQueryTool: 'Consultando finanzas',
    NutritionQueryTool: 'Consultando nutrición',
    GroceryQueryTool: 'Consultando compras',
    ActionTool: 'Ejecutando acción',
};

export function StreamStatus({ thinking, tools }: StreamStatusProps) {
    if (!thinking && tools.length === 0) return null;

    return (
        <div className="flex flex-col gap-1.5" role="status" aria-live="polite">
            {thinking && (
                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Loader2 className="h-3.5 w-3.5 animate-spin text-primary motion-reduce:animate-none" />
                    Pensando…
                </span>
            )}

            {tools.map((tool) => (
                <span key={tool.id} className="flex items-center gap-2 text-xs text-muted-foreground">
                    {tool.status === 'running' && (
                        <Loader2 className="h-3.5 w-3.5 animate-spin text-primary motion-reduce:animate-none" />
                    )}
                    {tool.status === 'done' && <CheckCircle2 className="h-3.5 w-3.5 text-primary" />}
                    {tool.status === 'failed' && <XCircle className="h-3.5 w-3.5 text-destructive" />}
                    {TOOL_LABELS[tool.name] ?? `Usando ${tool.name}`}
                </span>
            ))}
        </div>
    );
}
```

`Markdown.tsx`:

```tsx
import type { ComponentPropsWithoutRef } from 'react';
import ReactMarkdown from 'react-markdown';
import rehypeHighlight from 'rehype-highlight';
import remarkGfm from 'remark-gfm';
import { CitationChip } from '@/components/ai/chat/CitationChip';
import { CodeBlock } from '@/components/ai/chat/CodeBlock';
import { remarkCitations } from '@/lib/remark-citations';
import { cn } from '@/lib/utils';
import type { Citation } from '@/types/chat';

interface MarkdownProps {
    content: string;
    citations?: Citation[];
    className?: string;
    onCitationClick?: (index: number) => void;
}

export function Markdown({ content, citations = [], className, onCitationClick }: MarkdownProps) {
    return (
        <div className={cn('text-sm leading-relaxed text-foreground', className)}>
            <ReactMarkdown
                remarkPlugins={[remarkGfm, [remarkCitations, { max: citations.length }]]}
                rehypePlugins={[rehypeHighlight]}
                components={{
                    p: ({ children }) => <p className="my-2 first:mt-0 last:mb-0">{children}</p>,
                    h1: ({ children }) => <h1 className="mt-4 mb-2 text-lg font-black tracking-tight">{children}</h1>,
                    h2: ({ children }) => <h2 className="mt-4 mb-2 text-base font-black tracking-tight">{children}</h2>,
                    h3: ({ children }) => <h3 className="mt-3 mb-1.5 text-sm font-bold tracking-tight">{children}</h3>,
                    ul: ({ children }) => <ul className="my-2 list-disc space-y-1 pl-5">{children}</ul>,
                    ol: ({ children }) => <ol className="my-2 list-decimal space-y-1 pl-5">{children}</ol>,
                    li: ({ children }) => <li className="marker:text-primary/70">{children}</li>,
                    blockquote: ({ children }) => (
                        <blockquote className="my-3 border-l-2 border-primary/50 pl-3 text-muted-foreground">
                            {children}
                        </blockquote>
                    ),
                    hr: () => <hr className="my-4 border-border" />,
                    table: ({ children }) => (
                        <div className="my-3 overflow-x-auto rounded-lg border border-border">
                            <table className="w-full border-collapse text-left text-xs">{children}</table>
                        </div>
                    ),
                    th: ({ children }) => (
                        <th className="border-b border-border bg-muted/40 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-muted-foreground">
                            {children}
                        </th>
                    ),
                    td: ({ children }) => <td className="border-b border-border/60 px-3 py-2 align-top">{children}</td>,
                    a: ({ href, children }) => {
                        const citationMatch = href?.match(/^#cite-(\d+)$/);

                        if (citationMatch) {
                            const index = Number(citationMatch[1]);

                            return (
                                <CitationChip
                                    index={index}
                                    citation={citations[index - 1]}
                                    onClick={() => onCitationClick?.(index)}
                                />
                            );
                        }

                        return (
                            <a
                                href={href}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium text-primary underline decoration-primary/40 underline-offset-2 hover:decoration-primary"
                            >
                                {children}
                            </a>
                        );
                    },
                    code: ({ className: codeClassName, children, ...props }: ComponentPropsWithoutRef<'code'>) => {
                        if (/language-/.test(codeClassName ?? '')) {
                            return (
                                <code className={codeClassName} {...props}>
                                    {children}
                                </code>
                            );
                        }

                        return (
                            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-[0.8em] text-primary" {...props}>
                                {children}
                            </code>
                        );
                    },
                    pre: ({ children }) => <CodeBlock>{children}</CodeBlock>,
                }}
            >
                {content}
            </ReactMarkdown>
        </div>
    );
}
```

- [ ] **Step 6: Añadir estilos `hljs` al final de `resources/css/app.css`**

```css
/* Código resaltado — paleta Ember */
.hljs-comment,
.hljs-quote {
    color: #a18686;
    font-style: italic;
}

.hljs-keyword,
.hljs-selector-tag,
.hljs-literal,
.hljs-tag {
    color: #f87171;
}

.hljs-string,
.hljs-attr,
.hljs-addition {
    color: #fca5a5;
}

.hljs-number,
.hljs-symbol,
.hljs-bullet {
    color: #fdba74;
}

.hljs-title,
.hljs-section,
.hljs-name {
    color: #fecaca;
}

.hljs-built_in,
.hljs-type,
.hljs-attribute {
    color: #f97316;
}

.hljs-deletion {
    color: #ef4444;
}

.hljs-meta {
    color: #e8b4b4;
}
```

- [ ] **Step 7: Verificar**

Run: `npm run types`
Expected: sin errores nuevos

Run: `npx eslint resources/js/lib resources/js/hooks resources/js/components/ai --fix`
Expected: sin errores

Run: `npm run build`
Expected: build correcto

- [ ] **Step 8: Commit**

```bash
git add package.json package-lock.json resources/js/lib/chat-sse.ts resources/js/hooks/use-chat-stream.ts resources/js/lib/relative-date.ts resources/js/lib/remark-citations.ts resources/js/components/ai/chat/Markdown.tsx resources/js/components/ai/chat/CodeBlock.tsx resources/js/components/ai/chat/CitationChip.tsx resources/js/components/ai/chat/SourcesPanel.tsx resources/js/components/ai/chat/StreamStatus.tsx resources/css/app.css
git commit -m "feat(ai): add chat stream parser, hook and markdown rendering primitives"
```

---

### Task 7: UI de navegación — layout, rail de hilos, home, composer y selector de modelo

**Files:**
- Create: `resources/js/layouts/chat-layout.tsx`
- Create: `resources/js/components/ai/chat/ThreadRail.tsx`
- Create: `resources/js/components/ai/chat/ThreadItem.tsx`
- Create: `resources/js/components/ai/chat/ProviderNotice.tsx`
- Create: `resources/js/components/ai/chat/ModelPicker.tsx`
- Create: `resources/js/components/ai/chat/Composer.tsx`
- Modify: `resources/js/pages/ai/chat.tsx` (reemplaza la versión base de Task 3)

**Interfaces:**
- Consumes: `ChatThread`, `AiChatState` (Task 3); `useChatStream` y primitivas (Task 6); rutas `ai.chat.index/show/send` (Tasks 3-4).
- Produces: `ChatLayout({threads, activeThreadId, header, children})`; `Composer({models, model, onModelChange, onSubmit, onStop, streaming, disabled?, autoFocus?, large?, placeholder?})`; `ModelPicker({models, value, onChange, disabled?})`; `ThreadRail({threads, activeThreadId})`; `ProviderNotice()`. Consumido por Task 8.

- [ ] **Step 1: Crear `layouts/chat-layout.tsx`**

```tsx
import type { ReactNode } from 'react';
import { History } from 'lucide-react';
import { ThreadRail } from '@/components/ai/chat/ThreadRail';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import MainLayout from '@/layouts/main-layout';
import type { ChatThread } from '@/types/chat';

interface ChatLayoutProps {
    threads: ChatThread[];
    activeThreadId: string | null;
    header?: ReactNode;
    children: ReactNode;
}

export default function ChatLayout({ threads, activeThreadId, header, children }: ChatLayoutProps) {
    return (
        <MainLayout>
            <div className="flex h-[calc(100dvh-3rem)]">
                <aside className="hidden w-72 shrink-0 border-r border-border lg:block">
                    <ThreadRail threads={threads} activeThreadId={activeThreadId} />
                </aside>

                <div className="flex min-w-0 flex-1 flex-col">
                    <header className="flex h-14 shrink-0 items-center gap-2 border-b border-border px-3 sm:px-4">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="lg:hidden"
                                    aria-label="Abrir historial de hilos"
                                >
                                    <History className="h-4 w-4" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="w-72 border-border bg-background p-0">
                                <SheetTitle className="sr-only">Historial de hilos</SheetTitle>
                                <ThreadRail threads={threads} activeThreadId={activeThreadId} />
                            </SheetContent>
                        </Sheet>

                        <div className="flex min-w-0 flex-1 items-center gap-2">{header}</div>
                    </header>

                    <div className="flex min-h-0 flex-1 flex-col">{children}</div>
                </div>
            </div>
        </MainLayout>
    );
}
```

- [ ] **Step 2: Crear `ThreadRail.tsx` y `ThreadItem.tsx`**

```tsx
// ThreadRail.tsx
import { useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import { ThreadItem } from '@/components/ai/chat/ThreadItem';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { THREAD_GROUP_ORDER, threadGroup } from '@/lib/relative-date';
import type { ChatThread } from '@/types/chat';

interface ThreadRailProps {
    threads: ChatThread[];
    activeThreadId: string | null;
}

export function ThreadRail({ threads, activeThreadId }: ThreadRailProps) {
    const [query, setQuery] = useState('');

    const groups = useMemo(() => {
        const normalized = query.trim().toLowerCase();
        const filtered =
            normalized === ''
                ? threads
                : threads.filter((thread) => thread.title.toLowerCase().includes(normalized));

        return THREAD_GROUP_ORDER.map((group) => ({
            group,
            items: filtered.filter((thread) => threadGroup(thread.updated_at, thread.is_pinned) === group),
        })).filter((entry) => entry.items.length > 0);
    }, [threads, query]);

    return (
        <div className="flex h-full flex-col">
            <div className="space-y-2 p-3">
                <Button asChild className="w-full justify-start gap-2 bg-primary text-primary-foreground hover:bg-primary/90">
                    <Link href={ChatController.index().url}>
                        <Plus className="h-4 w-4" />
                        Nuevo hilo
                    </Link>
                </Button>

                <div className="relative">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Buscar hilos…"
                        aria-label="Buscar hilos"
                        className="h-8 border-border bg-card pl-8 text-xs"
                    />
                </div>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
                {groups.length === 0 && (
                    <p className="px-2 py-6 text-center text-xs text-muted-foreground">
                        {threads.length === 0 ? 'Aún no tienes hilos. Empieza una conversación.' : 'Sin resultados.'}
                    </p>
                )}

                {groups.map(({ group, items }) => (
                    <div key={group} className="mb-3">
                        <p className="px-2 py-1 text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                            {group}
                        </p>
                        <div className="space-y-0.5">
                            {items.map((thread) => (
                                <ThreadItem key={thread.id} thread={thread} active={thread.id === activeThreadId} />
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
```

```tsx
// ThreadItem.tsx
import { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Pin, PinOff, Trash2 } from 'lucide-react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { ChatThread } from '@/types/chat';

interface ThreadItemProps {
    thread: ChatThread;
    active: boolean;
}

export function ThreadItem({ thread, active }: ThreadItemProps) {
    const [renaming, setRenaming] = useState(false);
    const [title, setTitle] = useState(thread.title);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const commitRename = () => {
        const trimmed = title.trim();

        setRenaming(false);

        if (trimmed === '' || trimmed === thread.title) {
            setTitle(thread.title);

            return;
        }

        router.patch(ChatController.update.url(thread.id), { title: trimmed }, { preserveScroll: true, preserveState: true });
    };

    const togglePinned = () => {
        router.patch(
            ChatController.update.url(thread.id),
            { pinned: !thread.is_pinned },
            { preserveScroll: true, preserveState: true },
        );
    };

    const destroy = () => {
        setConfirmOpen(false);
        router.delete(ChatController.destroy.url(thread.id), { preserveScroll: true });
    };

    if (renaming) {
        return (
            <Input
                autoFocus
                value={title}
                onChange={(event) => setTitle(event.target.value)}
                onBlur={commitRename}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') commitRename();
                    if (event.key === 'Escape') {
                        setTitle(thread.title);
                        setRenaming(false);
                    }
                }}
                className="h-8 border-border bg-card text-xs"
                aria-label="Renombrar hilo"
            />
        );
    }

    return (
        <div className={cn('group flex items-center gap-1 rounded-lg pr-1', active ? 'bg-primary/15' : 'hover:bg-muted/40')}>
            <Link
                href={ChatController.show(thread.id).url}
                className={cn(
                    'min-w-0 flex-1 truncate px-2 py-1.5 text-xs transition-colors',
                    active ? 'font-bold text-primary' : 'text-foreground',
                )}
            >
                {thread.is_pinned && <Pin className="mr-1 inline h-3 w-3 text-primary" />}
                {thread.title}
            </Link>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6 shrink-0 text-muted-foreground opacity-0 focus-visible:opacity-100 group-hover:opacity-100"
                        aria-label={`Acciones de ${thread.title}`}
                    >
                        <MoreHorizontal className="h-3.5 w-3.5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="border-border bg-card">
                    <DropdownMenuItem onSelect={() => setRenaming(true)}>
                        <Pencil className="mr-2 h-3.5 w-3.5" />
                        Renombrar
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={togglePinned}>
                        {thread.is_pinned ? <PinOff className="mr-2 h-3.5 w-3.5" /> : <Pin className="mr-2 h-3.5 w-3.5" />}
                        {thread.is_pinned ? 'Desfijar' : 'Fijar'}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onSelect={() => setConfirmOpen(true)} className="text-destructive focus:text-destructive">
                        <Trash2 className="mr-2 h-3.5 w-3.5" />
                        Eliminar
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent className="border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Eliminar hilo</DialogTitle>
                        <DialogDescription>
                            Se eliminarán “{thread.title}” y todos sus mensajes. No se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button variant="ghost" onClick={() => setConfirmOpen(false)}>
                            Cancelar
                        </Button>
                        <Button onClick={destroy} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Eliminar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
```

- [ ] **Step 3: Crear `ProviderNotice.tsx` y `ModelPicker.tsx`**

```tsx
// ProviderNotice.tsx
import { Link } from '@inertiajs/react';
import { Bot } from 'lucide-react';

export function ProviderNotice() {
    return (
        <div className="flex items-start gap-3 rounded-xl border border-border bg-card p-4">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/15">
                <Bot className="h-4 w-4 text-primary" />
            </div>
            <div>
                <p className="text-sm font-bold text-foreground">Configura tu proveedor de IA</p>
                <p className="mt-1 text-xs text-muted-foreground">
                    El chat necesita una URL, una API key y un modelo. Se guardan cifrados.{' '}
                    <Link href="/settings/ai" className="font-medium text-primary hover:underline">
                        Ir a Settings → IA
                    </Link>
                </p>
            </div>
        </div>
    );
}
```

```tsx
// ModelPicker.tsx
import { Link } from '@inertiajs/react';
import { Check, ChevronDown, Settings2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface ModelPickerProps {
    models: string[];
    value: string | null;
    onChange: (model: string) => void;
    disabled?: boolean;
}

export function ModelPicker({ models, value, onChange, disabled = false }: ModelPickerProps) {
    const current = value ?? models[0] ?? 'Modelo';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={disabled || models.length === 0}
                    className="max-w-44 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                >
                    <span className="truncate">{current}</span>
                    <ChevronDown className="h-3.5 w-3.5 shrink-0" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="max-h-80 overflow-y-auto border-border bg-card">
                <DropdownMenuLabel className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                    Modelo
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {models.map((model) => (
                    <DropdownMenuItem key={model} onSelect={() => onChange(model)}>
                        <Check
                            className={
                                model === current ? 'mr-2 h-3.5 w-3.5 text-primary' : 'mr-2 h-3.5 w-3.5 opacity-0'
                            }
                        />
                        <span className="truncate">{model}</span>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/settings/ai">
                        <Settings2 className="mr-2 h-3.5 w-3.5" />
                        Gestionar en Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
```

- [ ] **Step 4: Crear `Composer.tsx`**

```tsx
import { useEffect, useRef, useState } from 'react';
import { ArrowUp, Square } from 'lucide-react';
import { ModelPicker } from '@/components/ai/chat/ModelPicker';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface ComposerProps {
    models: string[];
    model: string | null;
    onModelChange: (model: string) => void;
    onSubmit: (message: string) => void;
    onStop?: () => void;
    streaming: boolean;
    disabled?: boolean;
    autoFocus?: boolean;
    large?: boolean;
    placeholder?: string;
}

const MAX_LENGTH = 4000;

export function Composer({
    models,
    model,
    onModelChange,
    onSubmit,
    onStop,
    streaming,
    disabled = false,
    autoFocus = false,
    large = false,
    placeholder = 'Pregunta lo que quieras…',
}: ComposerProps) {
    const [value, setValue] = useState('');
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    useEffect(() => {
        if (autoFocus) textareaRef.current?.focus();
    }, [autoFocus]);

    const resize = () => {
        const textarea = textareaRef.current;

        if (!textarea) return;

        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
    };

    const submit = () => {
        const message = value.trim();

        if (message === '' || streaming || disabled) return;

        onSubmit(message);
        setValue('');
        requestAnimationFrame(resize);
    };

    const canSubmit = value.trim() !== '' && !streaming && !disabled;
    const nearLimit = value.length > MAX_LENGTH - 500;

    return (
        <div
            className={cn(
                'rounded-2xl border border-border bg-card shadow-lg shadow-black/20 transition-colors focus-within:border-primary/50',
                large ? 'p-3' : 'p-2',
            )}
        >
            <textarea
                ref={textareaRef}
                value={value}
                rows={1}
                maxLength={MAX_LENGTH}
                disabled={disabled}
                placeholder={placeholder}
                aria-label="Mensaje"
                onChange={(event) => {
                    setValue(event.target.value);
                    resize();
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        submit();
                    }
                }}
                className={cn(
                    'w-full resize-none bg-transparent px-2 py-1.5 text-sm text-foreground placeholder:text-muted-foreground/60 focus:outline-none disabled:opacity-50',
                    large && 'min-h-12 text-base',
                )}
            />

            <div className="flex items-center justify-between gap-2 px-1 pt-1">
                <ModelPicker models={models} value={model} onChange={onModelChange} disabled={disabled || streaming} />

                <div className="flex items-center gap-3">
                    {nearLimit && (
                        <span
                            className={cn(
                                'text-[10px] tabular-nums',
                                value.length >= MAX_LENGTH ? 'text-destructive' : 'text-muted-foreground',
                            )}
                        >
                            {value.length}/{MAX_LENGTH}
                        </span>
                    )}

                    {streaming ? (
                        <Button
                            type="button"
                            size="icon"
                            onClick={onStop}
                            aria-label="Detener generación"
                            className="h-8 w-8 rounded-full bg-card text-foreground ring-1 ring-border hover:bg-muted"
                        >
                            <Square className="h-3.5 w-3.5 fill-current" />
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            size="icon"
                            onClick={submit}
                            disabled={!canSubmit}
                            aria-label="Enviar mensaje"
                            className="h-8 w-8 rounded-full bg-primary text-primary-foreground hover:bg-primary/90 disabled:opacity-40"
                        >
                            <ArrowUp className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 5: Reemplazar `pages/ai/chat.tsx` (home completa)**

```tsx
import { useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Bot } from 'lucide-react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import { Composer } from '@/components/ai/chat/Composer';
import { ProviderNotice } from '@/components/ai/chat/ProviderNotice';
import { useChatStream } from '@/hooks/use-chat-stream';
import ChatLayout from '@/layouts/chat-layout';
import type { AiChatState, ChatThread } from '@/types/chat';

interface ChatIndexProps {
    threads: ChatThread[];
    models: string[];
    ai: AiChatState;
}

const SUGGESTIONS = [
    '¿Cómo va mi progreso de entrenamiento este mes?',
    'Resume mis gastos de la última semana',
    'Sugiere una cena alta en proteína con lo que tengo en casa',
    '¿Qué tareas tengo pendientes con fecha límite próxima?',
];

export default function ChatIndex({ threads, models, ai }: ChatIndexProps) {
    const [model, setModel] = useState<string | null>(ai.defaultModel ?? models[0] ?? null);
    const createdThreadRef = useRef<string | null>(null);

    const stream = useChatStream({
        onThread: (threadId) => {
            createdThreadRef.current = threadId;
        },
        onComplete: () => {
            const threadId = createdThreadRef.current;

            if (threadId) {
                router.visit(ChatController.show.url(threadId), { replace: true });
            }
        },
    });

    const submit = (message: string) => {
        stream.start(ChatController.send.url(), {
            message,
            model: model ?? undefined,
        });
    };

    return (
        <ChatLayout threads={threads} activeThreadId={null}>
            <Head title="Chat IA" />

            <div className="min-h-0 flex-1 overflow-y-auto">
                <div className="mx-auto flex w-full max-w-2xl flex-col px-4 pb-10 pt-10 sm:pt-16">
                    <div className="mb-6 flex flex-col items-center text-center">
                        <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/15">
                            <Bot className="h-6 w-6 text-primary" />
                        </div>
                        <h1 className="text-2xl font-black tracking-tight text-foreground sm:text-3xl">
                            ¿Qué quieres saber?
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Tu asistente con acceso a entrenamientos, finanzas, nutrición y freelance.
                        </p>
                    </div>

                    {!ai.configured && (
                        <div className="mb-4">
                            <ProviderNotice />
                        </div>
                    )}

                    <Composer
                        large
                        autoFocus
                        models={models}
                        model={model}
                        onModelChange={setModel}
                        onSubmit={submit}
                        onStop={stream.stop}
                        streaming={stream.status === 'streaming'}
                        disabled={!ai.configured}
                    />

                    {stream.error && (
                        <p className="mt-2 text-center text-xs text-destructive" role="alert">
                            {stream.error}
                        </p>
                    )}

                    <div className="mt-4 flex flex-wrap justify-center gap-2">
                        {SUGGESTIONS.map((suggestion) => (
                            <button
                                key={suggestion}
                                type="button"
                                disabled={!ai.configured || stream.status === 'streaming'}
                                onClick={() => submit(suggestion)}
                                className="rounded-full border border-border bg-card px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:border-primary/40 hover:text-foreground disabled:opacity-40"
                            >
                                {suggestion}
                            </button>
                        ))}
                    </div>

                    {stream.status === 'streaming' && stream.text !== '' && (
                        <div className="mt-6 rounded-2xl border border-border bg-card p-4">
                            <p className="whitespace-pre-wrap text-sm text-foreground">{stream.text}</p>
                        </div>
                    )}

                    {threads.length > 0 && (
                        <div className="mt-10">
                            <p className="mb-2 text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                Recientes
                            </p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {threads.slice(0, 6).map((thread) => (
                                    <Link
                                        key={thread.id}
                                        href={ChatController.show(thread.id).url}
                                        className="truncate rounded-xl border border-border bg-card px-3 py-2.5 text-sm text-foreground transition-colors hover:border-primary/40"
                                    >
                                        {thread.title}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </ChatLayout>
    );
}
```

- [ ] **Step 6: Verificar**

Run: `npm run types`
Expected: sin errores nuevos

Run: `npx eslint resources/js/layouts/chat-layout.tsx resources/js/components/ai resources/js/pages/ai --fix`
Expected: sin errores

Run: `npm run build`
Expected: build correcto

- [ ] **Step 7: Commit**

```bash
git add resources/js/layouts/chat-layout.tsx resources/js/components/ai/chat/ThreadRail.tsx resources/js/components/ai/chat/ThreadItem.tsx resources/js/components/ai/chat/ProviderNotice.tsx resources/js/components/ai/chat/ModelPicker.tsx resources/js/components/ai/chat/Composer.tsx resources/js/pages/ai/chat.tsx
git commit -m "feat(ai): add chat layout, thread rail, composer and home page"
```

---

### Task 8: Vista de hilo completa, FAB y limpieza del panel viejo

**Files:**
- Create: `resources/js/components/ai/chat/MessageList.tsx`
- Create: `resources/js/components/ai/chat/AssistantMessage.tsx`
- Create: `resources/js/components/ai/chat/UserMessage.tsx`
- Modify: `resources/js/pages/ai/thread.tsx` (reemplaza la versión base de Task 3)
- Modify: `resources/js/layouts/main-layout.tsx` (FAB → `Link` a `/ai/chat`, quitar `ChatPanel`)
- Modify: `resources/js/components/app-sidebar.tsx` (active state con `startsWith('/ai/chat')`)
- Delete: `resources/js/components/ai/ChatPanel.tsx`, `resources/js/components/ai/MessageBubble.tsx`

**Interfaces:**
- Consumes: `useChatStream`, `Markdown`, `SourcesPanel`, `StreamStatus`, `ChatLayout`, `Composer` (Tasks 6-7); rutas `ai.chat.send/regenerate/edit/update/destroy` (Tasks 3-4).
- Produces: vista de conversación completa con streaming, citas, copiar, regenerar, editar, renombrar, fijar y borrar.

- [ ] **Step 1: Crear `AssistantMessage.tsx` y `UserMessage.tsx`**

```tsx
// AssistantMessage.tsx
import { useState } from 'react';
import { Check, Copy, RefreshCw } from 'lucide-react';
import { Markdown } from '@/components/ai/chat/Markdown';
import { SourcesPanel } from '@/components/ai/chat/SourcesPanel';
import { StreamStatus } from '@/components/ai/chat/StreamStatus';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Citation, ToolActivity } from '@/types/chat';

interface AssistantMessageProps {
    content: string;
    citations: Citation[];
    tools?: ToolActivity[];
    streaming?: boolean;
    disabled?: boolean;
    onRegenerate?: () => void;
}

export function AssistantMessage({
    content,
    citations,
    tools = [],
    streaming = false,
    disabled = false,
    onRegenerate,
}: AssistantMessageProps) {
    const [copied, setCopied] = useState(false);
    const [activeCitation, setActiveCitation] = useState<number | null>(null);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(content);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // clipboard no disponible
        }
    };

    const thinking = streaming && content === '';

    return (
        <article className="flex gap-3" aria-live="polite">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-[10px] font-black text-primary">
                IA
            </div>

            <div className="min-w-0 flex-1">
                <StreamStatus thinking={thinking} tools={tools} />

                <Markdown
                    content={content}
                    citations={citations}
                    onCitationClick={setActiveCitation}
                    className={cn(thinking && 'hidden')}
                />

                {streaming && content !== '' && (
                    <span className="ml-0.5 inline-block h-4 w-1.5 animate-pulse bg-primary/70 align-text-bottom motion-reduce:animate-none" />
                )}

                <SourcesPanel citations={citations} activeIndex={activeCitation} />

                {!streaming && content !== '' && (
                    <div className="mt-2 flex items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            onClick={copy}
                            aria-label="Copiar respuesta"
                            className="h-7 w-7 text-muted-foreground hover:text-foreground"
                        >
                            {copied ? <Check className="h-3.5 w-3.5 text-primary" /> : <Copy className="h-3.5 w-3.5" />}
                        </Button>

                        {onRegenerate && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={onRegenerate}
                                disabled={disabled}
                                aria-label="Regenerar respuesta"
                                className="h-7 w-7 text-muted-foreground hover:text-foreground"
                            >
                                <RefreshCw className="h-3.5 w-3.5" />
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </article>
    );
}
```

```tsx
// UserMessage.tsx
import { useState } from 'react';
import { Check, Copy, Pencil } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { ChatMessage } from '@/types/chat';

interface UserMessageProps {
    message: ChatMessage;
    onEdit: (messageId: string, content: string) => void;
    disabled?: boolean;
}

export function UserMessage({ message, onEdit, disabled = false }: UserMessageProps) {
    const [editing, setEditing] = useState(false);
    const [value, setValue] = useState(message.content);
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(message.content);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            // clipboard no disponible
        }
    };

    const commit = () => {
        const trimmed = value.trim();

        setEditing(false);

        if (trimmed === '' || trimmed === message.content) {
            setValue(message.content);

            return;
        }

        onEdit(message.id, trimmed);
    };

    return (
        <article className="group flex justify-end">
            <div className="max-w-[85%]">
                {editing ? (
                    <div className="rounded-2xl border border-primary/40 bg-card p-3">
                        <textarea
                            autoFocus
                            value={value}
                            rows={Math.min(8, Math.max(2, value.split('\n').length))}
                            onChange={(event) => setValue(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter' && !event.shiftKey) {
                                    event.preventDefault();
                                    commit();
                                }

                                if (event.key === 'Escape') {
                                    setValue(message.content);
                                    setEditing(false);
                                }
                            }}
                            className="w-full resize-none bg-transparent text-sm text-foreground focus:outline-none"
                            aria-label="Editar mensaje"
                        />
                        <div className="mt-2 flex justify-end gap-2">
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                    setValue(message.content);
                                    setEditing(false);
                                }}
                            >
                                Cancelar
                            </Button>
                            <Button size="sm" onClick={commit} className="bg-primary text-primary-foreground hover:bg-primary/90">
                                Enviar
                            </Button>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="whitespace-pre-wrap break-words rounded-2xl rounded-tr-sm border border-primary/20 bg-primary/10 px-4 py-2.5 text-sm text-foreground">
                            {message.content}
                        </div>

                        <div className="mt-1 flex justify-end gap-1 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={copy}
                                aria-label="Copiar mensaje"
                                className="h-6 w-6 text-muted-foreground hover:text-foreground"
                            >
                                {copied ? <Check className="h-3 w-3 text-primary" /> : <Copy className="h-3 w-3" />}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => setEditing(true)}
                                disabled={disabled}
                                aria-label="Editar mensaje"
                                className="h-6 w-6 text-muted-foreground hover:text-foreground"
                            >
                                <Pencil className="h-3 w-3" />
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </article>
    );
}
```

- [ ] **Step 2: Crear `MessageList.tsx`**

```tsx
import { useEffect, useRef, useState } from 'react';
import { ArrowDown } from 'lucide-react';
import { AssistantMessage } from '@/components/ai/chat/AssistantMessage';
import { UserMessage } from '@/components/ai/chat/UserMessage';
import { Button } from '@/components/ui/button';
import type { ChatMessage, Citation, ToolActivity } from '@/types/chat';

interface MessageListProps {
    messages: ChatMessage[];
    liveText: string;
    liveCitations: Citation[];
    liveTools: ToolActivity[];
    streaming: boolean;
    onRegenerate: () => void;
    onEdit: (messageId: string, content: string) => void;
}

export function MessageList({
    messages,
    liveText,
    liveCitations,
    liveTools,
    streaming,
    onRegenerate,
    onEdit,
}: MessageListProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [pinned, setPinned] = useState(true);

    const scrollToBottom = (behavior: ScrollBehavior = 'auto') => {
        const container = containerRef.current;

        if (!container) return;

        container.scrollTo({ top: container.scrollHeight, behavior });
    };

    useEffect(() => {
        if (pinned) scrollToBottom();
    }, [messages, liveText, streaming, pinned]);

    const handleScroll = () => {
        const container = containerRef.current;

        if (!container) return;

        const distance = container.scrollHeight - container.scrollTop - container.clientHeight;
        setPinned(distance < 80);
    };

    const lastAssistantId = [...messages].reverse().find((message) => message.role === 'assistant')?.id ?? null;
    const showLive = streaming || liveText !== '';

    return (
        <div className="relative min-h-0 flex-1">
            <div ref={containerRef} onScroll={handleScroll} className="h-full overflow-y-auto">
                <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-6">
                    {messages.map((message) =>
                        message.role === 'user' ? (
                            <UserMessage key={message.id} message={message} onEdit={onEdit} disabled={streaming} />
                        ) : (
                            <AssistantMessage
                                key={message.id}
                                content={message.content}
                                citations={message.citations}
                                onRegenerate={message.id === lastAssistantId ? onRegenerate : undefined}
                                disabled={streaming}
                            />
                        ),
                    )}

                    {showLive && (
                        <AssistantMessage
                            content={liveText}
                            citations={liveCitations}
                            tools={liveTools}
                            streaming={streaming}
                        />
                    )}
                </div>
            </div>

            {!pinned && (
                <Button
                    type="button"
                    size="icon"
                    onClick={() => scrollToBottom('smooth')}
                    aria-label="Ir al final"
                    className="absolute bottom-4 right-4 h-8 w-8 rounded-full border border-border bg-card text-foreground shadow-lg hover:bg-muted"
                >
                    <ArrowDown className="h-4 w-4" />
                </Button>
            )}
        </div>
    );
}
```

- [ ] **Step 3: Reemplazar `pages/ai/thread.tsx`**

```tsx
import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { MoreHorizontal, Pin, PinOff, Trash2 } from 'lucide-react';
import ChatController from '@/actions/App/Http/Controllers/Ai/ChatController';
import { Composer } from '@/components/ai/chat/Composer';
import { MessageList } from '@/components/ai/chat/MessageList';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useChatStream } from '@/hooks/use-chat-stream';
import ChatLayout from '@/layouts/chat-layout';
import type { AiChatState, ChatMessage, ChatThread } from '@/types/chat';

interface ChatThreadProps {
    thread: ChatThread;
    messages: ChatMessage[];
    threads: ChatThread[];
    models: string[];
    ai: AiChatState;
}

export default function ChatThread({ thread, messages, threads, models, ai }: ChatThreadProps) {
    const [model, setModel] = useState<string | null>(thread.model ?? ai.defaultModel ?? models[0] ?? null);
    const [renaming, setRenaming] = useState(false);
    const [title, setTitle] = useState(thread.title);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const stream = useChatStream({
        onComplete: () => {
            router.reload({
                only: ['threads', 'messages'],
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => stream.reset(),
            });
        },
    });

    const submit = (message: string) => {
        stream.start(ChatController.send.url(), {
            message,
            thread_id: thread.id,
            model: model ?? undefined,
        });
    };

    const regenerate = () => {
        stream.start(ChatController.regenerate.url(thread.id), {});
    };

    const edit = (messageId: string, content: string) => {
        stream.start(ChatController.edit.url(thread.id), { message_id: messageId, content });
    };

    const commitRename = () => {
        const trimmed = title.trim();

        setRenaming(false);

        if (trimmed === '' || trimmed === thread.title) {
            setTitle(thread.title);

            return;
        }

        router.patch(ChatController.update.url(thread.id), { title: trimmed }, { preserveScroll: true, preserveState: true });
    };

    const togglePinned = () => {
        router.patch(
            ChatController.update.url(thread.id),
            { pinned: !thread.is_pinned },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <ChatLayout
            threads={threads}
            activeThreadId={thread.id}
            header={
                <>
                    {renaming ? (
                        <Input
                            autoFocus
                            value={title}
                            onChange={(event) => setTitle(event.target.value)}
                            onBlur={commitRename}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') commitRename();
                                if (event.key === 'Escape') {
                                    setTitle(thread.title);
                                    setRenaming(false);
                                }
                            }}
                            className="h-8 max-w-sm border-border bg-card text-sm"
                            aria-label="Renombrar hilo"
                        />
                    ) : (
                        <button
                            type="button"
                            onClick={() => setRenaming(true)}
                            className="min-w-0 truncate text-sm font-bold text-foreground hover:text-primary"
                            title={thread.title}
                        >
                            {thread.title}
                        </button>
                    )}

                    {thread.is_pinned && <Pin className="h-3.5 w-3.5 shrink-0 text-primary" />}

                    <div className="ml-auto flex items-center gap-1">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8 text-muted-foreground hover:text-foreground"
                                    aria-label="Acciones del hilo"
                                >
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="border-border bg-card">
                                <DropdownMenuItem onSelect={() => setRenaming(true)}>Renombrar</DropdownMenuItem>
                                <DropdownMenuItem onSelect={togglePinned}>
                                    {thread.is_pinned ? <PinOff className="mr-2 h-3.5 w-3.5" /> : <Pin className="mr-2 h-3.5 w-3.5" />}
                                    {thread.is_pinned ? 'Desfijar' : 'Fijar'}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onSelect={() => setConfirmOpen(true)}
                                    className="text-destructive focus:text-destructive"
                                >
                                    <Trash2 className="mr-2 h-3.5 w-3.5" />
                                    Eliminar
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </>
            }
        >
            <Head title={thread.title} />

            <MessageList
                messages={messages}
                liveText={stream.text}
                liveCitations={stream.citations}
                liveTools={stream.tools}
                streaming={stream.status === 'streaming'}
                onRegenerate={regenerate}
                onEdit={edit}
            />

            {stream.error && (
                <div className="mx-auto w-full max-w-3xl px-4" role="alert">
                    <p className="mb-2 text-xs text-destructive">{stream.error}</p>
                </div>
            )}

            <div className="shrink-0 border-t border-border bg-background/80 px-4 py-3 backdrop-blur">
                <div className="mx-auto w-full max-w-3xl">
                    <Composer
                        models={models}
                        model={model}
                        onModelChange={setModel}
                        onSubmit={submit}
                        onStop={stream.stop}
                        streaming={stream.status === 'streaming'}
                        disabled={!ai.configured}
                    />
                    <p className="mt-1.5 text-center text-[10px] text-muted-foreground">
                        La IA puede cometer errores. Verifica la información importante.
                    </p>
                </div>
            </div>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent className="border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Eliminar hilo</DialogTitle>
                        <DialogDescription>
                            Se eliminarán “{thread.title}” y todos sus mensajes. No se puede deshacer.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button variant="ghost" onClick={() => setConfirmOpen(false)}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={() => router.delete(ChatController.destroy.url(thread.id))}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            Eliminar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </ChatLayout>
    );
}
```

- [ ] **Step 4: FAB, sidebar activo y limpieza**

En `resources/js/layouts/main-layout.tsx`: quitar `useState`, el import de `ChatPanel`, el bloque del `<button>` con `onClick` y `<ChatPanel … />`. Sustituir el botón flotante por un `Link` (importar `Link` de `@inertiajs/react`):

```tsx
            <Link
                href="/ai/chat"
                className="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-white shadow-lg shadow-primary/30 transition-all hover:bg-primary/90 hover:scale-105 active:scale-95"
                aria-label="Abrir chat IA"
            >
                <MessageSquare className="h-6 w-6" />
            </Link>
```

En `resources/js/components/app-sidebar.tsx`: cambiar el `isActive` del item "Chat IA" a `window.location.pathname.startsWith('/ai/chat')`.

```bash
rm resources/js/components/ai/ChatPanel.tsx resources/js/components/ai/MessageBubble.tsx
```

- [ ] **Step 5: Verificar**

Run: `npm run types`
Expected: sin errores nuevos

Run: `npx eslint resources/js/components/ai resources/js/pages/ai resources/js/layouts --fix`
Expected: sin errores

Run: `npm run build`
Expected: build correcto

Run: `php artisan test --compact --filter=Chat`
Expected: toda la suite de chat verde

- [ ] **Step 6: Commit**

```bash
git add -u resources/js/components/ai/ChatPanel.tsx resources/js/components/ai/MessageBubble.tsx resources/js/layouts/main-layout.tsx resources/js/components/app-sidebar.tsx
git add resources/js/components/ai/chat/MessageList.tsx resources/js/components/ai/chat/AssistantMessage.tsx resources/js/components/ai/chat/UserMessage.tsx resources/js/pages/ai/thread.tsx
git commit -m "feat(ai): add full thread view with streaming, citations and message actions"
```

---

### Task 9: Fix de seguridad en Settings → IA (API key) + invalidación de cache de modelos

**Files:**
- Modify: `app/Http/Controllers/Settings/AiSettingsController.php`
- Modify: `resources/js/pages/settings/ai.tsx` (prop `has_provider_key`, placeholder)
- Test: `tests/Feature/Settings/AiSettingsTest.php`

**Interfaces:**
- Consumes: `UserFactory::withAiProvider` (Task 2), cache key `ai.models.{id}` (Task 2).
- Produces: prop Inertia `ai.has_provider_key: bool`; `update()` conserva la key si llega vacía y olvida la cache de modelos.

- [ ] **Step 1: Escribir el test**

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('ai settings page never exposes the provider key', function () {
    $user = User::factory()->withAiProvider()->create(['ai_provider_key' => 'sk-secreto']);

    $this->actingAs($user)
        ->get(route('ai-settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai')
            ->where('ai.has_provider_key', true)
            ->missing('ai.ai_provider_key')
        );
});

test('empty provider key keeps the stored key', function () {
    $user = User::factory()->withAiProvider()->create(['ai_provider_key' => 'sk-original']);

    $this->actingAs($user)
        ->put(route('ai-settings.update'), [
            'ai_provider_url' => 'https://api.example.com/v1',
            'ai_provider_key' => '',
            'ai_model' => 'modelo',
            'ai_enabled' => true,
        ])
        ->assertRedirect(route('ai-settings.edit'));

    expect($user->refresh()->ai_provider_key)->toBe('sk-original');
});

test('new provider key replaces the stored key and model cache is forgotten', function () {
    $user = User::factory()->withAiProvider()->create(['ai_provider_key' => 'sk-original']);

    Cache::put("ai.models.{$user->id}", ['viejo'], now()->addMinutes(5));

    $this->actingAs($user)
        ->put(route('ai-settings.update'), [
            'ai_provider_url' => 'https://api.example.com/v1',
            'ai_provider_key' => 'sk-nueva',
            'ai_model' => 'modelo',
            'ai_enabled' => true,
        ])
        ->assertRedirect(route('ai-settings.edit'));

    expect($user->refresh()->ai_provider_key)->toBe('sk-nueva');
    expect(Cache::has("ai.models.{$user->id}"))->toBeFalse();
});
```

- [ ] **Step 2: Correr el test (debe fallar)**

Run: `php artisan test --compact --filter=AiSettingsTest`
Expected: FAIL (`has_provider_key` no existe / la key se filtra)

- [ ] **Step 3: Modificar el controlador**

En `edit()`, sustituir la línea de `ai_provider_key`:

```php
            'ai' => [
                'ai_provider_url' => $request->user()->ai_provider_url,
                'has_provider_key' => filled($request->user()->ai_provider_key),
                'ai_model' => $request->user()->ai_model,
                'ai_enabled' => $request->user()->ai_enabled,
            ],
```

En `update()`, tras validar:

```php
        if (blank($validated['ai_provider_key'] ?? null)) {
            unset($validated['ai_provider_key']);
        }

        $request->user()->update($validated);

        Cache::forget("ai.models.{$request->user()->getKey()}");

        return to_route('ai-settings.edit');
```

Añadir el import `use Illuminate\Support\Facades\Cache;`.

- [ ] **Step 4: Ajustar la página `settings/ai.tsx`**

Cambiar el tipo de la prop a `has_provider_key: boolean` (en lugar de `ai_provider_key: string | null`) y el input de key a:

```tsx
                                        <Input
                                            id="ai_provider_key"
                                            name="ai_provider_key"
                                            type="password"
                                            className="mt-1 block w-full bg-background"
                                            defaultValue=""
                                            placeholder={ai.has_provider_key ? '•••••••• (guardada)' : 'sk-...'}
                                            autoComplete="off"
                                        />
```

Y actualizar el texto de ayuda: `Leave empty to keep your current key`.

- [ ] **Step 5: Correr el test (debe pasar)**

Run: `php artisan test --compact --filter=AiSettingsTest`
Expected: 3 passed

Run: `npm run types`

- [ ] **Step 6: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Settings/AiSettingsController.php resources/js/pages/settings/ai.tsx tests/Feature/Settings/AiSettingsTest.php
git commit -m "fix(ai): stop exposing provider key in settings and invalidate model cache"
```

---

### Task 10: QA final — suite completa, estáticos y Playwright MCP

**Files:**
- Modify: `docs/qa/playwright-report.md` (añadir sección del día con resultados; si el archivo no existe, crear `docs/qa/ai-chat-report.md`)

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: evidencia de verificación final del Spec A.

- [ ] **Step 1: Suite y estáticos**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
php artisan wayfinder:generate --with-form
npm run types
npm run lint
npm run build
```

Expected: suite completa verde, sin errores de tipos/lint, build correcto.

- [ ] **Step 2: QA manual con Playwright MCP en `:8010`**

Requiere servidor dev activo (`composer run dev`) y login `test@example.com` / `password`. Verificar y anotar (con captura) cada punto:

1. `/ai/chat` sin provider configurado → `ProviderNotice` visible y composer deshabilitado.
2. Con provider configurado: home muestra hero, composer y chips; el picker lista modelos.
3. Enviar mensaje → "Pensando…", texto en streaming, botón Stop visible; al terminar la URL cambia a `/ai/chat/{id}` y el mensaje queda persistido tras recargar.
4. Rail: agrupación, búsqueda filtra, renombrar, fijar (sube a "Fijados"), eliminar con confirmación.
5. Hilo: copiar, regenerar (reemplaza el último par), editar mensaje de usuario (trunca y re-stream), scroll al final.
6. Tool calls: pedir "¿cómo va mi entrenamiento?" y ver el chip "Consultando entrenamientos".
7. Citas: si el proveedor emite `citation`, ver chips `[1]` y panel de Fuentes; click hace scroll/resalta.
8. Responsive: `<lg` el rail abre en Sheet; sin clipping; composer sticky.
9. FAB en cualquier página navega a `/ai/chat`.
10. 403/404: abrir `/ai/chat/{id}` de otro usuario → 404.

- [ ] **Step 3: Documentar resultados**

Añadir al reporte QA una sección `## AI Chat Core (2026-09-24)` con: entorno, pasos verificados, resultado por punto, capturas referenciadas y cualquier incidencia con su decisión.

- [ ] **Step 4: Commit**

```bash
git add docs/qa/playwright-report.md
git commit -m "docs(qa): record ai chat core verification results"
```

---

## Cobertura del spec

| Requisito del spec | Task |
|---|---|
| Migración de columnas + modelos + factories | T1 |
| `ChatService` (hilos, regenerate, edit, truncado, modelos) | T2 |
| Policy, requests, resources, páginas y rutas base | T3 |
| SSE con evento `thread`, send/regenerate/edit | T4 |
| Endpoint de modelos con cache y refresco | T5 |
| Parser SSE, hook, markdown, citas, estilos hljs | T6 |
| Layout, rail, home, composer, picker | T7 |
| Vista de hilo, acciones, FAB, limpieza | T8 |
| Fix de API key + invalidación de cache | T9 |
| QA final | T10 |

