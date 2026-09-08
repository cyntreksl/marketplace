import { Link, usePage } from '@inertiajs/react';
import { Zap } from 'lucide-react';
import { trackEvent } from '@/lib/tracking';
import { show as listingShow } from '@/routes/listings';
import type { StorefrontListing } from '@/types';

function formatPrice(value: string | null): string {
    if (!value) {
        return 'Contact seller';
    }

    return `Rs. ${Number(value).toLocaleString('en-LK')}`;
}

function ListingPrice({ value }: { value: string | null }) {
    if (!value) {
        return 'Contact seller';
    }

    return (
        <>
            <span className="mr-1 text-sm font-semibold tracking-normal">
                Rs.
            </span>
            {Number(value).toLocaleString('en-LK')}
        </>
    );
}

export function ListingCard({ listing }: { listing: StorefrontListing }) {
    const isWholesale = usePage().url.split('?')[0] === '/wholesale';
    const detailHref = listingShow(listing.slug, {
        query: isWholesale ? { wholesale: true } : {},
    });
    const image = listing.media[0] ?? null;
    const savings =
        listing.listingType === 'buy_now' &&
        listing.salePrice !== null &&
        listing.price !== null &&
        listing.effectivePrice !== null
            ? (Math.round(Number(listing.price) * 100) -
                  Math.round(Number(listing.effectivePrice) * 100)) /
              100
            : 0;
    const trackSelection = () =>
        trackEvent('select_item', {
            item_list_name: 'storefront',
            items: [
                {
                    item_id: String(listing.id),
                    item_name: listing.title,
                    price: Number(listing.effectivePrice ?? 0),
                },
            ],
        });

    return (
        <article className="group @container flex h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-orange-100/50">
            <div className="relative">
                {listing.stockStatus === 'out_of_stock' && (
                    <span className="absolute top-3 left-3 z-10 rounded-full bg-slate-900 px-3 py-1 text-xs font-bold text-white shadow-sm">
                        Out of stock
                    </span>
                )}
                <Link
                    href={detailHref}
                    onClick={trackSelection}
                    className="relative block aspect-[1.02/1] overflow-hidden bg-gradient-to-br from-white via-orange-50/50 to-slate-50"
                >
                    {image ? (
                        <img
                            src={image.cardUrl}
                            srcSet={`${image.cardUrl} 640w, ${image.card2xUrl} 1280w`}
                            sizes="(min-width: 1280px) 18vw, (min-width: 640px) 30vw, 70vw"
                            alt={listing.title}
                            loading="lazy"
                            className="size-full object-contain p-4 transition duration-300 group-hover:scale-[1.03]"
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center px-8 text-center text-sm text-slate-400">
                            Product image coming soon
                        </div>
                    )}
                </Link>
            </div>

            <div className="flex flex-1 flex-col p-3 @min-[180px]:p-4">
                <Link
                    href={detailHref}
                    onClick={trackSelection}
                    title={listing.title}
                    className="truncate text-sm leading-6 font-normal text-slate-900 transition hover:text-[#FF6D00] @min-[180px]:text-base"
                >
                    {listing.title}
                </Link>

                <div className="mt-2 flex flex-col gap-1">
                    <p className="text-[clamp(1rem,12cqi,1.5rem)] leading-8 font-bold tracking-tight break-words text-slate-950">
                        {isWholesale && listing.effectivePrice && 'From '}
                        <ListingPrice value={listing.effectivePrice} />
                        {isWholesale && listing.effectivePrice && (
                            <span className="ml-1 text-xs font-semibold tracking-normal text-slate-500">
                                /unit
                            </span>
                        )}
                    </p>
                    {isWholesale && (
                        <p className="text-xs font-bold text-[#FF6D00]">
                            {listing.wholesaleMinimumQuantity ?? 2}+ units
                        </p>
                    )}
                    {Number.isFinite(savings) && savings > 0 && (
                        <p className="flex items-start gap-1 text-xs leading-5 font-bold text-rose-600 @min-[180px]:text-sm">
                            <Zap
                                className="mt-0.5 size-3.5 shrink-0 fill-current"
                                aria-hidden="true"
                            />
                            <span>Save {formatPrice(savings.toString())}</span>
                        </p>
                    )}
                </div>

                {listing.listingType === 'auction' && (
                    <p className="mt-1 text-xs text-slate-500">Auction</p>
                )}
            </div>
        </article>
    );
}
