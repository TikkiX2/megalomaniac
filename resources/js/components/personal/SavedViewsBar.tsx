import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { Bookmark, Trash, Plus } from 'lucide-react';
import { useState } from 'react';
import type { TaskSavedView, TaskViewType } from '@/types/personal';

interface Props {
    savedViews: TaskSavedView[];
    currentView: TaskViewType;
    currentFilters: Record<string, any>;
}

export default function SavedViewsBar({ savedViews, currentView, currentFilters }: Props) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [viewType, setViewType] = useState<TaskViewType>(currentView);

    const handleSave = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/personal/saved-views', {
            name,
            view_type: viewType,
            filters: currentFilters,
            sort: null,
            group_by: null,
        }, {
            preserveScroll: true,
            onSuccess: () => { setOpen(false); setName(''); },
        });
    };

    const handleApply = (view: TaskSavedView) => {
        const params: any = { ...(view.filters as any) };
        // view_type is stored but we handle via UI state - for now just apply filters
        router.get('/personal/tasks', params, { preserveState: true });
        // Dispatch custom event for parent to switch view
        window.dispatchEvent(new CustomEvent('saved-view-apply', { detail: view }));
    };

    const handleDelete = (view: TaskSavedView) => {
        if (confirm(`¿Eliminar vista "${view.name}"?`)) {
            router.delete(`/personal/saved-views/${view.id}`, { preserveScroll: true });
        }
    };

    return (
        <div className="flex items-center gap-2 flex-wrap">
            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                <Bookmark className="h-3 w-3" /> Vistas:
            </div>
            {savedViews.length === 0 ? (
                <span className="text-xs text-muted-foreground italic">Sin vistas guardadas</span>
            ) : (
                savedViews.map(v => (
                    <Badge key={v.id} variant="secondary" className="gap-1 pr-1 cursor-pointer hover:bg-primary hover:text-primary-foreground" onClick={() => handleApply(v)}>
                        {v.name} <span className="text-[9px] opacity-60">({v.view_type})</span>
                        <button onClick={(e) => { e.stopPropagation(); handleDelete(v); }} className="ml-1 rounded-full hover:bg-destructive/20 p-0.5">
                            <Trash className="h-3 w-3" />
                        </button>
                    </Badge>
                ))
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogTrigger asChild>
                    <Button variant="outline" size="sm" className="h-7 text-xs"><Plus className="mr-1 h-3 w-3" />Guardar vista</Button>
                </DialogTrigger>
                <DialogContent className="bg-card border-border">
                    <DialogHeader><DialogTitle>Guardar vista actual</DialogTitle></DialogHeader>
                    <form onSubmit={handleSave} className="flex flex-col gap-4">
                        <div className="flex flex-col gap-2">
                            <Label>Nombre</Label>
                            <Input value={name} onChange={e => setName(e.target.value)} placeholder="Mi vista" required className="bg-background border-border" />
                        </div>
                        <div className="flex flex-col gap-2">
                            <Label>Tipo de vista</Label>
                            <Select value={viewType} onValueChange={v => setViewType(v as TaskViewType)}>
                                <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="table">Tabla</SelectItem>
                                    <SelectItem value="kanban">Kanban</SelectItem>
                                    <SelectItem value="calendar">Calendario</SelectItem>
                                    <SelectItem value="list">Lista</SelectItem>
                                    <SelectItem value="gallery">Galería</SelectItem>
                                    <SelectItem value="timeline">Timeline</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button type="submit" className="bg-primary">Guardar</Button>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
