# Today — Implementation Plan (renombre EN + fixes + módulo Media)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. Ejecución: inline (aprobada).

**Goal:** Renombrar a inglés todo el módulo Hoy (DB, archivos, rutas), arreglar el flujo Semana/buscador, restaurar el dashboard con sección Hoy, agregar el módulo Media con búsqueda externa y pick aleatorio, y completar los huecos B1-B4.

**Architecture:** Un commit por tarea. Migración limpia recrea las 4 tablas (prod vacía) + notifications. Providers de búsqueda keyless con cache 24h. `TodayPanel` compartido entre `/dashboard` y `/today`. Sin cambios a otros módulos.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Tailwind v4, Pest 4, Wayfinder, Http client.

**Spec:** `docs/superpowers/specs/2026-10-10-today-completion-design.md`

## Global Constraints

- Copy visible **en español**; código/clases/tablas/rutas/props en **inglés**.
- Tipos media (valores DB): `pelicula|serie|disco|libro|juego`.
- Anclas DB: `wake_up|after_meal|after_gym|after_shower|before_sleep|no_anchor`.
- Estados: `pending|done|released`. Copy: pendiente/hecho/soltado.
- Máx 3 ítems por día (Request + modelo + índice único parcial).
- 🔒 Prohibido: rachas/puntos/%/tendencias/recompensas/historial de media/notifs de culpa.
- El random solo por click en "Sorprendeme", determinístico por día; nunca en notificaciones.
- `fetch` del frontend usa `csrfHeaders()` de `resources/js/lib/csrf.ts`.
- Pint antes de cada commit PHP. `npm run types` y `npm run build` antes del PR.
- No tocar: gimnasio, finanzas, salud, nutrición, contactos, integraciones Radarr/Jellyseerr.

## Review Focus

- Un 4to `day_item` se rechaza por Request, por modelo y por índice único (parcial).
- La búsqueda de Semana nunca devuelve `is_done=true` ni archivadas, y ordena por `created_at DESC`.
- `/dashboard` conserva las cards de nutrición/entreno/inventario/freelance y suma Hoy arriba (sin romper mobile 375px).
- Los providers externos toleran timeout/error y devuelven `[]` sin explotar (degradación).
- Archivo masivo: nunca ejecuta sin preview+confirmado; Deshacer restaura exactamente los ids archivados.
- `today:notify` no repite aviso y no culpa; `today:close` no muta `pending`.

---

## Task 0: Prerrequisito de entorno

**Files:** ninguno (solo `package-lock`/`node_modules`).

- [ ] **Step 1: instalar deps JS faltantes**

Run: `npm install`
Expected: `vite-plugin-pwa` presente en `node_modules/`.

- [ ] **Step 2: verificar build actual**

Run: `npm run build`
Expected: build OK (baseline antes de tocar código).

---

## Task 1: Renombre DB + modelos + factories

**Files:**
- Delete: `database/migrations/2026_10_10_000001_*.php`, `2026_10_10_000002_*.php`, `app/Models/{Dia,DiaItem,Bloque,ColaMediaItem}.php`, `tests/Feature/Hoy/*`
- Create: `database/migrations/2026_10_10_100001_create_today_tables.php`, `2026_10_10_100002_rename_project_tasks_today_columns.php`, `2026_10_10_100003_create_notifications_table.php`, `app/Models/{Day,DayItem,Block,QueueItem}.php`, `database/factories/{DayFactory,DayItemFactory,BlockFactory,QueueItemFactory}.php`, `tests/Feature/Today/{InvariantsTest,FlowTest,SystemTest}.php`
- Modify: `app/Models/ProjectTask.php`

**Interfaces (Produces):**
- `Day` (table `days`): fillable `user_id,date,pick_type`; casts `date=date`; relations `user()`, `items()`, `visibleItems()`; `items()` ordena por `position`.
- `DayItem`: consts `ANCHORS=['wake_up','after_meal','after_gym','after_shower','before_sleep','no_anchor']`, `STATES=['pending','done','released']`; fillable `day_id,task_id,title,anchor,position,state,closing_note,done_at`.
- `QueueItem` (table `queue_items`): consts `TYPES=['pelicula','serie','disco','libro','juego']`; fillable `user_id,title,type,position,source,external_id,cover_url,year,creator`.
- `Block` (table `blocks`): fillable `user_id,label,weekday,start_time,duration_min,active`.
- `ProjectTask` scopes: `inWeek()`, `notArchived()`, `archived()`, `notDone()`.

- [ ] **Step 1: escribir tests de invariantes (RED)**

`tests/Feature/Today/InvariantsTest.php`:

