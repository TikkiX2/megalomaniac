# Layouts persistentes, zona horaria y AI manual — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Que los layouts de la app dejen de remontarse en cada navegación, que la racha de gym use la zona horaria del usuario y que ninguna pantalla dispare pedidos de IA automáticamente.

**Architecture:** Los layouts pasan a ser persistentes de Inertia (`Component.layout`) con `MainLayout` como raíz y el layout de módulo anidado. La zona horaria se guarda por usuario y se usa solo para agrupar fechas de gym (los timestamps siguen en UTC). Las tarjetas de IA se llenan únicamente cuando la persona aprieta un botón.

**Tech Stack:** Laravel 12, Inertia v2.3, React 19, TypeScript, Tailwind v4, Pest.

**Spec:** este documento (diseño aprobado en conversación el 2026-11-10).

## Global Constraints

- No agregar dependencias PHP ni JS.
- No cambiar `config('app.timezone')`; sigue en UTC. Solo cambia el bucketing de fechas de gym.
- Los timestamps se guardan en UTC; la zona del usuario se aplica al leer/agrupar.
- La UI sigue en castellano, salvo textos existentes en inglés que no se tocan.
- Ningún endpoint de IA (`/ai/insights/*`, `/ai/suggest-*`, `/ai/generate-*`) se llama al montar una página; solo desde un handler de click.
- Correr `vendor/bin/pint --dirty --format agent` si se tocó PHP.
- Correr `php artisan test --compact` con el filtro indicado en cada tarea.
- Después de tocar frontend: `npm run types`, `npm run lint` y `npm run build`.
- No modificar `resources/js/pages/auth/two-factor-challenge.tsx` en la tarea de auth: su título depende de estado local y queda envuelto como hoy.

## Review Focus

- Navegar entre módulos mantiene montado `MainLayout` (sidebar, header, scroll) y no produce doble shell.
- Cada página convertida sigue renderizando exactamente una vez el chrome de la app.
- Un entrenamiento de 23:00 hora local (02:00 UTC del día siguiente) cuenta para el día local correcto.
- La racha sigue viva si ayer hubo entreno y hoy todavía no; se corta recién con dos días sin entreno.
- Ninguna de gym/history, grocery, finance dashboard ni finance statistics dispara `/ai/insights/*` al entrar.
- Cambiar de hilo en chat conserva el rail montado y el header sigue permitiendo renombrar/fijar/eliminar.
- Las páginas de auth conservan su título y descripción; el 2FA sigue funcionando.

---

### Task 1: Zona horaria por usuario

**Files:**
- Create: `database/migrations/2026_11_10_000000_add_timezone_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `app/Concerns/ProfileValidationRules.php`
- Modify: `app/Http/Controllers/Settings/ProfileController.php`
- Modify: `resources/js/pages/settings/profile.tsx`
- Modify: `resources/js/types/auth.ts`
- Test: `tests/Feature/Settings/ProfileUpdateTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: `User::localTimezone(): string`; columna `users.timezone` (string 64, default `America/Argentina/Buenos_Aires`); prop Inertia `timezones: string[]` en `settings/profile`.

- [ ] **Step 1: Escribir los tests que fallan**

Agregar a `tests/Feature/Settings/ProfileUpdateTest.php`:

```php
test('timezone can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'America/Montevideo',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->timezone)->toBe('America/Montevideo');
});

test('invalid timezone is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'timezone' => 'Not/AZone',
        ])
        ->assertSessionHasErrors('timezone')
        ->assertRedirect(route('profile.edit'));
});
```

- [ ] **Step 2: Correr los tests para verlos fallar**

Run: `php artisan test --compact --filter="timezone"`
Expected: FAIL porque no existe la columna ni la regla.

- [ ] **Step 3: Crear la migración**

`database/migrations/2026_11_10_000000_add_timezone_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('America/Argentina/Buenos_Aires')->after('target_weight');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }
};
```

- [ ] **Step 4: Modelo y validación**

