# Diario — Fase 1: escribir y encontrar — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un área `/diario` donde el usuario escribe una entrada por día y momentos sueltos con ánimo, etiquetas, fotos y personas, los encuentra por calendario, timeline, búsqueda y "tal día como hoy", y todo el texto privado queda cifrado en reposo.

**Architecture:** Ancla `journal_days` + `journal_moments` (enfoque A del spec). Escritura vía `JournalService` (resuelve día, re-ancla momentos, poda días vacíos). Lectura vía `JournalStats` (racha, mes). Cifrado por fila con el cast `JournalBody` sobre `body` y `title`. Fotos con `spatie/laravel-medialibrary` en disco `local`, servidas por ruta autenticada.

**Tech Stack:** Laravel 12, Inertia v2, React 19, TypeScript, Tailwind v4, Wayfinder, Pest 4, PostgreSQL, spatie/laravel-medialibrary 11.

**Spec:** `docs/superpowers/specs/2026-11-15-diario-design.md`

## Global Constraints

- Sin dependencias PHP ni JS nuevas; media library ya está instalado.
- No usar `DB::` en código de aplicación (sí en tests); Eloquent con `casts()`.
- Timestamps en UTC; fechas de diario en `User::localTimezone()`; `config('app.timezone')` no se toca.
- UI en castellano; tokens Ember (`bg-card`, `border-border`, `text-muted-foreground`, `bg-primary`); nada de hex nuevos.
- Fotos siempre en disco `local` y servidas por ruta autenticada; ninguna URL pública.
- Toda ruta nueva bajo `middleware(['auth', 'verified'])` y con dueño verificado por policy o query.
- Wayfinder (`@/routes/journal`) en el frontend una vez generadas las rutas; si el archivo todavía no existe, usar el path literal y migrarlo al primer `npm run build`.
- `vendor/bin/pint --dirty --format agent` después de tocar PHP.
- Después de tocar frontend: `npm run build` (genera Wayfinder), `npm run types`, `npm run lint`.
- Un commit por tarea; no commitear los `qa-*.png` sin trackear que ya existen en la raíz.

## Review Focus

- Escribir a las 23:30 del 15 en la zona del usuario debe quedar en el día 15, no en el 16 (el test de zona horaria de la Tarea 2 lo fija).
- Marcar privado un día ya escrito debe dejar ciphertext en la DB; volver a público debe dejar texto plano (Tarea 1).
- Borrar el último momento de un día sin texto debe eliminar la fila del día y sacarlo del calendario y del timeline (Tarea 3).
- Abrir un momento o una foto de otro usuario debe dar 404, no mostrar ni filtrar datos (Tareas 3 y 4).
- Buscar una palabra que sólo existe en una memoria privada no debe devolver nada (Tarea 8).

---

### Task 1: Esquema, modelos y cifrado

**Files:**
- Create: `database/migrations/2026_11_15_000001_create_journal_days_table.php`
- Create: `database/migrations/2026_11_15_000002_create_journal_moments_table.php`
- Create: `database/migrations/2026_11_15_000003_create_journal_moment_person_table.php`
- Create: `app/Casts/JournalBody.php`
- Create: `app/Models/JournalDay.php`
- Create: `app/Models/JournalMoment.php`
- Create: `app/Policies/JournalDayPolicy.php`
- Create: `app/Policies/JournalMomentPolicy.php`
- Create: `database/factories/JournalDayFactory.php`
- Create: `database/factories/JournalMomentFactory.php`
- Modify: `app/Models/User.php` (relaciones `journalDays()` y `journalMoments()`)
- Test: `tests/Feature/Journal/JournalDataTest.php`

**Interfaces:**
- Consumes: `User::localTimezone()`, `Person`.
- Produces: `JournalDay` (fillable `user_id,date,body,mood,tags,is_private`; casts `date:Y-m-d`, `body` → `JournalBody`, `tags` array, `mood` int, `is_private` bool; `moments(): HasMany`; `hasContent(): bool`; scope `forUser`). `JournalMoment` (fillable `user_id,journal_day_id,occurred_at,title,body,tags,is_private`; casts `occurred_at` datetime, `title`/`body` → `JournalBody`; `day(): BelongsTo`; `people(): BelongsToMany` con `withTimestamps()`; implementa `HasMedia`). Policies `view/update/delete` comparan `user_id`.

- [ ] **Step 1: Escribir los tests que fallan**

`tests/Feature/Journal/JournalDataTest.php`:

```php
<?php

use App\Models\JournalDay;
use App\Models\JournalMoment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('cifra el cuerpo privado y lo devuelve legible', function () {
    $user = User::factory()->create();
    $day = JournalDay::factory()->for($user)->create([
        'date' => '2026-11-15',
        'body' => 'algo íntimo',
        'is_private' => true,
    ]);

    $raw = DB::table('journal_days')->where('id', $day->id)->value('body');

    expect($raw)->toStartWith('enc:v1:')
        ->and($raw)->not->toContain('algo íntimo')
        ->and($day->fresh()->body)->toBe('algo íntimo');
});

it('guarda el cuerpo público en claro', function () {
    $day = JournalDay::factory()->create(['date' => '2026-11-14', 'body' => 'día normal', 'is_private' => false]);

    expect(DB::table('journal_days')->where('id', $day->id)->value('body'))->toBe('día normal');
});

it('cifra al marcar privado y descifra al volver a público', function () {
    $day = JournalDay::factory()->create(['date' => '2026-11-13', 'body' => 'secreto', 'is_private' => false]);

    $day->update(['is_private' => true]);
    expect(DB::table('journal_days')->where('id', $day->id)->value('body'))->toStartWith('enc:v1:');

    $day->refresh()->update(['is_private' => false]);
    expect(DB::table('journal_days')->where('id', $day->id)->value('body'))->toBe('secreto')
        ->and($day->fresh()->body)->toBe('secreto');
});

it('cifra también el título del momento privado', function () {
    $moment = JournalMoment::factory()->create(['title' => 'Título privado', 'body' => 'cuerpo', 'is_private' => true]);

    $raw = DB::table('journal_moments')->where('id', $moment->id)->first();

    expect($raw->title)->toStartWith('enc:v1:')
        ->and($raw->title)->not->toContain('Título privado')
        ->and($moment->fresh()->title)->toBe('Título privado');
});

it('no permite dos días del mismo usuario en la misma fecha', function () {
    $user = User::factory()->create();
    JournalDay::factory()->for($user)->create(['date' => '2026-11-12']);

    expect(fn () => JournalDay::factory()->for($user)->create(['date' => '2026-11-12']))
        ->toThrow(QueryException::class);
});

it('permite la misma fecha a usuarios distintos', function () {
    JournalDay::factory()->create(['date' => '2026-11-11']);
    JournalDay::factory()->create(['date' => '2026-11-11']);

    expect(JournalDay::where('date', '2026-11-11')->count())->toBe(2);
});

it('considera contenido el ánimo solo, pero no las etiquetas solas', function () {
    $conMood = JournalDay::factory()->create(['mood' => 4, 'body' => null]);
    $soloTags = JournalDay::factory()->create(['mood' => null, 'body' => null, 'tags' => ['viaje']]);

    expect($conMood->hasContent())->toBeTrue()
        ->and($soloTags->hasContent())->toBeFalse();
});

it('no deja que un usuario vea ni edite memorias ajenas', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $moment = JournalMoment::factory()->create(['user_id' => $owner->id]);

    expect($intruder->can('update', $moment))->toBeFalse()
        ->and($owner->can('update', $moment))->toBeTrue();
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalDataTest`
Expected: FAIL, `Class "App\Models\JournalDay" not found`.