```php
<?php

use App\Models\Day;
use App\Models\DayItem;
use App\Models\ProjectTask;
use App\Models\QueueItem;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('no existe campo tabla ni endpoint de rachas puntos o niveles', function () {
    $columns = [];
    foreach (['days', 'day_items', 'project_tasks', 'blocks', 'queue_items'] as $table) {
        if (Schema::hasTable($table)) {
            $columns = array_merge($columns, Schema::getColumnListing($table));
        }
    }
    foreach ($columns as $col) {
        expect(strtolower($col))->not->toMatch('/streak|racha|xp|level|nivel|points|puntos|badge|logro|heatmap/');
    }
    foreach (Route::getRoutes()->getRoutes() as $r) {
        expect(strtolower($r->uri()))->not->toMatch('/streak|racha|\bxp\b|niveles|logros|insignias/');
    }
});

test('no se puede crear un cuarto day_item', function () {
    $user = User::factory()->create();
    $day = Day::create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    foreach ([1, 2, 3] as $pos) {
        DayItem::create([
            'day_id' => $day->id, 'title' => "T{$pos}", 'anchor' => 'no_anchor',
            'position' => $pos, 'state' => 'pending',
        ]);
    }
    expect(fn () => DayItem::create([
        'day_id' => $day->id, 'title' => 'Cuarta', 'anchor' => 'no_anchor',
        'position' => 3, 'state' => 'pending',
    ]))->toThrow(Exception::class);
});

test('released libera slot y no aparece en visibles', function () {
    $user = User::factory()->create();
    $day = Day::create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    $item = DayItem::create(['day_id' => $day->id, 'title' => 'Soltada', 'anchor' => 'no_anchor', 'position' => 1, 'state' => 'pending']);
    $item->update(['state' => 'released']);

    DayItem::create(['day_id' => $day->id, 'title' => 'Nueva', 'anchor' => 'no_anchor', 'position' => 1, 'state' => 'pending']);
    expect($day->visibleItems()->pluck('title'))->not->toContain('Soltada');
});

test('day es unico por usuario/fecha y queue no guarda historial', function () {
    $user = User::factory()->create();
    Day::create(['user_id' => $user->id, 'date' => '2026-10-11']);
    expect(fn () => Day::create(['user_id' => $user->id, 'date' => '2026-10-11']))->toThrow(Exception::class);

    QueueItem::create(['user_id' => $user->id, 'title' => 'Dune', 'type' => 'libro', 'position' => 1]);
    $cols = Schema::getColumnListing('queue_items');
    expect($cols)->not->toContain('watched_at');
    expect($cols)->not->toContain('minutes_watched');
});

test('project_tasks usa in_week y archived_at', function () {
    expect(Schema::hasColumn('project_tasks', 'in_week'))->toBeTrue();
    expect(Schema::hasColumn('project_tasks', 'archived_at'))->toBeTrue();
    expect(Schema::hasColumn('project_tasks', 'en_semana'))->toBeFalse();

    $user = User::factory()->create();
    ProjectTask::factory()->create(['user_id' => $user->id, 'in_week' => true, 'archived_at' => null]);
    expect(ProjectTask::inWeek()->count())->toBe(1);
});
```

- [ ] **Step 2: correr y ver FAIL**

Run: `php artisan test --compact --filter=InvariantsTest`
Expected: FAIL con class not found (`App\Models\Day`).

- [ ] **Step 3: migraciones**

`database/migrations/2026_10_10_100001_create_today_tables.php`:

```php
public function up(): void
{
    Schema::dropIfExists('dia_items');
    Schema::dropIfExists('dias');
    Schema::dropIfExists('bloques');
    Schema::dropIfExists('cola_media');

    Schema::create('days', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->date('date');
        $table->string('pick_type')->default('pelicula');
        $table->timestamps();
        $table->unique(['user_id', 'date']);
    });

    Schema::create('day_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('day_id')->constrained('days')->cascadeOnDelete();
        $table->foreignId('task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
        $table->string('title');
        $table->string('anchor')->default('no_anchor');
        $table->unsignedTinyInteger('position');
        $table->string('state')->default('pending');
        $table->string('closing_note')->nullable();
        $table->timestamp('done_at')->nullable();
        $table->timestamps();
    });
    DB::statement("CREATE UNIQUE INDEX day_items_day_position_visible ON day_items (day_id, position) WHERE state != 'released'");

    Schema::create('blocks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('label');
        $table->unsignedTinyInteger('weekday');
        $table->time('start_time');
        $table->integer('duration_min');
        $table->boolean('active')->default(true);
        $table->timestamps();
    });

    Schema::create('queue_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('title');
        $table->string('type');
        $table->integer('position')->default(0);
        $table->string('source')->nullable();
        $table->string('external_id')->nullable();
        $table->string('cover_url')->nullable();
        $table->unsignedSmallInteger('year')->nullable();
        $table->string('creator')->nullable();
        $table->timestamps();
        $table->index(['user_id', 'position']);
        $table->index(['user_id', 'type']);
    });
}
```

