import { Head, Link } from '@inertiajs/react';
import { StorefrontLayout } from '@/components/storefront-layout';
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
        <StorefrontLayout title="Operations">
            <Head title="Operations" />
            <main className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <p className="text-sm font-bold tracking-wider text-amber-700 uppercase">
                    Admin portal
                </p>
                <h1 className="mt-2 text-4xl font-black">
                    Operations overview
                </h1>
                <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {cards.map((card) => (
                        <Link
                            key={card.label}
                            href={card.href}
                            className="rounded-2xl border border-stone-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md dark:border-stone-800 dark:bg-stone-900"
                        >
                            <p className="text-sm text-stone-500">
                                {card.label}
                            </p>
                            <p className="mt-2 text-3xl font-black">
                                {card.value}
                            </p>
                            <p className="mt-4 text-sm font-bold text-amber-700">
                                Open queue →
                            </p>
                        </Link>
                    ))}
                </div>
            </main>
        </StorefrontLayout>
    );
}
