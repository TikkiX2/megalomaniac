import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import MainLayout from '@/layouts/main-layout';
import { csrfHeaders } from '@/lib/csrf';
import { cn } from '@/lib/utils';
import inspiration from '@/routes/inspiration';
import type { SharedData } from '@/types';

export interface InspirationSourceRow {
    key: string;
    label: string;
    needs_key: boolean;
    has_key: boolean;
    configured: boolean;
    enabled: boolean;
    has_tier3_notice: boolean;
}

export interface InspirationSettingsBag {
    enabled_sources: string[];
    maturity: boolean;
    zerochan_ua: string | null;
    acknowledged_tier3: string[];
}

export interface InspirationSettingsPageProps {
    sources: InspirationSourceRow[];
    settings: InspirationSettingsBag;
    flash?: { success?: string | null; error?: string | null };
}

interface TestResult {
    ok: boolean;
    message?: string;
}

/**
 * Field name persisted under `keys.{source}` for every source whose
 * capabilities require a key. Sources registered so far use a single `key`
 * field; the canonical per-source map lives in `config/inspiration.php`
 * (e.g. `discogs` → `token`, `pixiv` → `refresh_token`).
 */
const DEFAULT_CREDENTIAL_FIELD = 'key';

/**
 * Minimal accessible switch (no dedicated UI primitive exists in the repo).
 *
 * Renders a native button with `role="switch"` so it stays keyboard- and
 * screen-reader-friendly.
 */
function Switch({
    checked,
    onCheckedChange,
    disabled = false,
    label,
}: {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    label: string;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label}
            disabled={disabled}
            onClick={() => onCheckedChange(!checked)}
            className={cn(
                'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full border border-border transition-colors disabled:cursor-not-allowed disabled:opacity-40',
                checked ? 'bg-primary' : 'bg-muted',
            )}
        >
            <span
                className={cn(
                    'inline-block size-3.5 rounded-full bg-background shadow-sm transition-transform',
                    checked ? 'translate-x-[18px]' : 'translate-x-[3px]',
                )}
            />
        </button>
    );
}

