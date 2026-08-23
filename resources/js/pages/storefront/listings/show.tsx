import { Link } from '@inertiajs/react';
import { StorefrontLayout } from '@/components/storefront-layout';
import { login } from '@/routes';

export default function ListingShow({ listing }: { listing: any }) {
    const price = listing.auction?.currentPrice ?? listing.price;

    return (
        <StorefrontLayout title={listing.title}>
            <main className="mx-auto grid max-w-7xl gap-10 px-4 py-10 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div className="aspect-square overflow-hidden rounded-3xl bg-gradient-to-br from-amber-100 to-stone-200 dark:from-amber-950 dark:to-stone-800">
                    {listing.media[0] && (
                        <img
                            className="h-full w-full object-cover"
                            src={`/storage/${listing.media[0].path}`}
                            alt={listing.title}
                        />
                    )}
                </div>
                <div>
                    <p className="text-sm font-bold tracking-wider text-amber-700 uppercase">
                        {listing.listingType === 'auction'
                            ? 'Live auction'
                            : 'Buy now'}{' '}
                        · {listing.condition}
                    </p>
                    <h1 className="mt-3 text-4xl font-black tracking-tight">
                        {listing.title}
                    </h1>
                    <p className="mt-5 text-lg leading-8 text-stone-600 dark:text-stone-300">
                        {listing.description}
                    </p>
                    <div className="mt-8 rounded-2xl bg-stone-100 p-6 dark:bg-stone-900">
                        <p className="text-sm font-medium text-stone-500">
                            {listing.auction ? 'Current bid' : 'Price'}
                        </p>
                        <p className="mt-1 text-4xl font-black">
                            Rs. {Number(price ?? 0).toLocaleString()}
                        </p>
                        {listing.auction && (
                            <p className="mt-2 text-sm text-stone-500">
                                {listing.auction.bidCount} bids · Ends{' '}
                                {new Date(
                                    listing.auction.endsAt,
                                ).toLocaleString()}
                            </p>
                        )}
                        <Link
                            href={login()}
                            className="mt-6 inline-flex rounded-full bg-amber-400 px-5 py-3 font-bold text-stone-950"
                        >
                            {listing.auction
                                ? 'Sign in to bid'
                                : 'Sign in to buy'}
                        </Link>
                    </div>
                    <dl className="mt-8 grid grid-cols-2 gap-5 text-sm">
                        <div>
                            <dt className="text-stone-500">Seller</dt>
                            <dd className="font-bold">
                                {listing.seller.store_name}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-stone-500">Location</dt>
                            <dd className="font-bold">{listing.location}</dd>
                        </div>
                        <div>
                            <dt className="text-stone-500">Warranty</dt>
                            <dd className="font-bold">
                                {listing.warranty ?? 'Not specified'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-stone-500">Availability</dt>
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
