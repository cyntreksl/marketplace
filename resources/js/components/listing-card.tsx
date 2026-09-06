import { Form, Link } from '@inertiajs/react';
import {
    Heart,
    ShieldCheck,
    ShoppingCart,
    Sparkles,
    Truck,
} from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { store as addCartItem } from '@/routes/cart/items';
import { show as listingShow } from '@/routes/listings';
import type { StorefrontListing } from '@/types';

function formatPrice(value: string | null): string {
    if (!value) {
        return 'Contact seller';
    }

    return `Rs. ${Number(value).toLocaleString('en-LK')}`;
}

function listingBadge(listing: StorefrontListing): {
    label: string;
    variant: 'default' | 'warning' | 'dark';
} {
    if (listing.listingType === 'auction') {
        return { label: 'AUCTION', variant: 'dark' };
    }

    if (listing.stockStatus === 'low_stock') {
        return { label: 'LIMITED STOCK', variant: 'warning' };
    }

    if (listing.discountPercentage !== null) {
        return {
            label: `${listing.discountPercentage}% OFF`,
            variant: 'default',
        };
    }

    return { label: 'BEST VALUE', variant: 'dark' };
}

export function ListingCard({ listing }: { listing: StorefrontListing }) {
    const badge = listingBadge(listing);
    const image = listing.media[0] ?? null;
    const rating = listing.ratingAverage?.toFixed(1) ?? '4.8';

    return (
        <article className="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-orange-100/50">
            <div className="relative">
                <Link
                    href={listingShow(listing.slug)}
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

                <div className="absolute top-3 left-3">
                    <Badge
                        className={`rounded-full px-2.5 py-1 text-[10px] font-black tracking-wide ${
                            badge.variant === 'default'
                                ? 'bg-[#FF6D00] text-white hover:bg-[#FF6D00]'
                                : badge.variant === 'warning'
                                  ? 'bg-orange-100 text-[#FF6D00] hover:bg-orange-100'
                                  : 'bg-slate-950 text-white hover:bg-slate-950'
                        }`}
                    >
                        {badge.label}
                    </Badge>
                </div>

                <button
                    type="button"
                    aria-label={`Add ${listing.title} to wishlist`}
                    className="absolute top-3 right-3 grid size-8 place-items-center rounded-full border border-slate-200 bg-white/95 text-slate-500 shadow-sm backdrop-blur transition hover:border-[#FF6D00] hover:text-[#FF6D00]"
                >
                    <Heart className="size-4" />
                </button>
            </div>

            <div className="flex flex-1 flex-col p-4">
                <Link
                    href={listingShow(listing.slug)}
                    className="line-clamp-2 min-h-11 text-sm leading-5 font-semibold text-slate-900 transition hover:text-[#FF6D00]"
                >
                    {listing.title}
                </Link>

                <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span className="flex items-center gap-1 font-semibold text-amber-500">
                        <Sparkles className="size-3.5 fill-current" />
                        {rating}
                    </span>
                    <span>({listing.reviewCount})</span>
                    <span className="text-slate-300">•</span>
                    <span>{listing.location}</span>
                </div>

                <div className="mt-3 flex items-end justify-between gap-3">
                    <div>
                        <p className="text-[11px] text-slate-400 line-through">
                            {listing.salePrice
                                ? formatPrice(listing.price)
                                : ''}
                        </p>
                        <p className="text-lg font-black tracking-tight text-[#FF6D00]">
                            {formatPrice(listing.effectivePrice)}
                        </p>
                    </div>
                    {listing.discountPercentage !== null && (
                        <span className="rounded-full bg-orange-50 px-2.5 py-1 text-[10px] font-black text-[#FF6D00]">
                            {listing.discountPercentage}% OFF
                        </span>
                    )}
                </div>

                <div className="mt-3 flex flex-wrap gap-2 text-[11px] font-medium text-slate-500">
                    <span className="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1">
                        <ShieldCheck className="size-3.5 text-[#FF6D00]" />
                        Official warranty
                    </span>
                    <span className="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2.5 py-1">
                        <Truck className="size-3.5 text-[#FF6D00]" />
                        Islandwide delivery
                    </span>
                </div>

                {listing.listingType === 'auction' ||
                listing.productType === 'variant' ? (
                    <Button
                        asChild
                        variant="outline"
                        className="mt-4 h-10 rounded-xl"
                    >
                        <Link href={listingShow(listing.slug)}>
                            {listing.listingType === 'auction'
                                ? 'View auction'
                                : 'Choose options'}
                        </Link>
                    </Button>
                ) : (
                    <Form
                        {...addCartItem.form()}
                        className="mt-4"
                        onError={(errors) =>
                            toast.error(
                                Object.values(errors)[0] ??
                                    'Unable to add this item.',
                            )
                        }
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
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={
                                        processing ||
                                        listing.stockStatus === 'out_of_stock'
                                    }
                                    className="h-10 w-full rounded-xl border-orange-200 font-bold text-orange-600"
                                >
                                    <ShoppingCart className="size-4" />
                                    {listing.stockStatus === 'out_of_stock'
                                        ? 'Out of stock'
                                        : 'Add to Cart'}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </article>
    );
}
