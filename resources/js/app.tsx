import { createInertiaApp } from '@inertiajs/react';
import { SeoHead } from '@/components/seo-head';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'ProDeals.lk';

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
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <SeoHead />
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#0f766e',
    },
});
