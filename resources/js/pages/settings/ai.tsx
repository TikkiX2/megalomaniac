import { Transition } from '@headlessui/react';
import { Form, Head, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, useState, type FormEvent } from 'react';
import AiSettingsController from '@/actions/App/Http/Controllers/Settings/AiSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { Textarea } from '@/components/ui/textarea';
import MainLayout from '@/layouts/main-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { csrfHeaders } from '@/lib/csrf';
import { preview as previewPrompt } from '@/routes/settings/ai/prompts';
import { destroy, store, test as testRoute, update } from '@/routes/settings/ai/providers';
import { update as updateScope } from '@/routes/settings/ai/scopes';
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

/**
 * Valor centinela de la opción «hereda» del `Select` de primario.
 *
 * Radix no admite `value=""` en un `SelectItem`, así que la cadena vacía se
 * representa con esta clave y se traduce a `[]` al patchear.
 */
const INHERIT_OPTION = '__inherit__';

/** Centinela que deja el `Select` «…respaldo» en su placeholder tras elegir. */
const ADD_BACKUP_OPTION = '__add__';

/** Tope de `provider_chain` en `UpdateAiScopeRequest::rules()` (`max:20`). */
const MAX_CHAIN = 20;

/** Nombre legible de un id de proveedor; los ids huérfanos se muestran como `#id`. */
function providerLabel(providers: ProviderRow[], id: number): string {
    return providers.find((provider) => provider.id === id)?.name ?? `#${id}`;
}

/**
 * Texto de la columna «Estado»: la cadena que el resolver usaría hoy para esa
 * fila, con el filtro de salud ya aplicado. Los ids que ya no existen (proveedor
 * borrado en otra pestaña) se omiten en vez de romper la celda.
 */
function effectiveLabel(row: ScopeRow, providers: ProviderRow[]): string {
    const names = row.effective
        .map((id) => providers.find((provider) => provider.id === id)?.name)
        .filter((name): name is string => Boolean(name));

    return names.length > 0 ? `Activo: ${names.join(' → ')}` : 'Sin proveedor';
}

/**
 * Control de cadena de una fila: `Select` de primario + chips de respaldos.
 *
 * El primario NO es un chip: se cambia desde el `Select` y se quita volviendo a
 * la opción de herencia (que deja la fila en `[]`, es decir «hereda de ↑»).
 * Elegir otro proveedor como primario lo mueve al frente y lo saca de donde
 * estuviera entre los respaldos, sin duplicar ids ni perder el resto del orden.
 * Cada cambio emite la cadena completa por `PATCH settings.ai.scopes.update`.
 */
