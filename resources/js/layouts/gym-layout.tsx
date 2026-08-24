import type { ReactNode } from 'react';
import MainLayout from '@/layouts/main-layout';

interface GymLayoutProps {
    children: ReactNode;
}

export default function GymLayout({ children }: GymLayoutProps) {
    return (
        <MainLayout>
            {children}
        </MainLayout>
    );
}
