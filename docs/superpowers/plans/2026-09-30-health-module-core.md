# Health Module Core (Fase 1) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el núcleo del módulo Salud (condiciones, medicación + tomas, mediciones con sync de peso, síntomas y profesionales) con web Inertia, API v1, tools MCP, tools del chat y chats de salud categorizados, todo escribiendo por un `HealthService` compartido.

**Architecture:** `app/Services/Health/HealthService.php` es la única vía de escritura (ownership + reglas + sync unidireccional de `users.weight`). Web, API, MCP y chat son adaptadores finos. Los chats de salud son hilos de `agent_conversations` con `category='salud'`, vínculo morph opcional (`health_condition`/`person`) y `tools_policy` fijo al grupo `health`.

**Tech Stack:** PHP 8.4, Laravel 12, Eloquent, Pest 4, laravel/mcp ^0.9.4, laravel/ai ^0.11, Sanctum, Inertia v2 + React 19 + Tailwind v4 + Wayfinder.

**Spec:** `docs/superpowers/specs/2026-09-30-health-design.md`

## Nota de coordinación

Hay otra sesión de trabajo activa en este repo. Antes de tocar archivos compartidos (`app/Ai/Tools/ToolCatalog.php`, `config/ai_tools.php`, `resources/js/lib/chat-tools.ts`, `resources/js/components/ai/chat/*`, `resources/js/pages/ai/chat.tsx`, `resources/js/types/chat.ts`, `routes/web.php`, `routes/api.php`, `database/seeders/DatabaseSeeder.php`, `app/Mcp/Servers/MegalomaniacServer.php`, `docs/modules/tools.md`, smoke tests) verificá `git log -3` y reaplicá las ediciones sobre el estado actual. Al commitear, stageá solo archivos de este plan.

## Global Constraints

- PHP 8.4 / Laravel 12 / Pest 4. `RefreshDatabase` ya aplica a `tests/Feature`; declarar `uses(RefreshDatabase::class)` por convención.
- Modelos nuevos: `casts()` como método, `$fillable` explícito, relaciones tipadas. Sin `DB::` fuera de migraciones.
- **Escrituras de Salud pasan por `App\Services\Health\HealthService`**; los adaptadores validan; el servicio valida ownership y lanza `ModelNotFoundException('Health<Model> not found.')`.
- **El agente maneja todo el módulo**: MCP `health-write` sin gate (paridad) y `HealthActionTool` del chat implementa `Approvable` con etiqueta por acción. Las tools nunca diagnostican (instrucción en la descripción).
- API v1: Form Requests en `app/Http/Requests/Api/`, Resources planos en `app/Http/Resources/`, `paginate(15)`, ownership `abort_if(..., 403)`, 201 en store, y **nombres de ruta con prefijo `api.`** (`Route::name('api.')->group(...)`) para no colisionar con web (lección de People).
- Web: `routes/health.php` requerido en `routes/web.php`; autorización con policies por modelo (auto-discovery) + `$this->authorize(...)`.
- Sync de peso: al crear/editar/borrar una medición `weight` **sin `person_id`** (titular), `users.weight` se recalcula al último peso restante, dentro del servicio y en transacción. Mediciones de familiares no tocan el perfil.
- UI: solo tokens Ember (`bg-card`, `border-border`, `text-muted-foreground`, `bg-primary`, `text-destructive`); sin dependencias npm nuevas; páginas PascalCase; reusar `components/ui/*` y `EmptyState`.
- Wayfinder: `npm run build` regenera `resources/js/routes/health` (gitignored); importar de `@/routes/health`.
- Cada tarea cierra con tests verdes del archivo tocado (`php artisan test --compact <path>`) + `vendor/bin/pint --dirty --format agent` + commit propio (staging explícito).

---

## File Structure

**Nuevos — backend**
- `database/migrations/2026_09_30_120000_create_health_professionals_table.php`
- `database/migrations/2026_09_30_120100_create_health_conditions_table.php`
- `database/migrations/2026_09_30_120200_create_health_medications_table.php`
- `database/migrations/2026_09_30_120300_create_health_medication_intakes_table.php`
- `database/migrations/2026_09_30_120400_create_health_measurements_table.php`
- `database/migrations/2026_09_30_120500_create_health_symptoms_table.php`
- `database/migrations/2026_09_30_120600_add_category_and_context_to_agent_conversations_table.php`
- `app/Health/Enums/{ConditionKind,ConditionStatus,Severity,MeasurementType,IntakeStatus,ProfessionalType}.php`
- `app/Models/{HealthCondition,HealthProfessional,HealthMedication,HealthMedicationIntake,HealthMeasurement,HealthSymptom}.php`
- `app/Services/Health/HealthService.php`
- `app/Policies/Health{Condition,Professional,Medication,MedicationIntake,Measurement,Symptom}Policy.php`
- `app/Http/Controllers/Health/{DashboardController,ConditionController,MedicationController,MedicationIntakeController,MeasurementController,SymptomController,ProfessionalController,HealthChatController}.php`
- `routes/health.php`
- `app/Http/Controllers/Api/V1/Health{Condition,Medication,MedicationIntake,Measurement,Symptom,Professional,Dashboard}Controller.php`
- `app/Http/Requests/Api/{Store,Update}Health{Condition,Medication,Measurement,Symptom,Professional}Request.php`
- `app/Http/Requests/Api/StoreHealthMedicationIntakeRequest.php`
- `app/Http/Resources/Health{Condition,Medication,MedicationIntake,Measurement,Symptom,Professional}Resource.php`
- `app/Mcp/Tools/{HealthReadTool,HealthWriteTool,HealthLogTool}.php`
- `app/Ai/Tools/{HealthQueryTool,HealthActionTool}.php`
- `database/factories/Health*Factory.php` (6)
- `database/seeders/HealthSeeder.php`
- `resources/js/layouts/health-layout.tsx`
- `resources/js/pages/health/{Dashboard,chats/Index,conditions/Index,conditions/Form,medications/Index,medications/Form,measurements/Index,symptoms/Index,professionals/Index,professionals/Form}.tsx`

**Nuevos — tests**
- `tests/Feature/Health/HealthDataTest.php`
- `tests/Feature/Health/HealthServiceTest.php`
- `tests/Feature/Health/HealthWebTest.php`
- `tests/Feature/Health/HealthMedicationsWebTest.php`
- `tests/Feature/Health/HealthMeasurementsWebTest.php`
- `tests/Feature/Health/HealthProfessionalsWebTest.php`
- `tests/Feature/Health/HealthChatTest.php`
- `tests/Feature/Api/HealthApiTest.php`
- `tests/Feature/Mcp/HealthToolsTest.php`
- `tests/Feature/Ai/HealthQueryToolTest.php`
- `tests/Feature/Ai/HealthActionToolTest.php`

**Modificados**
- `routes/web.php` — `require __DIR__.'/health.php';`
- `routes/api.php` — bloque `// Health` dentro de `auth:sanctum`
- `app/Models/ChatThread.php` — constantes de categoría, `context()` morph, scope `category()`
- `app/Http/Resources/ChatThreadResource.php` — `category`, `context_type`, `context_id`, `context_label`
- `resources/js/types/chat.ts` — campos de categoría/contexto en `ChatThread`
- `resources/js/components/ai/chat/ThreadItem.tsx` (y/o `ThreadRail.tsx`) — badge "Salud"
- `resources/js/components/app-sidebar.tsx` — grupo "Salud"
- `app/Mcp/Servers/MegalomaniacServer.php` — 3 tools
- `app/Ai/Tools/ToolCatalog.php` — grupo `health` + `actionTools()` + `make()`
- `config/ai_tools.php` — keywords `health` + fallback
- `resources/js/lib/chat-tools.ts` — labels
- `docs/modules/tools.md` — fila Health
- `database/seeders/DatabaseSeeder.php` — `HealthSeeder`
- `tests/Feature/Mcp/McpToolsSmokeTest.php` — conteo de tools
- `tests/Feature/Ai/{ToolCatalogTest,ToolRouterTest,MegalomaniacAgentTest}.php` — expectativas

---

### Task 1: Capa de datos Salud (migraciones, enums, modelos, factories, seeder)

**Files:**
- Create: las 6 migraciones `2026_09_30_1200xx_create_health_*`
- Create: `app/Health/Enums/{ConditionKind,ConditionStatus,Severity,MeasurementType,IntakeStatus,ProfessionalType}.php`
- Create: `app/Models/Health{Condition,Professional,Medication,MedicationIntake,Measurement,Symptom}.php`
- Create: `database/factories/Health*Factory.php` (6)
- Create: `database/seeders/HealthSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Health/HealthDataTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: tablas `health_conditions`, `health_professionals`, `health_medications`, `health_medication_intakes`, `health_measurements`, `health_symptoms`; enums en `App\Health\Enums\*` con `values(): array`; modelos con `casts()`, `$fillable`, relaciones `user()`, `person()`, `provider()`, `condition()`, `prescriber()`, `medication()`, `intakes()`; factories y `HealthSeeder`.

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/Health/HealthDataTest.php`:

```php
<?php

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\MeasurementType;
use App\Health\Enums\Severity;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthProfessional;
use App\Models\HealthSymptom;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates health records with enum casts and defaults', function () {
    $user = User::factory()->create();
    $condition = HealthCondition::factory()->create([
        'user_id' => $user->id,
        'kind' => ConditionKind::Diagnosis,
        'status' => ConditionStatus::Active,
        'severity' => Severity::Moderate,
    ]);

    expect($condition->fresh()->kind)->toBe(ConditionKind::Diagnosis)
        ->and($condition->fresh()->status)->toBe(ConditionStatus::Active)
        ->and($condition->fresh()->severity)->toBe(Severity::Moderate)
        ->and($condition->user->is($user))->toBeTrue();
});

it('links records to a family person optionally', function () {
    $person = Person::factory()->create();
    $measurement = HealthMeasurement::factory()->create([
        'user_id' => $person->user_id,
        'person_id' => $person->id,
        'type' => MeasurementType::BloodPressure,
        'value' => 120,
        'secondary_value' => 80,
        'unit' => 'mmHg',
    ]);

    expect($measurement->person->is($person))->toBeTrue();
});

it('relates medications, intakes, providers and symptoms', function () {
    $user = User::factory()->create();
    $provider = HealthProfessional::factory()->create(['user_id' => $user->id]);
    $medication = HealthMedication::factory()->create([
        'user_id' => $user->id,
        'prescriber_id' => $provider->id,
        'is_active' => true,
    ]);
    $intake = HealthMedicationIntake::factory()->create([
        'user_id' => $user->id,
        'medication_id' => $medication->id,
    ]);
    $symptom = HealthSymptom::factory()->create(['user_id' => $user->id]);

    expect($medication->prescriber->is($provider))->toBeTrue()
        ->and($medication->intakes()->count())->toBe(1)
        ->and($intake->medication->is($medication))->toBeTrue()
        ->and($symptom->severity)->toBeInstanceOf(Severity::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Health/HealthDataTest.php`
Expected: FAIL — `Class "App\Models\HealthCondition" not found`.

- [ ] **Step 3: Write the enums**

`app/Health/Enums/ConditionKind.php`:

