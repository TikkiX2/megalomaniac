export type MemoryScopeName = 'global' | 'thread';

export interface MemoryRow {
    id: string;
    scope: MemoryScopeName;
    thread_id: string | null;
    content: string;
    source: 'agent' | 'user';
    updated_at: string | null;
}

export interface ThreadOption {
    id: string;
    title: string | null;
}

export interface MemoryLimits {
    max_content: number;
    max_global: number;
    max_thread: number;
}
