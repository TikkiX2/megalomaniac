import { router } from '@inertiajs/react';
import { Plus, Trash } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { PersonalTask, TaskProperty } from '@/types/personal';

interface Props {
    task: PersonalTask;
}

const typeOptions = [
    { value: 'text', label: 'Texto' },
    { value: 'number', label: 'Número' },
    { value: 'date', label: 'Fecha' },
    { value: 'select', label: 'Selección' },
    { value: 'multi_select', label: 'Multi-selección' },
    { value: 'checkbox', label: 'Checkbox' },
    { value: 'url', label: 'URL' },
    { value: 'person', label: 'Persona' },
];

export default function TaskProperties({ task }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [newProp, setNewProp] = useState({ key: '', type: 'text' as TaskProperty['type'], value: '' });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(`/personal/tasks/${task.id}/properties`, {
            key: newProp.key,
            type: newProp.type,
            value_text: newProp.type === 'text' || newProp.type === 'url' || newProp.type === 'person' ? newProp.value : null,
            value_number: newProp.type === 'number' ? newProp.value : null,
            value_date: newProp.type === 'date' ? newProp.value : null,
            value_json: newProp.type === 'select' || newProp.type === 'multi_select' || newProp.type === 'checkbox' ? { value: newProp.value } : null,
        }, {
            preserveScroll: true,
            onSuccess: () => { setDialogOpen(false); setNewProp({ key: '', type: 'text', value: '' }); },
        });
    };

    const handleDelete = (prop: TaskProperty) => {
        if (confirm('¿Eliminar propiedad?')) {
            router.delete(`/personal/task-properties/${prop.id}`, { preserveScroll: true });
        }
    };

    const handleInlineUpdate = (prop: TaskProperty, value: string) => {
        const payload: any = {};
        if (prop.type === 'text' || prop.type === 'url' || prop.type === 'person') payload.value_text = value;
        else if (prop.type === 'number') payload.value_number = value;
        else if (prop.type === 'date') payload.value_date = value;
        else payload.value_json = { value };

        router.patch(`/personal/task-properties/${prop.id}`, payload, { preserveScroll: true });
    };

    return (
        <div className="flex flex-col gap-3">
            <div className="flex items-center justify-between">
                <h4 className="text-xs font-black uppercase tracking-widest text-muted-foreground">Propiedades</h4>
                <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                    <DialogTrigger asChild><Button variant="outline" size="sm" className="h-7 text-xs"><Plus className="mr-1 h-3 w-3" />Agregar</Button></DialogTrigger>
                    <DialogContent className="bg-card border-border">
                        <DialogHeader><DialogTitle>Nueva Propiedad</DialogTitle></DialogHeader>
                        <form onSubmit={handleAdd} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <Label>Nombre</Label>
                                <Input value={newProp.key} onChange={e => setNewProp({ ...newProp, key: e.target.value })} placeholder="Sprint, Story Points..." required className="bg-background border-border" />
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label>Tipo</Label>
                                <Select value={newProp.type} onValueChange={v => setNewProp({ ...newProp, type: v as TaskProperty['type'] })}>
                                    <SelectTrigger className="bg-background border-border"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {typeOptions.map(o => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label>Valor inicial (opcional)</Label>
                                <Input value={newProp.value} onChange={e => setNewProp({ ...newProp, value: e.target.value })} placeholder="Valor" className="bg-background border-border" />
                            </div>
                            <Button type="submit" className="bg-primary">Crear</Button>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            {(!task.properties || task.properties.length === 0) ? (
                <p className="text-xs text-muted-foreground italic">Sin propiedades personalizadas.</p>
            ) : (
                <div className="flex flex-col gap-2">
                    {task.properties.map(prop => (
                        <div key={prop.id} className="flex items-center gap-2 rounded-lg border border-border bg-muted/20 p-2">
                            <div className="flex-1 min-w-0">
                                <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">{prop.key} <span className="font-normal normal-case">· {prop.type}</span></p>
                                {prop.type === 'text' || prop.type === 'url' ? (
                                    <Input defaultValue={prop.value_text || ''} onBlur={e => handleInlineUpdate(prop, e.target.value)} className="h-7 text-xs mt-1 bg-background border-border" placeholder="—" />
                                ) : prop.type === 'number' ? (
                                    <Input type="number" defaultValue={prop.value_number || ''} onBlur={e => handleInlineUpdate(prop, e.target.value)} className="h-7 text-xs mt-1 bg-background border-border" />
                                ) : prop.type === 'date' ? (
                                    <Input type="date" defaultValue={prop.value_date ? String(prop.value_date).slice(0, 10) : ''} onBlur={e => handleInlineUpdate(prop, e.target.value)} className="h-7 text-xs mt-1 bg-background border-border" />
                                ) : (
                                    <Input defaultValue={typeof prop.value_json === 'object' && prop.value_json ? (prop.value_json as any).value ?? JSON.stringify(prop.value_json) : String(prop.value_json ?? '')} onBlur={e => handleInlineUpdate(prop, e.target.value)} className="h-7 text-xs mt-1 bg-background border-border" />
                                )}
                            </div>
                            <Button variant="ghost" size="icon" className="h-7 w-7 shrink-0" onClick={() => handleDelete(prop)}><Trash className="h-3 w-3 text-destructive" /></Button>
                        </div>
                    ))}
                </div>
            )}

            {/* Built-in properties preview */}
            <div className="grid grid-cols-2 gap-2 text-xs pt-2 border-t border-border">
                <div><span className="text-muted-foreground">Prioridad:</span> <Badge variant="outline" className="ml-1 text-[10px]">{task.priority ?? '—'}</Badge></div>
                <div><span className="text-muted-foreground">Estado:</span> <Badge variant="outline" className="ml-1 text-[10px]">{task.status}</Badge></div>
                <div><span className="text-muted-foreground">Vence:</span> {task.due_date ? new Date(task.due_date).toLocaleDateString() : '—'}</div>
                <div><span className="text-muted-foreground">Est. tiempo:</span> {task.estimated_time ? `${task.estimated_time}m` : '—'}</div>
            </div>
        </div>
    );
}