En `app/Models/User.php` agregar `'timezone'` a `$fillable` y este método público:

```php
public function localTimezone(): string
{
    return $this->timezone ?: config('app.timezone');
}
```

En `app/Concerns/ProfileValidationRules.php` agregar a `profileRules()`:

```php
'timezone' => ['nullable', 'string', 'timezone'],
```

- [ ] **Step 5: Pasar la lista de zonas al perfil**

En `app/Http/Controllers/Settings/ProfileController.php`, importar `DateTimeZone` y en `edit()` agregar:

```php
'timezones' => collect(DateTimeZone::listIdentifiers(DateTimeZone::ALL))->values(),
```

- [ ] **Step 6: Agregar el selector en el perfil**

En `resources/js/pages/settings/profile.tsx`:
- Tipar la prop `timezones: string[]`.
- Después del bloque de métricas corporales, agregar una sección "Preferencias" con un `<select name="timezone" defaultValue={auth.user.timezone ?? 'America/Argentina/Buenos_Aires'}>` que mapee `timezones`.
- Incluir `<InputError message={errors.timezone} />` y el texto de ayuda: `Se usa para calcular tu racha y los días de entrenamiento.`
- Estilos: reutilizar las clases de `Input`/`Label` del mismo archivo (`bg-background`, `border-border`, etc.).

- [ ] **Step 7: Actualizar el tipo compartido**

En `resources/js/types/auth.ts`, dentro del tipo `User`, agregar:

```ts
timezone?: string | null;
```

- [ ] **Step 8: Correr tests y formato**

Run: `php artisan test --compact --filter="timezone"`
Expected: PASS (2 tests).

Run: `vendor/bin/pint --dirty --format agent`
Expected: sin errores.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_11_10_000000_add_timezone_to_users_table.php app/Models/User.php app/Concerns/ProfileValidationRules.php app/Http/Controllers/Settings/ProfileController.php resources/js/pages/settings/profile.tsx resources/js/types/auth.ts tests/Feature/Settings/ProfileUpdateTest.php
git commit -m "feat(settings): zona horaria por usuario"
```

---

### Task 2: Racha y volumen semanal en hora local

**Files:**
- Modify: `app/Http/Controllers/Gym/ExerciseController.php`
- Test: `tests/Feature/Gym/StreakTest.php`

**Interfaces:**
- Consumes: `User::localTimezone()` de Task 1.
- Produces: props `streak.current`, `streak.days[]`, `weeklyVolumeByDay[]` calculados en hora local.

- [ ] **Step 1: Escribir el test que falla**

Crear `tests/Feature/Gym/StreakTest.php`:

```php
<?php

use App\Models\User;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function freezeAt(string $utc): void
{
    Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
}

afterEach(function () {
    Carbon::setTestNow();
});

it('buckets late workouts in the user timezone', function () {
    freezeAt('2026-11-10 12:00:00');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    // 23:00 del 9/11 en Buenos Aires == 02:00 UTC del 10/11.
    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-10 02:00:00']);
    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-09 02:00:00']);
    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-08 02:00:00']);

    $this->actingAs($user)->get('/fitness/gym')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('streak.current', 3)
            ->where('streak.days.5.date', '2026-11-09')
            ->where('streak.days.5.hasWorkout', true)
            ->where('weeklyVolumeByDay.5.date', '2026-11-09'));
});

it('keeps the streak alive when today has no workout yet', function () {
    freezeAt('2026-11-10 12:00:00');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-10 02:00:00']); // 9/11 local
    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-09 02:00:00']); // 8/11 local

    $this->actingAs($user)->get('/fitness/gym')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('streak.current', 2));
});

it('breaks the streak after two days without training', function () {
    freezeAt('2026-11-10 12:00:00');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-08 02:00:00']); // 7/11 local

    $this->actingAs($user)->get('/fitness/gym')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('streak.current', 0));
});

