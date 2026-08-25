import React, { useCallback, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { FileIcon, UploadCloud, X, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';

interface MediaGalleryProps {
    files?: any[]; // Spatie Media objects
    onUpload?: (files: File[]) => void; // If handled locally or directly
    uploadUrl?: string; // If using Inertia manual post — Wayfinder: freelance.projects.media.upload(project.id).url
    deleteUrl?: string; // Route pattern for deletion — Wayfinder: freelance.media.delete('__ID__').url
    downloadUrl?: string; // Route pattern for download — Wayfinder: freelance.media.download('__ID__').url
    readOnly?: boolean;
}

export default function MediaGallery({
    files = [],
    onUpload,
    uploadUrl,
    deleteUrl,
    downloadUrl,
    readOnly = false
}: MediaGalleryProps) {
    const [uploading, setUploading] = useState(false);

    const onDrop = useCallback(
        (acceptedFiles: File[]) => {
            if (!acceptedFiles.length) return;

            if (onUpload) {
                onUpload(acceptedFiles);
                return;
            }

            if (!uploadUrl) return;

            setUploading(true);

            // Backend ProjectController@uploadFile espera 'file' singular con addMediaFromRequest('file')
            // No 'files[]' plural. Para múltiples archivos, enviamos 1 request por archivo con key 'file' singular.
            // Esto mantiene consistencia con la validación 'file' => required|file|max:10240 y Wayfinder freelance.projects.media.upload
            let remaining = acceptedFiles.length;

            const onFinishOne = () => {
                remaining -= 1;
                if (remaining <= 0) setUploading(false);
            };

            acceptedFiles.forEach((file) => {
                const formData = new FormData();
                formData.append('file', file);

                router.post(uploadUrl, formData, {
                    preserveScroll: true,
                    forceFormData: true,
                    onFinish: onFinishOne,
                });
            });
        },
        [onUpload, uploadUrl],
    );

    const { getRootProps, getInputProps, isDragActive } = useDropzone({ onDrop, disabled: readOnly });

    const handleDelete = (file: any) => {
        if (!deleteUrl) return;
        if (confirm('¿Estás seguro de eliminar este archivo?')) {
            router.delete(deleteUrl.replace('__ID__', String(file.id)), {
                preserveScroll: true,
            });
        }
    };

    const handleDownload = (file: any) => {
        if (!downloadUrl) return;
        window.open(downloadUrl.replace('__ID__', String(file.id)), '_blank');
    };

    return (
        <div className="space-y-4">
            {!readOnly && (
                <div
                    {...getRootProps()}
                    className={cn(
                        "group relative grid w-full cursor-pointer place-items-center rounded-lg border-2 border-dashed border-[#3E2121] bg-[#1C0F0F] px-5 py-10 text-center transition hover:bg-[#2B1A1A]/50 hover:border-primary/30",
                        isDragActive && "border-primary/50 bg-[#2B1A1A]",
                        uploading && "pointer-events-none opacity-60"
                    )}
                >
                    <input {...getInputProps()} />
                    <UploadCloud className="mb-2 h-7 w-7 text-[#E8B4B4]" />
                    <div className="text-sm font-medium text-muted-foreground">
                        {uploading ? "Subiendo..." : isDragActive ? "Suelta los archivos aquí" : "Arrastra archivos aquí o haz clic para subir"}
                    </div>
                    <p className="text-[11px] text-muted-foreground/60 mt-1">Backend espera `file` singular (ProjectController@uploadFile) — 1 archivo por request.</p>
                </div>
            )}

            {files.length > 0 && (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    {files.map((file) => (
                        <div key={file.id} className="relative aspect-square rounded-md border border-[#3E2121] bg-[#2B1A1A] p-2 group overflow-hidden">
                            <div className="flex h-full w-full flex-col items-center justify-center gap-2">
                                {file.mime_type?.startsWith('image/') ? (
                                    <img
                                        src={file.original_url}
                                        alt={file.file_name}
                                        className="h-full w-full object-cover rounded-sm absolute inset-0 z-0 opacity-80 group-hover:opacity-100 transition-opacity"
                                    />
                                ) : (
                                    <FileIcon className="h-8 w-8 text-[#E8B4B4] z-10" />
                                )}
                                <div className="z-10 bg-[#1C0F0F]/80 p-1 rounded w-full text-center truncate text-xs font-medium text-white border border-[#3E2121]">
                                    {file.file_name}
                                </div>
                            </div>

                            <div className="absolute top-1 right-1 flex gap-1 z-20 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Button
                                    variant="secondary"
                                    size="icon"
                                    className="h-6 w-6 bg-[#1C0F0F] border border-[#3E2121] text-white hover:bg-white/10"
                                    onClick={(e) => { e.stopPropagation(); handleDownload(file); }}
                                >
                                    <Download className="h-3 w-3" />
                                </Button>
                                {!readOnly && (
                                    <Button
                                        variant="destructive"
                                        size="icon"
                                        className="h-6 w-6"
                                        onClick={(e) => { e.stopPropagation(); handleDelete(file); }}
                                    >
                                        <X className="h-3 w-3" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
