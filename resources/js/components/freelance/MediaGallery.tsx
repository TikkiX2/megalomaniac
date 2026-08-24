import React, { useCallback, useState } from 'react';
import { useDropzone } from 'react-dropzone';
import { FileIcon, UploadCloud, X, Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';

interface MediaGalleryProps {
    files?: any[]; // Spatie Media objects
    onUpload?: (files: File[]) => void; // If handled locally or directly
    uploadUrl?: string; // If using Inertia manual post
    deleteUrl?: string; // Route pattern for deletion
    downloadUrl?: string; // Route pattern for download
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

    const onDrop = (acceptedFiles: File[]) => {
        if (onUpload) {
            onUpload(acceptedFiles);
        } else if (uploadUrl) {
            setUploading(true);
            const formData = new FormData();
            acceptedFiles.forEach(file => {
                formData.append('files[]', file);
            });

            router.post(uploadUrl, formData, {
                onFinish: () => setUploading(false),
                preserveScroll: true,
                forceFormData: true,
            });
        }
    };

    const { getRootProps, getInputProps, isDragActive } = useDropzone({ onDrop, disabled: readOnly });

    const handleDelete = (file: any) => {
        if (!deleteUrl) return;
        if (confirm('¿Estás seguro de eliminar este archivo?')) {
            router.delete(deleteUrl.replace('__ID__', file.id), {
                preserveScroll: true,
            });
        }
    };

    const handleDownload = (file: any) => {
        if (!downloadUrl) return;
        // Usually we open a new tab for download or redirect
        window.open(downloadUrl.replace('__ID__', file.id), '_blank');
    };

    return (
        <div className="space-y-4">
            {!readOnly && (
                <div
                    {...getRootProps()}
                    className={cn(
                        "group relative grid w-full cursor-pointer place-items-center rounded-lg border-2 border-dashed border-muted-foreground/25 px-5 py-10 text-center transition hover:bg-muted/25",
                        isDragActive && "border-muted-foreground/50",
                        uploading && "pointer-events-none opacity-60"
                    )}
                >
                    <input {...getInputProps()} />
                    <UploadCloud className="mb-2 h-7 w-7 text-muted-foreground" />
                    <div className="text-sm font-medium text-muted-foreground">
                        {isDragActive ? "Suelta los archivos aquí" : "Arrastra archivos aquí o haz clic para subir"}
                    </div>
                </div>
            )}

            {files.length > 0 && (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                    {files.map((file) => (
                        <div key={file.id} className="relative aspect-square rounded-md border bg-background p-2 group overflow-hidden">
                            <div className="flex h-full w-full flex-col items-center justify-center gap-2">
                                {file.mime_type?.startsWith('image/') ? (
                                    <img
                                        src={file.original_url}
                                        alt={file.file_name}
                                        className="h-full w-full object-cover rounded-sm absolute inset-0 z-0 opacity-80 group-hover:opacity-100 transition-opacity"
                                    />
                                ) : (
                                    <FileIcon className="h-8 w-8 text-blue-500 z-10" />
                                )}
                                <div className="z-10 bg-background/80 p-1 rounded w-full text-center truncate text-xs font-medium">
                                    {file.file_name}
                                </div>
                            </div>

                            <div className="absolute top-1 right-1 flex gap-1 z-20 opacity-0 group-hover:opacity-100 transition-opacity">
                                <Button
                                    variant="secondary"
                                    size="icon"
                                    className="h-6 w-6"
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
