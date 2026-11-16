import type { LucideIcon } from 'lucide-react';
import {
    Brain,
    Briefcase,
    CheckSquare,
    Contact,
    Dumbbell,
    FolderKanban,
    HeartPulse,
    LayoutGrid,
    NotebookPen,
    Wallet,
} from 'lucide-react';
import { dashboard } from '@/routes';
import freelance from '@/routes/freelance';
import inspiration from '@/routes/inspiration';

export interface NavAreaItem {
    title: string;
    href: string;
    match?: string[];
    badge?: 'approvals';
}

export interface NavAreaGroup {
    label?: string;
    items: NavAreaItem[];
}

export interface NavArea {
    key: string;
    title: string;
    icon: LucideIcon;
    href: string;
    match: string[];
    groups: NavAreaGroup[];
}

export const navAreas: NavArea[] = [
    {
        key: 'today',
        title: 'Hoy',
        icon: CheckSquare,
        href: dashboard().url,
        match: ['/dashboard', '/today'],
        groups: [
            {
                items: [
                    {
                        title: 'Hoy',
                        href: dashboard().url,
                        match: [dashboard().url, '/today'],
                    },
                    { title: 'Semana', href: '/today/week' },
                    { title: 'Cola', href: '/today/queue' },
                    { title: 'Archivadas', href: '/today/archived' },
                ],
            },
        ],
    },
    {
        key: 'gym',
        title: 'Gym',
        icon: Dumbbell,
        href: '/fitness/gym',
        match: ['/fitness', '/gym'],
        groups: [
            {
                items: [
                    { title: 'Entrenamiento', href: '/fitness/gym' },
                    { title: 'Rutinas', href: '/fitness/routines' },
                    { title: 'Historial', href: '/fitness/history' },
                    { title: 'Nutrición', href: '/fitness/nutrition' },
                    { title: 'Suplementos', href: '/fitness/supplements' },
                    { title: 'Compras', href: '/fitness/groceries' },
                ],
            },
        ],
    },
    {
        key: 'personal',
        title: 'Proyectos',
        icon: FolderKanban,
        href: '/personal/projects',
        match: ['/personal'],
        groups: [
            {
                items: [
                    { title: 'Proyectos', href: '/personal/projects' },
                    { title: 'Tareas', href: '/personal/tasks' },
                ],
            },
        ],
    },
    {
        key: 'diario',
        title: 'Diario',
        icon: NotebookPen,
        href: '/diario',
        match: ['/diario'],
        groups: [
            {
                items: [
                    { title: 'Hoy', href: '/diario' },
                    { title: 'Calendario', href: '/diario/calendario' },
                    {
                        title: 'Todas',
                        href: '/diario/todas',
                        match: ['/diario/todas', '/diario/buscar'],
                    },
                    { title: 'Repaso', href: '/diario/repaso' },
                ],
            },
        ],
    },
    {
        key: 'ai',
        title: 'IA',
        icon: Brain,
        href: '/ai/chat',
        match: ['/ai'],
        groups: [
            {
                items: [{ title: 'Chat', href: '/ai/chat' }],
            },
            {
                label: 'Asistentes',
                items: [
                    { title: 'Gym', href: '/ai/gym' },
                    { title: 'Nutrición', href: '/ai/nutrition' },
                    { title: 'Compras', href: '/ai/grocery' },
                    { title: 'Finanzas', href: '/ai/finance' },
                    { title: 'Freelance', href: '/ai/freelance' },
                    { title: 'Salud', href: '/ai/health' },
                    { title: 'Personas', href: '/ai/people' },
                ],
            },
            {
                label: 'Sistema',
                items: [
                    { title: 'Fuentes', href: '/ai/sources' },
                    { title: 'Memoria', href: '/ai/memory' },
                ],
            },
        ],
    },
    {
        key: 'finance',
        title: 'Finanzas',
        icon: Wallet,
        href: '/finance/dashboard',
        match: ['/finance'],
        groups: [
            {
                items: [
                    { title: 'Panel', href: '/finance/dashboard' },
                    { title: 'Compras', href: '/finance/purchases' },
                    { title: 'Ingresos', href: '/finance/incomes' },
                ],
            },
        ],
    },
    {
        key: 'freelance',
        title: 'Freelance',
        icon: Briefcase,
        href: freelance.dashboard().url,
        match: ['/freelance'],
        groups: [
            {
                items: [
                    { title: 'Panel', href: freelance.dashboard().url },
                    { title: 'Clientes', href: freelance.clients.index().url },
                    {
                        title: 'Proyectos',
                        href: freelance.projects.index().url,
                    },
                    {
                        title: 'Cotizaciones',
                        href: freelance.quotes.index().url,
                    },
                ],
            },
        ],
    },
    {
        key: 'health',
        title: 'Salud',
        icon: HeartPulse,
        href: '/health',
        match: ['/health'],
        groups: [
            {
                items: [
                    { title: 'Panel', href: '/health' },
                    { title: 'Condiciones', href: '/health/conditions' },
                    { title: 'Medicación', href: '/health/medications' },
                    { title: 'Mediciones', href: '/health/measurements' },
                    { title: 'Síntomas', href: '/health/symptoms' },
                    { title: 'Estudios', href: '/health/studies' },
                    { title: 'Citas', href: '/health/appointments' },
                    { title: 'Profesionales', href: '/health/professionals' },
                    { title: 'Chats', href: '/health/chats' },
                ],
            },
        ],
    },
    {
        key: 'people',
        title: 'Personas',
        icon: Contact,
        href: '/people',
        match: ['/people'],
        groups: [
            {
                items: [
                    { title: 'Personas', href: '/people' },
                    { title: 'Historial', href: '/people/timeline' },
                    { title: 'Calendario', href: '/people/calendar' },
                ],
            },
        ],
    },
    {
        key: 'more',
        title: 'Más',
        icon: LayoutGrid,
        href: inspiration.explore().url,
        match: [
            '/inspiration',
            '/agents',
            '/feed',
            '/storage',
            '/integrations',
        ],
        groups: [
            {
                label: 'Inspiración',
                items: [
                    { title: 'Explorar', href: inspiration.explore().url },
                    {
                        title: 'Ajustes de fuentes',
                        href: inspiration.settings.index().url,
                    },
                ],
            },
            {
                label: 'Sistema',
                items: [
                    { title: 'Agentes', href: '/agents' },
                    { title: 'Feed', href: '/feed' },
                    { title: 'Archivos', href: '/storage' },
                    {
                        title: 'Aprobaciones',
                        href: '/integrations/approvals',
                        badge: 'approvals',
                    },
                ],
            },
        ],
    },
];

export function matchesHref(pathname: string, href: string): boolean {
    const cleanPathname = pathname.split('?')[0];
    const cleanHref = href.split('?')[0];

    return (
        cleanPathname === cleanHref ||
        cleanPathname.startsWith(cleanHref === '/' ? '/' : `${cleanHref}/`)
    );
}

export function findActiveArea(pathname: string): NavArea | null {
    let active: NavArea | null = null;
    let activeLength = 0;

    for (const area of navAreas) {
        for (const prefix of area.match) {
            if (matchesHref(pathname, prefix) && prefix.length > activeLength) {
                active = area;
                activeLength = prefix.length;
            }
        }
    }

    return active;
}

export function findActiveItem(
    pathname: string,
    area: NavArea,
): NavAreaItem | null {
    let active: NavAreaItem | null = null;
    let activeLength = 0;

    for (const group of area.groups) {
        for (const item of group.items) {
            const matchedLength = (item.match ?? [item.href])
                .filter((href) => matchesHref(pathname, href))
                .map((href) => href.split('?')[0].length)
                .sort((a, b) => b - a)[0];

            if (matchedLength !== undefined && matchedLength > activeLength) {
                active = item;
                activeLength = matchedLength;
            }
        }
    }

    return active;
}
