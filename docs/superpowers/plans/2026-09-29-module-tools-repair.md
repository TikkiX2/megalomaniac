# Reparación de módulos y herramientas (chat IA + MCP) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reparar las tools del chat IA y del servidor MCP para que lean/escriban en todos los módulos, permitir cambiar el tipo de un proyecto y moverlo entre Personal y Freelance, aislar los módulos por tipo y cerrar los huecos de seguridad adyacentes.

**Architecture:** Servicios de dominio compartidos (`app/Services/<Modulo>/`) como única fuente de verdad; action tools modulares en el chat (patrón `GymActionTool`) y write tools MCP que delegan en los mismos servicios. El `ActionTool` monolítico se retira a medida que sus acciones migran.

**Tech Stack:** Laravel 12, Laravel AI SDK (`laravel/ai`), `laravel/mcp` 0.9, Inertia v2 + React 19 + Tailwind v4 + Wayfinder, Pest 4.

**Spec:** `docs/superpowers/specs/2026-09-29-module-tools-repair-design.md`

## Global Constraints

- PHP 8.4; `php artisan test --compact`; Pint por tarea: `vendor/bin/pint --dirty --format agent`.
- Frontend: `npm run types` y `npm run lint` tras tocar TSX.
- **No ejecutar `git commit` sin pedido explícito del usuario.** Los pasos de commit del plan quedan listados como checkpoint, pero se ejecutan solo con autorización.
- No agregar dependencias nuevas.
- Todo scope de datos por `user_id`; `exists`/búsquedas con ownership.
- Respuestas de tools: nunca excepción cruda; JSON accionable `{success:false,error}` (chat) o `Response::error()` (MCP).
- Los approvals del chat (SDK `Approvable`) y las aprobaciones de integraciones no cambian de flujo.
- Tokens/paths exactos en cada task; no usar placeholders.

## File Structure (nuevos/modificados)

```
app/Services/Projects/ProjectService.php            (nuevo)
app/Services/Projects/ProjectTypeService.php        (nuevo)
app/Services/Tasks/TaskService.php                  (nuevo)
app/Services/Finance/FinanceService.php             (nuevo)
app/Services/Nutrition/NutritionService.php         (nuevo)
app/Services/Grocery/GroceryService.php             (nuevo)
app/Services/Supplement/SupplementService.php       (nuevo)
app/Services/Freelance/FreelanceService.php         (nuevo)
app/Policies/ProjectPolicy.php                      (nuevo; reemplaza PersonalProjectPolicy)
app/Ai/Tools/{Project,Task,Finance,Nutrition,Grocery,Supplement,Freelance}ActionTool.php (nuevos)
app/Ai/Tools/{Freelance,Supplement}QueryTool.php    (nuevos)
app/Ai/Tools/{ActionTool,TaskQueryTool,FinanceQueryTool,GroceryQueryTool,NutritionQueryTool}.php (modificar/retirar)
app/Mcp/Tools/Supplement{Read,Write}Tool.php        (nuevos)
app/Mcp/Tools/{PersonalProject,PersonalTask,Freelance,Finance,Grocery,Nutrition,Workout}*Tool.php (modificar)
config/ai_tools.php                                 (modificar)
app/Ai/Agents/{MegalomaniacAgent,RuntimeAgent}.php  (modificar)
app/Http/Controllers/{Personal,Freelance,Api/V1}/*  (modificar)
app/Http/Requests/{Personal,Freelance}/*            (modificar)
resources/js/pages/{personal,freelance}/projects/{Form,Show}.tsx (modificar)
resources/js/components/ai/chat/ToolsPicker.tsx     (modificar)
resources/js/pages/ai/{thread,chat}.tsx             (modificar)
tests/Feature/Ai/*, tests/Feature/Mcp/*, tests/Feature/{Personal,Freelance}/* (nuevos/modificar)
```

---

# FASE 0 — Hotfix

## Task 1: Router de tools — keywords, write verbs y fallback

**Files:**
- Modify: `config/ai_tools.php`
- Test: `tests/Feature/Ai/ToolRouterTest.php`

**Interfaces:**
- Produces: `ToolRouter::route(string $message): string[]` mantiene firma; los nuevos grupos resueltos dependen solo de config.

- [ ] **Step 1: Escribir tests que fallan**

Agregar a `tests/Feature/Ai/ToolRouterTest.php`:

```php
it('routes write verbs with accents and common imperatives to actions', function (string $message) {
    expect(ToolRouter::route($message))->toContain('actions');
})->with([
    'paga la deuda',
    'apunta que gasté 5000',
    'archiva el proyecto viejo',
    'agenda una tarea para mañana',
    'termina la tarea',
    'duplica el proyecto',
    'pospon la reunión',
    'cancela la tarea',
    'abona 100 a la deuda',
    'retira de la reserva',
    'compra leche',
    'muevo la tarea a otro proyecto',
    'create a task for tomorrow',
    'update my project',
    'delete the old task',
    'archive the project',
    'add a purchase',
    'pay the debt',
]);

it('routes body-part and finance vocabulary to their module groups', function (string $message, string $group) {
    expect(ToolRouter::route($message))->toContain($group);
})->with([
    ['hice pecho y espalda hoy', 'workout'],
    ['cómo va mi cardio', 'workout'],
    ['cuánto pagué este mes', 'finance'],
    ['cuánto tengo en la reserva', 'finance'],
    ['qué suplementos tomo', 'nutrition'],
    ['qué me falta en la tienda', 'grocery'],
    ['qué clientes tengo', 'tasks'],
    ['qué cotizaciones tengo pendientes', 'tasks'],
]);

it('falls back to data groups and keeps module reads available', function () {
    $groups = ToolRouter::route('hola, cómo estás');

    expect($groups)->toContain('tasks', 'finance', 'nutrition', 'grocery', 'workout')
        ->not->toContain('actions');
});
```

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php artisan test --compact tests/Feature/Ai/ToolRouterTest.php`
Expected: FAIL (`actions` no aparece en varios mensajes; fallback no incluye módulos).

- [ ] **Step 3: Implementar la config**

En `config/ai_tools.php` reemplazar los arrays por (dejando el formato existente):

```php
'keywords' => [
    'tasks' => [
        'tarea', 'backlog', 'pendiente', 'vence', 'vencimiento', 'plazo',
        'deadline', 'kanban', 'por hacer', 'proyecto', 'proyectos',
        'hito', 'milestone', 'cliente', 'clientes', 'cotiza', 'quote',
        'entrega', 'recordatorio', 'subtarea', 'tablero', 'columna',
        'comentario', 'archivado',
    ],
    'workout' => [
        'entren', 'ejercicio', 'rutina', 'serie', 'repeticion', 'press',
        'sentadilla', 'gym', 'workout', 'peso muerto', 'pesa', 'banca',
        'dominada', 'curl', 'pecho', 'espalda', 'pierna', 'hombro',
        'biceps', 'triceps', 'gluteo', 'abdominal', 'cardio', 'correr',
        'caminar', 'plancha', 'remo', 'jalon', 'reps', 'rpe', 'record',
        'progreso', 'volumen', 'fuerza', 'hipertrofia', 'peso corporal',
    ],
    'finance' => [
        'gasto', 'gaste', 'gasta', 'plata', 'dinero', 'presupuesto', 'deuda',
        'tarjeta', 'ingreso', 'cobro', 'cobra', 'factura', 'ahorro', 'finanz',
        'sueldo', 'saldo', 'pago', 'pague', 'retiro', 'extraccion', 'reserva',
        'inversion', 'cripto', 'moneda', 'divisa', 'credito', 'prestamo',
        'cuota', 'suscripcion', 'balance', 'impuesto', 'comision',
        'transferencia',
    ],
    'nutrition' => [
        'comida', 'comi', 'caloria', 'proteina', 'macro', 'dieta', 'almuerzo',
        'cena', 'desayuno', 'nutricion', 'comiste', 'alimento', 'merienda',
        'snack', 'batido', 'agua', 'hidratacion', 'suplemento', 'vitamina',
        'creatina', 'carbo', 'carbohidrato', 'grasa', 'kcal', 'gramos',
        'ayuno', 'receta',
    ],
    'grocery' => [
        'compra', 'comprar', 'super', 'supermercado', 'stock', 'inventario',
        'grocer', 'lista de compras', 'mercado', 'tienda', 'despensa',
        'reponer', 'reposicion', 'agotado', 'abarrote', 'mandado',
    ],
    'integrations' => [ /* sin cambios */ ],
    'agents' => [
        'agente', 'schedule', 'programa', 'automatiza', 'cada dia',
        'background', 'cada hora', 'cada semana', 'cron', 'monitor',
        'vigila', 'diario', 'recordatorio',
    ],
    'web' => [
        'busca', 'buscar', 'busqueda', 'internet', 'web', 'noticia', 'google',
        'ultima hora', 'actualidad', 'en linea', 'online', 'en la red',
        'clima', 'traduce', 'wiki', 'documentacion', 'investiga', 'precio',
    ],
],

