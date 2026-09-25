export type AuthFieldType = 'text' | 'password' | 'url' | 'select' | 'oauth' | 'qr';

export interface AuthField {
    name: string;
    type: AuthFieldType;
    label: string;
    required: boolean;
    help?: string | null;
    options: Record<string, string>;
}

export interface ConnectionCatalogItem {
    kind: string;
    label: string;
    group: string;
    description: string;
    auth_type: 'api_token' | 'basic' | 'oauth2' | 'none' | 'qr';
    auth_fields: AuthField[];
    transports: string[];
}

export type ConnectionStatusValue = 'unknown' | 'ok' | 'error' | 'expired';

export interface ConnectionRow {
    id: number;
    kind: string;
    name: string;
    auth_type: string;
    base_url: string | null;
    transport: string;
    enabled: boolean;
    status: ConnectionStatusValue;
    status_message: string | null;
    last_tested_at: string | null;
    last_used_at: string | null;
}

export interface ConnectionFormState {
    kind: string;
    name: string;
    credentials: Record<string, string>;
    base_url: string;
    transport: string;
    transport_config: Record<string, string>;
    enabled: boolean;
}

export interface ApprovalRow {
    id: number;
    connection_id: number;
    connection_name: string | null;
    connection_kind: string | null;
    action_key: string;
    access: 'read' | 'write' | 'destructive';
    summary: string;
    rationale: string | null;
    params: Record<string, unknown>;
    status: 'pending' | 'approved' | 'rejected' | 'expired' | 'executed' | 'failed';
    decision_note: string | null;
    expires_at: string | null;
    decided_at: string | null;
    created_at: string | null;
}

export interface ActivityLogRow {
    id: number;
    connection_id: number;
    connection_name: string | null;
    action_key: string;
    access: string;
    source: string;
    status: string;
    result_summary: string | null;
    error: string | null;
    duration_ms: number | null;
    params: Record<string, unknown>;
    created_at: string | null;
}

export interface FlashProps {
    success?: string | null;
    error?: string | null;
    test_result?: { ok: boolean; message: string } | null;
}
