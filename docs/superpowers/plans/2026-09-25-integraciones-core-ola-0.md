# Integraciones Core — Ola 0 (Framework + GitHub + Google + Docker) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el framework de integraciones (registro de conectores, transportes, executor con aprobaciones, OAuth, auditoría, UI) y los 3 conectores piloto (GitHub, Google, Docker) para que el agente IA y el usuario operen servicios externos con lectura libre y escritura aprobada.

**Architecture:** Registro de conectores (`kind → Connector`), acciones declarativas (`Action`/`Param`), `IntegrationExecutor` que valida, aplica rate limit, redacta secretos, audita y encola `ApprovalRequest` para escrituras/destructivas. Transportes conmutables (`direct`, `local_socket`, `ssh_tunnel`, `ssh_exec`). Dos tools de IA (`integration_catalog`, `integration_call`). UI Inertia en Settings → Conexiones + bandeja Aprobaciones + Actividad.

**Tech Stack:** PHP 8.4, Laravel 12, Pest 4, Inertia v2, React 19, Tailwind v4, Wayfinder, Sanctum, `laravel/ai` v0.11, `laravel/mcp` v0.9 (sin uso en Ola 0).

**Spec:** `docs/superpowers/specs/2026-09-25-integraciones-core-design.md`

## Global Constraints

- **Sin dependencias nuevas** (Composer ni npm). SSH vía binario OpenSSH + Symfony Process (ya incluido en Laravel).
- Convenciones del repo: casts en método `casts()`, sin `DB::`, Form Requests para HTTP, Eloquent scopes, enums backed, `declare(strict_types=1)` solo donde ya se usa (controllers no lo usan).
- PHP: llaves siempre, property promotion, return types, PHPDoc sobre comentarios inline.
- Credenciales siempre `encrypted:array`; **jamás** loggear tokens, passwords ni headers de auth.
- Tests: Pest 4; `php artisan test --compact --filter=...`; PHPUnit usa SQLite (ver `phpunit.xml`).
- Al terminar cambios PHP: `vendor/bin/pint --dirty --format agent`.
- Frontend: `npm run types`, `npm run lint`, `npm run build`.
- **Commits: NO ejecutar** salvo autorización explícita del usuario. Los pasos "Commit" de este plan quedan condicionados a esa autorización; si no, dejar los archivos en working tree.
- Los tests usan `Http::fake()` / `Process::fake()`; nunca red real.
- Rate limits, timeouts, TTL y retention salen de `config/integrations.php`; nada hardcodeado en clases.

---

## File Structure

```
config/integrations.php                          (nuevo)
app/Integrations/Enums/{ActionAccess,ApprovalStatus,ConnectionStatus,AuthType,TransportKind}.php
app/Integrations/Actions/{Param,Action,AuthField,ActionResult,ConnectionTestResult,ExecutionContext,ParamRules}.php
app/Integrations/Contracts/Connector.php
app/Integrations/ConnectorRegistry.php
app/Integrations/Connectors/AbstractConnector.php
app/Integrations/Support/SecretRedactor.php
app/Integrations/Transports/{Transport,TransportFactory,HttpCall,HttpResult,ExecResult}.php
app/Integrations/Transports/{DirectTransport,LocalSocketTransport,SshTunnelTransport,SshExecTransport}.php
app/Integrations/OAuth/{OAuthToken,OAuthPreset,OAuthBroker,Presets/GoogleOAuthPreset}.php
app/Integrations/IntegrationExecutor.php
app/Integrations/Connectors/Github/GithubConnector.php
app/Integrations/Connectors/Google/GoogleConnector.php
app/Integrations/Connectors/Docker/DockerConnector.php
app/Exceptions/Integrations/{UnknownConnectorException,UnsupportedTransportException}.php
app/Models/{Connection,ApprovalRequest,IntegrationActionLog}.php
app/Jobs/RunIntegrationActionJob.php
app/Console/Commands/{ExpireApprovalsCommand,PruneIntegrationActivityCommand}.php
app/Http/Controllers/Integrations/{ConnectionController,ApprovalController,ActivityController,OAuthController}.php
app/Http/Requests/Integrations/{StoreConnectionRequest,UpdateConnectionRequest,DecideApprovalRequest}.php
app/Policies/{ConnectionPolicy,ApprovalRequestPolicy}.php
app/Ai/Tools/{IntegrationCatalogTool,IntegrationCallTool}.php
app/Providers/IntegrationServiceProvider.php     (registro del registry)
routes/integrations.php                          (nuevo, require desde web.php)
routes/settings.php                              (+ bloque connections)
routes/console.php                               (+ schedule)
database/migrations/*_create_connections_table.php
database/migrations/*_create_approval_requests_table.php
database/migrations/*_create_integration_action_logs_table.php
database/factories/{ConnectionFactory,ApprovalRequestFactory,IntegrationActionLogFactory}.php
resources/js/pages/settings/connections.tsx
resources/js/pages/integrations/{approvals,activity}.tsx
resources/js/components/integrations/{ConnectionWizard,ConnectionCard,StatusBadge,ApprovalCard,EmptyState}.tsx
tests/Unit/Integrations/*                        (14 archivos)
tests/Feature/Integrations/*                     (12 archivos)
```

---

### Task 1: Config, enums y value objects

**Files:**
- Create: `config/integrations.php`
- Create: `app/Integrations/Enums/ActionAccess.php`, `ApprovalStatus.php`, `ConnectionStatus.php`, `AuthType.php`, `TransportKind.php`
- Create: `app/Integrations/Actions/Param.php`, `Action.php`, `AuthField.php`, `ActionResult.php`, `ConnectionTestResult.php`, `ExecutionContext.php`, `ParamRules.php`
- Test: `tests/Unit/Integrations/ValueObjectsTest.php`, `tests/Unit/Integrations/ParamRulesTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: enums y VOs usados por todas las tareas siguientes. `ParamRules::forParams(Param[]): array<string, array<int,string>>`; `ActionAccess::requiresApproval(): bool`; `ActionResult::{success,failure,pending}()`; `ExecutionContext::forUi(User)`, `::forAgent(User, ?string $rationale)`.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Unit/Integrations/ValueObjectsTest.php

use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ExecutionContext;
use App\Integrations\Enums\ActionAccess;
use App\Models\User;

it('knows which accesses require approval', function () {
    expect(ActionAccess::Read->requiresApproval())->toBeFalse()
        ->and(ActionAccess::Write->requiresApproval())->toBeTrue()
        ->and(ActionAccess::Destructive->requiresApproval())->toBeTrue();
});

it('builds action results', function () {
    expect(ActionResult::success('ok', ['a' => 1])->ok)->toBeTrue()
        ->and(ActionResult::failure('boom')->error)->toBe('boom')
        ->and(ActionResult::pending(42, 'Reiniciar contenedor')->approvalId)->toBe(42);
});

it('builds execution contexts for ui and agent', function () {
    $user = new User;

    $ui = ExecutionContext::forUi($user);
    expect($ui->source)->toBe('ui')->and($ui->preApproved)->toBeTrue();

    $agent = ExecutionContext::forAgent($user, 'Pedido por chat');
    expect($agent->source)->toBe('chat')->and($agent->preApproved)->toBeFalse()
        ->and($agent->rationale)->toBe('Pedido por chat');
});
```

```php
<?php // tests/Unit/Integrations/ParamRulesTest.php

use App\Integrations\Actions\Param;
use App\Integrations\Actions\ParamRules;

it('maps params to validation rules', function () {
    $rules = ParamRules::forParams([
        new Param('owner', 'string', true, 'Dueño'),
        new Param('count', 'integer', false, 'Cantidad', default: 10),
        new Param('dry_run', 'boolean', false, 'Simular'),
        new Param('labels', 'array', false, 'Etiquetas'),
        new Param('state', 'string', false, 'Estado', enum: ['open', 'closed']),
    ]);

    expect($rules['owner'])->toBe(['required', 'string'])
        ->and($rules['count'])->toBe(['nullable', 'integer'])
        ->and($rules['dry_run'])->toBe(['nullable', 'boolean'])
        ->and($rules['labels'])->toBe(['nullable', 'array'])
        ->and($rules['state'])->toBe(['nullable', 'string', 'in:open,closed']);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ValueObjectsTest --filter=ParamRulesTest`
Expected: FAIL — clases no existen.

- [ ] **Step 3: Write minimal implementation**

```php
<?php // config/integrations.php

use App\Integrations\Connectors\Docker\DockerConnector;
use App\Integrations\Connectors\Github\GithubConnector;
use App\Integrations\Connectors\Google\GoogleConnector;

return [
    'connectors' => [
        GithubConnector::class,
        GoogleConnector::class,
        DockerConnector::class,
    ],

    'approval' => [
        'ttl_hours' => (int) env('INTEGRATIONS_APPROVAL_TTL_HOURS', 72),
    ],

    'limits' => [
        'read_per_minute' => (int) env('INTEGRATIONS_READ_PER_MINUTE', 60),
        'write_per_minute' => (int) env('INTEGRATIONS_WRITE_PER_MINUTE', 10),
    ],

    'ssh' => [
        'connect_timeout' => (int) env('INTEGRATIONS_SSH_CONNECT_TIMEOUT', 5),
        'command_timeout' => (int) env('INTEGRATIONS_SSH_COMMAND_TIMEOUT', 15),
        'output_cap_bytes' => (int) env('INTEGRATIONS_SSH_OUTPUT_CAP', 262144),
    ],

    'http' => [
        'timeout' => (int) env('INTEGRATIONS_HTTP_TIMEOUT', 20),
    ],

    'retention_days' => (int) env('INTEGRATIONS_RETENTION_DAYS', 90),

    'redaction' => [
        'patterns' => [
            '/\b(Bearer|token|api[_-]?key|secret|password)\b\s*[:=]?\s*[\w\-\.]{6,}/i',
        ],
    ],
];
```

```php
<?php // app/Integrations/Enums/ActionAccess.php

namespace App\Integrations\Enums;

enum ActionAccess: string
{
    case Read = 'read';
    case Write = 'write';
    case Destructive = 'destructive';

    public function requiresApproval(): bool
    {
        return $this !== self::Read;
    }
}
```

```php
<?php // app/Integrations/Enums/ApprovalStatus.php

namespace App\Integrations\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Executed = 'executed';
    case Failed = 'failed';
}
```

```php
<?php // app/Integrations/Enums/ConnectionStatus.php

namespace App\Integrations\Enums;

enum ConnectionStatus: string
{
    case Unknown = 'unknown';
    case Ok = 'ok';
    case Error = 'error';
    case Expired = 'expired';
}
```

```php
<?php // app/Integrations/Enums/AuthType.php

namespace App\Integrations\Enums;

enum AuthType: string
{
    case ApiToken = 'api_token';
    case Basic = 'basic';
    case OAuth2 = 'oauth2';
    case None = 'none';
    case Qr = 'qr';
}
```

```php
<?php // app/Integrations/Enums/TransportKind.php

namespace App\Integrations\Enums;

enum TransportKind: string
{
    case Direct = 'direct';
    case LocalSocket = 'local_socket';
    case SshTunnel = 'ssh_tunnel';
    case SshExec = 'ssh_exec';
}
```

```php
<?php // app/Integrations/Actions/Param.php

namespace App\Integrations\Actions;

final readonly class Param
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $required,
        public string $description,
        public ?array $enum = null,
        public mixed $default = null,
        public bool $sensitive = false,
    ) {}
}
```

```php
<?php // app/Integrations/Actions/Action.php

namespace App\Integrations\Actions;

use App\Integrations\Enums\ActionAccess;

final readonly class Action
{
    /**
     * @param  Param[]  $params
     * @param  array<int, array<string, mixed>>  $examples
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public ActionAccess $access,
        public array $params = [],
        public array $examples = [],
        public ?string $returns = null,
    ) {}
}
```

```php
<?php // app/Integrations/Actions/AuthField.php

namespace App\Integrations\Actions;

final readonly class AuthField
{
    /**
     * @param  array<string, string>  $options
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $label,
        public bool $required = true,
        public ?string $help = null,
        public array $options = [],
    ) {}
}
```

```php
<?php // app/Integrations/Actions/ActionResult.php

namespace App\Integrations\Actions;

final readonly class ActionResult
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    public function __construct(
        public bool $ok,
        public string $summary,
        public ?array $data = null,
        public ?string $error = null,
        public ?int $approvalId = null,
        public bool $pending = false,
    ) {}

    public static function success(string $summary, ?array $data = null): self
    {
        return new self(ok: true, summary: $summary, data: $data);
    }

    public static function failure(string $error, ?array $data = null): self
    {
        return new self(ok: false, summary: $error, data: $data, error: $error);
    }

    public static function pending(int $approvalId, string $summary): self
    {
        return new self(ok: true, summary: $summary, approvalId: $approvalId, pending: true);
    }
}
```

```php
<?php // app/Integrations/Actions/ConnectionTestResult.php

namespace App\Integrations\Actions;

final readonly class ConnectionTestResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $ok,
        public string $message,
        public array $meta = [],
    ) {}

    public static function ok(string $message, array $meta = []): self
    {
        return new self(ok: true, message: $message, meta: $meta);
    }

    public static function fail(string $message, array $meta = []): self
    {
        return new self(ok: false, message: $message, meta: $meta);
    }
}
```

```php
<?php // app/Integrations/Actions/ExecutionContext.php

namespace App\Integrations\Actions;

use App\Models\User;

final readonly class ExecutionContext
{
    public function __construct(
        public ?User $actor,
        public string $source,
        public bool $preApproved = false,
        public ?string $rationale = null,
    ) {}

    public static function forUi(User $user): self
    {
        return new self(actor: $user, source: 'ui', preApproved: true);
    }

    public static function forAgent(User $user, ?string $rationale = null): self
    {
        return new self(actor: $user, source: 'chat', rationale: $rationale);
    }

    public static function forSchedule(User $user, ?string $rationale = null): self
    {
        return new self(actor: $user, source: 'schedule', rationale: $rationale);
    }
}
```