'write_verbs' => [
    // existentes...
    'escrib', 'ingres', 'carg', 'hazme', 'prepara',
    // nuevos ES
    'paga', 'pago', 'pague', 'pagar', 'abona', 'deposita', 'retira',
    'transfiere', 'apunta', 'archiva', 'agenda', 'pon', 'pone', 'poner',
    'termina', 'finaliza', 'duplica', 'copia', 'pospon', 'aplaza',
    'reprograma', 'cancela', 'envia', 'muevo', 'mover', 'vacia', 'vaciar',
    // nuevos EN
    'create', 'add', 'update', 'edit', 'delete', 'remove', 'log', 'pay',
    'archive', 'move', 'rename', 'assign', 'schedule', 'complete',
    'finish', 'mark', 'close', 'reopen', 'merge', 'duplicate', 'cancel',
    'reschedule', 'buy', 'send',
],

'fallback' => ['tasks', 'workout', 'finance', 'nutrition', 'grocery', 'integrations', 'skills'],
```

- [ ] **Step 4: Correr tests hasta verde**

Run: `php artisan test --compact tests/Feature/Ai/ToolRouterTest.php`
Expected: PASS. Si algún test choca con falsos positivos de stems (`pon` en "componente", `log` en "blog"), ajustar el stem en config y re-correr.

- [ ] **Step 5: Suite de routing completa + Pint**

Run: `php artisan test --compact tests/Feature/Ai` y `vendor/bin/pint --dirty --format agent`

- [ ] **Step 6: Commit (solo con autorización del usuario)**

```bash
git add config/ai_tools.php tests/Feature/Ai/ToolRouterTest.php
git commit -m "fix(ai): expand tool router keywords and write verbs"
```

---

## Task 2: Prompt condicional, RuntimeAgent y picker "Auto" del chat

**Files:**
- Modify: `app/Ai/Agents/MegalomaniacAgent.php` (instructions)
- Modify: `app/Ai/Agents/RuntimeAgent.php:52-78`
- Modify: `app/Http/Controllers/Agents/AgentController.php:53-59`
- Modify: `resources/js/components/agents/AgentWizard.tsx:245-260`
- Modify: `resources/js/components/ai/chat/ToolsPicker.tsx`
- Modify: `resources/js/pages/ai/thread.tsx:127-141` y `resources/js/pages/ai/chat.tsx:88-99`
- Test: `tests/Feature/Ai/MegalomaniacAgentTest.php`, `tests/Feature/Ai/ChatToolsPolicyTest.php`, `tests/Feature/Agents/AgentRunnerTest.php`

**Interfaces:**
- Produces: `MegalomaniacAgent::writeToolsEnabled(): bool` (protegido); `RuntimeAgent::tools()` incluye `tasks`; `ToolsPicker` acepta `activeGroups?: string[]`.
- Consumes: `ChatService::prepareToolPolicy()` ya persiste un override `{mode:'auto'}` como auto+routerado (no requiere cambio backend).

- [ ] **Step 1: Tests que fallan (prompt + runtime + contrato auto)**

En `tests/Feature/Ai/MegalomaniacAgentTest.php`:

```php
it('does not advertise write tools when the actions group is not enabled', function () {
    $agent = new MegalomaniacAgent(User::factory()->create(), null, ['tasks']);

    expect($agent->instructions())
        ->not->toContain('ActionTool')
        ->not->toContain('create_workout');
});

it('advertises write tools when actions or workout are enabled', function (array $groups) {
    $agent = new MegalomaniacAgent(User::factory()->create(), null, $groups);

    expect($agent->instructions())->toContain('ActionTool');
})->with([['tasks', 'actions'], ['workout']]);
```

En `tests/Feature/Ai/ChatToolsPolicyTest.php`:

```php
it('resets a manual thread policy back to auto when an auto override is sent', function () {
    $user = User::factory()->create();
    $thread = ChatThread::factory()->create([
        'user_id' => $user->id,
        'tools_policy' => ['mode' => 'manual', 'groups' => ['tasks']],
    ]);

    $policy = app(ChatService::class)->prepareToolPolicy(
        $thread->fresh(),
        'paga la deuda',
        ['mode' => 'auto', 'groups' => []],
    );

    expect($policy['mode'])->toBe('auto')
        ->and($policy['groups'])->toContain('finance', 'actions')
        ->and($thread->fresh()->tools_policy['mode'])->toBe('auto');
});
```

En `tests/Feature/Agents/AgentRunnerTest.php` (o `RuntimeAgentTest` si existe): un `AgentDefinition` con `tools_policy.tasks_query = true` produce una tool del grupo tasks.

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php artisan test --compact tests/Feature/Ai/MegalomaniacAgentTest.php tests/Feature/Ai/ChatToolsPolicyTest.php tests/Feature/Agents/AgentRunnerTest.php`
Expected: FAIL en prompt condicional y mapping `tasks` (el contrato auto probablemente ya pase: es guard de regresión).

- [ ] **Step 3: Implementar**

`MegalomaniacAgent`: agregar helper y reemplazar el párrafo de acción fijo (`instructions()` ~líneas 185-193):

