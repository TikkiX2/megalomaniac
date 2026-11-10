import type { ComponentType, ReactNode } from 'react';
import GroceryLayout from '@/layouts/grocery-layout';
import GymLayout from '@/layouts/gym-layout';
import HealthLayout from '@/layouts/health-layout';
import MainLayout from '@/layouts/main-layout';
import NutritionLayout from '@/layouts/nutrition-layout';
import PeopleLayout from '@/layouts/people-layout';
import SettingsLayout from '@/layouts/settings/layout';
import SupplementLayout from '@/layouts/supplement-layout';

export type LayoutComponent = ComponentType<{ children: ReactNode }>;

export function resolvePageLayout(name: string): LayoutComponent[] | null {
    if (name === 'welcome' || name.startsWith('auth/')) {
        return null;
    }

    if (name === 'ai/chat' || name === 'ai/thread' || name === 'ai/module') {
        return null; // todavía se envuelven solas; Task 7 las convierte
    }

    if (name === 'settings' || name.startsWith('settings/')) {
        return [MainLayout, SettingsLayout];
    }

    if (name.startsWith('health/')) {
        return [MainLayout, HealthLayout];
    }

    if (name.startsWith('people/')) {
        return [MainLayout, PeopleLayout];
    }

    if (name === 'fitness/gym-routine' || name === 'fitness/routines') {
        return [MainLayout, GymLayout];
    }

    if (name === 'fitness/nutrition') {
        return [MainLayout, NutritionLayout];
    }

    if (name === 'fitness/grocery') {
        return [MainLayout, GroceryLayout];
    }

    if (name === 'fitness/supplement') {
        return [MainLayout, SupplementLayout];
    }

    return [MainLayout];
}
