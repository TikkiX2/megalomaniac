import { router, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface LockedNoticeProps {
    expiresAt: string | null;
    action: string;
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString('es-AR', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function LockedNotice({ expiresAt, action }: LockedNoticeProps) {
    const form = useForm({ password: '' });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (form.processing) {
            return;
        }

        form.post(action, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                router.reload();
            },
        });
    };

    return (
        <div
            role="region"
            aria-label="Memorias privadas bloqueadas"
            className="mb-6 rounded-lg border border-primary/30 bg-card px-4 py-4"
        >
            <div className="flex items-start gap-3">
                <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md border border-primary/30 bg-primary/10 text-primary">
                    <Lock aria-hidden="true" className="size-4" />
                </span>
                <div className="min-w-0 flex-1">
                    <h2 className="text-sm font-semibold text-white/90">
                        Hay memorias privadas en este día
                    </h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Ingresá la contraseña de tu cuenta para verlas. El
                        diario queda desbloqueado por 15 minutos.
                    </p>
                    {expiresAt !== null && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            Desbloqueado hasta las {formatTime(expiresAt)}.
                        </p>
                    )}

                    <form
                        onSubmit={submit}
                        className="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end"
                    >
                        <div className="flex-1">
                            <Label
                                htmlFor="journal-unlock-password"
                                className="sr-only"
                            >
                                Contraseña
                            </Label>
                            <Input
                                id="journal-unlock-password"
                                type="password"
                                name="password"
                                autoComplete="current-password"
                                placeholder="Contraseña"
                                value={form.data.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                                aria-invalid={form.errors.password ? true : undefined}
                                aria-describedby={
                                    form.errors.password
                                        ? 'journal-unlock-error'
                                        : undefined
                                }
                                className="min-h-11"
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="min-h-11"
                        >
                            {form.processing ? 'Desbloqueando…' : 'Desbloquear'}
                        </Button>
                    </form>

                    {form.errors.password && (
                        <p
                            id="journal-unlock-error"
                            role="alert"
                            className="mt-2 text-xs text-destructive"
                        >
                            {form.errors.password}
                        </p>
                    )}

                    <a
                        href="/forgot-password"
                        className="mt-2 inline-block text-xs text-muted-foreground underline-offset-4 hover:text-white/90 hover:underline"
                    >
                        ¿Olvidaste tu contraseña?
                    </a>
                </div>
            </div>
        </div>
    );
}