- [ ] **Step 3: Implementar**

Migraciones con `foreignId('user_id')->constrained()->cascadeOnDelete()`:

- `journal_days`: `date`, `body` text nullable, `mood` unsignedSmallInteger nullable, `tags` json nullable, `is_private` boolean default false, `unique(['user_id','date'])`, índice `['user_id','date']`.
- `journal_moments`: `foreignId('journal_day_id')->constrained()->cascadeOnDelete()`, `occurred_at` timestamp, `title` text nullable, `body` text, `tags` json nullable, `is_private` boolean default false, índice `['journal_day_id','occurred_at']`.
- `journal_moment_person`: `moment_id` + `person_id` (FK a `journal_moments` y `people`, ambas cascade), `primary(['moment_id','person_id'])`.

`App\Casts\JournalBody` con prefijo `enc:v1:`: en `get()` descifra si el valor empieza con el prefijo, si no devuelve el valor; en `set()` cifra cuando `$model->is_private` es verdadero, si no guarda plano.

En ambos modelos, hook `booted()` → `static::saving()`: si `isDirty('is_private')` y el atributo de texto no está dirty, reasignarlo (`$model->body = $model->body;`, y `title` en el momento) para que el cast reescriba plano o cifrado. Es lo que hace que el toggle funcione sin un servicio aparte.

`hasContent(): bool` = `filled($this->body) || $this->mood !== null || $this->moments()->exists()` — las etiquetas solas **no** cuentan, tal como fija el spec.

Policies con el mismo patrón que `HealthMeasurementPolicy` (`$user->id === $model->user_id`).

`JournalMomentFactory` deriva el ancla del usuario para no crear datos incoherentes: `user_id => User::factory()` y `journal_day_id => fn (array $attributes) => JournalDay::firstOrCreate(['user_id' => $attributes['user_id'], 'date' => '2000-01-01'])->id`, con `occurred_at => now()->subDay()`. Los tests que necesitan días distintos pasan `journal_day_id` explícito.

- [ ] **Step 4: Correr y verificar que pasa**

Run: `php artisan test --compact --filter=JournalDataTest`
Expected: PASS (8 tests).

- [ ] **Step 5: Pint y commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_11_15_00000*.php app/Casts/JournalBody.php app/Models/JournalDay.php app/Models/JournalMoment.php app/Models/User.php app/Policies/Journal*.php database/factories/Journal*.php tests/Feature/Journal/JournalDataTest.php
git commit -m "feat(journal): modelo de días, momentos y cifrado por fila"
```

---

### Task 2: Servicio de escritura y página del día

**Files:**
- Create: `app/Services/Journal/JournalService.php`
- Create: `app/Http/Controllers/Journal/DiaryController.php`
- Create: `app/Http/Requests/Journal/UpdateDayRequest.php`
- Create: `routes/journal.php`
- Modify: `routes/web.php` (require del archivo nuevo)
- Create: `resources/js/pages/journal/Index.tsx`
- Create: `resources/js/components/journal/DayEditor.tsx`
- Create: `resources/js/components/journal/MoodPicker.tsx`
- Test: `tests/Feature/Journal/JournalDayPageTest.php`

**Interfaces:**
- Consumes: `JournalDay`, `JournalMoment`, policies de la Tarea 1.
- Produces:
  - `JournalService::today(User $user): CarbonImmutable`
  - `JournalService::dayFor(User $user, string $date): JournalDay`
  - `JournalService::saveDay(User $user, string $date, array $data): ?JournalDay` (null si podó el día)
  - `JournalService::pruneIfEmpty(JournalDay $day): bool`
  - `JournalService::normalizeTags(mixed $tags): array` (trim, minúsculas, dedupe, máx 8, descarta vacíos)
  - Rutas `journal.index` (`GET /diario`), `journal.show` (`GET /diario/{date}`), `journal.day.update` (`PUT /diario/{date}`).
  - Props de página: `date`, `today`, `day` (`{id, body, mood, tags, is_private}` | null), `prevDate`, `nextDate`.
  - Moods: `[{value:1,label:'Bajo'},{2,'Flojo'},{3,'Normal'},{4,'Bien'},{5,'Muy bien'}]` como constante compartida en `resources/js/components/journal/MoodPicker.tsx`.

- [ ] **Step 1: Escribir los tests que fallan**

`tests/Feature/Journal/JournalDayPageTest.php`:

```php
<?php

use App\Models\JournalDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);
    $this->actingAs($this->user);
});