```php
<?php // app/Integrations/Actions/ParamRules.php

namespace App\Integrations\Actions;

final class ParamRules
{
    /**
     * @param  Param[]  $params
     * @return array<string, array<int, string>>
     */
    public static function forParams(array $params): array
    {
        $rules = [];

        foreach ($params as $param) {
            $set = [$param->required ? 'required' : 'nullable'];
            $set[] = match ($param->type) {
                'integer' => 'integer',
                'number' => 'numeric',
                'boolean' => 'boolean',
                'array' => 'array',
                default => 'string',
            };

            if ($param->enum !== null) {
                $set[] = 'in:'.implode(',', $param->enum);
            }

            $rules[$param->name] = $set;
        }

        return $rules;
    }

    /**
     * @param  Param[]  $params
     * @return array<string, string>
     */
    public static function labels(array $params): array
    {
        $labels = [];

        foreach ($params as $param) {
            $labels[$param->name] = $param->description;
        }

        return $labels;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ValueObjectsTest --filter=ParamRulesTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit (solo con autorización explícita del usuario)**

```bash
git add config/integrations.php app/Integrations tests/Unit/Integrations
git commit -m "feat(integrations): add config, enums and value objects"
```

---

### Task 2: Migraciones, modelos y factories

**Files:**
- Create: `database/migrations/2026_09_25_000001_create_connections_table.php` (idem `000002_create_approval_requests_table.php`, `000003_create_integration_action_logs_table.php`)
- Create: `app/Models/Connection.php`, `ApprovalRequest.php`, `IntegrationActionLog.php`
- Create: `database/factories/ConnectionFactory.php`, `ApprovalRequestFactory.php`, `IntegrationActionLogFactory.php`
- Test: `tests/Feature/Integrations/ModelsTest.php`

**Interfaces:**
- Consumes: enums de Task 1.
- Produces: `Connection` (casts `credentials`/`transport_config` encrypted:array; scopes `forUser`, `enabled`; relations `user`, `actionLogs`, `approvals`), `ApprovalRequest` (scopes `pending`, `forUser`; relations `connection`, `user`, `log`, `requester`), `IntegrationActionLog` (relations `connection`, `approval`). Factories con estados.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Integrations/ModelsTest.php

use App\Enums\{...}; // N/A — usar imports reales
use App\Integrations\Enums\{ActionAccess, ApprovalStatus, ConnectionStatus};
use App\Models\ApprovalRequest;
use App\Models\Connection;
use App\Models\IntegrationActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('encrypts connection credentials at rest', function () {
    $connection = Connection::factory()->create([
        'credentials' => ['token' => 'super-secret'],
    ]);

    expect($connection->credentials['token'])->toBe('super-secret')
        ->and($connection->getRawOriginal('credentials'))->not->toContain('super-secret');
});

it('scopes connections by user and enabled', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['enabled' => true]);
    Connection::factory()->for($user)->create(['enabled' => false]);
    Connection::factory()->create();

    expect(Connection::query()->forUser($user)->enabled()->count())->toBe(1);
});

it('creates approvals with defaults and pending scope', function () {
    $approval = ApprovalRequest::factory()->create();

    expect($approval->status)->toBe(ApprovalStatus::Pending)
        ->and(ApprovalRequest::query()->pending()->count())->toBe(1)
        ->and($approval->connection)->toBeInstanceOf(Connection::class);
});

it('links action logs to approvals and connections', function () {
    $log = IntegrationActionLog::factory()->create();

    expect($log->connection)->toBeInstanceOf(Connection::class)
        ->and($log->params)->toBeArray()
        ->and($log->access)->toBe(ActionAccess::Read);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ModelsTest`
Expected: FAIL — tablas/modelos no existen.

- [ ] **Step 3: Write minimal implementation**

```php
<?php // database/migrations/2026_09_25_000001_create_connections_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 50);
            $table->string('name', 100);
            $table->string('auth_type', 30)->default('api_token');
            $table->text('credentials')->nullable();
            $table->string('base_url', 500)->nullable();
            $table->string('transport', 20)->default('direct');
            $table->text('transport_config')->nullable();
            $table->json('options')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('status', 20)->default('unknown');
            $table->text('status_message')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
```

```php
<?php // database/migrations/2026_09_25_000002_create_approval_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_key', 100);
            $table->json('params');
            $table->string('access', 15);
            $table->string('summary', 255);
            $table->text('rationale')->nullable();
            $table->string('status', 20)->default('pending');
            $table->nullableMorphs('requested_by');
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
```

```php
<?php // database/migrations/2026_09_25_000003_create_integration_action_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('action_key', 100);
            $table->string('access', 15);
            $table->nullableMorphs('actor');
            $table->string('source', 20);
            $table->json('params');
            $table->text('result_summary')->nullable();
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['connection_id', 'created_at']);
            $table->index(['action_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_action_logs');
    }
};
```

```php
<?php // app/Models/Connection.php

namespace App\Models;

use App\Integrations\Enums\AuthType;
use App\Integrations\Enums\ConnectionStatus;
use App\Integrations\Enums\TransportKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connection extends Model
{
    /** @use HasFactory<\Database\Factories\ConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'kind', 'name', 'auth_type', 'credentials', 'base_url',
        'transport', 'transport_config', 'options', 'enabled', 'status',
        'status_message', 'last_tested_at', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'transport_config' => 'encrypted:array',
            'options' => 'array',
            'enabled' => 'boolean',
            'auth_type' => AuthType::class,
            'transport' => TransportKind::class,
            'status' => ConnectionStatus::class,
            'last_tested_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(IntegrationActionLog::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }
}
```

```php
<?php // app/Models/ApprovalRequest.php

namespace App\Models;

use App\Integrations\Enums\ActionAccess;
use App\Integrations\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ApprovalRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'connection_id', 'user_id', 'action_key', 'params', 'access', 'summary',
        'rationale', 'status', 'decided_at', 'decided_by', 'decision_note',
        'executed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'access' => ActionAccess::class,
            'status' => ApprovalStatus::class,
            'decided_at' => 'datetime',
            'executed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function log(): HasOne
    {
        return $this->hasOne(IntegrationActionLog::class);
    }

    public function requester(): MorphTo
    {
        return $this->morphTo('requested_by');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStatus::Pending);
    }
}
```

```php
<?php // app/Models/IntegrationActionLog.php

namespace App\Models;

use App\Integrations\Enums\ActionAccess;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IntegrationActionLog extends Model
{
    /** @use HasFactory<\Database\Factories\IntegrationActionLogFactory> */
    use HasFactory;

    protected $fillable = [
        'connection_id', 'user_id', 'approval_request_id', 'action_key', 'access',
        'actor_type', 'actor_id', 'source', 'params', 'result_summary', 'status',
        'error', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'access' => ActionAccess::class,
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo('actor');
    }
}
```

```php
<?php // database/factories/ConnectionFactory.php

namespace Database\Factories;

use App\Integrations\Enums\AuthType;
use App\Integrations\Enums\ConnectionStatus;
use App\Integrations\Enums\TransportKind;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Connection>
 */
class ConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => 'github',
            'name' => fake()->unique()->words(2, true),
            'auth_type' => AuthType::ApiToken,
            'credentials' => ['token' => 'ghp_'.fake()->sha1()],
            'base_url' => 'https://api.github.com',
            'transport' => TransportKind::Direct,
            'transport_config' => null,
            'options' => null,
            'enabled' => true,
            'status' => ConnectionStatus::Unknown,
            'status_message' => null,
            'last_tested_at' => null,
            'last_used_at' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }
}
```

```php
<?php // database/factories/ApprovalRequestFactory.php

namespace Database\Factories;

use App\Integrations\Enums\ActionAccess;
use App\Integrations\Enums\ApprovalStatus;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'connection_id' => Connection::factory(),
            'user_id' => User::factory(),
            'action_key' => 'github.issues.create',
            'params' => ['title' => fake()->sentence()],
            'access' => ActionAccess::Write,
            'summary' => fake()->sentence(),
            'rationale' => null,
            'status' => ApprovalStatus::Pending,
            'decided_at' => null,
            'decided_by' => null,
            'decision_note' => null,
            'executed_at' => null,
            'expires_at' => now()->addHours(72),
        ];
    }
}
```

```php
<?php // database/factories/IntegrationActionLogFactory.php

namespace Database\Factories;

use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IntegrationActionLog>
 */
class IntegrationActionLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'connection_id' => Connection::factory(),
            'user_id' => fn (array $attributes) => Connection::find($attributes['connection_id'])->user_id,
            'approval_request_id' => null,
            'action_key' => 'github.issues.list',
            'access' => ActionAccess::Read,
            'actor_type' => null,
            'actor_id' => null,
            'source' => 'ui',
            'params' => [],
            'result_summary' => 'ok',
            'status' => 'success',
            'error' => null,
            'duration_ms' => 12,
        ];
    }
}
```

- [ ] **Step 4: Run migrations in test + test pass**

Run: `php artisan test --compact --filter=ModelsTest`
Expected: PASS (4 tests).

- [ ] **Step 5: Pint y commit condicional**

Run: `vendor/bin/pint --dirty --format agent`

---

### Task 3: Contrato de conector y registro

**Files:**
- Create: `app/Integrations/Contracts/Connector.php`
- Create: `app/Integrations/ConnectorRegistry.php`
- Create: `app/Exceptions/Integrations/UnknownConnectorException.php`
- Create: `app/Providers/IntegrationServiceProvider.php` (registrar en `bootstrap/providers.php`)
- Test: `tests/Unit/Integrations/ConnectorRegistryTest.php`

**Interfaces:**
- Consumes: `Action`, `ActionResult`, `ConnectionTestResult`, `AuthField`, `Connection`.
- Produces: `ConnectorRegistry::for(string $kind): Connector`, `::all(): array<string, Connector>`, `::has(kind): bool`, `::register(class)`. Binding singleton desde `config('integrations.connectors')`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Unit/Integrations/ConnectorRegistryTest.php

use App\Exceptions\Integrations\UnknownConnectorException;
use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\ConnectorRegistry;
use App\Integrations\Contracts\Connector;
use App\Integrations\Enums\ActionAccess;
use App\Models\Connection;

class FakeConnector implements Connector
{
    public function kind(): string { return 'fake'; }

    public function label(): string { return 'Fake'; }

    public function authFields(): array { return []; }

    public function transports(): array { return ['direct']; }

    public function actions(): array
    {
        return [new Action('ping', 'Ping', 'Ping the service', ActionAccess::Read)];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        return ActionResult::success('pong');
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        return ConnectionTestResult::ok('ok');
    }
}

it('registers and resolves connectors by kind', function () {
    $registry = new ConnectorRegistry;
    $registry->register(FakeConnector::class);

    expect($registry->has('fake'))->toBeTrue()
        ->and($registry->for('fake'))->toBeInstanceOf(FakeConnector::class)
        ->and($registry->all())->toHaveKey('fake');
});

it('throws for unknown connectors', function () {
    $registry = new ConnectorRegistry;

    expect(fn () => $registry->for('nope'))->toThrow(UnknownConnectorException::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ConnectorRegistryTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

```php
<?php // app/Integrations/Contracts/Connector.php

namespace App\Integrations\Contracts;

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\AuthField;
use App\Integrations\Actions\ConnectionTestResult;
use App\Models\Connection;

interface Connector
{
    public function kind(): string;

    public function label(): string;

    /** @return AuthField[] */
    public function authFields(): array;

    /** @return string[] */
    public function transports(): array;

    /** @return Action[] */
    public function actions(): array;

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(Connection $connection, string $key, array $params): ActionResult;

    public function test(Connection $connection): ConnectionTestResult;
}
```

```php
<?php // app/Exceptions/Integrations/UnknownConnectorException.php

namespace App\Exceptions\Integrations;

use RuntimeException;

class UnknownConnectorException extends RuntimeException
{
    public static function for(string $kind): self
    {
        return new self("Unknown integration connector [{$kind}].");
    }
}
```

```php
<?php // app/Integrations/ConnectorRegistry.php

namespace App\Integrations;

use App\Exceptions\Integrations\UnknownConnectorException;
use App\Integrations\Contracts\Connector;

final class ConnectorRegistry
{
    /** @var array<string, Connector> */
    private array $connectors = [];

    /**
     * @param  array<class-string<Connector>>  $classes
     */
    public function __construct(array $classes = [])
    {
        foreach ($classes as $class) {
            $this->register($class);
        }
    }

    /**
     * @param  class-string<Connector>  $class
     */
    public function register(string $class): void
    {
        $connector = app($class);
        $this->connectors[$connector->kind()] = $connector;
    }

    public function has(string $kind): bool
    {
        return isset($this->connectors[$kind]);
    }

    public function for(string $kind): Connector
    {
        return $this->connectors[$kind] ?? throw UnknownConnectorException::for($kind);
    }

    /**
     * @return array<string, Connector>
     */
    public function all(): array
    {
        return $this->connectors;
    }
}
```

```php
<?php // app/Providers/IntegrationServiceProvider.php

namespace App\Providers;

use App\Integrations\ConnectorRegistry;
use Illuminate\Support\ServiceProvider;

class IntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnectorRegistry::class, function () {
            return new ConnectorRegistry(config('integrations.connectors', []));
        });
    }
}
```

Añadir `App\Providers\IntegrationServiceProvider::class` a `bootstrap/providers.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ConnectorRegistryTest`
Expected: PASS (2 tests).

---

### Task 4: Transportes HTTP (interface + Direct + LocalSocket)

**Files:**
- Create: `app/Integrations/Transports/Transport.php`, `HttpCall.php`, `HttpResult.php`, `ExecResult.php`, `DirectTransport.php`, `LocalSocketTransport.php`, `TransportFactory.php`
- Create: `app/Exceptions/Integrations/UnsupportedTransportException.php`
- Test: `tests/Unit/Integrations/HttpTransportsTest.php`

**Interfaces:**
- Consumes: `Connection`, `ConnectionTestResult`.
- Produces:
  - `HttpCall`: `__construct(string $method, string $path = '', array $query = [], array $headers = [], ?array $json = null, ?int $timeout = null)`.
  - `HttpResult`: `ok`, `status`, `data (?array)`, `body (string)`, `error (?string)`, `durationMs (int)`.
  - `ExecResult`: `ok`, `exitCode (int)`, `output (string)`, `error (?string)`.
  - `Transport`: `request(Connection, HttpCall): HttpResult`; `exec(Connection, string): ExecResult`; `health(Connection): ConnectionTestResult`.
  - `TransportFactory::make(Connection): Transport`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Unit/Integrations/HttpTransportsTest.php

use App\Exceptions\Integrations\UnsupportedTransportException;
use App\Integrations\Enums\TransportKind;
use App\Integrations\Transports\DirectTransport;
use App\Integrations\Transports\HttpCall;
use App\Integrations\Transports\LocalSocketTransport;
use App\Integrations\Transports\TransportFactory;
use App\Models\Connection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends bearer token requests from credentials', function () {
    Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);

    $connection = Connection::factory()->make([
        'kind' => 'github',
        'base_url' => 'https://api.example.com',
        'transport' => TransportKind::Direct,
        'credentials' => ['token' => 'tok_123'],
    ]);

    $result = (new DirectTransport)->request($connection, new HttpCall('GET', 'repos'));

    expect($result->ok)->toBeTrue()
        ->and($result->data)->toBe(['ok' => true]);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer tok_123')
        && $request->url() === 'https://api.example.com/repos');
});