```php
protected function writeToolsEnabled(): bool
{
    return in_array('*', $this->toolGroups, true)
        || in_array('actions', $this->toolGroups, true)
        || in_array('workout', $this->toolGroups, true);
}
```

y construir el bloque de escritura solo si `writeToolsEnabled()`, nombrando los tools disponibles:

```php
if ($this->writeToolsEnabled()) {
    $instructions .= "\n\nWhen the user asks to perform an action (log a workout, add a purchase, "
        ."create a project or task, move a task to a project, etc.), use the available write tools "
        ."(ActionTool for records and tasks, GymActionTool for training) and confirm what you did after.";
} else {
    $instructions .= "\n\nOnly read tools are available for this turn. If the user asks to modify "
        ."data, say you cannot in this turn and suggest re-sending with the write tools enabled.";
}
```

`RuntimeAgent`: agregar al map `'tasks_query' => 'tasks'` y en el wizard/`AgentController` exponer `tasks_query` con label "Tareas y proyectos (consulta)".

`ToolsPicker.tsx`: nueva prop `activeGroups`; `toggle` arranca de `policy.mode === 'manual' ? policy.groups : (activeGroups ?? ['memory'])`; los labels de grupos se muestran tal cual (labels se ajustan en Task 16). `thread.tsx`/`chat.tsx`: pasar `activeGroups={stream.toolPolicy?.groups}` y enviar siempre la política elegida:

```tsx
tools_policy: toolsPolicy.mode === 'auto'
    ? { mode: 'auto', groups: [] }
    : toolsPolicy,
```

- [ ] **Step 4: Correr tests + types/lint**

Run: `php artisan test --compact tests/Feature/Ai tests/Feature/Agents && npm run types && npm run lint`
Expected: PASS / 0 errores.

- [ ] **Step 5: Commit (solo con autorización)**

```bash
git add app/Ai/Agents resources/js/components/ai/chat/ToolsPicker.tsx resources/js/pages/ai tests/Feature/Ai tests/Feature/Agents
git commit -m "fix(ai): conditional write instructions, runtime tasks group and working auto picker"
```

---

## Task 3: Query tools de Finance y Grocery (columnas inexistentes)

**Files:**
- Modify: `app/Ai/Tools/FinanceQueryTool.php:28-47`
- Modify: `app/Ai/Tools/GroceryQueryTool.php:23-46`
- Test: `tests/Feature/Ai/FinanceQueryToolTest.php` (nuevo), `tests/Feature/Ai/GroceryQueryToolTest.php` (nuevo)

**Interfaces:**
- Consumes: `Income::incomeSource()` (relación real), columnas `purchase_date`, `received_date`, `current_stock`, `target_stock`.
- Produces: `FinanceQueryTool` filtra por fecha real y trae `incomeSource`; `GroceryQueryTool` calcula low stock con `current_stock <= target_stock`.

- [ ] **Step 1: Tests que fallan**

```php
// tests/Feature/Ai/FinanceQueryToolTest.php
it('filters purchases and incomes by their real date columns', function () {
    $user = User::factory()->create();
    Purchase::factory()->create(['user_id' => $user->id, 'purchase_date' => now()->subDays(3)]);
    Income::factory()->create(['user_id' => $user->id, 'received_date' => now()->subDays(3)]);

    $tool = new FinanceQueryTool($user);

    $purchases = $tool->handle(new \Laravel\Ai\Tools\Request(['type' => 'purchases', 'days' => 7]));
    $incomes = $tool->handle(new \Laravel\Ai\Tools\Request(['type' => 'incomes', 'days' => 7]));

    expect(json_decode($purchases, true))->toHaveCount(1)
        ->and(json_decode($incomes, true))->toHaveCount(1);
});

// tests/Feature/Ai/GroceryQueryToolTest.php
it('reports low stock using current and target stock', function () {
    $user = User::factory()->create();
    GroceryItem::factory()->create(['user_id' => $user->id, 'current_stock' => 1, 'target_stock' => 5]);
    GroceryItem::factory()->create(['user_id' => $user->id, 'current_stock' => 9, 'target_stock' => 5]);

    $payload = json_decode((new GroceryQueryTool($user))->handle(new \Laravel\Ai\Tools\Request(['low_stock' => true])), true);

    expect($payload['low_stock_count'])->toBe(1)
        ->and($payload['items'])->toHaveCount(1);
});
```

(Ajustar el constructor de `Laravel\Ai\Tools\Request` al patrón usado en `tests/Feature/Ai/TaskToolsTest.php` si difiere.)

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php artisan test --compact tests/Feature/Ai/FinanceQueryToolTest.php tests/Feature/Ai/GroceryQueryToolTest.php`
Expected: FAIL (`RelationNotFoundException` en incomes / low stock cuenta todos).

- [ ] **Step 3: Implementar**

`FinanceQueryTool`:

```php
'purchases' => Purchase::with(['category', 'currency'])
    ->where('user_id', $this->user->id)
    ->where('purchase_date', '>=', now()->subDays($days)->toDateString())
    ->latest('purchase_date')->limit(20)->get(),
'incomes' => Income::with(['incomeSource', 'currency'])
    ->where('user_id', $this->user->id)
    ->where('received_date', '>=', now()->subDays($days)->toDateString())
    ->latest('received_date')->limit(20)->get(),
'debts' => Debt::with('payments')
    ->where('user_id', $this->user->id)
    ->latest()->limit(10)->get(),
```

`GroceryQueryTool`:

```php
if (! empty($request['low_stock'])) {
    $query->whereColumn('current_stock', '<=', 'target_stock');
}
...
$lowStock = $items->filter(fn ($item) => $item->current_stock <= $item->target_stock);
```

- [ ] **Step 4: Correr tests**

Run: `php artisan test --compact tests/Feature/Ai && vendor/bin/pint --dirty --format agent`
Expected: PASS.

- [ ] **Step 5: Commit (solo con autorización)**

```bash
git add app/Ai/Tools/FinanceQueryTool.php app/Ai/Tools/GroceryQueryTool.php tests/Feature/Ai/FinanceQueryToolTest.php tests/Feature/Ai/GroceryQueryToolTest.php
git commit -m "fix(ai): correct finance and grocery query columns"
```

---

## Task 4: MCP Personal — crash de ArrayAccess + smoke tests de los 14 tools

**Files:**
- Modify: `app/Mcp/Tools/PersonalProjectReadTool.php`, `PersonalProjectWriteTool.php`, `PersonalTaskReadTool.php`, `PersonalTaskWriteTool.php`
- Test: `tests/Feature/Mcp/McpToolsSmokeTest.php` (nuevo), `tests/Feature/Mcp/PersonalToolsTest.php` (nuevo)

**Interfaces:**
- Produces: los 4 tools usan `$request->get('key', $default)`; `limit` default correcto (`$request->get('limit', 20)`); smoke test recorre los 14 tools registrados.

- [ ] **Step 1: Test de humo que falla**

```php
// tests/Feature/Mcp/McpToolsSmokeTest.php
use App\Mcp\Servers\MegalomaniacServer;

