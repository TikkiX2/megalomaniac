export interface ProjectBrief {
    id: number;
    name: string;
    color: string | null;
}

export interface TaskRow {
    id: number;
    title: string;
    project: ProjectBrief | null;
}

export interface ActiveFilters {
    project_id?: number;
    priority?: string;
    status?: string;
    tag?: string;
    type?: string;
}

export interface FilterOption {
    id: number;
    name: string;
    type: string;
}
