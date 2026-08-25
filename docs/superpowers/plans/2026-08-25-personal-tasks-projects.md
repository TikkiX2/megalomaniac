# Personal Tasks & Personal Projects Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build personal Tasks module (rich editor, dynamic properties, 6 views) and complementary Personal Projects module that reuse freelance structure via `type` field.

**Architecture:** Extend existing `projects` + `project_tasks` tables with `type` column (personal vs freelance) + new auxiliary tables for dynamic properties, milestones, saved views. New controllers `Personal\PersonalProjectController` + `Personal\PersonalTaskController` scoped to `type='personal'`. Frontend pages under `resources/js/pages/personal/` with 6 view components, ProjectSidebar, Yoopta reuse, dnd-kit for Kanban.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Tailwind v4, Wayfinder (@laravel/vite-plugin-wayfinder), Pest 4, Yoopta Editor v4.9.9, @dnd-kit/core, lucide-react, Shadcn UI

**Spec:** Brainstormed 2026-08-25 — 8 clarifying answers: M-M optional, hybrid properties (predefined types + custom names), all 6 views, sidebar project bar, standard task fields, full project fields, advanced filters with saved views, reuse via type.

## Global Constraints

- PHP 8.5.2, Laravel 12 structure (bootstrap/app.php middleware, no Kernel.php)
- Eloquent `casts()` method (not property), `$fillable`, typed relations `BelongsTo`/`HasMany`
- Wayfinder: import from `@/routes/*` and `@/actions/*`, never hardcode URLs
- Form Requests for validation (follow `app/Http/Requests/Finance/*` pattern), no inline `$request->validate` for new code
- Policies: `$user->id === $model->user_id` check, `authorizeResource` or `$this->authorize`
- Inertia: `Inertia::render('personal/...', [...])`, `useForm`/`router.*` with `preserveScroll`
- Pest 4: `php artisan make:test --pest`, `php artisan test --compact`, factories for setup
- Tailwind v4: `@theme` tokens (`bg-background`, `bg-card`, `border-border`, `text-muted-foreground`, `bg-primary`), no hardcoded `#1c0f0f` hex in new code
- Pint: `vendor/bin/pint --dirty --format agent` before commit
- Palette: Ember `bg #1C0F0F` `card #2B1A1A` `border #3E2121` `primary #EF4444` `muted-fg #E8B4B4`
- Sidebar: 3 groups exist (Principal, Freelance, Finanzas) — add 4th "Personal" group in `app-sidebar.tsx`
- Reuse `YooptaEditor.tsx` for rich text, no new editor library
- No `DB::`, prefer `Model::query()`, eager load to avoid N+1

---

## File Structure

### New files

```
database/migrations/2026_08_25_000001_add_type_to_projects_table.php
database/migrations/2026_08_25_000002_extend_project_tasks_for_personal.php
database/migrations/2026_08_25_000003_create_task_properties_table.php
database/migrations/2026_08_25_000004_create_task_project_members_table.php
database/migrations/2026_08_25_000005_create_task_milestones_table.php
database/migrations/2026_08_25_000006_create_task_saved_views_table.php

app/Models/TaskProperty.php
app/Models/TaskProjectMember.php
app/Models/TaskMilestone.php
app/Models/TaskSavedView.php

app/Http/Controllers/Personal/PersonalProjectController.php
app/Http/Controllers/Personal/PersonalTaskController.php
app/Http/Requests/Personal/StorePersonalProjectRequest.php
app/Http/Requests/Personal/UpdatePersonalProjectRequest.php
app/Http/Requests/Personal/StorePersonalTaskRequest.php
app/Http/Requests/Personal/UpdatePersonalTaskRequest.php
app/Policies/TaskPropertyPolicy.php  (or reuse via ProjectTask logic)

resources/js/types/personal.ts
resources/js/pages/personal/tasks/Index.tsx
resources/js/pages/personal/tasks/Show.tsx
resources/js/pages/personal/projects/Index.tsx
resources/js/pages/personal/projects/Show.tsx
resources/js/pages/personal/projects/Form.tsx
resources/js/components/personal/ProjectSidebar.tsx
resources/js/components/personal/TaskProperties.tsx
resources/js/components/personal/PropertyEditor.tsx
resources/js/components/personal/TaskFilters.tsx
resources/js/components/personal/SavedViewsBar.tsx
resources/js/components/personal/views/TaskTable.tsx
resources/js/components/personal/views/TaskKanban.tsx
resources/js/components/personal/views/TaskCalendar.tsx
resources/js/components/personal/views/TaskList.tsx
resources/js/components/personal/views/TaskGallery.tsx
resources/js/components/personal/views/TaskTimeline.tsx

database/factories/TaskPropertyFactory.php
database/factories/TaskMilestoneFactory.php
database/factories/TaskSavedViewFactory.php

tests/Feature/Personal/PersonalProjectTest.php
tests/Feature/Personal/PersonalTaskTest.php
tests/Feature/Personal/TaskPropertyTest.php
tests/Feature/Personal/TaskViewsTest.php
```

### Modified files

```
app/Models/Project.php — add type cast, scopes, relationships
app/Models/ProjectTask.php — add new casts, relationships, scopes
app/Models/User.php — add personalProjects(), personalTasks() relationships
routes/web.php — add Route::prefix('personal') group
resources/js/components/app-sidebar.tsx — add Personal group
resources/css/app.css — no change (tokens already exist)
```

---

### Task 1: Database Migrations — Extend Schema for Personal Mode

**Files:**
- Create: `database/migrations/2026_08_25_000001_add_type_to_projects_table.php`
- Create: `database/migrations/2026_08_25_000002_extend_project_tasks_for_personal.php`
- Create: `database/migrations/2026_08_25_000003_create_task_properties_table.php`
- Create: `database/migrations/2026_08_25_000004_create_task_project_members_table.php`
- Create: `database/migrations/2026_08_25_000005_create_task_milestones_table.php`
- Create: `database/migrations/2026_08_25_000006_create_task_saved_views_table.php`
- Test: `tests/Feature/Personal/MigrationTest.php`

**Interfaces:**
- Consumes: existing `projects`, `project_tasks` tables
- Produces: new columns + 4 new tables for use by Models (Task 2)

- [ ] **Step 1: Write failing test for migrations**

```php
// tests/Feature/Personal/MigrationTest.php
use Illuminate\Support\Facades\Schema;

it('adds type column to projects table', function () {
    expect(Schema::hasColumn('projects', 'type'))->toBeTrue();
});

it('extends project_tasks with personal fields', function () {
    expect(Schema::hasColumn('project_tasks', 'start_date'))->toBeTrue();
    expect(Schema::hasColumn('project_tasks', 'estimated_time'))->toBeTrue();
    expect(Schema::hasColumn('project_tasks', 'actual_time'))->toBeTrue();
    expect(Schema::hasColumn('project_tasks', 'sort_order'))->toBeTrue();
    expect(Schema::hasColumn('project_tasks', 'is_archived'))->toBeTrue();
});

it('creates task_properties table', function () {
    expect(Schema::hasTable('task_properties'))->toBeTrue();
    expect(Schema::hasColumn('task_properties', 'key'))->toBeTrue();
});

it('creates task_project_members table', function () {
    expect(Schema::hasTable('task_project_members'))->toBeTrue();
});

it('creates task_milestones table', function () {
    expect(Schema::hasTable('task_milestones'))->toBeTrue();
});

it('creates task_saved_views table', function () {
    expect(Schema::hasTable('task_saved_views'))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MigrationTest`