it('sends basic auth when auth type is basic', function () {
    Http::fake(['api.example.com/*' => Http::response([], 200)]);

    $connection = Connection::factory()->make([
        'base_url' => 'https://api.example.com',
        'credentials' => ['username' => 'u', 'password' => 'p'],
    ]);

    (new DirectTransport)->request($connection, new HttpCall('GET', 'x'));

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Basic '.base64_encode('u:p')));
});

it('maps http errors to a failed result', function () {
    Http::fake(['api.example.com/*' => Http::response(['message' => 'nope'], 403)]);

    $connection = Connection::factory()->make(['base_url' => 'https://api.example.com']);
    $result = (new DirectTransport)->request($connection, new HttpCall('GET', 'x'));

    expect($result->ok)->toBeFalse()->and($result->status)->toBe(403)
        ->and($result->error)->toContain('403');
});

it('refuses exec on http transports', function () {
    $connection = Connection::factory()->make();

    (new DirectTransport)->exec($connection, 'ls');
})->throws(UnsupportedTransportException::class);

it('builds a local socket transport with unix socket curl options', function () {
    Http::fake(['localhost/*' => Http::response(['ok' => true], 200)]);

    $connection = Connection::factory()->make([
        'transport' => TransportKind::LocalSocket,
        'base_url' => 'http://localhost',
        'transport_config' => ['socket_path' => '/var/run/docker.sock'],
    ]);

    expect(fn () => (new LocalSocketTransport)->request($connection, new HttpCall('GET', 'version')))
        ->not->toThrow(Throwable::class);
});

