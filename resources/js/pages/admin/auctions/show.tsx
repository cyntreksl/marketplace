import { Form, Head } from '@inertiajs/react';
import { cancel } from '@/actions/App/Http/Controllers/AdminAuctionController';
import { PortalLayout } from '@/components/portal-layout';
import type { AuctionRecord } from '@/types';

export default function AdminAuctionDetails({
    auction,
}: {
    auction: AuctionRecord;
}) {
    const cancellable = ['scheduled', 'live', 'offer_pending'].includes(
        auction.status,
    );

    return (
        <PortalLayout portal="admin" title="Auction details">
            <Head title={`Auction · ${auction.listing.title}`} />
            <div className="mx-auto max-w-4xl">
                <p className="text-sm font-semibold text-primary capitalize">
                    {auction.status.replaceAll('_', ' ')}
                </p>
                <h1 className="mt-1 text-2xl font-bold">
                    {auction.listing.title}
                </h1>
                <dl className="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-900">
                    <Detail
                        label="Seller"
                        value={
                            auction.listing.seller_profile?.store_name ??
                            'Seller'
                        }
                    />
                    <Detail
                        label="Type"
                        value={auction.type.replace('_', ' ')}
                    />
                    <Detail label="Lot" value={`${auction.quantity} unit(s)`} />
                    <Detail
                        label="Winning / current price"
                        value={`LKR ${auction.current_price ?? auction.starting_price}`}
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
                {cancellable && (
                    <Form
                        {...cancel.form(auction.id)}
                        className="mt-6 grid gap-3 rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-900 dark:bg-red-950/20"
                    >
                        {({ errors, processing }) => (
                            <>
                                <h2 className="font-semibold text-red-900 dark:text-red-200">
                                    Cancel unpaid auction
                                </h2>
                                <textarea
                                    name="reason"
                                    required
                                    minLength={10}
                                    placeholder="Required cancellation reason"
                                    className="min-h-28 rounded-xl border border-red-200 bg-white p-3 text-base dark:border-red-900 dark:bg-slate-950"
                                />
                                {errors.reason && (
                                    <p className="text-sm text-red-600">
                                        {errors.reason}
                                    </p>
                                )}
                                <button
                                    disabled={processing}
                                    className="justify-self-start rounded-xl bg-red-600 px-5 py-2.5 text-sm font-semibold text-white"
                                >
                                    Cancel auction and release stock
                                </button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </PortalLayout>
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
