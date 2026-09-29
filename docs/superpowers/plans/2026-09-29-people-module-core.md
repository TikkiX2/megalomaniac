# People Module Core (Fase 1) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar el núcleo del módulo People (contactos, interacciones, fechas clave y redes sociales) con web Inertia, API v1, tools MCP y tools del chat, todo escribiendo por un `PeopleService` compartido.

**Architecture:** `app/Services/People/PeopleService.php` es la única fuente de escritura (ownership + reglas); web, API, MCP y chat son adaptadores finos. `people.last_contacted_at` se mantiene con un observer en `PersonInteraction` (`#[ObservedBy]`). El avatar vive en medialibrary (colección `avatar`, `singleFile`). El puente con Freelance es una FK suave `clients.person_id` sin observer espejo.

**Tech Stack:** PHP 8.4, Laravel 12, Eloquent, Pest 4, laravel/mcp ^0.9.4, laravel/ai ^0.11, Sanctum, Inertia v2 + React 19 + Tailwind v4 + Wayfinder, spatie/laravel-medialibrary ^11.18.

**Spec:** `docs/superpowers/specs/2026-09-29-people-design.md`

## Nota de coordinación

Hay otra sesión de trabajo activa en este repo (plan de Gym / reparación de tools). Antes de tocar archivos compartidos (`app/Ai/Tools/ToolCatalog.php`, `config/ai_tools.php`, `resources/js/lib/chat-tools.ts`, `routes/web.php`, `database/seeders/DatabaseSeeder.php`, `docs/modules/tools.md`) verificá `git log -3` y reaplicá la edición sobre el estado actual. Al commitear, stageá solo archivos de este plan.

## Global Constraints

- PHP 8.4 / Laravel 12 / Pest 4. `RefreshDatabase` ya aplica globalmente a `tests/Feature` (`tests/Pest.php`); igual se declara `uses(RefreshDatabase::class)` por convención.
- Modelos nuevos: `casts()` como método, `$fillable` explícito, relaciones tipadas. Sin `DB::` fuera de migraciones.
- **Escrituras de People pasan por `App\Services\People\PeopleService`** (convención `docs/modules/tools.md`). Los adaptadores validan y autorizan; el servicio valida ownership y lanza `ModelNotFoundException('X not found.')`.
- MCP: paridad actual — **sin gate de aprobación**; read tools con `#[IsReadOnly]` devuelven `Response::structured`; write tools devuelven `Response::structured` o `Response::error`.
- Chat: las action tools implementan `Approvable` + `InteractsWithApprovals` y usan `Approval::required(...)`; grupos y keywords en `ToolCatalog` + `config/ai_tools.php`; labels en `resources/js/lib/chat-tools.ts`.
- API v1: Form Requests en `app/Http/Requests/Api/`, Resources planos en `app/Http/Resources/`, `paginate(15)`, ownership con `abort_if(..., 403)`, respuestas 201 en store.
- Web: `PersonPolicy` (auto-discovery de Laravel 12) + `$this->authorize(...)`; validación inline en el controller (patrón `Freelance\ClientController` y `Gym\WorkoutController`).
- UI: solo tokens Ember (`bg-card`, `border-border`, `text-muted-foreground`, `bg-primary`, `text-destructive`); **no introducir hex nuevos ni dependencias npm**. Reusar `components/ui/*` existentes. Páginas en PascalCase (`Index.tsx`, `Form.tsx`, `Show.tsx`) como freelance/personal.
- Wayfinder: importar de `@/routes/people`; se regenera con `npm run build` (plugin de Vite). Las rutas anidadas con guion (`people.key-dates.*`) se consumen como `people.keyDates.*`.
- Cada tarea cierra con: tests verdes del archivo tocado (`php artisan test --compact <path>`) + `vendor/bin/pint --dirty --format agent` + commit propio.

---

## File Structure

**Nuevos — backend**
- `database/migrations/2026_09_29_110000_create_people_table.php`
- `database/migrations/2026_09_29_110100_create_person_interactions_table.php`
- `database/migrations/2026_09_29_110200_create_person_key_dates_table.php`
- `database/migrations/2026_09_29_110300_create_person_socials_table.php`
- `database/migrations/2026_09_29_110400_add_person_id_to_clients_table.php`
- `app/People/Enums/{Closeness,RelationshipStatus,PreferredContactChannel,InteractionChannel,KeyDateType}.php`
- `app/Models/{Person,PersonInteraction,PersonKeyDate,PersonSocial}.php`
- `app/Observers/PersonInteractionObserver.php`
- `app/Services/People/PeopleService.php`
- `app/Policies/PersonPolicy.php`
- `app/Http/Controllers/People/{PersonController,PersonInteractionController,PersonKeyDateController,PersonSocialController}.php`
- `routes/people.php`
- `app/Http/Controllers/Api/V1/{PersonController,PersonInteractionController,PersonKeyDateController,PersonSocialController}.php`
- `app/Http/Requests/Api/{StorePersonRequest,UpdatePersonRequest,StorePersonInteractionRequest,StorePersonKeyDateRequest,UpdatePersonKeyDateRequest,StorePersonSocialRequest,UpdatePersonSocialRequest}.php`
- `app/Http/Resources/{PersonResource,PersonInteractionResource,PersonKeyDateResource,PersonSocialResource}.php`
- `app/Mcp/Tools/{PeopleReadTool,PeopleWriteTool,PeopleLogTool}.php`
- `app/Ai/Tools/{PeopleQueryTool,PeopleActionTool}.php`
- `database/factories/{PersonFactory,PersonInteractionFactory,PersonKeyDateFactory,PersonSocialFactory}.php`
- `database/seeders/PeopleSeeder.php`
- `resources/js/layouts/people-layout.tsx`
- `resources/js/pages/people/{Index,Form,Show,Calendar,Timeline}.tsx`

**Nuevos — tests**
- `tests/Feature/People/PeopleDataTest.php`
- `tests/Feature/People/PeopleServiceTest.php`
- `tests/Feature/People/PeopleWebTest.php`
- `tests/Feature/People/FreelancePersonBridgeTest.php`
- `tests/Feature/Api/PeopleApiTest.php`
- `tests/Feature/Mcp/PeopleToolsTest.php`
- `tests/Feature/Ai/PeopleQueryToolTest.php`
- `tests/Feature/Ai/PeopleActionToolTest.php`

**Modificados**
- `routes/web.php` — `require __DIR__.'/people.php';`
- `routes/api.php` — bloque `// People` dentro de `auth:sanctum`
- `app/Models/Client.php` — `person_id` fillable + relación `person()`
- `app/Http/Controllers/Freelance/ClientController.php` — pasa `people` a create/edit + valida `person_id`
- `app/Http/Resources/ClientResource.php` — `person_id` + `person` whenLoaded
- `resources/js/pages/freelance/clients/Form.tsx` — selector de persona
- `app/Mcp/Servers/MegalomaniacServer.php` — 3 tools
- `app/Ai/Tools/ToolCatalog.php` — grupo `people` + `actionTools()`
- `config/ai_tools.php` — keywords `people` + fallback
- `resources/js/lib/chat-tools.ts` — labels
- `resources/js/components/app-sidebar.tsx` — grupo "Personas"
- `database/seeders/DatabaseSeeder.php` — `PeopleSeeder`
- `docs/modules/tools.md` — fila People
- `tests/Feature/Ai/ToolCatalogTest.php` y `tests/Feature/Ai/ToolRouterTest.php` (si existen) — expectativas nuevas

---

### Task 1: Capa de datos People (migraciones, enums, modelos, factories, seeder, observer)

**Files:**
- Create: `database/migrations/2026_09_29_110000_create_people_table.php`
- Create: `database/migrations/2026_09_29_110100_create_person_interactions_table.php`
- Create: `database/migrations/2026_09_29_110200_create_person_key_dates_table.php`
- Create: `database/migrations/2026_09_29_110300_create_person_socials_table.php`
- Create: `app/People/Enums/Closeness.php`, `RelationshipStatus.php`, `PreferredContactChannel.php`, `InteractionChannel.php`, `KeyDateType.php`
- Create: `app/Models/Person.php`, `PersonInteraction.php`, `PersonKeyDate.php`, `PersonSocial.php`
- Create: `app/Observers/PersonInteractionObserver.php`
- Create: `database/factories/PersonFactory.php`, `PersonInteractionFactory.php`, `PersonKeyDateFactory.php`, `PersonSocialFactory.php`
- Create: `database/seeders/PeopleSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/People/PeopleDataTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: tablas `people`, `person_interactions`, `person_key_dates`, `person_socials`; enums en `App\People\Enums\*` con `values(): array`; modelos con `casts()`; `Person` con `avatar_url`/`full_name` appends, scope `visible()`, `refreshLastContactedAt()`, relaciones `interactions()`, `keyDates()`, `socials()`; observer registrado por atributo; factories y `PeopleSeeder`.

- [ ] **Step 1: Write the failing test**

Crear `tests/Feature/People/PeopleDataTest.php`:

```php
<?php

use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\User;
use App\People\Enums\Closeness;
use App\People\Enums\InteractionChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates people with enum casts and defaults', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create(['user_id' => $user->id]);

    expect($person->fresh()->closeness)->toBeInstanceOf(Closeness::class)
        ->and($person->fresh()->is_archived)->toBeFalse()
        ->and($person->fresh()->last_contacted_at)->toBeNull();
});

it('updates last_contacted_at when an interaction is logged', function () {
    $person = Person::factory()->create();
    $when = now()->subDays(3)->startOfMinute();

    PersonInteraction::factory()->create([
        'user_id' => $person->user_id,
        'person_id' => $person->id,
        'channel' => InteractionChannel::Call,
        'occurred_at' => $when,
    ]);

    expect($person->fresh()->last_contacted_at->equalTo($when))->toBeTrue();
});

it('recomputes last_contacted_at when the latest interaction is deleted', function () {
    $person = Person::factory()->create();
    $old = PersonInteraction::factory()->create([
        'user_id' => $person->user_id,
        'person_id' => $person->id,
        'occurred_at' => now()->subDays(10),
    ]);
    $recent = PersonInteraction::factory()->create([
        'user_id' => $person->user_id,
        'person_id' => $person->id,
        'occurred_at' => now()->subDay(),
    ]);

    $recent->delete();

    expect($person->fresh()->last_contacted_at->equalTo($old->occurred_at))->toBeTrue();
});

it('scopes visible people', function () {
    Person::factory()->create(['is_archived' => false]);
    Person::factory()->create(['is_archived' => true]);

    expect(Person::visible()->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/People/PeopleDataTest.php`
Expected: FAIL — `Class "App\Models\Person" not found`.

- [ ] **Step 3: Write the enums**

`app/People/Enums/Closeness.php`:

```php
<?php

declare(strict_types=1);

namespace App\People\Enums;

enum Closeness: string
{
    case InnerCircle = 'inner_circle';
    case Close = 'close';
    case Friend = 'friend';
    case Acquaintance = 'acquaintance';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
```

`RelationshipStatus.php` con casos `Single='single'`, `Dating='dating'`, `InRelationship='in_relationship'`, `Engaged='engaged'`, `Married='married'`, `Divorced='divorced'`, `Widowed='widowed'`, `Complicated='complicated'`, `Other='other'`.
`PreferredContactChannel.php` con `Whatsapp='whatsapp'`, `Phone='phone'`, `Email='email'`, `Message='message'`, `InPerson='in_person'`, `Other='other'`.
`InteractionChannel.php` con `InPerson='in_person'`, `Call='call'`, `Video='video'`, `Message='message'`, `Email='email'`, `Other='other'`.
`KeyDateType.php` con `Birthday='birthday'`, `Anniversary='anniversary'`, `Graduation='graduation'`, `Memorial='memorial'`, `Custom='custom'`.
Los cinco comparten el mismo `values()` del ejemplo.

- [ ] **Step 4: Write the migrations**

`database/migrations/2026_09_29_110000_create_people_table.php`:

```php
<?php

use App\People\Enums\Closeness;
use App\People\Enums\PreferredContactChannel;
use App\People\Enums\RelationshipStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('nickname')->nullable();
            $table->date('birthday')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('whatsapp', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();
            $table->string('website')->nullable();
            $table->text('how_we_met')->nullable();
            $table->enum('closeness', Closeness::values())->default('friend');
            $table->enum('relationship_status', RelationshipStatus::values())->nullable();
            $table->enum('preferred_contact_channel', PreferredContactChannel::values())->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('last_contacted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_archived']);
            $table->index(['user_id', 'last_contacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
```

`database/migrations/2026_09_29_110100_create_person_interactions_table.php`:

```php
<?php

use App\People\Enums\InteractionChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', InteractionChannel::values());
            $table->timestamp('occurred_at');
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_interactions');
    }
};
```

`database/migrations/2026_09_29_110200_create_person_key_dates_table.php`:

```php
<?php

use App\People\Enums\KeyDateType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_key_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->enum('type', KeyDateType::values())->default('custom');
            $table->string('label')->nullable();
            $table->date('date');
            $table->unsignedTinyInteger('remind_days_before')->default(7);
            $table->boolean('is_recurring_annually')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_key_dates');
    }
};
```

`database/migrations/2026_09_29_110300_create_person_socials_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_socials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->cascadeOnDelete();
            $table->string('network', 50);
            $table->string('handle')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();

            $table->index(['person_id', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_socials');
    }
};
```

- [ ] **Step 5: Write the models**

`app/Models/Person.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\People\Enums\Closeness;
use App\People\Enums\PreferredContactChannel;
use App\People\Enums\RelationshipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Person extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'user_id', 'first_name', 'last_name', 'nickname', 'birthday',
        'email', 'phone', 'whatsapp', 'address', 'city', 'country',
        'company', 'job_title', 'website', 'how_we_met', 'closeness',
        'relationship_status', 'preferred_contact_channel', 'is_favorite',
        'is_archived', 'last_contacted_at', 'notes',
    ];

    protected $appends = ['avatar_url', 'full_name'];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'closeness' => Closeness::class,
            'relationship_status' => RelationshipStatus::class,
            'preferred_contact_channel' => PreferredContactChannel::class,
            'is_favorite' => 'boolean',
            'is_archived' => 'boolean',
            'last_contacted_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar') ?: null;
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function refreshLastContactedAt(): void
    {
        $this->forceFill([
            'last_contacted_at' => $this->interactions()->max('occurred_at'),
        ])->saveQuietly();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(PersonInteraction::class);
    }

    public function keyDates(): HasMany
    {
        return $this->hasMany(PersonKeyDate::class)->orderBy('date');
    }

    public function socials(): HasMany
    {
        return $this->hasMany(PersonSocial::class)->orderBy('network');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }
}
```

`app/Models/PersonInteraction.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\PersonInteractionObserver;
use App\People\Enums\InteractionChannel;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([PersonInteractionObserver::class])]
class PersonInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'person_id', 'channel', 'occurred_at', 'title', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'channel' => InteractionChannel::class,
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

`app/Models/PersonKeyDate.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\People\Enums\KeyDateType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonKeyDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'person_id', 'type', 'label', 'date',
        'remind_days_before', 'is_recurring_annually',
    ];

    protected function casts(): array
    {
        return [
            'type' => KeyDateType::class,
            'date' => 'date',
            'is_recurring_annually' => 'boolean',
        ];
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->label ?: ucfirst(str_replace('_', ' ', $this->type->value));
    }

    public function nextOccurrence(): ?Carbon
    {
        if (! $this->is_recurring_annually) {
            return $this->date->isToday() || $this->date->isFuture()
                ? $this->date->copy()
                : null;
        }

        $candidate = $this->date->copy()->year((int) now()->year);

        if ($candidate->lt(now()->startOfDay())) {
            $candidate->addYear();
        }

        return $candidate;
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

`app/Models/PersonSocial.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonSocial extends Model
{
    use HasFactory;

    protected $fillable = ['person_id', 'network', 'handle', 'url'];

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
```

- [ ] **Step 6: Write the observer**

`app/Observers/PersonInteractionObserver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\PersonInteraction;

class PersonInteractionObserver
{
    public function saved(PersonInteraction $interaction): void
    {
        $interaction->person?->refreshLastContactedAt();
    }

    public function deleted(PersonInteraction $interaction): void
    {
        $interaction->person?->refreshLastContactedAt();
    }
}
```

- [ ] **Step 7: Write the factories**

`database/factories/PersonFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\User;
use App\People\Enums\Closeness;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'nickname' => null,
            'birthday' => $this->faker->optional()->dateTimeBetween('-60 years', '-18 years'),
            'email' => $this->faker->optional()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'whatsapp' => null,
            'address' => null,
            'city' => $this->faker->optional()->city(),
            'country' => $this->faker->optional()->country(),
            'company' => $this->faker->optional()->company(),
            'job_title' => null,
            'website' => null,
            'how_we_met' => null,
            'closeness' => Closeness::Friend,
            'relationship_status' => null,
            'preferred_contact_channel' => null,
            'is_favorite' => false,
            'is_archived' => false,
            'last_contacted_at' => null,
            'notes' => null,
        ];
    }
}
```

`database/factories/PersonInteractionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\User;
use App\People\Enums\InteractionChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonInteraction>
 */
class PersonInteractionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => Person::factory(),
            'channel' => $this->faker->randomElement(InteractionChannel::cases()),
            'occurred_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'title' => $this->faker->optional()->sentence(3),
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }
}
```

`database/factories/PersonKeyDateFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonKeyDate;
use App\Models\User;
use App\People\Enums\KeyDateType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonKeyDate>
 */
class PersonKeyDateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'person_id' => Person::factory(),
            'type' => KeyDateType::Custom,
            'label' => $this->faker->optional()->word(),
            'date' => $this->faker->dateTimeBetween('-5 years', '+1 year'),
            'remind_days_before' => 7,
            'is_recurring_annually' => false,
        ];
    }
}
```

`database/factories/PersonSocialFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Person;
use App\Models\PersonSocial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonSocial>
 */
class PersonSocialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'network' => $this->faker->randomElement(['instagram', 'x', 'linkedin', 'github', 'telegram']),
            'handle' => '@'.$this->faker->userName(),
            'url' => $this->faker->optional()->url(),
        ];
    }
}
```

- [ ] **Step 8: Write the seeder and register it**

`database/seeders/PeopleSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\PersonKeyDate;
use App\Models\PersonSocial;
use App\Models\User;
use App\People\Enums\Closeness;
use App\People\Enums\InteractionChannel;
use App\People\Enums\KeyDateType;
use Illuminate\Database\Seeder;

class PeopleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first()
            ?? User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $ana = Person::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'nickname' => 'Anita',
            'closeness' => Closeness::InnerCircle,
            'is_favorite' => true,
            'birthday' => now()->subYears(29)->startOfYear()->addMonths(9)->addDays(12),
        ]);

        PersonSocial::factory()->create([
            'person_id' => $ana->id,
            'network' => 'instagram',
            'handle' => '@anagomez',
        ]);

        PersonKeyDate::factory()->create([
            'user_id' => $user->id,
            'person_id' => $ana->id,
            'type' => KeyDateType::Anniversary,
            'label' => 'Aniversario de amistad',
            'date' => now()->subYears(6)->startOfYear()->addMonths(10)->addDays(2),
            'is_recurring_annually' => true,
        ]);

        PersonInteraction::factory()->create([
            'user_id' => $user->id,
            'person_id' => $ana->id,
            'channel' => InteractionChannel::Call,
            'occurred_at' => now()->subDays(4),
            'title' => 'Llamada de cumpleaños',
        ]);

        $bruno = Person::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'Bruno',
            'last_name' => 'Díaz',
            'closeness' => Closeness::Friend,
        ]);

        PersonInteraction::factory()->create([
            'user_id' => $user->id,
            'person_id' => $bruno->id,
            'channel' => InteractionChannel::Message,
            'occurred_at' => now()->subDays(45),
            'title' => 'Nos escribimos',
        ]);

        Person::factory()->count(2)->create([
            'user_id' => $user->id,
            'closeness' => Closeness::Acquaintance,
        ]);
    }
}
```

Modificar `database/seeders/DatabaseSeeder.php` agregando después de `PersonalSeeder`:

```php
        $this->call(PeopleSeeder::class);
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/People/PeopleDataTest.php`
Expected: PASS (4 tests).

- [ ] **Step 10: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/People app/Models/Person.php app/Models/PersonInteraction.php app/Models/PersonKeyDate.php app/Models/PersonSocial.php app/Observers/PersonInteractionObserver.php database/migrations/2026_09_29_1100*.php database/factories/Person*.php database/seeders/PeopleSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/People/PeopleDataTest.php
git commit -m "feat(people): data layer for people, interactions, key dates and socials"
```

---

### Task 2: Puente Freelance — `clients.person_id`

**Files:**
- Create: `database/migrations/2026_09_29_110400_add_person_id_to_clients_table.php`
- Modify: `app/Models/Client.php`
- Modify: `app/Http/Controllers/Freelance/ClientController.php`
- Modify: `app/Http/Resources/ClientResource.php`
- Modify: `resources/js/pages/freelance/clients/Form.tsx`
- Test: `tests/Feature/People/FreelancePersonBridgeTest.php`

**Interfaces:**
- Consumes: `App\Models\Person` (Task 1).
- Produces: `clients.person_id` nullable FK; `Client::person()`; `Person::clients()` ya existe; `ClientController::create/edit` pasan prop `people` (id, name); `ClientResource` expone `person_id` y `person` whenLoaded.

- [ ] **Step 1: Write the failing test**

`tests/Feature/People/FreelancePersonBridgeTest.php`:

```php
<?php

use App\Models\Client;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('links a client to a person without duplicating data', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);

    $this->post('/freelance/clients', [
        'name' => 'Estudio Gómez',
        'person_id' => $person->id,
    ])->assertRedirect(route('freelance.clients.index'));

    $client = Client::where('name', 'Estudio Gómez')->firstOrFail();

    expect($client->person_id)->toBe($person->id)
        ->and($client->person->first_name)->toBe('Ana')
        ->and($person->clients()->count())->toBe(1);
});

it('rejects a person owned by another user', function () {
    $intruder = Person::factory()->create();

    $this->post('/freelance/clients', [
        'name' => 'Estudio Ajeno',
        'person_id' => $intruder->id,
    ])->assertSessionHasErrors('person_id');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/People/FreelancePersonBridgeTest.php`
Expected: FAIL — `column "person_id" does not exist`.

- [ ] **Step 3: Write the migration, model and controller changes**

`database/migrations/2026_09_29_110400_add_person_id_to_clients_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('person_id')->nullable()->after('user_id')
                ->constrained('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('person_id');
        });
    }
};
```

En `app/Models/Client.php`: agregar `'person_id'` a `$fillable` y:

```php
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
```

En `app/Http/Controllers/Freelance/ClientController.php`:
- `create()` y `edit()` pasan la lista de personas:

```php
use App\Models\Person;
use Illuminate\Validation\Rule;

    public function create()
    {
        return Inertia::render('freelance/clients/Form', [
            'people' => Person::where('user_id', request()->user()->id)
                ->visible()
                ->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function edit(Client $client)
    {
        return Inertia::render('freelance/clients/Form', [
            'client' => $client,
            'people' => Person::where('user_id', request()->user()->id)
                ->visible()
                ->get(['id', 'first_name', 'last_name']),
        ]);
    }
```

- En `store()` y `update()`, agregar a la validación y a la escritura:

```php
            'person_id' => [
                'nullable',
                Rule::exists('people', 'id')->where('user_id', $request->user()->id),
            ],
```

`$request->user()->clients()->create($validated)` y `$client->update($validated)` ya toman el campo desde `$validated`.

En `app/Http/Resources/ClientResource.php`, agregar al array:

```php
            'person_id' => $this->person_id,
            'person' => new PersonResource($this->whenLoaded('person')),
```

- [ ] **Step 4: Add the person selector to the client form**

En `resources/js/pages/freelance/clients/Form.tsx`:
- Aceptar `people` en props y agregar `person_id: client?.person_id?.toString() || ''` al `useForm`.
- Renderizar (junto al resto de los campos, usando los primitives existentes):

```tsx
<div className="grid gap-2">
    <Label htmlFor="person_id">Persona vinculada</Label>
    <Select
        value={data.person_id || 'none'}
        onValueChange={(value) => setData('person_id', value === 'none' ? '' : value)}
    >
        <SelectTrigger id="person_id">
            <SelectValue placeholder="Sin vincular" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem value="none">Sin vincular</SelectItem>
            {people.map((person: any) => (
                <SelectItem key={person.id} value={String(person.id)}>
                    {person.first_name} {person.last_name}
                </SelectItem>
            ))}
        </SelectContent>
    </Select>
    {errors.person_id && <p className="text-sm text-destructive">{errors.person_id}</p>}
</div>
```

(Importar `Label`, `Select*` desde `@/components/ui/...` si no están.)

- [ ] **Step 5: Run tests, build, pint, commit**

```bash
php artisan test --compact tests/Feature/People/FreelancePersonBridgeTest.php tests/Feature/Freelance
npm run build
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_09_29_110400_add_person_id_to_clients_table.php app/Models/Client.php app/Http/Controllers/Freelance/ClientController.php app/Http/Resources/ClientResource.php resources/js/pages/freelance/clients/Form.tsx tests/Feature/People/FreelancePersonBridgeTest.php
git commit -m "feat(people): link freelance clients to people through a soft reference"
```

---

### Task 3: `PeopleService` (escritura compartida)

**Files:**
- Create: `app/Services/People/PeopleService.php`
- Test: `tests/Feature/People/PeopleServiceTest.php`

**Interfaces:**
- Consumes: modelos y enums de Task 1.
- Produces: `PeopleService` con `findPerson(User,int): Person`, `createPerson`, `updatePerson`, `deletePerson`, `logInteraction`, `deleteInteraction`, `addKeyDate`, `updateKeyDate`, `deleteKeyDate`, `addSocial`, `updateSocial`, `deleteSocial`, `upcoming(User, int $days = 30, ?Person $person = null): Collection`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/People/PeopleServiceTest.php`:

```php
<?php

use App\Models\Person;
use App\Models\PersonKeyDate;
use App\Models\User;
use App\People\Enums\Closeness;
use App\People\Enums\InteractionChannel;
use App\Services\People\PeopleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PeopleService::class);
    $this->user = User::factory()->create();
});

it('creates and updates a person scoped to the user', function () {
    $person = $this->service->createPerson($this->user, [
        'first_name' => 'Ana',
        'closeness' => Closeness::Close->value,
    ]);

    expect($person->user_id)->toBe($this->user->id);

    $updated = $this->service->updatePerson($this->user, $person, ['nickname' => 'Anita']);

    expect($updated->nickname)->toBe('Anita');
});

it('refuses to touch another user person', function () {
    $intruder = User::factory()->create();

    $this->expectException(ModelNotFoundException::class);
    $this->expectExceptionMessage('Person not found.');

    $this->service->findPerson($intruder, Person::factory()->create()->id);
});

it('logs an interaction and refreshes last_contacted_at', function () {
    $person = $this->service->createPerson($this->user, ['first_name' => 'Bruno']);
    $when = now()->subHours(2)->startOfMinute();

    $this->service->logInteraction($this->user, $person, [
        'channel' => InteractionChannel::Message->value,
        'occurred_at' => $when,
        'title' => 'Nos escribimos',
    ]);

    expect($person->fresh()->last_contacted_at->equalTo($when))->toBeTrue();
});

it('returns upcoming birthdays and key dates inside the window', function () {
    $person = $this->service->createPerson($this->user, [
        'first_name' => 'Cami',
        'birthday' => now()->addDays(3)->toDateString(),
    ]);
    $this->service->addKeyDate($this->user, $person, [
        'type' => 'anniversary',
        'label' => 'Aniversario',
        'date' => now()->addDays(40)->toDateString(),
        'is_recurring_annually' => false,
    ]);
    $this->service->addKeyDate($this->user, $person, [
        'type' => 'custom',
        'label' => 'Dentista',
        'date' => now()->addDays(10)->toDateString(),
        'is_recurring_annually' => false,
    ]);

    $upcoming = $this->service->upcoming($this->user, 30);

    expect($upcoming)->toHaveCount(2)
        ->and($upcoming->first()['kind'])->toBe('birthday')
        ->and($upcoming->first()['days_until'])->toBe(3)
        ->and($upcoming->last()['kind'])->toBe('key_date');
});

