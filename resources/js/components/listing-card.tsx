import { Link, usePage } from '@inertiajs/react';
import { Zap } from 'lucide-react';
import { cn } from '@/lib/utils';
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

export function ListingCard({
    listing,
    fillHeightOnDesktop = false,
}: {
    listing: StorefrontListing;
    /** On lg+, shrink the image so the card fills its grid row instead of setting its height. */
    fillHeightOnDesktop?: boolean;
}) {
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

    const discountPct =
        listing.discountPercentage !== null &&
        listing.discountPercentage !== undefined &&
        listing.discountPercentage > 0
            ? Math.round(listing.discountPercentage)
            : null;

    const hasOriginalPrice =
        discountPct !== null &&
        listing.price !== null &&
        listing.effectivePrice !== null &&
        listing.price !== listing.effectivePrice;

    return (
        <article className="group @container flex h-full min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-orange-100/50">
            <div
                className={cn(
                    'relative',
                    fillHeightOnDesktop && 'lg:flex lg:min-h-0 lg:flex-1',
                )}
            >
                {listing.stockStatus === 'out_of_stock' && (
                    <span className="absolute top-3 left-3 z-10 rounded-full bg-slate-900 px-3 py-1 text-xs font-bold text-white shadow-sm">
                        Out of stock
                    </span>
                )}
                <Link
                    href={detailHref}
                    className={cn(
                        'relative block aspect-[1.02/1] overflow-hidden bg-gradient-to-br from-white via-orange-50/50 to-slate-50',
                        fillHeightOnDesktop &&
                            'lg:aspect-auto lg:min-h-0 lg:w-full lg:flex-1',
                    )}
                >
                    {image ? (
                        <img
                            src={image.cardUrl}
                            srcSet={`${image.cardUrl} 640w, ${image.card2xUrl} 1280w`}
                            sizes="(min-width: 1280px) 18vw, (min-width: 640px) 30vw, 70vw"
                            alt={listing.title}
                            loading="lazy"
                            className={cn(
                                'size-full object-contain p-4 transition duration-300 group-hover:scale-[1.03]',
                                fillHeightOnDesktop && 'lg:absolute lg:inset-0',
                            )}
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center px-8 text-center text-sm text-slate-400">
                            Product image coming soon
                        </div>
                    )}
                    {discountPct !== null &&
                        listing.stockStatus !== 'out_of_stock' && (
                            <div
                                className="absolute top-2 right-2 z-10 flex flex-col items-center justify-center rounded-md px-1.5 py-1 leading-none select-none"
                                style={{
                                    background:
                                        'linear-gradient(135deg, #dc2626 0%, #ea580c 100%)',
                                    minWidth: '2.75rem',
                                }}
                                aria-label={`${discountPct}% off`}
                            >
                                <span
                                    className="font-black text-white"
                                    style={{
                                        fontSize:
                                            'clamp(0.7rem, 4.5cqi, 0.875rem)',
                                    }}
                                >
                                    {discountPct}%
                                </span>
                                <span
                                    className="font-bold tracking-widest text-orange-100 uppercase"
                                    style={{
                                        fontSize:
                                            'clamp(0.4rem, 2cqi, 0.55rem)',
                                    }}
                                >
                                    off
                                </span>
                            </div>
                        )}
                </Link>
            </div>

            <div
                className={cn(
                    'flex flex-1 flex-col p-3 @min-[180px]:p-4',
                    fillHeightOnDesktop && 'lg:flex-none',
                )}
            >
                <Link
                    href={detailHref}
                    title={listing.title}
                    className="truncate text-sm leading-6 font-normal text-slate-900 transition hover:text-[#FF6D00] @min-[180px]:text-base"
                >
                    {listing.title}
                </Link>

                <div className="mt-2 flex flex-col gap-0.5">
                    <div className="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                        <p className="text-[clamp(1rem,12cqi,1.5rem)] leading-8 font-bold tracking-tight text-slate-950">
                            {isWholesale && listing.effectivePrice && 'From '}
                            <ListingPrice value={listing.effectivePrice} />
                            {isWholesale && listing.effectivePrice && (
                                <span className="ml-1 text-xs font-semibold tracking-normal text-slate-500">
                                    /unit
                                </span>
                            )}
                        </p>
                        {hasOriginalPrice && (
                            <p className="text-xs leading-none font-medium text-slate-400 line-through">
                                {formatPrice(listing.price)}
                            </p>
                        )}
                    </div>
                    {isWholesale && (
                        <p className="text-xs font-bold text-[#FF6D00]">
                            {listing.wholesaleMinimumQuantity ?? 2}+ units
                        </p>
                    )}
                    {Number.isFinite(savings) && savings > 0 && (
                        <p className="flex items-center gap-1 text-xs leading-5 font-bold text-rose-600 @min-[180px]:text-sm">
                            <Zap
                                className="size-3.5 shrink-0 fill-current"
                                aria-hidden="true"
                            />
                            <span>
                                You save {formatPrice(savings.toString())}
                            </span>
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
