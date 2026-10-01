import { Transition } from '@headlessui/react';
import { Form, Head, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import AiSettingsController from '@/actions/App/Http/Controllers/Settings/AiSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import MainLayout from '@/layouts/main-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { csrfHeaders } from '@/lib/csrf';
import { destroy, store, test as testRoute, update } from '@/routes/settings/ai/providers';
import type { SharedData } from '@/types';

export interface ProviderHealth {
    consecutive_failures: number;
    broken_until: string | null;
    last_error: string | null;
}

export interface ProviderRow {
    id: number;
    name: string;
    protocol: string;
    url: string;
    model: string;
    embeddings_model: string | null;
    enabled: boolean;
    sort_order: number;
    has_key: boolean;
    health: ProviderHealth | null;
}

/** One `AiScope` case with the chain stored for it (`[]` = hereda) and the chain in use today. */
export interface ScopeRow {
    scope: string;
    label: string;
    section: 'global' | 'surface' | 'module';
    chain: number[];
    effective: number[];
    prompt: string | null;
    prompt_preview: string | null;
}

export interface AiSettingsPageProps {
    ai: {
        ai_enabled: boolean;
        has_tavily_key: boolean;
        tavily_placeholder: string;
    };
    providers: ProviderRow[];
    /** Consumido por la pestaña «Asignaciones» (Task 12). */
    scopes: ScopeRow[];
    /** Módulo => etiqueta, para etiquetar las filas de módulo sin duplicar el enum. */
    module_labels: Record<string, string>;
    flash?: { success?: string | null };
}

/** Resultado inline de «Probar», tal como lo devuelve `settings.ai.providers.test`. */
interface TestResult {
    ok: boolean;
    ms: number;
    error: string | null;
}

/**
 * Estado del circuit breaker en una fila.
 *
 * `broken_until` en el futuro manda: el proveedor está fuera de la cadena
 * hasta ese horario, aunque el contador de fallas siga ahí. Sin salud o con
 * cero fallas consecutivas la fila está `OK`.
 */
function providerStatus(provider: ProviderRow): { label: string; className: string } {
    const failures = provider.health?.consecutive_failures ?? 0;

    if (failures === 0) {
        return { label: 'OK', className: 'border-border text-muted-foreground' };
    }

    const brokenUntil = provider.health?.broken_until
        ? new Date(provider.health.broken_until)
        : null;

    if (brokenUntil && brokenUntil.getTime() > Date.now()) {
        return {
            label: `caído hasta ${brokenUntil.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`,
            className: 'border-primary/40 bg-primary/10 text-primary',
        };
    }

    return {
        label: `${failures} ${failures === 1 ? 'falla' : 'fallas'}`,
        className: 'border-destructive/40 text-destructive',
    };
}

/**
 * Formulario del `Sheet`, en modo create o edit.
 *
 * `provider === null` = create (POST + nombre, URL, key y modelo obligatorios);
 * con provider = edit (PATCH, y la key vacía conserva la guardada porque el
 * backend nunca la devuelve). Se monta con `key` para que al cambiar de fila
 * el `useForm` arranque limpio.
 */
function ProviderSheetForm({
    provider,
    onDone,
}: {
    provider: ProviderRow | null;
    onDone: () => void;
}) {
    const form = useForm({
        name: provider?.name ?? '',
        protocol: provider?.protocol ?? 'openai_compatible',
        url: provider?.url ?? '',
        key: '',
        model: provider?.model ?? '',
        embeddings_model: provider?.embeddings_model ?? '',
        enabled: provider?.enabled ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: onDone };

        if (provider) {
            form.patch(update.url({ provider: provider.id }), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4 px-4 pb-4">
            <div className="grid gap-2">
                <Label htmlFor="provider-name">Nombre</Label>
                <Input
                    id="provider-name"
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    className="bg-background"
                    placeholder="Principal"
                    required
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="provider-url">URL base</Label>
                <Input
                    id="provider-url"
                    type="url"
                    value={form.data.url}
                    onChange={(event) => form.setData('url', event.target.value)}
                    className="bg-background"
                    placeholder="https://api.openai.com/v1"
                    required
                />
                <InputError message={form.errors.url} />
                <p className="text-xs text-muted-foreground">
                    Base OpenAI-compatible, sin <code>/chat/completions</code>. Para OpenCode Go usá
                    https://opencode.ai/zen/go/v1.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="provider-key">API Key</Label>
                <Input
                    id="provider-key"
                    type="password"
                    value={form.data.key}
                    onChange={(event) => form.setData('key', event.target.value)}
                    className="bg-background"
                    placeholder={provider?.has_key ? '•••••••• (guardada)' : 'sk-...'}
                    autoComplete="off"
                    required={!provider}
                />
                <InputError message={form.errors.key} />
                {provider?.has_key && (
                    <p className="text-xs text-muted-foreground">
                        Dejalo vacío para conservar la key guardada.
                    </p>
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="provider-model">Modelo</Label>
                <Input
                    id="provider-model"
                    value={form.data.model}
                    onChange={(event) => form.setData('model', event.target.value)}
                    className="bg-background"
                    placeholder="gpt-4o"
                    required
                />
                <InputError message={form.errors.model} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="provider-embeddings-model">Modelo de embeddings (opcional)</Label>
                <Input
                    id="provider-embeddings-model"
                    value={form.data.embeddings_model}
                    onChange={(event) => form.setData('embeddings_model', event.target.value)}
                    className="bg-background"
                    placeholder="text-embedding-3-small"
                />
                <InputError message={form.errors.embeddings_model} />
                <p className="text-xs text-muted-foreground">
                    Se usa para el ranking semántico del feed. Vacío = sin embeddings.
                </p>
            </div>

            <label className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox
                    checked={form.data.enabled}
                    onCheckedChange={(checked) => form.setData('enabled', checked === true)}
                />
                Activo
            </label>

            <p className="text-xs text-muted-foreground">Protocolo: OpenAI-compatible.</p>

            <div className="flex items-center gap-2">
                <Button type="submit" disabled={form.processing} className="bg-primary font-bold">
                    {provider ? 'Guardar cambios' : 'Agregar proveedor'}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onDone}
                    disabled={form.processing}
                >
                    Cancelar
                </Button>
            </div>
        </form>
    );
}

export default function AiSettings({ ai, providers }: AiSettingsPageProps) {
    const { flash } = usePage<SharedData & AiSettingsPageProps>().props;

    // `provider: null` + `open: true` = modo create.
    const [sheet, setSheet] = useState<{ open: boolean; provider: ProviderRow | null }>({
        open: false,
        provider: null,
    });
    const [testingId, setTestingId] = useState<number | null>(null);
    const [testResults, setTestResults] = useState<Record<number, TestResult>>({});
    const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);

    const closeSheet = () => setSheet({ open: false, provider: null });

    /**
     * Sonda de conexión. No pasa por Inertia a propósito: el endpoint siempre
     * responde 200 (con `ok: false` si falló) para que el resultado se pueda
     * pintar en la fila sin recargar, y así el throttle de 30/min tampoco
     * ensucia la navegación.
     */
    const runTest = async (provider: ProviderRow) => {
        setTestingId(provider.id);
        setTestResults((previous) => {
            const next = { ...previous };
            delete next[provider.id];

            return next;
        });

        const record = (result: TestResult) =>
            setTestResults((previous) => ({ ...previous, [provider.id]: result }));

        try {
            const response = await fetch(testRoute.url({ provider: provider.id }), {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                },
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => null);

                record({
                    ok: false,
                    ms: 0,
                    error:
                        payload?.message ??
                        (response.status === 429
                            ? 'Demasiadas pruebas seguidas, esperá un minuto.'
                            : `Error ${response.status}`),
                });

                return;
            }

            const payload: TestResult = await response.json();

            record({ ok: payload.ok === true, ms: payload.ms, error: payload.error ?? null });
        } catch {
            record({ ok: false, ms: 0, error: 'No se pudo contactar al servidor.' });
        } finally {
            setTestingId(null);
            // La sonda movió el circuit breaker: los badges de estado y las
            // cadenas efectivas quedaron desactualizados.
            router.reload({ only: ['providers', 'scopes'] });
        }
    };

    const toggleEnabled = (provider: ProviderRow, enabled: boolean) => {
        router.patch(
            update.url({ provider: provider.id }),
            { enabled },
            { preserveScroll: true },
        );
    };

    const remove = (provider: ProviderRow) => {
        router.delete(destroy.url({ provider: provider.id }), { preserveScroll: true });
        setConfirmDeleteId(null);
    };

    return (
        <MainLayout>
            <Head title="AI settings" />

            <h1 className="sr-only">AI Settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="AI Settings"
                        description="Configure your AI provider connection and preferences"
                    />

                    {flash?.success && (
                        <div className="rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                            {flash.success}
                        </div>
                    )}

                    <Form
                        {...AiSettingsController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        transform={(data) => ({
                            ...data,
                            ai_enabled: data.ai_enabled === 'on' || data.ai_enabled === true,
                        })}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="rounded-xl bg-card border border-border p-4 space-y-4">
                                    <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                                        <span className="material-symbols-outlined text-primary text-[18px]">power_settings_new</span>
                                        Enable AI Features
                                    </h3>

                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="ai_enabled"
                                            name="ai_enabled"
                                            defaultChecked={ai.ai_enabled}
                                        />
                                        <Label htmlFor="ai_enabled" className="cursor-pointer">
                                            Enable AI-powered features
                                        </Label>
                                    </div>
                                    <InputError className="mt-2" message={errors.ai_enabled} />
                                    <p className="text-xs text-muted-foreground">
                                        When enabled, AI features will be available across the application
                                    </p>
                                </div>

                                <div className="rounded-xl bg-card border border-border p-4 space-y-4">
                                    <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                                        <span className="material-symbols-outlined text-primary text-[18px]">travel_explore</span>
                                        Búsqueda web (Tavily)
                                    </h3>

                                    <div className="grid gap-2">
                                        <Label htmlFor="tavily_api_key">Tavily API Key</Label>
                                        <Input
                                            id="tavily_api_key"
                                            name="tavily_api_key"
                                            type="password"
                                            className="mt-1 block w-full bg-background"
                                            defaultValue=""
                                            placeholder={ai.tavily_placeholder}
                                            autoComplete="off"
                                        />
                                        <InputError className="mt-2" message={errors.tavily_api_key} />
                                        <p className="text-xs text-muted-foreground">
                                            Habilita la búsqueda web y la lectura de páginas en el chat. Déjalo vacío
                                            para conservar la key actual. Consigue una en{' '}
                                            <a
                                                href="https://tavily.com"
                                                target="_blank"
                                                rel="noreferrer"
                                                className="text-primary hover:underline"
                                            >
                                                tavily.com
                                            </a>
                                            : el free tier incluye ~1.000 créditos/mes (1 crédito por búsqueda
                                            básica).
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-ai-settings-button"
                                    >
                                        Save AI Settings
                                    </Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-neutral-600">
                                            Saved
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>

                    <Tabs defaultValue="providers" className="space-y-4">
                        <TabsList className="bg-card border border-border">
                            <TabsTrigger value="providers">Proveedores</TabsTrigger>
                            <TabsTrigger value="assignments">Asignaciones</TabsTrigger>
                            <TabsTrigger value="prompts">Prompts</TabsTrigger>
                        </TabsList>

                        <TabsContent value="providers" className="space-y-3">
                            <div className="rounded-xl bg-card border border-border p-4 space-y-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                                            <span className="material-symbols-outlined text-primary text-[18px]">dns</span>
                                            Proveedores
                                        </h3>
                                        <p className="text-xs text-muted-foreground">
                                            El primero de la lista es el principal; el resto entra como backup
                                            cuando el anterior falla.
                                        </p>
                                    </div>

                                    {providers.length > 0 && (
                                        <Button
                                            onClick={() => setSheet({ open: true, provider: null })}
                                            className="bg-primary font-bold"
                                        >
                                            <span className="material-symbols-outlined text-[18px]">add</span>
                                            Agregar proveedor
                                        </Button>
                                    )}
                                </div>

                                {providers.length === 0 ? (
                                    <div className="flex flex-col items-center gap-3 py-10 text-center">
                                        <span className="material-symbols-outlined text-primary text-[40px]">dns</span>
                                        <p className="text-sm text-muted-foreground">
                                            Todavía no tenés proveedores
                                        </p>
                                        <Button
                                            onClick={() => setSheet({ open: true, provider: null })}
                                            className="bg-primary font-bold"
                                        >
                                            <span className="material-symbols-outlined text-[18px]">add</span>
                                            Agregar proveedor
                                        </Button>
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Nombre</TableHead>
                                                <TableHead>Modelo</TableHead>
                                                <TableHead>URL</TableHead>
                                                <TableHead>Estado</TableHead>
                                                <TableHead className="text-right">Acciones</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {providers.map((provider) => {
                                                const status = providerStatus(provider);
                                                const result = testResults[provider.id];
                                                const testing = testingId === provider.id;

                                                return (
                                                    <TableRow key={provider.id}>
                                                        <TableCell className="font-medium text-foreground">
                                                            {provider.name}
                                                        </TableCell>
                                                        <TableCell className="text-muted-foreground">
                                                            {provider.model}
                                                        </TableCell>
                                                        <TableCell>
                                                            <span
                                                                className="block max-w-[180px] truncate text-muted-foreground"
                                                                title={provider.url}
                                                            >
                                                                {provider.url}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex flex-col items-start gap-1">
                                                                <Badge
                                                                    variant="outline"
                                                                    className={status.className}
                                                                    title={provider.health?.last_error ?? undefined}
                                                                >
                                                                    {status.label}
                                                                </Badge>

                                                                {result && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className={`max-w-[220px] ${
                                                                            result.ok
                                                                                ? 'border-border text-foreground'
                                                                                : 'border-destructive/40 text-destructive'
                                                                        }`}
                                                                        title={result.error ?? undefined}
                                                                    >
                                                                        {result.ok ? '✓' : '✗'}{' '}
                                                                        <span className="truncate">
                                                                            {result.ok
                                                                                ? `${result.ms}ms`
                                                                                : (result.error ?? 'Falló la prueba')}
                                                                        </span>
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center justify-end gap-1">
                                                                <Checkbox
                                                                    checked={provider.enabled}
                                                                    onCheckedChange={(checked) =>
                                                                        toggleEnabled(provider, checked === true)
                                                                    }
                                                                    aria-label={`Activar ${provider.name}`}
                                                                />

                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className="border-border bg-transparent"
                                                                    onClick={() => void runTest(provider)}
                                                                    disabled={testing}
                                                                    data-test={`test-provider-${provider.id}`}
                                                                >
                                                                    {testing ? (
                                                                        <Spinner />
                                                                    ) : (
                                                                        <span className="material-symbols-outlined text-[16px]">network_check</span>
                                                                    )}
                                                                    Probar
                                                                </Button>

                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setSheet({ open: true, provider })
                                                                    }
                                                                    data-test={`edit-provider-${provider.id}`}
                                                                >
                                                                    <span className="material-symbols-outlined text-[16px]">edit</span>
                                                                    Editar
                                                                </Button>

                                                                {confirmDeleteId === provider.id ? (
                                                                    <>
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            variant="destructive"
                                                                            onClick={() => remove(provider)}
                                                                        >
                                                                            Confirmar
                                                                        </Button>
                                                                        <Button
                                                                            type="button"
                                                                            size="sm"
                                                                            variant="ghost"
                                                                            onClick={() => setConfirmDeleteId(null)}
                                                                        >
                                                                            Cancelar
                                                                        </Button>
                                                                    </>
                                                                ) : (
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        className="text-destructive hover:text-destructive"
                                                                        onClick={() => setConfirmDeleteId(provider.id)}
                                                                        data-test={`delete-provider-${provider.id}`}
                                                                    >
                                                                        <span className="material-symbols-outlined text-[16px]">delete</span>
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            })}
                                        </TableBody>
                                    </Table>
                                )}
                            </div>
                        </TabsContent>

                        {/* Task 12 — «Asignaciones»: tabla jerárquica de scopes
                            (global → superficies → módulos) que persiste en
                            `settings.ai.scopes.update` con la prop `scopes` y
                            `module_labels`. */}
                        <TabsContent value="assignments" className="space-y-3">
                            <div className="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">
                                Próximamente: asigná un proveedor principal y backups por módulo y superficie.
                            </div>
                        </TabsContent>

                        {/* Task 13 — «Prompts»: textarea con contador por capa y
                            vista previa con `settings.ai.prompts.preview`. */}
                        <TabsContent value="prompts" className="space-y-3">
                            <div className="rounded-xl border border-border bg-card p-8 text-center text-sm text-muted-foreground">
                                Próximamente: personalizá el prompt por scope y previsualizá el resultado.
                            </div>
                        </TabsContent>
                    </Tabs>
                </div>
            </SettingsLayout>

            <Sheet
                open={sheet.open}
                onOpenChange={(open) => {
                    if (!open) {
                        closeSheet();
                    }
                }}
            >
                <SheetContent className="bg-background border-border overflow-y-auto">
                    <SheetHeader>
                        <SheetTitle className="text-base font-bold">
                            {sheet.provider ? 'Editar proveedor' : 'Agregar proveedor'}
                        </SheetTitle>
                        <SheetDescription>
                            {sheet.provider
                                ? 'Dejá la key vacía para conservar la guardada.'
                                : 'Base OpenAI-compatible + key: la clave se guarda cifrada.'}
                        </SheetDescription>
                    </SheetHeader>

                    <ProviderSheetForm
                        key={sheet.provider ? `edit-${sheet.provider.id}` : 'create'}
                        provider={sheet.provider}
                        onDone={closeSheet}
                    />
                </SheetContent>
            </Sheet>
        </MainLayout>
    );
}