it('resolves the transport from the connection', function () {
    expect(TransportFactory::make(Connection::factory()->make(['transport' => TransportKind::Direct])))
        ->toBeInstanceOf(DirectTransport::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=HttpTransportsTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

Implementar `HttpCall`, `HttpResult`, `ExecResult`, `Transport`, `UnsupportedTransportException` (como en el diseño) y:

```php
<?php // app/Integrations/Transports/DirectTransport.php

namespace App\Integrations\Transports;

use App\Exceptions\Integrations\UnsupportedTransportException;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Enums\AuthType;
use App\Models\Connection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class DirectTransport implements Transport
{
    public function request(Connection $connection, HttpCall $call): HttpResult
    {
        $started = microtime(true);

        try {
            $response = $this->client($connection)->send($call->method, ltrim($call->path, '/'), [
                'query' => $call->query,
                'json' => $call->json,
                'headers' => $call->headers,
            ]);

            $json = $response->json();

            return new HttpResult(
                ok: $response->successful(),
                status: $response->status(),
                data: is_array($json) ? $json : null,
                body: $response->body(),
                error: $response->successful() ? null : "HTTP {$response->status()}",
                durationMs: (int) ((microtime(true) - $started) * 1000),
            );
        } catch (Throwable $e) {
            return new HttpResult(
                ok: false,
                status: 0,
                data: null,
                body: '',
                error: $e->getMessage(),
                durationMs: (int) ((microtime(true) - $started) * 1000),
            );
        }
    }

    public function exec(Connection $connection, string $command): ExecResult
    {
        throw UnsupportedTransportException::for('exec', 'direct');
    }

    public function health(Connection $connection): ConnectionTestResult
    {
        return ConnectionTestResult::ok('direct transport ready');
    }

    protected function client(Connection $connection): PendingRequest
    {
        $request = Http::baseUrl(rtrim($connection->base_url ?? '', '/'))
            ->timeout($call->timeout ?? config('integrations.http.timeout'))
            ->acceptJson()
            ->withHeaders($connection->options['headers'] ?? []);
        // NOTA: `withHeaders` debe combinar con los headers del call en `send()`.

        $credentials = $connection->credentials ?? [];

        return match ($connection->auth_type) {
            AuthType::Basic => $request->withBasicAuth($credentials['username'] ?? '', $credentials['password'] ?? ''),
            AuthType::None => $request,
            default => $request->withToken($credentials['token'] ?? $credentials['access_token'] ?? ''),
        };
    }
}
```

> Ajuste de implementación: el timeout se toma de `$call->timeout`; pasar `HttpCall` al builder o setearlo dentro de `request()`. Evitar referenciar `$call` fuera de scope.

```php
<?php // app/Integrations/Transports/LocalSocketTransport.php

namespace App\Integrations\Transports;

use App\Integrations\Actions\ConnectionTestResult;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use Throwable;

class LocalSocketTransport extends DirectTransport
{
    protected function client(Connection $connection): \Illuminate\Http\Client\PendingRequest
    {
        $socket = $connection->transport_config['socket_path'] ?? '/var/run/docker.sock';

        return parent::client($connection)->withOptions([
            'curl' => [CURLOPT_UNIX_SOCKET_PATH => $socket],
        ]);
    }

    public function health(Connection $connection): ConnectionTestResult
    {
        $socket = $connection->transport_config['socket_path'] ?? '/var/run/docker.sock';

        return is_file($socket) || file_exists($socket)
            ? ConnectionTestResult::ok("socket {$socket} presente")
            : ConnectionTestResult::fail("socket {$socket} no encontrado");
    }
}
```

```php
<?php // app/Integrations/Transports/TransportFactory.php

namespace App\Integrations\Transports;

use App\Integrations\Enums\TransportKind;
use App\Models\Connection;

final class TransportFactory
{
    public static function make(Connection $connection): Transport
    {
        return match ($connection->transport) {
            TransportKind::LocalSocket => app(LocalSocketTransport::class),
            TransportKind::SshTunnel => app(SshTunnelTransport::class),
            TransportKind::SshExec => app(SshExecTransport::class),
            default => app(DirectTransport::class),
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=HttpTransportsTest`
Expected: PASS (6 tests). Si `withOptions` con `curl` no propaga en `Http::fake`, ajustar el test a verificar la opción vía `Http::assertSent` inspeccionando `$request->options()`; documentar en el test.

---

### Task 5: Transportes SSH (`ssh_tunnel` + `ssh_exec`)

**Files:**
- Create: `app/Integrations/Transports/SshTunnelTransport.php`, `SshExecTransport.php`
- Test: `tests/Unit/Integrations/SshTransportsTest.php`

**Interfaces:**
- Consumes: `Connection.transport_config` (`ssh_host`, `ssh_port`, `ssh_user`, `key_path|private_key`, `remote_host`, `remote_port`, `known_hosts_policy`), config `integrations.ssh.*`.
- Produces: `SshTunnelTransport::request()` (abre túnel, reusa `DirectTransport` contra `127.0.0.1:{localPort}`, cierra en `finally`); `SshExecTransport::exec()` (comando remoto con allowlist opcional en `transport_config['allowed_commands']`).

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Unit/Integrations/SshTransportsTest.php

use App\Integrations\Enums\TransportKind;
use App\Integrations\Transports\SshExecTransport;
use App\Integrations\Transports\SshTunnelTransport;
use App\Models\Connection;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function sshConnection(array $config = [], array $attributes = []): Connection
{
    return Connection::factory()->make(array_merge([
        'transport' => TransportKind::SshExec,
        'transport_config' => array_merge([
            'ssh_host' => '10.0.0.5',
            'ssh_port' => 22,
            'ssh_user' => 'root',
            'key_path' => '/root/.ssh/id_ed25519',
        ], $config),
    ], $attributes));
}

it('executes a remote command and caps output', function () {
    Process::fake([
        '*' => Process::result(output: str_repeat('a', 10), exitCode: 0),
    ]);

    $result = (new SshExecTransport)->exec(sshConnection(), 'docker ps');

    expect($result->ok)->toBeTrue()->and($result->exitCode)->toBe(0);

    Process::assertRan(fn (PendingProcess $process) => str_contains($process->command, 'ssh')
        && str_contains($process->command, 'docker ps'));
});

it('rejects commands outside the allowlist', function () {
    Process::fake();

    $transport = new SshExecTransport;
    $connection = sshConnection(['allowed_commands' => ['docker ps']]);

    $result = $transport->exec($connection, 'rm -rf /');
    expect($result->ok)->toBeFalse()->and($result->error)->toContain('allowlist');
});

it('provides exec and refuses request on ssh_exec', function () {
    $transport = new SshExecTransport;

    expect(fn () => $transport->request(sshConnection(), new \App\Integrations\Transports\HttpCall('GET')))
        ->toThrow(\App\Exceptions\Integrations\UnsupportedTransportException::class);
});

it('builds an ssh tunnel command with the right forward', function () {
    Process::fake(['*' => Process::result(exitCode: 0)]);

    $connection = sshConnection([
        'remote_host' => '127.0.0.1',
        'remote_port' => 2375,
    ], ['transport' => TransportKind::SshTunnel]);

    try {
        (new SshTunnelTransport)->openTunnel($connection);
    } catch (\Throwable) {
        // el fake no mantiene el proceso; sólo validamos el comando
    }

    Process::assertRan(fn (PendingProcess $process) => str_contains($process->command, '-L')
        && str_contains($process->command, '127.0.0.1:2375')
        && str_contains($process->command, 'root@10.0.0.5'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SshTransportsTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

Implementar:

```php
<?php // app/Integrations/Transports/SshExecTransport.php

namespace App\Integrations\Transports;

use App\Exceptions\Integrations\UnsupportedTransportException;
use App\Integrations\Actions\ConnectionTestResult;
use App\Models\Connection;
use Illuminate\Support\Facades\Process;
use Throwable;

class SshExecTransport implements Transport
{
    public function request(Connection $connection, HttpCall $call): HttpResult
    {
        throw UnsupportedTransportException::for('request', 'ssh_exec');
    }

    public function exec(Connection $connection, string $command): ExecResult
    {
        $allowed = $connection->transport_config['allowed_commands'] ?? null;

        if (is_array($allowed) && ! $this->isAllowed($command, $allowed)) {
            return new ExecResult(false, -1, '', 'Comando fuera de la allowlist para esta conexión.');
        }

        $started = microtime(true);

        try {
            $result = Process::timeout(config('integrations.ssh.command_timeout'))
                ->run($this->sshCommand($connection).' -- '.escapeshellarg($command));

            $cap = config('integrations.ssh.output_cap_bytes');
            $output = substr($result->output(), 0, $cap);

            return new ExecResult($result->successful(), $result->exitCode(), $output, $result->successful() ? null : $result->errorOutput());
        } catch (Throwable $e) {
            return new ExecResult(false, -1, '', $e->getMessage());
        }
    }

    public function health(Connection $connection): ConnectionTestResult
    {
        $result = $this->exec($connection, 'true');

        return $result->ok
            ? ConnectionTestResult::ok('SSH OK')
            : ConnectionTestResult::fail($result->error ?? 'SSH falló');
    }

    protected function sshCommand(Connection $connection): string
    {
        $config = $connection->transport_config ?? [];

        $parts = [
            'ssh',
            '-o', 'BatchMode=yes',
            '-o', 'ConnectTimeout='.config('integrations.ssh.connect_timeout'),
            '-p', (string) ($config['ssh_port'] ?? 22),
        ];

        if (! empty($config['key_path'])) {
            $parts[] = '-i';
            $parts[] = $config['key_path'];
        }

        return implode(' ', array_map('escapeshellarg', $parts))
            .' '.escapeshellarg(($config['ssh_user'] ?? 'root').'@'.($config['ssh_host'] ?? ''));
    }

    /**
     * @param  string[]  $allowed
     */
    protected function isAllowed(string $command, array $allowed): bool
    {
        foreach ($allowed as $pattern) {
            if ($command === $pattern || str_starts_with($command, $pattern.' ')) {
                return true;
            }
        }

        return false;
    }
}
```

```php
<?php // app/Integrations/Transports/SshTunnelTransport.php

namespace App\Integrations\Transports;

use App\Integrations\Actions\ConnectionTestResult;
use App\Models\Connection;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;
use Throwable;

class SshTunnelTransport extends DirectTransport
{
    public function request(Connection $connection, HttpCall $call): HttpResult
    {
        $tunnel = $this->openTunnel($connection);

        try {
            $local = $connection->replicate();
            $local->base_url = "http://127.0.0.1:{$tunnel['port']}";
            $local->transport = \App\Integrations\Enums\TransportKind::Direct;

            return parent::request($local, $call);
        } finally {
            if ($tunnel['process'] instanceof SymfonyProcess) {
                $tunnel['process']->stop(1);
            }
        }
    }

    /**
     * @return array{port: int, process: SymfonyProcess}
     */
    public function openTunnel(Connection $connection): array
    {
        $config = $connection->transport_config ?? [];
        $port = $config['local_port'] ?? $this->freePort();

        $command = array_merge(
            ['ssh', '-N', '-o', 'BatchMode=yes', '-o', 'ExitOnForwardFailure=yes',
                '-o', 'ConnectTimeout='.config('integrations.ssh.connect_timeout'),
                '-L', "127.0.0.1:{$port}:".($config['remote_host'] ?? '127.0.0.1').':'.($config['remote_port'] ?? 80),
                '-p', (string) ($config['ssh_port'] ?? 22)],
            ! empty($config['key_path']) ? ['-i', $config['key_path']] : [],
            [($config['ssh_user'] ?? 'root').'@'.($config['ssh_host'] ?? '')],
        );

        $process = new SymfonyProcess($command);
        $process->setTimeout(null);
        $process->start();

        $this->waitForPort($port);

        return ['port' => $port, 'process' => $process];
    }

    public function health(Connection $connection): ConnectionTestResult
    {
        return ConnectionTestResult::ok('ssh tunnel transport ready');
    }

    protected function waitForPort(int $port, int $attempts = 50): void
    {
        for ($i = 0; $i < $attempts; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(100_000);
        }
    }

    protected function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=SshTransportsTest`
Expected: PASS (4 tests).

> Nota: `Process::assertRan` con arrays usa el string unido; si el assert falla por formato, usar `fn (PendingProcess $p) => str_contains(implode(' ', (array) $p->command), '...')`.

---

### Task 6: Redacción de secretos

**Files:**
- Create: `app/Integrations/Support/SecretRedactor.php`
- Test: `tests/Unit/Integrations/SecretRedactorTest.php`

**Interfaces:**
- Consumes: `Param[]`.
- Produces: `SecretRedactor::redact(array $data, array $params = []): array` y `SecretRedactor::redactString(string $value): string`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Unit/Integrations/SecretRedactorTest.php

use App\Integrations\Actions\Param;
use App\Integrations\Support\SecretRedactor;

it('redacts sensitive params by key', function () {
    $params = [new Param('token', 'string', true, 'Token', sensitive: true)];

    $redacted = SecretRedactor::redact([
        'token' => 'abc123',
        'title' => 'Hola',
    ], $params);

    expect($redacted['token'])->toBe('[redacted]')
        ->and($redacted['title'])->toBe('Hola');
});

it('redacts nested keys that look secret', function () {
    $redacted = SecretRedactor::redact([
        'auth' => ['password' => 'p', 'api_key' => 'k'],
        'list' => [['access_token' => 't'], ['name' => 'ok']],
    ]);

    expect($redacted['auth']['password'])->toBe('[redacted]')
        ->and($redacted['auth']['api_key'])->toBe('[redacted]')
        ->and($redacted['list'][0]['access_token'])->toBe('[redacted]')
        ->and($redacted['list'][1]['name'])->toBe('ok');
});

it('scrubs secret patterns from strings', function () {
    $value = SecretRedactor::redactString('Authorization: Bearer sk-abcdef123456');

    expect($value)->not->toContain('sk-abcdef123456');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=SecretRedactorTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

```php
<?php // app/Integrations/Support/SecretRedactor.php

namespace App\Integrations\Support;

use App\Integrations\Actions\Param;

final class SecretRedactor
{
    private const REDACTED = '[redacted]';

    private const DEFAULT_KEYS = [
        'token', 'access_token', 'refresh_token', 'api_key', 'apikey', 'secret',
        'password', 'passwd', 'authorization', 'private_key', 'client_secret',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @param  Param[]  $params
     * @return array<string, mixed>
     */
    public static function redact(array $data, array $params = []): array
    {
        $sensitive = collect($params)
            ->filter(fn (Param $param) => $param->sensitive)
            ->pluck('name')
            ->all();

        return self::walk($data, array_merge(self::DEFAULT_KEYS, $sensitive));
    }

    public static function redactString(string $value): string
    {
        foreach (config('integrations.redaction.patterns', []) as $pattern) {
            $value = preg_replace($pattern, self::REDACTED, $value) ?? $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  string[]  $sensitiveKeys
     * @return array<string, mixed>
     */
    private static function walk(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $data[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::walk($value, $sensitiveKeys);
            }
        }

        return $data;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=SecretRedactorTest`
Expected: PASS (3 tests).

---

### Task 7: Executor — lectura, rate limit, auditoría

**Files:**
- Create: `app/Integrations/IntegrationExecutor.php`
- Test: `tests/Feature/Integrations/ExecutorReadTest.php`

**Interfaces:**
- Consumes: `ConnectorRegistry`, `TransportFactory`, `SecretRedactor`, `ParamRules`, models.
- Produces: `IntegrationExecutor::execute(Connection, string $actionKey, array $params, ExecutionContext): ActionResult`; métodos públicos `runApproved(...)`, `testConnection(...)` (Tasks 8-9 los completan).

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Integrations/ExecutorReadTest.php

use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\ExecutionContext;
use App\Integrations\Actions\Param;
use App\Integrations\ConnectorRegistry;
use App\Integrations\Contracts\Connector;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\IntegrationExecutor;
use App\Models\Connection;
use App\Models\IntegrationActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

class ExecutorFakeConnector implements Connector
{
    public static int $calls = 0;
    public static bool $shouldFail = false;

    public function kind(): string { return 'fake'; }
    public function label(): string { return 'Fake'; }
    public function authFields(): array { return []; }
    public function transports(): array { return ['direct']; }

    public function actions(): array
    {
        return [
            new Action('ping', 'Ping', 'Ping', ActionAccess::Read, [
                new Param('secret_token', 'string', false, 'Token', sensitive: true),
            ]),
            new Action('write', 'Write', 'Write', ActionAccess::Write),
        ];
    }

    public function execute(Connection $connection, string $key, array $params): ActionResult
    {
        self::$calls++;

        return self::$shouldFail
            ? ActionResult::failure('falló')
            : ActionResult::success('hecho', ['key' => $key]);
    }

    public function test(Connection $connection): ConnectionTestResult
    {
        return ConnectionTestResult::ok('ok');
    }
}

function executor(): IntegrationExecutor
{
    $registry = new ConnectorRegistry([ExecutorFakeConnector::class]);

    return new IntegrationExecutor($registry);
}

beforeEach(function () {
    ExecutorFakeConnector::$calls = 0;
    ExecutorFakeConnector::$shouldFail = false;
    RateLimiter::clear('integrations:1:read');
});

it('executes read actions and logs them redacted', function () {
    $connection = Connection::factory()->for(User::factory())->create(['kind' => 'fake']);
    $user = $connection->user;

    $result = executor()->execute($connection, 'ping', ['secret_token' => 'abc'], ExecutionContext::forAgent($user));

    expect($result->ok)->toBeTrue()->and($result->data)->toBe(['key' => 'ping']);

    $log = IntegrationActionLog::first();
    expect($log->status)->toBe('success')
        ->and($log->params['secret_token'])->toBe('[redacted]')
        ->and($log->source)->toBe('chat')
        ->and($log->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('rejects invalid params without calling the connector', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);

    $result = executor()->execute($connection, 'ping', ['unknown' => 1], ExecutionContext::forUi($connection->user));

    // 'unknown' no está en las reglas: se ignora; forzamos tipo inválido real
    $result = executor()->execute($connection, 'ping', ['secret_token' => ['array-invalido']], ExecutionContext::forUi($connection->user));

    expect($result->ok)->toBeFalse()->and(ExecutorFakeConnector::$calls)->toBe(1);
});

it('fails on unknown action and disabled connection', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);

    expect(executor()->execute($connection, 'nope', [], ExecutionContext::forUi($connection->user))->ok)->toBeFalse();

    $connection->update(['enabled' => false]);
    expect(executor()->execute($connection, 'ping', [], ExecutionContext::forUi($connection->user))->ok)->toBeFalse();
});

it('enforces the read rate limit', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);
    config(['integrations.limits.read_per_minute' => 1]);

    $first = executor()->execute($connection, 'ping', [], ExecutionContext::forUi($connection->user));
    $second = executor()->execute($connection, 'ping', [], ExecutionContext::forUi($connection->user));

    expect($first->ok)->toBeTrue()->and($second->ok)->toBeFalse()
        ->and($second->error)->toContain('Límite');
});

it('updates connection status after failures', function () {
    ExecutorFakeConnector::$shouldFail = true;
    $connection = Connection::factory()->create(['kind' => 'fake']);

    executor()->execute($connection, 'ping', [], ExecutionContext::forUi($connection->user));

    expect($connection->fresh()->status->value)->toBe('error')
        ->and($connection->fresh()->status_message)->toContain('falló');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ExecutorReadTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

```php
<?php // app/Integrations/IntegrationExecutor.php

namespace App\Integrations;

use App\Exceptions\Integrations\UnknownConnectorException;
use App\Integrations\Actions\Action;
use App\Integrations\Actions\ActionResult;
use App\Integrations\Actions\ConnectionTestResult;
use App\Integrations\Actions\ExecutionContext;
use App\Integrations\Actions\ParamRules;
use App\Integrations\Enums\ActionAccess;
use App\Integrations\Enums\ConnectionStatus;
use App\Integrations\Support\SecretRedactor;
use App\Models\Connection;
use App\Models\IntegrationActionLog;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class IntegrationExecutor
{
    public function __construct(
        private readonly ConnectorRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(Connection $connection, string $actionKey, array $params, ExecutionContext $context): ActionResult
    {
        if (! $connection->enabled) {
            return ActionResult::failure('La conexión está deshabilitada.');
        }

        try {
            $connector = $this->registry->for($connection->kind);
        } catch (UnknownConnectorException) {
            return ActionResult::failure("Conector desconocido [{$connection->kind}].");
        }

        $action = collect($connector->actions())->firstWhere('key', $actionKey);

        if (! $action instanceof Action) {
            return ActionResult::failure("Acción desconocida [{$actionKey}].");
        }

        try {
            $validated = Validator::make(
                $params,
                ParamRules::forParams($action->params),
                [],
                ParamRules::labels($action->params),
            )->validate();
        } catch (ValidationException $e) {
            return ActionResult::failure('Parámetros inválidos.', $e->errors());
        }

        return $this->run($connection, $connector, $action, $validated, $context);
    }

    public function testConnection(Connection $connection): ConnectionTestResult
    {
        try {
            $result = $this->registry->for($connection->kind)->test($connection);
        } catch (Throwable $e) {
            $result = ConnectionTestResult::fail($e->getMessage());
        }

        $connection->forceFill([
            'status' => $result->ok ? ConnectionStatus::Ok : ConnectionStatus::Error,
            'status_message' => $result->ok ? null : Str::limit($result->message, 500),
            'last_tested_at' => now(),
        ])->save();

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function run(Connection $connection, Contracts\Connector $connector, Action $action, array $params, ExecutionContext $context): ActionResult
    {
        if ($this->rateLimited($connection, $action)) {
            return ActionResult::failure('Límite de uso alcanzado; intentá de nuevo en un minuto.');
        }

        $started = microtime(true);

        $log = IntegrationActionLog::create([
            'connection_id' => $connection->id,
            'user_id' => $connection->user_id,
            'action_key' => $connection->kind.'.'.$action->key,
            'access' => $action->access->value,
            'actor_type' => $context->actor?->getMorphClass(),
            'actor_id' => $context->actor?->getKey(),
            'source' => $context->source,
            'params' => SecretRedactor::redact($params, $action->params),
            'status' => 'running',
        ]);

        try {
            $result = $connector->execute($connection, $action->key, $params);
        } catch (Throwable $e) {
            report($e);
            $result = ActionResult::failure('Error inesperado al ejecutar la acción.');
        }

        $connection->forceFill([
            'last_used_at' => now(),
            'status' => $result->ok ? ConnectionStatus::Ok : ConnectionStatus::Error,
            'status_message' => $result->ok ? null : Str::limit($result->error ?? '', 500),
        ])->save();

        $log->update([
            'status' => $result->ok ? 'success' : 'failed',
            'result_summary' => Str::limit(SecretRedactor::redactString($result->summary), 1000),
            'error' => $result->error ? SecretRedactor::redactString($result->error) : null,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);

        return $result;
    }

    protected function rateLimited(Connection $connection, Action $action): bool
    {
        $limit = $action->access === ActionAccess::Read
            ? config('integrations.limits.read_per_minute')
            : config('integrations.limits.write_per_minute');

        $key = "integrations:{$connection->id}:{$action->access->value}";

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return true;
        }

        RateLimiter::hit($key, 60);

        return false;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ExecutorReadTest`
Expected: PASS (5 tests).

---

### Task 8: Executor — aprobaciones, job y comandos programados

**Files:**
- Modify: `app/Integrations/IntegrationExecutor.php` (+`queueApproval`, `approve`, `reject`, `runApproval`)
- Create: `app/Jobs/RunIntegrationActionJob.php`
- Create: `app/Console/Commands/ExpireApprovalsCommand.php`, `PruneIntegrationActivityCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Integrations/ExecutorApprovalTest.php`, `tests/Feature/Integrations/ScheduledCommandsTest.php`

**Interfaces:**
- Consumes: Task 7 + `ApprovalRequest`.
- Produces: `execute()` devuelve `ActionResult::pending()` para write/destructive sin `preApproved`; `approve(ApprovalRequest, User, ?string $note)`, `reject(...)`, `runApproval(ApprovalRequest)`; `RunIntegrationActionJob(int $connectionId, string $actionKey, array $params, ?int $approvalId, string $source)`.

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/Integrations/ExecutorApprovalTest.php

use App\Integrations\Actions\ExecutionContext;
use App\Integrations\Enums\ApprovalStatus;
use App\Integrations\IntegrationExecutor;
use App\Jobs\RunIntegrationActionJob;
use App\Models\ApprovalRequest;
use App\Models\Connection;
use App\Models\IntegrationActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

// Reusar ExecutorFakeConnector de ExecutorReadTest: declararla también acá
// o moverla a tests/Support/FakeConnector.php e usarla en ambos.

it('queues an approval for agent writes', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);

    $result = executor()->execute($connection, 'write', ['x' => 1], ExecutionContext::forAgent($connection->user, 'motivo'));

    expect($result->pending)->toBeTrue()
        ->and($result->approvalId)->toBeInt()
        ->and(ApprovalRequest::pending()->count())->toBe(1)
        ->and(ApprovalRequest::first()->rationale)->toBe('motivo')
        ->and(IntegrationActionLog::first()->status)->toBe('pending');
});

it('executes ui writes immediately', function () {
    Bus::fake();
    $connection = Connection::factory()->create(['kind' => 'fake']);

    $result = executor()->execute($connection, 'write', [], ExecutionContext::forUi($connection->user));

    expect($result->ok)->toBeTrue()->and(ExecutorFakeConnector::$calls)->toBe(1);
    Bus::assertNothingDispatched();
});

it('approves and dispatches the job', function () {
    Bus::fake();
    $approval = ApprovalRequest::factory()->create(['connection_id' => Connection::factory()->create(['kind' => 'fake'])]);

    executor()->approve($approval, $approval->user, 'ok');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($approval->fresh()->decision_note)->toBe('ok');
    Bus::assertDispatched(RunIntegrationActionJob::class);
});

it('rejects and denies', function () {
    $approval = ApprovalRequest::factory()->create(['connection_id' => Connection::factory()->create(['kind' => 'fake'])]);

    executor()->reject($approval, $approval->user, 'no');

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Rejected)
        ->and(IntegrationActionLog::where('approval_request_id', $approval->id)->first()->status)->toBe('denied');
});

it('runs an approved action and marks it executed', function () {
    $connection = Connection::factory()->create(['kind' => 'fake']);
    $approval = ApprovalRequest::factory()->create([
        'connection_id' => $connection->id,
        'user_id' => $connection->user_id,
        'action_key' => 'fake.write',
        'access' => 'write',
        'params' => [],
    ]);

    $executor = executor();
    $executor->approve($approval, $approval->user);
    // Job en sync en tests: ejecutar directo
    app()->call([new RunIntegrationActionJob($connection->id, 'write', [], $approval->id, 'approval'), 'handle']);

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Executed)
        ->and(IntegrationActionLog::where('approval_request_id', $approval->id)->first()->status)->toBe('success');
});
```

```php
<?php // tests/Feature/Integrations/ScheduledCommandsTest.php

use App\Integrations\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expires pending approvals past ttl', function () {
    $stale = ApprovalRequest::factory()->create(['expires_at' => now()->subHour()]);
    $fresh = ApprovalRequest::factory()->create(['expires_at' => now()->addHour()]);

    $this->artisan('integrations:expire-approvals')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(ApprovalStatus::Expired)
        ->and($fresh->fresh()->status)->toBe(ApprovalStatus::Pending);
});

it('prunes old activity logs', function () {
    $old = \App\Models\IntegrationActionLog::factory()->create(['created_at' => now()->subDays(120)]);
    $new = \App\Models\IntegrationActionLog::factory()->create();

    $this->artisan('integrations:prune-activity')->assertSuccessful();

    expect(\App\Models\IntegrationActionLog::find($old->id))->toBeNull()
        ->and(\App\Models\IntegrationActionLog::find($new->id))->not->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ExecutorApprovalTest --filter=ScheduledCommandsTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

Ampliar `IntegrationExecutor` con:

```php
    protected function queueApproval(Connection $connection, Action $action, array $params, ExecutionContext $context): ActionResult
    {
        $approval = ApprovalRequest::create([
            'connection_id' => $connection->id,
            'user_id' => $connection->user_id,
            'action_key' => $connection->kind.'.'.$action->key,
            'params' => SecretRedactor::redact($params, $action->params),
            'access' => $action->access->value,
            'summary' => $action->label.' · '.$connection->name,
            'rationale' => $context->rationale,
            'status' => ApprovalStatus::Pending->value,
            'expires_at' => now()->addHours(config('integrations.approval.ttl_hours')),
        ]);

        IntegrationActionLog::create([
            'connection_id' => $connection->id,
            'user_id' => $connection->user_id,
            'approval_request_id' => $approval->id,
            'action_key' => $connection->kind.'.'.$action->key,
            'access' => $action->access->value,
            'actor_type' => $context->actor?->getMorphClass(),
            'actor_id' => $context->actor?->getKey(),
            'source' => $context->source,
            'params' => SecretRedactor::redact($params, $action->params),
            'status' => 'pending',
        ]);

        return ActionResult::pending($approval->id, $approval->summary);
    }

    public function approve(ApprovalRequest $approval, User $user, ?string $note = null): void
    {
        $approval->update([
            'status' => ApprovalStatus::Approved,
            'decided_at' => now(),
            'decided_by' => $user->id,
            'decision_note' => $note,
        ]);

        RunIntegrationActionJob::dispatch(
            $approval->connection_id,
            Str::after($approval->action_key, $approval->connection->kind.'.'),
            $approval->params,
            $approval->id,
            'approval',
        );
    }

    public function reject(ApprovalRequest $approval, User $user, ?string $note = null): void
    {
        $approval->update([
            'status' => ApprovalStatus::Rejected,
            'decided_at' => now(),
            'decided_by' => $user->id,
            'decision_note' => $note,
        ]);

        $approval->log?->update(['status' => 'denied']);
    }

    public function runApproval(ApprovalRequest $approval): void
    {
        $connection = $approval->connection;
        $connector = $this->registry->for($connection->kind);
        $action = collect($connector->actions())->firstWhere('key', Str::after($approval->action_key, $connection->kind.'.'));

        if (! $action instanceof Action) {
            $approval->update(['status' => ApprovalStatus::Failed]);

            return;
        }

        $result = $this->run($connection, $connector, $action, $approval->params, ExecutionContext::forUi($approval->user));

        $approval->update([
            'status' => $result->ok ? ApprovalStatus::Executed : ApprovalStatus::Failed,
            'executed_at' => now(),
        ]);
    }
```

En `execute()`, antes de `run()`:

```php
        if ($action->access->requiresApproval() && ! $context->preApproved) {
            return $this->queueApproval($connection, $action, $validated, $context);
        }
```

Y en `run()` aceptar `?ApprovalRequest $approval = null` para setear `approval_request_id` en el log.

`RunIntegrationActionJob`:

```php
<?php // app/Jobs/RunIntegrationActionJob.php

namespace App\Jobs;

use App\Integrations\IntegrationExecutor;
use App\Models\ApprovalRequest;
use App\Models\Connection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunIntegrationActionJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public int $connectionId,
        public string $actionKey,
        public array $params,
        public ?int $approvalId = null,
        public string $source = 'approval',
    ) {}

    public function handle(IntegrationExecutor $executor): void
    {
        $connection = Connection::find($this->connectionId);

        if (! $connection) {
            return;
        }

        if ($this->approvalId && $approval = ApprovalRequest::find($this->approvalId)) {
            $executor->runApproval($approval);

            return;
        }

        $executor->execute($connection, $this->actionKey, $this->params, \App\Integrations\Actions\ExecutionContext::forSchedule($connection->user));
    }
}
```

Comandos:

```php
<?php // app/Console/Commands/ExpireApprovalsCommand.php

namespace App\Console\Commands;

use App\Integrations\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use Illuminate\Console\Command;

class ExpireApprovalsCommand extends Command
{
    protected $signature = 'integrations:expire-approvals';

    protected $description = 'Expire pending integration approvals past their TTL';

    public function handle(): int
    {
        $count = ApprovalRequest::query()
            ->where('status', ApprovalStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => ApprovalStatus::Expired]);

        $this->info("Expired {$count} approvals.");

        return self::SUCCESS;
    }
}
```

```php
<?php // app/Console/Commands/PruneIntegrationActivityCommand.php

namespace App\Console\Commands;

use App\Models\IntegrationActionLog;
use Illuminate\Console\Command;

class PruneIntegrationActivityCommand extends Command
{
    protected $signature = 'integrations:prune-activity';

    protected $description = 'Prune integration activity logs past retention';

    public function handle(): int
    {
        $days = (int) config('integrations.retention_days');

        $count = IntegrationActionLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$count} activity logs.");

        return self::SUCCESS;
    }
}
```

`routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('integrations:expire-approvals')->everyTenMinutes();
Schedule::command('integrations:prune-activity')->daily();
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ExecutorApprovalTest --filter=ScheduledCommandsTest`
Expected: PASS (7 tests).

---

### Task 9: Conectores de prueba compartidos + executor en contenedor

**Files:**
- Create: `tests/Support/FakeConnector.php`
- Modify: `tests/Feature/Integrations/ExecutorReadTest.php`, `ExecutorApprovalTest.php` (importar desde `Tests\Support\`)
- Modify: `tests/Pest.php` (+ helper `executor()`)
- Test: `tests/Feature/Integrations/ExecutorContainerTest.php`

**Interfaces:**
- Consumes: Tasks 3, 7, 8.
- Produces: `Tests\Support\FakeConnector` reutilizable y `executor()` helper; `IntegrationExecutor` resoluble por container con el registry singleton.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Integrations/ExecutorContainerTest.php

use App\Integrations\IntegrationExecutor;

it('resolves the executor from the container with the configured registry', function () {
    expect(app(IntegrationExecutor::class))->toBeInstanceOf(IntegrationExecutor::class);
});
```

- [ ] **Step 2: Run test to verify it fails/passes**

Run: `php artisan test --compact --filter=ExecutorContainerTest`
Expected: PASS si el registry singleton está bindeado; si falla, revisar `IntegrationServiceProvider` en `bootstrap/providers.php`.

- [ ] **Step 3: Refactor**

Mover `ExecutorFakeConnector` a `tests/Support/FakeConnector.php` con namespace `Tests\Support`; helper en `tests/Pest.php`:

```php
function executor(): \App\Integrations\IntegrationExecutor
{
    return new \App\Integrations\IntegrationExecutor(
        new \App\Integrations\ConnectorRegistry([\Tests\Support\FakeConnector::class]),
    );
}
```

Actualizar imports en `ExecutorReadTest`/`ExecutorApprovalTest`.

- [ ] **Step 4: Run toda la carpeta de integraciones**

Run: `php artisan test --compact tests/Feature/Integrations tests/Unit/Integrations`
Expected: PASS (todos).

---

### Task 10: Metadata de UI en conectores, policies, requests y ConnectionController

**Files:**
- Modify: `app/Integrations/Contracts/Connector.php` (+`group(): string`, `description(): string`)
- Modify: `tests/Support/FakeConnector.php` (+ métodos nuevos)
- Create: `app/Policies/ConnectionPolicy.php`
- Create: `app/Http/Requests/Integrations/StoreConnectionRequest.php`, `UpdateConnectionRequest.php`
- Create: `app/Http/Controllers/Integrations/ConnectionController.php`
- Modify: `routes/settings.php`
- Test: `tests/Feature/Integrations/ConnectionSettingsTest.php`

**Interfaces:**
- Consumes: Tasks 1-9.
- Produces: `GET settings/connections` (page `settings/connections`), `POST settings/connections`, `PATCH settings/connections/{connection}`, `DELETE settings/connections/{connection}`, `POST settings/connections/test`. Props: `connections` (array), `catalog` (kinds con `kind/label/group/description/auth_fields/transports`), `flash`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Integrations/ConnectionSettingsTest.php

use App\Models\Connection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('renders the connections page with catalog and own connections only', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'fake']);
    Connection::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/connections')
            ->has('connections', 1)
            ->has('catalog')
            ->has('catalog.0.auth_fields')
            ->has('catalog.0.transports'));
});

it('stores a connection with encrypted credentials', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('connections.store'), [
        'kind' => 'fake',
        'name' => 'Mi fake',
        'auth_type' => 'api_token',
        'credentials' => ['token' => 'secret-token'],
        'base_url' => 'https://api.example.com',
        'transport' => 'direct',
        'enabled' => true,
    ])->assertRedirect(route('connections.index'));

    $connection = Connection::first();
    expect($connection->credentials['token'])->toBe('secret-token')
        ->and($connection->getRawOriginal('credentials'))->not->toContain('secret-token');
});