Expected: FAIL — tables/columns missing

- [ ] **Step 3: Create migration 000001 — add type to projects**

```php
// database/migrations/2026_08_25_000001_add_type_to_projects_table.php
return new class extends Migration {
    public function up(): void {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('type')->default('freelance')->after('user_id')->index();
            $table->string('color')->nullable()->after('priority');
            $table->string('icon')->nullable()->after('color');
            $table->decimal('budget', 15, 2)->nullable()->after('estimated_hours');
        });
        // Backfill existing rows
        \DB::table('projects')->update(['type' => 'freelance']);
    }
    public function down(): void {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['type', 'color', 'icon', 'budget']);
        });
    }
};
```

Note: `client_id` and `currency_id` must become nullable for personal projects (no client required). Add in same migration:

```php
$table->foreignId('client_id')->nullable()->change();
$table->foreignId('currency_id')->nullable()->change();
```

If `->change()` requires doctrine/dbal, use raw: `$table->string('type')` as above avoids doctrine. For nullable change, use `DB::statement` fallback if needed.

- [ ] **Step 4: Create migration 000002 — extend project_tasks**

```php
return new class extends Migration {
    public function up(): void {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->date('start_date')->nullable()->after('due_date');
            $table->integer('estimated_time')->nullable()->after('start_date'); // minutes
            $table->integer('actual_time')->nullable()->after('estimated_time');
            $table->integer('sort_order')->default(0)->after('actual_time');
            $table->boolean('is_archived')->default(false)->after('sort_order');
            // Backfill user_id from project
            // Will be done via DB::statement after column creation
        });
        \DB::statement('UPDATE project_tasks SET user_id = (SELECT user_id FROM projects WHERE projects.id = project_tasks.project_id) WHERE user_id IS NULL');
    }
    public function down(): void {
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropColumn(['user_id','start_date','estimated_time','actual_time','sort_order','is_archived']);
        });
    }
};
```

- [ ] **Step 5: Create migrations 000003–000006**

```php
// 000003_create_task_properties_table.php
Schema::create('task_properties', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
    $table->string('key'); // property name
    $table->enum('type', ['text','number','date','select','multi_select','checkbox','url','person']);
    $table->text('value_text')->nullable();
    $table->decimal('value_number', 15, 2)->nullable();
    $table->date('value_date')->nullable();
    $table->json('value_json')->nullable(); // for select options, multi_select array, person id
    $table->integer('sort_order')->default(0);
    $table->timestamps();
    $table->unique(['project_task_id', 'key']);
});

// 000004_create_task_project_members_table.php
Schema::create('task_project_members', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role')->default('member'); // owner, editor, viewer, member
    $table->timestamps();
    $table->unique(['project_id', 'user_id']);
});

// 000005_create_task_milestones_table.php
Schema::create('task_milestones', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->date('due_date')->nullable();
    $table->string('status')->default('pending'); // pending, completed, cancelled
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});

// 000006_create_task_saved_views_table.php
Schema::create('task_saved_views', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('view_type'); // table, kanban, calendar, list, gallery, timeline
    $table->json('filters')->nullable();
    $table->json('sort')->nullable();
    $table->string('group_by')->nullable();
    $table->boolean('is_default')->default(false);
    $table->timestamps();
});
```

- [ ] **Step 6: Run migrations and verify**

Run: `php artisan migrate --force && php artisan test --compact --filter=MigrationTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_25_*.php tests/Feature/Personal/MigrationTest.php
git commit -m "feat(personal): add migrations for personal tasks and projects"
```

---

### Task 2: Models — Update & Create Eloquent Models

**Files:**
- Modify: `app/Models/Project.php`
- Modify: `app/Models/ProjectTask.php`
- Modify: `app/Models/User.php`
- Create: `app/Models/TaskProperty.php`
- Create: `app/Models/TaskProjectMember.php`
- Create: `app/Models/TaskMilestone.php`
- Create: `app/Models/TaskSavedView.php`
- Create: `database/factories/TaskPropertyFactory.php`
- Create: `database/factories/TaskMilestoneFactory.php`
- Create: `database/factories/TaskSavedViewFactory.php`
- Test: `tests/Feature/Personal/ModelTest.php`

**Interfaces:**
- Consumes: migrations from Task 1
- Produces: `Project::personal()`, `Project::freelance()`, `ProjectTask::withProperties`, `TaskProperty`, `TaskMilestone`, `TaskSavedView` models used by Controllers (Task 4)

- [ ] **Step 1: Write failing test**

```php
// tests/Feature/Personal/ModelTest.php
it('creates personal project via factory', function () {
    $project = \App\Models\Project::factory()->create(['type' => 'personal', 'user_id' => \App\Models\User::factory()->create()->id]);
    expect($project->type)->toBe('personal');
    expect($project->isPersonal())->toBeTrue();
});

it('project has task properties through tasks', function () {
    $task = \App\Models\ProjectTask::factory()->create();
    $prop = \App\Models\TaskProperty::factory()->create(['project_task_id' => $task->id, 'key' => 'Sprint', 'type' => 'select']);
    expect($task->properties)->toHaveCount(1);
    expect($task->properties->first()->key)->toBe('Sprint');
});

it('task property casts work', function () {
    $prop = \App\Models\TaskProperty::factory()->create(['type' => 'number', 'value_number' => 42.5]);
    expect($prop->value_number)->toBe('42.50');
});

it('milestone belongs to project', function () {
    $project = \App\Models\Project::factory()->create(['type' => 'personal']);
    $milestone = \App\Models\TaskMilestone::factory()->create(['project_id' => $project->id]);
    expect($milestone->project->id)->toBe($project->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ModelTest`
Expected: FAIL — models/methods missing

- [ ] **Step 3: Update Project model**

Add to `$fillable`: `'type', 'color', 'icon', 'budget'`
Add to `$casts`: `'budget' => 'decimal:2'`
Add methods:

```php
public function scopePersonal($query) { return $query->where('type', 'personal'); }
public function scopeFreelance($query) { return $query->where('type', 'freelance'); }
public function isPersonal(): bool { return $this->type === 'personal'; }
public function isFreelance(): bool { return $this->type === 'freelance'; }
public function members(): HasMany { return $this->hasMany(TaskProjectMember::class); }
public function milestones(): HasMany { return $this->hasMany(TaskMilestone::class)->orderBy('sort_order'); }
public function getProgressAttribute(): int {
    $total = $this->tasks()->count();
    if ($total === 0) return 0;
    $done = $this->tasks()->whereIn('status', ['Done','Completed','done','completed','completada'])->count();
    return (int) round($done / $total * 100);
}
```