```php
<?php

declare(strict_types=1);

namespace App\Health\Enums;

enum ConditionKind: string
{
    case Condition = 'condition';
    case Diagnosis = 'diagnosis';
    case Allergy = 'allergy';
    case Surgery = 'surgery';
    case FamilyHistory = 'family_history';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
```

Con el mismo patrón:
- `ConditionStatus`: `Suspected='suspected'`, `Active='active'`, `Resolved='resolved'`, `InRemission='in_remission'`.
- `Severity`: `Mild='mild'`, `Moderate='moderate'`, `Severe='severe'`.
- `MeasurementType`: `Weight='weight'`, `BloodPressure='blood_pressure'`, `HeartRate='heart_rate'`, `Glucose='glucose'`, `Temperature='temperature'`, `OxygenSaturation='oxygen_saturation'`, `Waist='waist'`.
- `IntakeStatus`: `Taken='taken'`, `Skipped='skipped'`.
- `ProfessionalType`: `Professional='professional'`, `Center='center'`.

- [ ] **Step 4: Write the migrations**

`database/migrations/2026_09_30_120100_create_health_conditions_table.php`:

```php
<?php

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\Severity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->enum('kind', ConditionKind::values())->default('condition');
            $table->string('name');
            $table->enum('status', ConditionStatus::values())->default('active');
            $table->enum('severity', Severity::values())->nullable();
            $table->date('diagnosed_at')->nullable();
            $table->foreignId('provider_id')->nullable()->constrained('health_professionals')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_conditions');
    }
};
```

`database/migrations/2026_09_30_120000_create_health_professionals_table.php`:

```php
<?php

use App\Health\Enums\ProfessionalType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_professionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ProfessionalType::values())->default('professional');
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_professionals');
    }
};
```

**Ojo:** `health_conditions.provider_id` referencia `health_professionals`, así que esta migración debe correr ANTES que conditions. Renombrá los archivos para respetar el orden: `120000_create_health_professionals`, `120100_create_health_conditions`, `120200_create_health_medications`, `120300_create_health_medication_intakes`, `120400_create_health_measurements`, `120500_create_health_symptoms`.

`database/migrations/2026_09_30_120200_create_health_medications_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_medications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('name');
            $table->decimal('dose_amount', 8, 2)->nullable();
            $table->string('dose_unit', 30)->nullable();
            $table->string('route', 30)->nullable();
            $table->string('frequency_text')->nullable();
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('condition_id')->nullable()->constrained('health_conditions')->nullOnDelete();
            $table->foreignId('prescriber_id')->nullable()->constrained('health_professionals')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_medications');
    }
};
```

`database/migrations/2026_09_30_120300_create_health_medication_intakes_table.php`:

```php
<?php

use App\Health\Enums\IntakeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_medication_intakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medication_id')->constrained('health_medications')->cascadeOnDelete();
            $table->timestamp('taken_at');
            $table->enum('status', IntakeStatus::values())->default('taken');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['medication_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_medication_intakes');
    }
};
```

`database/migrations/2026_09_30_120400_create_health_measurements_table.php`:

```php
<?php

use App\Health\Enums\MeasurementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->enum('type', MeasurementType::values());
            $table->decimal('value', 8, 2);
            $table->decimal('secondary_value', 8, 2)->nullable();
            $table->string('unit', 20);
            $table->timestamp('measured_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'measured_at']);
            $table->index(['user_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_measurements');
    }
};
```

`database/migrations/2026_09_30_120500_create_health_symptoms_table.php`:

```php
<?php

use App\Health\Enums\Severity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_symptoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('symptom');
            $table->enum('severity', Severity::values())->default('mild');
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_symptoms');
    }
};
```

- [ ] **Step 5: Write the models**

`app/Models/HealthProfessional.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\ProfessionalType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthProfessional extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'type', 'name', 'specialty', 'phone', 'email',
        'address', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProfessionalType::class,
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(HealthCondition::class, 'provider_id');
    }

    public function medications(): HasMany
    {
        return $this->hasMany(HealthMedication::class, 'prescriber_id');
    }
}
```

`app/Models/HealthCondition.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\Severity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'person_id', 'kind', 'name', 'status', 'severity',
        'diagnosed_at', 'provider_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ConditionKind::class,
            'status' => ConditionStatus::class,
            'severity' => Severity::class,
            'diagnosed_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(HealthProfessional::class, 'provider_id');
    }
}
```

`app/Models/HealthMedication.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthMedication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'person_id', 'name', 'dose_amount', 'dose_unit', 'route',
        'frequency_text', 'started_at', 'ended_at', 'is_active',
        'condition_id', 'prescriber_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'dose_amount' => 'decimal:2',
            'started_at' => 'date',
            'ended_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(HealthCondition::class, 'condition_id');
    }

    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(HealthProfessional::class, 'prescriber_id');
    }

    public function intakes(): HasMany
    {
        return $this->hasMany(HealthMedicationIntake::class, 'medication_id');
    }
}
```

`app/Models/HealthMedicationIntake.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\IntakeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthMedicationIntake extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'medication_id', 'taken_at', 'status', 'notes'];

    protected function casts(): array
    {
        return [
            'status' => IntakeStatus::class,
            'taken_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(HealthMedication::class, 'medication_id');
    }
}
```

`app/Models/HealthMeasurement.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\MeasurementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthMeasurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'person_id', 'type', 'value', 'secondary_value',
        'unit', 'measured_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => MeasurementType::class,
            'value' => 'decimal:2',
            'secondary_value' => 'decimal:2',
            'measured_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
```

`app/Models/HealthSymptom.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Health\Enums\Severity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthSymptom extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'person_id', 'symptom', 'severity', 'occurred_at', 'notes'];

    protected function casts(): array
    {
        return [
            'severity' => Severity::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
```

- [ ] **Step 6: Write the factories**

Cada factory sigue `ClientFactory`. Ejemplo `database/factories/HealthConditionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Models\HealthCondition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthCondition>
 */
class HealthConditionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => null,
            'kind' => ConditionKind::Condition,
            'name' => $this->faker->randomElement(['Hipertensión', 'Asma', 'Migraña', 'Anemia']),
            'status' => ConditionStatus::Active,
            'severity' => null,
            'diagnosed_at' => $this->faker->optional()->dateTimeBetween('-10 years', 'now'),
            'provider_id' => null,
            'notes' => null,
        ];
    }
}
```

Con el mismo patrón:
- `HealthProfessionalFactory`: `type=Professional`, `name=$faker->name()`, `specialty` en `['Neurología','Endocrinología','Clínica médica','Kinesiología']`, phone/email null, `is_active=true`.
- `HealthMedicationFactory`: `name` en `['Levotiroxina','Ibuprofeno','Vitamina D']`, `dose_amount=50`, `dose_unit='mcg'`, `route='oral'`, `is_active=true`.
- `HealthMedicationIntakeFactory`: `medication_id => HealthMedication::factory()`, `taken_at => now()->subDay()`, `status=IntakeStatus::Taken`. **Importante:** `user_id` se toma de la medicación; los tests siempre pasan `user_id` y `medication_id` explícitos (el factory default usa `User::factory()`).
- `HealthMeasurementFactory`: `type=Weight`, `value=70.5`, `unit='kg'`, `measured_at=now()`.
- `HealthSymptomFactory`: `symptom='calambre'`, `severity=Severity::Mild`, `occurred_at=now()`.

- [ ] **Step 7: Write the seeder and register it**

`database/seeders/HealthSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\IntakeStatus;
use App\Health\Enums\MeasurementType;
use App\Health\Enums\ProfessionalType;
use App\Health\Enums\Severity;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthProfessional;
use App\Models\HealthSymptom;
use App\Models\User;
use Illuminate\Database\Seeder;

class HealthSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first()
            ?? User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $neuro = HealthProfessional::create([
            'user_id' => $user->id,
            'type' => ProfessionalType::Professional,
            'name' => 'Dra. López',
            'specialty' => 'Neurología',
            'is_active' => true,
        ]);

        HealthCondition::create([
            'user_id' => $user->id,
            'kind' => ConditionKind::Diagnosis,
            'name' => 'Miopatía en estudio',
            'status' => ConditionStatus::Suspected,
            'severity' => Severity::Moderate,
            'provider_id' => $neuro->id,
            'notes' => 'Pendiente electromiograma y control.',
        ]);

        $hypo = HealthCondition::create([
            'user_id' => $user->id,
            'kind' => ConditionKind::Diagnosis,
            'name' => 'Hipotiroidismo',
            'status' => ConditionStatus::Active,
            'provider_id' => $neuro->id,
        ]);

        $t4 = HealthMedication::create([
            'user_id' => $user->id,
            'name' => 'Levotiroxina',
            'dose_amount' => 50,
            'dose_unit' => 'mcg',
            'route' => 'oral',
            'frequency_text' => '1 por día',
            'condition_id' => $hypo->id,
            'prescriber_id' => $neuro->id,
            'is_active' => true,
        ]);

        foreach ([1, 2, 3, 5] as $daysAgo) {
            HealthMedicationIntake::create([
                'user_id' => $user->id,
                'medication_id' => $t4->id,
                'taken_at' => now()->subDays($daysAgo)->setTime(8, 0),
                'status' => IntakeStatus::Taken,
            ]);
        }

        foreach ([0, 3, 7, 14] as $daysAgo) {
            HealthMeasurement::create([
                'user_id' => $user->id,
                'type' => MeasurementType::Weight,
                'value' => 62.5 - ($daysAgo * 0.1),
                'unit' => 'kg',
                'measured_at' => now()->subDays($daysAgo),
            ]);
        }

        HealthMeasurement::create([
            'user_id' => $user->id,
            'type' => MeasurementType::BloodPressure,
            'value' => 118,
            'secondary_value' => 76,
            'unit' => 'mmHg',
            'measured_at' => now()->subDays(5),
        ]);

        HealthSymptom::create([
            'user_id' => $user->id,
            'symptom' => 'calambre en mano izquierda',
            'severity' => Severity::Mild,
            'occurred_at' => now()->subDays(2),
        ]);
    }
}
```

Modificar `database/seeders/DatabaseSeeder.php` agregando `$this->call(HealthSeeder::class);` después de `PeopleSeeder`.

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Health/HealthDataTest.php`
Expected: PASS (3 tests).

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Health app/Models/Health*.php database/migrations/2026_09_30_1200*.php database/factories/Health*Factory.php database/seeders/HealthSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Health/HealthDataTest.php
git commit -m "feat(health): data layer for conditions, medications, measurements and symptoms"
```

---

### Task 2: `HealthService` (escritura compartida + sync de peso)

**Files:**
- Create: `app/Services/Health/HealthService.php`
- Test: `tests/Feature/Health/HealthServiceTest.php`

**Interfaces:**
- Consumes: modelos y enums de Task 1.
- Produces: `HealthService` con `findCondition/findMedication/createCondition/updateCondition/deleteCondition/createMedication/updateMedication/deleteMedication/logIntake/deleteIntake/logMeasurement/updateMeasurement/deleteMeasurement/logSymptom/updateSymptom/deleteSymptom/createProfessional/updateProfessional/deleteProfessional/summary`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Health/HealthServiceTest.php`:

```php
<?php

