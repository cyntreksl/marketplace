import { createInertiaApp } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SeoHead } from '@/components/seo-head';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'ProDeals.lk';

function SeoLayout({ children }: { children: ReactNode }) {
    return (
        <>
            <SeoHead />
            {children}
        </>
    );
}

createInertiaApp({
    serverHead: true,
    title: (title) =>
        title
            ? title.includes(appName)
                ? title
                : `${title} - ${appName}`
            : appName,
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name.startsWith('storefront/'):
            case name.startsWith('admin/'):
            case name.startsWith('buyer/'):
            case name.startsWith('seller/'):
                return SeoLayout;
            case name.startsWith('auth/'):
                return [SeoLayout, AuthLayout];
            case name.startsWith('settings/'):
                return [SeoLayout, AppLayout, SettingsLayout];
            default:
                return [SeoLayout, AppLayout];
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#0f766e',
    },
});