function ScopeChainEditor({
    row,
    providers,
    chain,
    saving,
    error,
    onChange,
}: {
    row: ScopeRow;
    providers: ProviderRow[];
    chain: number[];
    saving: boolean;
    error?: string;
    onChange: (chain: number[]) => void;
}) {
    const primary = chain[0];
    const backups = chain.slice(1);
    const isGlobal = row.section === 'global';

    // El primario lista todos los proveedores (aunque estén deshabilitados) para
    // que un valor ya persistido siempre tenga una opción que lo represente;
    // el de respaldos sólo ofrece habilitados que todavía no están en la cadena.
    const primaryOptions = providers;
    const backupCandidates = providers.filter(
        (provider) => provider.enabled && !chain.includes(provider.id),
    );

    const setPrimary = (value: string) => {
        if (value === INHERIT_OPTION) {
            onChange([]);

            return;
        }

        const id = Number(value);

        onChange([id, ...chain.filter((current) => current !== id)]);
    };

    const addBackup = (value: string) => {
        if (value === ADD_BACKUP_OPTION || chain.includes(Number(value))) {
            return;
        }

        onChange([...chain, Number(value)]);
    };

    const moveBackup = (index: number, delta: number) => {
        const target = index + delta;

        if (target < 0 || target >= backups.length) {
            return;
        }

        const next = [...backups];

        [next[index], next[target]] = [next[target], next[index]];

        onChange([primary, ...next]);
    };

    const removeBackup = (index: number) => {
        onChange([primary, ...backups.filter((_, current) => current !== index)]);
    };

    return (
        <div className="flex flex-col items-start gap-2">
            <div className="flex flex-wrap items-center gap-2">
                <Select
                    value={primary === undefined ? INHERIT_OPTION : String(primary)}
                    onValueChange={setPrimary}
                    disabled={saving}
                >
                    <SelectTrigger
                        className="h-8 w-[220px] border-border bg-background text-xs"
                        aria-label={`Proveedor principal de ${row.label}`}
                        data-test={`scope-primary-${row.scope}`}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={INHERIT_OPTION}>
                            {isGlobal ? 'Todos los habilitados (sort_order)' : 'Hereda de ↑'}
                        </SelectItem>
                        {primaryOptions.map((provider) => (
                            <SelectItem key={provider.id} value={String(provider.id)}>
                                {provider.name}
                                {provider.enabled ? '' : ' (desactivado)'}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {primary !== undefined && (
                    <Select
                        value={ADD_BACKUP_OPTION}
                        onValueChange={addBackup}
                        disabled={saving || chain.length >= MAX_CHAIN || backupCandidates.length === 0}
                    >
                        <SelectTrigger
                            className="h-8 w-[150px] border-border bg-background text-xs text-muted-foreground"
                            aria-label={`Agregar respaldo a ${row.label}`}
                            data-test={`scope-add-backup-${row.scope}`}
                        >
                            <SelectValue placeholder="…respaldo" />
                        </SelectTrigger>
                        <SelectContent>
                            {backupCandidates.map((provider) => (
                                <SelectItem key={provider.id} value={String(provider.id)}>
                                    {provider.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}

                {saving && <Spinner className="size-3.5 text-muted-foreground" />}
            </div>

            {backups.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5">
                    {backups.map((id, index) => {
                        const name = providerLabel(providers, id);

                        return (
                            <span
                                key={id}
                                className="inline-flex items-center gap-0.5 rounded-md border border-border bg-background px-1.5 py-0.5 text-xs text-foreground"
                            >
                                {name}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-5 w-5 p-0 text-muted-foreground hover:text-foreground"
                                    disabled={saving || index === 0}
                                    onClick={() => moveBackup(index, -1)}
                                    aria-label={`Subir ${name}`}
                                >
                                    <span className="material-symbols-outlined text-[13px]">
                                        arrow_upward
                                    </span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-5 w-5 p-0 text-muted-foreground hover:text-foreground"
                                    disabled={saving || index === backups.length - 1}
                                    onClick={() => moveBackup(index, 1)}
                                    aria-label={`Bajar ${name}`}
                                >
                                    <span className="material-symbols-outlined text-[13px]">
                                        arrow_downward
                                    </span>
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-5 w-5 p-0 text-muted-foreground hover:text-destructive"
                                    disabled={saving}
                                    onClick={() => removeBackup(index)}
                                    aria-label={`Quitar ${name}`}
                                    data-test={`scope-remove-backup-${row.scope}-${id}`}
                                >
                                    <span className="material-symbols-outlined text-[13px]">
                                        close
                                    </span>
                                </Button>
                            </span>
                        );
                    })}
                </div>
            )}

            {error && <InputError className="text-xs" message={error} />}
        </div>
    );
}

/**
 * Pestaña «Asignaciones»: matriz jerárquica de scopes (global → superficies →
 * módulos) con la cadena persistida y la cadena que el resolver usaría hoy.
 *
 * El estado local es un mapa `scope → chain[]` sembrado desde la prop `scopes`
 * y re-sincronizado en render cuando Inertia trae props nuevas (tras cada PATCH
 * o si otra pestaña cambió algo). Cada edición hace un PATCH optimista y, si el
 * backend rechaza la cadena, el error de `provider_chain` queda en la fila y el
 * re-seed de las props la revierte al valor del servidor.
 */
function AssignmentsTab({
    providers,
    scopes,
}: {
    providers: ProviderRow[];
    scopes: ScopeRow[];
}) {
    const chainsFrom = (rows: ScopeRow[]) =>
        Object.fromEntries(rows.map((row) => [row.scope, row.chain]));

    const [synced, setSynced] = useState<ScopeRow[]>(scopes);
    const [chains, setChains] = useState<Record<string, number[]>>(() => chainsFrom(scopes));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [savingScope, setSavingScope] = useState<string | null>(null);

    // Las props cambiaron (respuesta del PATCH o recarga): la fila vuelve al
    // valor del servidor, que es la única fuente de verdad de la cadena.
    if (synced !== scopes) {
        setSynced(scopes);
        setChains(chainsFrom(scopes));
    }

    const persist = (scope: string, next: number[]) => {
        setChains((current) => ({ ...current, [scope]: next }));
        setErrors((current) => {
            const rest = { ...current };

            delete rest[scope];

            return rest;
        });
        setSavingScope(scope);

        router.patch(
            updateScope.url({ scope }),
            { provider_chain: next },
            {
                preserveScroll: true,
                onSuccess: () => setSavingScope(null),
                onError: (validationErrors) => {
                    setSavingScope(null);
                    setErrors((current) => ({
                        ...current,
                        [scope]:
                            validationErrors.provider_chain ??
                            'No se pudo guardar la cadena de este scope.',
                    }));
                },
            },
        );
    };

    const groups: { key: ScopeRow['section']; title: string; rows: ScopeRow[] }[] = [
        { key: 'global', title: 'Global', rows: [] },
        { key: 'surface', title: 'Superficies', rows: [] },
        { key: 'module', title: 'Módulos', rows: [] },
    ];

    for (const row of scopes) {
        groups.find((group) => group.key === row.section)?.rows.push(row);
    }

    return (
        <div className="rounded-xl bg-card border border-border p-4 space-y-4">
            <div>
                <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                    <span className="material-symbols-outlined text-primary text-[18px]">
                        account_tree
                    </span>
                    Asignaciones
                </h3>
                <p className="text-xs text-muted-foreground">
                    Cada fila define la cadena completa de su contexto: gana la primera
                    fila con cadena (superficie → módulo → global) y nunca se mezclan.
                    Sin cadena, la fila hereda la de arriba.
                </p>
            </div>

            {providers.length === 0 ? (
                <div className="flex flex-col items-center gap-3 py-10 text-center">
                    <span className="material-symbols-outlined text-primary text-[40px]">
                        account_tree
                    </span>
                    <p className="text-sm text-muted-foreground">
                        Agregá un proveedor en «Proveedores» para poder asignarlo.
                    </p>
                </div>
            ) : (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Scope</TableHead>
                            <TableHead>Cadena</TableHead>
                            <TableHead>Estado</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {groups.map((group) =>
                            group.rows.length === 0 ? null : (
                                <Fragment key={group.key}>
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell colSpan={3} className="bg-background/60 py-1.5">
                                            <span className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                                {group.title}
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                    {group.rows.map((row) => (
                                        <TableRow key={row.scope}>
                                            <TableCell className="align-top">
                                                <span className="block font-medium text-foreground">
                                                    {row.label}
                                                </span>
                                                <span className="block font-mono text-[10px] text-muted-foreground">
                                                    {row.scope}
                                                </span>
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <ScopeChainEditor
                                                    row={row}
                                                    providers={providers}
                                                    chain={chains[row.scope] ?? row.chain}
                                                    saving={savingScope === row.scope}
                                                    error={errors[row.scope]}
                                                    onChange={(next) => persist(row.scope, next)}
                                                />
                                            </TableCell>
                                            <TableCell className="align-top">
                                                <span className="text-xs text-muted-foreground">
                                                    {effectiveLabel(row, providers)}
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </Fragment>
                            ),
                        )}
                    </TableBody>
                </Table>
            )}
        </div>
    );
}

/**
 * Tope de caracteres por capa.
 *
 * Espejo de `AiPromptComposer::MAX_CHARS` (el que aplica
 * `UpdateAiScopeRequest::rules()`); el backend sigue siendo la autoridad y el
 * contador sólo evita el viaje de ida y vuelta cuando el texto ya excede.
 */
const MAX_PROMPT_CHARS = 8000;

/** Margen desde el que el contador pasa a avisar, en caracteres. */
const PROMPT_WARN_CHARS = 7200;

/**
 * Scope sin prompt editable.
 *
 * `surface:embeddings` no compone instrucciones: sólo genera vectores, así que
 * la pestaña lo oculta en vez de ofrecer una capa que el agente nunca lee. El
 * enum no se replica acá — el filtro trabaja sobre el valor, no sobre la lista.
 */
const PROMPT_HIDDEN_SCOPE = 'surface:embeddings';

/** Scope inicial de la pestaña mientras haya filas de la prop `scopes`. */
const DEFAULT_PROMPT_SCOPE = 'global';

/**
 * Orden de las secciones del `Select` de scope, con el copy de cada una.
 *
 * Las filas salen de la prop `scopes` (que ya viene en el orden de
 * `AiScope::cases()` y trae `label` del enum), así que la lista de módulos y
 * superficies nunca se duplica en el frontend.
 */
const PROMPT_SECTIONS: { key: ScopeRow['section']; title: string }[] = [
    { key: 'global', title: 'Global' },
    { key: 'surface', title: 'Superficies' },
    { key: 'module', title: 'Módulos' },
];

/**
 * Pestaña «Prompts»: una capa editable por scope con su contador y la vista
 * previa del prompt compuesto.
 *
 * `surface:embeddings` queda fuera (no compone instrucciones). Para el scope
 * elegido se edita `scopes[scope].prompt` y se persiste con
 * `PATCH settings.ai.scopes.update`; un texto vacío viaja como `''` y el backend
 * lo guarda como `null`, que es exactamente lo que hace «Vaciar capa».
 *
 * La capa se guarda bajo demanda (botón «Guardar capa»), no en cada tecla: es
 * texto de hasta 8.000 caracteres y la escritura automática dispararía un PATCH
 * por pulsación.
 */
function PromptsTab({ scopes }: { scopes: ScopeRow[] }) {
    const promptsFrom = (rows: ScopeRow[]) =>
        Object.fromEntries(
            rows
                .filter((row) => row.scope !== PROMPT_HIDDEN_SCOPE)
                .map((row) => [row.scope, row.prompt ?? '']),
        );

    const editable = scopes.filter((row) => row.scope !== PROMPT_HIDDEN_SCOPE);

    const [synced, setSynced] = useState<ScopeRow[]>(scopes);
    const [scope, setScope] = useState<string>(
        editable.some((row) => row.scope === DEFAULT_PROMPT_SCOPE)
            ? DEFAULT_PROMPT_SCOPE
            : (editable[0]?.scope ?? DEFAULT_PROMPT_SCOPE),
    );
    const [drafts, setDrafts] = useState<Record<string, string>>(() => promptsFrom(scopes));
    const [error, setError] = useState<string | undefined>(undefined);
    const [saving, setSaving] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [preview, setPreview] = useState<{ text: string; error: string | null } | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);

    // Las props cambiaron (respuesta del PATCH o recarga): la capa vuelve al
    // valor del servidor, que es la única fuente de verdad.
    if (synced !== scopes) {
        setSynced(scopes);
        setDrafts(promptsFrom(scopes));
    }

    const row = scopes.find((candidate) => candidate.scope === scope);
    const draft = drafts[scope] ?? row?.prompt ?? '';
    const length = draft.length;
    const over = length > MAX_PROMPT_CHARS;
    const warning = !over && length >= PROMPT_WARN_CHARS;
    const dirty = draft !== (row?.prompt ?? '');

    /**
     * Compone el prompt final de un scope y lo pinta en la vista previa.
     *
     * Va por `fetch` y no por Inertia a propósito: la respuesta es
     * `{preview}` y el endpoint siempre responde 200, así que se pinta sin
     * navegación ni recargar las props de la página. Es idempotente, así que
     * el trigger, el cambio de scope y el guardado tras un PATCH la comparten.
     */
    const loadPreviewFor = async (target: string) => {
        setPreviewLoading(true);

        try {
            const response = await fetch(previewPrompt.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ scope: target }),
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => null);

                setPreview({
                    text: '',
                    error:
                        payload?.message ??
                        `No se pudo componer la vista previa (error ${response.status}).`,
                });

                return;
            }

            const payload: { preview?: string } = await response.json();

            setPreview({ text: payload.preview ?? '', error: null });
        } catch {
            setPreview({ text: '', error: 'No se pudo contactar al servidor.' });
        } finally {
            setPreviewLoading(false);
        }
    };

    // Al cambiar de scope se recompone: la preview de otra capa no sirve.
    const selectScope = (next: string) => {
        setScope(next);
        setError(undefined);
        void loadPreviewFor(next);
    };

    /**
     * Persiste la capa del scope elegido. `prompt: ''` es la señal de «vaciar»:
     * `updateScope` la convierte en `null` antes de escribir.
     */
    const persist = (value: string) => {
        setError(undefined);
        setSaving(true);

        router.patch(
            updateScope.url({ scope }),
            { prompt: value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSaving(false);
                    void loadPreviewFor(scope);
                },
                onError: (validationErrors) => {
                    setSaving(false);
                    setError(
                        validationErrors.prompt ??
                            'No se pudo guardar la capa de este scope.',
                    );
                },
            },
        );
    };

    return (
        <div className="rounded-xl bg-card border border-border p-4 space-y-4">
            <div>
                <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                    <span className="material-symbols-outlined text-primary text-[18px]">
                        edit_note
                    </span>
                    Prompts
                </h3>
                <p className="text-xs text-muted-foreground">
                    Cada scope aporta una capa al prompt: se suman en orden global →
                    módulo → superficie y sólo se incluyen las que tienen texto. El
                    contexto de runtime (skills, memoria, documentos del hilo) se
                    agrega después y no se editá acá.
                </p>
            </div>

            {editable.length === 0 ? (
                <div className="flex flex-col items-center gap-3 py-10 text-center">
                    <span className="material-symbols-outlined text-primary text-[40px]">
                        edit_note
                    </span>
                    <p className="text-sm text-muted-foreground">
                        No hay scopes disponibles para personalizar.
                    </p>
                </div>
            ) : (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="prompt-scope">Scope</Label>
                        <Select value={scope} onValueChange={selectScope} disabled={saving}>
                            <SelectTrigger
                                id="prompt-scope"
                                className="h-9 w-full border-border bg-background text-sm sm:w-[320px]"
                                aria-label="Scope del prompt"
                                data-test="prompt-scope"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {PROMPT_SECTIONS.map((section) => {
                                    const rows = editable.filter((candidate) => candidate.section === section.key);

                                    return rows.length === 0 ? null : (
                                        <SelectGroup key={section.key}>
                                            <SelectLabel>{section.title}</SelectLabel>
                                            {rows.map((candidate) => (
                                                <SelectItem key={candidate.scope} value={candidate.scope}>
                                                    {candidate.label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    );
                                })}
                            </SelectContent>
                        </Select>
                        <p className="text-xs text-muted-foreground">
                            {row?.label} · <span className="font-mono">{row?.scope}</span>
                        </p>
                    </div>

                    <div className="grid gap-2">
                        <div className="flex items-center justify-between gap-2">
                            <Label htmlFor="prompt-layer">Capa de personalización</Label>
                            <span
                                className={`font-mono text-xs ${
                                    over
                                        ? 'text-destructive'
                                        : warning
                                          ? 'text-primary'
                                          : 'text-muted-foreground'
                                }`}
                                data-test="prompt-counter"
                            >
                                {length}/{MAX_PROMPT_CHARS}
                            </span>
                        </div>

                        <Textarea
                            id="prompt-layer"
                            value={draft}
                            onChange={(event) => setDrafts((current) => ({ ...current, [scope]: event.target.value }))}
                            rows={8}
                            placeholder="Instrucciones extra para este scope: tono, formato de respuesta, atajos…"
                            aria-invalid={over}
                            className={`bg-background text-sm ${over ? 'border-destructive' : warning ? 'border-primary' : ''}`}
                            disabled={saving}
                            data-test="prompt-textarea"
                        />

                        <InputError className="text-xs" message={error} />

                        {over && (
                            <p className="text-xs text-destructive">
                                Pasás el límite de {MAX_PROMPT_CHARS} caracteres: recortá la capa para
                                poder guardarla.
                            </p>
                        )}

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                className="bg-primary font-bold"
                                onClick={() => persist(draft)}
                                disabled={saving || over || !dirty}
                                data-test="prompt-save"
                            >
                                {saving && <Spinner className="size-3.5" />}
                                Guardar capa
                            </Button>

                            <Button
                                type="button"
                                variant="ghost"
                                className="text-destructive hover:text-destructive"
                                onClick={() => persist('')}
                                disabled={saving || draft === ''}
                                data-test="prompt-clear"
                            >
                                <span className="material-symbols-outlined text-[16px]">
                                    delete_sweep
                                </span>
                                Vaciar capa
                            </Button>

                            {!dirty && !saving && (
                                <span className="text-xs text-muted-foreground">
                                    Sin cambios para guardar.
                                </span>
                            )}
                        </div>

                        <p className="text-xs text-muted-foreground">
                            «Vaciar capa» borra la capa de este scope y la deja sin
                            texto: el scope sigue heredando las capas de arriba.
                        </p>
                    </div>

                    <Collapsible
                        open={previewOpen}
                        onOpenChange={(open) => {
                            setPreviewOpen(open);

                            if (open && preview === null) {
                                void loadPreviewFor(scope);
                            }
                        }}
                        className="rounded-lg border border-border bg-background"
                    >
                        <CollapsibleTrigger
                            className="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-black uppercase tracking-widest text-foreground hover:text-primary"
                            data-test="prompt-preview-toggle"
                        >
                            <span className="material-symbols-outlined text-primary text-[16px]">
                                visibility
                            </span>
                            Vista previa del prompt final
                            {previewLoading && <Spinner className="size-3.5 text-muted-foreground" />}
                        </CollapsibleTrigger>

                        <CollapsibleContent className="border-t border-border">
                            <div className="space-y-2 p-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="text-xs text-muted-foreground">
                                        Instrucciones base + capas de {row?.label}. Se recompone
                                        al cambiar de scope y tras cada guardado.
                                    </p>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="text-xs"
                                        onClick={() => void loadPreviewFor(scope)}
                                        disabled={previewLoading}
                                        data-test="prompt-preview-refresh"
                                    >
                                        <span className="material-symbols-outlined text-[16px]">
                                            refresh
                                        </span>
                                        Actualizar vista previa
                                    </Button>
                                </div>

                                {previewLoading && preview === null ? (
                                    <div className="flex items-center gap-2 py-4 text-xs text-muted-foreground">
                                        <Spinner className="size-3.5" />
                                        Componiendo el prompt…
                                    </div>
                                ) : preview?.error ? (
                                    <p className="text-xs text-destructive">{preview.error}</p>
                                ) : (
                                    <pre
                                        className="max-h-[320px] overflow-auto whitespace-pre-wrap break-words rounded-md border border-border bg-card p-3 font-mono text-xs text-foreground"
                                        data-test="prompt-preview"
                                    >
                                        {preview?.text || 'Sin prompt para este scope.'}
                                    </pre>
                                )}
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </>
            )}
        </div>
    );
}

export default function AiSettings({ ai, providers, scopes }: AiSettingsPageProps) {
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

                        <TabsContent value="assignments" className="space-y-3">
                            <AssignmentsTab providers={providers} scopes={scopes} />
                        </TabsContent>

                        <TabsContent value="prompts" className="space-y-3">
                            <PromptsTab scopes={scopes} />
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