`down()`: dropear las 4.

`database/migrations/2026_10_10_100002_rename_project_tasks_today_columns.php`:

```php
public function up(): void
{
    Schema::table('project_tasks', function (Blueprint $table) {
        $table->dropIndex(['user_id', 'en_semana']);
        $table->dropIndex(['user_id', 'archivada_at']);
    });
    Schema::table('project_tasks', function (Blueprint $table) {
        $table->renameColumn('en_semana', 'in_week');
        $table->renameColumn('archivada_at', 'archived_at');
    });
    Schema::table('project_tasks', function (Blueprint $table) {
        $table->index(['user_id', 'in_week']);
        $table->index(['user_id', 'archived_at']);
    });
}
```

> Nota: renombrar columnas exige `doctrine/dbal` en Laravel 12 para sqlite? No: Laravel 10+ usa sintaxis nativa (`alter table ... rename column`) en sqlite y pgsql. Verificar con `php artisan migrate:fresh` en testing (sqlite) — si falla, alternativa: crear columna nueva + copiar + dropear vieja (columna sin datos en prod, y en tests el `RefreshDatabase` recrea).

`database/migrations/2026_10_10_100003_create_notifications_table.php`: copiar el stub estándar de Laravel (`uuid`, `type`, `notifiable morphs`, `data`, `read_at`).

- [ ] **Step 4: modelos + factories**

`Day`:

```php
class Day extends Model
{
    use HasFactory;

    protected $table = 'days';
    protected $fillable = ['user_id', 'date', 'pick_type'];
    protected function casts(): array { return ['date' => 'date']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(DayItem::class)->orderBy('position'); }
    public function visibleItems(): HasMany
    {
        return $this->hasMany(DayItem::class)->where('state', '!=', 'released')->orderBy('position');
    }
}
```

`DayItem` (saving guard): valida `position` 1..3, `anchor` en `ANCHORS`, `state` en `STATES`, y máx 3 visibles — el `saving` cuenta `state IN (pending,done)` del mismo `day_id` excluyendo el propio id, y si el item a guardar es `pending|done` y ya hay 3, lanza `ValidationException` con `day_id => 'Máximo 3 ítems por día.'`. Copiar la lógica existente de `DiaItem::booted()` con los nombres nuevos.

`ProjectTask`: scopes

```php
public function scopeInWeek($query) { return $query->where('in_week', true)->whereNull('archived_at'); }
public function scopeNotArchived($query) { return $query->whereNull('archived_at'); }
public function scopeArchived($query) { return $query->whereNotNull('archived_at'); }
public function scopeNotDone($query) { return $query->where('is_done', false); }
```

Factories: `DayFactory` (`user_id => User::factory()`, `date => now()->toDateString()`, `pick_type => 'pelicula'`), `DayItemFactory`, `BlockFactory`, `QueueItemFactory` (estados/anchors/tipos random válidos, `position` incremental local).

- [ ] **Step 5: correr testes PASS**

Run: `php artisan test --compact --filter=InvariantsTest`
Expected: PASS 5/5.

- [ ] **Step 6: commit**

```bash
git add -A && git commit -m "refactor(today): rename hoy->today EN (db, models, factories) + notifications table"
```

---

## Task 2: Renombre controladores/rutas/páginas + fix Semana/buscador

**Files:**
- Delete: `app/Http/Controllers/Hoy/*`, `app/Http/Requests/Hoy/*`, `resources/js/pages/hoy/*`
- Create: `app/Http/Controllers/Today/{TodayController,TomorrowController,WeekController,QueueController,ArchiveController,ArchiveBulkController}.php`, `app/Http/Requests/Today/{StoreTomorrowRequest,UpdateDayItemRequest}.php`, `resources/js/pages/today/{Index,Tomorrow,Week,Queue,Archived,BulkArchive}.tsx`
- Modify: `routes/web.php`, `resources/js/components/app-sidebar.tsx`, `tests/Feature/Today/*`

**Interfaces (Consumes):** modelos de Task 1.
**Interfaces (Produces):** rutas `today.*`: `GET /today` → `today.index`; `GET|POST /today/tomorrow` → `today.tomorrow(.store)`; `POST /today/tomorrow/{item}/put-today` → `today.tomorrow.put-today`; `PATCH /today/items/{item}` → `today.items.update` (body `state`, `closing_note`); `POST /today/items/{item}/release` → `today.items.release`; `GET|POST /today/week`, `DELETE /today/week/{task}`, `GET /today/week/search` (JSON `[{id,title}]`); `GET|POST /today/queue`, `POST /today/queue/next`, `PATCH /today/queue/reorder`, `DELETE /today/queue/{item}`; `GET /today/archived`, `POST /today/archived/restore`; `GET /today/bulk-archive`, `POST /today/bulk-archive/preview`, `POST /today/bulk-archive/run`.

