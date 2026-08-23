import { Head, Link } from '@inertiajs/react';
import { PortalLayout } from '@/components/portal-layout';
import { index as listingsIndex } from '@/routes/admin/listings';
import { index as sellersIndex } from '@/routes/admin/sellers';

export default function AdminDashboard({
    metrics,
}: {
    metrics: Record<string, number>;
}) {
    const cards = [
        {
            label: 'Seller reviews',
            value: metrics.pendingSellers,
            href: sellersIndex(),
        },
        {
            label: 'Listing reviews',
            value: metrics.pendingListings,
            href: listingsIndex(),
        },
        {
            label: 'Open orders',
            value: metrics.openOrders,
            href: listingsIndex(),
        },
        {
            label: 'Buyer accounts',
            value: metrics.buyers,
            href: listingsIndex(),
        },
    ];

    return (
        <PortalLayout portal="admin" title="Operations">
            <Head title="Operations" />
            <main className="mx-auto max-w-7xl">
                <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                    Admin portal
                </p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                    Operations overview
                </h1>
                <div className="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <Link
                            key={card.label}
                            href={card.href}
                            className="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-lg hover:shadow-primary/10 dark:border-slate-800 dark:bg-slate-900"
                        >
                            <p className="text-sm font-medium text-muted-foreground">
                                {card.label}
                            </p>
                            <p className="mt-3 text-4xl font-bold tracking-tight">
                                {card.value}
                            </p>
                            <p className="mt-6 text-sm font-semibold text-primary">
                                Open queue →
                            </p>
                        </Link>
                    ))}
                </div>
            </main>
        </PortalLayout>
    );
}
