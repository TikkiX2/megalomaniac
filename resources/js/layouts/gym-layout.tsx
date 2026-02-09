import MainLayout from '@/layouts/main-layout';
import { ReactNode } from 'react';

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
