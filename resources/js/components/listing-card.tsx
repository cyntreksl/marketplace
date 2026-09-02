import { Form, Link, usePage } from '@inertiajs/react';
import { Gavel, ShoppingCart, Star } from 'lucide-react';
import { store as addCartItem } from '@/actions/App/Http/Controllers/CartController';
import { login } from '@/routes';
import { show as listingShow } from '@/routes/listings';
import type { StorefrontListing } from '@/types';

function formatPrice(value: string | null): string {
    return `Rs. ${Number(value ?? 0).toLocaleString('en-LK')}`;
}

export function ListingCard({
    listing,
    compact = false,
}: {
    listing: StorefrontListing;
    compact?: boolean;
}) {
    const { auth } = usePage().props;
    const detailUrl = listingShow(listing.slug);
    const needsOptions = listing.productType === 'variant';

    return (
        <article className="group flex h-full min-w-0 flex-col rounded-xl border border-slate-200 bg-white p-2.5 transition hover:border-orange-200 hover:shadow-lg hover:shadow-orange-100/60 sm:p-3">
            <Link
                href={detailUrl}
                className="relative block aspect-square overflow-hidden rounded-lg bg-white"
            >
                {listing.media[0] ? (
                    <img
                        src={listing.media[0].cardUrl}
                        srcSet={`${listing.media[0].cardUrl} 640w, ${listing.media[0].card2xUrl} 1280w`}
                        sizes="(min-width: 1280px) 18vw, (min-width: 640px) 30vw, 70vw"
                        alt={listing.title}
                        loading="lazy"
                        className={`size-full object-contain transition duration-300 group-hover:scale-105 ${compact ? 'p-1.5' : 'p-2'}`}
                    />
                ) : (
                    <span className="grid size-full place-items-center px-4 text-center text-xs font-semibold text-slate-400">
                        Product image coming soon
                    </span>
                )}
                {listing.discountPercentage !== null && (
                    <span className="absolute top-2 right-2 rounded-full bg-orange-50 px-2 py-1 text-[10px] font-extrabold text-[#ff5a00]">
                        {listing.discountPercentage}% OFF
                    </span>
                )}
                {listing.listingType === 'auction' && (
                    <span className="absolute top-2 left-2 rounded-full bg-slate-950 px-2 py-1 text-[10px] font-bold text-white">
                        AUCTION
                    </span>
                )}
            </Link>

            <Link
                href={detailUrl}
                className="mt-3 line-clamp-2 min-h-10 text-sm leading-5 font-medium text-slate-800 hover:text-[#ff5a00]"
            >
                {listing.title}
            </Link>
            <div className="mt-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <span className="text-sm font-extrabold text-slate-950">
                    {formatPrice(listing.effectivePrice)}
                </span>
                {listing.salePrice && (
                    <span className="text-[10px] text-slate-400 line-through">
                        {formatPrice(listing.price)}
                    </span>
                )}
            </div>
            <div className="mt-auto flex items-end justify-between gap-2 pt-2">
                <div>
                    <span className="flex items-center gap-1 text-[11px] font-bold text-amber-500">
                        <Star className="size-3 fill-current" />
                        {listing.ratingAverage?.toFixed(1) ?? 'New'}
                        {listing.reviewCount > 0 && (
                            <span className="font-normal text-slate-400">
                                ({listing.reviewCount})
                            </span>
                        )}
                    </span>
                    <span
                        className={`mt-1 block text-[10px] font-semibold ${listing.stockStatus === 'out_of_stock' ? 'text-red-500' : 'text-emerald-600'}`}
                    >
                        {listing.stockStatus === 'out_of_stock'
                            ? 'Out of stock'
                            : listing.stockStatus === 'low_stock'
                              ? 'Limited stock'
                              : 'In stock'}
                    </span>
                </div>
                {listing.listingType === 'auction' ? (
                    <Link
                        href={detailUrl}
                        aria-label={`Bid on ${listing.title}`}
                        className="grid size-9 shrink-0 place-items-center rounded-full border border-slate-200 text-slate-700 hover:border-[#ff5a00] hover:text-[#ff5a00]"
                    >
                        <Gavel className="size-4" />
                    </Link>
                ) : !auth.user || needsOptions ? (
                    <Link
                        href={!auth.user ? login() : detailUrl}
                        aria-label={
                            needsOptions
                                ? `Choose options for ${listing.title}`
                                : 'Sign in to add to cart'
                        }
                        className="grid size-9 shrink-0 place-items-center rounded-full border border-slate-200 text-slate-700 hover:border-[#ff5a00] hover:text-[#ff5a00]"
                    >
                        <ShoppingCart className="size-4" />
                    </Link>
                ) : (
                    <Form
                        {...addCartItem.form()}
                        options={{ preserveScroll: true }}
                    >
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
                                    type="submit"
                                    disabled={
                                        processing ||
                                        listing.stockStatus === 'out_of_stock'
                                    }
                                    aria-label={`Add ${listing.title} to cart`}
                                    className="grid size-9 shrink-0 place-items-center rounded-full border border-slate-200 text-slate-700 hover:border-[#ff5a00] hover:text-[#ff5a00] disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    <ShoppingCart className="size-4" />
                                </button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </article>
    );
}