it('keeps credentials when update sends none', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['credentials' => ['token' => 'keep-me']]);

    $this->actingAs($user)->patch(route('connections.update', $connection), [
        'name' => 'Renombrada',
        'credentials' => [],
    ])->assertRedirect();

    expect($connection->fresh()->name)->toBe('Renombrada')
        ->and($connection->fresh()->credentials['token'])->toBe('keep-me');
});

it('forbids acting on foreign connections', function () {
    $connection = Connection::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('connections.update', $connection), ['name' => 'X'])
        ->assertNotFound();
});

it('tests a saved connection through the executor', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create(['kind' => 'fake']);

    $this->actingAs($user)
        ->post(route('connections.test'), ['connection_id' => $connection->id])
        ->assertRedirect();

    expect($connection->fresh()->last_tested_at)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ConnectionSettingsTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

Extender `Connector`:

```php
    public function group(): string;          // 'Dev', 'Infra', ...
    public function description(): string;    // copy para el wizard
```

Añadirlos a `Tests\Support\FakeConnector` (`group: 'Test'`, `description: 'Conector de prueba'`).

`ConnectionPolicy` (auto-discovery por modelo):

```php
<?php // app/Policies/ConnectionPolicy.php

namespace App\Policies;

use App\Models\Connection;
use App\Models\User;

class ConnectionPolicy
{
    public function view(User $user, Connection $connection): bool
    {
        return $connection->user_id === $user->id;
    }

    public function update(User $user, Connection $connection): bool
    {
        return $this->view($user, $connection);
    }

    public function delete(User $user, Connection $connection): bool
    {
        return $this->view($user, $connection);
    }
}
```

`StoreConnectionRequest`:

```php
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(array_keys(app(ConnectorRegistry::class)->all()))],
            'name' => ['required', 'string', 'max:100'],
            'auth_type' => ['required', Rule::enum(AuthType::class)],
            'credentials' => ['nullable', 'array'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'transport' => ['required', Rule::enum(TransportKind::class)],
            'transport_config' => ['nullable', 'array'],
            'options' => ['nullable', 'array'],
            'enabled' => ['required', 'boolean'],
        ];
    }
```

`UpdateConnectionRequest`: igual pero todos `sometimes`; `credentials` `nullable|array` y en el controller `if (blank($credentials)) unset(...)`.

`ConnectionController`:

```php
    public function index(Request $request): Response
    {
        $connections = Connection::query()
            ->forUser($request->user())
            ->orderBy('kind')
            ->orderBy('name')
            ->get()
            ->map(fn (Connection $c) => [
                'id' => $c->id,
                'kind' => $c->kind,
                'name' => $c->name,
                'auth_type' => $c->auth_type->value,
                'base_url' => $c->base_url,
                'transport' => $c->transport->value,
                'enabled' => $c->enabled,
                'status' => $c->status->value,
                'status_message' => $c->status_message,
                'last_tested_at' => $c->last_tested_at?->toIso8601String(),
                'last_used_at' => $c->last_used_at?->toIso8601String(),
            ]);

        $catalog = collect(app(ConnectorRegistry::class)->all())
            ->map(fn (Connector $connector) => [
                'kind' => $connector->kind(),
                'label' => $connector->label(),
                'group' => $connector->group(),
                'description' => $connector->description(),
                'auth_fields' => array_map(fn (AuthField $f) => [
                    'name' => $f->name, 'type' => $f->type, 'label' => $f->label,
                    'required' => $f->required, 'help' => $f->help, 'options' => $f->options,
                ], $connector->authFields()),
                'transports' => $connector->transports(),
            ])
            ->values();

        return Inertia::render('settings/connections', [
            'connections' => $connections,
            'catalog' => $catalog,
        ]);
    }

    public function test(Request $request, IntegrationExecutor $executor): RedirectResponse
    {
        $validated = $request->validate([
            'connection_id' => ['nullable', 'integer'],
            'kind' => ['nullable', 'string'],
            'auth_type' => ['nullable', Rule::enum(AuthType::class)],
            'credentials' => ['nullable', 'array'],
            'base_url' => ['nullable', 'string', 'max:500'],
            'transport' => ['nullable', Rule::enum(TransportKind::class)],
            'transport_config' => ['nullable', 'array'],
        ]);

        if (! empty($validated['connection_id'])) {
            $connection = Connection::query()->forUser($request->user())->findOrFail($validated['connection_id']);
        } else {
            $connection = new Connection($validated + ['user_id' => $request->user()->id]);
        }

        $result = $executor->testConnection($connection);

        return back()->with('test_result', ['ok' => $result->ok, 'message' => $result->message]);
    }
```

> `IntegrationExecutor::testConnection()` sólo persiste si `$connection->exists` (ajustar Task 7 en consecuencia).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=ConnectionSettingsTest`
Expected: PASS (5 tests).

---

### Task 11: Bandeja de aprobaciones, actividad y rutas

**Files:**
- Create: `app/Http/Requests/Integrations/DecideApprovalRequest.php`
- Create: `app/Policies/ApprovalRequestPolicy.php`
- Create: `app/Http/Controllers/Integrations/ApprovalController.php`, `ActivityController.php`
- Create: `routes/integrations.php`; Modify: `routes/web.php` (`require`)
- Test: `tests/Feature/Integrations/ApprovalInboxTest.php`, `tests/Feature/Integrations/ActivityTest.php`

**Interfaces:**
- Consumes: Tasks 7-8.
- Produces: `GET integrations/approvals` (`integrations/approvals`), `POST integrations/approvals/{approval}/approve|reject`, `GET integrations/activity` (`integrations/activity`).

- [ ] **Step 1: Write the failing tests**

```php
<?php // tests/Feature/Integrations/ApprovalInboxTest.php

use App\Integrations\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('lists only the user pending approvals and history', function () {
    $user = User::factory()->create();
    $mine = ApprovalRequest::factory()->create(['user_id' => $user->id, 'connection_id' => \App\Models\Connection::factory()->for($user)]);
    ApprovalRequest::factory()->create();

    $this->actingAs($user)->get(route('integrations.approvals.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('integrations/approvals')
            ->has('pending', 1)
            ->has('history'));
});

it('approves an own approval', function () {
    Bus::fake();
    $user = User::factory()->create();
    $approval = ApprovalRequest::factory()->create(['user_id' => $user->id, 'connection_id' => \App\Models\Connection::factory()->for($user)]);

    $this->actingAs($user)->post(route('integrations.approvals.approve', $approval), ['note' => 'ok'])
        ->assertRedirect();

    expect($approval->fresh()->status)->toBe(ApprovalStatus::Approved);
});

it('rejects an own approval and 404s foreign ones', function () {
    $user = User::factory()->create();
    $approval = ApprovalRequest::factory()->create(['user_id' => $user->id, 'connection_id' => \App\Models\Connection::factory()->for($user)]);
    $foreign = ApprovalRequest::factory()->create();

    $this->actingAs($user)->post(route('integrations.approvals.reject', $approval))->assertRedirect();
    expect($approval->fresh()->status)->toBe(ApprovalStatus::Rejected);

    $this->actingAs($user)->post(route('integrations.approvals.reject', $foreign))->assertNotFound();
});
```

```php
<?php // tests/Feature/Integrations/ActivityTest.php

use App\Models\Connection;
use App\Models\IntegrationActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('lists activity scoped to the user with filters', function () {
    $user = User::factory()->create();
    $connection = Connection::factory()->for($user)->create();
    IntegrationActionLog::factory()->create(['user_id' => $user->id, 'connection_id' => $connection->id, 'status' => 'success']);
    IntegrationActionLog::factory()->create();

    $this->actingAs($user)->get(route('integrations.activity.index', ['status' => 'success']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('integrations/activity')
            ->has('logs.data', 1)
            ->has('filters'));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=ApprovalInboxTest --filter=ActivityTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

`ApprovalController@index` → props `pending` (pending + not expired, latest first) y `history` (últimas 50 decididas). `approve/reject` autorizan por policy, llaman al executor y `back()`. `DecideApprovalRequest`: `note => nullable|string|max:500`.

`ApprovalRequestPolicy`: `view/decide` → `$approval->user_id === $user->id`.

`ActivityController@index` → filtros `connection_id`, `status`, `action_key`, paginación 50, props `logs` (resource array con `connection_name`), `connections` (para el select), `filters`.

`routes/integrations.php`:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('integrations/approvals', [ApprovalController::class, 'index'])->name('integrations.approvals.index');
    Route::post('integrations/approvals/{approval}/approve', [ApprovalController::class, 'approve'])->middleware('throttle:30,1')->name('integrations.approvals.approve');
    Route::post('integrations/approvals/{approval}/reject', [ApprovalController::class, 'reject'])->middleware('throttle:30,1')->name('integrations.approvals.reject');
    Route::get('integrations/activity', [ActivityController::class, 'index'])->name('integrations.activity.index');
});
```

`routes/web.php`: agregar `require __DIR__.'/integrations.php';` junto al de settings.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter=ApprovalInboxTest --filter=ActivityTest`
Expected: PASS (4 tests).

---

### Task 12: OAuth broker + preset Google + controller

**Files:**
- Create: `app/Integrations/OAuth/OAuthToken.php`, `OAuthPreset.php`, `OAuthBroker.php`, `Presets/GoogleOAuthPreset.php`
- Create: `app/Http/Controllers/Integrations/OAuthController.php`
- Modify: `routes/integrations.php` (+ redirect/callback)
- Modify: `config/services.php` (+ `google.oauth`)
- Test: `tests/Feature/Integrations/OAuthTest.php`

**Interfaces:**
- Consumes: `Connection` (auth_type `oauth2`), `Http`.
- Produces: `OAuthBroker::redirectUrl(Connection): string`; `handleCallback(Connection, string $code, string $state): OAuthToken`; `refreshIfNeeded(Connection): Connection`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Integrations/OAuthTest.php

use App\Integrations\OAuth\OAuthBroker;
use App\Integrations\OAuth\OAuthToken;
use App\Models\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('builds a google authorize url with state and pkce', function () {
    config(['services.google.oauth.client_id' => 'client-1', 'services.google.oauth.client_secret' => 'shh']);

    $connection = Connection::factory()->make(['kind' => 'google', 'auth_type' => 'oauth2']);

    $url = app(OAuthBroker::class)->redirectUrl($connection);

    expect($url)->toContain('accounts.google.com/o/oauth2/v2/auth')
        ->and($url)->toContain('client_id=client-1')
        ->and($url)->toContain('state=')
        ->and($url)->toContain('code_challenge=');
});

it('exchanges a code and stores encrypted tokens', function () {
    config(['services.google.oauth.client_id' => 'client-1', 'services.google.oauth.client_secret' => 'shh']);
    Http::fake(['oauth2.googleapis.com/token' => Http::response([
        'access_token' => 'at', 'refresh_token' => 'rt', 'expires_in' => 3600,
    ])]);

    $connection = Connection::factory()->create(['kind' => 'google', 'auth_type' => 'oauth2', 'credentials' => []]);
    $broker = app(OAuthBroker::class);

    $url = $broker->redirectUrl($connection);
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    $token = $broker->handleCallback($connection, 'the-code', $query['state']);

    expect($token->accessToken)->toBe('at')
        ->and($connection->fresh()->credentials['access_token'])->toBe('at')
        ->and($connection->fresh()->getRawOriginal('credentials'))->not->toContain('"at"');
});

it('rejects an invalid oauth state', function () {
    $connection = Connection::factory()->create(['kind' => 'google', 'auth_type' => 'oauth2']);

    app(OAuthBroker::class)->handleCallback($connection, 'code', 'bogus-state');
})->throws(\Illuminate\Validation\ValidationException::class);

it('refreshes an expired token', function () {
    config(['services.google.oauth.client_id' => 'client-1', 'services.google.oauth.client_secret' => 'shh']);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'new', 'expires_in' => 3600])]);

    $connection = Connection::factory()->create([
        'kind' => 'google',
        'auth_type' => 'oauth2',
        'credentials' => ['access_token' => 'old', 'refresh_token' => 'rt', 'expires_at' => now()->subMinute()->toIso8601String()],
    ]);

    app(OAuthBroker::class)->refreshIfNeeded($connection);

    expect($connection->fresh()->credentials['access_token'])->toBe('new');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=OAuthTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

`OAuthToken` (`fromArray`, `toArray`, `isExpired`), `OAuthPreset` (authorizeUrl/exchange/refresh/scopes/usesPkce/clientId/clientSecret/redirectUri), `GoogleOAuthPreset` (endpoints Google + scopes `drive`, `gmail.readonly`, `gmail.send`, `calendar.events`), `OAuthBroker`:

```php
public function redirectUrl(Connection $connection): string
{
    $preset = $this->presetFor($connection);
    $state = Str::random(40);
    $verifier = Str::random(64);

    Cache::put("oauth:state:{$state}", [
        'connection_id' => $connection->id,
        'verifier' => $verifier,
    ], now()->addMinutes(10));

    return $preset->authorizeUrl($connection, $state, $verifier);
}

public function handleCallback(Connection $connection, string $code, string $state): OAuthToken
{
    $payload = Cache::pull("oauth:state:{$state}");

    if (! $payload || $payload['connection_id'] !== $connection->id) {
        throw ValidationException::withMessages(['state' => 'Estado OAuth inválido o expirado.']);
    }

    $token = $this->presetFor($connection)->exchange($connection, $code, $payload['verifier']);

    $connection->forceFill([
        'credentials' => array_merge($connection->credentials ?? [], $token->toArray()),
        'status' => ConnectionStatus::Ok,
        'status_message' => null,
    ])->save();

    return $token;
}

public function refreshIfNeeded(Connection $connection): Connection
{
    $token = OAuthToken::fromArray($connection->credentials ?? []);

    if (! $token->isExpired(60) || ! $token->refreshToken) {
        return $connection;
    }

    $refreshed = $this->presetFor($connection)->refresh($connection, $token);

    $connection->forceFill([
        'credentials' => array_merge($connection->credentials ?? [], $refreshed->toArray()),
    ])->save();

    return $connection;
}
```

`OAuthController@redirect`: verifica `auth_type === oauth2`, redirige a `redirectUrl()`. `@callback`: valida `state`/`code`, `handleCallback`, flash success, `to_route('connections.index')`; en fallo, flash error + status `expired`.

`config/services.php`:

```php
    'google' => [
        'oauth' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        ],
    ],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=OAuthTest`
Expected: PASS (4 tests).

---

### Task 13: Tools de IA + wiring del agente

**Files:**
- Create: `app/Ai/Tools/IntegrationCatalogTool.php`, `IntegrationCallTool.php`
- Modify: `app/Ai/Agents/MegalomaniacAgent.php` (+ tools)
- Test: `tests/Feature/Integrations/IntegrationToolsTest.php`

**Interfaces:**
- Consumes: `ConnectorRegistry`, `IntegrationExecutor`, `Connection`.
- Produces: tools `integration_catalog` e `integration_call` usadas por `MegalomaniacAgent`.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Integrations/IntegrationToolsTest.php

use App\Ai\Agents\MegalomaniacAgent;
use App\Ai\Tools\IntegrationCallTool;
use App\Ai\Tools\IntegrationCatalogTool;
use App\Integrations\IntegrationExecutor;
use App\Models\Connection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

it('lists enabled connections and actions in the catalog tool', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'fake', 'name' => 'Mi fake']);

    $payload = json_decode((string) (new IntegrationCatalogTool($user))->handle(new Request([])), true);

    expect($payload['connections'])->toHaveCount(1)
        ->and($payload['connections'][0]['actions'][0]['key'])->toBe('ping');
});

