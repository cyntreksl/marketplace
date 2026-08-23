import { Form, Link, usePage } from '@inertiajs/react';
import { store as addCartItem } from '@/actions/App/Http/Controllers/CartController';
import { StorefrontLayout } from '@/components/storefront-layout';
import { login } from '@/routes';

export default function ListingShow({ listing }: { listing: any }) {
    const price = listing.auction?.currentPrice ?? listing.price;
    const { auth } = usePage().props;

    return (
        <StorefrontLayout title={listing.title}>
            <main className="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div className="aspect-square overflow-hidden rounded-3xl bg-gradient-to-br from-blue-100 via-slate-100 to-cyan-100 shadow-lg shadow-blue-950/5 dark:from-blue-950 dark:via-slate-900 dark:to-slate-800">
                    {listing.media[0] && (
                        <img
                            className="h-full w-full object-cover"
                            src={`/storage/${listing.media[0].path}`}
                            alt={listing.title}
                        />
                    )}
                </div>
                <div>
                    <p className="text-sm font-bold tracking-wider text-blue-700 uppercase dark:text-blue-300">
                        {listing.listingType === 'auction'
                            ? 'Live auction'
                            : 'Buy now'}{' '}
                        · {listing.condition}
                    </p>
                    <h1 className="mt-3 text-4xl font-black tracking-tight">
                        {listing.title}
                    </h1>
                    <p className="mt-5 text-lg leading-8 text-slate-600 dark:text-slate-300">
                        {listing.description}
                    </p>
                    <div className="mt-8 rounded-2xl border border-blue-100 bg-blue-50/70 p-6 dark:border-slate-800 dark:bg-slate-900">
                        <p className="text-sm font-medium text-slate-500">
                            {listing.auction ? 'Current bid' : 'Price'}
                        </p>
                        <p className="mt-1 text-4xl font-black text-blue-700 dark:text-blue-300">
                            Rs. {Number(price ?? 0).toLocaleString()}
                        </p>
                        {listing.auction && (
                            <p className="mt-2 text-sm text-slate-500">
                                {listing.auction.bidCount} bids · Ends{' '}
                                {new Date(
                                    listing.auction.endsAt,
                                ).toLocaleString()}
                            </p>
                        )}
                        {!auth.user ? (
                            <Link
                                href={login()}
                                className="mt-6 inline-flex rounded-xl bg-blue-600 px-5 py-3 font-bold text-white shadow-sm shadow-blue-600/25 transition hover:bg-blue-700"
                            >
                                {listing.auction
                                    ? 'Sign in to bid'
                                    : 'Sign in to buy'}
                            </Link>
                        ) : listing.auction ? (
                            <p className="mt-6 rounded-xl bg-slate-950 px-5 py-3 text-center font-bold text-white dark:bg-slate-50 dark:text-slate-950">
                                Bidding is available from the auction panel.
                            </p>
                        ) : (
                            <Form {...addCartItem.form()} className="mt-6">
                                {({ processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="listing_id"
                                            value={listing.id}
                                        />
                                        <input
                                            type="hidden"
                                            name="quantity"
                                            value="1"
                                        />
                                        <button
                                            disabled={processing}
                                            className="rounded-xl bg-blue-600 px-5 py-3 font-bold text-white shadow-sm shadow-blue-600/25 transition hover:bg-blue-700 disabled:opacity-50"
                                        >
                                            {processing
                                                ? 'Adding…'
                                                : 'Add to cart'}
                                        </button>
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                    <dl className="mt-8 grid grid-cols-2 gap-5 text-sm">
                        <div>
                            <dt className="text-slate-500">Seller</dt>
                            <dd className="font-bold">
                                {listing.seller.store_name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Location</dt>
                            <dd className="font-bold">{listing.location}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Warranty</dt>
                            <dd className="font-bold">
                                {listing.warranty ?? 'Not specified'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Availability</dt>
                            <dd className="font-bold">
                                {listing.stockQuantity} available
                            </dd>
                        </div>
                    </dl>
                </div>
            </main>
        </StorefrontLayout>
    );
}
