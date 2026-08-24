import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { router, usePage } from '@inertiajs/react';
import { Send, Trash, Edit2, CornerDownRight } from 'lucide-react';
import RichTextEditor from './YooptaEditor';

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

export default function CommentSection({ project, comments }: CommentSectionProps) {
    const { auth } = usePage().props as any;
    const [newComment, setNewComment] = useState(null);
    const [replyTo, setReplyTo] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);

    const handleSubmit = () => {
        if (!newComment) return;
        setProcessing(true);
        router.post(route('freelance.projects.comments.store', project.id), {
            content: newComment,
            parent_id: replyTo
        }, {
            onFinish: () => {
                setProcessing(false);
                setNewComment(null);
                setReplyTo(null);
            },
            preserveScroll: true
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('¿Eliminar comentario?')) {
            router.delete(route('freelance.comments.destroy', id), {
                preserveScroll: true
            });
        }
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle className="text-lg">Comentarios ({comments.length})</CardTitle>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Add Comment */}
                    <div className="flex gap-4">
                        <Avatar className="h-8 w-8">
                            <AvatarFallback>{auth.user.name[0]}</AvatarFallback>
                        </Avatar>
                        <div className="flex-1 space-y-2">
                            <div className="border rounded-md focus-within:ring-1 focus-within:ring-primary transition-all">
                                <RichTextEditor
                                    value={newComment}
                                    onChange={setNewComment}
                                    className="border-0 shadow-none min-h-[100px]"
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                {replyTo && (
                                    <Button variant="ghost" size="sm" onClick={() => setReplyTo(null)}>
                                        Cancelar Respuesta
                                    </Button>
                                )}
                                <Button size="sm" onClick={handleSubmit} disabled={processing || !newComment}>
                                    <Send className="h-4 w-4 mr-2" />
                                    {replyTo ? 'Responder' : 'Comentar'}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <Separator className="my-6" />

                    {/* List Comments */}
                    <div className="space-y-8">
                        {comments.length === 0 ? (
                            <p className="text-sm text-muted-foreground text-center py-4">No hay comentarios aún.</p>
                        ) : (
                            comments.map(comment => (
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

function CommentItem({ comment, onReply, onDelete, currentUserId, isReply = false }) {
    return (
        <div className={`flex gap-4 ${isReply ? 'ml-12' : ''}`}>
            <Avatar className="h-8 w-8">
                <AvatarImage src={comment.user.profile_photo_url} />
                <AvatarFallback>{comment.user.name[0]}</AvatarFallback>
            </Avatar>
            <div className="flex-1 space-y-2">
                <div className="flex items-center justify-between">
                    <div>
                        <span className="text-sm font-semibold">{comment.user.name}</span>
                        <span className="text-xs text-muted-foreground ml-2">
                            {new Date(comment.created_at).toLocaleString()}
                        </span>
                    </div>
                    <div className="flex items-center gap-1">
                        <Button variant="ghost" size="icon" className="h-6 w-6" onClick={() => onReply(comment.id)}>
                            <CornerDownRight className="h-3 w-3" />
                        </Button>
                        {comment.user.id === currentUserId && (
                            <Button variant="ghost" size="icon" className="h-6 w-6 text-destructive" onClick={() => onDelete(comment.id)}>
                                <Trash className="h-3 w-3" />
                            </Button>
                        )}
                    </div>
                </div>

                <div className="text-sm">
                    <RichTextEditor value={comment.content} readOnly className="border-0 p-0 shadow-none" />
                </div>

                {comment.replies && comment.replies.length > 0 && (
                    <div className="mt-4 space-y-4">
                        {comment.replies.map(reply => (
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

import { Separator } from '@/components/ui/separator';