it('counts a workout started after local midnight as today', function () {
    freezeAt('2026-11-10 12:00:00');

    $user = User::factory()->create(['timezone' => 'America/Argentina/Buenos_Aires']);

    Workout::factory()->create(['user_id' => $user->id, 'started_at' => '2026-11-10 03:30:00']); // 00:30 local del 10/11

    $this->actingAs($user)->get('/fitness/gym')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('streak.current', 1)
            ->where('streak.days.6.date', '2026-11-10'));
});
```

- [ ] **Step 2: Correr el test para verlo fallar**

Run: `php artisan test --compact tests/Feature/Gym/StreakTest.php`
Expected: FAIL (fechas en UTC / racha en 0).

- [ ] **Step 3: Implementar el bucketing local**

En `app/Http/Controllers/Gym/ExerciseController.php`:
- Importar `Illuminate\Support\Carbon`.
- Al inicio de `index()`, después de `$user`:

```php
$timezone = $user->localTimezone();
$today = Carbon::now($timezone)->startOfDay();
$windowStart = $today->copy()->subDays(6);
```

- La consulta semanal usa `->where('started_at', '>=', $windowStart->copy()->utc())`.
- Para agrupar, usar la fecha local:

```php
$key = $w->started_at->copy()->setTimezone($timezone)->toDateString();
```

- Armar `$labels` y `$weeklyVolumeByDay` recorriendo del día `-6` a hoy con `$windowStart->copy()->addDays($i)`.
- Para la racha completa:

```php
$streakDates = $user->workouts()
    ->get(['started_at'])
    ->map(fn (Workout $workout) => $workout->started_at->copy()->setTimezone($timezone)->toDateString())
    ->unique()
    ->flip();

$cursor = $today->copy();

if (! isset($streakDates[$cursor->toDateString()])) {
    $cursor->subDay();
}

$currentStreak = 0;

while (isset($streakDates[$cursor->toDateString()])) {
    $currentStreak++;
    $cursor->subDay();
}
```

- `$streakDays` se arma con los mismos 7 días locales y `hasWorkout` desde las fechas locales.
- En `suggestedRoutine`, reemplazar `now()->format('l')` por `now($timezone)->format('l')`.
- Importar `App\Models\Workout` si hace falta para el closure tipado.

- [ ] **Step 4: Correr el test para verlo pasar**

Run: `php artisan test --compact tests/Feature/Gym/StreakTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Correr los tests de gym existentes**

