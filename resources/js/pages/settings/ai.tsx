import { Transition } from '@headlessui/react';
import { Form, Head } from '@inertiajs/react';
import AiSettingsController from '@/actions/App/Http/Controllers/Settings/AiSettingsController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import SettingsLayout from '@/layouts/settings/layout';

export default function AiSettings({
    ai,
}: {
    ai: {
        ai_provider_url: string | null;
        has_provider_key: boolean;
        ai_model: string | null;
        ai_enabled: boolean;
    };
}) {
    return (
        <MainLayout>
            <Head title="AI settings" />

            <h1 className="sr-only">AI Settings</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="AI Settings"
                        description="Configure your AI provider connection and preferences"
                    />

                    <Form
                        {...AiSettingsController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        transform={(data) => ({
                            ...data,
                            ai_enabled: data.ai_enabled === 'on' || data.ai_enabled === true,
                        })}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="rounded-xl bg-card border border-border p-4 space-y-4">
                                    <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                                        <span className="material-symbols-outlined text-primary text-[18px]">smart_toy</span>
                                        Provider Configuration
                                    </h3>

                                    <div className="grid gap-2">
                                        <Label htmlFor="ai_provider_url">Provider URL</Label>
                                        <Input
                                            id="ai_provider_url"
                                            name="ai_provider_url"
                                            type="url"
                                            className="mt-1 block w-full bg-background"
                                            defaultValue={ai.ai_provider_url ?? ''}
                                            placeholder="https://api.openai.com/v1"
                                        />
                                        <InputError className="mt-2" message={errors.ai_provider_url} />
                                        <p className="text-xs text-muted-foreground">
                                            OpenAI-compatible base URL, without <code>/chat/completions</code> (e.g.
                                            https://api.openai.com/v1). For OpenCode Go use
                                            https://opencode.ai/zen/go/v1 — the session header is sent automatically.
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="ai_provider_key">API Key</Label>
                                        <Input
                                            id="ai_provider_key"
                                            name="ai_provider_key"
                                            type="password"
                                            className="mt-1 block w-full bg-background"
                                            defaultValue=""
                                            placeholder={ai.has_provider_key ? '•••••••• (guardada)' : 'sk-...'}
                                            autoComplete="off"
                                        />
                                        <InputError className="mt-2" message={errors.ai_provider_key} />
                                        <p className="text-xs text-muted-foreground">
                                            Leave empty to keep your current key
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="ai_model">Model</Label>
                                        <Input
                                            id="ai_model"
                                            name="ai_model"
                                            type="text"
                                            className="mt-1 block w-full bg-background"
                                            defaultValue={ai.ai_model ?? ''}
                                            placeholder="gpt-4o"
                                        />
                                        <InputError className="mt-2" message={errors.ai_model} />
                                        <p className="text-xs text-muted-foreground">
                                            The model identifier to use for AI requests
                                        </p>
                                    </div>
                                </div>

                                <div className="rounded-xl bg-card border border-border p-4 space-y-4">
                                    <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                                        <span className="material-symbols-outlined text-primary text-[18px]">power_settings_new</span>
                                        Enable AI Features
                                    </h3>

                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="ai_enabled"
                                            name="ai_enabled"
                                            defaultChecked={ai.ai_enabled}
                                        />
                                        <Label htmlFor="ai_enabled" className="cursor-pointer">
                                            Enable AI-powered features
                                        </Label>
                                    </div>
                                    <InputError className="mt-2" message={errors.ai_enabled} />
                                    <p className="text-xs text-muted-foreground">
                                        When enabled, AI features will be available across the application
                                    </p>
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-ai-settings-button"
                                    >
                                        Save AI Settings
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
            </SettingsLayout>
        </MainLayout>
    );
}