it('deletes key dates and socials with ownership checks', function () {
    $person = $this->service->createPerson($this->user, ['first_name' => 'Dani']);
    $keyDate = $this->service->addKeyDate($this->user, $person, [
        'type' => 'custom',
        'date' => now()->toDateString(),
    ]);
    $social = $this->service->addSocial($this->user, $person, [
        'network' => 'instagram',
        'handle' => '@dani',
    ]);

    $this->service->deleteKeyDate($this->user, $keyDate);
    $this->service->deleteSocial($this->user, $social);

    expect(PersonKeyDate::find($keyDate->id))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/People/PeopleServiceTest.php`
Expected: FAIL — `Class "App\Services\People\PeopleService" not found`.

- [ ] **Step 3: Implement the service**

`app/Services/People/PeopleService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\People;

use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\PersonKeyDate;
use App\Models\PersonSocial;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PeopleService
{
    public function findPerson(User $user, int $personId): Person
    {
        $person = Person::where('user_id', $user->id)->find($personId);

        if (! $person) {
            throw new ModelNotFoundException('Person not found.');
        }

        return $person;
    }

    public function createPerson(User $user, array $data): Person
    {
        return $user->people()->create($data);
    }

    public function updatePerson(User $user, Person $person, array $data): Person
    {
        $this->assertOwner($user, $person);
        $person->update($data);

        return $person->refresh();
    }

    public function deletePerson(User $user, Person $person): void
    {
        $this->assertOwner($user, $person);
        $person->delete();
    }

    public function logInteraction(User $user, Person $person, array $data): PersonInteraction
    {
        $this->assertOwner($user, $person);

        return $person->interactions()->create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    public function deleteInteraction(User $user, PersonInteraction $interaction): void
    {
        $this->assertOwner($user, $interaction);
        $interaction->delete();
    }

    public function addKeyDate(User $user, Person $person, array $data): PersonKeyDate
    {
        $this->assertOwner($user, $person);

        return $person->keyDates()->create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    public function updateKeyDate(User $user, PersonKeyDate $keyDate, array $data): PersonKeyDate
    {
        $this->assertOwner($user, $keyDate);
        $keyDate->update($data);

        return $keyDate->refresh();
    }

    public function deleteKeyDate(User $user, PersonKeyDate $keyDate): void
    {
        $this->assertOwner($user, $keyDate);
        $keyDate->delete();
    }

    public function addSocial(User $user, Person $person, array $data): PersonSocial
    {
        $this->assertOwner($user, $person);

        return $person->socials()->create($data);
    }

    public function updateSocial(User $user, PersonSocial $social, array $data): PersonSocial
    {
        $this->assertOwner($user, $social);
        $social->update($data);

        return $social->refresh();
    }

    public function deleteSocial(User $user, PersonSocial $social): void
    {
        $this->assertOwner($user, $social);
        $social->delete();
    }

    /**
     * Cumpleaños y fechas clave dentro de los próximos N días.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function upcoming(User $user, int $days = 30, ?Person $person = null): Collection
    {
        $today = now()->startOfDay();
        $items = collect();

        $people = Person::query()
            ->where('user_id', $user->id)
            ->where('is_archived', false)
            ->when($person, fn ($query) => $query->whereKey($person->id))
            ->get(['id', 'first_name', 'last_name', 'birthday']);

        foreach ($people as $subject) {
            if ($subject->birthday === null) {
                continue;
            }

            $next = $this->nextAnnualOccurrence($subject->birthday);

            if ($next === null || $today->diffInDays($next, false) > $days) {
                continue;
            }

            $items->push([
                'kind' => 'birthday',
                'label' => 'Cumpleaños',
                'next_occurrence' => $next->toDateString(),
                'days_until' => (int) $today->diffInDays($next, false),
                'reminds_days_before' => 7,
                'person' => [
                    'id' => $subject->id,
                    'name' => $subject->full_name,
                ],
            ]);
        }

        $keyDates = PersonKeyDate::with('person:id,first_name,last_name')
            ->where('user_id', $user->id)
            ->when($person, fn ($query) => $query->where('person_id', $person->id))
            ->get();

        foreach ($keyDates as $keyDate) {
            $next = $keyDate->nextOccurrence();

            if ($next === null || $today->diffInDays($next, false) > $days) {
                continue;
            }

            $items->push([
                'kind' => 'key_date',
                'label' => $keyDate->display_label,
                'next_occurrence' => $next->toDateString(),
                'days_until' => (int) $today->diffInDays($next, false),
                'reminds_days_before' => $keyDate->remind_days_before,
                'person' => [
                    'id' => $keyDate->person?->id,
                    'name' => $keyDate->person?->full_name,
                ],
            ]);
        }

        return new Collection($items->sortBy('days_until')->values()->all());
    }

    private function nextAnnualOccurrence(Carbon $date): ?Carbon
    {
        $candidate = $date->copy()->year((int) now()->year);

        if ($candidate->lt(now()->startOfDay())) {
            $candidate->addYear();
        }

        return $candidate;
    }

    private function assertOwner(User $user, Model $model): void
    {
        if ((int) $model->user_id !== (int) $user->id) {
            throw new ModelNotFoundException(class_basename($model).' not found.');
        }
    }
}
```

**Ojo:** el nombre del modelo Person incluye `people()` como relación inversa. Agregar en `app/Models/User.php`:

```php
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/People/PeopleServiceTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/People/PeopleService.php app/Models/User.php tests/Feature/People/PeopleServiceTest.php
git commit -m "feat(people): shared people service for web, api, mcp and chat"
```

---

### Task 4: Web — listado y ficha (Index/Show), policy, rutas, layout y sidebar

**Files:**
- Create: `routes/people.php`
- Create: `app/Policies/PersonPolicy.php`
- Create: `app/Http/Controllers/People/PersonController.php` (index/show/create/edit/store/update/destroy/calendar/timeline/uploadAvatar/destroyAvatar)
- Create: `resources/js/layouts/people-layout.tsx`
- Create: `resources/js/pages/people/Index.tsx`
- Create: `resources/js/pages/people/Show.tsx`
- Create: `resources/js/pages/people/Timeline.tsx`
- Modify: `routes/web.php` (require de `people.php`)
- Modify: `resources/js/components/app-sidebar.tsx` (grupo "Personas")
- Test: `tests/Feature/People/PeopleWebTest.php`

**Interfaces:**
- Consumes: `Person`, `PeopleService` (Tasks 1 y 3).
- Produces: rutas `people.*`; `PersonPolicy`; página `people/Index` con props `people` (paginado), `filters`, `closenessOptions`; `people/Show` con props `person` (load `keyDates`, `socials`, `media`), `interactions` (paginado), `upcoming`, `channelOptions`; `people/Timeline` con `interactions`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/People/PeopleWebTest.php`:

```php
<?php

use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\User;
use App\People\Enums\Closeness;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('lists only the authenticated user people', function () {
    Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);
    Person::factory()->create(['first_name' => 'Intruso']);
    Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Archivada', 'is_archived' => true]);

    $this->get('/people')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('people/Index')
            ->where('people.data', fn ($rows) => collect($rows)->pluck('first_name')->contains('Ana'))
            ->where('people.data', fn ($rows) => ! collect($rows)->pluck('first_name')->contains('Intruso'))
            ->where('people.data', fn ($rows) => ! collect($rows)->pluck('first_name')->contains('Archivada')));
});

it('filters people by search, closeness and stale days', function () {
    Person::factory()->create([
        'user_id' => $this->user->id,
        'first_name' => 'Ana',
        'closeness' => Closeness::Close,
        'last_contacted_at' => now()->subDays(90),
    ]);
    Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Bruno']);

    $this->get('/people?search=ana&closeness=close&stale_days=30')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('people.data', fn ($rows) => collect($rows)->pluck('first_name')->all() === ['Ana'])
            ->where('filters.stale_days', '30'));
});

it('shows a person with key dates, socials and interactions', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);
    PersonInteraction::factory()->create(['user_id' => $this->user->id, 'person_id' => $person->id]);

    $this->get("/people/{$person->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('people/Show')
            ->where('person.first_name', 'Ana')
            ->has('interactions.data', 1));
});

it('forbids viewing another user person', function () {
    $person = Person::factory()->create();

    $this->get("/people/{$person->id}")->assertForbidden();
});

it('renders the global timeline', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    PersonInteraction::factory()->create(['user_id' => $this->user->id, 'person_id' => $person->id]);

    $this->get('/people/timeline')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('people/Timeline')->has('interactions.data', 1));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/People/PeopleWebTest.php`
Expected: FAIL — 404 / `people/Index` no existe.

- [ ] **Step 3: Write routes, policy and controller**

`routes/people.php`:

```php
<?php

use App\Http\Controllers\People\PersonController;
use App\Http\Controllers\People\PersonInteractionController;
use App\Http\Controllers\People\PersonKeyDateController;
use App\Http\Controllers\People\PersonSocialController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('people')->name('people.')->group(function () {
    Route::get('/', [PersonController::class, 'index'])->name('index');
    Route::get('create', [PersonController::class, 'create'])->name('create');
    Route::post('/', [PersonController::class, 'store'])->name('store');
    Route::get('calendar', [PersonController::class, 'calendar'])->name('calendar');
    Route::get('timeline', [PersonController::class, 'timeline'])->name('timeline');

    Route::get('{person}', [PersonController::class, 'show'])->name('show');
    Route::get('{person}/edit', [PersonController::class, 'edit'])->name('edit');
    Route::put('{person}', [PersonController::class, 'update'])->name('update');
    Route::delete('{person}', [PersonController::class, 'destroy'])->name('destroy');

    Route::post('{person}/avatar', [PersonController::class, 'uploadAvatar'])->name('avatar.store');
    Route::delete('{person}/avatar', [PersonController::class, 'destroyAvatar'])->name('avatar.destroy');

    Route::post('{person}/contacted', [PersonInteractionController::class, 'quickLog'])->name('contacted');
    Route::post('{person}/interactions', [PersonInteractionController::class, 'store'])->name('interactions.store');
    Route::delete('interactions/{interaction}', [PersonInteractionController::class, 'destroy'])->name('interactions.destroy');

    Route::post('{person}/key-dates', [PersonKeyDateController::class, 'store'])->name('key-dates.store');
    Route::patch('key-dates/{keyDate}', [PersonKeyDateController::class, 'update'])->name('key-dates.update');
    Route::delete('key-dates/{keyDate}', [PersonKeyDateController::class, 'destroy'])->name('key-dates.destroy');

    Route::post('{person}/socials', [PersonSocialController::class, 'store'])->name('socials.store');
    Route::patch('socials/{social}', [PersonSocialController::class, 'update'])->name('socials.update');
    Route::delete('socials/{social}', [PersonSocialController::class, 'destroy'])->name('socials.destroy');
});
```

En `routes/web.php`, junto a los otros requires:

```php
require __DIR__.'/people.php';
```

`app/Policies/PersonPolicy.php`:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Person $person): bool
    {
        return $user->id === $person->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Person $person): bool
    {
        return $user->id === $person->user_id;
    }

    public function delete(User $user, Person $person): bool
    {
        return $user->id === $person->user_id;
    }
}
```

`app/Http/Controllers/People/PersonController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\PersonKeyDate;
use App\People\Enums\Closeness;
use App\People\Enums\InteractionChannel;
use App\People\Enums\PreferredContactChannel;
use App\People\Enums\RelationshipStatus;
use App\Services\People\PeopleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function index(Request $request): Response
    {
        $query = Person::query()
            ->where('user_id', $request->user()->id)
            ->with('media');

        $query->when($request->search, fn ($q, $search) => $q->where(function ($w) use ($search) {
            $w->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nickname', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%");
        }));

        $query->when($request->closeness, fn ($q, $closeness) => $q->where('closeness', $closeness));

        $request->boolean('archived')
            ? $query->where('is_archived', true)
            : $query->where('is_archived', false);

        $query->when($request->boolean('favorites'), fn ($q) => $q->where('is_favorite', true));

        $query->when($request->stale_days, fn ($q, $days) => $q->where(function ($w) use ($days) {
            $w->whereNull('last_contacted_at')
                ->orWhere('last_contacted_at', '<', now()->subDays((int) $days));
        }));

        $people = $query->orderByDesc('is_favorite')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('people/Index', [
            'people' => $people,
            'filters' => $request->only(['search', 'closeness', 'favorites', 'archived', 'stale_days']),
            'closenessOptions' => Closeness::values(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('people/Form', [
            'closenessOptions' => Closeness::values(),
            'relationshipOptions' => RelationshipStatus::values(),
            'channelOptions' => PreferredContactChannel::values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $person = $this->people->createPerson($request->user(), $validated);

        return redirect()->route('people.show', $person)->with('success', 'Persona creada.');
    }

    public function show(Person $person): Response
    {
        $this->authorize('view', $person);

        $person->load(['keyDates', 'socials', 'media']);

        return Inertia::render('people/Show', [
            'person' => $person,
            'interactions' => $person->interactions()
                ->latest('occurred_at')
                ->paginate(30)
                ->withQueryString(),
            'upcoming' => $this->people->upcoming(request()->user(), 60, $person),
            'channelOptions' => InteractionChannel::values(),
        ]);
    }

    public function edit(Person $person): Response
    {
        $this->authorize('update', $person);

        return Inertia::render('people/Form', [
            'person' => $person,
            'closenessOptions' => Closeness::values(),
            'relationshipOptions' => RelationshipStatus::values(),
            'channelOptions' => PreferredContactChannel::values(),
        ]);
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $validated = $request->validate($this->rules());

        $this->people->updatePerson($request->user(), $person, $validated);

        return redirect()->route('people.show', $person)->with('success', 'Persona actualizada.');
    }

    public function destroy(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('delete', $person);

        $this->people->deletePerson($request->user(), $person);

        return redirect()->route('people.index')->with('success', 'Persona eliminada.');
    }

    public function calendar(Request $request): Response
    {
        return Inertia::render('people/Calendar', [
            'people' => Person::query()
                ->where('user_id', $request->user()->id)
                ->visible()
                ->with('media')
                ->get(['id', 'first_name', 'last_name', 'birthday']),
            'keyDates' => PersonKeyDate::query()
                ->where('user_id', $request->user()->id)
                ->with('person:id,first_name,last_name')
                ->get(),
        ]);
    }

    public function timeline(Request $request): Response
    {
        $interactions = PersonInteraction::query()
            ->where('user_id', $request->user()->id)
            ->with('person:id,first_name,last_name')
            ->latest('occurred_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('people/Timeline', ['interactions' => $interactions]);
    }

    public function uploadAvatar(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $request->validate(['avatar' => ['required', 'image', 'max:5120']]);

        $person->addMediaFromRequest('avatar')->toMediaCollection('avatar');

        return back()->with('success', 'Avatar actualizado.');
    }

    public function destroyAvatar(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $person->clearMediaCollection('avatar');

        return back()->with('success', 'Avatar eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'how_we_met' => ['nullable', 'string'],
            'closeness' => ['required', Rule::enum(Closeness::class)],
            'relationship_status' => ['nullable', Rule::enum(RelationshipStatus::class)],
            'preferred_contact_channel' => ['nullable', Rule::enum(PreferredContactChannel::class)],
            'is_favorite' => ['boolean'],
            'is_archived' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

Los otros tres controllers web (`PersonInteractionController`, `PersonKeyDateController`, `PersonSocialController`) se crean en las Tareas 6, 7 y 8; como `routes/people.php` los importa, creá desde ya stubs vacíos que extiendan `Controller` (sin métodos) para que las rutas no rompan, y completalos en su tarea.

- [ ] **Step 4: Write layout, Index, Show and Timeline pages**

`resources/js/layouts/people-layout.tsx`:

```tsx
import type { ReactNode } from 'react';
import MainLayout from '@/layouts/main-layout';

export default function PeopleLayout({ children }: { children: ReactNode }) {
    return <MainLayout>{children}</MainLayout>;
}
```

`resources/js/pages/people/Index.tsx`:

```tsx
import { Head, Link, router } from '@inertiajs/react';
import { Archive, Cake, MoreHorizontal, Pencil, Plus, Search, Star, Trash } from 'lucide-react';
import React, { useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel,
    DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

const CLOSENESS_LABELS: Record<string, string> = {
    inner_circle: 'Círculo íntimo',
    close: 'Cercano',
    friend: 'Amigo',
    acquaintance: 'Conocido',
};

function relativeDays(value: string | null): string {
    if (!value) return 'Sin registro';
    const days = Math.floor((Date.now() - new Date(value).getTime()) / 86_400_000);
    if (days <= 0) return 'Hoy';
    if (days === 1) return 'Ayer';
    if (days < 30) return `Hace ${days} días`;
    const months = Math.floor(days / 30);
    return months === 1 ? 'Hace 1 mes' : `Hace ${months} meses`;
}

export default function PeopleIndex({ people: paginator, filters, closenessOptions }: any) {
    const [search, setSearch] = useState(filters.search || '');

    const applyFilter = (patch: Record<string, string>) => {
        router.get(people.index().url, { ...filters, search, ...patch }, {
            preserveState: true,
            replace: true,
        });
    };

    const remove = (id: number) => {
        if (confirm('¿Eliminar esta persona y su historial?')) {
            router.delete(people.destroy(id).url);
        }
    };

    return (
        <PeopleLayout>
            <Head title="Personas" />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-white">Personas</h1>
                        <p className="text-muted-foreground">Tu agenda social: vínculos, fechas y contacto.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={people.calendar().url}><Cake className="mr-2 h-4 w-4" /> Calendario</Link>
                        </Button>
                        <Button asChild className="bg-primary text-white font-bold">
                            <Link href={people.create().url}><Plus className="mr-2 h-4 w-4" /> Nueva Persona</Link>
                        </Button>
                    </div>
                </div>

                <div className="flex flex-col gap-2 md:flex-row md:items-center">
                    <div className="relative flex-1 md:max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por nombre, alias o empresa..."
                            className="pl-8 bg-card border-border"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilter({})}
                        />
                    </div>
                    <Select value={filters.closeness || 'all'} onValueChange={(value) => applyFilter({ closeness: value === 'all' ? '' : value })}>
                        <SelectTrigger className="md:w-44 bg-card border-border"><SelectValue placeholder="Cercanía" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Toda cercanía</SelectItem>
                            {closenessOptions.map((value: string) => (
                                <SelectItem key={value} value={value}>{CLOSENESS_LABELS[value] ?? value}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select value={filters.stale_days || 'all'} onValueChange={(value) => applyFilter({ stale_days: value === 'all' ? '' : value })}>
                        <SelectTrigger className="md:w-48 bg-card border-border"><SelectValue placeholder="Contacto" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Sin filtro de contacto</SelectItem>
                            <SelectItem value="30">Sin contacto +30 días</SelectItem>
                            <SelectItem value="60">Sin contacto +60 días</SelectItem>
                            <SelectItem value="90">Sin contacto +90 días</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        variant={filters.favorites ? 'default' : 'outline'}
                        className={filters.favorites ? 'bg-primary' : ''}
                        onClick={() => applyFilter({ favorites: filters.favorites ? '' : '1' })}
                    >
                        <Star className="mr-2 h-4 w-4" /> Favoritos
                    </Button>
                    <Button
                        variant={filters.archived ? 'default' : 'outline'}
                        className={filters.archived ? 'bg-primary' : ''}
                        onClick={() => applyFilter({ archived: filters.archived ? '' : '1' })}
                    >
                        <Archive className="mr-2 h-4 w-4" /> Archivados
                    </Button>
                </div>

                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <Table>
                        <TableHeader className="bg-background">
                            <TableRow className="hover:bg-transparent border-border">
                                <TableHead />
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Nombre</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Cercanía</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Último contacto</TableHead>
                                <TableHead className="text-muted-foreground font-black uppercase text-[10px] tracking-widest">Cumpleaños</TableHead>
                                <TableHead className="w-[80px]" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {paginator.data.length === 0 ? (
                                <TableRow className="hover:bg-transparent border-border">
                                    <TableCell colSpan={6} className="text-center h-24 text-muted-foreground italic">
                                        No se encontraron personas.
                                    </TableCell>
                                </TableRow>
                            ) : paginator.data.map((person: any) => (
                                <TableRow key={person.id} className="hover:bg-white/5 border-border">
                                    <TableCell>
                                        <Avatar className="h-9 w-9">
                                            {person.avatar_url && <AvatarImage src={person.avatar_url} alt={person.full_name} />}
                                            <AvatarFallback className="bg-primary/20 text-primary text-xs font-black">
                                                {person.first_name?.[0]?.toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>
                                    </TableCell>
                                    <TableCell>
                                        <Link href={people.show(person.id).url} className="font-bold text-white hover:text-primary">
                                            {person.full_name}
                                        </Link>
                                        {person.nickname && <span className="ml-2 text-xs text-muted-foreground">“{person.nickname}”</span>}
                                        {person.is_favorite && <Star className="ml-2 inline h-3 w-3 fill-primary text-primary" />}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className="border-primary/30 text-primary text-[10px] uppercase font-black">
                                            {CLOSENESS_LABELS[person.closeness] ?? person.closeness}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-white/80">{relativeDays(person.last_contacted_at)}</TableCell>
                                    <TableCell className="text-white/80">{person.birthday?.slice(0, 10) || '-'}</TableCell>
                                    <TableCell>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" className="h-8 w-8 p-0 text-white hover:bg-white/10">
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="bg-card border-border text-white">
                                                <DropdownMenuLabel className="text-muted-foreground text-[10px] uppercase font-black">Acciones</DropdownMenuLabel>
                                                <DropdownMenuItem asChild className="focus:bg-secondary focus:text-white">
                                                    <Link href={people.edit(person.id).url}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator className="bg-border" />
                                                <DropdownMenuItem className="text-destructive focus:bg-destructive/20" onClick={() => remove(person.id)}>
                                                    <Trash className="mr-2 h-4 w-4" /> Eliminar
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {(paginator.prev_page_url || paginator.next_page_url) && (
                    <div className="flex items-center justify-end gap-2">
                        <Button variant="outline" disabled={!paginator.prev_page_url} asChild={!!paginator.prev_page_url}>
                            {paginator.prev_page_url
                                ? <Link href={paginator.prev_page_url}>Anterior</Link>
                                : <span>Anterior</span>}
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            Página {paginator.current_page} de {paginator.last_page}
                        </span>
                        <Button variant="outline" disabled={!paginator.next_page_url} asChild={!!paginator.next_page_url}>
                            {paginator.next_page_url
                                ? <Link href={paginator.next_page_url}>Siguiente</Link>
                                : <span>Siguiente</span>}
                        </Button>
                    </div>
                )}
            </div>
        </PeopleLayout>
    );
}
```

`resources/js/pages/people/Show.tsx` (base de la ficha; las Tareas 6, 7 y 8 insertan sus secciones antes del cierre de `</div>` final):

```tsx
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, MessageCircle, Pencil, Star } from 'lucide-react';
import React from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

const CLOSENESS_LABELS: Record<string, string> = {
    inner_circle: 'Círculo íntimo',
    close: 'Cercano',
    friend: 'Amigo',
    acquaintance: 'Conocido',
};

export default function PersonShow({ person, interactions, upcoming, channelOptions }: any) {
    const quickLog = () => {
        router.post(people.contacted(person.id).url, {}, { preserveScroll: true });
    };

    return (
        <PeopleLayout>
            <Head title={person.full_name} />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href={people.index().url}><ArrowLeft className="h-4 w-4" /></Link>
                        </Button>
                        <Avatar className="h-16 w-16">
                            {person.avatar_url && <AvatarImage src={person.avatar_url} alt={person.full_name} />}
                            <AvatarFallback className="bg-primary/20 text-primary text-xl font-black">
                                {person.first_name?.[0]?.toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight text-white">
                                {person.full_name}
                                {person.is_favorite && <Star className="h-4 w-4 fill-primary text-primary" />}
                            </h1>
                            <div className="mt-1 flex items-center gap-2">
                                <Badge variant="outline" className="border-primary/30 text-primary text-[10px] uppercase font-black">
                                    {CLOSENESS_LABELS[person.closeness] ?? person.closeness}
                                </Badge>
                                {person.nickname && <span className="text-sm text-muted-foreground">“{person.nickname}”</span>}
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={quickLog} className="bg-primary text-white font-bold">
                            <MessageCircle className="mr-2 h-4 w-4" /> Registrar contacto
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={people.edit(person.id).url}><Pencil className="mr-2 h-4 w-4" /> Editar</Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="bg-card border-border lg:col-span-1">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Perfil</CardTitle></CardHeader>
                        <CardContent className="space-y-2 text-sm text-white/90">
                            {person.birthday && <p><span className="text-muted-foreground">Cumpleaños:</span> {person.birthday.slice(0, 10)}</p>}
                            {person.email && <p><span className="text-muted-foreground">Email:</span> {person.email}</p>}
                            {person.phone && <p><span className="text-muted-foreground">Teléfono:</span> {person.phone}</p>}
                            {person.whatsapp && <p><span className="text-muted-foreground">WhatsApp:</span> {person.whatsapp}</p>}
                            {person.company && <p><span className="text-muted-foreground">Empresa:</span> {person.company}{person.job_title ? ` · ${person.job_title}` : ''}</p>}
                            {person.website && <p><span className="text-muted-foreground">Web:</span> {person.website}</p>}
                            {(person.city || person.country) && <p><span className="text-muted-foreground">Ubicación:</span> {[person.city, person.country].filter(Boolean).join(', ')}</p>}
                            {person.preferred_contact_channel && <p><span className="text-muted-foreground">Canal preferido:</span> {person.preferred_contact_channel}</p>}
                            {person.how_we_met && <p><span className="text-muted-foreground">Cómo se conocieron:</span> {person.how_we_met}</p>}
                            {person.notes && <p className="whitespace-pre-line"><span className="text-muted-foreground">Notas:</span> {person.notes}</p>}
                        </CardContent>
                    </Card>

                    <div className="flex flex-col gap-4 lg:col-span-2">
                        {/* Task 7 inserta aquí la sección de fechas clave. */}
                        {/* Task 8 inserta aquí la sección de redes. */}
                        {/* Task 6 inserta aquí la sección de historial. */}
                    </div>
                </div>
            </div>
        </PeopleLayout>
    );
}
```

`resources/js/pages/people/Timeline.tsx`:

```tsx
import { Head, Link } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import React from 'react';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

function monthLabel(value: string): string {
    return new Date(value).toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });
}

