import { Link } from '@inertiajs/react';
import { show as listingShow } from '@/routes/listings';

type Listing = {
    title: string;
    slug: string;
    condition: string;
    listingType: string;
    price: string | null;
    location: string;
    media: { path: string }[];
    auction: { currentPrice: string | null; endsAt: string | null } | null;
};

export function ListingCard({ listing }: { listing: Listing }) {
    const price = listing.auction?.currentPrice ?? listing.price;

    return (
        <Link
            href={listingShow(listing.slug)}
            className="group overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-stone-800 dark:bg-stone-900"
        >
            <div className="aspect-[4/3] bg-gradient-to-br from-amber-100 via-stone-100 to-stone-200 dark:from-amber-950 dark:via-stone-900 dark:to-stone-800">
                {listing.media[0] && (
                    <img
                        className="h-full w-full object-cover"
                        src={`/storage/${listing.media[0].path}`}
                        alt=""
                    />
                )}
            </div>
            <div className="space-y-3 p-4">
                <div className="flex items-center justify-between gap-2 text-xs font-bold tracking-wider text-stone-500 uppercase">
                    <span>{listing.condition}</span>
                    <span className="rounded-full bg-amber-100 px-2 py-1 text-amber-900 dark:bg-amber-900 dark:text-amber-100">
                        {listing.listingType === 'auction'
                            ? 'Auction'
                            : 'Buy now'}
                    </span>
                </div>
                <h2 className="line-clamp-2 font-bold group-hover:text-amber-700">
                    {listing.title}
                </h2>
                <div className="flex items-end justify-between gap-2">
                    <p className="text-lg font-black">
                        Rs. {Number(price ?? 0).toLocaleString()}
                    </p>
                    <p className="text-xs text-stone-500">{listing.location}</p>
                </div>
            </div>
        </Link>
    );
}
