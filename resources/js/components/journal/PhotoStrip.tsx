import { router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, ImagePlus, Trash2, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

export const MAX_PHOTOS_PER_MOMENT = 8;

export interface JournalMomentPhoto {
    id: number;
    url: string;
    name: string;
}

interface PhotoStripProps {
    momentId: number;
    photos: JournalMomentPhoto[];
}

const MOBILE_TILES = 3;
const DESKTOP_TILES = 4;

export default function PhotoStrip({ momentId, photos }: PhotoStripProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const pendingFile = useRef<File | null>(null);
    const [uploading, setUploading] = useState(false);
    const [viewingIndex, setViewingIndex] = useState<number | null>(null);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const form = useForm<{ file: File | null }>({ file: null });

    const canUpload = photos.length < MAX_PHOTOS_PER_MOMENT;
    const viewingPhoto =
        viewingIndex === null ? null : (photos[viewingIndex] ?? null);
    const hiddenOnMobile = photos.length - MOBILE_TILES;
    const hiddenOnDesktop = photos.length - DESKTOP_TILES;
    const hasPrevious = viewingIndex !== null && viewingIndex > 0;
    const hasNext = viewingIndex !== null && viewingIndex < photos.length - 1;

    const openViewer = (index: number) => {
        setConfirmingDelete(false);
        setViewingIndex(index);
    };

    const closeViewer = () => {
        setConfirmingDelete(false);
        setViewingIndex(null);
    };

    const moveViewer = (offset: number) => {
        setConfirmingDelete(false);
        setViewingIndex((current) => {
            if (current === null) {
                return null;
            }

            return Math.min(Math.max(current + offset, 0), photos.length - 1);
        });
    };

    const handleFile = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;

        event.target.value = '';

        if (file === null) {
            return;
        }

        pendingFile.current = file;

        form.transform(() => ({ file: pendingFile.current }));
        form.post(`/diario/momentos/${momentId}/fotos`, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setUploading(true),
            onFinish: () => setUploading(false),
            onSuccess: () => {
                pendingFile.current = null;
                form.reset();
            },
        });
    };

    const confirmDelete = () => {
        if (viewingPhoto === null) {
            return;
        }

        router.delete(`/diario/momentos/${momentId}/fotos/${viewingPhoto.id}`, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onSuccess: () => closeViewer(),
            onFinish: () => {
                setDeleting(false);
                setConfirmingDelete(false);
            },
        });
    };

    return (
        <div className="mt-3 flex flex-col gap-2">
            <div className="grid grid-cols-3 gap-1 sm:grid-cols-4">
                {photos.slice(0, DESKTOP_TILES).map((photo, index) => {
                    const mobileOverlay =
                        index === MOBILE_TILES - 1 && hiddenOnMobile > 0
                            ? hiddenOnMobile
                            : null;
                    const desktopOverlay =
                        index === DESKTOP_TILES - 1 && hiddenOnDesktop > 0
                            ? hiddenOnDesktop
                            : null;

                    return (
                        <button
                            key={photo.id}
                            type="button"
                            onClick={() => openViewer(index)}
                            aria-label={`Ver foto ${photo.name}`}
                            className={cn(
                                'group relative aspect-square overflow-hidden rounded-md border border-border bg-card outline-none focus-visible:ring-3 focus-visible:ring-primary/40',
                                index === DESKTOP_TILES - 1 &&
                                    'hidden sm:block',
                            )}
                        >
                            <img
                                src={photo.url}
                                alt={photo.name}
                                loading="lazy"
                                className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                            />

                            {mobileOverlay !== null && (
                                <span
                                    aria-hidden="true"
                                    className="absolute inset-0 flex items-center justify-center bg-background/75 text-sm font-bold text-white/90 tabular-nums sm:hidden"
                                >
                                    +{mobileOverlay}
                                </span>
                            )}

                            {desktopOverlay !== null && (
                                <span
                                    aria-hidden="true"
                                    className="absolute inset-0 hidden items-center justify-center bg-background/75 text-sm font-bold text-white/90 tabular-nums sm:flex"
                                >
                                    +{desktopOverlay}
                                </span>
                            )}
                        </button>
                    );
                })}

                {canUpload && (
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        disabled={uploading}
                        aria-label="Agregar foto"
                        className="flex aspect-square items-center justify-center rounded-md border border-dashed border-border text-muted-foreground transition-colors outline-none hover:border-primary/40 hover:text-white/90 focus-visible:ring-3 focus-visible:ring-primary/40 disabled:opacity-50"
                    >
                        <ImagePlus aria-hidden="true" className="size-4" />
                    </button>
                )}
            </div>

            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="sr-only"
                onChange={handleFile}
            />

            {uploading && (
                <p className="text-[11px] text-muted-foreground">Subiendo…</p>
            )}

            {form.errors.file && (
                <p role="alert" className="text-[11px] text-destructive">
                    {form.errors.file}
                </p>
            )}

            <Dialog
                open={viewingPhoto !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        closeViewer();
                    }
                }}
            >
                <DialogContent className="border-border bg-card sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle className="truncate pr-8 text-sm">
                            {viewingPhoto?.name ?? 'Foto'}
                        </DialogTitle>
                        <DialogDescription className="text-[11px]">
                            Foto {(viewingIndex ?? 0) + 1} de {photos.length}
                        </DialogDescription>
                    </DialogHeader>

                    {viewingPhoto !== null && (
                        <div className="relative flex items-center justify-center overflow-hidden rounded-md bg-black/40">
                            <img
                                src={viewingPhoto.url}
                                alt={viewingPhoto.name}
                                className="max-h-[65dvh] w-full object-contain"
                            />

                            {hasPrevious && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Foto anterior"
                                    onClick={() => moveViewer(-1)}
                                    className="absolute top-1/2 left-1 size-11 -translate-y-1/2 bg-background/60 text-white/90 hover:bg-background/80"
                                >
                                    <ChevronLeft
                                        aria-hidden="true"
                                        className="size-5"
                                    />
                                </Button>
                            )}

                            {hasNext && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Foto siguiente"
                                    onClick={() => moveViewer(1)}
                                    className="absolute top-1/2 right-1 size-11 -translate-y-1/2 bg-background/60 text-white/90 hover:bg-background/80"
                                >
                                    <ChevronRight
                                        aria-hidden="true"
                                        className="size-5"
                                    />
                                </Button>
                            )}
                        </div>
                    )}

                    <DialogFooter className="gap-2">
                        {confirmingDelete ? (
                            <>
                                <span className="mr-auto text-[11px] text-muted-foreground">
                                    ¿Eliminar esta foto?
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setConfirmingDelete(false)}
                                    disabled={deleting}
                                    className="min-h-11"
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    type="button"
                                    onClick={confirmDelete}
                                    disabled={deleting}
                                    className="min-h-11 bg-destructive text-white hover:bg-destructive/90"
                                >
                                    {deleting ? 'Eliminando…' : 'Eliminar'}
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setConfirmingDelete(true)}
                                    className="mr-auto min-h-11 text-muted-foreground hover:text-destructive"
                                >
                                    <Trash2
                                        aria-hidden="true"
                                        className="size-3.5"
                                    />
                                    Eliminar
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={closeViewer}
                                    className="min-h-11"
                                >
                                    <X
                                        aria-hidden="true"
                                        className="size-3.5"
                                    />
                                    Cerrar
                                </Button>
                            </>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