Run: `php artisan test --compact tests/Feature/Gym`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Gym/ExerciseController.php tests/Feature/Gym/StreakTest.php
git commit -m "fix(gym): calcular racha y volumen en la zona horaria del usuario"
```

---

### Task 3: IA solo a pedido

**Files:**
- Modify: `resources/js/components/ai/AiInsightCard.tsx`
- Modify: `resources/js/pages/fitness/history.tsx`
- Modify: `resources/js/pages/fitness/grocery.tsx`
- Modify: `resources/js/pages/finance/dashboard.tsx`
- Modify: `resources/js/pages/finance/statistics/index.tsx`

**Interfaces:**
- Consumes: endpoints existentes `/ai/insights/workout`, `/ai/insights/grocery`, `/ai/insights/finance`.
- Produces: `AiInsightCard` con props opcionales `onRequest?: () => void`, `requestLabel?: string`, `error?: string | null`.

- [ ] **Step 1: Extender `AiInsightCard`**

Agregar las tres props opcionales. Comportamiento:
- `loading`: spinner como hoy.
- `error`: bloque con el mensaje y botón "Reintentar" que llama `onRequest`.
- `insight`: texto y, si hay `onRequest`, botón "Actualizar análisis".
- Sin insight, sin loading y sin error: estado vacío actual, pero con botón `requestLabel ?? 'Analizar con IA'` que llama `onRequest`.
- Si no hay `onRequest`, mantener el comportamiento actual (usado por tareas).
- No cambiar los estilos base ni el layout de la tarjeta.

- [ ] **Step 2: Quitar el fetch automático de gym/history**

En `resources/js/pages/fitness/history.tsx`:
- Eliminar el `useEffect` que llama `fetchAiInsight()` al montar.
- Quitar `useEffect` del import de React si queda sin uso.
- En la sección "AI Analysis", cuando no haya `aiInsight`, `loadingAiInsight` ni `aiInsightError`, mostrar un botón "Analizar con IA" que llame `fetchAiInsight`.
- Mantener "Try Again" y "Refresh Analysis" existentes.
- No cambiar el endpoint ni los estados.

- [ ] **Step 3: Quitar el fetch automático de grocery**

En `resources/js/pages/fitness/grocery.tsx`:
- Eliminar el `useEffect` de montaje.
- Cambiar `useState(true)` de `insightLoading` a `useState(false)`.
- Extraer el fetch del botón "AI Shopping List" a `const fetchInsight = () => { setInsightLoading(true); fetch('/ai/insights/grocery')...finally(() => setInsightLoading(false)); }`.
- El botón "AI Shopping List" llama `fetchInsight()` y se deshabilita mientras `insightLoading`.
- La tarjeta sigue renderizándose solo si `groceryInsight || insightLoading`.

- [ ] **Step 4: Quitar el fetch automático de finanzas**

En `resources/js/pages/finance/dashboard.tsx` y `resources/js/pages/finance/statistics/index.tsx`:
- Eliminar el `useEffect` de montaje y el import de `useEffect` si queda sin uso.
- Cambiar `insightLoading` a `false` inicial.
- Crear `const fetchInsight = () => { setInsightLoading(true); fetch('/ai/insights/finance').then...finally(() => setInsightLoading(false)); }`.
- Pasar `onRequest={fetchInsight}` y `requestLabel="Analizar con IA"` a `AiInsightCard`.
- Para estadísticas, además mantener la tarjeta visible aunque no haya datos (el estado vacío con botón la reemplaza).

- [ ] **Step 5: Verificar que no quedan fetches automáticos**

Run: `grep -rn "ai/insights" resources/js/pages`
Expected: todas las apariciones están dentro de funciones llamadas por clicks; ninguna dentro de un `useEffect` con dependencia vacía.

- [ ] **Step 6: Verificar frontend**

Run: `npm run types`
Expected: sin errores.

Run: `npm run lint`
Expected: sin errores.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/ai/AiInsightCard.tsx resources/js/pages/fitness/history.tsx resources/js/pages/fitness/grocery.tsx resources/js/pages/finance/dashboard.tsx resources/js/pages/finance/statistics/index.tsx
git commit -m "fix(ai): pedir insights solo cuando la persona lo pide"
```

---

### Task 4: Layouts persistentes de la app

**Files:**
- Create: `resources/js/lib/page-layout.tsx`
- Create: `resources/js/lib/resolve-page.tsx`
- Modify: `resources/js/layouts/gym-layout.tsx`
- Modify: `resources/js/layouts/nutrition-layout.tsx`
- Modify: `resources/js/layouts/grocery-layout.tsx`
- Modify: `resources/js/layouts/supplement-layout.tsx`
- Modify: `resources/js/layouts/health-layout.tsx`
- Modify: `resources/js/layouts/people-layout.tsx`
- Modify: `resources/js/app.tsx`
- Modify: `resources/js/ssr.tsx`
- Modify: todas las páginas bajo `resources/js/pages/` que importen `@/layouts/main-layout` o un layout de módulo, excepto `resources/js/pages/auth/*` y `resources/js/pages/ai/{chat,thread}.tsx`.

**Interfaces:**
- Consumes: `MainLayout`, layouts de módulo y `SettingsLayout` existentes.
- Produces: `resolvePageLayout(name: string)` y `resolvePage(name: string)`; cada página deja de envolverse a sí misma.

- [ ] **Step 1: Convertir los layouts de módulo en contenido puro**

Cada uno de `gym-layout.tsx`, `nutrition-layout.tsx`, `grocery-layout.tsx`, `supplement-layout.tsx`, `health-layout.tsx` y `people-layout.tsx` debe quedar así (mismo nombre de archivo y de export):