it('executes reads inline through the call tool', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'fake', 'name' => 'Mi fake']);

    $tool = new IntegrationCallTool($user, app(IntegrationExecutor::class));
    $payload = json_decode((string) $tool->handle(new Request([
        'connection' => 'Mi fake', 'action' => 'ping', 'params' => [],
    ])), true);

    expect($payload['status'])->toBe('success');
});

it('returns pending approval for writes', function () {
    $user = User::factory()->create();
    Connection::factory()->for($user)->create(['kind' => 'fake', 'name' => 'Mi fake']);

    $tool = new IntegrationCallTool($user, app(IntegrationExecutor::class));
    $payload = json_decode((string) $tool->handle(new Request([
        'connection' => 'Mi fake', 'action' => 'write', 'params' => [],
    ])), true);

    expect($payload['status'])->toBe('pending_approval')->and($payload['approval_id'])->toBeInt();
});

it('registers both tools on the agent', function () {
    $user = User::factory()->create();
    $tools = (new MegalomaniacAgent($user))->tools();

    $classes = collect($tools)->map(fn ($tool) => $tool::class)->all();

    expect($classes)->toContain(IntegrationCatalogTool::class, IntegrationCallTool::class);
});
```

> Para este test, el registry debe incluir `Tests\Support\FakeConnector`: bindearlo en el test con `app()->instance(ConnectorRegistry::class, new ConnectorRegistry([FakeConnector::class]))` o extender `config('integrations.connectors')` en el test.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=IntegrationToolsTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

`IntegrationCatalogTool` (params: `connection`, `search`): devuelve JSON `{connections: [{name, kind, actions: [{key, label, description, access, params}]}]}` filtrando por nombre y/o texto.

`IntegrationCallTool` (schema: `connection` string required, `action` string required, `params` object):

```php
public function handle(Request $request): Stringable|string
{
    $connection = Connection::query()
        ->forUser($this->user)
        ->enabled()
        ->where(fn ($q) => $q->where('name', $request['connection'])
            ->orWhere('id', $request['connection']))
        ->first();

    if (! $connection) {
        return json_encode(['status' => 'error', 'message' => 'Conexión no encontrada. Usá integration_catalog para ver las disponibles.']);
    }

    $result = $this->executor->execute(
        $connection,
        (string) $request['action'],
        (array) ($request['params'] ?? []),
        ExecutionContext::forAgent($this->user),
    );

    if ($result->pending) {
        return json_encode([
            'status' => 'pending_approval',
            'approval_id' => $result->approvalId,
            'summary' => $result->summary,
            'message' => 'La acción quedó pendiente de aprobación del usuario en Aprobaciones.',
        ]);
    }

    return json_encode([
        'status' => $result->ok ? 'success' : 'error',
        'summary' => $result->summary,
        'data' => $result->data,
        'error' => $result->error,
    ]);
}
```

Agregar ambas a `MegalomaniacAgent::tools()`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=IntegrationToolsTest`
Expected: PASS (4 tests).

