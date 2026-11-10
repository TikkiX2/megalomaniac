import type { ComponentType, ReactNode } from 'react';

export type LayoutFunction = (page: ReactNode) => ReactNode;

export function withLayout<P extends object>(
    component: ComponentType<P>,
    layout: LayoutFunction,
): ComponentType<P> & { layout: LayoutFunction } {
    return Object.assign(component, { layout });
}