it('muestra el día local del usuario aunque en UTC ya sea mañana', function () {
    Carbon::setTestNow('2026-11-16 02:30:00'); // 23:30 del 15 en Buenos Aires

    $this->get('/diario')->assertOk()->assertInertia(fn ($page) => $page
        ->component('journal/Index')
        ->where('date', '2026-11-15')
        ->where('today', '2026-11-15'));
});

it('crea la entrada del día y la actualiza sin duplicar', function () {
    $this->put('/diario/2026-11-15', [
        'body' => 'Primer día',
        'mood' => 4,
        'tags' => ['Casa', ' casa ', 'viaje'],
        'is_private' => false,
    ])->assertRedirect();

    $this->put('/diario/2026-11-15', ['body' => 'Corregido', 'mood' => 5, 'tags' => [], 'is_private' => false])
        ->assertRedirect();

    $day = JournalDay::firstOrFail();

    expect(JournalDay::count())->toBe(1)
        ->and($day->body)->toBe('Corregido')
        ->and($day->mood)->toBe(5);
});

it('normaliza y deduplica etiquetas', function () {
    $this->put('/diario/2026-11-15', ['body' => 'x', 'tags' => [' Viaje ', 'viaje', 'CASA', '', 'a', 'b', 'c', 'd', 'e', 'f', 'g'], 'is_private' => false]);

    expect(JournalDay::firstOrFail()->tags)->toBe(['viaje', 'casa', 'a', 'b', 'c', 'd', 'e', 'f']);
});

it('borra el día cuando se vacía por completo', function () {
    $day = JournalDay::factory()->for($this->user)->create(['date' => '2026-11-10', 'body' => 'algo', 'mood' => 3, 'tags' => ['x']]);

    $this->put('/diario/2026-11-10', ['body' => '', 'mood' => null, 'tags' => [], 'is_private' => false])
        ->assertRedirect();

    expect(JournalDay::whereKey($day->id)->exists())->toBeFalse();
});

it('no muestra el día de otro usuario', function () {
    JournalDay::factory()->create(['date' => '2026-11-09', 'body' => 'ajeno']);

    $this->get('/diario/2026-11-09')->assertOk()
        ->assertInertia(fn ($page) => $page->where('day', null));
});

it('rechaza un ánimo fuera de rango y un cuerpo gigante', function () {
    $this->put('/diario/2026-11-08', ['mood' => 9, 'is_private' => false])
        ->assertSessionHasErrors('mood');

    $this->put('/diario/2026-11-08', ['body' => str_repeat('a', 20001), 'is_private' => false])
        ->assertSessionHasErrors('body');
});

