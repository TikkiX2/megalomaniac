import { Head } from '@inertiajs/react';
import { AlertCircle, Download, FileText, Image as ImageIcon, Loader2, Trash2, UploadCloud, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { attachmentStatusLabel, formatBytes } from '@/components/ai/chat/AttachmentChips';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useAttachmentUpload } from '@/hooks/use-attachment-upload';
import MainLayout from '@/layouts/main-layout';
import { cn } from '@/lib/utils';
import type { AiChatState, ChatAttachment } from '@/types/chat';

interface SourcesPageProps {
    items: ChatAttachment[];
    ai: AiChatState;
}

const ACCEPT = {
    'image/jpeg': ['.jpg', '.jpeg'],
    'image/png': ['.png'],
    'image/webp': ['.webp'],
    'text/plain': ['.txt'],
    'text/markdown': ['.md'],
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'],
    'application/pdf': ['.pdf'],
    'application/json': ['.json'],
};

const INPUT_ACCEPT = '.jpg,.jpeg,.png,.webp,.txt,.md,.docx,.pdf,.json';

const STATUS_TONES: Record<ChatAttachment['status'], string> = {
    ready: 'border-primary/30 bg-primary/10 text-primary',
    pending: 'border-border bg-muted text-muted-foreground',
    indexed: 'border-primary/30 bg-primary/10 text-primary',
    failed: 'border-destructive/40 bg-destructive/10 text-destructive',
};

function statusBadgeLabel(item: ChatAttachment): string {
    return item.status === 'failed' ? 'Error' : attachmentStatusLabel(item);
}

function sortTimestamp(item: ChatAttachment): number {
    if (item.created_at === null || item.created_at === undefined) {
        return Date.now();
    }

    const parsed = Date.parse(item.created_at);

    return Number.isNaN(parsed) ? Date.now() : parsed;
}