- [ ] **Step 1: tests de flujo (RED)**

`tests/Feature/Today/FlowTest.php` (los 4 primeros vienen de `HoyFlujoTest` con nombres nuevos, más):

```php
test('week search devuelve solo pendientes no archivadas fuera del pool, por creadas desc', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $vieja = ProjectTask::factory()->create(['user_id' => $user->id, 'is_done' => false, 'created_at' => now()->subDays(10)]);
    $nueva = ProjectTask::factory()->create(['user_id' => $user->id, 'is_done' => false, 'created_at' => now()->subDay()]);
    $hecha = ProjectTask::factory()->create(['user_id' => $user->id, 'is_done' => true, 'created_at' => now()]);
    $archivada = ProjectTask::factory()->create(['user_id' => $user->id, 'is_done' => false, 'archived_at' => now()]);
    $enPool = ProjectTask::factory()->create(['user_id' => $user->id, 'is_done' => false, 'in_week' => true]);

    $res = $this->getJson('/today/week/search')->assertOk()->json();
    expect(collect($res)->pluck('id')->all())->toContain($nueva->id, $vieja->id);
    expect(collect($res)->pluck('id')->all())->not->toContain($hecha->id, $archivada->id, $enPool->id);
    expect($res[0]['id'])->toBe($nueva->id); // created_at DESC: la más nueva primero
});
```

```php
test('pool excluye archivadas y marca el aviso por encima de 7', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ProjectTask::factory()->count(8)->create(['user_id' => $user->id, 'in_week' => true]);
    ProjectTask::factory()->create(['user_id' => $user->id, 'in_week' => true, 'archived_at' => now()]);

    $page = $this->get('/today/week')->assertOk()->viewData('page');
    expect($page['props']['pool'])->toHaveCount(8);
    expect($page['props']['overloaded'])->toBeTrue();
});

test('tomorrow crea el dia de mañana y no toca hoy', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->post('/today/tomorrow', ['items' => [
        ['title' => 'Lavar', 'anchor' => 'after_meal', 'position' => 1],
    ]])->assertRedirect();

    expect(Day::whereDate('date', now()->addDay())->exists())->toBeTrue();
    expect(Day::whereDate('date', now()->toDateString())->exists())->toBeFalse();
});

test('cuarto item rechazado por API', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $payload = collect(range(1, 4))->map(fn ($i) => ['title' => "T{$i}", 'anchor' => 'no_anchor', 'position' => min($i, 3)])->all();
    $this->post('/today/tomorrow', ['items' => $payload])->assertInvalid(['items']);
});

test('dashboard renderiza hoy sin count del backlog', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ProjectTask::factory()->count(5)->create(['user_id' => $user->id]);

    DB::enableQueryLog();
    $page = $this->get(route('dashboard'))->assertOk()->viewData('page');
    $queries = strtolower(collect(DB::getQueryLog())->pluck('query')->join(' '));

    expect($page['component'])->toBe('fitness/dashboard');
    expect($queries)->not->toMatch('/count\(\*\).*project_tasks/');
});
```

- [ ] **Step 2: correr y ver FAIL** — Run: `php artisan test --compact --filter=FlowTest` → FAIL (404 en `/today/...`).

- [ ] **Step 3: controladores (rename + fix)**

`WeekController`:

```php
public function index(Request $request)
{
    $pool = ProjectTask::where('user_id', $request->user()->id)
        ->inWeek()->notDone()
        ->orderBy('sort_order')
        ->get(['id', 'title', 'created_at']);

    return Inertia::render('today/Week', [
        'pool' => $pool,
        'overloaded' => $pool->count() > 7,
    ]);
}

public function search(Request $request)
{
    $q = trim((string) $request->get('q', ''));

    $tasks = ProjectTask::where('user_id', $request->user()->id)
        ->notArchived()->notDone()->where('in_week', false)
        ->when($q !== '', fn ($query) => $query->where('title', 'like', '%'.addcslashes($q, '%_\\').'%'))
        ->orderByDesc('created_at')
        ->limit(20)
        ->get(['id', 'title']);

    return response()->json($tasks);
}
```

Resto de controllers: renombrar namespace/archivos y actualizar strings de estado `pendiente|hecho|soltado` → `pending|done|released`; `ancla` → `anchor`; `titulo` → `title`; `nota_cierre` → `closing_note`; `posicion` → `position`.

- [ ] **Step 4: rutas + sidebar**

`routes/web.php`: borrar bloque `hoy`, agregar:

```php
Route::get('dashboard', [TodayController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::prefix('today')->name('today.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [TodayController::class, 'index'])->name('index');
    Route::get('tomorrow', [TomorrowController::class, 'show'])->name('tomorrow');
    Route::post('tomorrow', [TomorrowController::class, 'store'])->name('tomorrow.store');
    Route::post('tomorrow/{item}/put-today', [TomorrowController::class, 'putToday'])->name('tomorrow.put-today');
    Route::patch('items/{item}', [TodayController::class, 'update'])->name('items.update');
    Route::post('items/{item}/release', [TodayController::class, 'release'])->name('items.release');
    Route::get('week', [WeekController::class, 'index'])->name('week');
    Route::get('week/search', [WeekController::class, 'search'])->name('week.search');
    Route::post('week', [WeekController::class, 'store'])->name('week.store');
    Route::delete('week/{task}', [WeekController::class, 'destroy'])->name('week.destroy');
    Route::get('queue', [QueueController::class, 'index'])->name('queue');
    Route::post('queue', [QueueController::class, 'store'])->name('queue.store');
    Route::post('queue/next', [QueueController::class, 'next'])->name('queue.next');
    Route::patch('queue/reorder', [QueueController::class, 'reorder'])->name('queue.reorder');
    Route::delete('queue/{item}', [QueueController::class, 'destroy'])->name('queue.destroy');
    Route::get('archived', [ArchiveController::class, 'index'])->name('archived');
    Route::post('archived/restore', [ArchiveController::class, 'restore'])->name('archived.restore');
    Route::get('bulk-archive', [ArchiveBulkController::class, 'index'])->name('bulk-archive');
    Route::post('bulk-archive/preview', [ArchiveBulkController::class, 'preview'])->name('bulk-archive.preview');
    Route::post('bulk-archive/run', [ArchiveBulkController::class, 'run'])->name('bulk-archive.run');
});
```

> `week/search` declarado **antes** de `week/{task}` para no ser capturado.

Sidebar (`personalNavItems`): `Week` → `/today/week`, `Queue` → `/today/queue`, `Archived` → `/today/archived`; `mainNavItems[0]` = `{ title: 'Hoy', href: dashboard(), icon: CheckSquare }` y **eliminar** el duplicado.

- [ ] **Step 5: páginas (rename + estados nuevos en UI)**

Renombrar `pages/hoy/*` → `pages/today/*`, actualizar strings de estado en check `item.state === 'done'`, `'released'`, y usar `csrfHeaders()` en los `fetch` de `Week.tsx` y `BulkArchive.tsx`.

- [ ] **Step 6: tests PASS + types**

Run: `php artisan test --compact --filter="Today"` → PASS.
Run: `npm run types` → 0 errores.

- [ ] **Step 7: commit**

```bash
git add -A && git commit -m "refactor(today): controllers routes pages EN + fix week search (not done, not archived, created desc)"
```

---

## Task 3: TodayPanel + dashboard con sección Hoy

**Files:**
- Create: `resources/js/components/today/TodayPanel.tsx`, `resources/js/components/today/DayItemRow.tsx`, `resources/js/components/today/MediaLine.tsx`
- Modify: `resources/js/pages/fitness/dashboard.tsx`, `resources/js/pages/today/Index.tsx`, `app/Http/Controllers/Today/TodayController.php`, `tests/Feature/Today/FlowTest.php`

**Interfaces (Produces):**
- `TodayPanel` props: `{ date: string; day: DayPayload | null; block: BlockPayload | null; routine: RoutinePayload | null; pick: PickPayload | null }` donde `DayPayload = { id, pick_type, items: { id, title, anchor, position, state, closing_note }[] }`; `PickPayload = { title, type, cover_url }`.
- Dashboard renderiza `<TodayPanel ... />` arriba de las cards; elimina hidratación estática (`fitness/dashboard.tsx:203-216`) y deja `AiInsightCard`/volumen/racha fuera (ya removidos).
- `update()` acepta `state: done|pending` y `closing_note nullable`; al pasar a `done` setea `done_at = now()`, al volver a `pending` lo limpia. `release` permite de `pending` y `done`.

- [ ] **Step 1: test de dashboard con sección (RED)**

Agregar a `FlowTest`:

