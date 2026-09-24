export type ThreadGroup = 'Fijados' | 'Hoy' | 'Ayer' | 'Últimos 7 días' | 'Anteriores';

export const THREAD_GROUP_ORDER: ThreadGroup[] = ['Fijados', 'Hoy', 'Ayer', 'Últimos 7 días', 'Anteriores'];

export function threadGroup(updatedAt: string | null, isPinned: boolean): ThreadGroup {
    if (isPinned) return 'Fijados';

    if (!updatedAt) return 'Anteriores';

    const date = new Date(updatedAt);
    const now = new Date();
    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const startOfYesterday = new Date(startOfToday.getTime() - 86_400_000);
    const weekAgo = new Date(startOfToday.getTime() - 7 * 86_400_000);

    if (date >= startOfToday) return 'Hoy';
    if (date >= startOfYesterday) return 'Ayer';
    if (date >= weekAgo) return 'Últimos 7 días';

    return 'Anteriores';
}
