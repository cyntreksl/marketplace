import { createInertiaApp, router } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SeoLayout from '@/layouts/seo-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { initializeTracking, trackPageView } from '@/lib/tracking';

const appName = import.meta.env.VITE_APP_NAME || 'ProDeals.lk';

if (typeof window !== 'undefined') {
    initializeTracking();
    router.on('navigate', (event) => trackPageView(event.detail.page.url));
    window.queueMicrotask(() => trackPageView(window.location.href));
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