```php
test('dashboard incluye dia, rutina, block y pick sin backlog', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $day = Day::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    DayItem::factory()->create(['day_id' => $day->id, 'title' => 'Lavar', 'position' => 1]);
    Routine::factory()->create(['user_id' => $user->id, 'name' => 'Push', 'scheduled_date' => now()->format('l')]);
    Block::factory()->create(['user_id' => $user->id, 'label' => 'Proyecto', 'weekday' => now()->dayOfWeek, 'active' => true]);
    QueueItem::factory()->create(['user_id' => $user->id, 'title' => 'Dune', 'type' => 'pelicula', 'position' => 1]);

    $props = $this->get(route('dashboard'))->assertOk()->viewData('page')['props'];
    expect($props['day']['items'])->toHaveCount(1);
    expect($props['routine']['name'])->toBe('Push');
    expect($props['block']['label'])->toBe('Proyecto');
    expect($props['pick']['title'])->toBe('Dune');
});

test('marcar hecho setea done_at y nota; volver a pendiente la limpia', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $day = Day::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    $item = DayItem::factory()->create(['day_id' => $day->id, 'position' => 1]);

    $this->patch("/today/items/{$item->id}", ['state' => 'done', 'closing_note' => 'tranqui'])->assertRedirect();
    expect($item->fresh()->done_at)->not->toBeNull();
    expect($item->fresh()->closing_note)->toBe('tranqui');

    $this->patch("/today/items/{$item->id}", ['state' => 'pending'])->assertRedirect();
    expect($item->fresh()->done_at)->toBeNull();
});
```

- [ ] **Step 2: correr y ver FAIL** → `$props['day']` null / keys faltantes.

- [ ] **Step 3: `TodayController@index` (dashboard + /today)**

```php
public function index(Request $request)
{
    $userId = $request->user()->id;
    $today = now()->toDateString();

    $day = Day::with('visibleItems')->where('user_id', $userId)->whereDate('date', $today)->first();
    $pickType = $day?->pick_type ?? 'pelicula';
    $queueItem = QueueItem::where('user_id', $userId)->where('type', $pickType)
        ->orderBy($this->orderColumn(...))->first(); // ver Task 5 orderColumn: determinístico o position
    ...
    return Inertia::render('fitness/dashboard', [/** rutina, block, pick, y los props existentes del dashboard viejo */]);
}
```

> El render del dashboard debe incluir los props que quedaron en `fitness/dashboard.tsx` (`workoutCount, recentWorkouts, caloriesToday, macrosToday, goals, lowStockSupplements`) **recuperados de git** (`git show f97d459:routes/web.php` tiene el closure original; extraer el cálculo a un `DashboardService` o inline en `TodayController`). `route('dashboard')` sigue apuntando al dashboard, no a un index separado.

- [ ] **Step 4: componentes React**

`TodayPanel` (tokens Ember, sin scroll, responsive 375px): fecha, 3 líneas (`DayItemRow`: checkbox 44px tap target, `title`, `anchor` mapeado a copy español, estado `done` → line-through + nota inline con affordance "Agregar nota" y placeholder "¿cómo te sentiste?", estado `pending` → botón "Soltar"), línea de media (`MediaLine`: "Hoy toca: <title>", `Siguiente`, selector de tipo, "Sorprendeme" — Sorprendeme llega en Task 5), bloque/rutina gris sin interacción. Vacío → "Hoy no hay nada elegido." + CTA "Elegir" → `/today/tomorrow`. Sin rojo/signos de exclamación.

- [ ] **Step 5: tests PASS + types**

Run: `php artisan test --compact --filter="Today"` → PASS. `npm run types` → 0.

- [ ] **Step 6: commit** — `git commit -m "feat(today): TodayPanel compartido + dashboard con seccion Hoy (sin hidratacion estatica)"`

---

## Task 4: Módulo Media — búsqueda externa + Queue

**Files:**
- Create: `app/Services/Media/MediaSearchService.php`, `app/Services/Media/MediaSearchResult.php`, `app/Services/Media/Providers/{WikidataProvider,OpenLibraryProvider,MusicBrainzProvider}.php`, `app/Http/Controllers/Today/QueueController.php` (extendido), `resources/js/pages/today/Queue.tsx` (extendido)
- Modify: `routes/web.php`, `tests/Feature/Today/MediaSearchTest.php`, `tests/Feature/Today/FlowTest.php`

**Interfaces (Produces):**
- `MediaSearchResult::fromArray(array): self` — props: `title, creator, year, cover_url, source, external_id`.
- `MediaSearchService::search(string $type, string $query): array<MediaSearchResult>` (vacío si query < 2 chars o provider falla).
- `GET /today/queue/search?type=pelicula&q=dune` → JSON `[{title, creator, year, cover_url, source, external_id}]`.
- `POST /today/queue` acepta `{title, type, source?, external_id?, cover_url?, year?, creator?}`; dedupe por `(user_id, source, external_id)`.

- [ ] **Step 1: tests media (RED)**