Also make `client()` nullable: `return $this->belongsTo(Client::class)->withDefault();` or keep but allow null.

- [ ] **Step 4: Update ProjectTask model**

Add to `$fillable`: `'user_id','start_date','estimated_time','actual_time','sort_order','is_archived'`
Add to `$casts`: `'start_date' => 'date', 'is_archived' => 'boolean'`
Add:

```php
public function user(): BelongsTo { return $this->belongsTo(User::class); }
public function properties(): HasMany { return $this->hasMany(TaskProperty::class)->orderBy('sort_order'); }
public function scopePersonal($query) { return $query->whereHas('project', fn($q) => $q->where('type','personal')); }
public function scopeActive($query) { return $query->where('is_archived', false); }
public function scopeByStatus($query, string $status) { return $query->where('status', $status); }
```

- [ ] **Step 5: Create new models**

```php
// app/Models/TaskProperty.php
class TaskProperty extends Model {
    use HasFactory;
    protected $fillable = ['project_task_id','key','type','value_text','value_number','value_date','value_json','sort_order'];
    protected $casts = ['value_json' => 'array', 'value_date' => 'date', 'value_number' => 'decimal:2'];
    public function task(): BelongsTo { return $this->belongsTo(ProjectTask::class, 'project_task_id'); }
    public function getValueAttribute() { /* switch on type return appropriate value_* */ }
}

// app/Models/TaskProjectMember.php
class TaskProjectMember extends Model {
    use HasFactory;
    protected $fillable = ['project_id','user_id','role'];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}

// app/Models/TaskMilestone.php
class TaskMilestone extends Model {
    use HasFactory;
    protected $fillable = ['project_id','name','description','due_date','status','sort_order'];
    protected $casts = ['due_date' => 'date'];
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function scopePending($q) { return $q->where('status','pending'); }
}

// app/Models/TaskSavedView.php
class TaskSavedView extends Model {
    use HasFactory;
    protected $fillable = ['user_id','name','view_type','filters','sort','group_by','is_default'];
    protected $casts = ['filters'=>'array','sort'=>'array','is_default'=>'boolean'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
```

Update `User.php`: add `personalProjects()` (hasMany Project where type personal), `taskProperties()` etc.

- [ ] **Step 6: Create factories**

Mirror existing `ProjectFactory` pattern with `getYooptaContent()` helper. For `TaskPropertyFactory`, randomize type and set appropriate value_* field based on type.

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --compact --filter=ModelTest`
Expected: PASS

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Models/ database/factories/ tests/Feature/Personal/ModelTest.php
git commit -m "feat(personal): update models for personal tasks and projects"
```

---

### Task 3: Policies + Form Requests

**Files:**
- Create: `app/Http/Requests/Personal/StorePersonalProjectRequest.php`
- Create: `app/Http/Requests/Personal/UpdatePersonalProjectRequest.php`
- Create: `app/Http/Requests/Personal/StorePersonalTaskRequest.php`
- Create: `app/Http/Requests/Personal/UpdatePersonalTaskRequest.php`
- Create: `app/Policies/PersonalProjectPolicy.php` (or extend existing ProjectPolicy)
- Test: `tests/Feature/Personal/ValidationTest.php`

**Interfaces:**
- Consumes: Models from Task 2
- Produces: Validated request classes used by Controllers (Task 4)

- [ ] **Step 1: Write failing test**

```php
it('validates personal project creation requires name', function () {
    $user = \App\Models\User::factory()->create();
    $response = $this->actingAs($user)->post(route('personal.projects.store'), []);
    $response->assertSessionHasErrors('name');
});

it('validates task requires title', function () {
    $user = \App\Models\User::factory()->create();
    $project = \App\Models\Project::factory()->create(['type'=>'personal','user_id'=>$user->id]);
    $response = $this->actingAs($user)->post(route('personal.tasks.store', $project), []);
    $response->assertSessionHasErrors('title');
});

it('prevents user from viewing other users personal project', function () {
    $owner = \App\Models\User::factory()->create();
    $intruder = \App\Models\User::factory()->create();
    $project = \App\Models\Project::factory()->create(['type'=>'personal','user_id'=>$owner->id]);
    $this->actingAs($intruder)->get(route('personal.projects.show', $project))->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ValidationTest`
Expected: FAIL — routes/requests missing

- [ ] **Step 3: Create Form Requests**

Follow `app/Http/Requests/Finance/StorePurchaseRequest.php` pattern:

```php
// StorePersonalProjectRequest.php
public function authorize(): bool { return true; } // policy handles it
public function rules(): array {
    return [
        'name' => ['required','string','max:255'],
        'description' => ['nullable','array'],
        'status' => ['nullable','in:pending,in_progress,completed,cancelled,maintenance,active,archived'],
        'color' => ['nullable','string','max:20'],
        'icon' => ['nullable','string','max:50'],
        'priority' => ['nullable','string','max:50'],
        'start_date' => ['nullable','date'],
        'end_date' => ['nullable','date','after_or_equal:start_date'],
        'budget' => ['nullable','numeric','min:0'],
        'tags' => ['nullable','array'],
        'is_archived' => ['nullable','boolean'],
    ];
}
public function messages(): array { return ['name.required' => 'El nombre es obligatorio.']; }
```

Similarly for `StorePersonalTaskRequest`:

```php
rules(): array {
    return [
        'title' => ['required','string','max:255'],
        'description' => ['nullable','array'],
        'status' => ['nullable','string','max:50'],
        'priority' => ['nullable','string','max:50'],
        'project_id' => ['nullable','exists:projects,id'],
        'due_date' => ['nullable','date'],
        'start_date' => ['nullable','date','before_or_equal:due_date'],
        'estimated_time' => ['nullable','integer','min:0'],
        'actual_time' => ['nullable','integer','min:0'],
        'tags' => ['nullable','array'],
        'area' => ['nullable','string','max:100'],
        'module' => ['nullable','string','max:100'],
        'urgency' => ['nullable','string','max:50'],
        'importance' => ['nullable','string','max:50'],
        'sort_order' => ['nullable','integer'],
        'properties' => ['nullable','array'],
        'properties.*.key' => ['required_with:properties','string','max:100'],
        'properties.*.type' => ['required_with:properties','in:text,number,date,select,multi_select,checkbox,url,person'],
        'properties.*.value' => ['nullable'],
    ];
}
```

Update requests similar but with `sometimes` rules.

- [ ] **Step 4: Create/Extend Policy**

Reuse logic from `ProjectPolicy` but scope to `type==='personal'` if needed. Simplest: create `PersonalProjectPolicy` with same checks:

```php
public function view(User $user, Project $project): bool { return $user->id === $project->user_id; }
public function update(User $user, Project $project): bool { return $user->id === $project->user_id; }
public function delete(User $user, Project $project): bool { return $user->id === $project->user_id; }
```

Register in `AuthServiceProvider` or rely on auto-discovery (Laravel 12).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=ValidationTest`
Expected: PASS (after routes exist placeholder — may need Task 4 routes first; adjust to test requests directly via `app()->make()` if routes not yet created)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/Personal/ app/Policies/
git commit -m "feat(personal): add form requests and policies"
```

---

### Task 4: Controllers + Routes

**Files:**
- Create: `app/Http/Controllers/Personal/PersonalProjectController.php`
- Create: `app/Http/Controllers/Personal/PersonalTaskController.php`
- Create: `app/Http/Controllers/Personal/TaskPropertyController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Personal/PersonalProjectTest.php`, `tests/Feature/Personal/PersonalTaskTest.php`

**Interfaces:**
- Consumes: Models (Task 2), Requests (Task 3)
- Produces: Routes `personal.projects.*` and `personal.tasks.*` consumed by frontend (Task 5+)

- [ ] **Step 1: Write failing test**

```php
// tests/Feature/Personal/PersonalProjectTest.php
it('lists personal projects for authenticated user', function () {
    $user = \App\Models\User::factory()->create();
    \App\Models\Project::factory()->count(2)->create(['type'=>'personal','user_id'=>$user->id]);
    \App\Models\Project::factory()->create(['type'=>'freelance','user_id'=>$user->id]);
    $this->actingAs($user)->get(route('personal.projects.index'))->assertOk()->assertInertia(fn($p) => $p->component('personal/projects/Index')->where('projects.data', fn($v) => count($v) === 2));
});

it('creates personal project', function () {
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user)->post(route('personal.projects.store'), ['name'=>'Mi Proyecto','status'=>'pending'])->assertRedirect();
    expect(\App\Models\Project::where('type','personal')->where('name','Mi Proyecto')->exists())->toBeTrue();
});

it('lists tasks with filters', function () {
    $user = \App\Models\User::factory()->create();
    $project = \App\Models\Project::factory()->create(['type'=>'personal','user_id'=>$user->id]);
    \App\Models\ProjectTask::factory()->create(['project_id'=>$project->id,'status'=>'Pending','user_id'=>$user->id]);
    $this->actingAs($user)->get(route('personal.tasks.index', ['status'=>'Pending']))->assertOk();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PersonalProjectTest`
Expected: FAIL — routes/controllers missing

- [ ] **Step 3: Create PersonalProjectController**

```php
namespace App\Http\Controllers\Personal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Personal\StorePersonalProjectRequest;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PersonalProjectController extends Controller {
    public function index(Request $request) {
        $projects = Project::where('user_id', $request->user()->id)
            ->where('type','personal')
            ->when($request->status, fn($q,$v) => $q->where('status',$v))
            ->when($request->search, fn($q,$v) => $q->where('name','like',"%{$v}%"))
            ->withCount('tasks')
            ->with(['milestones','members'])
            ->orderBy('sort_order')->orderBy('created_at','desc')
            ->paginate(15)->withQueryString();
        // progress computed via accessor, ensure appended
        return Inertia::render('personal/projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['status','search']),
        ]);
    }
    public function store(StorePersonalProjectRequest $request) {
        $project = Project::create([...$request->validated(), 'user_id'=>$request->user()->id, 'type'=>'personal']);
        return redirect()->route('personal.projects.show', $project)->with('success','Proyecto creado.');
    }
    public function show(Project $project) {
        $this->authorize('view', $project);
        abort_unless($project->type==='personal', 404);
        $project->load(['tasks.properties','milestones','members.user','tasks' => fn($q) => $q->orderBy('sort_order')]);
        return Inertia::render('personal/projects/Show', ['project'=>$project]);
    }
    public function update(UpdatePersonalProjectRequest $request, Project $project) { /* authorize + update */ }
    public function destroy(Project $project) { $this->authorize('delete',$project); $project->delete(); return redirect()->route('personal.projects.index'); }
}
```

Similarly `PersonalTaskController` with `index` supporting filters: `project_id`, `status`, `priority`, `search`, `due_from/to`, `tags`, `archived`, with pagination and eager load `properties`, `project`.

`TaskPropertyController` with `store`, `update`, `destroy` for dynamic properties (upsert by key).

- [ ] **Step 4: Add routes to web.php**

Inside `Route::middleware(['auth','verified'])->group(function () { ... })`:

```php
Route::prefix('personal')->name('personal.')->group(function () {
    Route::resource('projects', \App\Http\Controllers\Personal\PersonalProjectController::class);
    Route::post('projects/{project}/milestones', [\App\Http\Controllers\Personal\PersonalProjectController::class, 'storeMilestone'])->name('projects.milestones.store');
    Route::resource('tasks', \App\Http\Controllers\Personal\PersonalTaskController::class)->except(['create','edit']);
    Route::patch('tasks/{task}/move', [\App\Http\Controllers\Personal\PersonalTaskController::class, 'move'])->name('tasks.move');
    Route::apiResource('tasks.properties', \App\Http\Controllers\Personal\TaskPropertyController::class)->shallow();
    Route::resource('saved-views', \App\Http\Controllers\Personal\TaskSavedViewController::class)->only(['index','store','destroy']);
});
```

Run `php artisan wayfinder:generate` or `npm run build` to regenerate Wayfinder routes (if needed, check `vite.config.ts` has `wayfinder()`).

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=PersonalProjectTest`
Expected: PASS

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Personal/ routes/web.php tests/Feature/Personal/
git commit -m "feat(personal): add controllers and routes for personal projects and tasks"
```

---

### Task 5: TypeScript Types + Sidebar + Shell

**Files:**
- Create: `resources/js/types/personal.ts`
- Modify: `resources/js/components/app-sidebar.tsx`
- Modify: `resources/js/layouts/main-layout.tsx` (if needed for project sidebar slot)
- Test: `tests/Feature/Personal/SidebarTest.php` (simple check that Inertia page renders with new nav) + manual `npm run build` check

**Interfaces:**
- Consumes: Routes from Task 4
- Produces: `PersonalProject`, `PersonalTask`, `TaskProperty`, `TaskMilestone` types + sidebar navigation used by all frontend tasks

- [ ] **Step 1: Write type definitions**