it('exige autenticación', function () {
    auth()->logout();

    $this->get('/diario')->assertRedirect('/login');
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalDayPageTest`
Expected: FAIL 404 en `/diario`.

- [ ] **Step 3: Implementar backend**

`routes/journal.php`, en este orden (las estáticas antes de `{date}`):

```php
Route::middleware(['auth', 'verified'])->prefix('diario')->name('journal.')->group(function () {
    Route::get('/', [DiaryController::class, 'index'])->name('index');
    Route::get('{date}', [DiaryController::class, 'show'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('show');
    Route::put('{date}', [DiaryController::class, 'update'])->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('day.update');
});
```

`require __DIR__.'/journal.php';` junto a los otros require de `routes/web.php`.

`UpdateDayRequest::rules()`: `body` nullable string max 20000, `mood` nullable integer between 1 and 5, `tags` nullable array max 30, `tags.*` string max 32, `is_private` boolean (no `required`: un `PUT` parcial no debe borrar la privacidad). `prepareForValidation()` convierte `tags` vacío a `[]`. El servicio recorta a 8 etiquetas después de normalizar.

`JournalService::saveDay()`: `dayFor` → `fill($data)` → `save()` → `pruneIfEmpty()` → devuelve el modelo o null. `pruneIfEmpty()` borra cuando `! $day->refresh()->hasContent()`.

`DiaryController@index`: `$date = $request->query('date') ?: JournalService::today($request->user())->toDateString();` y renderiza con el mismo método privado `render(Request, string $date)` que usa `show()`. `show()` valida el formato con `Carbon::createFromFormat('Y-m-d', $date)` (404 si no parsea) y renderiza `journal/Index` con `prevDate`/`nextDate` (±1 día). `update()` llama al servicio y vuelve con `back()`; si el día fue podado, el mensaje flash es "Día vacío eliminado.".

- [ ] **Step 4: Implementar la página**

`resources/js/pages/journal/Index.tsx`: `Head title="Diario"`, contenedor `mx-auto w-full max-w-3xl p-4 md:p-6`, header con `‹` / fecha larga en castellano (`toLocaleDateString('es-AR', {weekday:'long', day:'numeric', month:'long'})`) / `›` y botón `Hoy` cuando no es hoy, `DayEditor` con textarea (`text-[15px] leading-relaxed`), `MoodPicker`, input de etiquetas y toggle de privado. Guardar con `useForm().put(url, { preserveScroll: true })`; `Ctrl+Enter` dispara submit; error inline arriba del formulario; estado vacío con la pregunta "¿Qué querés recordar de hoy?" y el mismo formulario. Sin recarga completa: Inertia.

- [ ] **Step 5: Correr tests y verificar el frontend**

Run: `php artisan test --compact --filter=JournalDayPageTest`
Expected: PASS (7 tests).

Run: `npm run types && npm run lint`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/journal.php routes/web.php app/Services/Journal app/Http/Controllers/Journal app/Http/Requests/Journal resources/js/pages/journal resources/js/components/journal tests/Feature/Journal/JournalDayPageTest.php
git commit -m "feat(journal): página del día con ánimo, etiquetas y privacidad"
```

---

### Task 3: Momentos, espina del día y re-anclaje

**Files:**
- Create: `app/Http/Controllers/Journal/MomentController.php`
- Create: `app/Http/Requests/Journal/StoreMomentRequest.php`
- Create: `app/Http/Requests/Journal/UpdateMomentRequest.php`
- Modify: `app/Services/Journal/JournalService.php` (`createMoment`, `updateMoment`, `moveMoment`)
- Modify: `routes/journal.php`
- Modify: `app/Http/Controllers/Journal/DiaryController.php` (prop `moments`)
- Create: `resources/js/components/journal/MomentCard.tsx`
- Create: `resources/js/components/journal/MomentDialog.tsx`
- Modify: `resources/js/pages/journal/Index.tsx` (espina + composer)
- Test: `tests/Feature/Journal/JournalMomentsTest.php`

**Interfaces:**
- Consumes: `JournalService::dayFor/pruneIfEmpty/normalizeTags`.
- Produces:
  - `JournalService::createMoment(JournalDay $day, array $data): JournalMoment`
  - `JournalService::updateMoment(JournalMoment $moment, array $data): JournalMoment`
  - `JournalService::moveMoment(JournalMoment $moment, CarbonInterface $occurredAt): JournalMoment` (re-ancla al día de la nueva fecha local y poda el anterior)
  - Rutas `journal.moments.store` (`POST /diario/{date}/momentos`), `journal.moments.update` (`PATCH /diario/momentos/{moment}`), `journal.moments.destroy` (`DELETE /diario/momentos/{moment}`).
  - Prop `moments`: `[{id, occurred_at, title, body, tags, is_private, people: [{id, name}], photos: [{id, url}]}]`, ordenados por `occurred_at`.

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('crea un momento de hoy con la hora actual', function () {
    Carbon::setTestNow('2026-11-15 18:00:00');

    $this->post('/diario/2026-11-15/momentos', ['title' => 'Café', 'body' => 'Con Ana', 'tags' => ['tarde']])
        ->assertRedirect();

    $moment = JournalMoment::with('day')->firstOrFail();

    expect($moment->title)->toBe('Café')
        ->and($moment->day->date->toDateString())->toBe('2026-11-15')
        ->and($moment->occurred_at->utc()->toDateTimeString())->toBe('2026-11-15 18:00:00');
});

it('crea un momento en un día pasado a las 12 locales', function () {
    $this->post('/diario/2026-11-01/momentos', ['body' => 'Recuerdo viejo'])->assertRedirect();

    $moment = JournalMoment::firstOrFail();

    expect($moment->occurred_at->setTimezone('America/Argentina/Buenos_Aires')->format('H:i'))->toBe('12:00');
});

it('re-ancla el momento cuando cambia de fecha y poda el día viejo', function () {
    $this->post('/diario/2026-11-01/momentos', ['body' => 'x'])->assertRedirect();
    $moment = JournalMoment::firstOrFail();
    $oldDay = $moment->journal_day_id;

    $this->patch("/diario/momentos/{$moment->id}", ['occurred_at' => '2026-11-03T09:00:00-03:00'])
        ->assertRedirect();

    expect($moment->fresh()->day->date->toDateString())->toBe('2026-11-03')
        ->and(JournalDay::whereKey($oldDay)->exists())->toBeFalse();
});

it('borra el último momento y con él el día vacío', function () {
    $this->post('/diario/2026-11-02/momentos', ['body' => 'único'])->assertRedirect();
    $moment = JournalMoment::firstOrFail();
    $dayId = $moment->journal_day_id;

    $this->delete("/diario/momentos/{$moment->id}")->assertRedirect();

    expect(JournalMoment::count())->toBe(0)
        ->and(JournalDay::whereKey($dayId)->exists())->toBeFalse();
});

it('devuelve 404 en momentos ajenos', function () {
    $moment = JournalMoment::factory()->create();

    $this->patch("/diario/momentos/{$moment->id}", ['body' => 'hack'])->assertNotFound();
    $this->delete("/diario/momentos/{$moment->id}")->assertNotFound();
});

it('valida cuerpo requerido y largo del título', function () {
    $this->post('/diario/2026-11-04/momentos', ['body' => ''])->assertSessionHasErrors('body');
    $this->post('/diario/2026-11-04/momentos', ['body' => 'x', 'title' => str_repeat('a', 121)])->assertSessionHasErrors('title');
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalMomentsTest`
Expected: FAIL 404 en `POST /diario/{date}/momentos`.

- [ ] **Step 3: Implementar backend**

Rutas de momentos **antes** de `{date}` en `routes/journal.php` (el `momentos/...` literal gana, pero el orden deja explícito que no es una fecha).

`StoreMomentRequest`: `title` nullable string max 120, `body` required string max 20000, `tags` array max 30 de strings max 32, `is_private` boolean, `occurred_at` nullable date. `UpdateMomentRequest`: igual con `sometimes`.

`createMoment()`: si no viene `occurred_at`, usa `now()` para el día de hoy o las 12:00 locales del día recibido. Todo `occurred_at` se normaliza con `Carbon::parse((string) $value)->utc()`. `moveMoment()`: compara `occurred_at` convertido a la zona del usuario con la fecha del día actual; si difiere, `dayFor()` del nuevo día, guarda, y poda el anterior.

`DiaryController@show` agrega `moments` con `->with(['people:id,first_name,last_name', 'media'])` mapeado a props simples (sin serializar media cruda).

- [ ] **Step 4: Implementar la UI**

`MomentCard.tsx`: hora en el margen (columna de 3rem), línea vertical de la espina con nodo, título en negrita, cuerpo, chips de etiquetas y `@persona`, candado si privado. `MomentDialog.tsx`: Dialog con título, cuerpo, `datetime-local`, etiquetas y privado; se usa para crear y editar. `Index.tsx`: composer inline al final de la espina ("Agregar un momento"), lista por `occurred_at`, botón de editar por tarjeta y borrado con confirmación. Vacío: una ranura con borde punteado, no una card vacía.

- [ ] **Step 5: Correr tests y verificar el frontend**

Run: `php artisan test --compact --filter=JournalMomentsTest`
Expected: PASS (6 tests).

Run: `npm run types && npm run lint`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Journal app/Http/Requests/Journal app/Services/Journal routes/journal.php resources/js tests/Feature/Journal/JournalMomentsTest.php
git commit -m "feat(journal): momentos del día con re-anclaje y poda"
```

---

### Task 4: Fotos de un momento

**Files:**
- Modify: `app/Models/JournalMoment.php` (implementa `HasMedia`, `InteractsWithMedia`, colección `photos`)
- Create: `app/Http/Controllers/Journal/PhotoController.php`
- Create: `app/Http/Requests/Journal/StorePhotoRequest.php`
- Modify: `routes/journal.php`
- Modify: `app/Http/Controllers/Journal/DiaryController.php` (prop `photos` por momento con URL firmada interna)
- Create: `resources/js/components/journal/PhotoStrip.tsx`
- Modify: `resources/js/components/journal/MomentCard.tsx` y `MomentDialog.tsx`
- Test: `tests/Feature/Journal/JournalPhotosTest.php`

**Interfaces:**
- Consumes: `JournalMoment`, `JournalMomentPolicy`.
- Produces:
  - `POST /diario/momentos/{moment}/fotos` → `journal.photos.store`
  - `GET /diario/momentos/{moment}/fotos/{media}` → `journal.photos.show`
  - `DELETE /diario/momentos/{moment}/fotos/{media}` → `journal.photos.destroy`
  - Prop `photos`: `[{id, url, name}]`; `url` = `route('journal.photos.show', [...])`.

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('sube una foto al disco local y la sirve al dueño', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id]);

    $this->post("/diario/momentos/{$moment->id}/fotos", [
        'file' => UploadedFile::fake()->image('recuerdo.jpg', 800, 600),
    ])->assertRedirect();

    $media = $moment->fresh()->getFirstMedia('photos');

    expect($media)->not->toBeNull()
        ->and($media->disk)->toBe('local');

    $this->get("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertOk();
});

it('no sirve fotos de un momento ajeno', function () {
    $moment = JournalMoment::factory()->create();
    $moment->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos', 'local');
    $media = $moment->fresh()->getFirstMedia('photos');

    $this->get("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertNotFound();
    $this->delete("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertNotFound();
});

it('rechaza archivos que no son imagen y más de 10 MB', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id]);

    $this->post("/diario/momentos/{$moment->id}/fotos", [
        'file' => UploadedFile::fake()->create('doc.pdf', 10),
    ])->assertSessionHasErrors('file');

    $this->post("/diario/momentos/{$moment->id}/fotos", [
        'file' => UploadedFile::fake()->image('grande.jpg')->size(10241),
    ])->assertSessionHasErrors('file');
});

it('limita a 8 fotos por momento', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id]);

    foreach (range(1, 8) as $i) {
        $this->post("/diario/momentos/{$moment->id}/fotos", [
            'file' => UploadedFile::fake()->image("f{$i}.jpg"),
        ])->assertRedirect();
    }

    $this->post("/diario/momentos/{$moment->id}/fotos", [
        'file' => UploadedFile::fake()->image('nueve.jpg'),
    ])->assertSessionHasErrors('file');
});