```php
test('media search normaliza resultados de open library', function () {
    Http::fake(['openlibrary.org/*' => Http::response([
        'docs' => [['title' => 'Dune', 'author_name' => ['Frank Herbert'], 'first_publish_year' => 1965, 'cover_i' => 123, 'key' => '/works/OL1W']],
    ], 200)]);

    $res = app(MediaSearchService::class)->search('libro', 'dune');
    expect($res)->toHaveCount(1);
    expect($res[0]->title)->toBe('Dune');
    expect($res[0]->creator)->toBe('Frank Herbert');
    expect($res[0]->source)->toBe('openlibrary');
});

test('media search tolera fallas del provider', function () {
    Http::fake(['*' => Http::response('boom', 500)]);
    expect(app(MediaSearchService::class)->search('pelicula', 'dune'))->toBe([]);
});

test('media search cachea 24h', function () {
    Http::fake(['openlibrary.org/*' => Http::response(['docs' => []], 200)]);
    app(MediaSearchService::class)->search('libro', 'dune');
    app(MediaSearchService::class)->search('libro', 'dune');
    Http::assertSentCount(1);
});

test('agregar a la cola deduplica por external_id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $body = ['title' => 'Dune', 'type' => 'libro', 'source' => 'openlibrary', 'external_id' => 'OL1W'];

    $this->post('/today/queue', $body)->assertRedirect();
    $this->post('/today/queue', $body)->assertRedirect();
    expect(QueueItem::where('user_id', $user->id)->count())->toBe(1);
});
```

- [ ] **Step 2: ver FAIL** → `MediaSearchService` no existe.

- [ ] **Step 3: implementar providers**

- `WikidataProvider` (pelicula/serie/juego): `wbsearchentities` → filtrar ids `Q` → `wbgetentities&props=claims|descriptions` con P31 en `{Q11424 pelicula, Q5398426 serie, Q7889 juego}`, P577 year, P18 imagen (`https://commons.wikimedia.org/wiki/Special:FilePath/{P18}?width=200`).
- `OpenLibraryProvider` (libro): `search.json?q=&limit=10` → cover `https://covers.openlibrary.org/b/id/{cover_i}-M.jpg`, `external_id = key`.
- `MusicBrainzProvider` (disco): `release-group?query={q} AND primarytype:album&fmt=json&limit=10` con header `User-Agent: Megalomaniac/1.0 (personal app)`; `external_id = id`; cover vía Cover Art Archive `https://coverartarchive.org/release-group/{id}/front-250` (si 404, null).
- `MediaSearchService`: switch por tipo, `Cache::remember("media:{$type}:".md5($q), now()->addDay(), fn () => …)`, try/catch `ConnectionException|RequestException` → `[]`, timeout 6s.

- [ ] **Step 4: UI Queue**

`Queue.tsx`: buscador con selector de tipo (5), debounce 300ms, `csrfHeaders()`, resultados con portada/creator/year y "+ Agregar"; lista con drag (patrón de `TaskKanban`/kanban existente: PATCH `/today/queue/reorder` con `{order:[ids]}`); "Siguiente" (ya existe); alta manual si la búsqueda no trae lo que buscás.

- [ ] **Step 5: tests PASS + commit** — `git commit -m "feat(today): media module con busqueda externa keyless (wikidata/openlibrary/musicbrainz)"`

---

## Task 5: Pick aleatorio + controles

**Files:** Modify: `app/Http/Controllers/Today/TodayController.php`, `app/Services/Media/DailyPickService.php` (nuevo), `resources/js/components/today/MediaLine.tsx`, `tests/Feature/Today/FlowTest.php`

**Interfaces (Produces):**
- `DailyPickService::pick(int $userId, string $date, string $type, Collection $items): ?QueueItem` — determinístico: `$index = hexdec(substr(sha1("{$userId}|{$date}|{$type}|".implode(',', $items->pluck('id')->all())), 0, 8)) % $items->count()`.
- `POST /today/pick-type` body `{type}` → setea `days.pick_type` (primera visita crea el day vacío? No: si no hay day hoy, espera; el pick igual usa default `pelicula`).
- `GET /today/surprise?type=` → JSON `{title, type, cover_url, source}` (o 204 si no hay items de ese tipo).

- [ ] **Step 1: tests (RED)**

```php
test('pick es determinístico por usuario fecha y tipo', function () {
    $user = User::factory()->create();
    $items = QueueItem::factory()->count(5)->create(['user_id' => $user->id, 'type' => 'pelicula']);
    $svc = app(DailyPickService::class);

    $a = $svc->pick($user->id, '2026-10-10', 'pelicula', $items);
    $b = $svc->pick($user->id, '2026-10-10', 'pelicula', $items);
    $c = $svc->pick($user->id, '2026-10-11', 'pelicula', $items);

    expect($a->id)->toBe($b->id);
    expect($a->id)->not->toBeNull();
    expect($c->id)->not->toBeNull();
});

test('surprise respeta el tipo elegido', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    QueueItem::factory()->create(['user_id' => $user->id, 'type' => 'libro', 'title' => 'Dune']);
    QueueItem::factory()->create(['user_id' => $user->id, 'type' => 'pelicula', 'title' => 'Heat']);

    $res = $this->getJson('/today/surprise?type=libro')->assertOk()->json();
    expect($res['title'])->toBe('Dune');
});
```

