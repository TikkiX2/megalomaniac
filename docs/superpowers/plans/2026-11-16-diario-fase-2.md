# Diario — Fase 2: candado y reflexión — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que las memorias privadas no lleguen al cliente sin un desbloqueo de contraseña de 15 minutos, y que el diario devuelva una reflexión semanal guardada, generada por la IA con datos públicos y pedible desde el chat.

**Architecture:** `JournalAccess` guarda el desbloqueo en la sesión y los controladores sanean props con él. `JournalReflectionService` construye un payload público acotado, corre por `AiInsightRunner` (`module:journal`) con fallback determinístico y persiste una fila por semana ISO. `JournalRecapTool` expone el recap al chat.

**Tech Stack:** Laravel 12, Inertia v2, React 19, TypeScript, Tailwind v4, Wayfinder, Pest 4, Postgres, laravel/ai, spatie/laravel-medialibrary.

**Spec:** `docs/superpowers/specs/2026-11-16-diario-fase-2-design.md`

## Global Constraints

- Sin dependencias PHP ni JS nuevas.
- No usar `DB::table()`/queries crudas en código de aplicación; `DB::transaction` sí (patrón existente).
- Timestamps en UTC; semanas y fechas en `User::localTimezone()`; `config('app.timezone')` sigue en UTC.
- Todo bajo `middleware(['auth','verified'])`, scoped por `user_id`; un recurso ajeno da 404.
- **Mientras está bloqueado, el texto privado nunca se serializa**: ni en props de Inertia, ni en HTML, ni en un prompt.
- `423 Locked` sólo para respuestas binarias (fotos) y peticiones sin HTML; las mutaciones por Inertia redirigen con error en `password`.
- UI en castellano, tokens Ember, targets ≥44px, foco visible.
- Toda ruta nueva con nombre `journal.*`; URLs literales en el frontend.
- `vendor/bin/pint --format agent <paths>` (no `--dirty`: la imagen no tiene git).
- Después de tocar frontend: `npm run build`, `npm run types`, eslint acotado (el repo está rojo en lint global).
- Un commit por tarea.

## Review Focus

- Con el candado cerrado, ningún prop de día/momento/timeline contiene `body`, `title`, `people` ni fotos privadas (test que falla si se filtra).
- El candado vence a los 900 s exactos y no antes (test con `travel`).
- Una foto privada responde 423 bloqueada, 200 desbloqueada y 404 si es de otro usuario.
- El payload de la IA no incluye ninguna memoria privada (test con una privada sembrada que no debe aparecer).
- Si el proveedor falla, se guarda el fallback y la respuesta es 200 (nunca 500).

---

### Task 1: Sesión de desbloqueo y aviso

**Files:**
- Create: `app/Services/Journal/JournalAccess.php`
- Create: `app/Http/Controllers/Journal/UnlockController.php`
- Modify: `routes/journal.php`
- Modify: `app/Http/Controllers/Journal/DiaryController.php` (props `locked`, `unlock_expires_at`, `has_private`)
- Create: `resources/js/components/journal/LockedNotice.tsx`
- Modify: `resources/js/pages/journal/Index.tsx` (render del aviso)
- Test: `tests/Feature/Journal/JournalUnlockTest.php`

**Interfaces:**
- Consumes: `User` autenticado, `Hash`.
- Produces:
  - `JournalAccess::unlocked(Request $request): bool`
  - `JournalAccess::unlock(Request $request): void` (900 s)
  - `JournalAccess::lock(Request $request): void`
  - `JournalAccess::expiresAt(Request $request): ?CarbonImmutable`
  - Ruta `journal.unlock` (`POST /diario/desbloquear`, `throttle:5,1`)
  - Props de página `locked: bool`, `unlock_expires_at: string|null`, `has_private: bool`
  - `LockedNotice` props `{ expiresAt: string|null; action: string }`

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('rechaza una contraseña incorrecta', function () {
    $this->post('/diario/desbloquear', ['password' => 'mala'])
        ->assertSessionHasErrors('password');

    expect(session('journal_unlocked_until'))->toBeNull();
});