it('borra la foto y el archivo', function () {
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id]);
    $moment->addMedia(UploadedFile::fake()->image('x.jpg'))->toMediaCollection('photos', 'local');
    $media = $moment->fresh()->getFirstMedia('photos');

    $this->delete("/diario/momentos/{$moment->id}/fotos/{$media->id}")->assertRedirect();

    expect($moment->fresh()->getMedia('photos'))->toHaveCount(0);
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalPhotosTest`
Expected: FAIL 404 en `POST .../fotos`.

- [ ] **Step 3: Implementar backend**

`JournalMoment implements HasMedia` con `InteractsWithMedia` y `registerMediaCollections()`:

```php
$this->addMediaCollection('photos')
    ->acceptingMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
```

`PhotoController@store`: autoriza `update` del momento, valida con `StorePhotoRequest` (`file` required, `image`, `max:10240`; el `withValidator` agrega el error `file` con "Máximo 8 fotos por momento." cuando `$this->route('moment')->getMedia('photos')->count() >= 8`), guarda con `addMediaFromRequest('file')->toMediaCollection('photos', 'local')`. `show`: autoriza `view`, verifica `$media->model_id === $moment->id && $media->model_type === JournalMoment::class`, devuelve `response()->file($media->getPath())`. `destroy`: autoriza `update`, verifica pertenencia, `$media->delete()`.

- [ ] **Step 4: Implementar la UI**

`PhotoStrip.tsx`: grilla 4-up con `object-cover`, sin conversiones; click abre Dialog con la foto completa; input de subida con `forceFormData`; botón de borrar con confirmación. En móvil, 3-up y la última celda muestra `+N`.

- [ ] **Step 5: Correr tests y verificar el frontend**

Run: `php artisan test --compact --filter=JournalPhotosTest`
Expected: PASS (5 tests). Si el `image` de `UploadedFile::fake()` sin GD falla, usar `->create('f.jpg', 10, 'image/jpeg')` y anotarlo.

Run: `npm run types && npm run lint`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/JournalMoment.php app/Http/Controllers/Journal/PhotoController.php app/Http/Requests/Journal/StorePhotoRequest.php routes/journal.php app/Http/Controllers/Journal/DiaryController.php resources/js tests/Feature/Journal/JournalPhotosTest.php
git commit -m "feat(journal): fotos de momentos en disco privado"
```

---

### Task 5: Personas y normalización de etiquetas

**Files:**
- Modify: `app/Http/Requests/Journal/StoreMomentRequest.php` y `UpdateMomentRequest.php` (`person_ids`)
- Modify: `app/Services/Journal/JournalService.php` (sync de personas)
- Modify: `app/Http/Controllers/People/PersonController.php` (prop `memories`)
- Modify: `resources/js/components/journal/MomentDialog.tsx` (selector de personas)
- Modify: `resources/js/components/journal/MomentCard.tsx` (chips `@persona`)
- Modify: `resources/js/pages/people/Show.tsx` (sección "Recuerdos" al final)
- Test: `tests/Feature/Journal/JournalPeopleTest.php`

