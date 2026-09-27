import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { destroy, importMethod, store, toggle, update } from '@/routes/skills';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import MainLayout from '@/layouts/main-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { SharedData } from '@/types';

export interface SkillRow {
    id: string;
    key: string;
    name: string;
    description: string | null;
    instructions: string;
    enabled: boolean;
    source: string;
}

interface SkillsPageProps {
    skills: SkillRow[];
    limits: { max: number };
    flash?: { success?: string | null };
}

function SkillForm({ skill, onDone }: { skill?: SkillRow; onDone: () => void }) {
    const form = useForm({
        name: skill?.name ?? '',
        description: skill?.description ?? '',
        instructions: skill?.instructions ?? '',
        enabled: skill?.enabled ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onDone() };

        if (skill) {
            form.patch(update.url(skill.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <div className="grid gap-2">
                <Label htmlFor="skill-name">Nombre</Label>
                <Input
                    id="skill-name"
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    placeholder="Brainstorming"
                    className="bg-background"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="skill-description">Descripción (la ve el modelo para decidir cuándo usarla)</Label>
                <Input
                    id="skill-description"
                    value={form.data.description}
                    onChange={(event) => form.setData('description', event.target.value)}
                    placeholder="Explora intención y requisitos antes de implementar"
                    className="bg-background"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="skill-instructions">Instrucciones</Label>
                <Textarea
                    id="skill-instructions"
                    value={form.data.instructions}
                    onChange={(event) => form.setData('instructions', event.target.value)}
                    rows={10}
                    placeholder="Pegá acá el cuerpo de la skill (markdown)…"
                    className="bg-background font-mono text-xs"
                />
                <InputError message={form.errors.instructions} />
            </div>

            <label className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox
                    checked={form.data.enabled}
                    onCheckedChange={(checked) => form.setData('enabled', checked === true)}
                />
                Activa
            </label>

            <div className="flex items-center gap-2">
                <Button type="submit" disabled={form.processing} className="bg-primary font-bold">
                    {skill ? 'Guardar cambios' : 'Crear skill'}
                </Button>
                <Button type="button" variant="ghost" onClick={onDone} disabled={form.processing}>
                    Cancelar
                </Button>
            </div>
        </form>
    );
}

export default function SkillsSettings() {
    const { skills, limits, flash } = usePage<SharedData & SkillsPageProps>().props;

    const [creating, setCreating] = useState(false);
    const [editingId, setEditingId] = useState<string | null>(null);

    const toggleSkill = (skill: SkillRow) => {
        router.patch(toggle.url(skill.id), { enabled: !skill.enabled }, { preserveScroll: true });
    };

    const remove = (skill: SkillRow) => {
        if (!window.confirm(`¿Eliminar la skill "${skill.name}"?`)) {
            return;
        }

        router.delete(destroy.url(skill.id), { preserveScroll: true });
    };

    const importFile = (file: File | null) => {
        if (file === null) return;

        router.post(importMethod.url(), { file }, { forceFormData: true, preserveScroll: true });
    };

    return (
        <MainLayout>
            <Head title="Skills" />

            <h1 className="sr-only">Skills</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Skills"
                        description="Instrucciones reutilizables que el chat puede cargar solo, o que podés elegir en el composer."
                    />

                    {flash?.success && (
                        <div className="rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">
                            {flash.success}
                        </div>
                    )}

                    <div className="flex flex-wrap items-center gap-2">
                        <Button onClick={() => setCreating((open) => !open)} className="bg-primary font-bold">
                            Nueva skill
                        </Button>

                        <label className="inline-flex cursor-pointer items-center rounded-md border border-border bg-card px-3 py-2 text-sm text-foreground hover:bg-muted">
                            Importar SKILL.md
                            <input
                                type="file"
                                accept=".md,.markdown,.txt"
                                className="hidden"
                                onChange={(event) => {
                                    importFile(event.target.files?.[0] ?? null);
                                    event.target.value = '';
                                }}
                            />
                        </label>

                        <span className="text-xs text-muted-foreground">
                            {skills.length}/{limits.max}
                        </span>
                    </div>

                    {creating && (
                        <Card className="border-border bg-card">
                            <CardHeader>
                                <CardTitle className="text-base font-bold">Nueva skill</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SkillForm onDone={() => setCreating(false)} />
                            </CardContent>
                        </Card>
                    )}

                    {skills.length === 0 ? (
                        <Card className="border-border bg-card">
                            <CardContent className="py-8 text-center text-sm text-muted-foreground">
                                Todavía no hay skills. Creá una o importá un archivo SKILL.md.
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="space-y-3">
                            {skills.map((skill) => (
                                <Card key={skill.id} className="border-border bg-card">
                                    <CardHeader className="gap-1">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <CardTitle className="flex items-center gap-2 text-base font-bold">
                                                    {skill.name}
                                                    <Badge variant="outline" className="border-border text-[10px] text-muted-foreground">
                                                        {skill.key}
                                                    </Badge>
                                                    {skill.source === 'import' && (
                                                        <Badge variant="outline" className="border-border text-[10px] text-muted-foreground">
                                                            importada
                                                        </Badge>
                                                    )}
                                                </CardTitle>
                                                {skill.description && (
                                                    <CardDescription className="text-xs">{skill.description}</CardDescription>
                                                )}
                                            </div>

                                            <label className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                                                <Checkbox
                                                    checked={skill.enabled}
                                                    onCheckedChange={() => toggleSkill(skill)}
                                                />
                                                {skill.enabled ? 'Activa' : 'Inactiva'}
                                            </label>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        {editingId === skill.id ? (
                                            <SkillForm skill={skill} onDone={() => setEditingId(null)} />
                                        ) : (
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="border-border bg-transparent"
                                                    onClick={() => setEditingId(skill.id)}
                                                >
                                                    Editar
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => remove(skill)}
                                                >
                                                    Eliminar
                                                </Button>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </MainLayout>
    );
}
