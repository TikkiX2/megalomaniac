export interface ToolPolicy {
    mode: 'auto' | 'manual';
    groups: string[];
}

export interface ChatThread {
    id: string;
    title: string;
    model: string | null;
    tools_policy: ToolPolicy | null;
    is_pinned: boolean;
    created_at: string | null;
    updated_at: string | null;
}

export interface Citation {
    url: string;
    title: string | null;
    start_index?: number | null;
    end_index?: number | null;
}

export interface ChatReasoning {
    text: string;
    duration_ms: number | null;
}

export interface ChatMessage {
    id: string;
    role: 'user' | 'assistant';
    content: string;
    citations: Citation[];
    reasoning: ChatReasoning | null;
    created_at: string | null;
}

export interface AiChatState {
    enabled: boolean;
    configured: boolean;
    defaultModel: string | null;
}

export interface ToolActivity {
    id: string;
    name: string;
    status: 'running' | 'done' | 'failed';
}