```tsx
import type { ReactNode } from 'react';

export default function GymLayout({ children }: { children: ReactNode }) {
    return <>{children}</>;
}
```

Repetir para cada layout con su nombre. `MainLayout` no se toca.

- [ ] **Step 2: Crear el resolver de layouts**

`resources/js/lib/page-layout.tsx`:

```tsx
import type { ComponentType, ReactNode } from 'react';
import ChatLayout from '@/layouts/chat-layout';
import GroceryLayout from '@/layouts/grocery-layout';
import GymLayout from '@/layouts/gym-layout';
import HealthLayout from '@/layouts/health-layout';
import MainLayout from '@/layouts/main-layout';
import NutritionLayout from '@/layouts/nutrition-layout';
import PeopleLayout from '@/layouts/people-layout';
import SettingsLayout from '@/layouts/settings/layout';
import SupplementLayout from '@/layouts/supplement-layout';

export type LayoutComponent = ComponentType<{ children: ReactNode }>;

export function resolvePageLayout(name: string): LayoutComponent[] | null {
    if (name === 'welcome' || name.startsWith('auth/')) {
        return null;
    }

    if (name.startsWith('ai/chat') || name.startsWith('ai/thread') || name === 'ai/module') {
        return null;
    }

    if (name === 'settings' || name.startsWith('settings/')) {
        return [MainLayout, SettingsLayout];
    }

    if (name.startsWith('health/')) {
        return [MainLayout, HealthLayout];
    }

    if (name.startsWith('people/')) {
        return [MainLayout, PeopleLayout];
    }

    if (name === 'fitness/gym-routine' || name === 'fitness/routines') {
        return [MainLayout, GymLayout];
    }

    if (name === 'fitness/nutrition') {
        return [MainLayout, NutritionLayout];
    }

    if (name === 'fitness/groceries') {
        return [MainLayout, GroceryLayout];
    }

    if (name === 'fitness/supplements') {
        return [MainLayout, SupplementLayout];
    }

    return [MainLayout];
}
```

Nota: los `return null` de chat son temporales; Task 6 los cambia.

- [ ] **Step 3: Crear el resolver de páginas compartido**

`resources/js/lib/resolve-page.tsx`:

```tsx
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType, ReactNode } from 'react';
import { resolvePageLayout } from './page-layout';

type LayoutFunction = (page: ReactNode) => ReactNode;

type PageComponent = ComponentType<Record<string, unknown>> & {
    layout?: ComponentType<{ children: ReactNode }>[] | LayoutFunction | null;
};

type PageModule = {
    default: PageComponent;
};

export async function resolvePage(name: string): Promise<PageModule> {
    const page = (await resolvePageComponent(
        `../pages/${name}.tsx`,
        import.meta.glob('../pages/**/*.tsx'),
    )) as unknown as PageModule;

    if (page.default.layout === undefined) {
        const layout = resolvePageLayout(name);

        if (layout !== null) {
            page.default.layout = layout;
        }
    }

    return page;
}
```

- [ ] **Step 4: Usar el resolver compartido en `app.tsx` y `ssr.tsx`**

En `resources/js/app.tsx`:
- Reemplazar `resolvePageComponent` por `resolvePage`.
- Quitar el import de `resolvePageComponent`.
- `resolve: resolvePage`.

En `resources/js/ssr.tsx`: mismo cambio.

- [ ] **Step 5: Quitar los wrappers de las páginas**

Para cada página que importe `@/layouts/main-layout`, `@/layouts/gym-layout`, `@/layouts/nutrition-layout`, `@/layouts/grocery-layout`, `@/layouts/supplement-layout`, `@/layouts/health-layout` o `@/layouts/people-layout` (excepto `auth/*` y `ai/chat.tsx`/`ai/thread.tsx`):
- Borrar la línea de import del layout.
- Reemplazar la etiqueta de apertura `<MainLayout>` (o el layout de módulo) por `<>` y la de cierre `</MainLayout>` por `</>`.
- En páginas de settings, quitar también los wrappers `<SettingsLayout>` y su import; el resolver los agrega.
- Mantener `<Head>` y todo el contenido interior sin cambios.
- No tocar `resources/js/pages/welcome.tsx`, `resources/js/pages/auth/two-factor-challenge.tsx` ni `resources/js/pages/ai/module.tsx`.

