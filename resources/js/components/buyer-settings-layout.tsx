import { Link, usePage } from '@inertiajs/react';
import { BuyerPageHeader } from '@/components/buyer-page-header';
import { BuyerPortalLayout } from '@/components/buyer-portal-layout';
import { edit as profileEdit } from '@/routes/buyer/settings/profile';
import { edit as securityEdit } from '@/routes/buyer/settings/security';

export function BuyerSettingsLayout({
    children,
    title,
}: {
    children: React.ReactNode;
    title: string;
}) {
    const path = usePage().url.split('?')[0];
    const tabs = [
        { label: 'Profile', href: profileEdit() },
        { label: 'Password & security', href: securityEdit() },
    ];

    return (
        <BuyerPortalLayout title="Settings">
            <div className="space-y-7">
                <BuyerPageHeader
                    eyebrow="Account"
                    title={title}
                    description="Manage your personal details and secure access to your ProDeals account."
                />
                <nav
                    className="flex gap-2 border-b border-slate-200 dark:border-slate-800"
                    aria-label="Settings sections"
                >
                    {tabs.map((tab) => (
                        <Link
                            key={tab.label}
                            href={tab.href}
                            className={`border-b-2 px-3 py-3 text-sm font-bold ${path === tab.href.url ? 'border-orange-600 text-orange-700 dark:text-orange-300' : 'border-transparent text-slate-500'}`}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </nav>
                <div className="max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900">
                    {children}
                </div>
            </div>
        </BuyerPortalLayout>
    );
}