export default function PeopleTimeline({ interactions }: any) {
    const groups = interactions.data.reduce((acc: Record<string, any[]>, item: any) => {
        const key = monthLabel(item.occurred_at);
        (acc[key] ||= []).push(item);
        return acc;
    }, {});

    return (
        <PeopleLayout>
            <Head title="Historial social" />
            <div className="flex h-full flex-col gap-6 p-4 md:p-6 animate-in fade-in duration-700">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-white">Historial social</h1>
                    <p className="text-muted-foreground">Todas tus interacciones, agrupadas por mes.</p>
                </div>

                {interactions.data.length === 0 ? (
                    <div className="text-center py-12 text-muted-foreground italic border border-dashed border-border rounded-xl bg-card">
                        Todavía no registraste interacciones.
                    </div>
                ) : Object.entries(groups).map(([month, items]) => (
                    <div key={month} className="flex flex-col gap-2">
                        <h2 className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">{month}</h2>
                        <div className="flex flex-col gap-1 bg-card border border-border rounded-xl overflow-hidden">
                            {(items as any[]).map((item) => (
                                <div key={item.id} className="flex items-center gap-3 px-4 py-3 border-b border-border last:border-b-0">
                                    <CalendarClock className="h-4 w-4 text-primary" />
                                    <div className="flex-1">
                                        <p className="text-sm text-white font-medium">{item.title || 'Interacción'}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(item.occurred_at).toLocaleDateString('es-ES')} · {item.channel}
                                        </p>
                                    </div>
                                    {item.person && (
                                        <Link href={people.show(item.person.id).url} className="text-xs font-bold text-primary hover:underline">
                                            {item.person.first_name} {item.person.last_name}
                                        </Link>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                ))}

                {(interactions.prev_page_url || interactions.next_page_url) && (
                    <div className="flex items-center justify-end gap-2">
                        {interactions.prev_page_url && <Link className="text-sm text-primary" href={interactions.prev_page_url}>Anterior</Link>}
                        {interactions.next_page_url && <Link className="text-sm text-primary" href={interactions.next_page_url}>Siguiente</Link>}
                    </div>
                )}
            </div>
        </PeopleLayout>
    );
}
```

- [ ] **Step 5: Add the sidebar group**

En `resources/js/components/app-sidebar.tsx`: importar `Contact` y `Cake` de `lucide-react`, definir

```tsx
const peopleNavItems: NavItem[] = [
    { title: 'Personas', href: '/people', icon: Contact },
    { title: 'Historial', href: '/people/timeline', icon: Clock }, // usar Clock o CalendarClock de lucide
    { title: 'Calendario', href: '/people/calendar', icon: Cake },
];
```

y agregar el grupo después del grupo "Personal" (mismo patrón que `personalNavItems`, con `SidebarGroupLabel` "Personas").

- [ ] **Step 6: Generate Wayfinder routes, build and run tests**

```bash
npm run build
php artisan test --compact tests/Feature/People/PeopleWebTest.php
```
Expected: PASS (5 tests).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add routes/people.php routes/web.php app/Policies/PersonPolicy.php app/Http/Controllers/People resources/js/layouts/people-layout.tsx resources/js/pages/people resources/js/components/app-sidebar.tsx resources/js/routes/people resources/js/actions/App/Http/Controllers/People tests/Feature/People/PeopleWebTest.php
git commit -m "feat(people): web list, profile and timeline with policy and routes"
```

---

### Task 5: Web — alta/edición y avatar

**Files:**
- Create: `resources/js/pages/people/Form.tsx`
- Modify: `tests/Feature/People/PeopleWebTest.php` (nuevos `it(`)
- Modify: `app/Http/Controllers/People/PersonController.php` (ya incluye store/update/destroy/avatar de la Tarea 4)

**Interfaces:**
- Consumes: `PersonController` (Task 4), `PeopleService`.
- Produces: página `people/Form` con props `person?`, `closenessOptions`, `relationshipOptions`, `channelOptions`.

- [ ] **Step 1: Add failing tests**

Agregar a `tests/Feature/People/PeopleWebTest.php`:

```php
it('creates a person from the web form', function () {
    $this->post('/people', [
        'first_name' => 'Ana',
        'last_name' => 'Gómez',
        'closeness' => 'close',
        'is_favorite' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('people', [
        'first_name' => 'Ana',
        'user_id' => $this->user->id,
        'is_favorite' => true,
    ]);
});

it('updates and deletes a person', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->put("/people/{$person->id}", [
        'first_name' => 'Ana Renombrada',
        'closeness' => 'inner_circle',
    ])->assertRedirect(route('people.show', $person));

    expect($person->fresh()->first_name)->toBe('Ana Renombrada');

    $this->delete("/people/{$person->id}")->assertRedirect(route('people.index'));

    expect(Person::find($person->id))->toBeNull();
});

it('uploads an avatar through medialibrary', function () {
    Storage::fake('public');
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->post("/people/{$person->id}/avatar", [
        'avatar' => UploadedFile::fake()->image('ana.jpg'),
    ])->assertRedirect();

    expect($person->fresh()->getMedia('avatar'))->toHaveCount(1)
        ->and($person->fresh()->avatar_url)->not->toBeNull();
});

it('rejects an invalid payload', function () {
    $this->post('/people', ['first_name' => '', 'closeness' => 'nope'])
        ->assertSessionHasErrors(['first_name', 'closeness']);
});
```

Imports a agregar en el test: `use Illuminate\Http\UploadedFile; use Illuminate\Support\Facades\Storage;`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/People/PeopleWebTest.php`
Expected: FAIL en los nuevos tests (avatar sube pero `people/Form` no existe → el create test falla por página; los POST pueden pasar ya que la Tarea 4 implementó el controller; el test de Form fallará en el GET).

- [ ] **Step 3: Write the form page**

`resources/js/pages/people/Form.tsx`:

```tsx
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Upload } from 'lucide-react';
import React, { useRef, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

const CLOSENESS_LABELS: Record<string, string> = {
    inner_circle: 'Círculo íntimo', close: 'Cercano', friend: 'Amigo', acquaintance: 'Conocido',
};

export default function PersonForm({ person, closenessOptions, relationshipOptions, channelOptions }: any) {
    const isEditing = !!person;
    const avatarInput = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm({
        first_name: person?.first_name || '',
        last_name: person?.last_name || '',
        nickname: person?.nickname || '',
        birthday: person?.birthday?.slice(0, 10) || '',
        email: person?.email || '',
        phone: person?.phone || '',
        whatsapp: person?.whatsapp || '',
        address: person?.address || '',
        city: person?.city || '',
        country: person?.country || '',
        company: person?.company || '',
        job_title: person?.job_title || '',
        website: person?.website || '',
        how_we_met: person?.how_we_met || '',
        closeness: person?.closeness || 'friend',
        relationship_status: person?.relationship_status || '',
        preferred_contact_channel: person?.preferred_contact_channel || '',
        is_favorite: person?.is_favorite || false,
        is_archived: person?.is_archived || false,
        notes: person?.notes || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) put(people.update(person.id).url);
        else post(people.store().url);
    };

    const uploadAvatar = (file: File) => {
        const formData = new FormData();
        formData.append('avatar', file);
        setUploading(true);
        router.post(people.avatar.store(person.id).url, formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setUploading(false),
        });
    };

    return (
        <PeopleLayout>
            <Head title={isEditing ? `Editar ${person.full_name}` : 'Nueva persona'} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={isEditing ? people.show(person.id).url : people.index().url}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold tracking-tight text-white">
                        {isEditing ? 'Editar persona' : 'Nueva persona'}
                    </h1>
                </div>

                {isEditing && (
                    <div className="flex items-center gap-4">
                        <Avatar className="h-16 w-16">
                            {person.avatar_url && <AvatarImage src={person.avatar_url} alt={person.full_name} />}
                            <AvatarFallback className="bg-primary/20 text-primary text-xl font-black">
                                {person.first_name?.[0]?.toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <input
                            ref={avatarInput}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => e.target.files?.[0] && uploadAvatar(e.target.files[0])}
                        />
                        <Button type="button" variant="outline" disabled={uploading} onClick={() => avatarInput.current?.click()}>
                            <Upload className="mr-2 h-4 w-4" /> {uploading ? 'Subiendo...' : 'Cambiar avatar'}
                        </Button>
                        {person.avatar_url && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive"
                                onClick={() => router.delete(people.avatar.destroy(person.id).url, { preserveScroll: true })}
                            >
                                Quitar
                            </Button>
                        )}
                    </div>
                )}

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Datos</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="first_name">Nombre *</Label>
                                <Input id="first_name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                                {errors.first_name && <p className="text-xs text-destructive">{errors.first_name}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="last_name">Apellido</Label>
                                <Input id="last_name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="nickname">Alias</Label>
                                <Input id="nickname" value={data.nickname} onChange={(e) => setData('nickname', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="birthday">Cumpleaños</Label>
                                <Input id="birthday" type="date" value={data.birthday} onChange={(e) => setData('birthday', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label>Cercanía *</Label>
                                <Select value={data.closeness} onValueChange={(value) => setData('closeness', value)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {closenessOptions.map((value: string) => (
                                            <SelectItem key={value} value={value}>{CLOSENESS_LABELS[value] ?? value}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.closeness && <p className="text-xs text-destructive">{errors.closeness}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Estado de relación</Label>
                                <Select value={data.relationship_status || 'none'} onValueChange={(value) => setData('relationship_status', value === 'none' ? '' : value)}>
                                    <SelectTrigger><SelectValue placeholder="Sin dato" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin dato</SelectItem>
                                        {relationshipOptions.map((value: string) => (
                                            <SelectItem key={value} value={value}>{value.replace(/_/g, ' ')}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label>Canal preferido</Label>
                                <Select value={data.preferred_contact_channel || 'none'} onValueChange={(value) => setData('preferred_contact_channel', value === 'none' ? '' : value)}>
                                    <SelectTrigger><SelectValue placeholder="Sin dato" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Sin dato</SelectItem>
                                        {channelOptions.map((value: string) => (
                                            <SelectItem key={value} value={value}>{value.replace(/_/g, ' ')}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <label className="flex items-center gap-2 text-sm text-white/90">
                                <Checkbox checked={data.is_favorite} onCheckedChange={(checked) => setData('is_favorite', !!checked)} />
                                Favorito
                            </label>
                            {isEditing && (
                                <label className="flex items-center gap-2 text-sm text-white/90">
                                    <Checkbox checked={data.is_archived} onCheckedChange={(checked) => setData('is_archived', !!checked)} />
                                    Archivada
                                </label>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Contacto</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                {errors.email && <p className="text-xs text-destructive">{errors.email}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="phone">Teléfono</Label>
                                <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="whatsapp">WhatsApp</Label>
                                <Input id="whatsapp" value={data.whatsapp} onChange={(e) => setData('whatsapp', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="website">Sitio web</Label>
                                <Input id="website" value={data.website} onChange={(e) => setData('website', e.target.value)} />
                                {errors.website && <p className="text-xs text-destructive">{errors.website}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="city">Ciudad</Label>
                                <Input id="city" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="country">País</Label>
                                <Input id="country" value={data.country} onChange={(e) => setData('country', e.target.value)} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="bg-card border-border">
                        <CardHeader><CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Contexto</CardTitle></CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="company">Empresa</Label>
                                    <Input id="company" value={data.company} onChange={(e) => setData('company', e.target.value)} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="job_title">Puesto</Label>
                                    <Input id="job_title" value={data.job_title} onChange={(e) => setData('job_title', e.target.value)} />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="how_we_met">Cómo se conocieron</Label>
                                <Textarea id="how_we_met" value={data.how_we_met} onChange={(e) => setData('how_we_met', e.target.value)} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notas</Label>
                                <Textarea id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" asChild>
                            <Link href={isEditing ? people.show(person.id).url : people.index().url}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing} className="bg-primary text-white font-bold">
                            {isEditing ? 'Guardar cambios' : 'Crear persona'}
                        </Button>
                    </div>
                </form>
            </div>
        </PeopleLayout>
    );
}
```

- [ ] **Step 4: Run tests, build, pint, commit**

```bash
npm run build
php artisan test --compact tests/Feature/People/PeopleWebTest.php
vendor/bin/pint --dirty --format agent
git add resources/js/pages/people/Form.tsx resources/js/routes/people resources/js/actions tests/Feature/People/PeopleWebTest.php
git commit -m "feat(people): person create/edit form with avatar upload"
```

---

### Task 6: Web — interacciones (alta rápida y timeline en la ficha)

**Files:**
- Modify: `app/Http/Controllers/People/PersonInteractionController.php`
- Modify: `resources/js/pages/people/Show.tsx` (sección historial)
- Modify: `tests/Feature/People/PeopleWebTest.php`

**Interfaces:**
- Consumes: `PeopleService::logInteraction/deleteInteraction`.
- Produces: rutas `people.contacted`, `people.interactions.store`, `people.interactions.destroy`.

- [ ] **Step 1: Add failing tests**

```php
it('logs an interaction and refreshes last_contacted_at', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $occurredAt = now()->subDay()->startOfMinute();

    $this->post("/people/{$person->id}/interactions", [
        'channel' => 'call',
        'occurred_at' => $occurredAt->toDateTimeString(),
        'title' => 'Llamada',
    ])->assertRedirect();

    $this->assertDatabaseHas('person_interactions', [
        'person_id' => $person->id,
        'channel' => 'call',
    ]);
    expect($person->fresh()->last_contacted_at->equalTo($occurredAt))->toBeTrue();
});

it('quick logs a contact with the default channel', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->post("/people/{$person->id}/contacted")->assertRedirect();

    $this->assertDatabaseHas('person_interactions', [
        'person_id' => $person->id,
        'channel' => 'message',
    ]);
});

it('deletes an interaction owned by the user', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $interaction = PersonInteraction::factory()->create([
        'user_id' => $this->user->id,
        'person_id' => $person->id,
    ]);

    $this->delete("/people/interactions/{$interaction->id}")->assertRedirect();

    expect(PersonInteraction::find($interaction->id))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/People/PeopleWebTest.php`
Expected: FAIL — el controller stub no tiene métodos.

- [ ] **Step 3: Implement the controller**

`app/Http/Controllers/People/PersonInteractionController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonInteraction;
use App\People\Enums\InteractionChannel;
use App\Services\People\PeopleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonInteractionController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $validated = $request->validate([
            'channel' => ['required', Rule::enum(InteractionChannel::class)],
            'occurred_at' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $this->people->logInteraction($request->user(), $person, $validated);

        return back()->with('success', 'Interacción registrada.');
    }

    public function quickLog(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $validated = $request->validate([
            'channel' => ['nullable', Rule::enum(InteractionChannel::class)],
        ]);

        $this->people->logInteraction($request->user(), $person, [
            'channel' => $validated['channel'] ?? InteractionChannel::Message->value,
            'occurred_at' => now(),
            'title' => 'Contacto rápido',
        ]);

        return back()->with('success', 'Contacto registrado.');
    }

    public function destroy(Request $request, PersonInteraction $interaction): RedirectResponse
    {
        abort_if($interaction->user_id !== $request->user()->id, 403);

        $this->people->deleteInteraction($request->user(), $interaction);

        return back()->with('success', 'Interacción eliminada.');
    }
}
```

- [ ] **Step 4: Add the history section to Show.tsx**

En `resources/js/pages/people/Show.tsx`, en el bloque comentado `{/* Task 6 inserta aquí la sección de historial. */}`, reemplazar por:

```tsx
<Card className="bg-card border-border">
    <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">
            Historial de contacto
        </CardTitle>
        <Dialog open={logOpen} onOpenChange={setLogOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline"><Plus className="mr-2 h-4 w-4" /> Registrar</Button>
            </DialogTrigger>
            <DialogContent className="bg-card border-border">
                <DialogHeader><DialogTitle>Registrar interacción</DialogTitle></DialogHeader>
                <form onSubmit={submitInteraction} className="flex flex-col gap-4">
                    <div className="grid gap-2">
                        <Label>Canal</Label>
                        <Select value={form.data.channel} onValueChange={(value) => form.setData('channel', value)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {channelOptions.map((value: string) => (
                                    <SelectItem key={value} value={value}>{value.replace(/_/g, ' ')}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.channel && <p className="text-xs text-destructive">{form.errors.channel}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="occurred_at">Fecha y hora</Label>
                        <Input
                            id="occurred_at"
                            type="datetime-local"
                            value={form.data.occurred_at}
                            onChange={(e) => form.setData('occurred_at', e.target.value)}
                        />
                        {form.errors.occurred_at && <p className="text-xs text-destructive">{form.errors.occurred_at}</p>}
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="title">Título</Label>
                        <Input id="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notas</Label>
                        <Textarea id="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={form.processing} className="bg-primary text-white font-bold">Guardar</Button>
                </form>
            </DialogContent>
        </Dialog>
    </CardHeader>
    <CardContent className="flex flex-col gap-1">
        {interactions.data.length === 0 ? (
            <p className="py-6 text-center text-sm text-muted-foreground italic">Todavía no hay interacciones.</p>
        ) : interactions.data.map((item: any) => (
            <div key={item.id} className="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/5">
                <MessageCircle className="h-4 w-4 text-primary" />
                <div className="flex-1">
                    <p className="text-sm font-medium text-white">{item.title || 'Interacción'}</p>
                    <p className="text-xs text-muted-foreground">
                        {new Date(item.occurred_at).toLocaleDateString('es-ES')} · {item.channel.replace(/_/g, ' ')}
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 text-destructive"
                    onClick={() => router.delete(people.interactions.destroy(item.id).url, { preserveScroll: true })}
                >
                    <Trash className="h-3.5 w-3.5" />
                </Button>
            </div>
        ))}
        {(interactions.prev_page_url || interactions.next_page_url) && (
            <div className="flex justify-end gap-3 pt-2 text-xs">
                {interactions.prev_page_url && <Link className="text-primary" href={interactions.prev_page_url}>Anterior</Link>}
                {interactions.next_page_url && <Link className="text-primary" href={interactions.next_page_url}>Siguiente</Link>}
            </div>
        )}
    </CardContent>
</Card>
```

Y en el cuerpo del componente (antes del `return`), agregar el estado y el form:

```tsx
const [logOpen, setLogOpen] = useState(false);
const form = useForm({
    channel: 'message',
    occurred_at: new Date().toISOString().slice(0, 16),
    title: '',
    notes: '',
});
const submitInteraction = (e: React.FormEvent) => {
    e.preventDefault();
    form.post(people.interactions.store(person.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            setLogOpen(false);
        },
    });
};
```

Imports nuevos en Show.tsx: `router, useForm` desde `@inertiajs/react`; `Plus`, `Trash` de lucide; `useState` de react; `Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger` y `Textarea` desde `@/components/ui/...`.

- [ ] **Step 5: Run tests, build, pint, commit**

```bash
npm run build
php artisan test --compact tests/Feature/People/PeopleWebTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/People/PersonInteractionController.php resources/js/pages/people/Show.tsx resources/js/routes/people tests/Feature/People/PeopleWebTest.php
git commit -m "feat(people): interaction quick log and timeline on the person profile"
```

---

### Task 7: Web — fechas clave y calendario

**Files:**
- Create: `resources/js/pages/people/Calendar.tsx`
- Modify: `app/Http/Controllers/People/PersonKeyDateController.php`
- Modify: `resources/js/pages/people/Show.tsx` (sección fechas clave)
- Modify: `tests/Feature/People/PeopleWebTest.php`

**Interfaces:**
- Consumes: `PersonKeyDate`, `PeopleService`, `PersonController::calendar` (Task 4).
- Produces: rutas `people.key-dates.store/update/destroy`; página `people/Calendar` con props `people`, `keyDates`.

- [ ] **Step 1: Add failing tests**

```php
it('creates, updates and deletes key dates', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->post("/people/{$person->id}/key-dates", [
        'type' => 'anniversary',
        'label' => 'Aniversario',
        'date' => now()->addDays(10)->toDateString(),
        'is_recurring_annually' => true,
    ])->assertRedirect();

    $keyDate = $person->keyDates()->firstOrFail();

    $this->patch("/people/key-dates/{$keyDate->id}", ['remind_days_before' => 14])->assertRedirect();
    expect($keyDate->fresh()->remind_days_before)->toBe(14);

    $this->delete("/people/key-dates/{$keyDate->id}")->assertRedirect();
    expect(PersonKeyDate::find($keyDate->id))->toBeNull();
});

it('renders the key dates calendar', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id, 'birthday' => now()->addDays(5)]);

    $this->get('/people/calendar')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('people/Calendar')
            ->has('people', 1)
            ->has('keyDates', 0));
});
```

Import nuevo: `use App\Models\PersonKeyDate;`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/People/PeopleWebTest.php`
Expected: FAIL — stub sin métodos / página no existe.

- [ ] **Step 3: Implement the controller**

`app/Http/Controllers/People/PersonKeyDateController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonKeyDate;
use App\People\Enums\KeyDateType;
use App\Services\People\PeopleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonKeyDateController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $this->people->addKeyDate($request->user(), $person, $request->validate($this->rules()));

        return back()->with('success', 'Fecha clave agregada.');
    }

    public function update(Request $request, PersonKeyDate $keyDate): RedirectResponse
    {
        $this->authorize('update', $keyDate->person);

        $this->people->updateKeyDate($request->user(), $keyDate, $request->validate($this->rules()));

        return back()->with('success', 'Fecha clave actualizada.');
    }

    public function destroy(Request $request, PersonKeyDate $keyDate): RedirectResponse
    {
        $this->authorize('update', $keyDate->person);

        $this->people->deleteKeyDate($request->user(), $keyDate);

        return back()->with('success', 'Fecha clave eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(KeyDateType::class)],
            'label' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'remind_days_before' => ['nullable', 'integer', 'min:0', 'max:90'],
            'is_recurring_annually' => ['boolean'],
        ];
    }
}
```

- [ ] **Step 4: Add the key dates section to Show.tsx**

En el bloque `{/* Task 7 inserta aquí la sección de fechas clave. */}`:

```tsx
<Card className="bg-card border-border">
    <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Fechas clave</CardTitle>
        <Button size="sm" variant="outline" onClick={() => setKeyDateOpen(true)}>
            <Plus className="mr-2 h-4 w-4" /> Agregar
        </Button>
    </CardHeader>
    <CardContent className="flex flex-col gap-2">
        {person.key_dates.length === 0 ? (
            <p className="py-4 text-center text-sm text-muted-foreground italic">Sin fechas clave.</p>
        ) : person.key_dates.map((keyDate: any) => (
            <div key={keyDate.id} className="flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-white/5">
                <Cake className="h-4 w-4 text-primary" />
                <div className="flex-1">
                    <p className="text-sm font-medium text-white">{keyDate.label || keyDate.type.replace(/_/g, ' ')}</p>
                    <p className="text-xs text-muted-foreground">
                        {keyDate.date.slice(0, 10)} · avisa {keyDate.remind_days_before} días antes
                        {keyDate.is_recurring_annually ? ' · cada año' : ''}
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 text-destructive"
                    onClick={() => router.delete(people.keyDates.destroy(keyDate.id).url, { preserveScroll: true })}
                >
                    <Trash className="h-3.5 w-3.5" />
                </Button>
            </div>
        ))}
    </CardContent>

    <Dialog open={keyDateOpen} onOpenChange={setKeyDateOpen}>
        <DialogContent className="bg-card border-border">
            <DialogHeader><DialogTitle>Nueva fecha clave</DialogTitle></DialogHeader>
            <form onSubmit={submitKeyDate} className="flex flex-col gap-4">
                <div className="grid gap-2">
                    <Label>Tipo</Label>
                    <Select value={keyDateForm.data.type} onValueChange={(value) => keyDateForm.setData('type', value)}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            {['birthday', 'anniversary', 'graduation', 'memorial', 'custom'].map((value) => (
                                <SelectItem key={value} value={value}>{value}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="kd_label">Etiqueta</Label>
                    <Input id="kd_label" value={keyDateForm.data.label} onChange={(e) => keyDateForm.setData('label', e.target.value)} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="kd_date">Fecha</Label>
                    <Input id="kd_date" type="date" value={keyDateForm.data.date} onChange={(e) => keyDateForm.setData('date', e.target.value)} />
                    {keyDateForm.errors.date && <p className="text-xs text-destructive">{keyDateForm.errors.date}</p>}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="kd_remind">Días de aviso</Label>
                    <Input id="kd_remind" type="number" min={0} max={90} value={keyDateForm.data.remind_days_before}
                        onChange={(e) => keyDateForm.setData('remind_days_before', e.target.value)} />
                </div>
                <label className="flex items-center gap-2 text-sm text-white/90">
                    <Checkbox checked={keyDateForm.data.is_recurring_annually}
                        onCheckedChange={(checked) => keyDateForm.setData('is_recurring_annually', !!checked)} />
                    Se repite cada año
                </label>
                <Button type="submit" disabled={keyDateForm.processing} className="bg-primary text-white font-bold">Guardar</Button>
            </form>
        </DialogContent>
    </Dialog>
</Card>
```

Estado y submit a agregar en el componente:

```tsx
const [keyDateOpen, setKeyDateOpen] = useState(false);
const keyDateForm = useForm({
    type: 'custom',
    label: '',
    date: '',
    remind_days_before: '7',
    is_recurring_annually: true,
});
const submitKeyDate = (e: React.FormEvent) => {
    e.preventDefault();
    keyDateForm.post(people.keyDates.store(person.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            keyDateForm.reset();
            setKeyDateOpen(false);
        },
    });
};
```

Imports nuevos: `Cake` de lucide, `Checkbox` de `@/components/ui/checkbox`.

- [ ] **Step 5: Write the calendar page**

`resources/js/pages/people/Calendar.tsx`:

```tsx
import { Head, Link } from '@inertiajs/react';
import { Cake, ChevronLeft, ChevronRight } from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import PeopleLayout from '@/layouts/people-layout';
import people from '@/routes/people';

interface Entry {
    id: string;
    label: string;
    personId: number;
    personName: string;
    recurring: boolean;
    date: string;
}

function nextOccurrence(date: string, recurring: boolean): Date | null {
    const original = new Date(`${date.slice(0, 10)}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let candidate = new Date(original);

    if (recurring) {
        candidate = new Date(today.getFullYear(), original.getMonth(), original.getDate());
        if (candidate < today) candidate = new Date(today.getFullYear() + 1, original.getMonth(), original.getDate());
    }

    return candidate >= today ? candidate : null;
}

export default function PeopleCalendar({ people: peopleList, keyDates }: any) {
    const [current, setCurrent] = useState(() => new Date());
    const year = current.getFullYear();
    const month = current.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const startOffset = firstDay === 0 ? 6 : firstDay - 1;

    const days: (number | null)[] = Array(startOffset).fill(null);
    for (let day = 1; day <= daysInMonth; day++) days.push(day);
    while (days.length % 7 !== 0) days.push(null);

    const entries: Entry[] = [
        ...peopleList.filter((p: any) => p.birthday).map((p: any) => ({
            id: `person-${p.id}`,
            label: `Cumpleaños de ${p.first_name}`,
            personId: p.id,
            personName: p.first_name,
            recurring: true,
            date: p.birthday.slice(0, 10),
        })),
        ...keyDates.map((k: any) => ({
            id: `key-${k.id}`,
            label: k.label || k.type,
            personId: k.person?.id,
            personName: `${k.person?.first_name ?? ''} ${k.person?.last_name ?? ''}`.trim(),
            recurring: k.is_recurring_annually,
            date: k.date.slice(0, 10),
        })),
    ];

    const entriesForDay = (day: number) => {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        return entries.filter((entry) => {
            const occurrence = nextOccurrence(entry.date, entry.recurring);
            if (!occurrence) return false;
            return occurrence.toISOString().slice(0, 10) === dateStr;
        });
    };

    const upcoming = entries
        .map((entry) => ({ entry, next: nextOccurrence(entry.date, entry.recurring) }))
        .filter((row): row is { entry: Entry; next: Date } => row.next !== null)
        .sort((a, b) => a.next.getTime() - b.next.getTime())
        .slice(0, 8);

    const monthName = current.toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });

    return (
        <PeopleLayout>
            <Head title="Calendario de fechas" />
            <div className="grid gap-6 p-4 md:p-6 lg:grid-cols-[2fr_1fr] animate-in fade-in duration-700">
                <div className="flex flex-col gap-4 bg-card border border-border rounded-xl p-4">
                    <div className="flex items-center justify-between">
                        <h1 className="text-lg font-bold capitalize text-white">{monthName}</h1>
                        <div className="flex gap-1">
                            <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => setCurrent(new Date(year, month - 1, 1))}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => setCurrent(new Date())}>Hoy</Button>
                            <Button variant="outline" size="icon" className="h-8 w-8" onClick={() => setCurrent(new Date(year, month + 1, 1))}>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <div className="grid grid-cols-7 gap-px bg-border rounded-lg overflow-hidden">
                        {['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].map((day) => (
                            <div key={day} className="bg-muted py-2 text-center text-[10px] font-black uppercase tracking-widest text-muted-foreground">{day}</div>
                        ))}
                        {days.map((day, index) => (
                            <div key={index} className={`min-h-24 p-1 flex flex-col gap-1 ${day == null ? 'bg-muted/30' : 'bg-card'}`}>
                                {day != null && (
                                    <>
                                        <span className="text-xs font-bold h-6 w-6 flex items-center justify-center rounded-full text-white/80">{day}</span>
                                        {entriesForDay(day).map((entry) => (
                                            <Link
                                                key={entry.id}
                                                href={people.show(entry.personId).url}
                                                className="text-[10px] leading-tight truncate px-1 py-0.5 rounded bg-primary/20 text-primary font-medium hover:bg-primary/30"
                                            >
                                                {entry.label}
                                            </Link>
                                        ))}
                                    </>
                                )}
                            </div>
                        ))}
                    </div>
                </div>

                <div className="flex flex-col gap-3">
                    <h2 className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Próximas fechas</h2>
                    {upcoming.length === 0 ? (
                        <div className="text-center py-12 text-muted-foreground italic border border-dashed border-border rounded-xl bg-card">
                            Sin fechas cargadas.
                        </div>
                    ) : upcoming.map(({ entry, next }) => (
                        <Link
                            key={entry.id}
                            href={entry.personId ? people.show(entry.personId).url : people.calendar().url}
                            className="flex items-center gap-3 bg-card border border-border rounded-xl px-4 py-3 hover:border-primary/40"
                        >
                            <Cake className="h-4 w-4 text-primary" />
                            <div className="flex-1">
                                <p className="text-sm font-medium text-white">{entry.label}</p>
                                <p className="text-xs text-muted-foreground">{entry.personName}</p>
                            </div>
                            <Badge variant="outline" className="border-primary/30 text-primary text-[10px] font-black">
                                {next.toLocaleDateString('es-ES', { day: 'numeric', month: 'short' })}
                            </Badge>
                        </Link>
                    ))}
                </div>
            </div>
        </PeopleLayout>
    );
}
```

- [ ] **Step 6: Run tests, build, pint, commit**

```bash
npm run build
php artisan test --compact tests/Feature/People/PeopleWebTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/People/PersonKeyDateController.php resources/js/pages/people tests/Feature/People/PeopleWebTest.php
git commit -m "feat(people): key dates CRUD and recurring dates calendar"
```

---

### Task 8: Web — redes sociales

**Files:**
- Modify: `app/Http/Controllers/People/PersonSocialController.php`
- Modify: `resources/js/pages/people/Show.tsx` (sección redes)
- Modify: `tests/Feature/People/PeopleWebTest.php`

**Interfaces:**
- Consumes: `PeopleService::addSocial/updateSocial/deleteSocial`.
- Produces: rutas `people.socials.store/update/destroy`.

- [ ] **Step 1: Add failing tests**

```php
it('adds and deletes person socials', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->post("/people/{$person->id}/socials", [
        'network' => 'instagram',
        'handle' => '@ana',
        'url' => 'https://instagram.com/ana',
    ])->assertRedirect();

    $social = $person->socials()->firstOrFail();

    $this->patch("/people/socials/{$social->id}", ['handle' => '@ana.gomez'])->assertRedirect();
    expect($social->fresh()->handle)->toBe('@ana.gomez');

    $this->delete("/people/socials/{$social->id}")->assertRedirect();
    expect($person->socials()->count())->toBe(0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/People/PeopleWebTest.php`
Expected: FAIL — stub sin métodos.

- [ ] **Step 3: Implement the controller**

`app/Http/Controllers/People/PersonSocialController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\People;

use App\Http\Controllers\Controller;
use App\Models\Person;
use App\Models\PersonSocial;
use App\Services\People\PeopleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PersonSocialController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function store(Request $request, Person $person): RedirectResponse
    {
        $this->authorize('update', $person);

        $this->people->addSocial($request->user(), $person, $request->validate($this->rules()));

        return back()->with('success', 'Red agregada.');
    }

    public function update(Request $request, PersonSocial $social): RedirectResponse
    {
        $this->authorize('update', $social->person);

        $this->people->updateSocial($request->user(), $social, $request->validate($this->rules()));

        return back()->with('success', 'Red actualizada.');
    }

    public function destroy(Request $request, PersonSocial $social): RedirectResponse
    {
        $this->authorize('update', $social->person);

        $this->people->deleteSocial($request->user(), $social);

        return back()->with('success', 'Red eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'network' => ['required', 'string', 'max:50'],
            'handle' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Add the socials section to Show.tsx**

En el bloque `{/* Task 8 inserta aquí la sección de redes. */}`:

```tsx
<Card className="bg-card border-border">
    <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="text-sm font-black uppercase tracking-widest text-muted-foreground">Redes</CardTitle>
        <Button size="sm" variant="outline" onClick={() => setSocialOpen(true)}>
            <Plus className="mr-2 h-4 w-4" /> Agregar
        </Button>
    </CardHeader>
    <CardContent className="flex flex-wrap gap-2">
        {person.socials.length === 0 ? (
            <p className="w-full py-4 text-center text-sm text-muted-foreground italic">Sin redes cargadas.</p>
        ) : person.socials.map((social: any) => (
            <div key={social.id} className="flex items-center gap-2 rounded-full border border-border bg-background px-3 py-1.5">
                <span className="text-xs font-bold text-white">{social.network}</span>
                {social.handle && <span className="text-xs text-muted-foreground">{social.handle}</span>}
                <button
                    type="button"
                    className="text-destructive"
                    onClick={() => router.delete(people.socials.destroy(social.id).url, { preserveScroll: true })}
                >
                    <X className="h-3 w-3" />
                </button>
            </div>
        ))}
    </CardContent>

    <Dialog open={socialOpen} onOpenChange={setSocialOpen}>
        <DialogContent className="bg-card border-border">
            <DialogHeader><DialogTitle>Nueva red</DialogTitle></DialogHeader>
            <form onSubmit={submitSocial} className="flex flex-col gap-4">
                <div className="grid gap-2">
                    <Label htmlFor="network">Red</Label>
                    <Input id="network" list="social-networks" value={socialForm.data.network}
                        onChange={(e) => socialForm.setData('network', e.target.value)} />
                    <datalist id="social-networks">
                        {['instagram', 'x', 'facebook', 'tiktok', 'linkedin', 'github', 'telegram', 'whatsapp'].map((network) => (
                            <option key={network} value={network} />
                        ))}
                    </datalist>
                    {socialForm.errors.network && <p className="text-xs text-destructive">{socialForm.errors.network}</p>}
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="handle">Usuario</Label>
                    <Input id="handle" value={socialForm.data.handle} onChange={(e) => socialForm.setData('handle', e.target.value)} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="url">URL</Label>
                    <Input id="url" value={socialForm.data.url} onChange={(e) => socialForm.setData('url', e.target.value)} />
                    {socialForm.errors.url && <p className="text-xs text-destructive">{socialForm.errors.url}</p>}
                </div>
                <Button type="submit" disabled={socialForm.processing} className="bg-primary text-white font-bold">Guardar</Button>
            </form>
        </DialogContent>
    </Dialog>
</Card>
```

Estado y submit:

```tsx
const [socialOpen, setSocialOpen] = useState(false);
const socialForm = useForm({ network: '', handle: '', url: '' });
const submitSocial = (e: React.FormEvent) => {
    e.preventDefault();
    socialForm.post(people.socials.store(person.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            socialForm.reset();
            setSocialOpen(false);
        },
    });
};
```

Import nuevo: `X` de lucide.

- [ ] **Step 5: Run tests, build, pint, commit**

```bash
npm run build
php artisan test --compact tests/Feature/People/PeopleWebTest.php
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/People/PersonSocialController.php resources/js/pages/people/Show.tsx resources/js/routes/people tests/Feature/People/PeopleWebTest.php
git commit -m "feat(people): social networks section on the person profile"
```

---

### Task 9: API v1 (Sanctum, requests, resources)

**Files:**
- Create: `app/Http/Controllers/Api/V1/PersonController.php`
- Create: `app/Http/Controllers/Api/V1/PersonInteractionController.php`
- Create: `app/Http/Controllers/Api/V1/PersonKeyDateController.php`
- Create: `app/Http/Controllers/Api/V1/PersonSocialController.php`
- Create: `app/Http/Requests/Api/StorePersonRequest.php`, `UpdatePersonRequest.php`
- Create: `app/Http/Requests/Api/StorePersonInteractionRequest.php`
- Create: `app/Http/Requests/Api/StorePersonKeyDateRequest.php`, `UpdatePersonKeyDateRequest.php`
- Create: `app/Http/Requests/Api/StorePersonSocialRequest.php`, `UpdatePersonSocialRequest.php`
- Create: `app/Http/Resources/PersonResource.php`, `PersonInteractionResource.php`, `PersonKeyDateResource.php`, `PersonSocialResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/PeopleApiTest.php`

**Interfaces:**
- Consumes: `PeopleService` (Task 3), modelos.
- Produces: endpoints `/api/v1/people*` bajo `auth:sanctum`, 201 en store, `paginate(15)`, 403 para ajenos.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Api/PeopleApiTest.php`:

```php
<?php

use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('api-token')->plainTextToken;
    $this->headers = ['Authorization' => 'Bearer '.$this->token];
});

it('lists and searches people scoped to the token user', function () {
    Person::factory()->create(['user_id' => $this->user->id, 'first_name' => 'Ana']);
    Person::factory()->create(['first_name' => 'Intruso']);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/people?search=ana')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.first_name', 'Ana');
});

it('creates a person and validates the payload', function () {
    $this->withHeaders($this->headers)
        ->postJson('/api/v1/people', ['first_name' => 'Ana', 'closeness' => 'close'])
        ->assertCreated()
        ->assertJsonPath('data.first_name', 'Ana');

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/people', ['closeness' => 'nope'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'closeness']);
});

it('shows a person with key dates and socials', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $person->keyDates()->create(['user_id' => $this->user->id, 'type' => 'custom', 'date' => now()->toDateString()]);
    $person->socials()->create(['network' => 'instagram', 'handle' => '@ana']);

    $this->withHeaders($this->headers)
        ->getJson("/api/v1/people/{$person->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.key_dates')
        ->assertJsonCount(1, 'data.socials');
});

it('updates, deletes and protects ownership', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);
    $intruder = Person::factory()->create();

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/people/{$person->id}", ['nickname' => 'Anita'])
        ->assertOk()
        ->assertJsonPath('data.nickname', 'Anita');

    $this->withHeaders($this->headers)
        ->patchJson("/api/v1/people/{$intruder->id}", ['nickname' => 'hack'])
        ->assertForbidden();

    $this->withHeaders($this->headers)
        ->deleteJson("/api/v1/people/{$person->id}")
        ->assertOk();

    expect(Person::find($person->id))->toBeNull();
});

it('manages interactions, key dates and socials', function () {
    $person = Person::factory()->create(['user_id' => $this->user->id]);

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/people/{$person->id}/interactions", [
            'channel' => 'call',
            'occurred_at' => now()->toDateTimeString(),
        ])
        ->assertCreated();

    $this->withHeaders($this->headers)
        ->getJson("/api/v1/people/{$person->id}/interactions")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/people/{$person->id}/key-dates", [
            'type' => 'anniversary',
            'date' => now()->addDays(5)->toDateString(),
        ])
        ->assertCreated();

    $this->withHeaders($this->headers)
        ->postJson("/api/v1/people/{$person->id}/socials", ['network' => 'github', 'handle' => '@ana'])
        ->assertCreated();
});

it('returns upcoming key dates and birthdays', function () {
    $person = Person::factory()->create([
        'user_id' => $this->user->id,
        'birthday' => now()->addDays(4)->toDateString(),
    ]);

    $this->withHeaders($this->headers)
        ->getJson('/api/v1/people/upcoming?days=30')
        ->assertOk()
        ->assertJsonPath('data.0.kind', 'birthday')
        ->assertJsonPath('data.0.person.id', $person->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Api/PeopleApiTest.php`
Expected: FAIL — rutas 404.

- [ ] **Step 3: Write requests and resources**

`app/Http/Requests/Api/StorePersonRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use App\People\Enums\Closeness;
use App\People\Enums\PreferredContactChannel;
use App\People\Enums\RelationshipStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'how_we_met' => ['nullable', 'string'],
            'closeness' => ['required', Rule::enum(Closeness::class)],
            'relationship_status' => ['nullable', Rule::enum(RelationshipStatus::class)],
            'preferred_contact_channel' => ['nullable', Rule::enum(PreferredContactChannel::class)],
            'is_favorite' => ['sometimes', 'boolean'],
            'is_archived' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

`UpdatePersonRequest.php` y los pares de key dates/socials: subclases vacías que heredan reglas.

```php
<?php

namespace App\Http\Requests\Api;

class UpdatePersonRequest extends StorePersonRequest {}
```

`StorePersonInteractionRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use App\People\Enums\InteractionChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::enum(InteractionChannel::class)],
            'occurred_at' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
```

`StorePersonKeyDateRequest.php`:

```php
<?php

namespace App\Http\Requests\Api;

use App\People\Enums\KeyDateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonKeyDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(KeyDateType::class)],
            'label' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'remind_days_before' => ['nullable', 'integer', 'min:0', 'max:90'],
            'is_recurring_annually' => ['boolean'],
        ];
    }
}
```

`UpdatePersonKeyDateRequest extends StorePersonKeyDateRequest {}` y `StorePersonSocialRequest` con `['network' => ['required','string','max:50'], 'handle' => ['nullable','string','max:255'], 'url' => ['nullable','url','max:255']]` + `UpdatePersonSocialRequest extends StorePersonSocialRequest {}`.

`app/Http/Resources/PersonResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'nickname' => $this->nickname,
            'avatar_url' => $this->avatar_url,
            'birthday' => $this->birthday?->toDateString(),
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'website' => $this->website,
            'how_we_met' => $this->how_we_met,
            'closeness' => $this->closeness?->value,
            'relationship_status' => $this->relationship_status?->value,
            'preferred_contact_channel' => $this->preferred_contact_channel?->value,
            'is_favorite' => $this->is_favorite,
            'is_archived' => $this->is_archived,
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'notes' => $this->notes,
            'key_dates' => PersonKeyDateResource::collection($this->whenLoaded('keyDates')),
            'socials' => PersonSocialResource::collection($this->whenLoaded('socials')),
            'interactions' => PersonInteractionResource::collection($this->whenLoaded('interactions')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

`PersonInteractionResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonInteractionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'channel' => $this->channel?->value,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'title' => $this->title,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

`PersonKeyDateResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonKeyDateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'type' => $this->type?->value,
            'label' => $this->display_label,
            'date' => $this->date?->toDateString(),
            'remind_days_before' => $this->remind_days_before,
            'is_recurring_annually' => $this->is_recurring_annually,
        ];
    }
}
```

`PersonSocialResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonSocialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'person_id' => $this->person_id,
            'network' => $this->network,
            'handle' => $this->handle,
            'url' => $this->url,
        ];
    }
}
```

- [ ] **Step 4: Write the API controllers**

`app/Http/Controllers/Api/V1/PersonController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePersonRequest;
use App\Http\Requests\Api\UpdatePersonRequest;
use App\Http\Resources\PersonResource;
use App\Models\Person;
use App\Services\People\PeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Person::query()->where('user_id', $request->user()->id);

        $query->when($request->search, fn ($q, $search) => $q->where(function ($w) use ($search) {
            $w->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nickname', 'like', "%{$search}%");
        }));

        $query->when($request->closeness, fn ($q, $closeness) => $q->where('closeness', $closeness));

        $request->boolean('archived')
            ? $query->where('is_archived', true)
            : $query->where('is_archived', false);

        $query->when($request->boolean('favorite'), fn ($q) => $q->where('is_favorite', true));

        $query->when($request->stale_days, fn ($q, $days) => $q->where(function ($w) use ($days) {
            $w->whereNull('last_contacted_at')
                ->orWhere('last_contacted_at', '<', now()->subDays((int) $days));
        }));

        return PersonResource::collection(
            $query->orderByDesc('is_favorite')->orderBy('first_name')->paginate(15)
        );
    }

    public function store(StorePersonRequest $request): JsonResponse
    {
        $person = $this->people->createPerson($request->user(), $request->validated());

        return (new PersonResource($person))->response()->setStatusCode(201);
    }

    public function show(Request $request, Person $person): PersonResource
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        return new PersonResource($person->load(['keyDates', 'socials']));
    }

    public function update(UpdatePersonRequest $request, Person $person): PersonResource
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        return new PersonResource(
            $this->people->updatePerson($request->user(), $person, $request->validated())
        );
    }

    public function destroy(Request $request, Person $person): JsonResponse
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        $this->people->deletePerson($request->user(), $person);

        return response()->json(['message' => 'Person deleted.']);
    }

    public function upcoming(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 30);

        return response()->json([
            'data' => $this->people->upcoming($request->user(), $days)->all(),
        ]);
    }
}
```

`app/Http/Controllers/Api/V1/PersonInteractionController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\StorePersonInteractionRequest;
use App\Http\Resources\PersonInteractionResource;
use App\Models\Person;
use App\Models\PersonInteraction;
use App\Services\People\PeopleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PersonInteractionController extends Controller
{
    public function __construct(protected PeopleService $people) {}

    public function index(Request $request, Person $person): AnonymousResourceCollection
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        return PersonInteractionResource::collection(
            $person->interactions()->latest('occurred_at')->paginate(20)
        );
    }

    public function store(StorePersonInteractionRequest $request, Person $person): JsonResponse
    {
        abort_if($person->user_id !== $request->user()->id, 403);

        $interaction = $this->people->logInteraction($request->user(), $person, $request->validated());

        return (new PersonInteractionResource($interaction))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, Person $person, PersonInteraction $interaction): JsonResponse
    {
        abort_if($person->user_id !== $request->user()->id, 403);
        abort_if($interaction->person_id !== $person->id, 404);

        $this->people->deleteInteraction($request->user(), $interaction);

        return response()->json(['message' => 'Interaction deleted.']);
    }
}
```

`PersonKeyDateController` y `PersonSocialController` siguen el mismo patrón (index/store/destroy anidados; update top-level implícito por binding sin `person`), con `abort_if` de ownership y `201` en store. Para `update`:

```php
    public function update(UpdatePersonKeyDateRequest $request, PersonKeyDate $keyDate): PersonKeyDateResource
    {
        abort_if($keyDate->user_id !== $request->user()->id, 403);

        return new PersonKeyDateResource(
            $this->people->updateKeyDate($request->user(), $keyDate, $request->validated())
        );
    }
```

- [ ] **Step 5: Register the API routes**

En `routes/api.php`, dentro del grupo `auth:sanctum` (después del bloque Personal), agregar:

```php
        // People
        Route::get('people/upcoming', [PersonController::class, 'upcoming']);
        Route::apiResource('people', PersonController::class);
        Route::get('people/{person}/interactions', [PersonInteractionController::class, 'index']);
        Route::post('people/{person}/interactions', [PersonInteractionController::class, 'store']);
        Route::delete('people/{person}/interactions/{interaction}', [PersonInteractionController::class, 'destroy']);
        Route::get('people/{person}/key-dates', [PersonKeyDateController::class, 'index']);
        Route::post('people/{person}/key-dates', [PersonKeyDateController::class, 'store']);
        Route::patch('key-dates/{keyDate}', [PersonKeyDateController::class, 'update']);
        Route::delete('key-dates/{keyDate}', [PersonKeyDateController::class, 'destroy']);
        Route::get('people/{person}/socials', [PersonSocialController::class, 'index']);
        Route::post('people/{person}/socials', [PersonSocialController::class, 'store']);
        Route::patch('socials/{social}', [PersonSocialController::class, 'update']);
        Route::delete('socials/{social}', [PersonSocialController::class, 'destroy']);
```

con los `use` correspondientes a `App\Http\Controllers\Api\V1\Person*`.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Api/PeopleApiTest.php`
Expected: PASS (6 tests).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/Person* app/Http/Requests/Api/*Person* app/Http/Resources/Person* routes/api.php tests/Feature/Api/PeopleApiTest.php
git commit -m "feat(people): api v1 endpoints for people, interactions, key dates and socials"
```

---

### Task 10: MCP — `PeopleReadTool`, `PeopleWriteTool`, `PeopleLogTool`

**Files:**
- Create: `app/Mcp/Tools/PeopleReadTool.php`
- Create: `app/Mcp/Tools/PeopleWriteTool.php`
- Create: `app/Mcp/Tools/PeopleLogTool.php`
- Modify: `app/Mcp/Servers/MegalomaniacServer.php`
- Test: `tests/Feature/Mcp/PeopleToolsTest.php`

**Interfaces:**
- Consumes: `PeopleService` (Task 3).
- Produces: tools `people-read`, `people-write`, `people-log` en el server MCP, scoped al usuario autenticado.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Mcp/PeopleToolsTest.php`:

```php
<?php

use App\Mcp\Servers\MegalomaniacServer;
use App\Mcp\Tools\PeopleLogTool;
use App\Mcp\Tools\PeopleReadTool;
use App\Mcp\Tools\PeopleWriteTool;
use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\User;
use App\People\Enums\InteractionChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates, reads, updates and deletes people', function () {
    $user = User::factory()->create();

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleWriteTool::class, ['action' => 'create_person', 'first_name' => 'Ana'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('person.first_name', 'Ana')->etc());

    $person = Person::where('user_id', $user->id)->firstOrFail();

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleReadTool::class, [])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('records')->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleWriteTool::class, [
            'action' => 'update_person',
            'person_id' => $person->id,
            'nickname' => 'Anita',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('person.nickname', 'Anita')->etc());

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleWriteTool::class, ['action' => 'delete_person', 'person_id' => $person->id])
        ->assertOk();

    expect(Person::find($person->id))->toBeNull();
});

it('logs an interaction through the log tool and refreshes last contact', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create(['user_id' => $user->id]);

    MegalomaniacServer::actingAs($user)
        ->tool(PeopleLogTool::class, [
            'person_id' => $person->id,
            'channel' => InteractionChannel::Call->value,
            'title' => 'Llamada',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('interaction.person_id', $person->id)->etc());

    expect(PersonInteraction::where('person_id', $person->id)->count())->toBe(1)
        ->and($person->fresh()->last_contacted_at)->not->toBeNull();
});

it('returns upcoming key dates and scopes tools to the authenticated user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $person = Person::factory()->create([
        'user_id' => $owner->id,
        'birthday' => now()->addDays(4)->toDateString(),
    ]);

    MegalomaniacServer::actingAs($owner)
        ->tool(PeopleReadTool::class, ['upcoming_days' => 30])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->has('upcoming')->etc());

    MegalomaniacServer::actingAs($intruder)
        ->tool(PeopleWriteTool::class, ['action' => 'update_person', 'person_id' => $person->id, 'nickname' => 'hack'])
        ->assertHasErrors(['not found']);

    expect($person->fresh()->nickname)->not->toBe('hack');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Mcp/PeopleToolsTest.php`
Expected: FAIL — tools no existen.

- [ ] **Step 3: Implement the tools**

`app/Mcp/Tools/PeopleReadTool.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Models\Person;
use App\People\Enums\Closeness;
use App\Services\People\PeopleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PeopleReadTool extends Tool
{
    protected string $name = 'people-read';

    protected string $description = 'Read the authenticated user\'s personal contacts: search and filter people (closeness, favorites, not contacted in N days), get a full person record with key dates and socials, and list upcoming birthdays and key dates.';

    public function __construct(protected PeopleService $people) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'person_id' => $schema->integer()->description('Person ID for the full record'),
            'search' => $schema->string()->description('Search by first name, last name, nickname or company'),
            'closeness' => $schema->string()->description('Filter by closeness')->enum(Closeness::values()),
            'favorite' => $schema->boolean()->description('Only favorite people'),
            'archived' => $schema->boolean()->description('Only archived people'),
            'stale_days' => $schema->integer()->description('Only people not contacted in the last N days')->min(1)->max(3650),
            'upcoming_days' => $schema->integer()->description('Include birthdays and key dates within the next N days')->min(1)->max(365),
            'limit' => $schema->integer()->description('Maximum records (default 20)')->min(1)->max(100),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $personId = $request->get('person_id');

        if ($personId) {
            $person = Person::with(['keyDates', 'socials'])
                ->where('user_id', $user->id)
                ->find((int) $personId);

            if (! $person) {
                return Response::error('Person not found.');
            }

            $payload = $person->toArray();
            $payload['interactions'] = $person->interactions()
                ->latest('occurred_at')
                ->limit(10)
                ->get()
                ->toArray();
            $payload['upcoming'] = $this->people->upcoming($user, 60, $person)->all();

            return Response::structured(['person' => $payload]);
        }

        $limit = (int) $request->get('limit', 20);

        $query = Person::query()->where('user_id', $user->id);

        $query->when($request->get('search'), fn ($q, $search) => $q->where(function ($w) use ($search) {
            $w->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nickname', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%");
        }));

        $query->when($request->get('closeness'), fn ($q, $closeness) => $q->where('closeness', $closeness));

        filter_var($request->get('archived', false), FILTER_VALIDATE_BOOLEAN)
            ? $query->where('is_archived', true)
            : $query->where('is_archived', false);

        $query->when(
            filter_var($request->get('favorite', false), FILTER_VALIDATE_BOOLEAN),
            fn ($q) => $q->where('is_favorite', true),
        );

        $query->when($request->get('stale_days'), fn ($q, $days) => $q->where(function ($w) use ($days) {
            $w->whereNull('last_contacted_at')
                ->orWhere('last_contacted_at', '<', now()->subDays((int) $days));
        }));

        $people = $query->orderByDesc('is_favorite')->orderBy('first_name')->limit($limit)->get();

        $payload = [
            'records' => $people->toArray(),
            'count' => $people->count(),
            'limit' => $limit,
        ];

        if ($days = (int) $request->get('upcoming_days', 0)) {
            $payload['upcoming'] = $this->people->upcoming($user, $days)->all();
        }

        return Response::structured($payload);
    }
}
```

`app/Mcp/Tools/PeopleWriteTool.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\People\Enums\Closeness;
use App\People\Enums\KeyDateType;
use App\People\Enums\PreferredContactChannel;
use App\People\Enums\RelationshipStatus;
use App\Services\People\PeopleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class PeopleWriteTool extends Tool
{
    protected string $name = 'people-write';

    protected string $description = 'Create, update or delete people, and manage their key dates and social networks. Use people-log for interactions.';

    public function __construct(protected PeopleService $people) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->description('Action to perform')->enum([
                'create_person', 'update_person', 'delete_person',
                'add_key_date', 'update_key_date', 'delete_key_date',
                'add_social', 'update_social', 'delete_social',
            ])->required(),
            'person_id' => $schema->integer()->description('Person ID (all actions except create_person)'),
            'first_name' => $schema->string()->description('First name (create_person)')->max(255),
            'last_name' => $schema->string()->description('Last name'),
            'nickname' => $schema->string()->description('Nickname'),
            'birthday' => $schema->string()->description('Birthday in YYYY-MM-DD format'),
            'email' => $schema->string()->description('Email'),
            'phone' => $schema->string()->description('Phone'),
            'whatsapp' => $schema->string()->description('WhatsApp'),
            'city' => $schema->string()->description('City'),
            'country' => $schema->string()->description('Country'),
            'company' => $schema->string()->description('Company'),
            'job_title' => $schema->string()->description('Job title'),
            'website' => $schema->string()->description('Website URL'),
            'how_we_met' => $schema->string()->description('How you met'),
            'closeness' => $schema->string()->description('Closeness')->enum(Closeness::values()),
            'relationship_status' => $schema->string()->description('Relationship status'),
            'preferred_contact_channel' => $schema->string()->description('Preferred contact channel'),
            'is_favorite' => $schema->boolean()->description('Favorite flag'),
            'is_archived' => $schema->boolean()->description('Archived flag'),
            'notes' => $schema->string()->description('Notes'),
            'key_date_id' => $schema->integer()->description('Key date ID (update_key_date, delete_key_date)'),
            'key_date_type' => $schema->string()->description('Key date type')->enum(KeyDateType::values()),
            'label' => $schema->string()->description('Key date label'),
            'date' => $schema->string()->description('Key date in YYYY-MM-DD format'),
            'remind_days_before' => $schema->integer()->description('Reminder days before the date')->min(0)->max(90),
            'is_recurring_annually' => $schema->boolean()->description('Repeats every year'),
            'social_id' => $schema->integer()->description('Social ID (update_social, delete_social)'),
            'network' => $schema->string()->description('Social network (e.g. instagram, x, linkedin)'),
            'handle' => $schema->string()->description('Social handle'),
            'url' => $schema->string()->description('Social URL'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $action = (string) $request->get('action', '');
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        try {
            return match ($action) {
                'create_person' => $this->createPerson($request, $user),
                'update_person' => $this->updatePerson($request, $user),
                'delete_person' => $this->deletePerson($request, $user),
                'add_key_date' => $this->addKeyDate($request, $user),
                'update_key_date' => $this->updateKeyDate($request, $user),
                'delete_key_date' => $this->deleteKeyDate($request, $user),
                'add_social' => $this->addSocial($request, $user),
                'update_social' => $this->updateSocial($request, $user),
                'delete_social' => $this->deleteSocial($request, $user),
                default => Response::error("Invalid action: {$action}"),
            };
        } catch (ModelNotFoundException|AuthorizationException $exception) {
            return Response::error($exception->getMessage());
        }
    }

    private function createPerson(Request $request, $user): Response|ResponseFactory
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'how_we_met' => ['nullable', 'string'],
            'closeness' => ['nullable', Rule::enum(Closeness::class)],
            'relationship_status' => ['nullable', Rule::enum(RelationshipStatus::class)],
            'preferred_contact_channel' => ['nullable', Rule::enum(PreferredContactChannel::class)],
            'is_favorite' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $person = $this->people->createPerson($user, array_filter($request->only([
            'first_name', 'last_name', 'nickname', 'birthday', 'email', 'phone',
            'whatsapp', 'city', 'country', 'company', 'job_title', 'website',
            'how_we_met', 'closeness', 'relationship_status',
            'preferred_contact_channel', 'is_favorite', 'notes',
        ]), fn ($value) => $value !== null));

        return Response::structured([
            'person' => $person->toArray(),
            'message' => 'Person created successfully.',
        ]);
    }

    private function updatePerson(Request $request, $user): Response|ResponseFactory
    {
        $person = $this->people->findPerson($user, (int) $request->get('person_id', 0));

        $data = array_filter($request->only([
            'first_name', 'last_name', 'nickname', 'birthday', 'email', 'phone',
            'whatsapp', 'address', 'city', 'country', 'company', 'job_title',
            'website', 'how_we_met', 'closeness', 'relationship_status',
            'preferred_contact_channel', 'notes',
        ]), fn ($value) => $value !== null);

        if ($request->get('is_favorite') !== null) {
            $data['is_favorite'] = (bool) $request->get('is_favorite');
        }

        if ($request->get('is_archived') !== null) {
            $data['is_archived'] = (bool) $request->get('is_archived');
        }

        $person = $this->people->updatePerson($user, $person, $data);

        return Response::structured([
            'person' => $person->toArray(),
            'message' => 'Person updated successfully.',
        ]);
    }

    private function deletePerson(Request $request, $user): Response|ResponseFactory
    {
        $person = $this->people->findPerson($user, (int) $request->get('person_id', 0));
        $this->people->deletePerson($user, $person);

        return Response::structured(['message' => 'Person deleted successfully.']);
    }

    private function addKeyDate(Request $request, $user): Response|ResponseFactory
    {
        $person = $this->people->findPerson($user, (int) $request->get('person_id', 0));

        $request->validate([
            'key_date_type' => ['required', Rule::enum(KeyDateType::class)],
            'date' => ['required', 'date'],
            'label' => ['nullable', 'string', 'max:255'],
            'remind_days_before' => ['nullable', 'integer', 'min:0', 'max:90'],
            'is_recurring_annually' => ['boolean'],
        ]);

        $keyDate = $this->people->addKeyDate($user, $person, [
            'type' => $request->get('key_date_type'),
            'label' => $request->get('label'),
            'date' => $request->get('date'),
            'remind_days_before' => $request->get('remind_days_before', 7),
            'is_recurring_annually' => (bool) $request->get('is_recurring_annually', true),
        ]);

        return Response::structured([
            'key_date' => $keyDate->toArray(),
            'message' => 'Key date added successfully.',
        ]);
    }

    private function updateKeyDate(Request $request, $user): Response|ResponseFactory
    {
        $keyDate = \App\Models\PersonKeyDate::where('user_id', $user->id)
            ->findOrFail((int) $request->get('key_date_id', 0));

        $data = array_filter($request->only([
            'label', 'date', 'remind_days_before',
        ]), fn ($value) => $value !== null);

        if ($request->get('key_date_type') !== null) {
            $data['type'] = $request->get('key_date_type');
        }

        if ($request->get('is_recurring_annually') !== null) {
            $data['is_recurring_annually'] = (bool) $request->get('is_recurring_annually');
        }

        $keyDate = $this->people->updateKeyDate($user, $keyDate, $data);

        return Response::structured(['key_date' => $keyDate->toArray(), 'message' => 'Key date updated.']);
    }

    private function deleteKeyDate(Request $request, $user): Response|ResponseFactory
    {
        $keyDate = \App\Models\PersonKeyDate::where('user_id', $user->id)
            ->findOrFail((int) $request->get('key_date_id', 0));

        $this->people->deleteKeyDate($user, $keyDate);

        return Response::structured(['message' => 'Key date deleted.']);
    }

    private function addSocial(Request $request, $user): Response|ResponseFactory
    {
        $person = $this->people->findPerson($user, (int) $request->get('person_id', 0));

        $request->validate([
            'network' => ['required', 'string', 'max:50'],
            'handle' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
        ]);

        $social = $this->people->addSocial($user, $person, [
            'network' => $request->get('network'),
            'handle' => $request->get('handle'),
            'url' => $request->get('url'),
        ]);

        return Response::structured(['social' => $social->toArray(), 'message' => 'Social added.']);
    }

    private function updateSocial(Request $request, $user): Response|ResponseFactory
    {
        $social = \App\Models\PersonSocial::whereHas('person', fn ($q) => $q->where('user_id', $user->id))
            ->findOrFail((int) $request->get('social_id', 0));

        $data = array_filter($request->only(['network', 'handle', 'url']), fn ($value) => $value !== null);

        $social = $this->people->updateSocial($user, $social, $data);

        return Response::structured(['social' => $social->toArray(), 'message' => 'Social updated.']);
    }

    private function deleteSocial(Request $request, $user): Response|ResponseFactory
    {
        $social = \App\Models\PersonSocial::whereHas('person', fn ($q) => $q->where('user_id', $user->id))
            ->findOrFail((int) $request->get('social_id', 0));

        \Illuminate\Support\Facades\Gate::forUser($user);

        $this->people->deleteSocial($user, $social);

        return Response::structured(['message' => 'Social deleted.']);
    }
}
```

**Nota:** los `PersonKeyDate`/`PersonSocial` no tienen `user_id` propio; por eso el `findOrFail` scopeado por `whereHas('person')`. En `deleteSocial` sobra la línea de `Gate` — no agregarla (queda como recordatorio de que la autorización vive en el servicio). Para `updateSocial`/`deleteSocial` el servicio valida `$social->person->user_id` contra el usuario.

`app/Mcp/Tools/PeopleLogTool.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\People\Enums\InteractionChannel;
use App\Services\People\PeopleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class PeopleLogTool extends Tool
{
    protected string $name = 'people-log';

    protected string $description = 'Quick-log a social interaction with a person (call, message, meeting...). Only creates new interaction records; it never edits or deletes existing data.';

    public function __construct(protected PeopleService $people) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'person_id' => $schema->integer()->description('Person ID')->required(),
            'channel' => $schema->string()->description('Interaction channel')->enum(InteractionChannel::values()),
            'occurred_at' => $schema->string()->description('ISO 8601 datetime (defaults to now)'),
            'title' => $schema->string()->description('Short title'),
            'notes' => $schema->string()->description('Notes'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();

        if (! $user) {
            return Response::error('Unauthenticated.');
        }

        $request->validate([
            'person_id' => ['required', 'integer'],
            'channel' => ['nullable', Rule::enum(InteractionChannel::class)],
            'occurred_at' => ['nullable', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $person = $this->people->findPerson($user, (int) $request->get('person_id'));
        } catch (ModelNotFoundException $exception) {
            return Response::error($exception->getMessage());
        }

        $interaction = $this->people->logInteraction($user, $person, [
            'channel' => $request->get('channel') ?? InteractionChannel::Message->value,
            'occurred_at' => $request->get('occurred_at') ?? now(),
            'title' => $request->get('title'),
            'notes' => $request->get('notes'),
        ]);

        return Response::structured([
            'interaction' => $interaction->toArray(),
            'message' => 'Interaction logged successfully.',
        ]);
    }
}
```

- [ ] **Step 4: Register the tools in the server**

En `app/Mcp/Servers/MegalomaniacServer.php`: imports + `$tools`:

```php
        PeopleReadTool::class,
        PeopleWriteTool::class,
        PeopleLogTool::class,
```

y ampliar el `#[Instructions]` mencionando "personal contacts (people, interactions, key dates and socials)".

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Mcp/PeopleToolsTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Mcp/Tools/People* app/Mcp/Servers/MegalomaniacServer.php tests/Feature/Mcp/PeopleToolsTest.php
git commit -m "feat(people): mcp read, write and log tools"
```

---

### Task 11: Chat IA — `PeopleQueryTool`, `PeopleActionTool`, catálogo y routing

**Files:**
- Create: `app/Ai/Tools/PeopleQueryTool.php`
- Create: `app/Ai/Tools/PeopleActionTool.php`
- Modify: `app/Ai/Tools/ToolCatalog.php` (grupo `people` + `actionTools()` + `make()`)
- Modify: `config/ai_tools.php` (keywords `people` + fallback)
- Modify: `resources/js/lib/chat-tools.ts` (labels)
- Modify: `docs/modules/tools.md` (fila People)
- Test: `tests/Feature/Ai/PeopleQueryToolTest.php`, `tests/Feature/Ai/PeopleActionToolTest.php`
- Modify (si existen): `tests/Feature/Ai/ToolCatalogTest.php`, `tests/Feature/Ai/ToolRouterTest.php`

**Interfaces:**
- Consumes: `PeopleService` (Task 3).
- Produces: grupo de chat `people` con ambas tools; keywords de routing; labels de UI.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Ai/PeopleQueryToolTest.php`:

```php
<?php

use App\Ai\Tools\PeopleQueryTool;
use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

it('finds people by search and returns the detail with key dates', function () {
    $user = User::factory()->create();
    $person = Person::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Ana',
        'birthday' => now()->addDays(5)->toDateString(),
    ]);
    $person->keyDates()->create([
        'user_id' => $user->id,
        'type' => 'anniversary',
        'label' => 'Aniversario',
        'date' => now()->subYears(2)->toDateString(),
        'is_recurring_annually' => true,
    ]);
    Person::factory()->create(['first_name' => 'Bruno']);

    $list = json_decode((new PeopleQueryTool($user))->handle(new Request(['search' => 'ana'])), true);

    expect($list['records'])->toHaveCount(1)
        ->and($list['records'][0]['first_name'])->toBe('Ana');

    $detail = json_decode((new PeopleQueryTool($user))->handle(new Request(['person_id' => $person->id])), true);

    expect($detail['person']['first_name'])->toBe('Ana')
        ->and($detail['person']['key_dates'])->toHaveCount(1)
        ->and($detail['upcoming'])->not->toBeEmpty();
});

it('lists stale contacts and upcoming dates', function () {
    $user = User::factory()->create();
    Person::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Ana',
        'last_contacted_at' => now()->subDays(60),
    ]);
    Person::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Cami',
        'birthday' => now()->addDays(3)->toDateString(),
    ]);

    $stale = json_decode((new PeopleQueryTool($user))->handle(new Request(['stale_days' => 30])), true);
    expect($stale['records'])->toHaveCount(1)
        ->and($stale['records'][0]['first_name'])->toBe('Ana');

    $upcoming = json_decode((new PeopleQueryTool($user))->handle(new Request(['upcoming_days' => 7])), true);
    expect($upcoming['upcoming'])->toHaveCount(1)
        ->and($upcoming['upcoming'][0]['kind'])->toBe('birthday');
});
```

`tests/Feature/Ai/PeopleActionToolTest.php`:

```php
<?php

use App\Ai\Tools\PeopleActionTool;
use App\Models\Person;
use App\Models\User;
use App\Services\People\PeopleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

function peopleTool(User $user): PeopleActionTool
{
    return new PeopleActionTool($user, app(PeopleService::class));
}

it('creates a person and logs an interaction', function () {
    $user = User::factory()->create();

    $created = json_decode(peopleTool($user)->handle(new Request([
        'action' => 'create_person',
        'first_name' => 'Ana',
        'closeness' => 'close',
    ])), true);

    expect($created['success'])->toBeTrue();

    $person = Person::where('user_id', $user->id)->firstOrFail();

    $logged = json_decode(peopleTool($user)->handle(new Request([
        'action' => 'log_interaction',
        'person_id' => $person->id,
        'channel' => 'call',
        'title' => 'Llamada',
    ])), true);

    expect($logged['success'])->toBeTrue()
        ->and($person->fresh()->last_contacted_at)->not->toBeNull();
});

it('requires approval with a readable label', function () {
    $user = User::factory()->create();

    $approval = peopleTool($user)->needsApproval(new Request(['action' => 'create_person']));

    expect($approval)->toBeInstanceOf(Approval::class)
        ->and($approval->question)->toContain('crear');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Ai/PeopleQueryToolTest.php tests/Feature/Ai/PeopleActionToolTest.php`
Expected: FAIL — clases no existen.

- [ ] **Step 3: Implement `PeopleQueryTool`**

`app/Ai/Tools/PeopleQueryTool.php`:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Person;
use App\Models\User;
use App\Services\People\PeopleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PeopleQueryTool implements Tool
{
    public function __construct(
        protected User $user,
        protected PeopleService $people,
    ) {}

    public function description(): Stringable|string
    {
        return 'Query the user\'s personal contacts: search people by name or nickname, get a full record with key dates, socials and recent interactions, list people not contacted in N days, and list upcoming birthdays and key dates.';
    }

    public function handle(Request $request): Stringable|string
    {
        $personId = $request['person_id'] ?? null;

        if ($personId) {
            $person = Person::with(['keyDates', 'socials'])
                ->where('user_id', $this->user->id)
                ->find((int) $personId);

            if (! $person) {
                return 'Person not found.';
            }

            $payload = $person->toArray();
            $payload['interactions'] = $person->interactions()
                ->latest('occurred_at')
                ->limit(10)
                ->get()
                ->toArray();
            $payload['upcoming'] = $this->people->upcoming($this->user, 60, $person)->all();

            return json_encode(['person' => $payload], JSON_PRETTY_PRINT);
        }

        $query = Person::query()->where('user_id', $this->user->id);

        $query->when($request['search'] ?? null, fn ($q, $search) => $q->where(function ($w) use ($search) {
            $w->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('nickname', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%");
        }));

        $query->when($request['closeness'] ?? null, fn ($q, $closeness) => $q->where('closeness', $closeness));

        $query->when($request['stale_days'] ?? null, fn ($q, $days) => $q->where(function ($w) use ($days) {
            $w->whereNull('last_contacted_at')
                ->orWhere('last_contacted_at', '<', now()->subDays((int) $days));
        }));

        $people = $query->orderByDesc('is_favorite')->orderBy('first_name')->limit(15)->get();

        $payload = [
            'records' => $people->toArray(),
            'count' => $people->count(),
        ];

        if ($days = (int) ($request['upcoming_days'] ?? 0)) {
            $payload['upcoming'] = $this->people->upcoming($this->user, $days)->all();
        }

        return json_encode($payload, JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'person_id' => $schema->integer()->description('Person ID for the full record'),
            'search' => $schema->string()->description('Search by name, nickname or company'),
            'closeness' => $schema->string()->description('Filter by closeness: inner_circle, close, friend, acquaintance'),
            'stale_days' => $schema->integer()->description('People not contacted in the last N days'),
            'upcoming_days' => $schema->integer()->description('Include birthdays and key dates within the next N days'),
        ];
    }
}
```

- [ ] **Step 4: Implement `PeopleActionTool`**

`app/Ai/Tools/PeopleActionTool.php`:

```php
<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Person;
use App\Models\User;
use App\Services\People\PeopleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PeopleActionTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        protected User $user,
        protected PeopleService $people,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create and update personal contacts: create or edit a person, log an interaction, add a key date. Use this when the user asks to record something about a person.';
    }

    public function needsApproval(Request $request): Approval|bool
    {
        return Approval::required('Va a '.($this->actionLabels()[$request['action'] ?? ''] ?? 'modificar tus contactos').'.');
    }

    /**
     * @return array<string, string>
     */
    protected function actionLabels(): array
    {
        return [
            'create_person' => 'crear una persona',
            'update_person' => 'actualizar una persona',
            'log_interaction' => 'registrar una interacción',
            'add_key_date' => 'agregar una fecha clave',
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            return match ($request['action'] ?? '') {
                'create_person' => $this->createPerson($request),
                'update_person' => $this->updatePerson($request),
                'log_interaction' => $this->logInteraction($request),
                'add_key_date' => $this->addKeyDate($request),
                default => $this->error('Invalid action. Use: create_person, update_person, log_interaction, add_key_date'),
            };
        } catch (ModelNotFoundException|AuthorizationException $exception) {
            return $this->error($exception->getMessage());
        }
    }

    private function createPerson(Request $request): string
    {
        $name = trim((string) ($request['first_name'] ?? ''));

        if ($name === '') {
            return $this->error('First name is required.');
        }

        $person = $this->people->createPerson($this->user, array_filter([
            'first_name' => $name,
            'last_name' => $request['last_name'] ?? null,
            'nickname' => $request['nickname'] ?? null,
            'birthday' => $request['birthday'] ?? null,
            'email' => $request['email'] ?? null,
            'phone' => $request['phone'] ?? null,
            'whatsapp' => $request['whatsapp'] ?? null,
            'city' => $request['city'] ?? null,
            'company' => $request['company'] ?? null,
            'closeness' => $request['closeness'] ?? null,
            'notes' => $request['notes'] ?? null,
        ], fn ($value) => $value !== null));

        return $this->success('Person created.', ['person' => $person->toArray()]);
    }

    private function updatePerson(Request $request): string
    {
        $person = $this->people->findPerson($this->user, (int) ($request['person_id'] ?? 0));

        $data = array_filter([
            'first_name' => $request['first_name'] ?? null,
            'last_name' => $request['last_name'] ?? null,
            'nickname' => $request['nickname'] ?? null,
            'birthday' => $request['birthday'] ?? null,
            'email' => $request['email'] ?? null,
            'phone' => $request['phone'] ?? null,
            'whatsapp' => $request['whatsapp'] ?? null,
            'city' => $request['city'] ?? null,
            'company' => $request['company'] ?? null,
            'closeness' => $request['closeness'] ?? null,
            'notes' => $request['notes'] ?? null,
        ], fn ($value) => $value !== null);

        if ($request['is_favorite'] !== null) {
            $data['is_favorite'] = (bool) $request['is_favorite'];
        }

        if ($request['is_archived'] !== null) {
            $data['is_archived'] = (bool) $request['is_archived'];
        }

        $person = $this->people->updatePerson($this->user, $person, $data);

        return $this->success('Person updated.', ['person' => $person->toArray()]);
    }

    private function logInteraction(Request $request): string
    {
        $person = $this->people->findPerson($this->user, (int) ($request['person_id'] ?? 0));

        $interaction = $this->people->logInteraction($this->user, $person, [
            'channel' => $request['channel'] ?? 'message',
            'occurred_at' => $request['occurred_at'] ?? now(),
            'title' => $request['title'] ?? null,
            'notes' => $request['notes'] ?? null,
        ]);

        return $this->success('Interaction logged.', [
            'interaction' => $interaction->toArray(),
            'last_contacted_at' => $person->fresh()->last_contacted_at?->toIso8601String(),
        ]);
    }

    private function addKeyDate(Request $request): string
    {
        $person = $this->people->findPerson($this->user, (int) ($request['person_id'] ?? 0));

        $date = (string) ($request['date'] ?? '');

        if ($date === '') {
            return $this->error('Date is required.');
        }

        $keyDate = $this->people->addKeyDate($this->user, $person, [
            'type' => $request['key_date_type'] ?? 'custom',
            'label' => $request['label'] ?? null,
            'date' => $date,
            'remind_days_before' => (int) ($request['remind_days_before'] ?? 7),
            'is_recurring_annually' => (bool) ($request['is_recurring_annually'] ?? true),
        ]);

        return $this->success('Key date added.', ['key_date' => $keyDate->toArray()]);
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
                ->enum(['create_person', 'update_person', 'log_interaction', 'add_key_date'])
                ->description('Action to perform')
                ->required(),
            'person_id' => $schema->integer()->description('Person ID (update_person, log_interaction, add_key_date)'),
            'first_name' => $schema->string()->description('First name (create_person)'),
            'last_name' => $schema->string()->description('Last name'),
            'nickname' => $schema->string()->description('Nickname'),
            'birthday' => $schema->string()->description('Birthday YYYY-MM-DD'),
            'email' => $schema->string()->description('Email'),
            'phone' => $schema->string()->description('Phone'),
            'whatsapp' => $schema->string()->description('WhatsApp'),
            'city' => $schema->string()->description('City'),
            'company' => $schema->string()->description('Company'),
            'closeness' => $schema->string()->description('Closeness: inner_circle, close, friend, acquaintance'),
            'is_favorite' => $schema->boolean()->description('Favorite flag'),
            'is_archived' => $schema->boolean()->description('Archived flag'),
            'notes' => $schema->string()->description('Notes'),
            'channel' => $schema->string()->description('Interaction channel for log_interaction'),
            'occurred_at' => $schema->string()->description('ISO 8601 datetime for log_interaction'),
            'title' => $schema->string()->description('Title for log_interaction or key date label context'),
            'key_date_type' => $schema->string()->description('Key date type for add_key_date'),
            'label' => $schema->string()->description('Key date label'),
            'date' => $schema->string()->description('Key date YYYY-MM-DD (add_key_date)'),
            'remind_days_before' => $schema->integer()->description('Key date reminder days'),
            'is_recurring_annually' => $schema->boolean()->description('Key date repeats annually'),
        ];
    }
}
```

- [ ] **Step 5: Wire the catalog, routing and labels**

En `app/Ai/Tools/ToolCatalog.php`:
- `groups()`: agregar `'people' => ['label' => 'Personas', 'tools' => [PeopleQueryTool::class, PeopleActionTool::class]],`
- `actionTools()`: agregar `PeopleActionTool::class,`
- `make()`:

```php
            PeopleActionTool::class => new PeopleActionTool($user, app(PeopleService::class)),
```

con `use App\Services\People\PeopleService;`.

En `config/ai_tools.php`:
- `keywords`: agregar

```php
        'people' => [
            'persona', 'personas', 'contacto', 'contactos', 'amig', 'familia',
            'cumple', 'cumpleanos', 'vinculo', 'interaccion', 'hable', 'llam',
            'escribi', 'conoci', 'regalo', 'aniversario',
        ],
```

- `fallback`: agregar `'people'` a la lista (queda como módulo de datos en lectura).

En `resources/js/lib/chat-tools.ts`:

```ts
    PeopleQueryTool: 'Consultando personas',
    PeopleActionTool: 'Actualizando personas',
```

En `docs/modules/tools.md`, agregar a la tabla de grupos:

```
| `people` | `PeopleQueryTool` | `PeopleActionTool` | `Services\People\PeopleService` |
```

- [ ] **Step 6: Run the chat tests and the catalog/router tests**

```bash
php artisan test --compact tests/Feature/Ai/PeopleQueryToolTest.php tests/Feature/Ai/PeopleActionToolTest.php
php artisan test --compact tests/Feature/Ai/ToolCatalogTest.php tests/Feature/Ai/ToolRouterTest.php
```

Si `ToolCatalogTest`/`ToolRouterTest` afirman listas exactas de grupos, actualizarlos con `people` y los keywords nuevos. Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
npm run build
vendor/bin/pint --dirty --format agent
git add app/Ai/Tools/PeopleQueryTool.php app/Ai/Tools/PeopleActionTool.php app/Ai/Tools/ToolCatalog.php config/ai_tools.php resources/js/lib/chat-tools.ts docs/modules/tools.md tests/Feature/Ai/PeopleQueryToolTest.php tests/Feature/Ai/PeopleActionToolTest.php
git commit -m "feat(people): chat query and action tools with routing and labels"
```

---

### Task 12: Cierre — suite, build, pint final y QA de flujo

**Files:**
- Modify: solo si la verificación encuentra fallos.

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: rama verde y flujo verificado.

- [ ] **Step 1: Run the full People test set**

```bash
php artisan test --compact tests/Feature/People tests/Feature/Api/PeopleApiTest.php tests/Feature/Mcp/PeopleToolsTest.php tests/Feature/Ai/PeopleQueryToolTest.php tests/Feature/Ai/PeopleActionToolTest.php
```
Expected: PASS.

- [ ] **Step 2: Run the full suite (regression gate)**

```bash
php artisan test --compact
```
Expected: PASS. Si algo falla por cambios compartidos (ToolCatalog, config/ai_tools, sidebar), arreglar en esta tarea y commitear el fix.

- [ ] **Step 3: Build frontend**

```bash
npm run build
```
Expected: build OK, Wayfinder regenera `resources/js/routes/people` y `resources/js/actions/App/Http/Controllers/People`.

- [ ] **Step 4: QA manual/Playwright del flujo**

Con la app corriendo (`composer run dev` o el stack de prod en `127.0.0.1:8095`), verificar:
1. Login `test@example.com` / `password` → sidebar muestra "Personas".
2. `/people` lista los contactos del seeder; buscar filtra; "Favoritos" y "Archivados" filtran; "sin contacto +30 días" deja a Bruno.
3. Crear persona → aparece en la lista; editar; subir avatar; crear una fecha clave; agregar una red; "Registrar contacto" actualiza "Último contacto".
4. `/people/calendar` muestra cumpleaños y fechas; `/people/timeline` muestra las interacciones.
5. Chat IA: "¿quién cumple años este mes?" responde con datos; "registrá que hablé con Ana" pide aprobación y al aprobar crea la interacción.
6. `php artisan mcp` tools listadas (si hay inspector) o verificar con `php artisan route:list --path=people`.

Si Playwright MCP está disponible, hacer el recorrido y corregir cualquier clipping/overlap/estado vacío roto (regla anti-ui-slop: renderizar una vez y arreglar lo observable).

- [ ] **Step 5: Final commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A
git commit -m "chore(people): phase 1 polish and qa pass"
```

---

## Self-Review

**Cobertura de la spec (Fase 1):** F1 ficha (Task 4/5), F2 listado/búsqueda/filtros (Task 4), F3 timeline global y por persona (Tasks 4/6), F4 alta rápida (Task 6 + `PeopleLogTool` Task 10 + chat Task 11), F5 fechas clave (Task 7), F6 calendario (Task 7). Puente Freelance (Task 2). API v1 (Task 9). MCP (Task 10). Chat (Task 11). Fases 2–4 quedan fuera por diseño.

**Consistencia de tipos:** `PeopleService` se usa con las mismas firmas en Tasks 3, 9, 10 y 11; `PersonResource` y `PeopleReadTool` serializan `closeness`/`relationship_status` como `->value`; la página `Show` consume `person.key_dates`, `person.socials` (snake_case de Eloquent) y `interactions.data`; Wayfinder genera `people.keyDates`, `people.socials`, `people.avatar` desde los nombres con guion.

**Placeholders:** no hay TBD/TODO; cada paso de código trae el contenido. Las páginas crecen por ediciones exactas en Tasks 6/7/8 (bloques comentados en `Show.tsx`).

**Riesgo de coordinación:** cubierto en "Nota de coordinación" y en la Tarea 12 (suite completa como red de seguridad).



