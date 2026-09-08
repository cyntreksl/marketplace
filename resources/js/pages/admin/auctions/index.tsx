import { Head, Link } from '@inertiajs/react';
import { PortalLayout } from '@/components/portal-layout';
import { show } from '@/routes/admin/auctions';
import { index as settings } from '@/routes/admin/auctions/settings';
import type { AuctionRecord } from '@/types';

export default function AdminAuctions({
    auctions,
}: {
    auctions: { data: AuctionRecord[] };
}) {
    return (
        <PortalLayout portal="admin" title="Auctions">
            <Head title="Auction Management" />
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">Auction management</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Monitor scheduled, live, and unpaid auctions.
                    </p>
                </div>
                <Link
                    href={settings()}
                    className="rounded-xl border px-5 py-2.5 text-sm font-semibold"
                >
                    Feature settings
                </Link>
            </div>
            <div className="mt-6 grid gap-3">
                {auctions.data.map((auction) => (
                    <Link
                        key={auction.id}
                        href={show(auction.id)}
                        className="grid gap-2 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-[1fr_auto] dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div>
                            <p className="font-semibold">
                                {auction.listing.title}
                            </p>
                            <p className="mt-1 text-sm text-slate-500">
                                {auction.listing.seller_profile?.store_name} ·{' '}
                                {auction.type.replace('_', ' ')} ·{' '}
                                {auction.quantity} unit(s)
                            </p>
                        </div>
                        <span className="self-center text-sm font-semibold capitalize">
                            {auction.status.replaceAll('_', ' ')}
                        </span>
                    </Link>
                ))}
            </div>
        </PortalLayout>
    );
}
