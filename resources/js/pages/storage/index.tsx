import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Download, FolderPlus, Link2, Pencil, Trash2, Upload } from 'lucide-react';
import EmptyState from '@/components/integrations/EmptyState';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import MainLayout from '@/layouts/main-layout';
import type { SharedData } from '@/types';
import type { FlashProps } from '@/types/integrations';

interface Entry {
    path: string;
    name: string;
    type: 'dir' | 'file';
    size: number | null;
    last_modified: number | null;
}

interface StorageIndexProps {
    disks: { id: number; name: string; kind: string }[];
    selectedDisk: number;
    path: string;
    entries: Entry[];
    flash: FlashProps;
}

function humanSize(size: number | null): string {
    if (size === null) {
        return '—';
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / 1024 / 1024).toFixed(1)} MB`;
}

export default function StorageIndex() {
    const { disks, selectedDisk, path, entries, flash } = usePage<SharedData & StorageIndexProps>().props;
    const [uploading, setUploading] = useState(false);
    const [folderName, setFolderName] = useState('');

    const go = (nextPath: string, disk = selectedDisk) => {
        router.get('/storage', { disk, path: nextPath }, { preserveState: false });
    };

    const upload = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];

        if (!file || !selectedDisk) {
            return;
        }

        setUploading(true);
        router.post(
            '/storage/upload',
            { disk_id: selectedDisk, path, file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => setUploading(false),
            },
        );
    };

    const mkdir = () => {
        if (!folderName) {
            return;
        }

        router.post('/storage/mkdir', { disk_id: selectedDisk, path: `${path.replace(/\/$/, '')}/${folderName}` }, {
            preserveScroll: true,
            onSuccess: () => setFolderName(''),
        });
    };

    const rename = (entry: Entry) => {
        const target = window.prompt('Nueva ruta', entry.path);

        if (!target || target === entry.path) {
            return;
        }

        router.post('/storage/move', { disk_id: selectedDisk, from: entry.path, to: target }, { preserveScroll: true });
    };

    const remove = (entry: Entry) => {
        if (!window.confirm(`¿Eliminar "${entry.name}"?`)) {
            return;
        }

        router.delete('/storage/file', {
            data: { disk_id: selectedDisk, path: entry.path, recursive: entry.type === 'dir' },
            preserveScroll: true,
        });
    };

    const share = async (entry: Entry) => {
        const response = await fetch('/storage/share', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
            },
            body: JSON.stringify({ disk_id: selectedDisk, path: entry.path }),
        });

        const payload = await response.json();

        window.alert(payload.ok ? payload.url : payload.error ?? 'No se pudo compartir.');
    };

    const segments = path.split('/').filter(Boolean);

    return (
        <MainLayout>
            <Head title="Archivos" />

            <div className="mx-auto max-w-4xl space-y-5 px-4 py-6">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <Heading title="Archivos" description="Explorá y gestioná tus discos (local, S3, SFTP, Drive, Dropbox, WebDAV)." />
                    {disks.length > 0 && (
                        <Select value={String(selectedDisk)} onValueChange={(value) => go('/', Number(value))}>
                            <SelectTrigger className="w-56 bg-card border-border">
                                <SelectValue placeholder="Disco" />
                            </SelectTrigger>
                            <SelectContent>
                                {disks.map((disk) => (
                                    <SelectItem key={disk.id} value={String(disk.id)}>
                                        {disk.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-primary/30 bg-primary/10 p-3 text-sm text-primary">{flash.success}</div>
                )}
                {flash?.error && (
                    <div className="rounded-xl border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive">{flash.error}</div>
                )}

                {disks.length === 0 ? (
                    <EmptyState
                        title="Sin discos"
                        description="Creá una conexión de tipo storage_* en Settings → Conexiones (local, S3, SFTP, FTP, Drive, Dropbox o WebDAV)."
                        action={
                            <Button asChild className="bg-primary font-bold">
                                <a href="/settings/connections">Ir a Conexiones</a>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <button type="button" className="hover:text-primary" onClick={() => go('/')}>
                                raíz
                            </button>
                            {segments.map((segment, index) => (
                                <span key={segment + index} className="flex items-center gap-2">
                                    /
                                    <button
                                        type="button"
                                        className="hover:text-primary"
                                        onClick={() => go('/' + segments.slice(0, index + 1).join('/'))}
                                    >
                                        {segment}
                                    </button>
                                </span>
                            ))}
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <label className="inline-flex cursor-pointer items-center gap-2 rounded-md border border-border bg-card px-3 py-2 text-xs text-muted-foreground hover:bg-accent">
                                <Upload className="h-4 w-4" />
                                {uploading ? 'Subiendo…' : 'Subir archivo'}
                                <input type="file" className="hidden" onChange={upload} disabled={uploading} />
                            </label>
                            <div className="flex items-center gap-1">
                                <Input
                                    value={folderName}
                                    onChange={(e) => setFolderName(e.target.value)}
                                    placeholder="nueva carpeta"
                                    className="h-8 w-40 bg-card border-border text-xs"
                                />
                                <Button size="sm" variant="outline" onClick={mkdir}>
                                    <FolderPlus className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>

                        {entries.length === 0 ? (
                            <EmptyState title="Carpeta vacía" description="Subí un archivo o creá una carpeta para empezar." />
                        ) : (
                            <Card className="border-border bg-card">
                                <CardContent className="pt-4">
                                    <table className="w-full text-left text-sm">
                                        <thead>
                                            <tr className="border-b border-border text-[10px] uppercase tracking-widest text-muted-foreground">
                                                <th className="pb-2">Nombre</th>
                                                <th className="pb-2">Tamaño</th>
                                                <th className="pb-2 text-right">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {entries.map((entry) => (
                                                <tr key={entry.path} className="border-b border-border last:border-0">
                                                    <td className="py-2">
                                                        {entry.type === 'dir' ? (
                                                            <button
                                                                type="button"
                                                                className="font-bold text-foreground hover:text-primary"
                                                                onClick={() => go(entry.path)}
                                                            >
                                                                📁 {entry.name}
                                                            </button>
                                                        ) : (
                                                            <span className="text-foreground">{entry.name}</span>
                                                        )}
                                                    </td>
                                                    <td className="py-2 text-xs text-muted-foreground">{humanSize(entry.size)}</td>
                                                    <td className="py-2">
                                                        <div className="flex justify-end gap-1">
                                                            {entry.type === 'file' && (
                                                                <Button size="sm" variant="ghost" asChild aria-label="Descargar">
                                                                    <a href={`/storage/download?disk_id=${selectedDisk}&path=${encodeURIComponent(entry.path)}`}>
                                                                        <Download className="h-4 w-4" />
                                                                    </a>
                                                                </Button>
                                                            )}
                                                            <Button size="sm" variant="ghost" onClick={() => share(entry)} aria-label="Compartir">
                                                                <Link2 className="h-4 w-4" />
                                                            </Button>
                                                            <Button size="sm" variant="ghost" onClick={() => rename(entry)} aria-label="Renombrar">
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                className="text-destructive"
                                                                onClick={() => remove(entry)}
                                                                aria-label="Eliminar"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}
            </div>
        </MainLayout>
    );
}