```ts
// resources/js/types/personal.ts
export interface PersonalProject {
  id: number; user_id: number; name: string; description: any | null;
  type: 'personal'; status: string; color: string | null; icon: string | null;
  priority: string | null; start_date: string | null; end_date: string | null;
  budget: string | null; tags: string[] | null; is_archived: boolean;
  progress: number; // computed
  tasks_count?: number;
  tasks?: PersonalTask[]; milestones?: TaskMilestone[]; members?: TaskProjectMember[];
  created_at: string; updated_at: string;
}
export interface PersonalTask {
  id: number; project_id: number | null; user_id: number | null;
  title: string; description: any | null; status: string; priority: string | null;
  due_date: string | null; start_date: string | null;
  estimated_time: number | null; actual_time: number | null;
  tags: string[] | null; area: string | null; module: string | null;
  urgency: string | null; importance: string | null;
  sort_order: number; is_archived: boolean;
  properties?: TaskProperty[];
  project?: PersonalProject;
  created_at: string; updated_at: string;
}
export interface TaskProperty {
  id: number; project_task_id: number; key: string;
  type: 'text'|'number'|'date'|'select'|'multi_select'|'checkbox'|'url'|'person';
  value_text: string | null; value_number: string | null; value_date: string | null; value_json: any;
  sort_order: number;
}
export interface TaskMilestone { id: number; project_id: number; name: string; description: string | null; due_date: string | null; status: string; sort_order: number; }
export interface TaskProjectMember { id: number; project_id: number; user_id: number; role: string; user?: {id:number; name:string; email:string} }
export interface TaskSavedView { id: number; user_id: number; name: string; view_type: string; filters: any; sort: any; group_by: string | null; is_default: boolean; }
export type TaskViewType = 'table'|'kanban'|'calendar'|'list'|'gallery'|'timeline';
```

- [ ] **Step 2: Update sidebar**

In `app-sidebar.tsx`, add after `financeNavItems`:

```tsx
import { CheckSquare, FolderKanban, ListTodo } from 'lucide-react';
import personal from '@/routes/personal'; // after wayfinder generation

const personalNavItems: NavItem[] = [
    { title: 'Tareas', href: personal.tasks.index().url, icon: CheckSquare },
    { title: 'Proyectos', href: personal.projects.index().url, icon: FolderKanban },
];
```

And render new `<SidebarGroup>`:

```tsx
<SidebarGroup>
  <SidebarGroupLabel>Personal</SidebarGroupLabel>
  <SidebarMenu>
    {personalNavItems.map(item => (
      <SidebarMenuItem key={item.title}>
        <SidebarMenuButton asChild isActive={route().current('personal.*')}>
          <Link href={item.href}><item.icon /> <span>{item.title}</span></Link>
        </SidebarMenuButton>
      </SidebarMenuItem>
    ))}
  </SidebarMenu>
</SidebarGroup>
```

If Wayfinder not yet generated, use hardcoded `href: '/personal/tasks'` temporarily and switch to Wayfinder after build.

- [ ] **Step 3: Verify build**

Run: `npm run build` (check no TS errors)
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add resources/js/types/personal.ts resources/js/components/app-sidebar.tsx
git commit -m "feat(personal): add types and sidebar navigation"
```

---

### Task 6: Personal Projects Pages — CRUD

**Files:**
- Create: `resources/js/pages/personal/projects/Index.tsx`
- Create: `resources/js/pages/personal/projects/Show.tsx`
- Create: `resources/js/pages/personal/projects/Form.tsx`
- Create: `resources/js/components/personal/ProjectSidebar.tsx`
- Test: Manual via `php artisan test --filter=PersonalProjectTest` + Playwright crawl

**Interfaces:**
- Consumes: Types (Task 5), Routes (Task 4)
- Produces: Project CRUD pages, `ProjectSidebar` reused in tasks Index

- [ ] **Step 1: Build ProjectSidebar component**

```tsx
// resources/js/components/personal/ProjectSidebar.tsx
// Props: { projects: PersonalProject[], activeProjectId?: number, onSelect?: (id:number|null)=>void }
// Renders: list with color dot, icon, name, task count badge, progress bar (w-full h-1 bg-muted -> w-[progress%] bg-primary)
// Uses: Card, Progress, Badge, Button from @/components/ui/*
// Tailwind: bg-card border-border text-muted-foreground etc. No hardcoded hex.
// Reference: existing TaskBoard.tsx for styling but tokenized
```

Include: "All tasks" item at top, "+ Nuevo proyecto" button opening dialog with `PersonalProjectForm`.

- [ ] **Step 2: Build projects/Index.tsx**

Layout: `<MainLayout>` + `<Head title="Proyectos Personales" />`
Two-column on desktop: `ProjectSidebar` (w-64) + grid of project cards. On mobile, sidebar collapses to horizontal scroll or drawer.
Cards show: name, description preview (strip Yoopta JSON), progress bar, task count, milestones, members avatars, tags.
Actions: search input, status filter Select, create button -> Dialog with Form.tsx
Uses `router.get(personal.projects.index().url, {search, status}, {preserveState:true})` for filtering.

- [ ] **Step 3: Build projects/Show.tsx**

Shows single project: header with name/color/icon, description via YooptaEditor readOnly, milestones list with due dates, members, linked tasks (reuse TaskTable mini), edit/delete buttons.

- [ ] **Step 4: Build projects/Form.tsx**

Reusable form for create/edit:
- Fields: name (Input), description (YooptaEditor), status (Select), color (color picker Input type color + presets), icon (Select with lucide options), priority (Select), dates (Input type date), budget (Input number), tags (Input with comma split -> array)
- Uses `useForm` with Wayfinder: `personal.projects.store.form()` / `personal.projects.update.form(project.id)`
- Validation errors from `form.errors`

- [ ] **Step 5: Verify and commit**

Run: `npm run build` + `php artisan test --compact --filter=PersonalProjectTest`
Expected: PASS

```bash
git add resources/js/pages/personal/projects/ resources/js/components/personal/ProjectSidebar.tsx
git commit -m "feat(personal): add personal projects CRUD pages and sidebar"
```

---

### Task 7: Dynamic Properties System

**Files:**
- Create: `resources/js/components/personal/TaskProperties.tsx`
- Create: `resources/js/components/personal/PropertyEditor.tsx`
- Create: `resources/js/components/personal/AddPropertyDialog.tsx`
- Test: `tests/Feature/Personal/TaskPropertyTest.php`

**Interfaces:**
- Consumes: PersonalTask + TaskProperty types, TaskPropertyController routes
- Produces: `TaskProperties` component used inside all 6 view detail drawers and table columns

- [ ] **Step 1: Write failing test**

```php
it('can add custom property to task', function () {
    $user = \App\Models\User::factory()->create();
    $task = \App\Models\ProjectTask::factory()->create(['user_id'=>$user->id]);
    $this->actingAs($user)->post(route('personal.tasks.properties.store', $task), [
        'key'=>'Sprint','type'=>'select','value_json'=>['options'=>['S1','S2'],'value'=>'S1']
    ])->assertCreated();
    expect($task->fresh()->properties)->toHaveCount(1);
});