function formatDate(value: string | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? '—'
        : date.toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function Sources({ items }: SourcesPageProps) {
    const upload = useAttachmentUpload(null, items);
    const [dropError, setDropError] = useState<string | null>(null);
    const [pendingDelete, setPendingDelete] = useState<ChatAttachment | null>(null);
    const [deleting, setDeleting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { getRootProps, isDragActive } = useDropzone({
        accept: ACCEPT,
        multiple: true,
        noClick: true,
        disabled: upload.uploading,
        onDrop: (files) => {
            setDropError(null);
            void upload.addFiles(files);
        },
        onDropRejected: (rejections) => {
            const names = rejections.map((rejection) => rejection.file.name).join(', ');

            setDropError(`No se admiten: ${names}. Usa imágenes JPG, PNG o WebP, .txt, .md, .docx, .pdf o .json.`);
        },
    });

    const rows = [...upload.attachments].sort((left, right) => sortTimestamp(right) - sortTimestamp(left));
    const alert = dropError ?? upload.error;

    const confirmDelete = async (): Promise<void> => {
        if (pendingDelete === null) {
            return;
        }

        setDeleting(true);
        await upload.remove(pendingDelete.id);
        setDeleting(false);
        setPendingDelete(null);
    };

    return (
        <MainLayout>
            <Head title="Fuentes" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
                <Heading
                    title="Fuentes"
                    description="Tu biblioteca de documentos e imágenes para el chat: subí archivos, adjuntá documentos a los hilos y reutilizá todo cuando quieras."
                />

                <div
                    {...getRootProps({
                        className: cn(
                            'rounded-2xl border border-dashed px-4 py-6 text-center transition-colors',
                            isDragActive
                                ? 'border-primary/60 bg-primary/5'
                                : 'border-border bg-card/40 hover:border-primary/40',
                        ),
                    })}
                >
                    <input
                        ref={fileInputRef}
                        type="file"
                        multiple
                        hidden
                        accept={INPUT_ACCEPT}
                        onChange={(event) => {
                            if (event.target.files !== null && event.target.files.length > 0) {
                                setDropError(null);
                                void upload.addFiles(Array.from(event.target.files));
                            }

                            event.target.value = '';
                        }}
                    />

                    <div className="flex flex-col items-center gap-2">
                        {upload.uploading ? (
                            <Loader2 className="h-6 w-6 animate-spin text-primary" />
                        ) : (
                            <UploadCloud className={cn('h-6 w-6', isDragActive ? 'text-primary' : 'text-muted-foreground')} />
                        )}

                        <p className="text-sm text-foreground">
                            {upload.uploading ? 'Subiendo archivos…' : 'Arrastrá tus archivos acá'}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Imágenes JPG, PNG o WebP hasta 10 MB · .txt, .md, .docx, .pdf o .json hasta 25 MB
                        </p>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => fileInputRef.current?.click()}
                            disabled={upload.uploading}
                            className="border-border"
                        >
                            Elegir archivos
                        </Button>
                    </div>
                </div>

                {alert !== null && alert !== '' && (
                    <div
                        role="alert"
                        className="flex items-start justify-between gap-3 rounded-xl border border-destructive/40 bg-destructive/10 px-3 py-2"
                    >
                        <p className="flex items-center gap-2 text-xs text-destructive">
                            <AlertCircle className="h-3.5 w-3.5 shrink-0" />
                            {alert}
                        </p>
                        <button
                            type="button"
                            onClick={() => {
                                setDropError(null);
                                upload.dismissError();
                            }}
                            aria-label="Descartar aviso"
                            className="shrink-0 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-3.5 w-3.5" />
                        </button>
                    </div>
                )}

                {rows.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-xl border border-border bg-card px-4 py-12 text-center">
                        <FileText className="h-8 w-8 text-muted-foreground" />
                        <p className="text-sm font-medium text-foreground">Todavía no tenés fuentes</p>
                        <p className="max-w-md text-xs text-muted-foreground">
                            Subí una imagen o un documento (.txt, .md, .docx, .pdf o .json) para guardarlo en tu biblioteca y reutilizarlo
                            en el chat.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow className="border-border hover:bg-transparent">
                                    <TableHead>Nombre</TableHead>
                                    <TableHead>Tamaño</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead>Hilos</TableHead>
                                    <TableHead>Agregado</TableHead>
                                    <TableHead className="text-right">Acciones</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((item) => {
                                    const failed = item.status === 'failed';
                                    const pending = item.status === 'pending';
                                    const openable = !failed && item.url !== '';
                                    const isImage = item.is_image || item.kind === 'image';
                                    const threads = item.threads ?? [];
                                    const threadsCount = item.threads_count ?? 0;

                                    return (
                                        <TableRow key={item.id} className="border-border">
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {pending ? (
                                                        <Loader2 className="h-4 w-4 shrink-0 animate-spin text-muted-foreground" />
                                                    ) : isImage && openable ? (
                                                        <img
                                                            src={item.url}
                                                            alt=""
                                                            loading="lazy"
                                                            className="h-8 w-8 shrink-0 rounded border border-border object-cover"
                                                        />
                                                    ) : isImage ? (
                                                        <ImageIcon
                                                            className={cn(
                                                                'h-4 w-4 shrink-0',
                                                                failed ? 'text-destructive' : 'text-muted-foreground',
                                                            )}
                                                        />
                                                    ) : (
                                                        <FileText
                                                            className={cn(
                                                                'h-4 w-4 shrink-0',
                                                                failed ? 'text-destructive' : 'text-muted-foreground',
                                                            )}
                                                        />
                                                    )}
                                                    <span className="max-w-64 truncate text-xs text-foreground" title={item.name}>
                                                        {item.name}
                                                    </span>
                                                </div>
                                            </TableCell>

                                            <TableCell className="text-xs text-muted-foreground tabular-nums">
                                                {formatBytes(item.size)}
                                            </TableCell>

                                            <TableCell>
                                                <span
                                                    title={attachmentStatusLabel(item)}
                                                    className={cn(
                                                        'inline-flex rounded-full border px-1.5 py-px text-[9px] font-medium tracking-wide uppercase',
                                                        STATUS_TONES[item.status],
                                                    )}
                                                >
                                                    {statusBadgeLabel(item)}
                                                </span>
                                                {failed && item.error !== null && item.error !== '' && (
                                                    <span
                                                        className="mt-0.5 block max-w-40 truncate text-[10px] text-destructive"
                                                        title={item.error}
                                                    >
                                                        {item.error}
                                                    </span>
                                                )}
                                            </TableCell>

                                            <TableCell className="text-xs">
                                                {isImage ? (
                                                    <span className="text-muted-foreground">—</span>
                                                ) : threadsCount > 0 ? (
                                                    <div title={threads.join('\n')}>
                                                        <span className="text-foreground">
                                                            {threadsCount} {threadsCount === 1 ? 'hilo' : 'hilos'}
                                                        </span>
                                                        {threads.length > 0 && (
                                                            <span className="mt-0.5 block max-w-40 truncate text-[10px] text-muted-foreground">
                                                                {threads.slice(0, 2).join(', ')}
                                                                {threads.length > 2 ? '…' : ''}
                                                            </span>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">Sin adjuntar</span>
                                                )}
                                            </TableCell>

                                            <TableCell className="text-xs text-muted-foreground">
                                                {formatDate(item.created_at)}
                                            </TableCell>

                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    {openable ? (
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-7 w-7 text-muted-foreground hover:text-foreground"
                                                        >
                                                            <a
                                                                href={item.url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                aria-label={`Abrir ${item.name}`}
                                                                title="Abrir"
                                                            >
                                                                <Download className="h-3.5 w-3.5" />
                                                            </a>
                                                        </Button>
                                                    ) : (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            disabled
                                                            aria-label={`Abrir ${item.name}`}
                                                            className="h-7 w-7 text-muted-foreground"
                                                        >
                                                            <Download className="h-3.5 w-3.5" />
                                                        </Button>
                                                    )}

                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => setPendingDelete(item)}
                                                        aria-label={`Eliminar ${item.name}`}
                                                        title="Eliminar"
                                                        className="h-7 w-7 text-muted-foreground hover:text-destructive"
                                                    >
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            <Dialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setPendingDelete(null);
                    }
                }}
            >
                <DialogContent className="border-border bg-card sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Eliminar fuente</DialogTitle>
                        <DialogDescription>
                            {pendingDelete?.is_image || pendingDelete?.kind === 'image' ? (
                                <>Se eliminará “{pendingDelete?.name}” de tu biblioteca. No se puede deshacer.</>
                            ) : (
                                <>
                                    Se eliminará “{pendingDelete?.name}” de tu biblioteca
                                    {(pendingDelete?.threads_count ?? 0) > 0
                                        ? ` y de los ${pendingDelete?.threads_count} hilos donde está adjunta`
                                        : ''}
                                    . Los hilos dejarán de usar este documento como contexto. No se puede deshacer.
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter className="gap-2">
                        <Button variant="ghost" onClick={() => setPendingDelete(null)} disabled={deleting}>
                            Cancelar
                        </Button>
                        <Button
                            onClick={() => {
                                void confirmDelete();
                            }}
                            disabled={deleting}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {deleting ? 'Eliminando…' : 'Eliminar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