it('every registered tool responds without internal errors', function () {
    $user = User::factory()->create();

    $tools = (new ReflectionClass(MegalomaniacServer::class))->getDefaultProperties()['tools'];

    expect($tools)->toHaveCount(14);

    foreach ($tools as $tool) {
        $response = MegalomaniacServer::actingAs($user)->tool($tool, ['action' => 'noop']);

        expect($response->response->isError())
            ->toBeFalse("Tool {$tool} crashed: ".$response->response->content());
    }
});
```

Ajustar el acceso al `Response` según lo que exponga `laravel/mcp` 0.9 (`WorkoutToolsTest` usa `assertOk()/assertStructuredContent()`); si no hay helper de error, encadenar `->assertOk()` salvo para los write tools con `noop`, que devuelven `Response::error` (usar `->assertError()` si existe, o comparar `content()`).

- [ ] **Step 2: Correr y verificar que falla**

Run: `php artisan test --compact tests/Feature/Mcp/McpToolsSmokeTest.php`
Expected: FAIL con `Cannot use object of type Laravel\Mcp\Request as array` en los 4 tools de Personal.

- [ ] **Step 3: Implementar**

En los 4 archivos reemplazar todo acceso por corchetes:

```php
$limit = (int) $request->get('limit', 20);          // antes: (int) $request['limit'] ?? 20
if ($request->get('status')) { ... }                 // antes: isset($request['status'])
'name' => $request->get('name'),
'project_id' => $request->get('project_id', 0),
...
```

- [ ] **Step 4: Test funcional de Personal + correr todo MCP**

`tests/Feature/Mcp/PersonalToolsTest.php`: crear proyecto vía `PersonalProjectWriteTool` (`action=create`, `name`), leerlo con `PersonalProjectReadTool` (assert que aparece), actualizar (`action=update`, `status=in_progress`), y create/update/delete de task suelta con `PersonalTaskWriteTool`; todo por `MegalomaniacServer::actingAs($user)`.

Run: `php artisan test --compact tests/Feature/Mcp && vendor/bin/pint --dirty --format agent`
Expected: PASS.

- [ ] **Step 5: Commit (solo con autorización)**

```bash
git add app/Mcp/Tools tests/Feature/Mcp
git commit -m "fix(mcp): personal tools request access and add smoke coverage"
```

---

# FASE 1 — Proyectos: tipo, movimiento, aislamiento y seguridad

## Task 5: ProjectService + ProjectTypeService

**Files:**
- Create: `app/Services/Projects/ProjectService.php`, `app/Services/Projects/ProjectTypeService.php`
- Test: `tests/Feature/Projects/ProjectTypeServiceTest.php`, `tests/Feature/Projects/ProjectServiceTest.php`

**Interfaces:**
- `ProjectService::create(User $user, array $data): Project` — exige `type` en `['personal','freelance']`; garantiza `client_id`/`currency_id` según tipo (personal: default currency; freelance: exige `client_id` con ownership); lanza `InvalidArgumentException`.
- `ProjectService::update(User $user, Project $project, array $data): Project` — si `type` cambia, delega en `ProjectTypeService::changeType()`.
- `ProjectService::archive(User $user, Project $project): Project`, `::delete(User $user, Project $project): void`.
- `ProjectTypeService::changeType(User $user, Project $project, string $type, ?int $clientId = null): Project` — transaccional; re-siembra columnas y remapea tasks (done→done, resto→primera no-done); a personal limpia `client_id` si es el cliente auto "Personal" (`name = 'Personal'` sin email/phone); a freelance exige `clientId` con ownership.

- [ ] **Step 1: Tests que fallan**

```php
// tests/Feature/Projects/ProjectTypeServiceTest.php
it('moves a personal project to freelance and remaps task statuses', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal', 'client_id' => null]);
    TaskBoardColumnService::seedFor($project);

    $done = $project->tasks()->create([
        'user_id' => $user->id, 'title' => 'Hecha',
        'status' => 'Done', 'is_done' => true, 'sort_order' => 1,
    ]);
    $pending = $project->tasks()->create([
        'user_id' => $user->id, 'title' => 'Pendiente',
        'status' => 'Pending', 'is_done' => false, 'sort_order' => 2,
    ]);

    $moved = app(ProjectTypeService::class)->changeType($user, $project, 'freelance', $client->id);

    expect($moved->type)->toBe('freelance')
        ->and($moved->client_id)->toBe($client->id)
        ->and($moved->boardColumns()->pluck('key'))->toContain('To Do', 'Done')
        ->and($done->fresh()->is_done)->toBeTrue()
        ->and($pending->fresh()->status)->toBe('To Do');
});

it('clears the auto Personal client when moving to personal', function () {
    $user = User::factory()->create();
    $auto = Client::firstOrCreate(['user_id' => $user->id, 'name' => 'Personal'], ['email' => null, 'phone' => null]);
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance', 'client_id' => $auto->id]);

    $moved = app(ProjectTypeService::class)->changeType($user, $project, 'personal');

    expect($moved->client_id)->toBeNull();
});

it('rejects moving to freelance without a real client', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    expect(fn () => app(ProjectTypeService::class)->changeType($user, $project, 'freelance'))
        ->toThrow(InvalidArgumentException::class);
});
```

`ProjectServiceTest`: create personal sin cliente no crea cliente "Personal"; create freelance sin `client_id` lanza; create con `type` inválido lanza; update que cambia tipo usa el servicio (assert columnas remapeadas).

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php artisan test --compact tests/Feature/Projects`
Expected: FAIL (clases no existen).

- [ ] **Step 3: Implementar los servicios**

`ProjectTypeService::changeType()` en transacción:

```php
DB::transaction(function () use ($user, $project, $type, $clientId) {
    if ($type === 'freelance') {
        $client = $clientId
            ? Client::where('user_id', $user->id)->findOrFail($clientId)
            : throw new InvalidArgumentException('Freelance projects require a client.');
        $project->client_id = $client->id;
    } else {
        if ($project->client && $project->client->name === 'Personal' && ! $project->client->email && ! $project->client->phone) {
            $project->client_id = null;
        }
    }

    $project->type = $type;
    $project->save();

    $columns = TaskBoardColumnService::seedDefaultsForType($project); // nuevo helper público
    $doneKey = collect($columns)->firstWhere('is_done', true)['key'];
    $firstKey = collect($columns)->first()['key'];
    $keys = collect($columns)->pluck('key');

    $project->tasks()->get()->each(function (ProjectTask $task) use ($keys, $doneKey, $firstKey) {
        $status = $task->is_done ? $doneKey : ($keys->contains($task->status) ? $task->status : $firstKey);
        $task->update(['status' => $status, 'is_done' => $status === $doneKey]);
    });

    return $project->fresh(['boardColumns', 'tasks']);
});
```

`TaskBoardColumnService`: extraer `seedFor()` a un helper reutilizable `insertDefaults()` (ya existe) y agregar `public static function seedDefaultsForType(Project $project): array` que inserta (si faltan) las columnas del tipo y devuelve el set completo; `seedFor()` lo usa.

