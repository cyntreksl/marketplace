import { Head } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/SellerAuctionController';
import { AuctionForm } from '@/components/auction-form';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import type {
    AuctionFlags,
    AuctionRecord,
    SellerAuctionListing,
} from '@/types';

export default function EditAuction(props: {
    auction: AuctionRecord;
    listings: SellerAuctionListing[];
    flags: AuctionFlags;
    defaults: {
        durationDays: number;
        extensionMinutes: number;
        startsAt: string;
        endsAt: string;
    };
}) {
    return (
        <SellerPortalLayout title="Edit auction">
            <Head title="Edit Auction" />
            <div className="mx-auto max-w-5xl">
                <h1 className="mb-6 text-2xl font-bold">Edit auction draft</h1>
                <AuctionForm
                    action={update.form(props.auction.id)}
                    {...props}
                />
            </div>
        </SellerPortalLayout>
    );
}