Sugerencia para el implementador: escribir un script Node descartable en `/tmp` que haga los reemplazos por regex, ejecutarlo y luego correr `npx prettier --write resources/js/pages` para normalizar. No commitear el script.

- [ ] **Step 6: Verificar que no quedan wrappers**

Run: `grep -rn "@/layouts/main-layout\|GymLayout\|NutritionLayout\|GroceryLayout\|SupplementLayout\|HealthLayout\|PeopleLayout\|SettingsLayout" resources/js/pages`
Expected: solo aparecen en `auth/*` (AuthLayout) y en los imports que Task 5/Task 6 todavía manejan; ninguna página no-auth/no-chat debe importar los layouts anteriores.

Run: `grep -rn "<MainLayout" resources/js/pages`
Expected: sin resultados.

- [ ] **Step 7: Verificar frontend**

Run: `npm run types`
Expected: sin errores.

Run: `npm run lint`
Expected: sin errores.

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 8: Commit**

```bash
git add resources/js/lib/page-layout.tsx resources/js/lib/resolve-page.tsx resources/js/layouts resources/js/app.tsx resources/js/ssr.tsx resources/js/pages
git commit -m "refactor(layouts): layouts persistentes de Inertia en toda la app"
```

---

### Task 5: Layout persistente en auth

**Files:**
- Modify: `resources/js/pages/auth/login.tsx`
- Modify: `resources/js/pages/auth/register.tsx`
- Modify: `resources/js/pages/auth/forgot-password.tsx`
- Modify: `resources/js/pages/auth/reset-password.tsx`
- Modify: `resources/js/pages/auth/verify-email.tsx`
- Modify: `resources/js/pages/auth/confirm-password.tsx`
- Not modified: `resources/js/pages/auth/two-factor-challenge.tsx`

**Interfaces:**
- Consumes: `AuthLayout` con props `title` y `description`.
- Produces: páginas de auth sin wrapper interno y con `layout` estático.

- [ ] **Step 1: Convertir cada página de auth**

Para cada archivo listado, reemplazar el `export default function X(...) { return (<AuthLayout ...>...</AuthLayout>); }` por:

```tsx
function Login(props: Props) {
    return (
        <>
            {/* contenido interior actual, sin AuthLayout */}
        </>
    );
}

Login.layout = (page: ReactNode) => (
    <AuthLayout title="Log in to your account" description="Enter your email and password below to log in">
        {page}
    </AuthLayout>
);

export default Login;
```

- Mantener los textos exactos de `title` y `description` que hoy tiene cada página.
- Importar `ReactNode` desde `react`.
- Para páginas sin `Props`, tipar con el tipo de props existente o `Record<string, never>`.
- `two-factor-challenge.tsx` no se toca.

- [ ] **Step 2: Verificar tipos y build**

Run: `npm run types`
Expected: sin errores.

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/auth
git commit -m "refactor(auth): layout persistente por página"
```

---

### Task 6: Chat persistente

**Files:**
- Modify: `resources/js/layouts/chat-layout.tsx`
- Modify: `resources/js/pages/ai/chat.tsx`
- Modify: `resources/js/pages/ai/thread.tsx`
- Modify: `resources/js/lib/page-layout.tsx`

**Interfaces:**
- Consumes: `ChatLayout` persistente y props de página `threads`, `thread`.
- Produces: `[MainLayout, ChatLayout]` para `ai/chat`, `ai/module` y `ai/thread`.

- [ ] **Step 1: Convertir `ChatLayout` en layout de rail**

En `resources/js/layouts/chat-layout.tsx`:
- Quitar `MainLayout` y el prop `header`.
- Recibir solo `children: ReactNode`.
- Leer `threads` y `thread` desde `usePage().props` (`threads` es `ChatThread[]`; `thread` puede faltar).
- `activeThreadId` = `(props.thread as ChatThread | undefined)?.id ?? null`.
- Mantener el rail de escritorio, el `Sheet` móvil y la columna derecha:

```tsx
<div className="flex h-[calc(100dvh-3rem)]">
    <aside className="hidden w-72 shrink-0 border-r border-border lg:block">
        <ThreadRail threads={threads} activeThreadId={activeThreadId} />
    </aside>
    <div className="flex min-w-0 flex-1 flex-col">
        {children}
    </div>
