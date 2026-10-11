import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { ActiveFilters, FilterOption } from '@/types/today';

/** Radix Select no acepta `value=""`; 'todos' es el sentinel de "sin filtro". */
const ALL = 'todos';

const PRIORITIES: { value: string; label: string }[] = [
    { value: 'Low', label: 'Baja' },
    { value: 'Normal', label: 'Normal' },
    { value: 'High', label: 'Alta' },
    { value: 'Urgent', label: 'Urgente' },
];

const STATUSES: { value: string; label: string }[] = [
    { value: 'Pending', label: 'Pendiente' },
    { value: 'In Progress', label: 'En progreso' },
];

const TYPES: { value: string; label: string }[] = [
    { value: 'personal', label: 'Personal' },
    { value: 'freelance', label: 'Freelance' },
];

export interface TaskFilterBarProps {
    filters: ActiveFilters;
    projects: FilterOption[];
    tags: string[];
    onChange: (next: ActiveFilters) => void;
    onClear: () => void;
}

export default function TaskFilterBar({ filters, projects, tags, onChange, onClear }: TaskFilterBarProps) {
    const hasActiveFilters =
        filters.project_id !== undefined || !!filters.priority || !!filters.status || !!filters.tag || !!filters.type;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Select
                value={filters.project_id !== undefined ? String(filters.project_id) : ALL}
                onValueChange={v => onChange({ ...filters, project_id: v === ALL ? undefined : Number(v) })}
            >
                <SelectTrigger className="h-9 w-44 border-border bg-card" aria-label="Filtrar por proyecto">
                    <SelectValue placeholder="Proyecto" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Todos</SelectItem>
                    {projects.map(project => (
                        <SelectItem key={project.id} value={String(project.id)}>
                            {project.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={filters.priority ?? ALL}
                onValueChange={v => onChange({ ...filters, priority: v === ALL ? undefined : v })}
            >
                <SelectTrigger className="h-9 w-36 border-border bg-card" aria-label="Filtrar por prioridad">
                    <SelectValue placeholder="Prioridad" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Todos</SelectItem>
                    {PRIORITIES.map(priority => (
                        <SelectItem key={priority.value} value={priority.value}>
                            {priority.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select
                value={filters.status ?? ALL}
                onValueChange={v => onChange({ ...filters, status: v === ALL ? undefined : v })}
            >
                <SelectTrigger className="h-9 w-36 border-border bg-card" aria-label="Filtrar por estado">
                    <SelectValue placeholder="Estado" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Todos</SelectItem>
                    {STATUSES.map(status => (
                        <SelectItem key={status.value} value={status.value}>
                            {status.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={filters.tag ?? ALL} onValueChange={v => onChange({ ...filters, tag: v === ALL ? undefined : v })}>
                <SelectTrigger className="h-9 w-36 border-border bg-card" aria-label="Filtrar por etiqueta">
                    <SelectValue placeholder="Etiqueta" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Todos</SelectItem>
                    {tags.map(tag => (
                        <SelectItem key={tag} value={tag}>
                            {tag}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Select value={filters.type ?? ALL} onValueChange={v => onChange({ ...filters, type: v === ALL ? undefined : v })}>
                <SelectTrigger className="h-9 w-36 border-border bg-card" aria-label="Filtrar por tipo">
                    <SelectValue placeholder="Tipo" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Todos</SelectItem>
                    {TYPES.map(type => (
                        <SelectItem key={type.value} value={type.value}>
                            {type.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {hasActiveFilters && (
                <Button variant="outline" size="sm" onClick={onClear}>
                    <X className="mr-1 h-3 w-3" />
                    Limpiar filtros
                </Button>
            )}
        </div>
    );
}
