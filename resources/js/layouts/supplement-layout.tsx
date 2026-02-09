import MainLayout from '@/layouts/main-layout';
import { ReactNode } from 'react';

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