it('desbloquea por 15 minutos con la contraseña correcta', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');

    $this->post('/diario/desbloquear', ['password' => 'password'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(session('journal_unlocked_until'))->toBe(now()->addSeconds(900)->timestamp);
    $this->get('/diario')->assertInertia(fn ($page) => $page->where('locked', false));
});

it('vuelve a bloquear a los 900 segundos', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');
    $this->post('/diario/desbloquear', ['password' => 'password']);

    Carbon::setTestNow('2026-11-16 10:14:59');
    $this->get('/diario')->assertInertia(fn ($page) => $page->where('locked', false));

    Carbon::setTestNow('2026-11-16 10:15:01');
    $this->get('/diario')->assertInertia(fn ($page) => $page->where('locked', true));
});

it('limita a cinco intentos por minuto', function () {
    foreach (range(1, 5) as $i) {
        $this->post('/diario/desbloquear', ['password' => 'mala'])->assertSessionHasErrors('password');
    }

    $this->post('/diario/desbloquear', ['password' => 'mala'])->assertStatus(429);
});

it('el logout vuelve a bloquear', function () {
    $this->post('/diario/desbloquear', ['password' => 'password']);
    $this->post('/logout');

    expect(session('journal_unlocked_until'))->toBeNull();
});

it('redirige al login si no hay sesión', function () {
    auth()->logout();

    $this->post('/diario/desbloquear', ['password' => 'password'])->assertRedirect('/login');
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalUnlockTest`
Expected: FAIL 404 en `POST /diario/desbloquear`.

- [ ] **Step 3: Implementar el servicio y la ruta**

`JournalAccess` sin estado, con las cuatro firmas. `unlock()` usa `now()->addSeconds(900)->timestamp`; `expiresAt()` devuelve `CarbonImmutable::createFromTimestamp($ts)` sólo si sigue vigente.

`UnlockController@store(Request)`: valida `password` (`required|string`), `Hash::check`, en éxito `unlock()` + `back()->with('success', 'Diario desbloqueado por 15 minutos.')`, en fallo `back()->withErrors(['password' => 'Contraseña incorrecta.'])`.

Ruta antes de `{date}`:

```php
Route::post('desbloquear', [UnlockController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('unlock');
```

`DiaryController@render` agrega `locked` (`! $access->unlocked($request)`), `unlock_expires_at` y `has_private` (existe al menos un día o momento privado del usuario en esa fecha, con `where('is_private', true)->exists()`).

- [ ] **Step 4: Implementar `LockedNotice` y montarlo**

Card con `Lock` de lucide, texto "Hay memorias privadas en este día", formulario `useForm({ password: '' })` que postea a `/diario/desbloquear`, input `type=password`, botón `min-h-11`, error inline bajo el input y link a `/forgot-password`. En `Index.tsx`, se renderiza arriba de la espina cuando `locked && has_private`. Al éxito (`onSuccess`), `router.reload()`.

- [ ] **Step 5: Correr tests y frontend**

Run: `php artisan test --compact --filter=JournalUnlockTest`
Expected: PASS (6 tests).

Run: `npm run build && npm run types`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --format agent app/Services/Journal/JournalAccess.php app/Http/Controllers/Journal/UnlockController.php app/Http/Controllers/Journal/DiaryController.php routes/journal.php tests/Feature/Journal/JournalUnlockTest.php
git add app/Services/Journal/JournalAccess.php app/Http/Controllers/Journal/UnlockController.php app/Http/Controllers/Journal/DiaryController.php routes/journal.php resources/js/components/journal/LockedNotice.tsx resources/js/pages/journal/Index.tsx tests/Feature/Journal/JournalUnlockTest.php
git commit -m "feat(journal): desbloqueo de 15 minutos para memorias privadas"
```

---

### Task 2: Día saneado y mutaciones privadas

**Files:**
- Modify: `app/Http/Controllers/Journal/DiaryController.php`
- Modify: `app/Http/Controllers/Journal/MomentController.php`
- Modify: `app/Services/Journal/JournalService.php` (guard de mutación reutilizable)
- Modify: `resources/js/components/journal/MomentCard.tsx`, `MomentDialog.tsx`, `DayEditor.tsx`
- Test: `tests/Feature/Journal/JournalLockedContentTest.php`

**Interfaces:**
- Consumes: `JournalAccess` de Task 1.
- Produces:
  - Prop de día `locked: bool`; de momento `locked: bool`.
  - `JournalAccess::assertUnlockedForPrivate(Request $request, bool $isPrivate): void` que lanza `ValidationException` con `password` cuando corresponde (y `abort(423)` en peticiones sin HTML).
  - Contrato: bloqueado → `body`/`title`/`people`/`photos` privados en `null`/`[]`.

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('no expone el texto privado bloqueado', function () {
    $day = JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-16', 'body' => 'secreto del día', 'is_private' => true,
    ]);
    $moment = JournalMoment::factory()->create([
        'user_id' => $this->user->id, 'journal_day_id' => $day->id,
        'title' => 'título privado', 'body' => 'cuerpo privado', 'is_private' => true,
    ]);

    $this->get('/diario/2026-11-16')->assertOk()->assertInertia(fn ($page) => $page
        ->component('journal/Index')
        ->where('day.body', null)
        ->where('day.locked', true)
        ->where('moments.0.title', null)
        ->where('moments.0.body', null)
        ->where('moments.0.locked', true));

    expect($moment->body)->toBe('cuerpo privado');
});

it('muestra el texto privado desbloqueado', function () {
    JournalDay::factory()->for($this->user)->create([
        'date' => '2026-11-16', 'body' => 'secreto del día', 'is_private' => true,
    ]);

    $this->post('/diario/desbloquear', ['password' => 'password']);

    $this->get('/diario/2026-11-16')->assertInertia(fn ($page) => $page
        ->where('day.body', 'secreto del día')
        ->where('day.locked', false));
});

it('no deja editar ni borrar un momento privado bloqueado', function () {
    $moment = JournalMoment::factory()->create([
        'user_id' => $this->user->id, 'body' => 'privado', 'is_private' => true,
    ]);

    $this->patch("/diario/momentos/{$moment->id}", ['body' => 'cambio'])
        ->assertSessionHasErrors('password');
    $this->delete("/diario/momentos/{$moment->id}")->assertSessionHasErrors('password');
    $this->assertDatabaseHas('journal_moments', ['id' => $moment->id, 'body' => 'privado']);
});

it('permite editar una memoria pública sin desbloquear', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id, 'body' => 'público']);

    $this->patch("/diario/momentos/{$moment->id}", ['body' => 'editado'])->assertSessionHasNoErrors();

    expect($moment->fresh()->body)->toBe('editado');
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalLockedContentTest`
Expected: FAIL: `day.body` llega con texto.

- [ ] **Step 3: Implementar el saneamiento**

En `DiaryController`, calcular `$locked` una vez y mapear:

```php
$dayLocked = $day?->is_private === true && $locked;
'body' => $dayLocked ? null : $day?->body,
'locked' => $dayLocked,
```

Para momentos: `$momentLocked = $moment->is_private && $locked`; `title`/`body` null, `people => []`, `photos => []`, `locked => true`. El prop `occurred_at` y `is_private` se conservan.

En `MomentController@update/destroy`: si `$moment->is_private && ! $access->unlocked($request)` → `$access->assertUnlockedForPrivate($request, true)`. Igual en `PhotoController` para `store`/`destroy`.

En `DiaryController@update`: si la fila **existente** es privada y está bloqueada, mismo error; crear una nueva entrada privada estando bloqueado se permite.

- [ ] **Step 4: Implementar los estados bloqueados en la UI**

`MomentCard`: si `moment.locked`, candado + "Privado — desbloqueá para leer" en lugar de título/cuerpo/chips/fotos. `MomentDialog`: si el momento está bloqueado, campos deshabilitados y botón de guardar oculto. `DayEditor`: `readOnly` + candado cuando `day.locked`, conservando el textarea con placeholder "Contenido privado". `locked` viaja en los tipos de payload.

- [ ] **Step 5: Correr tests y frontend**

Run: `php artisan test --compact --filter=JournalLockedContentTest`
Expected: PASS (4 tests).

Run: `npm run build && npm run types`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --format agent app/Http/Controllers/Journal app/Services/Journal tests/Feature/Journal/JournalLockedContentTest.php
git add app/Http/Controllers/Journal app/Services/Journal resources/js tests/Feature/Journal/JournalLockedContentTest.php
git commit -m "feat(journal): props saneadas y bloqueo de edición privada"
```

---

### Task 3: Fotos y timeline bloqueados

**Files:**
- Modify: `app/Http/Controllers/Journal/PhotoController.php`
- Modify: `app/Http/Controllers/Journal/TimelineController.php`
- Modify: `resources/js/components/journal/PhotoStrip.tsx`, `MemoryCard.tsx`
- Modify: `resources/js/pages/journal/All.tsx` (banner de bloqueo)
- Test: `tests/Feature/Journal/JournalLockedPhotosTest.php`

**Interfaces:**
- Consumes: `JournalAccess`, `PhotoController` y `TimelineController` existentes.
- Produces: foto privada bloqueada → `423`; desbloqueada → `200`; timeline con `locked: bool` y `excerpt`/`photo_url` nulos para días sin contenido público.

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('devuelve 423 para una foto privada bloqueada y 200 desbloqueada', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id, 'is_private' => true]);
    $moment->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos', 'local');
    $media = $moment->fresh()->getFirstMedia('photos');

    $this->get("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertStatus(423);

    $this->post('/diario/desbloquear', ['password' => 'password']);
    $this->get("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertOk();
});

