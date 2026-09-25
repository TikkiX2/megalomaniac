import { Transition } from '@headlessui/react';
import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import MainLayout from '@/layouts/main-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/ai-settings';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'API Keys',
        href: '/settings/api-keys',
    },
];

interface ApiToken {
    id: string;
    name: string;
    created_at: string;
}

export default function ApiTokensSettings({
    tokens,
    flash_token,
}: {
    tokens: ApiToken[];
    flash_token?: string;
}) {
    const [showToken, setShowToken] = useState(!!flash_token);
    const [confirmDelete, setConfirmDelete] = useState<string | null>(null);

    const handleDelete = (tokenId: string) => {
        router.delete(`/settings/api-keys/${tokenId}`);
        setConfirmDelete(null);
    };

    return (
        <MainLayout>
            <Head title="API Keys" />

            <h1 className="sr-only">API Keys</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="API Keys"
                        description="Manage API keys for REST API and MCP server access"
                    />

                    {showToken && flash_token && (
                        <div className="rounded-xl bg-primary/10 border border-primary/30 p-4 space-y-3">
                            <h3 className="text-sm font-black uppercase tracking-widest text-primary flex items-center gap-2">
                                <span className="material-symbols-outlined text-[18px]">key</span>
                                Token Created
                            </h3>
                            <div className="bg-background rounded-lg p-3 font-mono text-sm text-foreground break-all border border-border">
                                {flash_token}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Copy this token now. It will not be shown again.
                            </p>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    navigator.clipboard.writeText(flash_token);
                                }}
                            >
                                Copy to Clipboard
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setShowToken(false)}
                            >
                                Dismiss
                            </Button>
                        </div>
                    )}

                    <div className="rounded-xl bg-card border border-border p-4 space-y-4">
                        <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary text-[18px]">add_circle</span>
                            Create New Key
                        </h3>

                        <Form
                            method="post"
                            action="/settings/api-keys"
                            options={{ preserveScroll: true }}
                            className="flex items-end gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="flex-1 grid gap-2">
                                        <Label htmlFor="name">Key Name</Label>
                                        <Input
                                            id="name"
                                            name="name"
                                            type="text"
                                            className="bg-background"
                                            placeholder="e.g. MCP Server, Mobile App, etc."
                                            required
                                        />
                                        <InputError className="mt-1" message={errors.name} />
                                    </div>
                                    <Button
                                        disabled={processing}
                                        data-test="create-token-button"
                                    >
                                        Generate
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>

                    <div className="rounded-xl bg-card border border-border p-4 space-y-4">
                        <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary text-[18px]">vpn_key</span>
                            Active Keys
                        </h3>

                        {tokens.length === 0 ? (
                            <p className="text-sm text-muted-foreground py-4 text-center">
                                No API keys yet. Create one above.
                            </p>
                        ) : (
                            <div className="space-y-2">
                                {tokens.map((token) => (
                                    <div
                                        key={token.id}
                                        className="flex items-center justify-between p-3 rounded-lg bg-background border border-border"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="material-symbols-outlined text-primary text-[18px]">key</span>
                                            <div>
                                                <p className="text-sm font-medium text-foreground">{token.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    Created {new Date(token.created_at).toLocaleDateString()}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {confirmDelete === token.id ? (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() => handleDelete(token.id)}
                                                        data-test="confirm-delete-token"
                                                    >
                                                        Delete
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        onClick={() => setConfirmDelete(null)}
                                                    >
                                                        Cancel
                                                    </Button>
                                                </>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => setConfirmDelete(token.id)}
                                                    data-test="delete-token-button"
                                                >
                                                    <span className="material-symbols-outlined text-[16px]">delete</span>
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="rounded-xl bg-card border border-border p-4 space-y-3">
                        <h3 className="text-sm font-black uppercase tracking-widest text-foreground flex items-center gap-2">
                            <span className="material-symbols-outlined text-primary text-[18px]">info</span>
                            Usage
                        </h3>
                        <div className="text-sm text-muted-foreground space-y-2">
                            <p>
                                <strong className="text-foreground">REST API:</strong>{' '}
                                <code className="bg-background px-1.5 py-0.5 rounded text-xs">Authorization: Bearer {'{token}'}</code>
                            </p>
                            <p>
                                <strong className="text-foreground">MCP Server:</strong>{' '}
                                Use the same token for MCP authentication at <code className="bg-background px-1.5 py-0.5 rounded text-xs">POST /mcp/megalomaniac</code>
                            </p>
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </MainLayout>
    );
}
