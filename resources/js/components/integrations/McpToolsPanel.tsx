import { useCallback, useEffect, useState } from 'react';
import { csrfHeaders } from '@/lib/csrf';

interface McpTool {
    name: string;
    title: string | null;
    description: string | null;
    access: 'read' | 'write' | 'destructive';
    enabled: boolean;
}

interface DiscoverResponse {
    ok: boolean;
    authorization_required?: boolean;
    connect_url?: string;
    error?: string;
    tools?: McpTool[];
    resources?: { uri: string; name: string; mime_type: string | null }[];
    prompts?: { name: string; description: string | null }[];
}

const accessStyles: Record<McpTool['access'], string> = {
    read: 'border-border text-muted-foreground',
    write: 'border-amber-500/40 text-amber-500',
    destructive: 'border-destructive/40 text-destructive',
};

export default function McpToolsPanel({ connectionId }: { connectionId: number }) {
    const [data, setData] = useState<DiscoverResponse | null>(null);
    const [loading, setLoading] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);

        try {
            const response = await fetch(`/settings/connections/${connectionId}/discover`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            setData(await response.json());
        } catch {
            setData({ ok: false, error: 'No se pudo descubrir el servidor MCP.' });
        } finally {
            setLoading(false);
        }
    }, [connectionId]);

    useEffect(() => {
        void load();
    }, [load]);

    const toggle = async (name: string) => {
        if (!data?.tools) {
            return;
        }

        const enabled = data.tools
            .filter((tool) => (tool.name === name ? !tool.enabled : tool.enabled))
            .map((tool) => tool.name);

        setData({
            ...data,
            tools: data.tools.map((tool) => (tool.name === name ? { ...tool, enabled: !tool.enabled } : tool)),
        });

        await fetch(`/settings/connections/${connectionId}/tools`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify({ enabled_tools: enabled }),
        });
    };

    return (
        <div className="space-y-3 rounded-lg border border-border bg-background/50 p-3">
            <div className="flex items-center justify-between">
                <p className="text-xs font-black uppercase tracking-widest text-muted-foreground">
                    Herramientas del MCP
                </p>
                <button
                    type="button"
                    onClick={() => void load()}
                    className="text-xs text-primary hover:underline"
                    disabled={loading}
                >
                    {loading ? 'Descubriendo…' : 'Redescubrir'}
                </button>
            </div>

            {!data && loading && <p className="text-xs text-muted-foreground">Conectando con el servidor…</p>}

            {data?.authorization_required && (
                <div className="space-y-2 rounded-lg border border-primary/30 bg-primary/10 p-3 text-xs text-primary">
                    <p>Este servidor requiere autorización OAuth.</p>
                    <a href={data.connect_url} className="font-bold underline">
                        Conectar ahora
                    </a>
                </div>
            )}

            {data?.error && <p className="text-xs text-destructive">{data.error}</p>}

            {data?.ok && (data.tools?.length ?? 0) === 0 && (
                <p className="text-xs text-muted-foreground">El servidor no expone herramientas.</p>
            )}

            {data?.ok &&
                data.tools?.map((tool) => (
                    <label
                        key={tool.name}
                        className="flex items-start gap-2 rounded-md border border-border bg-card p-2 text-xs"
                    >
                        <input
                            type="checkbox"
                            checked={tool.enabled}
                            onChange={() => void toggle(tool.name)}
                            className="mt-0.5"
                        />
                        <span className="flex-1">
                            <span className="flex items-center gap-2">
                                <span className="font-bold text-foreground">{tool.title ?? tool.name}</span>
                                <span
                                    className={`rounded-full border px-1.5 text-[10px] font-bold uppercase ${accessStyles[tool.access]}`}
                                >
                                    {tool.access}
                                </span>
                            </span>
                            {tool.description && (
                                <span className="mt-0.5 block text-muted-foreground">{tool.description}</span>
                            )}
                        </span>
                    </label>
                ))}

            {data?.ok && (data.resources?.length ?? 0) > 0 && (
                <div className="space-y-1 text-xs text-muted-foreground">
                    <p className="font-bold uppercase tracking-widest">Recursos</p>
                    {data.resources?.map((resource) => (
                        <p key={resource.uri}>
                            {resource.name} · <span className="font-mono">{resource.uri}</span>
                        </p>
                    ))}
                </div>
            )}

            {data?.ok && (data.prompts?.length ?? 0) > 0 && (
                <div className="space-y-1 text-xs text-muted-foreground">
                    <p className="font-bold uppercase tracking-widest">Prompts</p>
                    {data.prompts?.map((prompt) => (
                        <p key={prompt.name}>
                            {prompt.name}
                            {prompt.description ? ` · ${prompt.description}` : ''}
                        </p>
                    ))}
                </div>
            )}
        </div>
    );
}