- [ ] **Step 4: Correr tests + Pint**

Run: `php artisan test --compact tests/Feature/Projects tests/Feature/TaskBoardColumnServiceTest.php tests/Feature/ProjectColumnSeedTest.php`
Expected: PASS.

- [ ] **Step 5: Commit (solo con autorización)**

```bash
git add app/Services/Projects app/Services/TaskBoardColumnService.php tests/Feature/Projects
git commit -m "feat(projects): project and type-change services with board remap"
```

---

## Task 6: Web — tipo editable, aislar módulos, fin del cliente "Personal"

**Files:**
- Modify: `app/Http/Requests/Personal/{Store,Update}PersonalProjectRequest.php`
- Modify: `app/Http/Controllers/Personal/PersonalProjectController.php:44-115`
- Modify: `app/Http/Controllers/Freelance/ProjectController.php:19-148`
- Modify: `app/Http/Controllers/Freelance/FreelanceDashboardController.php:22-38`
- Modify: `app/Http/Controllers/Personal/PersonalTaskController.php:22-37`
- Modify: `app/Http/Controllers/Api/V1/ProjectController.php:42-69`
- Test: `tests/Feature/Personal/PersonalFlowTest.php`, `tests/Feature/Freelance/ProjectTest.php` (extender)

**Interfaces:**
- Consumes: `ProjectService`, `ProjectTypeService` (Task 5).
- Produces: requests aceptan `'type' => ['sometimes','in:personal,freelance']` (update) y store personal conserva `personal` salvo `type` explícito; Freelance index/dashboard solo `type=freelance`; tasks personales solo de proyectos personales o sueltas.

- [ ] **Step 1: Tests que fallan**

```php
// PersonalFlowTest
it('changes a personal project type through the web update', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['user_id' => $user->id, 'type' => 'personal']);

    $this->actingAs($user)
        ->put("/personal/projects/{$project->id}", ['name' => 'Movido', 'type' => 'freelance', 'client_id' => $client->id])
        ->assertRedirect();

    expect($project->fresh()->type)->toBe('freelance');
});

it('does not create a Personal client for new personal projects', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/personal/projects', ['name' => 'Solo', 'status' => 'pending']);

    expect(Client::where('user_id', $user->id)->where('name', 'Personal')->exists())->toBeFalse();
});

// Freelance/ProjectTest
it('excludes personal projects from the freelance index and dashboard', function () {
    $user = User::factory()->create();
    Project::factory()->create(['user_id' => $user->id, 'name' => 'Personal Uno', 'type' => 'personal']);
    Project::factory()->create(['user_id' => $user->id, 'name' => 'Freelance Uno', 'type' => 'freelance']);

    $this->actingAs($user)->get('/freelance/projects')
        ->assertInertia(fn ($page) => $page->component('freelance/projects/Index')
            ->where('projects.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Freelance Uno']));
});
```

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php artisan test --compact tests/Feature/Personal/PersonalFlowTest.php tests/Feature/Freelance/ProjectTest.php`
Expected: FAIL (type ignorado, cliente "Personal" creado, personales listados).

- [ ] **Step 3: Implementar**

- `PersonalProjectController@store`: quitar el `firstOrCreate` del cliente "Personal"; usar `ProjectService::create()`.
- `PersonalProjectController@update`: usar `ProjectService::update()` (delega el cambio de tipo).
- `UpdatePersonalProjectRequest`: agregar `'type' => ['sometimes','in:personal,freelance']` y `'client_id' => ['nullable','exists:clients,id']`; `Store...`: `'type' => ['nullable','in:personal,freelance']` (default `personal` en controller).
- `Freelance\ProjectController@index`: `->where('type', 'freelance')`; `FreelanceDashboardController`: filtrar `type=freelance` en stats/recent/upcoming; `update`: usar `ProjectService::update()` con `type` validado.
- `PersonalTaskController@index`: `->where(fn ($q) => $q->whereNull('project_id')->orWhereHas('project', fn ($p) => $p->where('type', 'personal')))`.
- `Api/V1/ProjectController@store`: no aceptar `type` en create (forzar `freelance`); `update`: usar `ProjectService::update()`.
- `resources/js/types/personal.ts:40`: `type: 'personal' | 'freelance'`.

- [ ] **Step 4: Correr tests + Pint**

Run: `php artisan test --compact tests/Feature/Personal tests/Feature/Freelance tests/Feature/Api && vendor/bin/pint --dirty --format agent`
Expected: PASS (los tests viejos que asumían el cliente "Personal" se actualizan; no se borran).

- [ ] **Step 5: Commit (solo con autorización)**

```bash
git add app/Http/Requests/Personal app/Http/Controllers resources/js/types/personal.ts tests/Feature
git commit -m "feat(projects): editable type, module isolation and no auto Personal client"
```

---

## Task 7: Seguridad — ProjectPolicy, ownership freelance y project_id validado

**Files:**
- Create: `app/Policies/ProjectPolicy.php`; Delete: `app/Policies/PersonalProjectPolicy.php`
- Modify: `app/Http/Controllers/Freelance/ProjectController.php:93-187`
- Modify: `app/Http/Controllers/Personal/PersonalProjectController.php`
- Modify: `app/Services/Tasks/TaskService.php` (o validación en `PersonalTaskController` hasta Task 13)
- Modify: `app/Http/Requests/Personal/{Store,Update}PersonalTaskRequest.php`
- Test: `tests/Feature/Freelance/ProjectSecurityTest.php` (nuevo), `tests/Feature/Personal/PersonalFlowTest.php` (extender)

**Interfaces:**
- `ProjectPolicy` (`view`, `update`, `delete`, `viewAny`, `create`) por `user_id`; auto-discovery de Laravel para `App\Models\Project` (borrar la policy mal nombrada).
- `TaskService::assertProjectAssignable(User $user, ?int $projectId, string $scopeType): ?Project` — owner + tipo; usado por controllers y tools.

- [ ] **Step 1: Tests que fallan**

```php
it('forbids editing another user freelance project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id, 'type' => 'freelance']);

    $this->actingAs($intruder)->get("/freelance/projects/{$project->id}")->assertForbidden();
    $this->actingAs($intruder)->put("/freelance/projects/{$project->id}", ['name' => 'hack'])->assertForbidden();
    $this->actingAs($intruder)->delete("/freelance/projects/{$project->id}")->assertForbidden();
});

it('rejects attaching a task to a project of another user or another module', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $foreign = Project::factory()->create(['user_id' => $other->id, 'type' => 'personal']);
    $freelance = Project::factory()->create(['user_id' => $user->id, 'type' => 'freelance']);
    $task = ProjectTask::factory()->create(['user_id' => $user->id, 'project_id' => null]);

    $this->actingAs($user)->put("/personal/tasks/{$task->id}", ['project_id' => $foreign->id])->assertForbidden();
    $this->actingAs($user)->put("/personal/tasks/{$task->id}", ['project_id' => $freelance->id])->assertForbidden();
});
```

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php artisan test --compact tests/Feature/Freelance/ProjectSecurityTest.php`
Expected: FAIL (200 en vez de 403).