- [ ] **Step 2: ver FAIL**.
- [ ] **Step 3: implementar** `DailyPickService` + endpoint `surprise` + `pick-type`; `MediaLine` cablea "Sorprendeme" (reemplaza el título mostrado sin recargar) y el selector persiste el tipo.
- [ ] **Step 4: PASS + commit** — `git commit -m "feat(today): deterministic daily media pick + type selector"`.

---

## Task 6: Huecos B1-B4 + bloques CRUD + deshacer

**Files:** Modify/crear: `app/Console/Commands/Today{Notify,Close}.php`, `app/Notifications/ChooseTomorrowNotification.php`, `app/Http/Controllers/Today/{WeekController,ArchiveBulkController}.php`, `resources/js/pages/today/{BulkArchive,Week,Tomorrow}.tsx`, `routes/console.php`, `tests/Feature/Today/SystemTest.php`

**Interfaces (Produces):**
- `TodayNotify`: `$user->notify(new ChooseTomorrowNotification)` para cada user con `notifications` habilitado (global); texto neutro "¿Elegimos las 3 de mañana?".
- Bulk archive: `preview` sin `confirmado` no archiva; `run` guarda `session('bulk_archive_undo', ids)`; `GET /today/archived` muestra banner `Deshacer` si hay session y `POST /today/archived/undo` restaura y limpia session.
- `blocks` CRUD: `POST /today/blocks`, `PATCH /today/blocks/{block}`, `DELETE /today/blocks/{block}` + sección en `Week.tsx` (label, weekday select, start_time, duration, active toggle).

- [ ] **Step 1: tests (RED)**

```php
test('run de bulk archive sin preview/confirmado no archiva y deshacer restaura', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    ProjectTask::factory()->count(3)->create(['user_id' => $user->id, 'is_done' => false, 'created_at' => now()->subDays(40)]);

    $this->post('/today/bulk-archive/run', ['filter' => 'older_than', 'days' => 30])->assertInvalid(['confirmed']);
    expect(ProjectTask::notArchived()->count())->toBe(3);

    $this->post('/today/bulk-archive/run', ['filter' => 'older_than', 'days' => 30, 'confirmed' => true])->assertRedirect();
    expect(ProjectTask::archived()->count())->toBe(3);

    $this->post('/today/archived/undo')->assertRedirect();
    expect(ProjectTask::archived()->count())->toBe(0);
});

test('notify usa texto neutro sin culpa y cierra sin mutar', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->artisan('today:notify')->assertSuccessful();
    Notification::assertSentTo($user, ChooseTomorrowNotification::class);

    $day = Day::factory()->create(['user_id' => $user->id, 'date' => now()->toDateString()]);
    $item = DayItem::factory()->create(['day_id' => $day->id, 'state' => 'pending']);
    $this->artisan('today:close')->assertSuccessful();
    expect($item->fresh()->state)->toBe('pending');
});

test('bloques CRUD', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->post('/today/blocks', ['label' => 'Proyecto', 'weekday' => 3, 'start_time' => '19:00', 'duration_min' => 90])->assertRedirect();
    expect(Block::count())->toBe(1);
});
```

- [ ] **Step 2: ver FAIL**.
- [ ] **Step 3: implementar** comandos (schedule ya con `today:notify` 21:00 / `today:close` 00:05, renombrar los imports), notificación + migración ya creada en Task 1, undo en sesión, CRUD bloques, filtros `nunca|older_than|project|all` (`filter` values EN).
- [ ] **Step 4: PASS + commit** — `git commit -m "feat(today): B1 notification, bulk archive undo, blocks CRUD"`.

---

## Task 7: QA final + PR + deploy

- [ ] **Step 1: suite completa**

Run: `php artisan test --compact` → 0 failed.
Run: `vendor/bin/pint --dirty --format agent` → passed.
Run: `npm run types` → 0. Run: `npm run build` → OK.
Run: `npm run lint` → 0 errores nuevos vs baseline.

- [ ] **Step 2: smoke manual en build local** (`composer run dev` o `php artisan serve` + `npm run dev`): `/dashboard` (sección Hoy + cards), `/today/tomorrow` (elegir 3), `/today/week` (pool + buscador con pendientes), `/today/queue` (buscar "dune" → agregar), pick.

- [ ] **Step 3: PR** con checklist §6 + "qué no se hizo y por qué" (push notifications fuera de alcance; Radarr/Jellyseerr no usados).

- [ ] **Step 4: deploy** en `/root/docker/megalomaniac`: `./deploy.sh` (rsync + build + migrate + up) y verificación post-deploy de `/dashboard`.
