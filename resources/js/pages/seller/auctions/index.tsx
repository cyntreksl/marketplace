import { Head, Link } from '@inertiajs/react';
import { Gavel, Plus } from 'lucide-react';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { create, show } from '@/routes/seller/auctions';
import type { AuctionRecord } from '@/types';

export default function SellerAuctions({
    auctions,
}: {
    auctions: { data: AuctionRecord[] };
}) {
    return (
        <SellerPortalLayout title="Auctions">
            <Head title="Auctions" />
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">Auctions</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Manage auction offers attached to your products.
                    </p>
                </div>
                <Link
                    href={create()}
                    className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-semibold text-primary-foreground"
                >
                    <Plus className="size-4" /> Create auction
                </Link>
            </div>
            <div className="mt-6 grid gap-3">
                {auctions.data.map((auction) => (
                    <Link
                        key={auction.id}
                        href={show(auction.id)}
                        className="grid gap-2 rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-primary/50 md:grid-cols-[1fr_auto] dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div>
                            <p className="font-semibold">
                                {auction.listing.title}
                            </p>
                            <p className="mt-1 text-sm text-slate-500">
                                {auction.type.replace('_', ' ')} ·{' '}
                                {auction.quantity} unit(s) · starts{' '}
                                {new Date(auction.starts_at).toLocaleString()}
                            </p>
                        </div>
                        <span className="self-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize dark:bg-slate-800">
                            {auction.status.replaceAll('_', ' ')}
                        </span>
                    </Link>
                ))}
                {auctions.data.length === 0 && (
                    <div className="rounded-2xl border border-dashed p-12 text-center text-slate-500">
                        <Gavel className="mx-auto mb-3 size-8" />
                        No auctions yet.
                    </div>
                )}
            </div>
        </SellerPortalLayout>
    );
}