- [ ] **Step 3: Implementar**

- Crear `ProjectPolicy` con checks `$user->id === $project->user_id`; borrar `PersonalProjectPolicy` (dead code, solo se auto-referencia).
- `Freelance\ProjectController`: `$this->authorize('view', $project)` en show/edit/upload/download; `authorize('update'|'delete')` en update/destroy/deleteFile; `downloadFile`/`deleteFile` resuelven la media y verifican `$media->model instanceof Project && user_id`.
- `PersonalProjectController`: reemplazar `abort_if($project->user_id !== auth()->id(), 403)` por `$this->authorize(...)`.
- `StorePersonalTaskRequest`/`UpdatePersonalTaskRequest`: `'project_id' => ['nullable', 'integer']` y validar en controller/service con ownership+tipo (personal para rutas personales; freelance para `Freelance\ProjectTaskController`).

- [ ] **Step 4: Correr tests**

Run: `php artisan test --compact tests/Feature/Freelance tests/Feature/Personal && vendor/bin/pint --dirty --format agent`
Expected: PASS.

- [ ] **Step 5: Commit (solo con autorización)**

```bash
git add app/Policies app/Http/Controllers app/Http/Requests tests/Feature
git commit -m "fix(security): project policy, freelance ownership and task project scoping"
```

---

## Task 8: UI — selector de Tipo/Módulo y "Mover a…"

**Files:**
- Modify: `resources/js/pages/personal/projects/Form.tsx`, `resources/js/pages/freelance/projects/Form.tsx`
- Modify: `resources/js/pages/personal/projects/Show.tsx`, `resources/js/pages/freelance/projects/Show.tsx`
- Modify: `resources/js/types/personal.ts` (y `freelance.ts` si tipa `Project`)
- Test: verificación manual Playwright + `npm run types && npm run lint && npm run build`

**Interfaces:**
- Consumes: endpoints web de Task 6 (`PUT /personal/projects/{id}` y freelance aceptan `type` y `client_id`).
- Produces: `type` en el form edit con confirmación; acción "Mover a Freelance/Personal" en Show (usa el mismo endpoint).

- [ ] **Step 1: Implementar el selector (siguiendo design system Ember)**

En ambos `Form.tsx`: agregar campo "Módulo" (Select: Personal | Freelance) junto a Estado/Prioridad; si se elige Freelance, mostrar Select de Cliente (datos ya disponibles en el form freelance; en personal agregar prop `clients` desde el controller) y helper text: "Al mover el proyecto se re-mapean las columnas del tablero."; el submit incluye `type` y `client_id`. El form freelance mantiene su campo `module` (etiqueta de texto) pero se renombra el label a "Módulo del proyecto (etiqueta)" para no confundir con el tipo.

En ambos `Show.tsx`: menú de acciones con "Mover a Freelance"/"Mover a Personal"; si destino es Freelance y no hay cliente, abrir diálogo de selección; al confirmar, `router.put` con `{ type, client_id }` y toast.

Aplicar `ui-radar` para el patrón de select/menú y pasar el finish gate de `anti-ui-slop` (focus visible, disabled, táctil ≥44px, responsive, tokens `bg-card`/`border-border`/`text-primary`).

- [ ] **Step 2: Verificar**

Run: `npm run types && npm run lint && npm run build`
Expected: 0 errores.

- [ ] **Step 3: QA manual (Playwright MCP, puerto 8010)**

Login `test@example.com/password` → Personal → Proyecto → Editar → cambiar Módulo a Freelance → guardar → verificar que aparece en Freelance y ya no en Personal; volver a Personal; probar también la acción "Mover a…" del Show.

- [ ] **Step 4: Commit (solo con autorización)**

```bash
git add resources/js
git commit -m "feat(ui): project type selector and move-between-modules action"
```

---

## Task 9: Chat + MCP — create con tipo obligatorio y update/move de proyecto

**Files:**
- Create: `app/Ai/Tools/ProjectActionTool.php`
- Modify: `app/Ai/Tools/ActionTool.php` (quitar `create_project`)
- Modify: `app/Ai/Tools/ToolCatalog.php`
- Modify: `app/Ai/Agents/MegalomaniacAgent.php` (mención de ProjectActionTool)
- Modify: `app/Mcp/Tools/PersonalProjectWriteTool.php`, `FreelanceWriteTool.php`
- Test: `tests/Feature/Ai/ProjectActionToolTest.php` (nuevo), `tests/Feature/Mcp/PersonalToolsTest.php` (extender), `tests/Feature/Ai/TaskToolsTest.php` (migrar create_project)

**Interfaces:**
- `ProjectActionTool` (`Approvable`): acciones `create_project` (requiere `type`), `update_project` (incluye `type`/`client_id` → mover), `archive_project`, `delete_project`. Delega en `ProjectService`/`ProjectTypeService`.
- `PersonalProjectWriteTool`/`FreelanceWriteTool`: `update` acepta `type` (move) y campos completos; usan servicios.
- `ToolCatalog`: grupo `tasks` = `[TaskQueryTool, ProjectActionTool]`; grupo `freelance` = `[FreelanceQueryTool? (Task 14), ProjectActionTool, ...]`; `actions` = alias con todos los action tools; `toolsFor()` dedupea por clase.

- [ ] **Step 1: Tests que fallan**

```php
it('requires a type and asks instead of defaulting to personal', function () {
    $user = User::factory()->create();
    $payload = json_decode((new ProjectActionTool($user, app(ProjectService::class), app(ProjectTypeService::class)))
        ->handle(new Request(['action' => 'create_project', 'name' => 'Nuevo'])), true);

    expect($payload['success'])->toBeFalse()
        ->and($payload['error'])->toContain('type')
        ->and(Project::where('user_id', $user->id)->exists())->toBeFalse();
});

it('moves a personal project to freelance through the chat tool', function () {
    // create con type=personal, luego update_project con type=freelance y client_id
    // assert type cambiado y columnas remapeadas
});
```

En MCP: `PersonalProjectWriteTool` con `action=update` + `type=freelance` mueve el proyecto (assert `type` y columnas).

- [ ] **Step 2: Correr y verificar que fallan**

Run: `php artisan test --compact tests/Feature/Ai/ProjectActionToolTest.php`
Expected: FAIL (clase no existe).

- [ ] **Step 3: Implementar**

`ProjectActionTool` copiando el patrón de `GymActionTool` (try/catch `ModelNotFoundException|AuthorizationException|InvalidArgumentException`, helpers `success/error`, `actionLabels()`):

```php
'create_project' => 'crear un proyecto',
'update_project' => 'actualizar o mover un proyecto',
'archive_project' => 'archivar un proyecto',
'delete_project' => 'eliminar un proyecto',
```