it('una foto ajena sigue dando 404 aunque esté desbloqueado', function () {
    $this->post('/diario/desbloquear', ['password' => 'password']);
    $moment = JournalMoment::factory()->create();
    $moment->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos', 'local');

    $this->get("/diario/momentos/{$moment->id}/fotos/{$moment->fresh()->getFirstMedia('photos')->id}")
        ->assertNotFound();
});

it('el timeline tapa el extracto y la foto de un día sólo privado', function () {
    $day = JournalDay::factory()->for($this->user)->create(['date' => '2026-11-10', 'body' => 'privado', 'is_private' => true]);

    $this->get('/diario/todas')->assertInertia(fn ($page) => $page
        ->where('days.data.0.excerpt', null)
        ->where('days.data.0.photo_url', null)
        ->where('days.data.0.locked', true));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalLockedPhotosTest`
Expected: FAIL: la foto devuelve 200.

- [ ] **Step 3: Implementar**

`PhotoController@show`: después de verificar pertenencia, si `$moment->is_private && ! $access->unlocked($request)` → `abort(423)`. `store`/`destroy` sobre momento privado bloqueado: `$access->assertUnlockedForPrivate($request, true)`.

`TimelineController`: `$locked = ! $access->unlocked($request)`; un día sin contenido público (`!$is_private && (body||mood)` falso y sin momentos públicos) expone `excerpt: null`, `photo_url: null`, `locked: true`. Los días con contenido público no cambian.

UI: `PhotoStrip` no pide URLs de fotos privadas bloqueadas (ya no vienen); si `locked`, celda con candado. `MemoryCard` muestra candado y "Privado — desbloqueá para leer" cuando `locked`. `All.tsx` monta `LockedNotice` como banner cuando algún día viene bloqueado.

- [ ] **Step 4: Correr tests y frontend**

Run: `php artisan test --compact --filter='JournalLockedPhotosTest|JournalTimelineTest|JournalPhotosTest'`
Expected: PASS.

Run: `npm run build && npm run types`
Expected: sin errores.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --format agent app/Http/Controllers/Journal tests/Feature/Journal/JournalLockedPhotosTest.php
git add app/Http/Controllers/Journal resources/js tests/Feature/Journal/JournalLockedPhotosTest.php
git commit -m "feat(journal): fotos y timeline bloqueados por privacidad"
```

---

### Task 4: Modelo y servicio de reflexión con fallback

**Files:**
- Create: `database/migrations/2026_11_16_000001_create_journal_reflections_table.php`
- Create: `app/Models/JournalReflection.php`
- Create: `database/factories/JournalReflectionFactory.php`
- Create: `app/Services/Journal/JournalReflectionService.php`
- Modify: `app/Models/User.php` (`journalReflections()`)
- Test: `tests/Feature/Journal/JournalReflectionDataTest.php`

**Interfaces:**
- Consumes: `JournalDay`, `JournalMoment`, `JournalStats`, `JournalService::today`.
- Produces:
  - `JournalReflection` fillable `user_id, week_start, body, source, model, generated_at`; casts `week_start:date:Y-m-d`, `generated_at:datetime`.
  - `JournalReflectionService::weekStart(User $user): CarbonImmutable`
  - `JournalReflectionService::forWeek(User $user, CarbonImmutable $weekStart): ?JournalReflection`
  - `JournalReflectionService::recap(User $user, ?string $weekStart = null): ?JournalReflection`
  - `JournalReflectionService::generate(User $user, CarbonImmutable $weekStart): JournalReflection`
  - `JournalReflectionService::generateWithAi(User $user, string $payload): ?array{body: string, model: string|null}` (en Task 4 devuelve `null`; Task 5 lo implementa)
  - `JournalReflectionService::publicPayload(User $user, CarbonImmutable $weekStart): array{text: string, days: int, moods: array<int, int>}`
  - `JournalReflectionService::fallback(User $user, CarbonImmutable $weekStart): string`

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('guarda una reflexión por usuario y semana', function () {
    $service = app(JournalReflectionService::class);
    $week = $service->weekStart($this->user);

    $first = $service->generate($this->user, $week);
    $second = $service->generate($this->user, $week);

    expect(JournalReflection::count())->toBe(1)
        ->and($first->id)->toBe($second->id)
        ->and($second->source)->toBe('fallback');
});

it('el payload sólo incluye memorias públicas', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);
    JournalDay::factory()->for($this->user)->create([
        'date' => $week->toDateString(), 'body' => 'público de la semana', 'is_private' => false,
    ]);
    JournalDay::factory()->for($this->user)->create([
        'date' => $week->addDay()->toDateString(), 'body' => 'SECRETO PRIVADO', 'is_private' => true,
    ]);

    $payload = app(JournalReflectionService::class)->publicPayload($this->user, $week);

    expect($payload['text'])->toContain('público de la semana')
        ->not->toContain('SECRETO PRIVADO');
});

