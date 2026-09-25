import { Link, router } from '@inertiajs/react';
import { FolderKanban, Plus, Layers } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import type { PersonalProject } from '@/types/personal';

interface Props {
    projects: PersonalProject[];
    activeProjectId?: number | null;
    onSelect?: (id: number | null) => void;
    onCreateProject?: () => void;
}

export default function ProjectSidebar({ projects, activeProjectId, onSelect, onCreateProject }: Props) {
    const handleSelect = (id: number | null) => {
        if (onSelect) {
            onSelect(id);
        } else {
            router.get('/personal/tasks', { project_id: id ?? undefined }, { preserveState: true, replace: true });
        }
    };

    return (
        <div className="flex flex-col gap-3 w-full">
            <div className="flex items-center justify-between">
                <h3 className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Proyectos</h3>
                <Button size="icon" variant="ghost" className="h-7 w-7 text-muted-foreground hover:text-primary" onClick={onCreateProject}>
                    <Plus className="h-4 w-4" />
                </Button>
            </div>

            <button
                onClick={() => handleSelect(null)}
                className={`flex items-center gap-3 rounded-lg border p-3 text-left transition-colors ${activeProjectId == null ? 'bg-primary/10 border-primary/30' : 'bg-card border-border hover:bg-accent'}`}
            >
                <div className="flex h-8 w-8 items-center justify-center rounded-md bg-muted">
                    <Layers className="h-4 w-4" />
                </div>
                <div className="flex-1 min-w-0">
                    <p className="text-sm font-semibold truncate">Todas las tareas</p>
                    <p className="text-xs text-muted-foreground">{projects.reduce((a, p) => a + (p.tasks_count ?? 0), 0)} tareas</p>
                </div>
            </button>

            <div className="flex flex-col gap-2 max-h-[60vh] overflow-auto pr-1">
                {projects.length === 0 ? (
                    <p className="text-xs text-muted-foreground italic py-4 text-center">Sin proyectos</p>
                ) : (
                    projects.map((project) => (
                        <button
                            key={project.id}
                            onClick={() => handleSelect(project.id)}
                            className={`flex flex-col gap-2 rounded-lg border p-3 text-left transition-colors ${activeProjectId === project.id ? 'bg-primary/10 border-primary/30' : 'bg-card border-border hover:bg-accent'}`}
                        >
                            <div className="flex items-start gap-2 w-full">
                                <div
                                    className="h-8 w-8 rounded-md flex items-center justify-center text-white text-xs font-black shrink-0"
                                    style={{ backgroundColor: project.color || '#EF4444' }}
                                >
                                    {project.icon ? project.icon.slice(0, 2).toUpperCase() : project.name.slice(0, 2).toUpperCase()}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold truncate">{project.name}</p>
                                    <p className="text-xs text-muted-foreground truncate">{project.status}</p>
                                </div>
                                <Badge variant="outline" className="text-[10px] font-black shrink-0">
                                    {project.tasks_count ?? 0}
                                </Badge>
                            </div>
                            <div className="flex flex-col gap-1">
                                <div className="flex justify-between text-[10px] font-bold text-muted-foreground">
                                    <span>{project.progress}%</span>
                                    <span>{project.tasks_count ?? 0} tareas</span>
                                </div>
                                <Progress value={project.progress} className="h-1" />
                            </div>
                            {project.tags && project.tags.length > 0 && (
                                <div className="flex flex-wrap gap-1">
                                    {project.tags.slice(0, 3).map((tag) => (
                                        <Badge key={tag} variant="secondary" className="text-[9px] px-1 py-0">
                                            {tag}
                                        </Badge>
                                    ))}
                                </div>
                            )}
                        </button>
                    ))
                )}
            </div>
        </div>
    );
}