Schema: `type` con `->enum(['personal','freelance'])->required()` solo documenta; la validación real es en el servicio (si falta → error que instruye a preguntar). `update_project` acepta `project_id` (requerido), `type`, `client_id`, `name`, `status`, `deadline`, `priority`, `budget`, `area`, `module`, `tags`, `notes`, `is_archived`.

`ToolCatalog::toolsFor()` dedupe:

```php
$tools = [];
foreach (...) {
    foreach ($group['tools'] as $class) {
        $tools[$class] ??= self::make($user, $class, $thread);
    }
}
return array_values($tools);
```

- [ ] **Step 4: Correr tests**

Run: `php artisan test --compact tests/Feature/Ai tests/Feature/Mcp && vendor/bin/pint --dirty --format agent`
Expected: PASS (tests de `create_project` migrados desde `TaskToolsTest`).

- [ ] **Step 5: Commit (solo con autorización)**

```bash
git add app/Ai app/Mcp/Tools/PersonalProjectWriteTool.php app/Mcp/Tools/FreelanceWriteTool.php tests/Feature
git commit -m "feat(ai,mcp): project action tool with required type and move support"
```

---

# FASE 2 — Cobertura de módulos (servicios + tools)

## Task 10: FinanceService + FinanceActionTool + paridad MCP

**Files:**
- Create: `app/Services/Finance/FinanceService.php`, `app/Ai/Tools/FinanceActionTool.php`
- Modify: `app/Mcp/Tools/FinanceWriteTool.php`, `app/Mcp/Tools/FinanceReadTool.php`
- Modify: `app/Ai/Tools/ToolCatalog.php` (grupo finance = query + action)
- Test: `tests/Feature/Finance/FinanceServiceTest.php`, `tests/Feature/Ai/FinanceActionToolTest.php`, `tests/Feature/Mcp/FinanceToolsTest.php`

**Interfaces:**
- `FinanceService`: `createPurchase/updatePurchase/deletePurchase`, `createIncome/updateIncome/deleteIncome`, `createDebt/updateDebt/deleteDebt/addDebtPayment`, `createWithdrawal`/reservas básicas. FKs con ownership (`category_id`, `currency_id`, `income_source_id`, `credit_card_id`); `currency_id`/`income_source_id` con default del usuario igual que la web; `incomes` sin `notes`.
- `FinanceActionTool` acciones: `add_purchase`, `update_purchase`, `delete_purchase`, `add_income`, `update_income`, `delete_income`, `add_debt`, `update_debt`, `delete_debt`, `add_debt_payment`, `add_withdrawal`, `deposit_reserve`, `withdraw_reserve`.
- MCP `finance-write`: mismas acciones/firmas; `finance-read`: debts vencidas incluidas (sin filtro por `due_date` relativo), ventanas por fecha correctas.

- [ ] Test-first: `FinanceServiceTest` (FK ownership, delete, pago de deuda actualiza `remaining_amount`), `FinanceActionToolTest` (approval + happy path), `FinanceToolsTest` (MCP `update`/`delete`/`pay`).
- [ ] Implementar servicio; luego tool chat; luego MCP delegando en el servicio.
- [ ] Quitar `add_purchase`/`add_income`/`add_debt` de `ActionTool` y migrar sus tests.
- [ ] Run: `php artisan test --compact tests/Feature/Finance tests/Feature/Ai tests/Feature/Mcp && vendor/bin/pint --dirty --format agent`
- [ ] Commit (solo con autorización): `feat(finance): shared service, chat actions and complete mcp tools`

---

## Task 11: Nutrition + Supplement (servicios y tools)

**Files:**
- Create: `app/Services/Nutrition/NutritionService.php`, `app/Services/Supplement/SupplementService.php`, `app/Ai/Tools/NutritionActionTool.php`, `app/Ai/Tools/SupplementActionTool.php`, `app/Ai/Tools/SupplementQueryTool.php`
- Create MCP: `app/Mcp/Tools/SupplementReadTool.php`, `SupplementWriteTool.php`; Modify `NutritionWriteTool.php`, `NutritionReadTool.php`, `MegalomaniacServer.php`
- Test: `tests/Feature/Nutrition/NutritionServiceTest.php`, `tests/Feature/Supplement/SupplementServiceTest.php`, `tests/Feature/Ai/NutritionToolsTest.php`, `tests/Feature/Ai/SupplementToolsTest.php`, `tests/Feature/Mcp/NutritionToolsTest.php`, `tests/Feature/Mcp/SupplementToolsTest.php`

**Interfaces:**
- `NutritionService`: `createMealLog/upsertMealLog(date, meal_type)` (firstOrCreate por fecha+tipo), `addMealItem(MealLog, Food|array)` con macros **×quantity** (paridad web), `deleteMealItem`, `createFood`, `searchFoods`.
- `SupplementService`: CRUD de `Supplement` + `logIntake`.
- `NutritionActionTool`: `log_meal` (con items), `add_meal_item`, `delete_meal_item`, `create_food`, `log_supplement`, `create_supplement`. `SupplementActionTool`/`SupplementQueryTool` para CRUD y consulta; grupo `supplements` nuevo en `ToolCatalog` + keywords en config.
- MCP: `nutrition-write` delega en el servicio (corrige ÷100 → ×quantity); `supplement-read/write` nuevos; registrar en `MegalomaniacServer` (16 tools).

- [ ] Test-first: paridad de macros (food 100 kcal × 2 = 200), upsert de meal log, supplement log con ownership, MCP update/delete.
- [ ] Implementar + migrar `log_meal`/`log_supplement` fuera de `ActionTool`.
- [ ] Run: `php artisan test --compact tests/Feature/Nutrition tests/Feature/Supplement tests/Feature/Ai tests/Feature/Mcp` + Pint.
- [ ] Commit (solo con autorización): `feat(nutrition,supplement): shared services and complete tools`

---

## Task 12: Grocery — servicio y tools

**Files:**
- Create: `app/Services/Grocery/GroceryService.php`, `app/Ai/Tools/GroceryActionTool.php`
- Modify: `app/Mcp/Tools/GroceryWriteTool.php`, `GroceryReadTool.php`
- Test: `tests/Feature/Grocery/GroceryServiceTest.php`, `tests/Feature/Ai/GroceryActionToolTest.php`, `tests/Feature/Mcp/GroceryToolsTest.php`

**Interfaces:**
- `GroceryService`: `create/update/delete/consume/restock` con transacción y lock (`lockForUpdate`) en consume/restock; `GroceryPriceHistory` al registrar precio/cambio.
- `GroceryActionTool`: `add_grocery_item`, `update_grocery_item`, `delete_grocery_item`, `consume_grocery_item`, `restock_grocery_item`.
- MCP `grocery-write` alineado (sin `purchased_at = now()` hardcodeado; `category` con ownership).

- [ ] Test-first (consume no baja de 0, restock crea historial, update/delete, MCP parity).
- [ ] Implementar + quitar `add_grocery_item` de `ActionTool`.
- [ ] Run + Pint; Commit (solo con autorización): `feat(grocery): shared service and complete tools`

---

## Task 13: Tasks — TaskService, TaskActionTool y paridad MCP