it('el fallback arma un resumen con datos de la semana', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);
    JournalDay::factory()->for($this->user)->create([
        'date' => $week->toDateString(), 'body' => 'Día uno', 'mood' => 4, 'tags' => ['casa'],
    ]);

    $reflection = app(JournalReflectionService::class)->generate($this->user, $week);

    expect($reflection->body)->toContain('Día uno')
        ->and($reflection->body)->toContain('Sin IA');
});

it('una semana sin datos públicos devuelve null', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');

    expect(app(JournalReflectionService::class)->recap($this->user))->toBeNull();
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalReflectionDataTest`
Expected: FAIL: clase no encontrada.

- [ ] **Step 3: Implementar**

Migración con `unique(['user_id','week_start'])` y FK cascade.

`weekStart()`: `JournalService::today($user)` → `startOfWeek(CarbonInterface::MONDAY)`.

`publicPayload()`: días del rango con `is_private = false`; cuerpo truncado con `Str::limit(..., 500)`; momentos públicos (`is_private = false`) del rango, `title`/`body` truncados a 200; etiquetas y personas sólo de memorias públicas. Arma un texto con encabezados `Día 2026-11-16 · ánimo 4/5 · tags: casa` y líneas de momentos. Corta el texto final a 7000 caracteres.

`fallback()`: días escritos, racha (`JournalStats::streak`), promedio de ánimo con etiqueta (`Bajo…Muy bien`), hasta 3 tags más repetidos y hasta 3 momentos públicos con fecha + primer renglón; cierra con "Sin IA configurada; este resumen se armó con tus datos.".

`generate()`: sin IA (`!$user->ai_enabled`) o payload vacío → fallback; si hay IA, llama a `generateWithAi()` (stub `null` en esta tarea) y usa su resultado o el fallback. Guarda con `updateOrCreate(['user_id','week_start'], [...])` y `generated_at = now()`.

`recap()`: valida `week_start` opcional (`Y-m-d`, lunes, dentro de las últimas 8 semanas); si no viene, `weekStart()`. Si no hay fila guardada y `publicPayload()` no tiene días ni momentos públicos, devuelve `null` (no crea una reflexión vacía). Si hay datos, devuelve la guardada o genera.

- [ ] **Step 4: Correr tests**

Run: `php artisan test --compact --filter=JournalReflectionDataTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --format agent app/Models/JournalReflection.php app/Services/Journal/JournalReflectionService.php database/migrations/2026_11_16_000001_create_journal_reflections_table.php database/factories/JournalReflectionFactory.php app/Models/User.php tests/Feature/Journal/JournalReflectionDataTest.php
git add database/migrations/2026_11_16_000001_create_journal_reflections_table.php app/Models/JournalReflection.php database/factories/JournalReflectionFactory.php app/Services/Journal/JournalReflectionService.php app/Models/User.php tests/Feature/Journal/JournalReflectionDataTest.php
git commit -m "feat(journal): reflexión semanal persistida con fallback"
```

---

### Task 5: Camino de IA, ruta y UI de Repaso

**Files:**
- Modify: `app/Services/Journal/JournalReflectionService.php` (`generateWithAi`)
- Create: `app/Http/Controllers/Journal/ReflectionController.php`
- Modify: `routes/journal.php`
- Modify: `app/Http/Controllers/Journal/ReviewController.php` (props `weekStart`, `reflection`, `reflections`)
- Create: `resources/js/components/journal/ReflectionCard.tsx`
- Modify: `resources/js/pages/journal/Review.tsx`
- Test: `tests/Feature/Journal/JournalReflectionAiTest.php`

**Interfaces:**
- Consumes: `AiInsightRunner`, `MegalomaniacAgent`, `JournalReflectionService` de Task 4.
- Produces:
  - `POST /diario/reflexiones` (`journal.reflections.store`, `throttle:3,1`), body `week_start` opcional.
  - Props de Repaso `weekStart: string`, `reflection: {...}|null`, `reflections: array` (8, descendente).
  - `ReflectionCard` props `{ reflection, weekStart }`.

- [ ] **Step 1: Escribir los tests que fallan**

```php
function journalChatBody(string $content): array
{
    return [
        'model' => 'qa',
        'choices' => [['message' => ['content' => $content], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ];
}

it('genera la reflexión con IA y guarda el modelo', function () {
    $this->user->update(['ai_enabled' => true]);
    AiProvider::factory()->for($this->user)->create([
        'name' => 'QA', 'url' => 'https://ai.example.com/v1', 'enabled' => true,
    ]);
    Http::fake(['ai.example.com/*' => Http::response(journalChatBody("Temas:\n- casa\n\nÁnimo: estable."), 200)]);
    JournalDay::factory()->for($this->user)->create(['date' => now()->toDateString(), 'body' => 'Día de prueba']);

    $this->post('/diario/reflexiones')->assertRedirect();

    $reflection = JournalReflection::firstOrFail();

    expect($reflection->source)->toBe('ai')
        ->and($reflection->body)->toContain('Temas')
        ->and($reflection->model)->not->toBeNull();
});

it('cae al fallback si el proveedor falla', function () {
    $this->user->update(['ai_enabled' => true]);
    AiProvider::factory()->for($this->user)->create(['enabled' => true, 'url' => 'https://ai.example.com/v1']);
    Http::fake(['ai.example.com/*' => Http::response('boom', 500)]);
    JournalDay::factory()->for($this->user)->create(['date' => now()->toDateString(), 'body' => 'Día de prueba']);

    $this->post('/diario/reflexiones')->assertRedirect();

    expect(JournalReflection::firstOrFail()->source)->toBe('fallback');
});

it('limita la generación a tres por minuto', function () {
    foreach (range(1, 3) as $i) {
        $this->post('/diario/reflexiones')->assertRedirect();
    }

    $this->post('/diario/reflexiones')->assertStatus(429);
});

it('repaso trae la reflexión guardada y el historial', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);
    JournalReflection::factory()->for($this->user)->create(['week_start' => $week, 'body' => 'Resumen guardado']);

    $this->get('/diario/repaso')->assertInertia(fn ($page) => $page
        ->component('journal/Review')
        ->where('weekStart', $week->toDateString())
        ->where('reflection.body', 'Resumen guardado')
        ->has('reflections', 1));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalReflectionAiTest`
Expected: FAIL 404 en `POST /diario/reflexiones`.

- [ ] **Step 3: Implementar el camino de IA**

`generateWithAi(User $user, string $payload): ?array{body: string, model: string|null}`:

- Prompt fijo en castellano (el de la spec) + `$payload`.
- `app(AiInsightRunner::class)->run($user, 'journal', fn (string $key, string $model, ?string $block) => (new MegalomaniacAgent($user))->forUser($user)->withPersonalization($block)->prompt($prompt, provider: $key, model: $model))`.
- `$outcome->isEmpty()` → `null` (el llamador usa fallback). Si hay texto, devuelve `['body' => trim($text), 'model' => $model]`.
- `generate()` usa el resultado o el fallback, con `source` correspondiente.

`ReflectionController@store`: valida `week_start` opcional (`date_format:Y-m-d`, lunes, últimas 8 semanas), llama a `generate()` y vuelve con flash "Reflexión guardada.". `throttle:3,1`.

`ReviewController`: agrega `weekStart`, `reflection` (semana actual o null) y `reflections` (`latest('week_start')->limit(8)`).

- [ ] **Step 4: Implementar la UI**

`ReflectionCard`: título "Reflexión semanal", rango de la semana (`10–16 nov`), badge `IA · {model}` o "Sin IA", cuerpo en `whitespace-pre-line text-[15px] leading-relaxed`, fecha de generación, botón "Regenerar" con confirmación. Vacío: una línea explicativa + botón "Resumir esta semana" (`useForm().post('/diario/reflexiones')`). Historial en un `Collapsible` con las 8 semanas.

- [ ] **Step 5: Correr tests y frontend**

Run: `php artisan test --compact --filter=JournalReflectionAiTest`
Expected: PASS (4 tests).

Run: `npm run build && npm run types`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --format agent app/Http/Controllers/Journal/ReflectionController.php app/Http/Controllers/Journal/ReviewController.php app/Services/Journal/JournalReflectionService.php routes/journal.php tests/Feature/Journal/JournalReflectionAiTest.php
git add app/Http/Controllers/Journal app/Services/Journal/JournalReflectionService.php routes/journal.php resources/js tests/Feature/Journal/JournalReflectionAiTest.php
git commit -m "feat(journal): reflexión semanal con IA y su pantalla"
```

---

### Task 6: Tool `journal.recap` para el chat

**Files:**
- Create: `app/Ai/Tools/JournalRecapTool.php`
- Modify: `app/Ai/Tools/ToolCatalog.php` (grupo y `make()`)
- Modify: `config/ai_tools.php` (keywords)
- Modify: `app/Ai/Enums/AiScope.php` (`ModuleJournal`)
- Test: `tests/Feature/Ai/JournalRecapToolTest.php`

**Interfaces:**
- Consumes: `JournalReflectionService::recap`, `User`.
- Produces:
  - `JournalRecapTool implements Tool` con `description()`, `handle(Request)`, `schema(JsonSchema)`.
  - Grupo `journal` en `ToolCatalog::groups()` con label "Diario".
  - Scope `AiScope::ModuleJournal = 'module:journal'` con label "Diario".

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('devuelve la reflexión guardada de la semana', function () {
    $week = app(JournalReflectionService::class)->weekStart($this->user);
    JournalReflection::factory()->for($this->user)->create([
        'week_start' => $week, 'body' => 'Resumen de la semana',
    ]);

    $tool = new JournalRecapTool($this->user, app(JournalReflectionService::class));
    $result = (string) $tool->handle(new Request(['week_start' => $week->toDateString()]));

    expect($result)->toContain('Resumen de la semana');
});

it('genera la semana si no había resumen', function () {
    JournalDay::factory()->for($this->user)->create(['date' => now()->toDateString(), 'body' => 'Día']);

    $tool = new JournalRecapTool($this->user, app(JournalReflectionService::class));
    $result = (string) $tool->handle(new Request([]));

    expect(JournalReflection::count())->toBe(1)
        ->and($result)->toContain('Sin IA');
});

it('responde con un mensaje si la semana no es lunes', function () {
    $tool = new JournalRecapTool($this->user, app(JournalReflectionService::class));

    $result = (string) $tool->handle(new Request(['week_start' => '2026-11-17']));

    expect($result)->toContain('lunes');
});

it('el grupo journal está en el catálogo y en las keywords', function () {
    expect(ToolCatalog::isValidGroup('journal'))->toBeTrue()
        ->and(config('ai_tools.keywords.journal'))->toContain('diario')
        ->and(AiScope::fromModuleKey('journal'))->toBe(AiScope::ModuleJournal);
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalRecapToolTest`
Expected: FAIL: clase no encontrada.

- [ ] **Step 3: Implementar**

`JournalRecapTool`: `description()` de la spec; `schema()` con `week_start` string opcional; `handle()` valida lunes (`CarbonImmutable::parse($value)->isMonday()`); si no es lunes devuelve `"La semana debe ser un lunes en formato Y-m-d."` (no lanza). Llama a `recap()`; si no hay reflexión devuelve `"No hay memorias públicas en esa semana."`; si hay, `"Semana del {Y-m-d}\n\n{body}"`. No lee memorias.

`ToolCatalog::groups()`: `'journal' => ['label' => 'Diario', 'tools' => [JournalRecapTool::class]]`; `make()`: `JournalRecapTool::class => new JournalRecapTool($user, app(JournalReflectionService::class))`.

`config/ai_tools.php`: `'journal' => ['diario', 'memoria', 'memorias', 'recap', 'reflexion', 'reflexión']`.

`AiScope`: agregar el caso `ModuleJournal`, su label y su inclusión en `section()`; `moduleSuggestions('journal')` con `['¿Cómo fue mi semana?', 'Resumí mi diario', '¿Qué escribí estos días?']`.

- [ ] **Step 4: Correr tests**

Run: `php artisan test --compact --filter='JournalRecapToolTest|AiProviderModelTest|AiCallerScopesTest'`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --format agent app/Ai/Tools/JournalRecapTool.php app/Ai/Tools/ToolCatalog.php app/Ai/Enums/AiScope.php config/ai_tools.php tests/Feature/Ai/JournalRecapToolTest.php
git add app/Ai/Tools/JournalRecapTool.php app/Ai/Tools/ToolCatalog.php app/Ai/Enums/AiScope.php config/ai_tools.php tests/Feature/Ai/JournalRecapToolTest.php
git commit -m "feat(journal): tool de recap semanal para el chat"
```

---

### Task 7: Verificación final

**Files:**
- Test: ninguno nuevo; se corre la suite completa.
- Modify: el plan no toca más código salvo defectos encontrados.

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: rama lista para revisión final.

- [ ] **Step 1: Suite completa**

Run: `php artisan test --compact`
Expected: PASS (base 2041 + nuevos; 0 fallos).

- [ ] **Step 2: Frontend**

Run: `npm run build && npm run types`
Expected: sin errores.

- [ ] **Step 3: Smoke HTTP**

Levantar el server del worktree y confirmar que `/diario`, `/diario/repaso`, `/diario/desbloquear` y `/diario/reflexiones` responden 302 a login sin sesión y 200/redirect con sesión. Reportar códigos.

- [ ] **Step 4: QA visual**

No hay navegador en esta máquina: documentar el límite y listar lo que queda para inspección manual (aviso de bloqueo en día y timeline, estados bloqueados de tarjetas y fotos, sección de reflexión vacía/con datos/historial).

- [ ] **Step 5: Commit final**

Si no hubo cambios de código, no hay commit. Si el smoke obligó a corregir algo, commit con el fix y el test que lo cubre.

---

## Después de la Fase 2

Con esto el diario queda cerrado como producto: escribir, encontrar, proteger y releer. Lo que sigue, si alguna vez se quiere, es otra conversación: preguntas libres sobre memorias, exportación, cifrado de imágenes.