**Interfaces:**
- Consumes: `Person`, `JournalService::normalizeTags`, `JournalMoment::people()`.
- Produces: `person_ids` (array de ids validados con `Rule::exists('people','id')->where('user_id', ...)`); prop `memories` en `people/Show` con `[{id, date, excerpt, is_private}]` (máx 5, más recientes).

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('enlaza y desenlaza personas del momento', function () {
    $ana = Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);
    $this->post('/diario/2026-11-15/momentos', ['body' => 'Café', 'person_ids' => [$ana->id]])->assertRedirect();

    $moment = JournalMoment::firstOrFail();
    expect($moment->people->pluck('id')->all())->toBe([$ana->id]);

    $this->patch("/diario/momentos/{$moment->id}", ['person_ids' => []])->assertRedirect();
    expect($moment->fresh()->people)->toHaveCount(0);
});

it('rechaza personas de otro usuario', function () {
    $ajena = Person::factory()->create();

    $this->post('/diario/2026-11-15/momentos', ['body' => 'x', 'person_ids' => [$ajena->id]])
        ->assertSessionHasErrors('person_ids.0');
});

it('muestra los recuerdos vinculados en la ficha de la persona', function () {
    $ana = Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);
    $moment = JournalMoment::factory()->create(['user_id' => $this->user->id, 'body' => 'Cumple de Ana']);
    $moment->people()->attach($ana->id);

    $this->get("/people/{$ana->id}")->assertOk()
        ->assertInertia(fn ($page) => $page->component('people/Show')
            ->has('memories', 1)
            ->where('memories.0.excerpt', 'Cumple de Ana'));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalPeopleTest`
Expected: FAIL: `people` vacío en el primer test.

- [ ] **Step 3: Implementar**

Regla de validación con ownership, `sync()` dentro de una transacción con el guardado del momento. En `PersonController@show`, consultar momentos del usuario vinculados a la persona, ordenar por `occurred_at` desc, limitar 5, mapear a `{id, date, excerpt}` — excerpt vacío si `is_private`, y `is_private` como flag. En la ficha, sección "Recuerdos" con link a `/diario/{date}`.

- [ ] **Step 4: Correr tests y verificar el frontend**

Run: `php artisan test --compact --filter=JournalPeopleTest`
Expected: PASS (3 tests).

Run: `npm run types && npm run lint`
Expected: sin errores.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Journal app/Services/Journal app/Http/Controllers/People/PersonController.php resources/js tests/Feature/Journal/JournalPeopleTest.php
git commit -m "feat(journal): personas vinculadas a memorias"
```

---

### Task 6: Calendario y racha

**Files:**
- Create: `app/Services/Journal/JournalStats.php`
- Create: `app/Http/Controllers/Journal/CalendarController.php`
- Modify: `routes/journal.php`
- Create: `resources/js/pages/journal/Calendar.tsx`
- Create: `resources/js/components/journal/CalendarGrid.tsx`
- Create: `resources/js/components/journal/JournalRail.tsx`
- Modify: `resources/js/pages/journal/Index.tsx` (riel con calendario compacto y racha)
- Test: `tests/Feature/Journal/JournalCalendarTest.php`

**Interfaces:**
- Consumes: `JournalDay`, `JournalMoment`.
- Produces:
  - `JournalStats::streak(User $user): int`
  - `JournalStats::month(User $user, CarbonImmutable $month): Collection<int, array{date: string, mood: ?int, public: bool, moments: int}>`
  - Ruta `journal.calendar` (`GET /diario/calendario?month=YYYY-MM`), props `month`, `days`, `streak`, `today`, `prevMonth`, `nextMonth`.

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('marca el mes con contenido y distingue sólo privado', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-03', 'body' => 'público']);
    $privado = JournalDay::factory()->for($this->user)->create(['date' => '2026-11-04', 'body' => 'íntimo', 'is_private' => true]);

    $this->get('/diario/calendario?month=2026-11')->assertOk()
        ->assertInertia(fn ($page) => $page->component('journal/Calendar')
            ->where('days.0.public', true)
            ->where('days.1.public', false)
            ->where('days.1.date', '2026-11-04'));
});

it('no filtra el mes de otro usuario', function () {
    JournalDay::factory()->create(['date' => '2026-11-05']);

    $this->get('/diario/calendario?month=2026-11')->assertOk()
        ->assertInertia(fn ($page) => $page->where('days', []));
});

it('cuenta racha con hoy, o con ayer si hoy aún no hay nada', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    foreach (['2026-11-13', '2026-11-14'] as $date) {
        JournalDay::factory()->for($this->user)->create(['date' => $date, 'mood' => 3]);
    }

    expect(app(JournalStats::class)->streak($this->user))->toBe(2);

    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-15', 'mood' => 4]);
    expect(app(JournalStats::class)->streak($this->user))->toBe(3);
});

it('corta la racha con dos días sin contenido', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-12', 'mood' => 3]);

    expect(app(JournalStats::class)->streak($this->user))->toBe(0);
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalCalendarTest`
Expected: FAIL 404 en `/diario/calendario`.

- [ ] **Step 3: Implementar**

`JournalStats::month()` consulta días del rango con `withCount('moments')` y `public = ! $day->is_private || $day->moments()->where('is_private', false)->exists()` (un día con cuerpo privado pero un momento público no es "sólo privado"). También suma días que sólo tienen momentos (el día se crea al crear el momento, así que alcanza con `journal_days`). `streak()` camina hacia atrás desde hoy (o ayer si hoy no tiene contenido) contando días con `hasContent()`, en la zona del usuario, con una sola query de fechas y un `Set`.

`CalendarController` valida `month` con regex `^\d{4}-\d{2}$`, default mes actual local.

- [ ] **Step 4: Implementar la UI**

`CalendarGrid.tsx`: grilla 7×6 construida con `Date`, lunes primero, día de hoy con anillo `primary`, días con contenido con punto; si `public=false`, el punto es un candado y no hay tooltip con texto. `JournalRail.tsx`: calendario compacto, racha ("N días seguidos") y slot para el teaser de Repaso (lo llena la Tarea 8). `Calendar.tsx`: página con título "Calendario", `‹ mes ›`, grilla y racha. En la página del día, el riel aparece sólo en `lg:`.

- [ ] **Step 5: Correr tests y verificar el frontend**

Run: `php artisan test --compact --filter=JournalCalendarTest`
Expected: PASS (4 tests).

Run: `npm run types && npm run lint`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Journal app/Http/Controllers/Journal/CalendarController.php routes/journal.php resources/js tests/Feature/Journal/JournalCalendarTest.php
git commit -m "feat(journal): calendario mensual y racha"
```

---

### Task 7: Timeline "Todas"

**Files:**
- Create: `app/Http/Controllers/Journal/TimelineController.php`
- Modify: `routes/journal.php`
- Create: `resources/js/pages/journal/All.tsx`
- Create: `resources/js/components/journal/MemoryCard.tsx`
- Test: `tests/Feature/Journal/JournalTimelineTest.php`

**Interfaces:**
- Consumes: `JournalDay`, `JournalMoment`.
- Produces: `journal.timeline` (`GET /diario/todas`), props `days` (paginador de 20 con `data: [{date, excerpt, moments_count, is_private, photo_url}]`) y `total`.

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('lista sólo días con contenido, más nuevos primero', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-01', 'body' => 'viejo']);
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-10', 'body' => 'nuevo']);
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-05', 'tags' => ['solo-tag']]);

    $this->get('/diario/todas')->assertOk()
        ->assertInertia(fn ($page) => $page->component('journal/All')
            ->has('days.data', 2)
            ->where('days.data.0.excerpt', 'nuevo')
            ->where('days.data.1.excerpt', 'viejo'));
});

