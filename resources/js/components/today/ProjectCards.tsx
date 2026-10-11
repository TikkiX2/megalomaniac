import { ChevronDown } from 'lucide-react';
import { Fragment, useState } from 'react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import type { ProjectBrief } from '@/types/today';

/** Synthetic group key for items without project (real ids are always positive). */
const NO_PROJECT_KEY = -1;
const NO_PROJECT_NAME = 'Sin proyecto';

export interface ProjectCardsProps<T extends { id: number; project: ProjectBrief | null }> {
    items: T[];
    renderRow: (item: T) => ReactNode;
    showCount?: boolean;
    collapsible?: boolean;
    order?: 'count' | 'appearance';
}

interface ProjectGroup<T> {
    key: number;
    name: string;
    color: string | null;
    items: T[];
}

export default function ProjectCards<T extends { id: number; project: ProjectBrief | null }>({
    items,
    renderRow,
    showCount = false,
    collapsible = false,
    order = 'count',
}: ProjectCardsProps<T>) {
    const [collapsedIds, setCollapsedIds] = useState<Set<number>>(new Set());

    const toggleGroup = (key: number) => {
        setCollapsedIds(prev => {
            const next = new Set(prev);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }
            return next;
        });
    };

    const groups: ProjectGroup<T>[] = [];
    const groupByKey = new Map<number, ProjectGroup<T>>();

    items.forEach(item => {
        const key = item.project ? item.project.id : NO_PROJECT_KEY;
        let group = groupByKey.get(key);
        if (!group) {
            group = {
                key,
                name: item.project ? item.project.name : NO_PROJECT_NAME,
                color: item.project ? item.project.color : null,
                items: [],
            };
            groupByKey.set(key, group);
            groups.push(group);
        }
        group.items.push(item);
    });

    // 'count': cantidad desc, luego nombre asc ("Sin proyecto" ordena alfabéticamente).
    // 'appearance': se respeta el orden de primera aparición.
    if (order === 'count') {
        groups.sort((a, b) => b.items.length - a.items.length || a.name.localeCompare(b.name));
    }

    if (groups.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-6">
            {groups.map(group => {
                const isCollapsed = collapsedIds.has(group.key);

                const header = (
                    <Fragment>
                        <span
                            className="h-2 w-2 shrink-0 rounded-full bg-muted-foreground"
                            style={group.color ? { backgroundColor: group.color } : undefined}
                        />
                        <span className="truncate">{group.name}</span>
                        {showCount && (
                            <Badge variant="secondary" className="text-[10px]">
                                {group.items.length}
                            </Badge>
                        )}
                        {collapsible && (
                            <ChevronDown
                                className={`h-3.5 w-3.5 shrink-0 transition-transform ${isCollapsed ? '-rotate-90' : ''}`}
                            />
                        )}
                    </Fragment>
                );

                return (
                    <div key={group.key} className="flex flex-col gap-2">
                        {collapsible ? (
                            <button
                                type="button"
                                onClick={() => toggleGroup(group.key)}
                                aria-expanded={!isCollapsed}
                                className="flex w-fit items-center gap-2 text-xs font-black uppercase tracking-widest text-muted-foreground transition-colors hover:text-foreground"
                            >
                                {header}
                            </button>
                        ) : (
                            <h3 className="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-muted-foreground">
                                {header}
                            </h3>
                        )}
                        {!isCollapsed && (
                            <div className="flex flex-col divide-y divide-border overflow-hidden rounded-xl border border-border bg-card">
                                {group.items.map(item => (
                                    <Fragment key={item.id}>{renderRow(item)}</Fragment>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