use App\Health\Enums\IntakeStatus;
use App\Health\Enums\MeasurementType;
use App\Health\Enums\Severity;
use App\Models\HealthCondition;
use App\Models\HealthMedication;
use App\Models\HealthMeasurement;
use App\Models\User;
use App\Services\Health\HealthService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(HealthService::class);
    $this->user = User::factory()->create();
});

it('creates and updates conditions scoped to the user', function () {
    $condition = $this->service->createCondition($this->user, [
        'kind' => 'diagnosis',
        'name' => 'Hipotiroidismo',
        'status' => 'active',
    ]);

    expect($condition->user_id)->toBe($this->user->id);

    $updated = $this->service->updateCondition($this->user, $condition, ['status' => 'resolved']);

    expect($updated->status->value)->toBe('resolved');
});

it('refuses to touch another user records', function () {
    $intruder = User::factory()->create();
    $condition = HealthCondition::factory()->create();

    $this->expectException(ModelNotFoundException::class);
    $this->expectExceptionMessage('HealthCondition not found.');

    $this->service->findCondition($intruder, $condition->id);
});

it('logs intakes through the medication and deletes them', function () {
    $medication = HealthMedication::factory()->create(['user_id' => $this->user->id]);

    $intake = $this->service->logIntake($this->user, $medication, [
        'taken_at' => now()->subHour()->toDateTimeString(),
        'status' => IntakeStatus::Taken->value,
    ]);

    expect($medication->intakes()->count())->toBe(1);

    $this->service->deleteIntake($this->user, $intake);

    expect($medication->intakes()->count())->toBe(0);
});

it('syncs the profile weight when logging, updating and deleting a weight measurement', function () {
    $first = $this->service->logMeasurement($this->user, [
        'type' => MeasurementType::Weight->value,
        'value' => 61.5,
        'unit' => 'kg',
        'measured_at' => now()->subDays(10)->toDateTimeString(),
    ]);
    $latest = $this->service->logMeasurement($this->user, [
        'type' => MeasurementType::Weight->value,
        'value' => 62.5,
        'unit' => 'kg',
        'measured_at' => now()->subDay()->toDateTimeString(),
    ]);

    expect((float) $this->user->fresh()->weight)->toBe(62.5);

    $this->service->deleteMeasurement($this->user, $latest);

    expect((float) $this->user->fresh()->weight)->toBe(61.5);

    $this->service->deleteMeasurement($this->user, $first);

    expect($this->user->fresh()->weight)->toBeNull();
});

it('does not sync weight for family members', function () {
    $person = \App\Models\Person::factory()->create(['user_id' => $this->user->id]);
    $this->user->forceFill(['weight' => 62.0])->save();

    $this->service->logMeasurement($this->user, [
        'person_id' => $person->id,
        'type' => MeasurementType::Weight->value,
        'value' => 70.0,
        'unit' => 'kg',
        'measured_at' => now()->toDateTimeString(),
    ]);

    expect((float) $this->user->fresh()->weight)->toBe(62.0);
});

