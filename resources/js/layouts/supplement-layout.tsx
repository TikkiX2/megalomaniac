import type { ReactNode } from 'react';
import MainLayout from '@/layouts/main-layout';

interface SupplementLayoutProps {
    children: ReactNode;
}

export default function SupplementLayout({ children }: SupplementLayoutProps) {
    return (
        <MainLayout>
            {children}
        </MainLayout>
    );
}
