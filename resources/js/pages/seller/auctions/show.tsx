import { Form, Head, Link } from '@inertiajs/react';
import { destroy } from '@/actions/App/Http/Controllers/SellerAuctionController';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { edit } from '@/routes/seller/auctions';
import type { AuctionRecord } from '@/types';

export default function SellerAuctionDetails({
    auction,
}: {
    auction: AuctionRecord;
}) {
    return (
        <SellerPortalLayout title="Auction details">
            <Head title={`Auction · ${auction.listing.title}`} />
            <div className="mx-auto max-w-4xl">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm font-semibold text-primary capitalize">
                            {auction.status.replaceAll('_', ' ')}
                        </p>
                        <h1 className="mt-1 text-2xl font-bold">
                            {auction.listing.title}
                        </h1>
                    </div>
                    {auction.status === 'draft' && (
                        <div className="flex gap-2">
                            <Link
                                href={edit(auction.id)}
                                className="rounded-xl border px-5 py-2.5 text-sm font-semibold"
                            >
                                Edit draft
                            </Link>
                            <Form
                                {...destroy.form(auction.id)}
                                onBefore={() =>
                                    window.confirm('Remove this auction draft?')
                                }
                            >
                                {({ processing }) => (
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-xl border border-red-300 px-5 py-2.5 text-sm font-semibold text-red-700 disabled:opacity-50 dark:border-red-900 dark:text-red-300"
                                    >
                                        Remove draft
                                    </button>
                                )}
                            </Form>
                        </div>
                    )}
                </div>
                <dl className="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-900">
                    <Detail
                        label="Type"
                        value={auction.type.replace('_', ' ')}
                    />
                    <Detail label="Lot" value={`${auction.quantity} unit(s)`} />
                    <Detail
                        label="Starting price"
                        value={`LKR ${auction.starting_price}`}
                    />
                    <Detail
                        label="Current price"
                        value={
                            auction.type === 'blind' &&
                            auction.status === 'live'
                                ? 'Private while live'
                                : `LKR ${auction.current_price ?? auction.starting_price}`
                        }
                    />
                    <Detail
                        label="Starts"
                        value={new Date(auction.starts_at).toLocaleString()}
                    />
                    <Detail
                        label="Ends"
                        value={new Date(auction.ends_at).toLocaleString()}
                    />
                </dl>
                {auction.status !== 'draft' && (
                    <p className="mt-4 rounded-xl bg-slate-100 p-4 text-sm dark:bg-slate-800">
                        Scheduled auctions cannot be edited or cancelled by
                        sellers.
                    </p>
                )}
            </div>
        </SellerPortalLayout>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                {label}
            </dt>
            <dd className="mt-1 text-base font-medium capitalize">{value}</dd>
        </div>
    );
}
