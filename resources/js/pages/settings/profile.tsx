import { Transition } from '@headlessui/react';
import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem, SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

function calcIMC(weight: number | null, height: number | null): { imc: number | null; label: string; color: string } {
    if (!weight || !height || weight <= 0 || height <= 0) return { imc: null, label: '—', color: 'text-muted-foreground' };
    const hM = height > 3 ? height / 100 : height;
    const imc = weight / (hM * hM);
    if (imc < 18.5) return { imc, label: 'Bajo peso', color: 'text-blue-400' };
    if (imc < 25) return { imc, label: 'Normal', color: 'text-emerald-400' };
    if (imc < 30) return { imc, label: 'Sobrepeso', color: 'text-amber-400' };
    return { imc, label: 'Obesidad', color: 'text-red-400' };
}

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<SharedData>().props;
    const initialWeight = auth.user.weight ? Number(auth.user.weight) : null;
    const initialHeight = auth.user.height ? Number(auth.user.height) : null;
    const imcInfo = calcIMC(initialWeight, initialHeight);

    return (
        <MainLayout>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile Settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Información del perfil"
                        description="Actualiza tu nombre, email y métricas corporales"
                    />

                    <Form
                        {...ProfileController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>

                                    <Input
                                        id="name"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.name}
                                        name="name"
                                        required
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.email}
                                    />
                                </div>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <div>
                                            <p className="-mt-4 text-sm text-muted-foreground">
                                                Your email address is
                                                unverified.{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                >
                                                    Click here to resend the
                                                    verification email.
                                                </Link>
                                            </p>

                                            {status ===
                                                'verification-link-sent' && (
                                                    <div className="mt-2 text-sm font-medium text-primary">
                                                        A new verification link has
                                                        been sent to your email
                                                        address.
                                                    </div>
                                                )}
                                        </div>
                                    )}

                                <div className="rounded-xl bg-card border border-border p-4 space-y-4">
                                    <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                                        <span className="material-symbols-outlined text-primary text-[18px]">monitor_weight</span>
                                        Métricas corporales
                                    </h3>
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="weight">Peso (kg)</Label>
                                            <Input
                                                id="weight"
                                                type="number"
                                                step="0.1"
                                                min="20"
                                                max="500"
                                                defaultValue={auth.user.weight as string | undefined}
                                                name="weight"
                                                placeholder="75.5"
                                                className="bg-background"
                                            />
                                            <InputError className="mt-1" message={errors.weight} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="height">Altura (cm)</Label>
                                            <Input
                                                id="height"
                                                type="number"
                                                step="0.1"
                                                min="50"
                                                max="300"
                                                defaultValue={auth.user.height as string | undefined}
                                                name="height"
                                                placeholder="175"
                                                className="bg-background"
                                            />
                                            <InputError className="mt-1" message={errors.height} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="target_weight">Peso objetivo (kg)</Label>
                                            <Input
                                                id="target_weight"
                                                type="number"
                                                step="0.1"
                                                min="20"
                                                max="500"
                                                defaultValue={auth.user.target_weight as string | undefined}
                                                name="target_weight"
                                                placeholder="70"
                                                className="bg-background"
                                            />
                                            <InputError className="mt-1" message={errors.target_weight} />
                                        </div>
                                    </div>
                                    {imcInfo.imc !== null ? (
                                        <div className="flex items-center justify-between rounded-lg bg-background border border-border px-4 py-3">
                                            <div>
                                                <p className="text-[10px] font-black uppercase tracking-widest text-muted-foreground">IMC</p>
                                                <p className="text-lg font-black text-foreground">{imcInfo.imc.toFixed(1)}</p>
                                                <p className={`text-xs font-bold ${imcInfo.color}`}>{imcInfo.label}</p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-[10px] text-muted-foreground">Peso: {initialWeight} kg {auth.user.target_weight ? `→ ${auth.user.target_weight} kg` : ''}</p>
                                                <p className="text-[10px] text-muted-foreground">Altura: {initialHeight} cm</p>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">Introduce peso y altura para calcular tu IMC.</p>
                                    )}
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        Save
                                    </Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-neutral-600">
                                            Saved
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <DeleteUser />
            </SettingsLayout>
        </MainLayout>
    );
}