export default function InspirationSettings() {
    const { sources, settings, flash } = usePage<
        SharedData & InspirationSettingsPageProps
    >().props;

    const [drafts, setDrafts] = useState<Record<string, string>>({});
    const [revealed, setRevealed] = useState<Record<string, boolean>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState<string | null>(null);
    const [testing, setTesting] = useState<string | null>(null);
    const [results, setResults] = useState<Record<string, TestResult>>({});

    const acknowledged = new Set(settings.acknowledged_tier3);
    const tier3Sources = sources.filter((source) => source.has_tier3_notice);

    /**
     * Every patch carries the complete bag because `update()` replaces it and
     * resets anything the client omits. Keys stay out unless the user typed
     * one: the server merges stored keys for sources the payload does not
     * mention (so an unrelated toggle never wipes a saved credential).
     */
    const basePayload = {
        enabled_sources: settings.enabled_sources,
        maturity: settings.maturity,
        zerochan_ua: settings.zerochan_ua,
        acknowledged_tier3: settings.acknowledged_tier3,
    };

    const toggleSource = (source: InspirationSourceRow, enabled: boolean) => {
        const enabledSources = enabled
            ? Array.from(new Set([...settings.enabled_sources, source.key]))
            : settings.enabled_sources.filter((key) => key !== source.key);

        router.patch(
            inspiration.settings.update.url(),
            { ...basePayload, enabled_sources: enabledSources },
            { preserveScroll: true },
        );
    };

    const toggleMaturity = (maturity: boolean) => {
        router.patch(
            inspiration.settings.update.url(),
            { ...basePayload, maturity },
            { preserveScroll: true },
        );
    };

    const toggleAcknowledgement = (source: string, accepted: boolean) => {
        const acknowledgedTier3 = accepted
            ? Array.from(new Set([...settings.acknowledged_tier3, source]))
            : settings.acknowledged_tier3.filter((key) => key !== source);

        router.patch(
            inspiration.settings.update.url(),
            { ...basePayload, acknowledged_tier3: acknowledgedTier3 },
            { preserveScroll: true },
        );
    };

    const saveKey = (source: InspirationSourceRow) => {
        const value = (drafts[source.key] ?? '').trim();

        setSaving(source.key);
        setErrors((current) => {
            const next = { ...current };
            delete next[source.key];

            return next;
        });

        router.patch(
            inspiration.settings.update.url(),
            {
                ...basePayload,
                keys: { [source.key]: { [DEFAULT_CREDENTIAL_FIELD]: value } },
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSaving(null);
                    setDrafts((current) => {
                        const next = { ...current };
                        delete next[source.key];

                        return next;
                    });
                },
                onError: (validationErrors) => {
                    setSaving(null);
                    setErrors((current) => ({
                        ...current,
                        [source.key]:
                            validationErrors[
                                `keys.${source.key}.${DEFAULT_CREDENTIAL_FIELD}`
                            ] ??
                            Object.values(validationErrors)[0] ??
                            'No se pudo guardar la key de esta fuente.',
                    }));
                },
            },
        );
    };

    /**
     * Connectivity probe. Plain fetch (not Inertia) so the result can be shown
     * inline without a navigation, mirroring the AI providers screen.
     */
    const runTest = async (source: InspirationSourceRow) => {
        setTesting(source.key);
        setResults((current) => {
            const next = { ...current };
            delete next[source.key];

            return next;
        });

        try {
            const response = await fetch(
                inspiration.sources.test.url({ source: source.key }),
                {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        ...csrfHeaders(),
                    },
                },
            );

            const payload: { ok?: boolean; message?: string } | null =
                await response.json().catch(() => null);

            setResults((current) => ({
                ...current,
                [source.key]:
                    response.ok && payload?.ok
                        ? { ok: true }
                        : {
                              ok: false,
                              message:
                                  payload?.message ??
                                  `Error ${response.status}`,
                          },
            }));
        } catch {
            setResults((current) => ({
                ...current,
                [source.key]: {
                    ok: false,
                    message: 'No se pudo contactar al servidor.',
                },
            }));
        } finally {
            setTesting(null);
        }
    };

    return (
        <MainLayout>
            <Head title="Ajustes de inspiración" />

            <div className="mx-auto w-full max-w-6xl space-y-5 px-4 py-6">
                <Heading
                    title="Ajustes de inspiración"
                    description="Elegí qué fuentes usar, guardá tus credenciales y controlá el contenido adulto."
                />

                {flash?.success && (
                    <div className="rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">
                        {flash.error}
                    </div>
                )}

                <section className="rounded-xl border border-border bg-card">
                    <div className="border-b border-border p-4">
                        <h3 className="flex items-center gap-2 text-sm font-black tracking-widest text-foreground uppercase">
                            <span className="material-symbols-outlined text-[18px] text-primary">
                                hub
                            </span>
                            Fuentes
                        </h3>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Las fuentes que requieren key sólo se activan una
                            vez configuradas. La key se guarda en el servidor y
                            nunca se vuelve a mostrar.
                        </p>
                    </div>

                    <div className="overflow-x-auto p-4">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[10px] font-black tracking-widest text-muted-foreground uppercase">
                                    <th className="pr-4 pb-2">Fuente</th>
                                    <th className="pr-4 pb-2">Activa</th>
                                    <th className="pr-4 pb-2">Credencial</th>
                                    <th className="pb-2">Probar</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sources.map((source) => {
                                    const gated =
                                        source.has_tier3_notice &&
                                        !acknowledged.has(source.key);
                                    const result = results[source.key];

                                    return (
                                        <tr
                                            key={source.key}
                                            className="border-t border-border align-top"
                                        >
                                            <td className="py-3 pr-4">
                                                <span className="block font-medium text-foreground">
                                                    {source.label}
                                                </span>
                                                <span className="block font-mono text-[10px] text-muted-foreground">
                                                    {source.key}
                                                </span>
                                                <span className="mt-1 flex flex-wrap gap-1">
                                                    {source.has_tier3_notice && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-primary/40 text-primary"
                                                        >
                                                            Tier 3
                                                        </Badge>
                                                    )}
                                                    {source.needs_key &&
                                                        !source.has_key && (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-destructive/40 text-destructive"
                                                            >
                                                                falta key
                                                            </Badge>
                                                        )}
                                                    {source.has_key && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-primary/40 text-primary"
                                                        >
                                                            key guardada
                                                        </Badge>
                                                    )}
                                                    {!source.configured && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-muted-foreground"
                                                        >
                                                            sin configurar
                                                        </Badge>
                                                    )}
                                                </span>
                                            </td>

                                            <td className="py-3 pr-4">
                                                <Switch
                                                    checked={source.enabled}
                                                    disabled={gated}
                                                    label={`Activar ${source.label}`}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        toggleSource(
                                                            source,
                                                            checked,
                                                        )
                                                    }
                                                />
                                                {gated && (
                                                    <span className="mt-1 block text-[10px] text-muted-foreground">
                                                        Aceptá los riesgos de
                                                        Tier 3 para activarla.
                                                    </span>
                                                )}
                                            </td>

                                            <td className="py-3 pr-4">
                                                {source.needs_key ? (
                                                    <div className="space-y-1">
                                                        <div className="flex items-center gap-1.5">
                                                            <Input
                                                                type={
                                                                    revealed[
                                                                        source
                                                                            .key
                                                                    ]
                                                                        ? 'text'
                                                                        : 'password'
                                                                }
                                                                value={
                                                                    drafts[
                                                                        source
                                                                            .key
                                                                    ] ?? ''
                                                                }
                                                                autoComplete="off"
                                                                placeholder={
                                                                    source.has_key
                                                                        ? '•••••••• (guardada)'
                                                                        : 'Pegá la key'
                                                                }
                                                                aria-label={`Key de ${source.label}`}
                                                                className="h-8 w-44 bg-background font-mono text-xs"
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setDrafts(
                                                                        (
                                                                            current,
                                                                        ) => ({
                                                                            ...current,
                                                                            [source.key]:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        }),
                                                                    )
                                                                }
                                                            />
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-8"
                                                                aria-label={
                                                                    revealed[
                                                                        source
                                                                            .key
                                                                    ]
                                                                        ? 'Ocultar key'
                                                                        : 'Mostrar key'
                                                                }
                                                                onClick={() =>
                                                                    setRevealed(
                                                                        (
                                                                            current,
                                                                        ) => ({
                                                                            ...current,
                                                                            [source.key]:
                                                                                !current[
                                                                                    source
                                                                                        .key
                                                                                ],
                                                                        }),
                                                                    )
                                                                }
                                                            >
                                                                <span className="material-symbols-outlined text-[16px]">
                                                                    {revealed[
                                                                        source
                                                                            .key
                                                                    ]
                                                                        ? 'visibility_off'
                                                                        : 'visibility'}
                                                                </span>
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                size="sm"
                                                                className="h-8"
                                                                disabled={
                                                                    saving ===
                                                                    source.key
                                                                }
                                                                onClick={() =>
                                                                    saveKey(
                                                                        source,
                                                                    )
                                                                }
                                                            >
                                                                {saving ===
                                                                    source.key && (
                                                                    <Spinner className="size-3.5" />
                                                                )}
                                                                Guardar
                                                            </Button>
                                                        </div>
                                                        <InputError
                                                            message={
                                                                errors[
                                                                    source.key
                                                                ]
                                                            }
                                                            className="text-xs"
                                                        />
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        No requiere key
                                                    </span>
                                                )}
                                            </td>

                                            <td className="py-3">
                                                <div className="flex flex-col items-start gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        className="h-8"
                                                        disabled={
                                                            testing ===
                                                            source.key
                                                        }
                                                        onClick={() =>
                                                            void runTest(source)
                                                        }
                                                    >
                                                        {testing ===
                                                        source.key ? (
                                                            <Spinner className="size-3.5" />
                                                        ) : (
                                                            <span className="material-symbols-outlined text-[16px]">
                                                                wifi_tethering
                                                            </span>
                                                        )}
                                                        Probar
                                                    </Button>
                                                    {result &&
                                                        (result.ok ? (
                                                            <span className="text-xs text-primary">
                                                                Conexión OK
                                                            </span>
                                                        ) : (
                                                            <span className="max-w-[220px] text-xs text-destructive">
                                                                {result.message}
                                                            </span>
                                                        ))}
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-xl border border-border bg-card p-4">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h3 className="flex items-center gap-2 text-sm font-black tracking-widest text-foreground uppercase">
                                <span className="material-symbols-outlined text-[18px] text-primary">
                                    no_adult_content
                                </span>
                                Contenido para adultos
                            </h3>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Al activarlo, las búsquedas pueden devolver
                                resultados con niveles de madurez. Queda bajo tu
                                responsabilidad.
                            </p>
                        </div>
                        <Switch
                            checked={settings.maturity}
                            label="Permitir contenido para adultos"
                            onCheckedChange={toggleMaturity}
                        />
                    </div>
                </section>

                {tier3Sources.length > 0 && (
                    <section className="space-y-3 rounded-xl border border-primary/30 bg-primary/5 p-4">
                        <div>
                            <h3 className="flex items-center gap-2 text-sm font-black tracking-widest text-primary uppercase">
                                <span className="material-symbols-outlined text-[18px]">
                                    gpp_maybe
                                </span>
                                Fuentes Tier 3
                            </h3>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Estas fuentes pueden operar fuera de las APIs
                                oficiales. Aceptá los riesgos para poder
                                activarlas.
                            </p>
                        </div>

                        <div className="space-y-2">
                            {tier3Sources.map((source) => (
                                <label
                                    key={source.key}
                                    className="flex items-start gap-2 text-sm text-foreground"
                                >
                                    <Checkbox
                                        checked={acknowledged.has(source.key)}
                                        onCheckedChange={(checked) =>
                                            toggleAcknowledgement(
                                                source.key,
                                                checked === true,
                                            )
                                        }
                                        className="mt-0.5"
                                    />
                                    <span>
                                        Entiendo los riesgos de usar{' '}
                                        <span className="font-medium">
                                            {source.label}
                                        </span>
                                        .
                                    </span>
                                </label>
                            ))}
                        </div>
                    </section>
                )}

                <p className="text-xs text-muted-foreground">
                    Los cambios se guardan automáticamente. Las credenciales se
                    almacenan por usuario y no se incluyen en las respuestas de
                    esta página.
                </p>
            </div>
        </MainLayout>
    );
}