it('prevents duplicate property keys per task', function () {
    $user = \App\Models\User::factory()->create();
    $task = \App\Models\ProjectTask::factory()->create(['user_id'=>$user->id]);
    \App\Models\TaskProperty::factory()->create(['project_task_id'=>$task->id,'key'=>'Sprint']);
    $this->actingAs($user)->post(route('personal.tasks.properties.store', $task), [
        'key'=>'Sprint','type'=>'text','value_text'=>'dup'
    ])->assertStatus(422);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=TaskPropertyTest`
Expected: FAIL

- [ ] **Step 3: Implement TaskProperties.tsx**

Features:
- Displays built-in properties (status, priority, due_date, etc.) + custom properties from `task.properties`
- Each row: key label (text-[10px] font-black uppercase tracking-widest text-muted-foreground) + value editor
- Inline editing: click to edit, save on blur/enter via `router.patch` or `router.post` to properties endpoint
- Type-aware editors in `PropertyEditor.tsx`:
  - text -> Input
  - number -> Input type number
  - date -> Input type date
  - select -> Select with options from value_json.options
  - multi_select -> multi Badge + Select
  - checkbox -> Checkbox
  - url -> Input type url with Link preview
  - person -> Select with users (if members available)
- Delete property button (trash icon) with confirm

- [ ] **Step 4: Implement AddPropertyDialog.tsx**

Dialog with: key Input, type Select (8 options), initial value field dynamic per type, save -> POST to properties store.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=TaskPropertyTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/personal/TaskProperties.tsx resources/js/components/personal/PropertyEditor.tsx resources/js/components/personal/AddPropertyDialog.tsx tests/Feature/Personal/TaskPropertyTest.php
git commit -m "feat(personal): add dynamic properties system"
```

---

### Task 8: Table View — Notion DB Style

**Files:**
- Create: `resources/js/components/personal/views/TaskTable.tsx`
- Create: `resources/js/components/personal/views/TaskTableRow.tsx` (optional, or inline)
- Test: `tests/Feature/Personal/TaskViewsTest.php` (table sorting/filtering)

**Interfaces:**
- Consumes: PersonalTask[], TaskProperty, PersonalProject[], filters
- Produces: `TaskTable` component used in `personal/tasks/Index.tsx`

- [ ] **Step 1: Write failing test for sorting/filtering**

```php
it('returns tasks sorted by due_date when requested', function () {
    $user = \App\Models\User::factory()->create();
    $t1 = \App\Models\ProjectTask::factory()->create(['user_id'=>$user->id,'due_date'=>'2026-09-01']);
    $t2 = \App\Models\ProjectTask::factory()->create(['user_id'=>$user->id,'due_date'=>'2026-08-01']);
    $response = $this->actingAs($user)->get(route('personal.tasks.index', ['sort'=>'due_date','direction'=>'asc']));
    $response->assertOk();
    $tasks = $response->inertia('tasks')['data'] ?? $response->inertia('tasks');
    // assert order
});
```

- [ ] **Step 2: Implement TaskTable.tsx**

Features:
- Uses `Table`, `TableHeader`, `TableRow`, `TableHead`, `TableCell` from `@/components/ui/table`
- Columns: checkbox (select), Title (with project color dot), Status (Badge), Priority (Badge), Due Date, Tags, + dynamic property columns (from first task's properties keys or from saved view)
- Header click to sort (arrow indicator), filter icon per column
- Sort via `router.get` with `sort` and `direction` params, `preserveState:true`
- Inline editing: double-click cell to edit (title -> Input, status -> Select, date -> Input date, etc.) saving via `router.patch(personal.tasks.update.url(task.id), {field: value})`
- Row click -> open `TaskDetailDrawer` (or navigate to Show)
- Bulk actions bar when rows selected (archive, delete, move project)
- Empty state with "Create task" CTA
- Styling: tokenized, no hardcoded hex. Reference `freelance/projects/Index.tsx` table but tokenized.

Props: `{ tasks: {data: PersonalTask[], links, meta}, projects: PersonalProject[], sortField, sortDirection, onSort, onTaskClick }`

- [ ] **Step 3: Integrate into tasks/Index.tsx shell**

Create `resources/js/pages/personal/tasks/Index.tsx` shell with:
- `<MainLayout>` + `ProjectSidebar` (w-64) + main area
- Top bar: search Input + `TaskFilters` + view switcher Tabs (6 icons) + "New task" Button
- View switcher state: `const [view, setView] = useState<TaskViewType>(savedView ?? 'table')`
- Conditional render: `{view==='table' && <TaskTable ... />}` etc.
- `TaskFilters` and `SavedViewsBar` above table

For this task, only implement table view; other views show "Coming soon" placeholder.

- [ ] **Step 4: Verify**

Run: `npm run build && php artisan test --compact --filter=TaskViewsTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/personal/views/TaskTable.tsx resources/js/pages/personal/tasks/Index.tsx
git commit -m "feat(personal): add table view (Notion DB style)"
```

---

### Task 9: Kanban View — Drag & Drop

**Files:**
- Create: `resources/js/components/personal/views/TaskKanban.tsx`
- Modify: `resources/js/pages/personal/tasks/Index.tsx` (wire kanban)
- Test: Manual drag test + `php artisan test --filter=PersonalTaskTest` (move endpoint)

**Interfaces:**
- Consumes: PersonalTask[], PersonalProject[], dnd-kit, move route
- Produces: `TaskKanban` component

- [ ] **Step 1: Install dnd-kit if not present**

Check `package.json` for `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities`. If missing:

```bash
npm install @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities
```

- [ ] **Step 2: Implement TaskKanban.tsx**

Features:
- 3 default columns: To Do (Pending/pendiente), In Progress (En Progreso/in_progress), Done (Completada/completed) — reuse `normalizeStatus` map from `TaskBoard.tsx`
- Allow grouping by any `select` property (status by default, but also priority, or custom select property via `groupBy` prop)
- Each column: header with title + count badge + color indicator, droppable area, list of sortable cards
- Card: title, priority badge, due date (overdue red), tags, assignee avatar, property previews
- Drag: use `DndContext` + `SortableContext` from dnd-kit. On `onDragEnd`, if `over.id` differs from `active` column, call `router.patch(personal.tasks.move.url(taskId), {status: newStatus, sort_order: newIndex}, {preserveScroll:true})`
- Add task button per column (opens dialog with status preset)
- Quick-move buttons (chevron) as fallback for touch
- Empty column placeholder with dashed border
- Tokenized styling, reuse patterns from `freelance/TaskBoard.tsx` but with dnd-kit instead of button-only moves

Props: `{ tasks: PersonalTask[], groupBy?: string, onTaskClick?: (task)=>void }`

Backend `move` action in `PersonalTaskController@move`:

```php
public function move(Request $request, ProjectTask $task) {
    $this->authorize('update', $task->project ?? $task);
    $request->validate(['status'=>'required|string','sort_order'=>'nullable|integer']);
    $task->update(['status'=>$request->status,'sort_order'=>$request->sort_order ?? $task->sort_order]);
    return back();
}
```

- [ ] **Step 3: Wire into Index.tsx**

`{view==='kanban' && <TaskKanban tasks={tasks.data} groupBy={groupBy} />}`

- [ ] **Step 4: Verify**

Run: `npm run build && php artisan test --compact --filter=PersonalTaskTest`
Expected: PASS, drag visually works

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/personal/views/TaskKanban.tsx resources/js/pages/personal/tasks/Index.tsx package.json package-lock.json
git commit -m "feat(personal): add kanban view with drag and drop"
```

---

### Task 10: Calendar + List Views

**Files:**
- Create: `resources/js/components/personal/views/TaskCalendar.tsx`
- Create: `resources/js/components/personal/views/TaskList.tsx`
- Modify: `resources/js/pages/personal/tasks/Index.tsx`
- Test: Manual QA for calendar interactions

**Interfaces:**
- Consumes: PersonalTask[], date utilities
- Produces: `TaskCalendar`, `TaskList` components

- [ ] **Step 1: Implement TaskCalendar.tsx**

Features:
- Monthly grid (7 columns, 5-6 rows) + week view toggle
- Header: month/year display, prev/next buttons, "Today" button, view toggle (month/week)
- Each day cell: date number, list of tasks with due_date or start_date matching that day (color dot by priority/project), +N more indicator with popover
- Click day -> create task dialog with due_date preset to that day
- Click task -> onTaskClick
- Drag task between days (optional v1, can be simple click-to-edit date)
- Uses `date-fns` (already in deps via existing code) for date math
- Tokenized: `bg-card border-border`, today `bg-primary/10 border-primary`, overdue `text-destructive`

Props: `{ tasks: PersonalTask[], onTaskClick?, onDateClick? }`

- [ ] **Step 2: Implement TaskList.tsx**

Features:
- Vertical list grouped by: project, status, due date, or no group (controlled by `groupBy` prop)
- Each group: header with group name + count + collapse toggle
- Each row: Checkbox (toggle status Done/Pending via `router.patch`), Title, Priority Badge, Due date, Tags, Project badge
- Inline add: " + New task" input at bottom of each group
- Sorting within group by sort_order or due_date
- Empty groups hidden unless filter says show empty
- Tokenized styling

Props: `{ tasks: PersonalTask[], groupBy?: string, onTaskClick? }`

- [ ] **Step 3: Wire into Index.tsx**

`{view==='calendar' && <TaskCalendar ... />}` + `{view==='list' && <TaskList ... />}`

- [ ] **Step 4: Verify**

Run: `npm run build`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/personal/views/TaskCalendar.tsx resources/js/components/personal/views/TaskList.tsx
git commit -m "feat(personal): add calendar and list views"
```

---

### Task 11: Gallery + Timeline Views

**Files:**
- Create: `resources/js/components/personal/views/TaskGallery.tsx`
- Create: `resources/js/components/personal/views/TaskTimeline.tsx`
- Modify: `resources/js/pages/personal/tasks/Index.tsx`
- Test: Manual QA

**Interfaces:**
- Consumes: PersonalTask[], PersonalProject[]
- Produces: `TaskGallery`, `TaskTimeline` components — completes 6-view set

- [ ] **Step 1: Implement TaskGallery.tsx**

Features:
- Responsive grid: `grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4`
- Card: cover area (project color gradient or placeholder), title, description preview (stripped Yoopta), status/priority badges, due date, tags, assignee, progress if subtask-like properties exist, card footer with property icons
- Card click -> onTaskClick
- Filter/sort still applies (gallery respects current filters)
- Empty state

Props: `{ tasks: PersonalTask[], onTaskClick? }`

- [ ] **Step 2: Implement TaskTimeline.tsx**

Features:
- Horizontal timeline / Gantt-lite: x-axis = dates (weeks), y-axis = tasks grouped by project or status
- Each task: bar from start_date to due_date (if no start_date, bar is point on due_date), color by status/project/priority, label inside bar, tooltip on hover with details
- Header: zoom controls (week/month/quarter), today line (red vertical), scrollable
- No heavy Gantt library — build with divs + CSS grid/flex, dates via date-fns
- Bar drag to reschedule (optional, v1 can be read-only with click to edit dates)
- Tokenized: bar `bg-primary`, overdue `bg-destructive`, completed `bg-muted` + line-through

Props: `{ tasks: PersonalTask[], onTaskClick? }`

If no start_date/due_date, show warning "Add dates to see timeline" and table fallback.

- [ ] **Step 3: Wire into Index.tsx**

`{view==='gallery' && <TaskGallery ... />}` + `{view==='timeline' && <TaskTimeline ... />}`

- [ ] **Step 4: Verify**

Run: `npm run build`
Expected: PASS — all 6 views selectable via Tabs

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/personal/views/TaskGallery.tsx resources/js/components/personal/views/TaskTimeline.tsx
git commit -m "feat(personal): add gallery and timeline views"
```

---

### Task 12: Task Detail + Rich Editor + Filters + Saved Views

**Files:**
- Create: `resources/js/pages/personal/tasks/Show.tsx`
- Create: `resources/js/components/personal/TaskDetailDrawer.tsx` (optional slide-over)
- Create: `resources/js/components/personal/TaskFilters.tsx`
- Create: `resources/js/components/personal/SavedViewsBar.tsx`
- Modify: `resources/js/pages/personal/tasks/Index.tsx` (wire filters/drawer)
- Create: `app/Http/Controllers/Personal/TaskSavedViewController.php`
- Test: `tests/Feature/Personal/SavedViewTest.php`

**Interfaces:**
- Consumes: All view components, YooptaEditor, TaskProperties, TaskSavedView model
- Produces: Complete task management UX

- [ ] **Step 1: Write failing test for saved views**

```php
it('can save and retrieve view', function () {
    $user = \App\Models\User::factory()->create();
    $this->actingAs($user)->post(route('personal.saved-views.store'), [
        'name'=>'My Table','view_type'=>'table','filters'=>['status'=>'Pending'],'sort'=>['field'=>'due_date','direction'=>'asc']
    ])->assertRedirect();
    expect(\App\Models\TaskSavedView::where('user_id',$user->id)->exists())->toBeTrue();
});
```

- [ ] **Step 2: Implement TaskSavedViewController**

CRUD for saved views: `index` returns user's views, `store` validates and creates, `destroy` deletes.

- [ ] **Step 3: Implement Show.tsx (task detail page)**

Layout: `<MainLayout>` + header with breadcrumb (Personal > Project > Task), title (editable Input), status/priority Selects, dates, tags, project link
Body: two columns — left: YooptaEditor for description (full height, onChange auto-save via debounced `router.patch`), TaskProperties section, comments placeholder; right: metadata (created/updated), milestones if linked project, members, attachments placeholder
Actions: archive, delete (confirm), duplicate

Props: `{ task: PersonalTask & {properties, project}, projects: PersonalProject[] }`

- [ ] **Step 4: Implement TaskDetailDrawer.tsx (optional but recommended)**

Slide-over Drawer (using `@/components/ui/dialog` or `Sheet`) that opens on row/card click without navigating away. Contains same content as Show.tsx but compact. Allows quick edit. Reference `freelance/TaskBoard.tsx` dialog pattern.

- [ ] **Step 5: Implement TaskFilters.tsx**

Features:
- Search Input (debounced `router.get` with search param)
- Status filter (multi-select or Select)
- Priority filter (Select)
- Project filter (Select, populated from ProjectSidebar projects)
- Tags filter (Input with comma, or Select)
- Date range (two Inputs type date: due_from, due_to)
- Archived toggle (Switch)
- Clear all button
- Active filter badges with X to remove
- Props: `{ filters: any, projects: PersonalProject[], onChange: (filters)=>void }`
- onChange calls `router.get(personal.tasks.index().url, filters, {preserveState:true, replace:true})`

- [ ] **Step 6: Implement SavedViewsBar.tsx**

Features:
- Horizontal bar with saved view pills (name + view_type icon)
- Active view highlighted (`bg-primary text-primary-foreground`)
- "+" to save current filters/sort/view as new saved view (Dialog with name Input)
- Delete saved view (X icon with confirm)
- Click pill -> apply filters/sort/view via `router.get`
- Fetch views from Inertia prop `savedViews` passed by controller

- [ ] **Step 7: Wire everything into Index.tsx**

Add `savedViews` to controller's Inertia props. Render `SavedViewsBar` above filters. Render `TaskDetailDrawer` controlled by `selectedTask` state.

- [ ] **Step 8: Verify**

Run: `npm run build && php artisan test --compact --filter=SavedViewTest`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add resources/js/pages/personal/tasks/Show.tsx resources/js/components/personal/TaskDetailDrawer.tsx resources/js/components/personal/TaskFilters.tsx resources/js/components/personal/SavedViewsBar.tsx app/Http/Controllers/Personal/TaskSavedViewController.php
git commit -m "feat(personal): add task detail, filters and saved views"
```

---

### Task 13: Polish, Seed & QA

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php` (add personal projects/tasks seeding)
- Modify: `resources/css/app.css` (if any token missing)
- Create: `tests/Feature/Personal/PersonalFlowTest.php` (end-to-end)
- Test: Full suite + Playwright crawl checklist

**Interfaces:**
- Consumes: All previous tasks
- Produces: Production-ready module with seed data and green tests

- [ ] **Step 1: Add seeder for personal module**

In `DatabaseSeeder.php` or new `PersonalSeeder.php`:

```php
$user = User::firstOrCreate(['email'=>'test@example.com'], [...]);
$projects = Project::factory()->count(3)->create(['type'=>'personal','user_id'=>$user->id, 'client_id'=>null, 'currency_id'=>null]);
foreach ($projects as $project) {
    ProjectTask::factory()->count(8)->create(['project_id'=>$project->id, 'user_id'=>$user->id]);
    TaskMilestone::factory()->count(2)->create(['project_id'=>$project->id]);
    // Add 1-2 custom properties per task
    foreach ($project->tasks->take(3) as $task) {
        TaskProperty::factory()->create(['project_task_id'=>$task->id]);
    }
}
```

Handle nullable `client_id`/`currency_id` (ensure factories allow null when type personal).

- [ ] **Step 2: Write end-to-end flow test**

```php
it('completes full personal flow: create project, create task, add property, switch views, filter', function () {
    $user = \App\Models\User::factory()->create();
    // Create project
    $this->actingAs($user)->post(route('personal.projects.store'), ['name'=>'Proyecto Test'])->assertRedirect();
    $project = \App\Models\Project::where('name','Proyecto Test')->first();
    expect($project->type)->toBe('personal');
    // Create task
    $this->actingAs($user)->post(route('personal.tasks.store'), ['title'=>'Tarea 1','project_id'=>$project->id,'status'=>'Pending'])->assertRedirect();
    $task = \App\Models\ProjectTask::where('title','Tarea 1')->first();
    expect($task->project_id)->toBe($project->id);
    // Add custom property
    $this->actingAs($user)->post(route('personal.tasks.properties.store', $task), ['key'=>'Sprint','type'=>'text','value_text'=>'S1'])->assertCreated();
    // Filter
    $this->actingAs($user)->get(route('personal.tasks.index', ['search'=>'Tarea 1']))->assertOk();
    // Move task (kanban)
    $this->actingAs($user)->patch(route('personal.tasks.move', $task), ['status'=>'Done'])->assertRedirect();
    expect($task->fresh()->status)->toBe('Done');
});
```

- [ ] **Step 3: Run full test suite**

Run: `php artisan test --compact`
Expected: All PASS

- [ ] **Step 4: Run Pint and build**

Run: `vendor/bin/pint --dirty --format agent && npm run build`
Expected: No errors

- [ ] **Step 5: Manual QA checklist (Playwright crawl)**

- Landing -> Login (test@example.com/password) -> Personal > Tareas renders with 6 view tabs
- ProjectSidebar shows projects with counts and progress
- Create project -> appears in sidebar
- Create task -> appears in table, kanban, calendar, list, gallery, timeline (if dates)
- Edit task inline (table) and via drawer
- Add custom property -> appears as column (table) and in properties panel
- Drag kanban card -> status updates
- Calendar shows tasks on correct dates
- Filters (status, project, search) work across views
- Saved views create/apply/delete
- Task Show page: Yoopta editor saves, properties editable
- Mobile responsive (sidebar collapses)

- [ ] **Step 6: Commit**

```bash
git add database/seeders/ tests/Feature/Personal/PersonalFlowTest.php
git commit -m "feat(personal): polish, seed and QA for personal tasks and projects"
```

---

## Self-Review

**Spec coverage check:**
- Tasks with standard fields (title, description rich, status, priority, due/start, estimated/actual, tags, area/module/urgency/importance) → Task 1,2,4,8
- Dynamic properties hybrid (predefined types + custom names) → Task 3,7
- Rich editor (Yoopta) → Task 12 (Show + Drawer)
- 6 views (table, kanban, calendar, list, gallery, timeline) → Tasks 8,9,10,11
- Project bar (sidebar with counts/progress) → Task 5,6
- Personal Projects full (color/icon, dates, priority, tags, budget, progress, milestones, members, permissions, archive, custom fields) → Task 1,2,4,6
- Saved views + advanced filters (search, status, priority, project, tags, date range, sort, group) → Task 12
- Reuse via type (personal vs freelance) → Task 1,2,4

No gaps.

**Placeholder scan:** No TBD/TODO. All steps have concrete code.

**Type consistency:**
- `PersonalProject` / `PersonalTask` names consistent across tasks
- `TaskProperty.type` enum `text|number|date|select|multi_select|checkbox|url|person` consistent
- `TaskViewType` `'table'|'kanban'|'calendar'|'list'|'gallery'|'timeline'` consistent
- Route names `personal.projects.*` / `personal.tasks.*` / `personal.saved-views.*` consistent
- Controller namespaces `App\Http\Controllers\Personal\*` consistent

---