---

### Task 14: Conector GitHub

**Files:**
- Create: `app/Integrations/Connectors/AbstractConnector.php` (si no existe: helper `request()` vía `TransportFactory`)
- Create: `app/Integrations/Connectors/Github/GithubConnector.php`
- Test: `tests/Feature/Integrations/Connectors/GithubConnectorTest.php`

**Interfaces:**
- Consumes: `TransportFactory`, `HttpCall`, `Action`, etc.
- Produces: kind `github`, group `Dev`, auth `api_token` (`token`), transport `direct`, 16 acciones.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Integrations/Connectors/GithubConnectorTest.php

use App\Integrations\Connectors\Github\GithubConnector;
use App\Integrations\Transports\HttpCall;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

function github(): Connection
{
    return Connection::factory()->make([
        'kind' => 'github',
        'base_url' => 'https://api.github.com',
        'credentials' => ['token' => 'ghp_test'],
    ]);
}

it('declares the github catalog', function () {
    $keys = collect((new GithubConnector)->actions())->pluck('key')->all();

    expect($keys)->toContain('repos.list', 'issues.create', 'pulls.list', 'actions.runs.rerun', 'search.code')
        ->and((new GithubConnector)->authFields()[0]->name)->toBe('token');
});

it('lists repositories', function () {
    Http::fake(['api.github.com/user/repos*' => Http::response([['full_name' => 'a/b']], 200)]);

    $result = (new GithubConnector)->execute(github(), 'repos.list', ['per_page' => 5]);

    expect($result->ok)->toBeTrue()->and($result->data[0]['full_name'])->toBe('a/b');
});

it('creates an issue', function () {
    Http::fake(['api.github.com/repos/a/b/issues' => Http::response(['number' => 7, 'title' => 'Bug'], 201)]);

    $result = (new GithubConnector)->execute(github(), 'issues.create', [
        'owner' => 'a', 'repo' => 'b', 'title' => 'Bug',
    ]);

    expect($result->ok)->toBeTrue()->and($result->data['number'])->toBe(7);

    Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->data()['title'] === 'Bug');
});

it('maps github errors', function () {
    Http::fake(['api.github.com/*' => Http::response(['message' => 'Not Found'], 404)]);

    $result = (new GithubConnector)->execute(github(), 'repos.get', ['owner' => 'a', 'repo' => 'missing']);

    expect($result->ok)->toBeFalse()->and($result->error)->toContain('404');
});

it('tests the connection against /user', function () {
    Http::fake(['api.github.com/user' => Http::response(['login' => 'me'], 200)]);

    $result = (new GithubConnector)->test(github());

    expect($result->ok)->toBeTrue()->and($result->meta['login'])->toBe('me');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GithubConnectorTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

`AbstractConnector`:

```php
abstract class AbstractConnector implements Connector
{
    public function group(): string { return 'Otros'; }

    public function transports(): array { return ['direct']; }

    protected function request(Connection $connection, HttpCall $call): HttpResult
    {
        return TransportFactory::make($connection)->request($connection, $call);
    }

    protected function exec(Connection $connection, string $command): ExecResult
    {
        return TransportFactory::make($connection)->exec($connection, $command);
    }
}
```

`GithubConnector` — mapa de acciones (todas `Read` salvo las marcadas `Write`):

| key | método | endpoint | params |
|---|---|---|---|
| `repos.list` | GET | `/user/repos` | `per_page`, `sort` |
| `repos.get` | GET | `/repos/{owner}/{repo}` | `owner*`, `repo*` |
| `issues.list` | GET | `/repos/{owner}/{repo}/issues` | `owner*`, `repo*`, `state`, `per_page` |
| `issues.get` | GET | `/repos/{owner}/{repo}/issues/{number}` | `owner*`, `repo*`, `number*` |
| `issues.create` (w) | POST | `/repos/{owner}/{repo}/issues` | `owner*`, `repo*`, `title*`, `body` |
| `issues.comment` (w) | POST | `/repos/{owner}/{repo}/issues/{number}/comments` | `owner*`, `repo*`, `number*`, `body*` |
| `issues.close` (w) | PATCH | `/repos/{owner}/{repo}/issues/{number}` | `owner*`, `repo*`, `number*` (body `state=closed`) |
| `pulls.list` | GET | `/repos/{owner}/{repo}/pulls` | `owner*`, `repo*`, `state` |
| `pulls.get` | GET | `/repos/{owner}/{repo}/pulls/{number}` | `owner*`, `repo*`, `number*` |
| `pulls.files` | GET | `/repos/{owner}/{repo}/pulls/{number}/files` | `owner*`, `repo*`, `number*` |
| `pulls.create` (w) | POST | `/repos/{owner}/{repo}/pulls` | `owner*`, `repo*`, `title*`, `head*`, `base*`, `body` |
| `actions.runs.list` | GET | `/repos/{owner}/{repo}/actions/runs` | `owner*`, `repo*`, `per_page` |
| `actions.runs.rerun` (w) | POST | `/repos/{owner}/{repo}/actions/runs/{run_id}/rerun` | `owner*`, `repo*`, `run_id*` |
| `notifications.list` | GET | `/notifications` | `all`, `per_page` |
| `search.code` | GET | `/search/code` | `q*` |
| `search.issues` | GET | `/search/issues` | `q*` |

Implementación: `execute()` con `match ($key)` devolviendo `$this->ok($response, $mensaje)` / `$this->failed($response)` helpers; `test()` llama `GET /user`.

`$this->ok()` / `$this->failed()`: helpers en `AbstractConnector` que construyen `ActionResult` desde `HttpResult` (mapea errores a `failure`).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=GithubConnectorTest`
Expected: PASS (5 tests).

---

### Task 15: Conector Google (Drive + Gmail + Calendar)

**Files:**
- Modify: `app/Integrations/Transports/HttpCall.php` (+`body`, `contentType`) y `DirectTransport` (envío de body crudo)
- Create: `app/Integrations/Connectors/Google/GoogleConnector.php`
- Test: `tests/Feature/Integrations/Connectors/GoogleConnectorTest.php`

**Interfaces:**
- Consumes: OAuthBroker (refresh antes de cada request).
- Produces: kind `google`, group `Google`, auth `oauth2`, transport `direct`, acciones Drive/Gmail/Calendar (ver spec §Catálogo).

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Integrations/Connectors/GoogleConnectorTest.php

use App\Integrations\Connectors\Google\GoogleConnector;
use App\Models\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function googleConn(): Connection
{
    return Connection::factory()->make([
        'kind' => 'google',
        'auth_type' => 'oauth2',
        'base_url' => 'https://www.googleapis.com',
        'credentials' => ['access_token' => 'at', 'refresh_token' => 'rt', 'expires_at' => now()->addHour()->toIso8601String()],
    ]);
}

it('lists drive files', function () {
    Http::fake(['www.googleapis.com/drive/v3/files*' => Http::response(['files' => [['id' => 'f1']]], 200)]);

    $result = (new GoogleConnector)->execute(googleConn(), 'drive.files.list', ['q' => "name contains 'x'"]);

    expect($result->ok)->toBeTrue()->and($result->data['files'][0]['id'])->toBe('f1');
});

it('sends a gmail message', function () {
    Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm1'], 200)]);

    $result = (new GoogleConnector)->execute(googleConn(), 'gmail.messages.send', [
        'to' => 'a@b.com', 'subject' => 'Hola', 'body' => 'Texto',
    ]);

    expect($result->ok)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'gmail/v1/users/me/messages/send')
        && str_contains(json_encode($request->data()), 'raw'));
});

it('lists calendar events', function () {
    Http::fake(['www.googleapis.com/calendar/v3/calendars/*' => Http::response(['items' => [['id' => 'e1']]], 200)]);

    $result = (new GoogleConnector)->execute(googleConn(), 'calendar.events.list', []);

    expect($result->ok)->toBeTrue()->and($result->data['items'][0]['id'])->toBe('e1');
});

it('refreshes the token before requests when expired', function () {
    config(['services.google.oauth.client_id' => 'c', 'services.google.oauth.client_secret' => 's']);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new', 'expires_in' => 3600]),
        'www.googleapis.com/*' => Http::response(['files' => []], 200),
    ]);

    $connection = googleConn();
    $connection->credentials = ['access_token' => 'old', 'refresh_token' => 'rt', 'expires_at' => now()->subMinute()->toIso8601String()];
    $connection->exists = true;
    $connection->id = 999;
    $connection->user_id = 1;

    (new GoogleConnector)->execute($connection, 'drive.files.list', []);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer new'));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GoogleConnectorTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

En `GoogleConnector::execute()`: llamar `app(OAuthBroker::class)->refreshIfNeeded($connection)` primero. Mapa de acciones:

| key | método | endpoint | notas |
|---|---|---|---|
| `drive.files.list` | GET | `/drive/v3/files` | params `q`, `page_size`, `fields` |
| `drive.files.get` | GET | `/drive/v3/files/{file_id}` | params `file_id*`, `fields` |
| `drive.files.create` (w) | POST | `/upload/drive/v3/files` | `name*`, `content*`, `mime_type`; `uploadType=media` + body crudo |
| `drive.files.move` (w) | PATCH | `/drive/v3/files/{file_id}` | `file_id*`, `add_parents*`, `remove_parents` |
| `drive.files.delete` (d) | DELETE | `/drive/v3/files/{file_id}` | `file_id*` |
| `gmail.messages.list` | GET | `https://gmail.googleapis.com/gmail/v1/users/me/messages` | `q`, `max_results` |
| `gmail.messages.get` | GET | `https://gmail.googleapis.com/gmail/v1/users/me/messages/{id}` | `id*`, `format` |
| `gmail.messages.send` (w) | POST | `https://gmail.googleapis.com/gmail/v1/users/me/messages/send` | `to*`, `subject*`, `body*` → RFC822 base64url |
| `gmail.labels.list` | GET | `https://gmail.googleapis.com/gmail/v1/users/me/labels` | |
| `calendar.events.list` | GET | `https://www.googleapis.com/calendar/v3/calendars/{calendar_id}/events` | `calendar_id` (default `primary`), `time_min`, `max_results` |
| `calendar.events.create` (w) | POST | `.../calendars/{calendar_id}/events` | `summary*`, `start*`, `end*`, `description` |
| `calendar.events.update` (w) | PATCH | `.../calendars/{calendar_id}/events/{event_id}` | `event_id*`, `summary`, `start`, `end` |
| `calendar.calendars.list` | GET | `/calendar/v3/users/me/calendarList` | |

- Gmail send: construir RFC822 con headers `To/Subject` + body, `base64url` (str_replace `+/` y rtrim `=`).
- `test()`: `GET /drive/v3/about?fields=user` → meta `email`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=GoogleConnectorTest`
Expected: PASS (4 tests).

---

### Task 16: Conector Docker (socket + ssh_exec + api directa)

**Files:**
- Create: `app/Integrations/Connectors/Docker/DockerConnector.php`
- Test: `tests/Feature/Integrations/Connectors/DockerConnectorTest.php`

**Interfaces:**
- Consumes: `LocalSocketTransport`, `SshExecTransport`, `DirectTransport`.
- Produces: kind `docker`, group `Infra`, transports `local_socket|ssh_exec|direct`, acciones contenedores/imágenes/volúmenes/system.

- [ ] **Step 1: Write the failing test**

```php
<?php // tests/Feature/Integrations/Connectors/DockerConnectorTest.php

use App\Integrations\Connectors\Docker\DockerConnector;
use App\Integrations\Enums\TransportKind;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

it('declares docker actions and transports', function () {
    $connector = new DockerConnector;

    expect(collect($connector->actions())->pluck('key'))->toContain('containers.list', 'containers.restart', 'containers.logs', 'system.df')
        ->and($connector->transports())->toBe(['local_socket', 'ssh_exec', 'direct']);
});

it('lists containers over the docker socket', function () {
    Http::fake(['localhost/*' => Http::response([['Names' => ['/jellyfin']]], 200)]);

    $connection = Connection::factory()->make([
        'kind' => 'docker',
        'auth_type' => 'none',
        'base_url' => 'http://localhost',
        'transport' => TransportKind::LocalSocket,
        'transport_config' => ['socket_path' => '/var/run/docker.sock'],
    ]);

    $result = (new DockerConnector)->execute($connection, 'containers.list', ['all' => true]);

    expect($result->ok)->toBeTrue()->and($result->data[0]['Names'][0])->toBe('/jellyfin');
});

it('restarts a container via the api', function () {
    Http::fake(['localhost/*' => Http::response('', 204)]);

    $connection = Connection::factory()->make([
        'kind' => 'docker', 'auth_type' => 'none', 'base_url' => 'http://localhost',
        'transport' => TransportKind::LocalSocket,
        'transport_config' => ['socket_path' => '/var/run/docker.sock'],
    ]);

    $result = (new DockerConnector)->execute($connection, 'containers.restart', ['name' => 'jellyfin']);

    expect($result->ok)->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), '/containers/jellyfin/restart'));
});

