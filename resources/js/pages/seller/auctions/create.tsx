import { Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/SellerAuctionController';
import { AuctionForm } from '@/components/auction-form';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import type { AuctionFlags, SellerAuctionListing } from '@/types';

export default function CreateAuction(props: {
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
        <SellerPortalLayout title="Create auction">
            <Head title="Create Auction" />
            <div className="mx-auto max-w-5xl">
                <h1 className="mb-6 text-2xl font-bold">Create an auction</h1>
                <AuctionForm action={store.form()} {...props} />
            </div>
        </SellerPortalLayout>
    );
}