it('logs symptoms and builds a summary', function () {
    HealthMeasurement::factory()->create(['user_id' => $this->user->id]);
    $this->service->logSymptom($this->user, [
        'symptom' => 'calambre',
        'severity' => Severity::Moderate->value,
        'occurred_at' => now()->toDateTimeString(),
    ]);

    $summary = $this->service->summary($this->user);

    expect($summary)->toHaveKeys(['active_conditions', 'active_medications', 'last_measurements', 'recent_symptoms'])
        ->and($summary['recent_symptoms'])->toHaveCount(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Health/HealthServiceTest.php`
Expected: FAIL — `Class "App\Services\Health\HealthService" not found`.

- [ ] **Step 3: Implement the service**

`app/Services/Health/HealthService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Health;

use App\Health\Enums\MeasurementType;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\HealthProfessional;
use App\Models\HealthSymptom;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class HealthService
{
    public function findCondition(User $user, int $id): HealthCondition
    {
        return $this->owned(HealthCondition::query(), $user)
            ->find($id) ?? throw new ModelNotFoundException('HealthCondition not found.');
    }

    public function findMedication(User $user, int $id): HealthMedication
    {
        return $this->owned(HealthMedication::query(), $user)
            ->find($id) ?? throw new ModelNotFoundException('HealthMedication not found.');
    }

    public function createCondition(User $user, array $data): HealthCondition
    {
        return $user->healthConditions()->create($data);
    }

    public function updateCondition(User $user, HealthCondition $condition, array $data): HealthCondition
    {
        $this->assertOwner($user, $condition);
        $condition->update($data);

        return $condition->refresh();
    }

    public function deleteCondition(User $user, HealthCondition $condition): void
    {
        $this->assertOwner($user, $condition);
        $condition->delete();
    }

    public function createMedication(User $user, array $data): HealthMedication
    {
        return $user->healthMedications()->create($data);
    }

    public function updateMedication(User $user, HealthMedication $medication, array $data): HealthMedication
    {
        $this->assertOwner($user, $medication);
        $medication->update($data);

        return $medication->refresh();
    }

    public function deleteMedication(User $user, HealthMedication $medication): void
    {
        $this->assertOwner($user, $medication);
        $medication->delete();
    }

    public function logIntake(User $user, HealthMedication $medication, array $data): HealthMedicationIntake
    {
        $this->assertOwner($user, $medication);

        return $medication->intakes()->create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    public function deleteIntake(User $user, HealthMedicationIntake $intake): void
    {
        $this->assertOwner($user, $intake);
        $intake->delete();
    }

    public function logMeasurement(User $user, array $data): HealthMeasurement
    {
        return DB::transaction(function () use ($user, $data): HealthMeasurement {
            $measurement = $user->healthMeasurements()->create($data);
            $this->syncProfileWeight($user, $measurement);

            return $measurement;
        });
    }

    public function updateMeasurement(User $user, HealthMeasurement $measurement, array $data): HealthMeasurement
    {
        $this->assertOwner($user, $measurement);

        return DB::transaction(function () use ($measurement, $data, $user): HealthMeasurement {
            $wasWeight = $measurement->type === MeasurementType::Weight;
            $measurement->update($data);

            if ($wasWeight || $measurement->type === MeasurementType::Weight) {
                $this->syncProfileWeight($user, $measurement);
            }

            return $measurement->refresh();
        });
    }

    public function deleteMeasurement(User $user, HealthMeasurement $measurement): void
    {
        $this->assertOwner($user, $measurement);

        DB::transaction(function () use ($measurement, $user): void {
            $isWeight = $measurement->type === MeasurementType::Weight;
            $measurement->delete();

            if ($isWeight) {
                $this->syncProfileWeight($user);
            }
        });
    }

    public function logSymptom(User $user, array $data): HealthSymptom
    {
        return $user->healthSymptoms()->create($data);
    }

    public function updateSymptom(User $user, HealthSymptom $symptom, array $data): HealthSymptom
    {
        $this->assertOwner($user, $symptom);
        $symptom->update($data);

        return $symptom->refresh();
    }

    public function deleteSymptom(User $user, HealthSymptom $symptom): void
    {
        $this->assertOwner($user, $symptom);
        $symptom->delete();
    }

    public function createProfessional(User $user, array $data): HealthProfessional
    {
        return $user->healthProfessionals()->create($data);
    }

    public function updateProfessional(User $user, HealthProfessional $professional, array $data): HealthProfessional
    {
        $this->assertOwner($user, $professional);
        $professional->update($data);

        return $professional->refresh();
    }

    public function deleteProfessional(User $user, HealthProfessional $professional): void
    {
        $this->assertOwner($user, $professional);
        $professional->delete();
    }

    /**
     * Resumen del expediente para panel, MCP y chat.
     *
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        return [
            'active_conditions' => HealthCondition::where('user_id', $user->id)
                ->whereIn('status', ['suspected', 'active', 'in_remission'])
                ->orderBy('name')
                ->get()
                ->toArray(),
            'active_medications' => HealthMedication::where('user_id', $user->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->toArray(),
            'last_measurements' => HealthMeasurement::where('user_id', $user->id)
                ->latest('measured_at')
                ->limit(10)
                ->get()
                ->toArray(),
            'recent_symptoms' => HealthSymptom::where('user_id', $user->id)
                ->latest('occurred_at')
                ->limit(10)
                ->get()
                ->toArray(),
        ];
    }

    private function syncProfileWeight(User $user, ?HealthMeasurement $measurement = null): void
    {
        if ($measurement !== null && $measurement->person_id !== null) {
            return;
        }

        $latest = HealthMeasurement::query()
            ->where('user_id', $user->id)
            ->whereNull('person_id')
            ->where('type', MeasurementType::Weight->value)
            ->orderByDesc('measured_at')
            ->orderByDesc('id')
            ->value('value');

        $user->forceFill(['weight' => $latest])->saveQuietly();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Model>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Model>
     */
    private function owned($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    private function assertOwner(User $user, Model $model): void
    {
        if ((int) $model->user_id !== (int) $user->id) {
            throw new ModelNotFoundException(class_basename($model).' not found.');
        }
    }
}
```

En `app/Models/User.php` agregar las relaciones:

```php
    public function healthConditions(): HasMany
    {
        return $this->hasMany(HealthCondition::class);
    }

    public function healthMedications(): HasMany
    {
        return $this->hasMany(HealthMedication::class);
    }

    public function healthMeasurements(): HasMany
    {
        return $this->hasMany(HealthMeasurement::class);
    }

    public function healthSymptoms(): HasMany
    {
        return $this->hasMany(HealthSymptom::class);
    }

    public function healthProfessionals(): HasMany
    {
        return $this->hasMany(HealthProfessional::class);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Health/HealthServiceTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Health/HealthService.php app/Models/User.php tests/Feature/Health/HealthServiceTest.php
git commit -m "feat(health): shared health service with profile weight sync"
```

---

### Task 3: Web base — rutas, policies, panel, layout y sidebar

**Files:**
- Create: `routes/health.php`
- Create: `app/Policies/Health{Condition,Professional,Medication,MedicationIntake,Measurement,Symptom}Policy.php`
- Create: `app/Http/Controllers/Health/DashboardController.php`
- Create: `resources/js/layouts/health-layout.tsx`
- Create: `resources/js/pages/health/Dashboard.tsx`
- Modify: `routes/web.php` (`require __DIR__.'/health.php';`)
- Modify: `resources/js/components/app-sidebar.tsx` (grupo "Salud")
- Test: `tests/Feature/Health/HealthWebTest.php`

**Interfaces:**
- Consumes: `HealthService::summary`, modelos de Task 1.
- Produces: rutas `health.*` (dashboard + las que usarán las tareas siguientes); policies auto-descubiertas; página `health/Dashboard` con prop `summary` (claves `active_conditions`, `active_medications`, `last_measurements`, `recent_symptoms`).

- [ ] **Step 1: Write the failing test**

`tests/Feature/Health/HealthWebTest.php`:

```php
<?php

use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\HealthSymptom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the health dashboard with the user summary only', function () {
    HealthCondition::factory()->create(['user_id' => $this->user->id, 'name' => 'Hipotiroidismo']);
    HealthCondition::factory()->create(['name' => 'Ajena']);
    HealthMedication::factory()->create(['user_id' => $this->user->id]);
    HealthMeasurement::factory()->create(['user_id' => $this->user->id]);
    HealthSymptom::factory()->create(['user_id' => $this->user->id]);

    $this->get('/health')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/Dashboard')
            ->where('summary.active_conditions', fn ($rows) => collect($rows)->pluck('name')->contains('Hipotiroidismo'))
            ->where('summary.active_conditions', fn ($rows) => ! collect($rows)->pluck('name')->contains('Ajena'))
            ->has('summary.last_measurements', 1)
            ->has('summary.recent_symptoms', 1));
});

it('requires authentication for health routes', function () {
    auth()->logout();

    $this->get('/health')->assertRedirect('/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Health/HealthWebTest.php`
Expected: FAIL — 404.

- [ ] **Step 3: Write routes, policies and the dashboard controller**

`routes/health.php` (los controllers de las tareas 4–8 se crean en sus tareas; en esta tarea solo se declaran dashboard, condiciones, medicación, mediciones, síntomas, profesionales y chats, con los controllers que existan; creá stubs vacíos para los que falten):

```php
<?php

use App\Http\Controllers\Health\ConditionController;
use App\Http\Controllers\Health\DashboardController;
use App\Http\Controllers\Health\HealthChatController;
use App\Http\Controllers\Health\MeasurementController;
use App\Http\Controllers\Health\MedicationController;
use App\Http\Controllers\Health\MedicationIntakeController;
use App\Http\Controllers\Health\ProfessionalController;
use App\Http\Controllers\Health\SymptomController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('health')->name('health.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('chats', [HealthChatController::class, 'index'])->name('chats.index');
    Route::post('chats', [HealthChatController::class, 'store'])->name('chats.store');

    Route::resource('conditions', ConditionController::class)->except(['show']);
    Route::resource('medications', MedicationController::class)->except(['show']);
    Route::post('medications/{medication}/intakes', [MedicationIntakeController::class, 'store'])
        ->name('medications.intakes.store');
    Route::delete('medications/{medication}/intakes/{intake}', [MedicationIntakeController::class, 'destroy'])
        ->name('medications.intakes.destroy');
    Route::resource('measurements', MeasurementController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('symptoms', SymptomController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('professionals', ProfessionalController::class)->except(['show']);
});
```

En `routes/web.php`, junto a los otros requires:

```php
require __DIR__.'/health.php';
```

Policies (6 archivos, auto-discovery). `app/Policies/HealthConditionPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthCondition;
use App\Models\User;

class HealthConditionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, HealthCondition $condition): bool
    {
        return $user->id === $condition->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, HealthCondition $condition): bool
    {
        return $user->id === $condition->user_id;
    }

    public function delete(User $user, HealthCondition $condition): bool
    {
        return $user->id === $condition->user_id;
    }
}
```

El resto (`HealthProfessionalPolicy`, `HealthMedicationPolicy`, `HealthMedicationIntakePolicy`, `HealthMeasurementPolicy`, `HealthSymptomPolicy`) es idéntico sustituyendo el modelo y el parámetro; `HealthMedicationIntake` también tiene `user_id` directo.

`app/Http/Controllers/Health/DashboardController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): Response
    {
        return Inertia::render('health/Dashboard', [
            'summary' => $this->health->summary($request->user()),
        ]);
    }
}
```

Stubs para las tareas 4–8 (extienden `App\Http\Controllers\Controller`, sin métodos): `ConditionController`, `MedicationController`, `MedicationIntakeController`, `MeasurementController`, `SymptomController`, `ProfessionalController`, `HealthChatController`.

- [ ] **Step 4: Write layout, dashboard page and sidebar group**

`resources/js/layouts/health-layout.tsx`:

```tsx
import type { ReactNode } from 'react';
import MainLayout from '@/layouts/main-layout';

export default function HealthLayout({ children }: { children: ReactNode }) {
    return <MainLayout>{children}</MainLayout>;
}
```

`resources/js/pages/health/Dashboard.tsx`:

```tsx
import { Head, Link } from '@inertiajs/react';
import { Activity, HeartPulse, Stethoscope, Tablets, Thermometer } from 'lucide-react';
import React from 'react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import HealthLayout from '@/layouts/health-layout';
import health from '@/routes/health';

const SEVERITY_LABELS: Record<string, string> = {
    mild: 'Leve', moderate: 'Moderada', severe: 'Severa',
};

export default function HealthDashboard({ summary }: any) {
    return (
        <HealthLayout>
            <Head title="Salud" />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Salud</h1>
                        <p className="text-muted-foreground">Tu expediente: condiciones, medicación, mediciones y síntomas.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={health.chats.index().url} className="text-sm font-bold text-primary hover:underline">Chats de salud</Link>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {[
                        { title: 'Condiciones activas', icon: Stethoscope, href: health.conditions.index().url, items: summary.active_conditions, render: (item: any) => item.name },
                        { title: 'Medicación activa', icon: Tablets, href: health.medications.index().url, items: summary.active_medications, render: (item: any) => `${item.name}${item.dose_amount ? ` ${Number(item.dose_amount)} ${item.dose_unit ?? ''}` : ''}` },
                        { title: 'Últimas mediciones', icon: Activity, href: health.measurements.index().url, items: summary.last_measurements, render: (item: any) => `${item.type.replace(/_/g, ' ')}: ${Number(item.value)}${item.secondary_value ? `/${Number(item.secondary_value)}` : ''} ${item.unit}` },
                        { title: 'Síntomas recientes', icon: Thermometer, href: health.symptoms.index().url, items: summary.recent_symptoms, render: (item: any) => `${item.symptom} · ${SEVERITY_LABELS[item.severity] ?? item.severity}` },
                    ].map((card) => (
                        <Card key={card.title} className="bg-card border-border">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">{card.title}</CardTitle>
                                <card.icon className="h-4 w-4 text-primary" />
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2">
                                {card.items.length === 0 ? (
                                    <p className="text-sm text-muted-foreground italic">Sin datos.</p>
                                ) : card.items.slice(0, 4).map((item: any, index: number) => (
                                    <p key={index} className="truncate text-sm text-white/90">{card.render(item)}</p>
                                ))}
                                <Link href={card.href} className="text-xs font-bold text-primary hover:underline">Ver todo</Link>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="rounded-xl border border-dashed border-border bg-card p-6">
                    <div className="flex items-center gap-3">
                        <HeartPulse className="h-5 w-5 text-primary" />
                        <div>
                            <p className="text-sm font-bold text-white">Chats exclusivos de salud</p>
                            <p className="text-xs text-muted-foreground">
                                Abrí una conversación categorizada y vinculada a una condición o persona.
                            </p>
                        </div>
                        <Badge variant="outline" className="ml-auto border-primary/30 text-primary text-[10px] font-black uppercase">Salud</Badge>
                    </div>
                </div>
            </div>
        </HealthLayout>
    );
}
```

En `resources/js/components/app-sidebar.tsx`: importar `Activity, BriefcaseMedical, HeartPulse, MessagesSquare, Stethoscope, Tablets, Thermometer` de lucide, definir

```tsx
const healthNavItems: NavItem[] = [
    { title: 'Panel', href: '/health', icon: HeartPulse },
    { title: 'Condiciones', href: '/health/conditions', icon: Stethoscope },
    { title: 'Medicación', href: '/health/medications', icon: Tablets },
    { title: 'Mediciones', href: '/health/measurements', icon: Activity },
    { title: 'Síntomas', href: '/health/symptoms', icon: Thermometer },
    { title: 'Profesionales', href: '/health/professionals', icon: BriefcaseMedical },
    { title: 'Chats', href: '/health/chats', icon: MessagesSquare },
];
```

y agregar el grupo "Salud" después del grupo "Personas" (mismo patrón que `personalNavItems`; en el panel de la sidebar se muestra como grupo con subitems, y activo con `window.location.pathname.startsWith('/health')`).

- [ ] **Step 5: Generate Wayfinder, build and run tests**

```bash
npm run build
php artisan test --compact tests/Feature/Health/HealthWebTest.php
```
Expected: PASS (2 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/health.php routes/web.php app/Policies/Health*Policy.php app/Http/Controllers/Health resources/js/layouts/health-layout.tsx resources/js/pages/health resources/js/components/app-sidebar.tsx tests/Feature/Health/HealthWebTest.php
git commit -m "feat(health): web base with routes, policies, dashboard and sidebar"
```

---

### Task 4: Web — condiciones

**Files:**
- Modify: `app/Http/Controllers/Health/ConditionController.php`
- Create: `resources/js/pages/health/conditions/Index.tsx`, `Form.tsx`
- Modify: `tests/Feature/Health/HealthWebTest.php`

**Interfaces:**
- Consumes: `HealthService::createCondition/updateCondition/deleteCondition`, enums `ConditionKind/ConditionStatus/Severity`, rutas `health.conditions.*`.
- Produces: flows web de condiciones con props `conditions` (paginado), `filters`, y en create/edit `kindOptions/statusOptions/severityOptions/people/providers`.

- [ ] **Step 1: Add failing tests**

```php
it('creates, updates and deletes a condition', function () {
    $this->post('/health/conditions', [
        'kind' => 'diagnosis',
        'name' => 'Hipotiroidismo',
        'status' => 'active',
        'severity' => 'moderate',
    ])->assertRedirect(route('health.conditions.index'));

    $condition = HealthCondition::where('user_id', $this->user->id)->firstOrFail();

    $this->put("/health/conditions/{$condition->id}", ['status' => 'resolved'])
        ->assertRedirect(route('health.conditions.index'));
    expect($condition->fresh()->status->value)->toBe('resolved');

    $this->delete("/health/conditions/{$condition->id}")->assertRedirect(route('health.conditions.index'));
    expect(HealthCondition::find($condition->id))->toBeNull();
});

it('lists and filters conditions of the authenticated user', function () {
    HealthCondition::factory()->create(['user_id' => $this->user->id, 'name' => 'Miopatía', 'status' => 'suspected']);
    HealthCondition::factory()->create(['name' => 'Ajena']);

    $this->get('/health/conditions?search=mio')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/conditions/Index')
            ->where('conditions.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Miopatía']));
});

it('forbids editing another user condition', function () {
    $condition = HealthCondition::factory()->create();

    $this->put("/health/conditions/{$condition->id}", ['name' => 'hack'])->assertForbidden();

    expect($condition->fresh()->name)->not->toBe('hack');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Health/HealthWebTest.php`
Expected: FAIL — el stub no tiene métodos.

- [ ] **Step 3: Implement the controller**

`app/Http/Controllers/Health/ConditionController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\Severity;
use App\Http\Controllers\Controller;
use App\Models\HealthCondition;
use App\Models\HealthProfessional;
use App\Models\Person;
use App\Services\Health\HealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConditionController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function index(Request $request): Response
    {
        $query = HealthCondition::query()
            ->where('user_id', $request->user()->id)
            ->with(['person:id,first_name,last_name', 'provider:id,name']);

        $query->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%"));
        $query->when($request->status, fn ($q, $status) => $q->where('status', $status));
        $query->when($request->kind, fn ($q, $kind) => $q->where('kind', $kind));

        return Inertia::render('health/conditions/Index', [
            'conditions' => $query->latest()->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'kind']),
            'statusOptions' => ConditionStatus::values(),
            'kindOptions' => ConditionKind::values(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('health/conditions/Form', $this->formProps($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->health->createCondition($request->user(), $request->validate($this->rules()));

        return redirect()->route('health.conditions.index')->with('success', 'Condición creada.');
    }

    public function edit(Request $request, HealthCondition $condition): Response
    {
        $this->authorize('update', $condition);

        return Inertia::render('health/conditions/Form', [
            'condition' => $condition->load('person:id,first_name,last_name'),
            ...$this->formProps($request),
        ]);
    }

    public function update(Request $request, HealthCondition $condition): RedirectResponse
    {
        $this->authorize('update', $condition);

        $this->health->updateCondition($request->user(), $condition, $request->validate($this->rules(partial: true)));

        return redirect()->route('health.conditions.index')->with('success', 'Condición actualizada.');
    }

    public function destroy(Request $request, HealthCondition $condition): RedirectResponse
    {
        $this->authorize('delete', $condition);

        $this->health->deleteCondition($request->user(), $condition);

        return redirect()->route('health.conditions.index')->with('success', 'Condición eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Request $request): array
    {
        return [
            'kindOptions' => ConditionKind::values(),
            'statusOptions' => ConditionStatus::values(),
            'severityOptions' => Severity::values(),
            'people' => Person::where('user_id', $request->user()->id)->visible()
                ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'providers' => HealthProfessional::where('user_id', $request->user()->id)
                ->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $partial = false): array
    {
        return [
            'kind' => [$partial ? 'sometimes' : 'required', Rule::enum(ConditionKind::class)],
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
            'status' => [$partial ? 'sometimes' : 'required', Rule::enum(ConditionStatus::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'diagnosed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $request->user()->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $request->user()->id)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

`rules()` usa `$request->user()` pero no recibe `$request`; cambiá la firma a `private function rules(Request $request, bool $partial = false): array` y llamala con `$this->rules($request)` / `$this->rules($request, partial: true)`.

- [ ] **Step 4: Write the pages**

`resources/js/pages/health/conditions/Index.tsx`: mismo esqueleto que `people/Index` (header + buscador + selects de estado/tipo + tabla con nombre, tipo, estado, persona, profesional + acciones editar/eliminar con `confirm()`), usando `health.conditions.index/create/edit/destroy` de `@/routes/health`.

`resources/js/pages/health/conditions/Form.tsx`: formulario `useForm` con nombre, tipo (`kindOptions`), estado (`statusOptions`), severidad (`severityOptions` opcional), fecha de diagnóstico (`date`), persona (`people`, "Sin vincular" → `person_id: ''`), profesional (`providers`, "Sin profesional"), notas; submit a `health.conditions.store/update`.

Seguí el patrón exacto de `resources/js/pages/people/Form.tsx` (normalización del prop: `const condition = conditionProp ?? {}` — **obligatorio** para evitar el crash del React Compiler con props opcionales) y tokens Ember.

- [ ] **Step 5: Run tests, build, pint, commit**

```bash
npm run build
php artisan test --compact tests/Feature/Health/HealthWebTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Health/ConditionController.php resources/js/pages/health/conditions tests/Feature/Health/HealthWebTest.php
git commit -m "feat(health): conditions web crud"
```

---

### Task 5: Web — medicación y tomas

**Files:**
- Modify: `app/Http/Controllers/Health/MedicationController.php`, `MedicationIntakeController.php`
- Create: `resources/js/pages/health/medications/Index.tsx`, `Form.tsx`
- Test: `tests/Feature/Health/HealthMedicationsWebTest.php`

**Interfaces:**
- Consumes: `HealthService::createMedication/updateMedication/deleteMedication/logIntake/deleteIntake`, rutas `health.medications.*`, `health.medications.intakes.*`.
- Produces: CRUD de medicación + alta/baja de tomas desde la lista; props con `medications` (paginado con `intakes` y último intake), `conditions`, `providers`, `people`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Health/HealthMedicationsWebTest.php`:

```php
<?php

use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates and updates a medication', function () {
    $this->post('/health/medications', [
        'name' => 'Levotiroxina',
        'dose_amount' => 50,
        'dose_unit' => 'mcg',
        'is_active' => true,
    ])->assertRedirect(route('health.medications.index'));

    $medication = HealthMedication::where('user_id', $this->user->id)->firstOrFail();

    $this->put("/health/medications/{$medication->id}", ['is_active' => false])
        ->assertRedirect(route('health.medications.index'));

    expect($medication->fresh()->is_active)->toBeFalse();
});

it('logs and deletes intakes from the list', function () {
    $medication = HealthMedication::factory()->create(['user_id' => $this->user->id]);

    $this->post("/health/medications/{$medication->id}/intakes", [
        'taken_at' => now()->toDateTimeString(),
        'status' => 'taken',
    ])->assertRedirect();

    $intake = HealthMedicationIntake::where('medication_id', $medication->id)->firstOrFail();

    $this->delete("/health/medications/{$medication->id}/intakes/{$intake->id}")->assertRedirect();

    expect(HealthMedicationIntake::find($intake->id))->toBeNull();
});

it('forbids another user medication and intake', function () {
    $medication = HealthMedication::factory()->create();
    $intake = HealthMedicationIntake::factory()->create([
        'user_id' => $medication->user_id,
        'medication_id' => $medication->id,
    ]);

    $this->put("/health/medications/{$medication->id}", ['name' => 'hack'])->assertForbidden();
    $this->post("/health/medications/{$medication->id}/intakes", ['taken_at' => now()])->assertForbidden();
    $this->delete("/health/medications/{$medication->id}/intakes/{$intake->id}")->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Health/HealthMedicationsWebTest.php`
Expected: FAIL — stubs sin métodos.

- [ ] **Step 3: Implement the controllers**

`app/Http/Controllers/Health/MedicationController.php`: `index` (query con `with('condition:id,name')` + `withCount('intakes')` + último intake `->withMax('intakes', 'taken_at')`; filtros `search`, `active`), `create/edit` con `formProps` (conditions, providers, people), `store/store` vía `HealthService`, `destroy`. Validación (store/partial update):

```php
'name' => [$partial ? 'sometimes' : 'required', 'string', 'max:255'],
'dose_amount' => ['nullable', 'numeric', 'min:0'],
'dose_unit' => ['nullable', 'string', 'max:30'],
'route' => ['nullable', 'string', 'max:30'],
'frequency_text' => ['nullable', 'string', 'max:255'],
'started_at' => ['nullable', 'date'],
'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
'is_active' => ['boolean'],
'condition_id' => ['nullable', Rule::exists('health_conditions', 'id')->where('user_id', $request->user()->id)],
'prescriber_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $request->user()->id)],
'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $request->user()->id)],
'notes' => ['nullable', 'string'],
```

`app/Http/Controllers/Health/MedicationIntakeController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Health\Enums\IntakeStatus;
use App\Http\Controllers\Controller;
use App\Models\HealthMedication;
use App\Models\HealthMedicationIntake;
use App\Services\Health\HealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicationIntakeController extends Controller
{
    public function __construct(protected HealthService $health) {}

    public function store(Request $request, HealthMedication $medication): RedirectResponse
    {
        $this->authorize('update', $medication);

        $this->health->logIntake($request->user(), $medication, $request->validate([
            'taken_at' => ['required', 'date'],
            'status' => ['required', Rule::enum(IntakeStatus::class)],
            'notes' => ['nullable', 'string'],
        ]));

        return back()->with('success', 'Toma registrada.');
    }

    public function destroy(Request $request, HealthMedication $medication, HealthMedicationIntake $intake): RedirectResponse
    {
        $this->authorize('update', $medication);
        abort_if($intake->medication_id !== $medication->id, 404);

        $this->health->deleteIntake($request->user(), $intake);

        return back()->with('success', 'Toma eliminada.');
    }
}
```

- [ ] **Step 4: Write the pages**

`medications/Index.tsx`: tabla (medicamento, dosis, condición, tomas, último intake, estado activo) + botones "Registrar toma" (dialog con fecha/hora + estado tomada/omitida) y acciones editar/eliminar; `medications/Form.tsx` con todos los campos y el prop normalizado del Task 4.

- [ ] **Step 5: Run tests, build, pint, commit**

```bash
npm run build
php artisan test --compact tests/Feature/Health/HealthMedicationsWebTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Health/MedicationController.php app/Http/Controllers/Health/MedicationIntakeController.php resources/js/pages/health/medications tests/Feature/Health/HealthMedicationsWebTest.php
git commit -m "feat(health): medications and manual intake logging web"
```

---

### Task 6: Web — mediciones (con evolución y sync de peso) y síntomas

**Files:**
- Modify: `app/Http/Controllers/Health/MeasurementController.php`, `SymptomController.php`
- Create: `resources/js/pages/health/measurements/Index.tsx`, `resources/js/pages/health/symptoms/Index.tsx`
- Test: `tests/Feature/Health/HealthMeasurementsWebTest.php`

**Interfaces:**
- Consumes: `HealthService::logMeasurement/updateMeasurement/deleteMeasurement/logSymptom/updateSymptom/deleteSymptom`.
- Produces: listados con dialogs de alta/edición; `measurements` prop con `chart` (últimos 60 puntos de `weight`/`blood_pressure` serializados) y `typeOptions`/`unitSuggestions`; `symptoms` con `severityOptions`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Health/HealthMeasurementsWebTest.php`:

```php
<?php

use App\Models\HealthMeasurement;
use App\Models\HealthSymptom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('logs a weight measurement and syncs the profile weight', function () {
    $this->post('/health/measurements', [
        'type' => 'weight',
        'value' => 62.5,
        'unit' => 'kg',
        'measured_at' => now()->toDateTimeString(),
    ])->assertRedirect();

    expect(HealthMeasurement::where('user_id', $this->user->id)->count())->toBe(1)
        ->and((float) $this->user->fresh()->weight)->toBe(62.5);
});

it('logs a blood pressure measurement with secondary value', function () {
    $this->post('/health/measurements', [
        'type' => 'blood_pressure',
        'value' => 118,
        'secondary_value' => 76,
        'unit' => 'mmHg',
        'measured_at' => now()->toDateTimeString(),
    ])->assertRedirect();

    $measurement = HealthMeasurement::firstOrFail();

    expect($measurement->secondary_value)->not->toBeNull();
});

it('renders measurements with chart data and symptoms list', function () {
    HealthMeasurement::factory()->count(2)->create(['user_id' => $this->user->id]);
    HealthSymptom::factory()->create(['user_id' => $this->user->id, 'symptom' => 'calambre']);

    $this->get('/health/measurements')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('health/measurements/Index')->has('chart', 2));

    $this->get('/health/symptoms')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/symptoms/Index')
            ->where('symptoms.data', fn ($rows) => collect($rows)->pluck('symptom')->contains('calambre')));
});

it('forbids deleting another user measurement or symptom', function () {
    $measurement = HealthMeasurement::factory()->create();
    $symptom = HealthSymptom::factory()->create();

    $this->delete("/health/measurements/{$measurement->id}")->assertForbidden();
    $this->delete("/health/symptoms/{$symptom->id}")->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Health/HealthMeasurementsWebTest.php`
Expected: FAIL — stubs sin métodos.

- [ ] **Step 3: Implement the controllers**

`MeasurementController`:
- `index`: `$measurements = HealthMeasurement::where('user_id',...)->when(type)->latest('measured_at')->paginate(15)->withQueryString();` + `chart` = últimos 60 puntos del tipo seleccionado (default `weight`) del titular y sin `person_id`, serializados como `[{date: 'Y-m-d', value: float}]`; + `typeOptions` y `unitSuggestions` (mapa tipo→unidad).
- `store/update/destroy` con `HealthService` + validación:

```php
'type' => [$partial ? 'sometimes' : 'required', Rule::enum(MeasurementType::class)],
'value' => [$partial ? 'sometimes' : 'required', 'numeric'],
'secondary_value' => ['nullable', 'numeric'],
'unit' => [$partial ? 'sometimes' : 'required', 'string', 'max:20'],
'measured_at' => [$partial ? 'sometimes' : 'required', 'date'],
'notes' => ['nullable', 'string'],
'person_id' => ['nullable', Rule::exists('people','id')->where('user_id', $request->user()->id)],
```

Autorización: `$this->authorize('update'|'delete', $measurement)`.

`SymptomController`: `index` (filtros search/severity, `with('person:id,first_name,last_name')`), `store/update/destroy` con validación `symptom` required string, `severity` enum, `occurred_at` date, `notes`, `person_id`. Mismo patrón de autorización.

- [ ] **Step 4: Write the pages**

`measurements/Index.tsx`: select de tipo (default Peso), tarjeta de evolución con SVG propio (polyline de los `chart` puntos normalizados al alto del contenedor, sin librerías; guía de máxima/mínima), tabla de mediciones con acciones, dialog de alta/edición con `secondary_value` visible solo para presión, y unidad pre-sugerida.

` symptoms/Index.tsx`: buscador + select de severidad + tabla (fecha, síntoma, severidad como badge, persona, notas) + dialog de alta/edición.

- [ ] **Step 5: Run tests, build, pint, commit**

```bash
npm run build
php artisan test --compact tests/Feature/Health/HealthMeasurementsWebTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Health/MeasurementController.php app/Http/Controllers/Health/SymptomController.php resources/js/pages/health/measurements resources/js/pages/health/symptoms tests/Feature/Health/HealthMeasurementsWebTest.php
git commit -m "feat(health): measurements with evolution chart and symptoms web"
```

---

### Task 7: Web — profesionales

**Files:**
- Modify: `app/Http/Controllers/Health/ProfessionalController.php`
- Create: `resources/js/pages/health/professionals/Index.tsx`, `Form.tsx`
- Test: `tests/Feature/Health/HealthProfessionalsWebTest.php`

**Interfaces:**
- Consumes: `HealthService::createProfessional/updateProfessional/deleteProfessional`.
- Produces: CRUD de profesionales/centros con `professionals` paginado y filtros `search`/`type`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\HealthProfessional;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates, updates and deletes a professional', function () {
    $this->post('/health/professionals', [
        'type' => 'professional',
        'name' => 'Dra. López',
        'specialty' => 'Neurología',
    ])->assertRedirect(route('health.professionals.index'));

    $professional = HealthProfessional::where('user_id', $this->user->id)->firstOrFail();

    $this->put("/health/professionals/{$professional->id}", ['is_active' => false])->assertRedirect();
    expect($professional->fresh()->is_active)->toBeFalse();

    $this->delete("/health/professionals/{$professional->id}")->assertRedirect();
    expect(HealthProfessional::find($professional->id))->toBeNull();
});

it('forbids editing another user professional', function () {
    $professional = HealthProfessional::factory()->create();

    $this->put("/health/professionals/{$professional->id}", ['name' => 'hack'])->assertForbidden();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Health/HealthProfessionalsWebTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement controller + pages**

`ProfessionalController` con `index/create/store/edit/update/destroy`, validación (`type` enum, `name` required, `specialty/phone/email/address/notes` nullable, `email` email, `is_active` boolean) y `authorize`. Páginas `Index.tsx` (tabla + acciones) y `Form.tsx` (prop normalizado, patrón Task 4).

- [ ] **Step 4: Run tests, build, pint, commit**

```bash
npm run build
php artisan test --compact tests/Feature/Health/HealthProfessionalsWebTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Health/ProfessionalController.php resources/js/pages/health/professionals tests/Feature/Health/HealthProfessionalsWebTest.php
git commit -m "feat(health): professionals web crud"
```

---

### Task 8: Chats de salud categorizados

**Files:**
- Create: `database/migrations/2026_09_30_120600_add_category_and_context_to_agent_conversations_table.php`
- Modify: `app/Models/ChatThread.php`
- Modify: `app/Http/Resources/ChatThreadResource.php`
- Modify: `app/Http/Controllers/Health/HealthChatController.php`
- Create: `resources/js/pages/health/chats/Index.tsx`
- Modify: `resources/js/types/chat.ts`
- Modify: `resources/js/components/ai/chat/ThreadItem.tsx` (badge "Salud")
- Test: `tests/Feature/Health/HealthChatTest.php`

**Interfaces:**
- Consumes: `ChatService::createThread`, `ChatThread::forUser/active/ordered/category`, modelos `HealthCondition`/`Person`.
- Produces: hilos con `category` + `context_type/context_id` + `tools_policy={mode:'manual',groups:['health']}`; página `health/chats/Index` con `threads` y `contextOptions`; badge en el rail.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Health/HealthChatTest.php`:

```php
<?php

use App\Models\ChatThread;
use App\Models\HealthCondition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates a health chat with pinned tools and context', function () {
    $condition = HealthCondition::factory()->create(['user_id' => $this->user->id]);

    $this->post('/health/chats', [
        'context_type' => 'health_condition',
        'context_id' => $condition->id,
    ])->assertRedirect();

    $thread = ChatThread::where('category', 'salud')->firstOrFail();

    expect($thread->tools_policy)->toBe(['mode' => 'manual', 'groups' => ['health']])
        ->and($thread->context_type)->toBe(HealthCondition::class)
        ->and($thread->context_id)->toBe($condition->id)
        ->and($thread->participant_id)->toBe($this->user->id);
});

it('lists only the user health chats', function () {
    $createThread = function (string $title, string $category): ChatThread {
        $thread = ChatThread::create([
            'id' => (string) \Illuminate\Support\Str::uuid7(),
            'participant_type' => $this->user->getMorphClass(),
            'participant_id' => $this->user->id,
            'title' => $title,
        ]);
        $thread->forceFill(['category' => $category])->save();

        return $thread;
    };

    $createThread('Hilo salud', 'salud');
    $createThread('Hilo general', 'general');

    $this->get('/health/chats')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('health/chats/Index')
            ->where('threads', fn ($rows) => collect($rows)->pluck('title')->all() === ['Hilo salud']));
});

it('rejects a context owned by another user', function () {
    $condition = HealthCondition::factory()->create();

    $this->post('/health/chats', [
        'context_type' => 'health_condition',
        'context_id' => $condition->id,
    ])->assertNotFound();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Health/HealthChatTest.php`
Expected: FAIL — columna `category` inexistente / stub sin métodos.

- [ ] **Step 3: Migration + model + resource**

`database/migrations/2026_09_30_120600_add_category_and_context_to_agent_conversations_table.php`:

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
            $table->string('category', 30)->default('general')->index();
            $table->string('context_type', 100)->nullable();
            $table->unsignedBigInteger('context_id')->nullable();
            $table->index(['context_type', 'context_id']);
        });
    }

    public function down(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');

        Schema::table($conversationsTable, function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropIndex(['context_type', 'context_id']);
            $table->dropColumn(['category', 'context_type', 'context_id']);
        });
    }
};
```

En `ChatThread`: constantes y relación

```php
    public const CATEGORY_GENERAL = 'general';
    public const CATEGORY_HEALTH = 'salud';

    public function context(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function scopeCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }
```

En `ChatThreadResource::toArray` agregar:

```php
            'category' => $this->category ?? ChatThread::CATEGORY_GENERAL,
            'context_type' => $this->context_type,
            'context_id' => $this->context_id,
            'context_label' => $this->whenLoaded('context', fn (): ?string => match (true) {
                $this->context instanceof \App\Models\HealthCondition => $this->context->name,
                $this->context instanceof \App\Models\Person => $this->context->full_name,
                default => null,
            }),
```

- [ ] **Step 4: Controller + page + rail badge**

`HealthChatController`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Ai\Services\ChatService;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatThreadResource;
use App\Models\ChatThread;
use App\Models\HealthCondition;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HealthChatController extends Controller
{
    public function __construct(protected ChatService $chat) {}

    public function index(Request $request): Response
    {
        $threads = ChatThread::query()
            ->forUser($request->user())
            ->category(ChatThread::CATEGORY_HEALTH)
            ->active()
            ->ordered()
            ->with('context')
            ->limit(100)
            ->get();

        return Inertia::render('health/chats/Index', [
            'threads' => ChatThreadResource::collection($threads),
            'contextOptions' => [
                'conditions' => HealthCondition::where('user_id', $request->user()->id)
                    ->orderBy('name')->get(['id', 'name']),
                'people' => Person::where('user_id', $request->user()->id)->visible()
                    ->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'context_type' => ['nullable', 'in:health_condition,person'],
            'context_id' => ['nullable', 'integer'],
        ]);

        $context = match ($validated['context_type'] ?? null) {
            'health_condition' => HealthCondition::where('user_id', $request->user()->id)
                ->findOrFail($validated['context_id']),
            'person' => Person::where('user_id', $request->user()->id)
                ->findOrFail($validated['context_id']),
            default => null,
        };

        $thread = $this->chat->createThread($request->user(), 'Consulta de salud');

        $thread->forceFill([
            'category' => ChatThread::CATEGORY_HEALTH,
            'context_type' => $context?->getMorphClass(),
            'context_id' => $context?->getKey(),
            'tools_policy' => ['mode' => 'manual', 'groups' => ['health']],
        ])->save();

        return redirect()->route('ai.chat.show', $thread);
    }
}
```

`resources/js/pages/health/chats/Index.tsx`: lista de hilos (título, badge de contexto con `context_label` o "General", fecha) linkeando a `/ai/chat/{id}` + botón "Nuevo chat de salud" con select de contexto (condiciones y personas de `contextOptions`) que hace `router.post(health.chats.store().url, { context_type, context_id })`. Estado vacío con `EmptyState` o el patrón de timeline.

`resources/js/types/chat.ts` — agregar a `ChatThread`:

```ts
    category: string;
    context_type: string | null;
    context_id: number | null;
    context_label?: string | null;
```

`resources/js/components/ai/chat/ThreadItem.tsx`: junto al título del hilo, si `thread.category === 'salud'` renderizar el badge

```tsx
<span className="rounded-full bg-primary/15 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-widest text-primary">Salud</span>
```

- [ ] **Step 5: Run tests, build, pint, commit**

```bash
npm run build
php artisan test --compact tests/Feature/Health/HealthChatTest.php
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_09_30_120600_add_category_and_context_to_agent_conversations_table.php app/Models/ChatThread.php app/Http/Resources/ChatThreadResource.php app/Http/Controllers/Health/HealthChatController.php resources/js/pages/health/chats resources/js/types/chat.ts resources/js/components/ai/chat/ThreadItem.tsx tests/Feature/Health/HealthChatTest.php
git commit -m "feat(health): categorised health chats with context and pinned tools"
```

---

### Task 9: API v1

**Files:**
- Create: `app/Http/Controllers/Api/V1/Health{Condition,Medication,MedicationIntake,Measurement,Symptom,Professional}Controller.php`
- Create: `app/Http/Requests/Api/{Store,Update}Health*Request.php`
- Create: `app/Http/Resources/Health*Resource.php` (6)
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/HealthApiTest.php`

**Interfaces:**
- Consumes: `HealthService`, modelos/enums, `HealthSeeder` (no).
- Produces: endpoints `/api/v1/health/*` con nombres `api.health.*`, 201 en store, `paginate(15)`, 403 ajenos.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Api/HealthApiTest.php`:

```php
<?php

use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->headers = ['Authorization' => 'Bearer '.$this->user->createToken('api-token')->plainTextToken];
});

it('creates, lists and updates conditions scoped to the token user', function () {
    HealthCondition::factory()->create(['user_id' => $this->user->id, 'name' => 'Miopatía', 'status' => 'suspected']);
    HealthCondition::factory()->create(['name' => 'Ajena']);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/health/conditions?search=mio')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Miopatía');

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/conditions', [
            'kind' => 'diagnosis',
            'name' => 'Hipotiroidismo',
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Hipotiroidismo');

    $condition = HealthCondition::where('name', 'Hipotiroidismo')->firstOrFail();

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/conditions/{$condition->id}", ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/conditions', ['name' => 'Incompleta'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['kind', 'status']);
});

it('logs medications, intakes, measurements and symptoms', function () {
    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/medications', ['name' => 'Levotiroxina', 'is_active' => true])
        ->assertCreated();

    $medication = HealthMedication::where('user_id', $this->user->id)->firstOrFail();

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/health/medications/{$medication->id}/intakes", [
            'taken_at' => now()->toDateTimeString(),
            'status' => 'taken',
        ])
        ->assertCreated();

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/measurements', [
            'type' => 'weight',
            'value' => 62.5,
            'unit' => 'kg',
            'measured_at' => now()->toDateTimeString(),
        ])
        ->assertCreated();

    expect((float) $this->user->fresh()->weight)->toBe(62.5);

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/health/symptoms', [
            'symptom' => 'calambre',
            'severity' => 'moderate',
            'occurred_at' => now()->toDateTimeString(),
        ])
        ->assertCreated();
});

it('protects ownership and exposes the summary', function () {
    $foreign = HealthCondition::factory()->create();

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/health/conditions/{$foreign->id}", ['status' => 'resolved'])
        ->assertForbidden();

    $this->withHeaders($this->headers)
        ->deleteJson("/api/v1/health/conditions/{$foreign->id}")
        ->assertForbidden();

    HealthMeasurement::factory()->create(['user_id' => $this->user->id]);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/health/summary')
        ->assertOk()
        ->assertJsonStructure(['data' => ['active_conditions', 'active_medications', 'last_measurements', 'recent_symptoms']]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Api/HealthApiTest.php`
Expected: FAIL — 404.

- [ ] **Step 3: Write requests, resources and controllers**

Requests (Store + Update con `sometimes` en los `required` del store, patrón People). Ejemplo `StoreHealthConditionRequest`:

```php
<?php

namespace App\Http\Requests\Api;

use App\Health\Enums\ConditionKind;
use App\Health\Enums\ConditionStatus;
use App\Health\Enums\Severity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(ConditionKind::class)],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ConditionStatus::class)],
            'severity' => ['nullable', Rule::enum(Severity::class)],
            'diagnosed_at' => ['nullable', 'date'],
            'provider_id' => ['nullable', Rule::exists('health_professionals', 'id')->where('user_id', $this->user()->id)],
            'person_id' => ['nullable', Rule::exists('people', 'id')->where('user_id', $this->user()->id)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

`UpdateHealthConditionRequest` extiende el Store y en `rules()` reemplaza `required` por `sometimes` en `kind`, `name`, `status` (`array_merge(parent::rules(), [...overrides])`).

Lo mismo para `HealthMedication` (`name` required en store), `HealthMeasurement` (`type`, `value`, `unit`, `measured_at`), `HealthSymptom` (`symptom`, `severity`?, `occurred_at`), `HealthProfessional` (`type`, `name`), `StoreHealthMedicationIntakeRequest` (`taken_at`, `status`).

Resources: arrays planos con enums `->value`, fechas ISO8601/`toDateString`, `person`/`provider`/`condition` `whenLoaded`. Ejemplo `HealthConditionResource`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthConditionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind?->value,
            'name' => $this->name,
            'status' => $this->status?->value,
            'severity' => $this->severity?->value,
            'diagnosed_at' => $this->diagnosed_at?->toDateString(),
            'provider_id' => $this->provider_id,
            'person_id' => $this->person_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

Controllers: patrón `Api/V1/PersonController` (scoping con `abort_if`, `paginate(15)`, `201` en store, `summary` con `HealthService`). Nombres de clases `HealthConditionController`, etc.

- [ ] **Step 4: Register the API routes**

En `routes/api.php`, dentro del grupo `auth:sanctum`, agregar (con los `use` correspondientes):

```php
        // Health
        Route::name('api.')->group(function () {
            Route::get('health/summary', [HealthDashboardController::class, 'summary'])->name('health.summary');
            Route::apiResource('health/conditions', HealthConditionController::class)->except(['create', 'edit']);
            Route::apiResource('health/professionals', HealthProfessionalController::class)->except(['create', 'edit']);
            Route::apiResource('health/medications', HealthMedicationController::class)->except(['create', 'edit']);
            Route::get('health/medications/{medication}/intakes', [HealthMedicationIntakeController::class, 'index'])->name('health.medications.intakes.index');
            Route::post('health/medications/{medication}/intakes', [HealthMedicationIntakeController::class, 'store'])->name('health.medications.intakes.store');
            Route::delete('health/medications/{medication}/intakes/{intake}', [HealthMedicationIntakeController::class, 'destroy'])->name('health.medications.intakes.destroy');
            Route::apiResource('health/measurements', HealthMeasurementController::class)->except(['create', 'edit']);
            Route::apiResource('health/symptoms', HealthSymptomController::class)->except(['create', 'edit']);
        });
```

`HealthDashboardController` (Api/V1) implementa solo `summary` con `HealthService::summary`.

**Ojo:** `apiResource` con prefijo `health/` genera nombres `api.health.conditions.*` dentro del grupo `Route::name('api.')` — pero `->name('api.')` se aplica a nombres generados por apiResource: sí, Laravel aplica el name prefix del grupo. Verificalo con `php artisan route:list --path=api/v1/health` (nombres `api.health.*`) y que **no** colisionen con web `health.*`.

- [ ] **Step 5: Run tests, pint, commit**

```bash
php artisan test --compact tests/Feature/Api/HealthApiTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/Health*.php app/Http/Requests/Api/*Health* app/Http/Resources/Health* routes/api.php tests/Feature/Api/HealthApiTest.php
git commit -m "feat(health): api v1 endpoints for the full record"
```

---

### Task 10: MCP — `HealthReadTool`, `HealthWriteTool`, `HealthLogTool`

**Files:**
- Create: `app/Mcp/Tools/HealthReadTool.php`, `HealthWriteTool.php`, `HealthLogTool.php`
- Modify: `app/Mcp/Servers/MegalomaniacServer.php`
- Modify: `tests/Feature/Mcp/McpToolsSmokeTest.php` (conteo)
- Test: `tests/Feature/Mcp/HealthToolsTest.php`

**Interfaces:**
- Consumes: `HealthService`.
- Produces: tools `health-read`, `health-write`, `health-log` en el server MCP.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Mcp/HealthToolsTest.php`:

```php
<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\HealthLogTool;
use App\Mcp\Tools\HealthReadTool;
use App\Mcp\Tools\HealthWriteTool;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, reads, updates and deletes conditions', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(HealthWriteTool::class, ['action' => 'create_condition', 'name' => 'Hipotiroidismo', 'kind' => 'diagnosis', 'status' => 'active'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('condition.name', 'Hipotiroidismo')->etc());

    $condition = HealthCondition::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(HealthReadTool::class, ['resource' => 'conditions'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('records')->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(HealthWriteTool::class, ['action' => 'update_condition', 'condition_id' => $condition->id, 'status' => 'resolved'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('condition.status', 'resolved')->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(HealthWriteTool::class, ['action' => 'delete_condition', 'condition_id' => $condition->id])
        ->assertOk();

    expect(HealthCondition::find($condition->id))->toBeNull();
});

it('logs measurements through the log tool and syncs weight', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(HealthLogTool::class, [
            'kind' => 'measurement',
            'type' => 'weight',
            'value' => 62.5,
            'unit' => 'kg',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('measurement')->etc());

    expect((float) $user->fresh()->weight)->toBe(62.5)
        ->and(HealthMeasurement::where('user_id', $user->id)->count())->toBe(1);
});

it('returns the summary and scopes tools to the authenticated user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    HealthMedication::factory()->create(['user_id' => $owner->id]);
    HealthCondition::factory()->create(['user_id' => $owner->id]);

    MegalomaniacServer::actingAs($owner)
        ->tool(HealthReadTool::class, ['resource' => 'summary'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('summary.active_medications')->etc());

    MegalomaniacServer::actingAs($intruder)
        ->tool(HealthWriteTool::class, ['action' => 'update_condition', 'condition_id' => $owner->healthConditions()->firstOrFail()->id, 'name' => 'hack'])
        ->assertHasErrors(['not found']);

    expect($owner->healthConditions()->first()->name)->not->toBe('hack');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Mcp/HealthToolsTest.php`
Expected: FAIL — tools no existen.

- [ ] **Step 3: Implement the tools**

`HealthReadTool` (`#[IsReadOnly]`, `$name = 'health-read'`): parámetros `resource` (enum conditions|medications|intakes|measurements|symptoms|professionals|summary, default summary), `person_id`, `active`, `type`, `search`, `days`, `limit`. Devuelve `Response::structured`:
- `summary` → `$service->summary($user)` bajo `summary`.
- resto → `records` (limit default 20, 100 max) con filtros: `search` en name/symptom, `active` en medication, `type` en measurement, `days` en `measured_at`/`occurred_at`.

`HealthWriteTool` (`$name = 'health-write'`): actions `create_condition`, `update_condition`, `delete_condition`, `create_medication`, `update_medication`, `delete_medication`, `create_professional`, `update_professional`, `delete_professional`, `log_measurement`, `log_symptom`, `log_intake`. Cada acción delega en `HealthService` con `findCondition/findMedication` para updates/deletes; `log_intake` requiere `medication_id`. `$request->validate([...])` en cada acción (patrón `PeopleWriteTool`), campos nullable/sometimes en updates.

`HealthLogTool` (`$name = 'health-log'`): `kind` enum `measurement|symptom|intake` + campos por kind; **solo crea**; nunca edita. Descripción: "Quick-log health events. Only creates new records; never edits existing data."

Registrar las 3 en `MegalomaniacServer::$tools` y ampliar `#[Instructions]` ("health records: conditions, medications and intakes, measurements, symptoms and professionals"). Bump del conteo en `McpToolsSmokeTest` (19 → 22).

**Nota:** los tests de scoping usan `assertHasErrors(['not found'])`; el servicio lanza `HealthCondition not found.` etc.

- [ ] **Step 4: Run tests, pint, commit**

```bash
php artisan test --compact tests/Feature/Mcp/HealthToolsTest.php tests/Feature/Mcp
vendor/bin/pint --dirty --format agent
git add app/Mcp/Tools/Health*.php app/Mcp/Servers/MegalomaniacServer.php tests/Feature/Mcp/HealthToolsTest.php tests/Feature/Mcp/McpToolsSmokeTest.php
git commit -m "feat(health): mcp read, write and log tools"
```

---

### Task 11: Chat IA — `HealthQueryTool`, `HealthActionTool`, catálogo y routing

**Files:**
- Create: `app/Ai/Tools/HealthQueryTool.php`, `HealthActionTool.php`
- Modify: `app/Ai/Tools/ToolCatalog.php`, `config/ai_tools.php`, `resources/js/lib/chat-tools.ts`, `docs/modules/tools.md`
- Test: `tests/Feature/Ai/HealthQueryToolTest.php`, `HealthActionToolTest.php`
- Modify (si sus expectativas exactas lo requieren): `tests/Feature/Ai/{ToolCatalogTest,ToolRouterTest,MegalomaniacAgentTest}.php`

**Interfaces:**
- Consumes: `HealthService`.
- Produces: grupo de chat `health` con query + action (aprobable); keywords; labels.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Ai/HealthQueryToolTest.php`:

```php
<?php

use App\Ai\Tools\HealthQueryTool;
use App\Models\HealthCondition;
use App\Models\HealthMeasurement;
use App\Models\HealthSymptom;
use App\Models\User;
use App\Services\Health\HealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function healthQueryTool(User $user): HealthQueryTool
{
    return new HealthQueryTool($user, app(HealthService::class));
}

it('returns the summary and filters records by resource', function () {
    $user = User::factory()->create();
    HealthCondition::factory()->create(['user_id' => $user->id, 'name' => 'Hipotiroidismo']);
    HealthMeasurement::factory()->create(['user_id' => $user->id]);
    HealthSymptom::factory()->create(['user_id' => $user->id, 'symptom' => 'calambre']);

    $summary = json_decode((string) healthQueryTool($user)->handle(new Request(['resource' => 'summary'])), true);

    expect($summary['summary']['active_conditions'])->toHaveCount(1)
        ->and($summary['summary']['recent_symptoms'])->toHaveCount(1);

    $conditions = json_decode((string) healthQueryTool($user)->handle(new Request(['resource' => 'conditions', 'search' => 'hipo'])), true);

    expect($conditions['records'])->toHaveCount(1)
        ->and($conditions['records'][0]['name'])->toBe('Hipotiroidismo');
});

it('scopes results to the authenticated user', function () {
    $user = User::factory()->create();
    HealthCondition::factory()->create(['name' => 'Ajena']);

    $result = json_decode((string) healthQueryTool($user)->handle(new Request(['resource' => 'conditions'])), true);

    expect($result['records'])->toHaveCount(0);
});
```

`tests/Feature/Ai/HealthActionToolTest.php`:

```php
<?php

use App\Ai\Tools\HealthActionTool;
use App\Health\Enums\IntakeStatus;
use App\Models\HealthCondition;
use App\Models\HealthMedication;
use App\Models\User;
use App\Services\Health\HealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function healthTool(User $user): HealthActionTool
{
    return new HealthActionTool($user, app(HealthService::class));
}

it('creates conditions, medications and logs events', function () {
    $user = User::factory()->create();

    $condition = json_decode(healthTool($user)->handle(new Request([
        'action' => 'create_condition',
        'name' => 'Hipotiroidismo',
        'kind' => 'diagnosis',
        'status' => 'active',
    ])), true);

    expect($condition['success'])->toBeTrue();

    $medication = json_decode(healthTool($user)->handle(new Request([
        'action' => 'create_medication',
        'name' => 'Levotiroxina',
        'dose_amount' => 50,
        'dose_unit' => 'mcg',
    ])), true);

    expect($medication['success'])->toBeTrue();

    $medicationId = HealthMedication::where('user_id', $user->id)->firstOrFail()->id;

    $intake = json_decode(healthTool($user)->handle(new Request([
        'action' => 'log_intake',
        'medication_id' => $medicationId,
        'status' => IntakeStatus::Taken->value,
    ])), true);

    expect($intake['success'])->toBeTrue()
        ->and(HealthCondition::where('user_id', $user->id)->exists())->toBeTrue();
});

it('returns domain errors instead of throwing', function () {
    $user = User::factory()->create();

    $result = json_decode(healthTool($user)->handle(new Request([
        'action' => 'update_condition',
        'condition_id' => 999,
        'name' => 'x',
    ])), true);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('not found');
});

it('requires approval with a readable label and never diagnoses', function () {
    $user = User::factory()->create();

    $approval = healthTool($user)->needsApproval(new Request(['action' => 'create_condition']));

    expect($approval)->toBeInstanceOf(Approval::class)
        ->and($approval->reason)->toContain('crear')
        ->and((string) healthTool($user)->description())->toContain('never diagnose');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Ai/HealthQueryToolTest.php tests/Feature/Ai/HealthActionToolTest.php`
Expected: FAIL — clases no existen.

- [ ] **Step 3: Implement the tools**

`HealthQueryTool(User $user, HealthService $health)`: mismo esquema de `resource` que el MCP read, devolviendo JSON pretty-print (`GymQueryTool` pattern). `description()` menciona que el asistente **no diagnostica**.

`HealthActionTool(User $user, HealthService $health)` implements `Approvable, Tool` con `InteractsWithApprovals`, `needsApproval` = `Approval::required('Va a '.$labels[$action])`, labels en español (`create_condition` → 'crear una condición', `log_measurement` → 'registrar una medición', etc.), y `handle` con `match` sobre las mismas acciones del MCP write + log. Errores: catch `ModelNotFoundException|\ValueError|InvalidArgumentException` → `$this->error(...)`.

Ambas tools: constructor con `HealthService` (el `make()` del catálogo debe resolverlas).

- [ ] **Step 4: Wire catalog, routing and labels**

- `ToolCatalog::groups()`: `'health' => ['label' => 'Salud', 'tools' => [HealthQueryTool::class, HealthActionTool::class]]`.
- `actionTools()`: agregar `HealthActionTool::class`.
- `make()`: `HealthQueryTool::class => new HealthQueryTool($user, app(HealthService::class))`, `HealthActionTool::class => new HealthActionTool($user, app(HealthService::class))`.
- `config/ai_tools.php` keywords:

```php
        'health' => [
            'salud', 'médic', 'medic', 'remedio', 'pastilla', 'síntoma', 'sintoma',
            'dolor', 'presión', 'presion', 'glucosa', 'análisis', 'analisis',
            'estudio', 'laboratorio', 'doctor', 'doctora', 'turno', 'consulta',
            'tsh', 'tiroides', 'peso', 'orina', 'calambre', 'cansancio',
        ],
```

y `'health'` en `fallback`.
- `resources/js/lib/chat-tools.ts`: `HealthQueryTool: 'Consultando salud'`, `HealthActionTool: 'Actualizando salud'`.
- `docs/modules/tools.md`: fila `| health | HealthQueryTool | HealthActionTool | Services\Health\HealthService |`.
- Actualizar `ToolCatalogTest`/`ToolRouterTest`/`MegalomaniacAgentTest` si hardcodean grupos/conteos.

- [ ] **Step 5: Run tests, build, pint, commit**

```bash
php artisan test --compact tests/Feature/Ai/HealthQueryToolTest.php tests/Feature/Ai/HealthActionToolTest.php tests/Feature/Ai/ToolCatalogTest.php tests/Feature/Ai/ToolRouterTest.php
npm run build
vendor/bin/pint --dirty --format agent
git add app/Ai/Tools/Health*.php app/Ai/Tools/ToolCatalog.php config/ai_tools.php resources/js/lib/chat-tools.ts docs/modules/tools.md tests/Feature/Ai/Health*Test.php tests/Feature/Ai/ToolCatalogTest.php tests/Feature/Ai/ToolRouterTest.php tests/Feature/Ai/MegalomaniacAgentTest.php
git commit -m "feat(health): chat query and action tools with routing and labels"
```

---

### Task 12: Cierre — suite completa, build, smoke de rutas y QA browser

**Files:**
- Modify: solo si la verificación encuentra fallos.

- [ ] **Step 1: Health test set**

```bash
php artisan test --compact tests/Feature/Health tests/Feature/Api/HealthApiTest.php tests/Feature/Mcp/HealthToolsTest.php tests/Feature/Ai/HealthQueryToolTest.php tests/Feature/Ai/HealthActionToolTest.php
```
Expected: PASS.

- [ ] **Step 2: Full suite (regression gate)**

```bash
php artisan test --compact
```
Expected: PASS. Si algo falla por archivos compartidos (ToolCatalog, chat, sidebar), arreglar acá con commit explícito.

- [ ] **Step 3: Build + types**

```bash
npm run build
npm run types
php artisan route:list --path=health
php artisan route:list --path=api/v1/health
```
Expected: build/types OK; web `health.*` y API `api.health.*` sin colisiones.

- [ ] **Step 4: QA browser (controller)**

Con un server efímero aislado (sqlite en `/tmp`), recorrer: login → sidebar Salud → panel con datos del seeder → alta de condición → medicación + toma → medición de peso (verificar que el perfil cambia) → síntoma → profesional → `/health/chats` crea un chat vinculado y redirige al chat con badge "Salud". Corregir lo observable (regla anti-ui-slop).

- [ ] **Step 5: Commit final (solo si hubo fixes)**

```bash
vendor/bin/pint --dirty --format agent
git add <paths explícitos>
git commit -m "chore(health): phase 1 polish and qa pass"
```

**No deploy** sin confirmación del usuario.

---

## Self-Review

**Cobertura de la spec (Fase 1):** condiciones (T1, T4, T9–T11), medicación + tomas (T1, T5, T9–T11), mediciones + sync de peso (T1–T2, T6, T9–T11), síntomas (T1, T6, T9–T11), profesionales (T1, T7, T9–T11), panel (T3), chats categorizados (T8), API v1 (T9), MCP (T10), chat IA con escritura total y aviso de no diagnosticar (T11). Estudios/PDF/consultas quedan para F2/F3 según spec.

**Consistencia de tipos:** `HealthService` con las mismas firmas en T2/T9/T10/T11; enums en `App\Health\Enums`; tablas `health_*`; rutas web `health.*`, API `api.health.*`, chat `health`; `ChatThread::CATEGORY_HEALTH = 'salud'`; context morph con FQCN de `HealthCondition`/`Person`.

**Placeholders:** no hay TBD; cada paso trae código o instrucción exacta de edición. Los stubs de controllers se completan en sus propias tareas (indicado explícitamente).

**Riesgo de coordinación:** el chat compartido (T8/T11) se edita con staging explícito y verificación de `git log` previa; el cierre (T12) corre la suite completa como red.


