import { Form, Head, Link } from '@inertiajs/react';
import {
    create,
    edit,
    submit,
} from '@/actions/App/Http/Controllers/SellerListingController';
import { PortalLayout } from '@/components/portal-layout';

type Listing = {
    id: number;
    title: string;
    status: string;
    moderation_reason: string | null;
    listing_type: string;
    price: string | null;
    created_at: string;
    category: { name: string };
    auction: { status: string; ends_at: string } | null;
};

export default function SellerListings({
    sellerStatus,
    listings,
}: {
    sellerStatus: string;
    listings: { data: Listing[] };
}) {
    return (
        <PortalLayout portal="seller" title="Your listings">
            <Head title="Seller listings" />
            <main className="mx-auto max-w-7xl">
                <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-bold tracking-wider text-primary uppercase">
                            Seller portal
                        </p>
                        <h1 className="mt-2 text-4xl font-black">
                            Your listings
                        </h1>
                        <p className="mt-2 text-stone-600 dark:text-stone-300">
                            Account status:{' '}
                            <span className="font-bold capitalize">
                                {sellerStatus.replace('_', ' ')}
                            </span>
                        </p>
                    </div>
                    <Link
                        href={create()}
                        className="rounded-xl bg-primary px-5 py-3 text-center font-bold text-primary-foreground"
                    >
                        Create listing
                    </Link>
                </div>

                {sellerStatus !== 'approved' && sellerStatus !== 'active' && (
                    <p className="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                        You can prepare drafts now. Your account must be
                        approved before you submit a listing for review.
                    </p>
                )}

                <div className="mt-8 overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
                    {listings.data.length === 0 ? (
                        <div className="p-12 text-center text-stone-500">
                            No listings yet. Add your first item when you are
                            ready.
                        </div>
                    ) : (
                        <ul className="divide-y divide-stone-200 dark:divide-stone-800">
                            {listings.data.map((listing) => (
                                <li
                                    key={listing.id}
                                    className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-bold">
                                            {listing.title}
                                        </p>
                                        <p className="mt-1 text-sm text-stone-500">
                                            {listing.category.name} ·{' '}
                                            {listing.listing_type === 'auction'
                                                ? 'Auction'
                                                : `LKR ${listing.price}`}
                                        </p>
                                        {listing.moderation_reason && (
                                            <p className="mt-2 text-sm text-amber-800 dark:text-amber-200">
                                                Review note:{' '}
                                                {listing.moderation_reason}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-bold capitalize dark:bg-stone-800">
                                            {listing.status.replace('_', ' ')}
                                        </span>
                                        {[
                                            'draft',
                                            'changes_requested',
                                            'rejected',
                                        ].includes(listing.status) && (
                                            <Link
                                                href={edit(listing.id)}
                                                className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold dark:border-stone-700"
                                            >
                                                Edit
                                            </Link>
                                        )}
                                        {[
                                            'draft',
                                            'changes_requested',
                                            'rejected',
                                        ].includes(listing.status) && (
                                            <Form {...submit.form()}>
                                                {({ processing }) => (
                                                    <>
                                                        <input
                                                            type="hidden"
                                                            name="listing_id"
                                                            value={listing.id}
                                                        />
                                                        <button
                                                            disabled={
                                                                processing ||
                                                                (sellerStatus !==
                                                                    'approved' &&
                                                                    sellerStatus !==
                                                                        'active')
                                                            }
                                                            className="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-40"
                                                        >
                                                            Submit review
                                                        </button>
                                                    </>
                                                )}
                                            </Form>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </main>
        </PortalLayout>
    );
}
