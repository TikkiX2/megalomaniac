import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PersonalProject } from '@/types/personal';

interface Props {
    filters: Record<string, any>;
    projects: PersonalProject[];
}

export default function TaskFilters({ filters, projects }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    useEffect(() => {
        const t = setTimeout(() => {
            if (search !== (filters.search || '')) {
                router.get('/personal/tasks', { ...filters, search: search || undefined }, { preserveState: true, replace: true });
            }
        }, 300);
        return () => clearTimeout(t);
    }, [search]);

    const update = (key: string, value: string | undefined) => {
        const next = { ...filters, [key]: value || undefined };
        // clean empty
        Object.keys(next).forEach(k => { if (next[k] === '' || next[k] == null) delete next[k]; });
        router.get('/personal/tasks', next, { preserveState: true, replace: true });
    };

    const clearAll = () => router.get('/personal/tasks', {}, { preserveState: true });

    const hasFilters = !!(filters.status || filters.priority || filters.project_id || filters.search);

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap gap-2 items-center">
                <div className="relative flex-1 min-w-48 max-w-sm">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input placeholder="Buscar tareas..." value={search} onChange={e => setSearch(e.target.value)} className="pl-8 bg-card border-border h-9" />
                </div>

                <Select value={filters.status || 'all'} onValueChange={v => update('status', v === 'all' ? undefined : v)}>
                    <SelectTrigger className="w-36 bg-card border-border h-9"><SelectValue placeholder="Estado" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos</SelectItem>
                        <SelectItem value="Pending">Pendiente</SelectItem>
                        <SelectItem value="In Progress">En Progreso</SelectItem>
                        <SelectItem value="Done">Hecho</SelectItem>
                    </SelectContent>
                </Select>

                <Select value={filters.priority || 'all'} onValueChange={v => update('priority', v === 'all' ? undefined : v)}>
                    <SelectTrigger className="w-32 bg-card border-border h-9"><SelectValue placeholder="Prioridad" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas</SelectItem>
                        <SelectItem value="Low">Baja</SelectItem>
                        <SelectItem value="Normal">Normal</SelectItem>
                        <SelectItem value="High">Alta</SelectItem>
                        <SelectItem value="Urgent">Urgente</SelectItem>
                    </SelectContent>
                </Select>

                <Select value={filters.project_id ? String(filters.project_id) : 'all'} onValueChange={v => update('project_id', v === 'all' ? undefined : v)}>
                    <SelectTrigger className="w-40 bg-card border-border h-9"><SelectValue placeholder="Proyecto" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todos proyectos</SelectItem>
                        {projects.map(p => <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>)}
                    </SelectContent>
                </Select>

                {hasFilters && <Button variant="ghost" size="sm" onClick={clearAll}><X className="mr-1 h-3 w-3" />Limpiar</Button>}
            </div>

            {hasFilters && (
                <div className="flex flex-wrap gap-1">
                    {filters.search && <Badge variant="secondary" className="gap-1">Buscar: {filters.search} <button onClick={() => update('search', undefined)}><X className="h-3 w-3" /></button></Badge>}
                    {filters.status && <Badge variant="secondary" className="gap-1">Estado: {filters.status} <button onClick={() => update('status', undefined)}><X className="h-3 w-3" /></button></Badge>}
                    {filters.priority && <Badge variant="secondary" className="gap-1">Prioridad: {filters.priority} <button onClick={() => update('priority', undefined)}><X className="h-3 w-3" /></button></Badge>}
                </div>
            )}
        </div>
    );
}
