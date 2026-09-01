import { Form } from '@inertiajs/react';
import { destroy } from '@/actions/App/Http/Controllers/WatchlistController';
import { ListingCard } from '@/components/listing-card';
import { StorefrontLayout } from '@/components/storefront-layout';
import type { StorefrontCategory, StorefrontListing } from '@/types';

export default function Watchlist({
    categories,
    listings,
}: {
    categories: StorefrontCategory[];
    listings: StorefrontListing[];
}) {
    return (
        <StorefrontLayout title="My wishlist" categories={categories}>
            <main className="mx-auto max-w-[96rem] px-4 py-8 sm:px-6">
                <h1 className="text-3xl font-black">My Wishlist</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Saved products tied to your account.
                </p>
                {listings.length === 0 ? (
                    <p className="mt-8 rounded-xl border border-dashed p-10 text-center text-sm text-slate-400">
                        Your wishlist is empty.
                    </p>
                ) : (
                    <div className="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-5">
                        {listings.map((listing) => (
                            <div key={listing.id}>
                                <ListingCard listing={listing} />
                                <Form
                                    {...destroy.form(listing.slug)}
                                    options={{ preserveScroll: true }}
                                >
                                    <button className="mt-2 w-full text-xs font-bold text-slate-500 hover:text-red-600">
                                        Remove
                                    </button>
                                </Form>
                            </div>
                        ))}
                    </div>
                )}
            </main>
        </StorefrontLayout>
    );
}
