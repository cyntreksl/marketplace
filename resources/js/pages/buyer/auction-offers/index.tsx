import { Head, Link } from '@inertiajs/react';
import { Gavel } from 'lucide-react';
import { BuyerPortalLayout } from '@/components/buyer-portal-layout';
import { show } from '@/routes/buyer/auction-offers';
import type { AuctionOffer } from '@/types';

export default function BuyerAuctionOffers({
    offers,
}: {
    offers: { data: AuctionOffer[] };
}) {
    return (
        <BuyerPortalLayout title="Auction offers">
            <Head title="Auction Offers" />
            <h1 className="text-2xl font-bold">Auction offers</h1>
            <p className="mt-1 text-sm text-slate-500">
                Winning offers stay available for exactly 24 hours.
            </p>
            <div className="mt-6 grid gap-3">
                {offers.data.map((offer) => (
                    <Link
                        key={offer.id}
                        href={show(offer.id)}
                        className="grid gap-2 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-[1fr_auto] dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div>
                            <p className="font-semibold">
                                {offer.auction.listing.title}
                            </p>
                            <p className="mt-1 text-sm text-slate-500">
                                LKR {offer.unit_price} × {offer.quantity}{' '}
                                unit(s)
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-sm font-semibold capitalize">
                                {offer.status}
                            </p>
                            {offer.expires_at && (
                                <p className="mt-1 text-xs text-slate-500">
                                    Expires{' '}
                                    {new Date(
                                        offer.expires_at,
                                    ).toLocaleString()}
                                </p>
                            )}
                        </div>
                    </Link>
                ))}
                {offers.data.length === 0 && (
                    <div className="rounded-2xl border border-dashed p-12 text-center text-slate-500">
                        <Gavel className="mx-auto mb-3 size-8" />
                        You do not have any auction offers.
                    </div>
                )}
            </div>
        </BuyerPortalLayout>
    );
}
