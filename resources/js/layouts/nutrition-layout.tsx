import MainLayout from '@/layouts/main-layout';
import { ReactNode } from 'react';

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
