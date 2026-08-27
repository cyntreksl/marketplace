import { Link } from '@inertiajs/react';
import { ArrowUpRight, MapPin } from 'lucide-react';
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
            className="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-200/50 transition duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-xl hover:shadow-primary/10 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none"
        >
            <div className="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-primary/20 via-slate-100 to-primary/10 dark:from-primary/20 dark:via-slate-900 dark:to-slate-800">
                {listing.media[0] && (
                    <img
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                        src={`/storage/${listing.media[0].path}`}
                        alt=""
                    />
                )}
                <span className="absolute top-3 left-3 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-extrabold tracking-wider text-primary uppercase shadow-sm backdrop-blur dark:bg-slate-950/80">
                    {listing.listingType === 'auction'
                        ? 'Live auction'
                        : 'Buy now'}
                </span>
            </div>
            <div className="space-y-3 p-4">
                <div className="flex items-center justify-between gap-2 text-xs font-bold tracking-wider text-slate-500 uppercase">
                    <span>{listing.condition}</span>
                    <ArrowUpRight className="size-4 text-primary transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                </div>
                <h2 className="line-clamp-2 min-h-10 font-bold text-slate-900 transition group-hover:text-primary dark:text-white">
                    {listing.title}
                </h2>
                <div className="flex items-end justify-between gap-2">
                    <p className="text-lg font-black text-primary">
                        Rs. {Number(price ?? 0).toLocaleString()}
                    </p>
                    <p className="flex items-center gap-1 text-xs text-slate-500">
                        <MapPin className="size-3" />
                        {listing.location}
                    </p>
                </div>
            </div>
        </Link>
    );
}