</div>
```

- No renderizar `<MainLayout>`.

- [ ] **Step 2: Quitar `ChatLayout` de las páginas**

En `resources/js/pages/ai/chat.tsx`:
- Devolver solo el contenido interior (el `Head`, el contenedor scrolleable y el composer), sin `<ChatLayout>`.
- No tocar la lógica de envío ni el estado.

En `resources/js/pages/ai/thread.tsx`:
- Quitar `<ChatLayout threads={threads} activeThreadId={thread.id} header={...}>`.
- Mover el JSX del `header` al comienzo del contenido, dentro de un `<header className="flex h-14 shrink-0 items-center gap-2 border-b border-border px-3 sm:px-4">` (o las clases equivalentes que tenía `ChatLayout`).
- Mantener el `Sheet` móvil: si el botón de historial estaba en el header de `ChatLayout`, replicarlo dentro del header de la página o dejar que el rail móvil se abra desde `ChatLayout`; no duplicar el `Sheet`.
- Ajustar el contenedor para que el header y el resto sigan en columna y el composer quede abajo.

- [ ] **Step 3: Activar el layout persistente para chat**

En `resources/js/lib/page-layout.tsx`, reemplazar el `return null` de chat por:

```tsx
if (name.startsWith('ai/chat') || name.startsWith('ai/thread') || name === 'ai/module') {
    return [MainLayout, ChatLayout];
}
```

- [ ] **Step 4: Verificar tipos, lint y build**

Run: `npm run types`
Expected: sin errores.

Run: `npm run lint`
Expected: sin errores.

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 5: Verificación manual rápida (si hay servidor)**

Si el servidor está disponible, abrir `/ai/chat`, crear o abrir un hilo, cambiar a otro hilo del rail y confirmar que el rail no se remonta y que renombrar/fijar/eliminar siguen funcionando. Si no hay servidor, dejar constancia en el reporte.

- [ ] **Step 6: Commit**

```bash
git add resources/js/layouts/chat-layout.tsx resources/js/pages/ai/chat.tsx resources/js/pages/ai/thread.tsx resources/js/lib/page-layout.tsx
git commit -m "refactor(chat): layout persistente del rail de hilos"
```

---

### Task 7: Verificación final

**Files:** ninguno (solo verificación).

- [ ] **Step 1: Correr la suite de gym y settings**

Run: `php artisan test --compact tests/Feature/Gym tests/Feature/Settings`
Expected: PASS.

- [ ] **Step 2: Correr tipos, lint y build**

Run: `npm run types`
Expected: sin errores.

Run: `npm run lint`
Expected: sin errores.

Run: `npm run build`
Expected: build exitoso.

- [ ] **Step 3: Buscar restos de wrappers y fetches automáticos**

Run: `grep -rn "<MainLayout\|<GymLayout\|<HealthLayout\|<PeopleLayout\|<SettingsLayout" resources/js/pages`
Expected: sin resultados.

Run: `grep -rn "fetch('/ai/insights" resources/js/pages`
Expected: todas las llamadas dentro de handlers, ninguna en `useEffect` de montaje.

- [ ] **Step 4: Resumen para revisión**

Reportar:
- Tests corridos y resultado.
- Build/lint/types.
- Archivos con cambios.
- Cualquier decisión tomada fuera del plan.
