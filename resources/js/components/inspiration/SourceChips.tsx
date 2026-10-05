import { formatCacheAge, sourceLabel, type SourceStatus } from './shared';

interface SourceChipsProps {
    sources: Record<string, SourceStatus>;
    active: string;
    onSelect: (source: string) => void;
}

function chipClass(kind: 'active' | 'ok' | 'down' | 'disabled'): string {
    const base =
        'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-background';

    switch (kind) {
        case 'active':
            return `${base} border-primary bg-primary text-primary-foreground`;
        case 'down':
            return `${base} border-red-500/40 bg-red-500/10 text-red-300 hover:bg-red-500/20`;
        case 'disabled':
            return `${base} cursor-not-allowed border-border bg-muted/40 text-muted-foreground opacity-70`;
        default:
            return `${base} border-emerald-500/30 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20`;
    }
}

function statusKind(status: SourceStatus | undefined, isActive: boolean): 'active' | 'ok' | 'down' | 'disabled' {
    if (isActive) {
        return 'active';
    }

    if (!status || !status.enabled || !status.configured) {
        return 'disabled';
    }

    return status.down ? 'down' : 'ok';
}

/**
 * Horizontal source filter. Order follows the `sources` record (backend
 * registry order) with a fixed "Todo" chip first. Chips carry the health of
 * each source so a dead adapter is visible before the user clicks it.
 */
export default function SourceChips({ sources, active, onSelect }: SourceChipsProps) {
    return (
        <div className="flex flex-wrap gap-2">
            <button
                type="button"
                onClick={() => onSelect('all')}
                className={chipClass(active === 'all' ? 'active' : 'ok')}
                aria-pressed={active === 'all'}
            >
                Todo
            </button>

            {Object.entries(sources).map(([key, status]) => {
                const isActive = active === key;
                const kind = statusKind(status, isActive);
                const cache = status.down ? formatCacheAge(status.cache_age_minutes) : null;

                return (
                    <button
                        key={key}
                        type="button"
                        disabled={kind === 'disabled'}
                        onClick={() => onSelect(key)}
                        className={chipClass(kind)}
                        aria-pressed={isActive}
                        title={status.down ? 'Fuente caída' : undefined}
                    >
                        {!isActive && (
                            <span
                                className={`h-1.5 w-1.5 rounded-full ${
                                    kind === 'down' ? 'bg-red-400' : kind === 'disabled' ? 'bg-muted-foreground' : 'bg-emerald-400'
                                }`}
                            />
                        )}
                        {sourceLabel(key)}
                        {cache && <span className="font-normal opacity-80">· caché {cache}</span>}
                    </button>
                );
            })}
        </div>
    );
}
