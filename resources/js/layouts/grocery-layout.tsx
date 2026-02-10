import type { ReactNode } from 'react';
import MainLayout from '@/layouts/main-layout';

interface GroceryLayoutProps {
    children: ReactNode;
}

export default function GroceryLayout({ children }: GroceryLayoutProps) {
    return (
        <MainLayout>
            {children}
        </MainLayout>
    );
}
