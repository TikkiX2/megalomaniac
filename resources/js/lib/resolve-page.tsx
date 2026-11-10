import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ComponentType, ReactNode } from 'react';
import { resolvePageLayout } from './page-layout';

type LayoutFunction = (page: ReactNode) => ReactNode;

type PageComponent = ComponentType<Record<string, unknown>> & {
    layout?: ComponentType<{ children: ReactNode }>[] | LayoutFunction | null;
};

type PageModule = {
    default: PageComponent;
};

export async function resolvePage(name: string): Promise<PageModule> {
    const page = (await resolvePageComponent(
        `../pages/${name}.tsx`,
        import.meta.glob('../pages/**/*.tsx'),
    )) as unknown as PageModule;

    if (page.default.layout === undefined) {
        const layout = resolvePageLayout(name);

        if (layout !== null) {
            page.default.layout = layout;
        }
    }

    return page;
}
