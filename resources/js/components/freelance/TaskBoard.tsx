import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Plus, MoreHorizontal, Calendar, User, Tag, Clock } from 'lucide-react';
import { router } from '@inertiajs/react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Task {
    id: number;
    title: string;
    status: string;
    priority: string;
    due_date?: string;
    responsible?: string;
    tags?: string[];
}

interface TaskBoardProps {
    project: any;
    tasks: Task[];
}

export default function TaskBoard({ project, tasks }: TaskBoardProps) {
    const columns = ['To Do', 'In Progress', 'Done', 'Review'];

    const getTasksByStatus = (status: string) => tasks.filter(t => t.status === status);

    const handleStatusChange = (task: Task, newStatus: string) => {
        router.patch(route('freelance.tasks.update', task.id), {
            status: newStatus
        }, {
            preserveScroll: true
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar esta tarea?')) {
            router.delete(route('freelance.tasks.destroy', id), {
                preserveScroll: true
            });
        }
    };

    return (
        <div className="flex flex-col gap-6">
            <div className="flex justify-between items-center">
                <h2 className="text-xl font-bold">Tablero de Tareas</h2>
                <Button size="sm">
                    <Plus className="h-4 w-4 mr-2" /> Nueva Tarea
                </Button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 overflow-x-auto pb-4">
                {columns.map(status => (
                    <div key={status} className="flex flex-col gap-3 min-w-[250px]">
                        <div className="flex items-center justify-between px-2">
                            <h3 className="font-semibold text-sm uppercase tracking-wider text-muted-foreground">
                                {status}
                                <span className="ml-2 text-xs bg-muted px-1.5 py-0.5 rounded-full">
                                    {getTasksByStatus(status).length}
                                </span>
                            </h3>
                        </div>

                        <div className="flex flex-col gap-3 bg-muted/30 p-2 rounded-lg min-h-[500px]">
                            {getTasksByStatus(status).map(task => (
                                <TaskCard
                                    key={task.id}
                                    task={task}
                                    onStatusChange={(s) => handleStatusChange(task, s)}
                                    onDelete={() => handleDelete(task.id)}
                                    columns={columns}
                                />
                            ))}
                            {getTasksByStatus(status).length === 0 && (
                                <p className="text-xs text-muted-foreground text-center py-4 italic">Sin tareas</p>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function TaskCard({ task, onStatusChange, onDelete, columns }) {
    const priorityColors = {
        'High': 'text-red-500 bg-red-500/10 border-red-500/20',
        'Normal': 'text-blue-500 bg-blue-500/10 border-blue-500/20',
        'Low': 'text-gray-500 bg-gray-500/10 border-gray-500/20',
        'Urgent': 'text-purple-500 bg-purple-500/10 border-purple-500/20',
    };

    return (
        <Card className="shadow-sm hover:shadow-md transition-shadow cursor-grab active:cursor-grabbing group">
            <CardContent className="p-3 space-y-3">
                <div className="flex justify-between items-start gap-2">
                    <h4 className="font-medium text-sm leading-tight">{task.title}</h4>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {columns.filter(c => c !== task.status).map(col => (
                                <DropdownMenuItem key={col} onClick={() => onStatusChange(col)}>
                                    Mover a {col}
                                </DropdownMenuItem>
                            ))}
                            <DropdownMenuItem className="text-destructive" onClick={onDelete}>
                                Eliminar
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div className="flex flex-wrap gap-1.5">
                    <Badge variant="outline" className={priorityColors[task.priority] || ''}>
                        {task.priority}
                    </Badge>
                    {task.tags?.map(tag => (
                        <Badge key={tag} variant="secondary" className="text-[10px] px-1 h-4">
                            {tag}
                        </Badge>
                    ))}
                </div>

                <div className="flex items-center justify-between text-[11px] text-muted-foreground mt-2">
                    <div className="flex items-center gap-1">
                        <Calendar className="h-3 w-3" />
                        {task.due_date ? new Date(task.due_date).toLocaleDateString() : 'Sin fecha'}
                    </div>
                    {task.responsible && (
                        <div className="flex items-center gap-1">
                            <User className="h-3 w-3" />
                            {task.responsible}
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
