import { router, usePage } from '@inertiajs/react';
import { Send, Trash, CornerDownRight } from 'lucide-react';
import React, { useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { destroy } from '@/routes/freelance/comments';
import { store } from '@/routes/freelance/projects/comments';
import RichTextEditor from './YooptaEditor';
// Wayfinder — rutas tipadas generadas por vite-plugin-wayfinder (no Ziggy route() global)

interface Comment {
    id: number;
    content: any; // Yoopta JSON
    user: {
        id: number;
        name: string;
        profile_photo_url?: string;
    };
    created_at: string;
    replies?: Comment[];
}

interface CommentSectionProps {
    project: any;
    comments: Comment[];
}

function isYooptaEmpty(value: any): boolean {
    if (value == null) return true;
    // string empty
    if (typeof value === 'string') return value.trim().length === 0;
    // array empty
    if (Array.isArray(value) && value.length === 0) return true;

    try {
        const json = JSON.stringify(value);
        if (!json || json === '[]' || json === '{}' || json === 'null') return true;
        // Yoopta vacío típico: paragraph con texto vacío
        // Detecta si todos los bloques tienen texto vacío
        const parsed = typeof value === 'object' ? value : JSON.parse(json);
        const blocks: any[] = Array.isArray(parsed)
            ? parsed
            : Array.isArray((parsed as any)?.blocks)
              ? (parsed as any).blocks
              : Object.values(parsed as object);

        if (blocks.length === 0) return true;

        const hasText = blocks.some((b: any) => {
            const text = JSON.stringify(b);
            // busca propiedad text con contenido no vacío
            const extract = (node: any): string => {
                if (typeof node === 'string') return node;
                if (node?.text) return node.text;
                if (node?.children) return node.children.map(extract).join('');
                if (node?.value) return extract(node.value);
                if (Array.isArray(node)) return node.map(extract).join('');
                return '';
            };
            return extract(b).trim().length > 0;
        });
        return !hasText;
    } catch {
        return !value;
    }
}

export default function CommentSection({ project, comments }: CommentSectionProps) {
    const { auth } = usePage().props as any;
    const [newComment, setNewComment] = useState<any>(null);
    const [replyTo, setReplyTo] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);

    const isEmpty = isYooptaEmpty(newComment);

    const handleSubmit = () => {
        if (isEmpty) return;
        setProcessing(true);
        // Wayfinder: POST /freelance/projects/{project}/comments
        // store.url(project.id) — usa helper tipado, no route() global
        router.post(
            store.url(project.id),
            {
                content: newComment,
                parent_id: replyTo,
            },
            {
                onFinish: () => {
                    setProcessing(false);
                    setNewComment(null);
                    setReplyTo(null);
                },
                preserveScroll: true,
            },
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar comentario?')) {
            // Wayfinder destroy: DELETE /freelance/comments/{comment}
            router.delete(destroy.url(id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="space-y-6">
            <Card className="bg-[#2B1A1A] border-[#3E2121] text-white">
                <CardHeader>
                    <CardTitle className="text-lg text-white">Comentarios ({comments.length})</CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Add Comment */}
                    <div className="flex gap-4">
                        <Avatar className="h-8 w-8">
                            <AvatarFallback className="bg-[#1C0F0F] text-[#E8B4B4] border border-[#3E2121]">
                                {auth.user.name[0]}
                            </AvatarFallback>
                        </Avatar>
                        <div className="flex-1 space-y-2">
                            <div className="border border-[#3E2121] rounded-md focus-within:ring-1 focus-within:ring-primary transition-all bg-[#1C0F0F] overflow-hidden">
                                <RichTextEditor
                                    value={newComment}
                                    onChange={setNewComment}
                                    className="border-0 shadow-none min-h-[100px] bg-transparent"
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                {replyTo && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setReplyTo(null)}
                                        className="text-[#E8B4B4] hover:bg-white/5 hover:text-white"
                                    >
                                        Cancelar Respuesta
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    onClick={handleSubmit}
                                    disabled={processing || isEmpty}
                                    className="bg-primary text-primary-foreground hover:bg-primary/90 font-bold shadow-[0_0_15px_rgba(239,68,68,0.25)] disabled:opacity-50"
                                >
                                    <Send className="h-4 w-4 mr-2" />
                                    {processing ? 'Enviando…' : replyTo ? 'Responder' : 'Comentar'}
                                </Button>
                            </div>
                            {isEmpty && newComment && (
                                <p className="text-xs text-muted-foreground">El comentario está vacío.</p>
                            )}
                        </div>
                    </div>

                    <Separator className="my-6 bg-[#3E2121]" />

                    {/* List Comments */}
                    <div className="space-y-8">
                        {comments.length === 0 ? (
                            <p className="text-sm text-muted-foreground text-center py-4">No hay comentarios aún.</p>
                        ) : (
                            comments.map((comment) => (
                                <CommentItem
                                    key={comment.id}
                                    comment={comment}
                                    onReply={(id) => setReplyTo(id)}
                                    onDelete={handleDelete}
                                    currentUserId={auth.user.id}
                                />
                            ))
                        )}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function CommentItem({
    comment,
    onReply,
    onDelete,
    currentUserId,
    isReply = false,
}: {
    comment: Comment;
    onReply: (id: number) => void;
    onDelete: (id: number) => void;
    currentUserId: number;
    isReply?: boolean;
}) {
    return (
        <div className={`flex gap-4 ${isReply ? 'ml-12' : ''}`}>
            <Avatar className="h-8 w-8">
                <AvatarImage src={comment.user.profile_photo_url} />
                <AvatarFallback className="bg-[#1C0F0F] text-[#E8B4B4] border border-[#3E2121]">{comment.user.name[0]}</AvatarFallback>
            </Avatar>
            <div className="flex-1 space-y-2">
                <div className="flex items-center justify-between">
                    <div>
                        <span className="text-sm font-semibold text-white">{comment.user.name}</span>
                        <span className="text-xs text-muted-foreground ml-2">
                            {new Date(comment.created_at).toLocaleString()}
                        </span>
                    </div>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="h-6 w-6 text-muted-foreground hover:text-white hover:bg-white/5"
                            onClick={() => onReply(comment.id)}
                        >
                            <CornerDownRight className="h-3 w-3" />
                        </Button>
                        {comment.user.id === currentUserId && (
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-6 w-6 text-destructive hover:text-destructive hover:bg-destructive/10"
                                onClick={() => onDelete(comment.id)}
                            >
                                <Trash className="h-3 w-3" />
                            </Button>
                        )}
                    </div>
                </div>

                <div className="text-sm text-[#E8B4B4]">
                    <RichTextEditor value={comment.content} readOnly className="border-0 p-0 shadow-none bg-transparent" />
                </div>

                {comment.replies && comment.replies.length > 0 && (
                    <div className="mt-4 space-y-4">
                        {comment.replies.map((reply) => (
                            <CommentItem
                                key={reply.id}
                                comment={reply}
                                onReply={onReply}
                                onDelete={onDelete}
                                currentUserId={currentUserId}
                                isReply={true}
                            />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