it('usa el primer momento como extracto si el día no tiene texto', function () {
    JournalMoment::factory()->create([
        'user_id' => $this->user->id,
        'journal_day_id' => JournalDay::factory()->for($this->user)->create(['date' => '2026-11-09', 'body' => null])->id,
        'body' => 'Caminata por la costanera',
    ]);

    $this->get('/diario/todas')->assertOk()
        ->assertInertia(fn ($page) => $page->where('days.data.0.excerpt', 'Caminata por la costanera'));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter=JournalTimelineTest`
Expected: FAIL 404.

- [ ] **Step 3: Implementar**

Query de días del usuario con `where(fn ($q) => $q->whereNotNull('body')->where('body','!=','')->orWhereNotNull('mood')->orWhereHas('moments'))`, `withCount('moments')`, `with('moments' => fn ($q) => $q->orderBy('occurred_at'))`, `orderByDesc('date')`, `paginate(20)->withQueryString()`. Extracto: `body` o el primer momento; recortado a 180 caracteres en el server.

- [ ] **Step 4: Implementar la UI**

`MemoryCard.tsx`: fecha como chip (`15 NOV` + año), extracto, contador de momentos, miniaturas si hay y candado si privado. `All.tsx`: lista agrupada visualmente por mes, paginación Anterior/Siguiente como `health/measurements/Index.tsx`, vacío con CTA "Escribir hoy".

- [ ] **Step 5: Correr tests y verificar el frontend**

Run: `php artisan test --compact --filter=JournalTimelineTest`
Expected: PASS (2 tests).

Run: `npm run types && npm run lint`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Journal/TimelineController.php routes/journal.php resources/js tests/Feature/Journal/JournalTimelineTest.php
git commit -m "feat(journal): timeline de memorias"
```

---

### Task 8: Búsqueda y Repaso

**Files:**
- Create: `app/Http/Controllers/Journal/SearchController.php`
- Create: `app/Http/Controllers/Journal/ReviewController.php`
- Modify: `routes/journal.php`
- Create: `resources/js/pages/journal/Review.tsx`
- Modify: `resources/js/pages/journal/All.tsx` (buscador)
- Modify: `resources/js/components/journal/JournalRail.tsx` (teaser de repaso)
- Modify: `resources/js/pages/journal/Index.tsx` (prop `onThisDay`)
- Test: `tests/Feature/Journal/JournalSearchTest.php`
- Test: `tests/Feature/Journal/JournalReviewTest.php`

**Interfaces:**
- Consumes: `JournalDay`, `JournalMoment`.
- Produces:
  - `journal.search` (`GET /diario/buscar?q=`), props `q`, `days` (máx 20), `moments` (máx 20).
  - `journal.review` (`GET /diario/repaso`), props `onThisDay: Memory[]`, `random: ?Memory`, `today`.
  - `Memory = {id, type: 'day'|'moment', date, excerpt, is_private}`.

- [ ] **Step 1: Escribir los tests que fallan**

```php
it('busca en días y momentos públicos', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-01', 'body' => 'Caminata en la costanera']);
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'title' => 'Costanera al atardecer', 'body' => 'foto']);

    $this->get('/diario/buscar?q=costanera')->assertOk()
        ->assertInertia(fn ($page) => $page->component('journal/All')
            ->has('days', 1)
            ->has('moments', 1));
});

it('no devuelve memorias privadas', function () {
    JournalDay::factory()->for($this->user)->create(['date' => '2026-11-01', 'body' => 'Secreto de costanera', 'is_private' => true]);
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'body' => 'costanera privada', 'is_private' => true]);

    $this->get('/diario/buscar?q=costanera')->assertOk()
        ->assertInertia(fn ($page) => $page->where('days', [])->where('moments', []));
});

it('ignora búsquedas de menos de dos caracteres y escapa comodines', function () {
    JournalDay::factory()->for($this->user)->create(['body' => '100% real']);
    JournalDay::factory()->for($this->user)->create(['body' => 'abc']);

    $this->get('/diario/buscar?q=a')->assertOk()->assertInertia(fn ($page) => $page->where('days', []));
    $this->get('/diario/buscar?q=a%25')->assertOk()->assertInertia(fn ($page) => $page->where('days', []));
    $this->get('/diario/buscar?q=100%25')->assertOk()->assertInertia(fn ($page) => $page->has('days', 1));
});

it('repaso trae el mismo día de años anteriores y excluye privadas', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'occurred_at' => '2025-11-15 15:00:00', 'body' => 'Hace un año']);
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'occurred_at' => '2024-11-15 15:00:00', 'body' => 'Hace dos', 'is_private' => true]);
    JournalMoment::factory()->create(['user_id' => $this->user->id, 'occurred_at' => '2025-10-01 15:00:00', 'body' => 'Otro día']);
    JournalDay::factory()->for($this->user)->create(['date' => '2024-11-15', 'body' => 'Entrada de hace dos años']);

    $this->get('/diario/repaso')->assertOk()
        ->assertInertia(fn ($page) => $page->component('journal/Review')
            ->has('onThisDay', 2)
            ->where('onThisDay.0.excerpt', 'Hace un año')
            ->where('onThisDay.1.excerpt', 'Entrada de hace dos años'));
});

it('repaso no filtra memorias de otro usuario', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    JournalMoment::factory()->create(['occurred_at' => '2025-11-15 15:00:00', 'body' => 'ajena']);

    $this->get('/diario/repaso')->assertOk()
        ->assertInertia(fn ($page) => $page->where('onThisDay', []));
});
```

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact --filter='JournalSearchTest|JournalReviewTest'`
Expected: FAIL 404.

- [ ] **Step 3: Implementar**

Búsqueda: `q` recortado a 2..100; escapar `%`, `_` y `\` antes del `ILIKE`; `where('is_private', false)` explícito; días por `body`, momentos por `title` o `body`; `orderByDesc('date')` / `orderByDesc('occurred_at')`; límite 20 cada uno.

Repaso: fecha local de hoy; días (`date`) y momentos (`occurred_at`) unificados en `Memory`, ordenados por fecha descendente, mismo mes-día en años anteriores, `is_private = false`, límite 6. Para momentos, filtrar por mes/día en la zona del usuario: traer una ventana de 3 días UTC alrededor del día objetivo y filtrar en PHP con `setTimezone($tz)`, en vez de SQL dependiente de zona. `random`: una memoria pública de hace más de 30 días, aleatoria.

- [ ] **Step 4: Implementar la UI**

`All.tsx`: input de búsqueda con debounce de 300 ms que navega a `/diario/buscar?q=`, resultados separados "Días" y "Momentos", vacío con "Nada con esa palabra". `Review.tsx`: "Tal día como hoy" con `MemoryCard`, bloque "Una memoria al azar" con botón "Otra" (`router.reload`). Riel: teaser de 1 memoria con link a Repaso.

- [ ] **Step 5: Correr tests y verificar el frontend**

Run: `php artisan test --compact --filter='JournalSearchTest|JournalReviewTest'`
Expected: PASS (5 tests).

Run: `npm run types && npm run lint && npm run build`
Expected: sin errores.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Journal routes/journal.php resources/js tests/Feature/Journal/JournalSearchTest.php tests/Feature/Journal/JournalReviewTest.php
git commit -m "feat(journal): búsqueda y repaso de memorias"
```

---

### Task 9: Navegación, barra móvil y QA visual

**Files:**
- Modify: `resources/js/lib/navigation-areas.ts`
- Modify: `resources/js/components/mobile-bottom-nav.tsx`
- Modify: `resources/js/pages/journal/*.tsx` (estados finales y accesibilidad)
- Test: `tests/Feature/Journal/JournalNavigationTest.php`

**Interfaces:**
- Consumes: todas las rutas `journal.*`.
- Produces: área `diario` con subitems Hoy, Calendario, Todas, Repaso; entrada "Diario" en `moreItems` móvil.

- [ ] **Step 1: Escribir el test de rutas y navegación**

```php
it('expone las cuatro páginas del diario al usuario autenticado', function () {
    foreach (['/diario', '/diario/2026-11-15', '/diario/calendario', '/diario/todas', '/diario/repaso'] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get('/diario/buscar?q=diario')->assertOk();
});
```

- [ ] **Step 2: Correr y verificar que pasa (ya existen las rutas)**

Run: `php artisan test --compact --filter=JournalNavigationTest`
Expected: PASS. Si falla, es una ruta mal registrada: corregir antes de seguir.

- [ ] **Step 3: Agregar el área a la navegación**

En `navigation-areas.ts`, después de `personal`:

```ts
{
    key: 'diario',
    title: 'Diario',
    icon: NotebookPen,
    href: '/diario',
    match: ['/diario'],
    groups: [
        {
            items: [
                { title: 'Hoy', href: '/diario' },
                { title: 'Calendario', href: '/diario/calendario' },
                { title: 'Todas', href: '/diario/todas' },
                { title: 'Repaso', href: '/diario/repaso' },
            ],
        },
    ],
},
```

`NotebookPen` se importa de `lucide-react`. En `mobile-bottom-nav.tsx`, agregar `{ title: 'Diario', href: '/diario', matches: ['/diario'] }` a `moreItems`.

- [ ] **Step 4: Pasada de estados y accesibilidad**

Revisar en cada página: vacío, con datos, error, cargando (skeleton al cambiar de día), foco visible, `aria-label` en botones de icono, targets de 44px en móvil, texto de cuerpo con `text-[15px] leading-relaxed`, sin colores nuevos. El riel no aparece en `<lg`; el calendario compacto del riel no duplica la página de Calendario (sólo el mes actual y link).

- [ ] **Step 5: QA renderizado**

Con el server de la app (`:8010` o el que corresponda), Playwright MCP: login `test@example.com/password`, recorrer Landing → Diario → escribir día con ánimo y 1 etiqueta → agregar momento → subir foto → Calendario → Todas → buscar → Repaso. Capturas en 1280×720 y 375×720. Corregir clipping, solapamiento, controles inertes y foco roto. Documentar el resultado en el mensaje final (no crear archivos de reporte si no se pidieron).

- [ ] **Step 6: Verificación completa y commit**

Run: `npm run types && npm run lint && npm run build && php artisan test --compact --filter=Journal`
Expected: todo PASS.

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/lib/navigation-areas.ts resources/js/components/mobile-bottom-nav.tsx resources/js/pages/journal resources/js/components/journal tests/Feature/Journal/JournalNavigationTest.php
git commit -m "feat(journal): área Diario en la navegación y pulido visual"
```

---

## Después de la Fase 1

La Fase 2 (re-autenticación, candados y reflexión con IA) lleva su propio plan cuando la Fase 1 esté en uso: depende de cómo se sienta el flujo de escritura y de qué tanto se usen las memorias privadas.