it('lists containers via ssh exec on remote hosts', function () {
    Process::fake(['*' => Process::result(output: "{\"Names\":\"jellyfin\"}\n", exitCode: 0)]);

    $connection = Connection::factory()->make([
        'kind' => 'docker', 'auth_type' => 'none',
        'transport' => TransportKind::SshExec,
        'transport_config' => ['ssh_host' => '10.0.0.9', 'ssh_user' => 'root', 'key_path' => '/k'],
    ]);

    $result = (new DockerConnector)->execute($connection, 'containers.list', []);

    expect($result->ok)->toBeTrue()->and($result->data[0]['Names'])->toBe('jellyfin');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DockerConnectorTest`
Expected: FAIL.

- [ ] **Step 3: Write minimal implementation**

Mapa (API Docker Engine; con `ssh_exec` se traduce a CLI):

| key | API | CLI (ssh_exec) | access |
|---|---|---|---|
| `containers.list` | GET `/containers/json?all=` | `docker ps --all --format '{{json .}}'` | read |
| `containers.inspect` (w) | GET `/containers/{name}/json` | `docker inspect {name}` | read |
| `containers.logs` | GET `/containers/{name}/logs?stdout=1&stderr=1&tail=` | `docker logs --tail {tail} {name}` | read |
| `containers.start` | POST `/containers/{name}/start` | `docker start {name}` | write |
| `containers.stop` | POST `/containers/{name}/stop` | `docker stop {name}` | write |
| `containers.restart` | POST `/containers/{name}/restart` | `docker restart {name}` | write |
| `containers.pause` | POST `/containers/{name}/pause` | `docker pause {name}` | write |
| `containers.unpause` | POST `/containers/{name}/unpause` | `docker unpause {name}` | write |
| `containers.remove` | DELETE `/containers/{name}?force=` | `docker rm -f {name}` | destructive |
| `images.list` | GET `/images/json` | `docker images --format '{{json .}}'` | read |
| `images.prune` | POST `/images/prune` | `docker image prune -f` | destructive |
| `volumes.list` | GET `/volumes` | `docker volume ls --format '{{json .}}'` | read |
| `networks.list` | GET `/networks` | `docker network ls --format '{{json .}}'` | read |
| `system.df` | GET `/system/df` | `docker system df` | read |

Implementación: helper privado `api(Connection, method, path, query): ActionResult` y `viaSsh(Connection, string $command): ActionResult` (parsea líneas JSON con `json_decode`). `execute()` decide por `$connection->transport === TransportKind::SshExec`. `test()`: API `GET /_ping`; SSH `docker version --format '{{.Server.Version}}'`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=DockerConnectorTest`
Expected: PASS (4 tests).

---

### Task 17: UI — Settings → Conexiones (lista + wizard)

**Files:**
- Modify: `resources/js/layouts/settings/layout.tsx` (+ item "Conexiones")
- Create: `resources/js/types/integrations.ts`
- Create: `resources/js/components/integrations/{StatusBadge,ConnectionCard,ConnectionWizard,EmptyState}.tsx`
- Create: `resources/js/pages/settings/connections.tsx`
- Test: `npm run types`, `npm run lint`, `npm run build` + QA Playwright

**Interfaces:**
- Consumes: props `connections`, `catalog` (Task 10) + endpoints store/update/destroy/test.
- Produces: página funcional con estados loading/empty/error/success/disabled.

- [ ] **Step 1: Types**

```ts
// resources/js/types/integrations.ts
export type AuthFieldType = 'text' | 'password' | 'url' | 'select' | 'oauth' | 'qr';

export interface AuthField {
    name: string;
    type: AuthFieldType;
    label: string;
    required: boolean;
    help?: string | null;
    options: Record<string, string>;
}

export interface ConnectionCatalogItem {
    kind: string;
    label: string;
    group: string;
    description: string;
    auth_fields: AuthField[];
    transports: string[];
}

export interface ConnectionRow {
    id: number;
    kind: string;
    name: string;
    auth_type: string;
    base_url: string | null;
    transport: string;
    enabled: boolean;
    status: 'unknown' | 'ok' | 'error' | 'expired';
    status_message: string | null;
    last_tested_at: string | null;
    last_used_at: string | null;
}
```

- [ ] **Step 2: Componentes**

- `StatusBadge`: puntito + label por estado con tokens (`ok` → `text-primary`, `error` → `text-destructive`, `expired`/`unknown` → `text-muted-foreground`), `title={status_message}`.
- `ConnectionCard`: nombre, `kind` label, `StatusBadge`, última prueba/uso relativo, switch enabled (PATCH), botones Probar (`router.post('/settings/connections/test', {connection_id})`), Editar (abre wizard), Eliminar (confirm).
- `ConnectionWizard`: `Dialog` (desktop) / `Sheet` (mobile) en 4 pasos: (1) grid de `catalog` agrupado por `group` con `label`+`description`; (2) `AuthFieldsForm` dinámico desde `auth_fields` + botón "Conectar con OAuth" si `auth_type === 'oauth2'` (link a `/integrations/oauth/{id}/redirect`, requiere guardar); (3) `TransportFields` (select de `transports` + campos SSH si aplica); (4) Probar (`/settings/connections/test` con draft) + Guardar (`useForm` post/patch). Validación client-side mínima + `InputError` server-side.
- `EmptyState`: copy "Todavía no hay conexiones" + CTA "Nueva conexión".

- [ ] **Step 3: Página**

`connections.tsx`: `MainLayout` + `SettingsLayout`; header con botón "Nueva conexión"; agrupación por `group`; `ConnectionCard` grid (`md:grid-cols-2`); flash de `test_result` (`ok` → banner primary, `!ok` → banner destructive con mensaje); estado vacío con `EmptyState`. Todo con tokens Ember y componentes existentes.

- [ ] **Step 4: Verificar**

Run: `npm run types && npm run lint && npm run build`
Expected: sin errores.

---

### Task 18: UI — Aprobaciones, Actividad y badge en sidebar

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (+ share `approvals_pending_count`)
- Modify: `resources/js/components/app-sidebar.tsx` (+ item Aprobaciones con badge; usa `usePage().props`)
- Create: `resources/js/components/integrations/ApprovalCard.tsx`
- Create: `resources/js/pages/integrations/{approvals,activity}.tsx`
- Test: `tests/Feature/Integrations/SharedPropsTest.php` + `npm run types`

**Interfaces:**
- Consumes: props de Tasks 11.
- Produces: bandeja funcional + badge compartido.

- [ ] **Step 1: Shared prop**

```php
// HandleInertiaRequests::share()
'approvals_pending_count' => fn () => $request->user()
    ? \App\Models\ApprovalRequest::query()->forUser($request->user())->pending()->count()
    : 0,
```

- [ ] **Step 2: Página approvals**

`Header` + tabs Pendientes/Historial; `ApprovalCard` con `summary`, conexión, `access` badge (Write/Destructive), `rationale`, tabla de `params` (clave/valor, JSON formateado), expiración relativa, acciones Aprobar (con nota opcional) / Rechazar (con nota). Estado vacío: "No hay aprobaciones pendientes". En `history`, mostrar status final y fecha.

- [ ] **Step 3: Página activity**

Tabla densa: fecha, conexión, `action_key`, `access`, `status`, duración, actor, source; filtros en header (conexión, status, acción) que actualizan query params con `router.get`; detalle expandible con params/result; paginación server-side (links). Estado vacío.

- [ ] **Step 4: Sidebar**

Agregar `{ title: 'Aprobaciones', href: '/integrations/approvals', icon: ShieldCheck }` con badge `approvals_pending_count > 0 && <span className="...">{count}</span>` en `app-sidebar.tsx` (sección IA, junto a "Chat IA").

- [ ] **Step 5: Verificar**

Run: `php artisan test --compact --filter=SharedPropsTest && npm run types && npm run lint`
Expected: PASS.

---

### Task 19: Verificación final de la Ola 0

**Files:**
- Modify: `docs/qa/playwright-report.md` (agregar sección de la Ola 0)

- [ ] **Step 1: Suite completa + estilo**

Run: `php artisan test --compact`
Expected: toda la suite en verde (incluye tests previos del repo).

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin cambios pendientes.

- [ ] **Step 2: Frontend**

Run: `npm run types && npm run lint && npm run build`
Expected: sin errores.

- [ ] **Step 3: QA manual (Playwright MCP en `:8010`, login `test@example.com/password`)**

1. Settings → Conexiones: crear una conexión GitHub con token ficticio (`ghp_x`) y base URL `https://api.github.com`; verificar estado `error` tras "Probar" (sin red real) y que el mensaje se muestre.
2. Wizard: validar campos requeridos, cambio de transporte, estados disabled durante submit.
3. Crear conexión Docker local (`local_socket`, socket inexistente) → Probar muestra error claro sin romper la página.
4. Aprobaciones: sin pendientes → estado vacío correcto; badge ausente.
5. Actividad: vacío → estado vacío; con un log de prueba (tinker/factory) → fila visible y filtros funcionando.
6. Responsive: wizard en mobile (<lg) como sheet full-screen; lista apilada; sin clipping de overlays.

- [ ] **Step 4: Documentar QA**

Agregar al reporte QA la sección `## Integraciones Ola 0` con los resultados y capturas si aplica.

---

## Self-Review (ejecutado al escribir el plan)

**Spec coverage:** framework (T1-T9), políticas/UI de conexiones (T10), aprobaciones/actividad (T11), OAuth (T12), tools IA (T13), GitHub (T14), Google (T15), Docker (T16), UI (T17-T18), QA (T19). Catálogo completo queda para olas 1-4 (fuera de este plan, según spec).

**Placeholder scan:** sin "TBD/TODO"; los pasos incluyen código o tablas de endpoints exactas.

**Type consistency:** `execute(Connection, string $actionKey, array $params, ExecutionContext)` en contrato/executor/tools; `ActionResult::{success,failure,pending}`; `ConnectionTestResult::{ok,fail}`; `ParamRules::forParams`; `ExecutionContext::{forUi,forAgent,forSchedule}`; `TransportFactory::make`; `OAuthBroker::{redirectUrl,handleCallback,refreshIfNeeded}`. Claves de acción relativas en `Action` y prefijadas con `kind.` en logs/aprobaciones.

**Desviaciones menores respecto del spec (conscientes):** `Connector::execute/test` reciben `Connection`; rutas OAuth por `{connection}`; UI de conexiones con página única + wizard (no páginas create/edit separadas); `HttpCall` gana `body/contentType` para Gmail/Drive. Todas quedaron reflejadas en el spec (ver §Rutas y archivos y §Contratos).

## Execution Handoff

Plan guardado en `docs/superpowers/plans/2026-09-25-integraciones-core-ola-0.md`.

- **Subagent-Driven (recomendado):** un subagente fresco por tarea + revisión entre tareas.
- **Inline:** ejecutar en esta sesión con `superpowers:executing-plans`.

El usuario solicitó ejecución; este plan se ejecuta **inline**. Los pasos de commit permanecen bloqueados hasta autorización explícita.
