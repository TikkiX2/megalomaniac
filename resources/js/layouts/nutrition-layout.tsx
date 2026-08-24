import type { ReactNode } from 'react';
import MainLayout from '@/layouts/main-layout';

interface NutritionLayoutProps {
    children: ReactNode;
}

export default function NutritionLayout({ children }: NutritionLayoutProps) {
    return (
        <MainLayout>
            {children}
        </MainLayout>
    );
}
