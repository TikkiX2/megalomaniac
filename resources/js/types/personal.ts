export interface PersonalProject {
    id: number;
    user_id: number;
    name: string;
    description: string | null;
    type: 'personal';
    status: string;
    color: string | null;
    icon: string | null;
    priority: string | null;
    start_date: string | null;
    end_date: string | null;
    deadline: string | null;
    budget: string | null;
    tags: string[] | null;
    is_archived: boolean;
    progress: number;
    tasks_count?: number;
    tasks?: PersonalTask[];
    milestones?: TaskMilestone[];
    members?: TaskProjectMember[];
    client?: { id: number; name: string } | null;
    currency?: { id: number; code: string } | null;
    created_at: string;
    updated_at: string;
}

export interface PersonalTask {
    id: number;
    project_id: number | null;
    user_id: number | null;
    title: string;
    description: string | null;
    status: string;
    priority: string | null;
    due_date: string | null;
    start_date: string | null;
    estimated_time: number | null;
    actual_time: number | null;
    tags: string[] | null;
    area: string | null;
    module: string | null;
    urgency: string | null;
    importance: string | null;
    responsible: string | null;
    sort_order: number;
    is_archived: boolean;
    properties?: TaskProperty[];
    project?: PersonalProject | null;
    created_at: string;
    updated_at: string;
}

export interface TaskProperty {
    id: number;
    project_task_id: number;
    key: string;
    type: 'text' | 'number' | 'date' | 'select' | 'multi_select' | 'checkbox' | 'url' | 'person';
    value_text: string | null;
    value_number: string | null;
    value_date: string | null;
    value_json: unknown;
    sort_order: number;
    created_at?: string;
    updated_at?: string;
}

export interface TaskMilestone {
    id: number;
    project_id: number;
    name: string;
    description: string | null;
    due_date: string | null;
    status: string;
    sort_order: number;
    created_at?: string;
    updated_at?: string;
}

export interface TaskProjectMember {
    id: number;
    project_id: number;
    user_id: number;
    role: string;
    user?: { id: number; name: string; email: string };
    created_at?: string;
}

export interface TaskSavedView {
    id: number;
    user_id: number;
    name: string;
    view_type: string;
    filters: Record<string, unknown> | null;
    sort: Record<string, unknown> | null;
    group_by: string | null;
    is_default: boolean;
}

export type TaskViewType = 'table' | 'kanban' | 'calendar' | 'list' | 'gallery' | 'timeline';
