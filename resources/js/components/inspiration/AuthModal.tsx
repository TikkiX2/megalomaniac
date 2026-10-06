import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import inspiration from '@/routes/inspiration';

/**
 * Connect a session credential for one source: paste the session cookie from
 * the user's own browser (primary) or run a simulated login (alternative, with
 * an explicit risk acknowledgement). Credentials live encrypted on the server.
 */
export default function AuthModal({
    sourceKey,
    sourceLabel,
    hasAuth,
    onClose,
}: {
    sourceKey: string;
    sourceLabel: string;
    hasAuth: boolean;
    onClose: () => void;
}) {
    const [method, setMethod] = useState<'cookie' | 'login'>('cookie');
    const [cookie, setCookie] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [accepted, setAccepted] = useState(false);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState<string | null>(null);

    const submit = () => {
        setSaving(true);
        setMessage(null);

        const payload =
            method === 'cookie'
                ? { method: 'cookie', cookie }
                : { method: 'login', email, password, acknowledged_login: accepted };

        router.post(
            inspiration.sources.auth.store.url(sourceKey),
            payload,
            {
                preserveScroll: true,
                onSuccess: () => onClose(),
                onError: (errors) => {
                    setMessage(errors.message ?? 'No se pudo guardar la credencial.');
                    setSaving(false);
                },
            },
        );
    };

    const disconnect = () => {
        setSaving(true);

        router.delete(inspiration.sources.auth.destroy.url(sourceKey), {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: () => {
                setSaving(false);
                setMessage('No se pudo desconectar la cuenta.');
            },
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Conectar cuenta — {sourceLabel}</DialogTitle>
                    <DialogDescription>
                        Pegá la cookie de sesión que tenés en tu navegador (principal) o usá el
                        login simulado (alternativa).
                    </DialogDescription>
                </DialogHeader>

                <div className="mb-3 flex gap-2">
                    <Button
                        type="button"
                        variant={method === 'cookie' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setMethod('cookie')}
                    >
                        Pegar cookie
                    </Button>
                    <Button
                        type="button"
                        variant={method === 'login' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setMethod('login')}
                    >
                        Login simulado
                    </Button>
                </div>

                {method === 'cookie' ? (
                    <div className="space-y-2">
                        <Label htmlFor="auth-cookie">Cookie de sesión</Label>
                        <textarea
                            id="auth-cookie"
                            value={cookie}
                            onChange={(event) => setCookie(event.target.value)}
                            rows={5}
                            className="w-full rounded-md border border-border bg-background p-2 font-mono text-xs text-foreground focus-visible:ring-2 focus-visible:ring-primary"
                            placeholder="nombre1=valor1; nombre2=valor2"
                            aria-label="Cookie de sesión"
                        />
                        <p className="text-[11px] text-muted-foreground">
                            Guardada cifrada; se envía como header Cookie solo en esta fuente.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="auth-email">Email</Label>
                            <Input id="auth-email" type="email" value={email} onChange={(event) => setEmail(event.target.value)} autoComplete="off" />
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="auth-password">Contraseña</Label>
                            <Input id="auth-password" type="password" value={password} onChange={(event) => setPassword(event.target.value)} autoComplete="off" />
                        </div>
                        <label className="flex items-start gap-2 text-xs text-muted-foreground">
                            <input
                                type="checkbox"
                                checked={accepted}
                                onChange={(event) => setAccepted(event.target.checked)}
                                className="mt-0.5"
                            />
                            <span>
                                Entiendo que el login automatizado puede violar los términos del
                                servicio y exponer mi cuenta a un bloqueo. Prefiero la cookie cuando
                                sea posible.
                            </span>
                        </label>
                    </div>
                )}

                {message && <p className="text-xs text-destructive">{message}</p>}

                <DialogFooter className="gap-2">
                    {hasAuth && (
                        <Button type="button" variant="destructive" onClick={disconnect} disabled={saving}>
                            {saving ? <Spinner className="size-3.5" /> : null} Desconectar
                        </Button>
                    )}
                    <Button type="button" onClick={submit} disabled={saving || (method === 'login' && (!accepted || !email || !password)) || (method === 'cookie' && cookie.trim().length < 5)}>
                        {saving ? <Spinner className="size-3.5" /> : null} Guardar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}