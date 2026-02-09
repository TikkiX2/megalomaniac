import MainLayout from '@/layouts/main-layout';
import { ReactNode } from 'react';

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