**Files:**
- Create: `app/Services/Tasks/TaskService.php`, `app/Ai/Tools/TaskActionTool.php`
- Modify: `app/Mcp/Tools/PersonalTaskWriteTool.php`, `PersonalTaskReadTool.php`, `FreelanceWriteTool.php` (tasks)
- Modify: `app/Ai/Tools/ActionTool.php` (quitar tasks), `TaskQueryTool.php` (lecturas de proyectos)
- Test: `tests/Feature/Tasks/TaskServiceTest.php`, `tests/Feature/Ai/TaskToolsTest.php` (migrar), `tests/Feature/Mcp/PersonalToolsTest.php` (extender)

**Interfaces:**
- `TaskService`: `create/update/complete/move/delete`; `move()` = status + `sort_order` + `project_id` + `ordered_ids` (espejo de `PersonalTaskController@move`); sincroniza `is_done` con la columna (`TaskBoardColumnService::ensureColumnForScope`); valida owner+tipo del proyecto destino.
- `TaskActionTool`: `create_task`, `complete_task`, `update_task` (títulos, fechas, tags, archive, prioridad, descripción Markdown), `move_task` (proyecto/columna/orden), `delete_task`.
- MCP `personal-task-write`: create/update/delete + `move` + `project_id`; `personal-task-read`: incluye tasks de proyectos personales; `freelance-write` tasks con type-guard.

- [ ] Test-first (status→is_done sync, move entre proyectos, rejection de proyecto freelance en tarea personal).
- [ ] Implementar + migrar tests de `ActionTool` de tasks.
- [ ] Run + Pint; Commit (solo con autorización): `feat(tasks): shared task service and complete tools`

---

## Task 14: Freelance — clientes y cotizaciones; gym restante; retiro de ActionTool

**Files:**
- Create: `app/Services/Freelance/FreelanceService.php`, `app/Ai/Tools/FreelanceQueryTool.php`, `app/Ai/Tools/FreelanceActionTool.php`
- Modify: `app/Mcp/Tools/FreelanceWriteTool.php`, `FreelanceReadTool.php` (clients/projects/tasks/quotes/payments), `WorkoutWriteTool.php`, `app/Ai/Tools/GymActionTool.php`
- Delete: `app/Ai/Tools/ActionTool.php` (una vez migradas todas sus acciones)
- Modify: `app/Ai/Tools/ToolCatalog.php` (grupos finales), `app/Ai/Agents/MegalomaniacAgent.php`
- Test: `tests/Feature/Freelance/FreelanceServiceTest.php`, `tests/Feature/Ai/FreelanceToolsTest.php`, `tests/Feature/Mcp/FreelanceToolsTest.php`, `tests/Feature/Mcp/WorkoutToolsTest.php` (extender)

**Interfaces:**
- `FreelanceService`: clients/quotes CRUD + convert; `FreelanceQueryTool` (clients, projects freelance, quotes, payments, tasks); `FreelanceActionTool` (create/update/delete client y quote, send/convert).
- Gym cobertura: `update_workout`, `delete_workout`, `remove_exercise`, `remove_set`, `delete_routine` en `GymActionTool` y `WorkoutWriteTool`.
- `ToolCatalog` final: `tasks` [TaskQuery, TaskAction], `workout` [GymQuery, GymAction], `finance` [FinanceQuery, FinanceAction], `nutrition` [NutritionQuery, NutritionAction], `grocery` [GroceryQuery, GroceryAction], `supplements` [SupplementQuery, SupplementAction], `freelance` [FreelanceQuery, FreelanceAction], `integrations`, `agents`, `skills`, `memory`, `web`, `actions` (alias de todos los action tools). Labels de grupos de lectura: "(consulta)"; de escritura: "(lectura y escritura)".
- `ActionTool` eliminado; tests migrados/renombrados; `ChatApprovalTest`/`ChatUnknownToolTest` actualizados a las nuevas tools.

- [ ] Test-first por bloque (freelance service → tools; gym cobertura; catálogo final con dedupe y labels).
- [ ] Run: `php artisan test --compact` (suite completa) + Pint.
- [ ] Commit (solo con autorización): `refactor(ai,mcp): modular action tools and complete module coverage`

---

# FASE 3 — Verificación y docs

## Task 15: QA Playwright, docs y suite completa

**Files:**
- Modify: `docs/modules/mcp.md`, `docs/qa/playwright-report.md`
- Create: `docs/modules/tools.md`
- Test: suite completa

- [ ] **Step 1: QA con Playwright MCP (:8010)**

Crawl: Landing → Login (`test@example.com/password`) → Dashboard → Gym → Nutrition → Grocery → Finance → Freelance → Personal → Settings → Chat IA. En el chat: "paga 100 de la deuda" (debe pedir aprobación y ejecutar), "¿cuáles son mis proyectos personales?" (debe responder con datos), "mueve el proyecto X a Freelance" (aprobación + verificación en UI). Registrar hallazgos en `docs/qa/playwright-report.md`.

- [ ] **Step 2: Verificación MCP real**

`POST /mcp/megalomaniac` con PAT: `tools/list` (16) y una llamada `personal-project-write` de create/update (antes crasheaba).

- [ ] **Step 3: Docs**

`docs/modules/mcp.md`: instrucciones del server propio y tools por módulo. `docs/modules/tools.md`: mapa grupo chat → tools → servicio, y reglas (approval, ownership).

- [ ] **Step 4: Suite + lint final**

Run: `php artisan test --compact && vendor/bin/pint --dirty --format agent && npm run types && npm run lint && npm run build`
Expected: todo verde.

- [ ] **Step 5: Commit (solo con autorización)**

```bash
git add docs
git commit -m "docs(qa): module tools repair report and mcp tools reference"
```

---

## Self-Review

**Cobertura del spec:**
- Chat "no puedo escribir" → Tasks 1, 2 (router, prompt, picker) + Tasks 9-14 (cobertura).
- MCP incompleto/crash → Tasks 4, 9, 10-14, 15 (smoke + cobertura + QA).
- Tipo de proyecto / crear mal / mover → Tasks 5, 6, 8, 9.
- Aislamiento → Task 6. Seguridad (IDOR/ownership) → Task 7.
- Divergencias (macros, FKs, columnas) → Tasks 3, 10, 11, 12, 13.
- UI/finish gate → Task 8; QA/docs → Task 15.

**Consistencia de tipos:** `ProjectService::create/update/archive/delete`, `ProjectTypeService::changeType(User, Project, string, ?int)`, `TaskService::move`, `TaskBoardColumnService::seedDefaultsForType(Project): array`, action tools con `handle(Request): Stringable|string` y `schema(JsonSchema): array` — verificados contra el patrón existente (`GymActionTool`). Los MCP write tools usan `$request->get()`.

**Riesgos:** (a) falsos positivos de stems cortos en Task 1 → ajustar stems; (b) tests viejos que asumían cliente "Personal"/`ActionTool` → se migran, no se borran sin aprobación; (c) `ActionTool` no se elimina hasta Task 14 para no romper approvals existentes.
