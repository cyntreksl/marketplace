import { Form, Link, usePage } from '@inertiajs/react';
import {
    Check,
    GitCompareArrows,
    Heart,
    Share2,
    Star,
    Truck,
    ShieldCheck,
    RotateCcw,
    CreditCard,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { store as placeBid } from '@/actions/App/Http/Controllers/AuctionBidController';
import {
    destroy as removeWish,
    store as addWish,
} from '@/actions/App/Http/Controllers/WatchlistController';
import { ListingCard } from '@/components/listing-card';
import { ProductDetails } from '@/components/product-details';
import { ProductGallery } from '@/components/product-gallery';
import { ProductPurchase } from '@/components/product-purchase';
import { SellerSummary } from '@/components/seller-summary';
import { StorefrontBreadcrumbs } from '@/components/storefront-breadcrumbs';
import { StorefrontLayout } from '@/components/storefront-layout';
import { useProductComparison } from '@/hooks/use-product-comparison';
import { buildCatalogItem, trackEvent } from '@/lib/tracking';
import { home, login } from '@/routes';
import { show as brandShow } from '@/routes/brands';
import { show as categoryShow } from '@/routes/categories';
import { index as listingsIndex } from '@/routes/listings';
import { show as listingShow } from '@/routes/listings';
import { shipping, returns } from '@/routes/policies';
import { show as storeShow } from '@/routes/stores';
import type {
    PublicSellerSummary,
    StorefrontCategory,
    StorefrontCategoryNode,
    StorefrontListing,
    StorefrontReview,
} from '@/types';

const recentStorageKey = 'prodeals.recentlyViewedListingIds';
type Question = {
    id: number;
    question: string;
    answer: string | null;
    askedBy: string;
    answeredBy: string | null;
    answeredAt: string | null;
};
type Campaign = { title: string; subtitle: string | null; endsAt: string };
type Policies = { returnWindowDays: number; codEnabled: boolean } | null;

function formatPrice(value: string | null): string {
    return `Rs. ${Number(value ?? 0).toLocaleString('en-LK')}`;
}

function OfferCountdown({ endsAt }: { endsAt: string }) {
    const [remaining, setRemaining] = useState(0);
    useEffect(() => {
        const update = () =>
            setRemaining(Math.max(0, new Date(endsAt).getTime() - Date.now()));
        const initialTimer = window.setTimeout(update, 0);
        const timer = window.setInterval(update, 1000);

        return () => {
            window.clearTimeout(initialTimer);
            window.clearInterval(timer);
        };
    }, [endsAt]);
    const total = Math.floor(remaining / 1000);

    return (
        <div className="flex items-center gap-1">
            {[
                Math.floor(total / 3600),
                Math.floor((total % 3600) / 60),
                total % 60,
            ].map((value, index) => (
                <span
                    key={index}
                    className="rounded border bg-white px-2 py-1 text-sm font-black"
                >
                    {String(value).padStart(2, '0')}
                </span>
            ))}
        </div>
    );
}

export default function ListingShow({
    listing,
    categories,
    categoryTrail,
    reviews,
    questions,
    pendingQuestions,
    isWishlisted,
    activeCampaign,
    categoryPolicies,
    relatedListings,
    sellerListings,
    selectedVariantId,
    sellerSummary,
    purchaseContext,
}: {
    listing: StorefrontListing;
    categories: StorefrontCategory[];
    categoryTrail: StorefrontCategoryNode[];
    reviews: StorefrontReview[];
    questions: Question[];
    pendingQuestions: Question[];
    isWishlisted: boolean;
    activeCampaign: Campaign | null;
    categoryPolicies: Policies;
    relatedListings: StorefrontListing[];
    sellerListings: StorefrontListing[];
    selectedVariantId: number | null;
    sellerSummary: PublicSellerSummary | null;
    purchaseContext: {
        channel: 'retail' | 'wholesale';
        initialQuantity: number;
    };
}) {
    const { auth, reviewFlags } = usePage().props;
    const comparison = useProductComparison();
    const [quantity, setQuantity] = useState(purchaseContext.initialQuantity);
    const initialVariant = listing.variants.find(
        (variant) => variant.id === selectedVariantId,
    );
    const [selections, setSelections] = useState<Record<string, string>>(
        initialVariant?.selections ?? {},
    );
    const selectedVariant = useMemo(
        () =>
            listing.variants.find((variant) =>
                Object.entries(variant.selections).every(
                    ([name, value]) => selections[name] === value,
                ),
            ),
        [listing.variants, selections],
    );
    const wholesaleTiers =
        selectedVariant === undefined
            ? listing.wholesaleTiers
            : selectedVariant.wholesaleTiers;
    const wholesaleMinimum = wholesaleTiers[0]?.minimumQuantity ?? null;
    const appliedWholesaleTier = [...wholesaleTiers]
        .reverse()
        .find((tier) => quantity >= tier.minimumQuantity);
    const lowestWholesaleTier = [...wholesaleTiers].sort(
        (first, second) =>
            Number(first.unitPrice) - Number(second.unitPrice) ||
            first.minimumQuantity - second.minimumQuantity,
    )[0];
    const retailPrice =
        selectedVariant?.sellingPrice ?? listing.salePrice ?? listing.price;
    const isWholesaleQuantity = Boolean(
        listing.wholesaleEnabled && appliedWholesaleTier,
    );
    const displayedSellingPrice = isWholesaleQuantity
        ? (appliedWholesaleTier?.unitPrice ?? null)
        : retailPrice;
    const displayedMarketPrice = selectedVariant
        ? selectedVariant.marketPrice
        : listing.salePrice
          ? listing.price
          : null;
    const displayedDiscountPercentage =
        displayedMarketPrice && displayedSellingPrice
            ? Math.round(
                  ((Number(displayedMarketPrice) -
                      Number(displayedSellingPrice)) /
                      Number(displayedMarketPrice)) *
                      100,
              )
            : null;
    const canPurchase =
        !listing.retailEnabled &&
        (!wholesaleMinimum || quantity < wholesaleMinimum)
            ? false
            : listing.productType === 'simple'
              ? listing.stockStatus !== 'out_of_stock' &&
                (listing.stockStatus === 'backorder' ||
                    listing.stockQuantity >= quantity)
              : Boolean(
                    selectedVariant &&
                    selectedVariant.stockQuantity >= quantity,
                );
    const isOutOfStock =
        listing.stockStatus === 'out_of_stock' ||
        (listing.productType === 'variant' &&
            selectedVariant?.stockQuantity === 0);
    useEffect(() => {
        trackEvent('view_item', {
            currency: 'LKR',
            value: Number(displayedSellingPrice ?? 0),
            items: [
                buildCatalogItem(listing.id, selectedVariant?.id, {
                    item_name: listing.title,
                    item_brand: listing.brand?.name,
                    item_category: listing.category?.name,
                    price: Number(displayedSellingPrice ?? 0),
                }),
            ],
        });
    }, [
        displayedSellingPrice,
        listing.brand?.name,
        listing.category?.name,
        listing.id,
        listing.title,
        selectedVariant?.id,
    ]);

    useEffect(() => {
        const url = listingShow.url(listing.slug, {
            query: selectedVariant
                ? { variant: selectedVariant.id }
                : undefined,
        });
        window.history.replaceState(
            window.history.state,
            '',
            url + window.location.hash,
        );
    }, [listing.slug, selectedVariant]);

    useEffect(() => {
        try {
            const stored = JSON.parse(
                window.localStorage.getItem(recentStorageKey) ?? '[]',
            );
            const ids = Array.isArray(stored)
                ? stored.filter((id): id is number => Number.isInteger(id))
                : [];
            window.localStorage.setItem(
                recentStorageKey,
                JSON.stringify(
                    [
                        listing.id,
                        ...ids.filter((id) => id !== listing.id),
                    ].slice(0, 12),
                ),
            );
        } catch {
            window.localStorage.setItem(
                recentStorageKey,
                JSON.stringify([listing.id]),
            );
        }
    }, [listing.id]);

    const share = async () => {
        const data = { title: listing.title, url: window.location.href };

        try {
            if (navigator.share) {
                await navigator.share(data);
            } else {
                await navigator.clipboard.writeText(window.location.href);
                toast.success('Product link copied.');
            }
        } catch (error) {
            if (!(
                error instanceof DOMException && error.name === 'AbortError'
            )) {
                toast.error('The product link could not be shared.');
            }
        }
    };

    const purchaseProps = {
        listingId: listing.id,
        variantId: selectedVariant?.id,
        stockLimit:
            listing.productType === 'variant'
                ? (selectedVariant?.stockQuantity ?? 100)
                : listing.stockStatus === 'backorder'
                  ? 100000
                  : listing.stockQuantity,
        quantity,
        setQuantity,
        canPurchase,
        isOutOfStock,
        needsVariant:
            listing.productType === 'variant' && selectedVariant === undefined,
        unitPrice: displayedSellingPrice ?? 0,
        minimumQuantity: listing.retailEnabled ? 1 : (wholesaleMinimum ?? 2),
    };
    const offerSummary = (
        <div className="overflow-hidden rounded-xl border border-rose-100 bg-gradient-to-br from-rose-50 via-white to-orange-50 shadow-[0_8px_24px_-20px_rgba(244,63,94,0.8)]">
            {activeCampaign && (
                <div className="flex flex-wrap items-center justify-between gap-2 bg-gradient-to-r from-[#ff334f] to-[#ff6d00] px-3 py-2 text-sm font-bold text-white sm:px-4">
                    <span>{activeCampaign.title}</span>
                    <span className="flex items-center gap-2">
                        Ends in{' '}
                        <OfferCountdown endsAt={activeCampaign.endsAt} />
                    </span>
                </div>
            )}
            <div className="p-3 sm:p-4">
                <div className="flex flex-wrap items-center gap-2 text-sm font-bold text-[#ff334f]">
                    <span>Deal price</span>
                    {displayedDiscountPercentage !== null &&
                        displayedDiscountPercentage > 0 && (
                            <span className="rounded bg-[#ff334f] px-2 py-0.5 text-white">
                                {displayedDiscountPercentage}% OFF
                            </span>
                        )}
                </div>
                <div className="mt-1 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <p className="text-4xl font-black tracking-[-0.04em] text-[#ff334f] sm:text-5xl xl:text-4xl">
                        {formatPrice(displayedSellingPrice)}
                    </p>
                    {displayedMarketPrice &&
                        Number(displayedMarketPrice) >
                            Number(displayedSellingPrice) && (
                            <span className="text-sm text-slate-400 line-through">
                                {formatPrice(displayedMarketPrice)}
                            </span>
                        )}
                </div>
                {isWholesaleQuantity && (
                    <p className="mt-2 text-sm font-bold text-orange-700">
                        Wholesale unit price ·{' '}
                        {appliedWholesaleTier?.minimumQuantity}+ tier
                    </p>
                )}
                {listing.retailEnabled && listing.wholesaleEnabled && (
                    <p className="mt-2 text-sm text-slate-600">
                        Retail{' '}
                        {formatPrice(
                            selectedVariant?.sellingPrice ??
                                listing.salePrice ??
                                listing.price,
                        )}{' '}
                        · Wholesale from{' '}
                        {formatPrice(lowestWholesaleTier?.unitPrice ?? null)} at{' '}
                        {lowestWholesaleTier?.minimumQuantity}+ units
                    </p>
                )}
                {listing.wholesaleEnabled && wholesaleTiers.length > 0 && (
                    <div className="mt-4 overflow-hidden rounded-lg border border-orange-100 bg-white">
                        <div className="grid grid-cols-2 bg-orange-50 px-3 py-2 text-xs font-bold tracking-wide text-orange-900 uppercase">
                            <span>Quantity</span>
                            <span className="text-right">Unit price</span>
                        </div>
                        {wholesaleTiers.map((tier, index) => {
                            const nextTier = wholesaleTiers[index + 1];
                            const isActive =
                                appliedWholesaleTier?.minimumQuantity ===
                                tier.minimumQuantity;

                            return (
                                <div
                                    key={tier.minimumQuantity}
                                    className={`grid grid-cols-2 px-3 py-2 text-sm ${index > 0 ? 'border-t border-orange-100' : ''} ${isActive ? 'bg-orange-100 font-bold text-orange-900' : 'text-slate-600'}`}
                                >
                                    <span>
                                        {nextTier
                                            ? `${tier.minimumQuantity}–${nextTier.minimumQuantity - 1} units`
                                            : `${tier.minimumQuantity}+ units`}
                                    </span>
                                    <span className="text-right">
                                        {formatPrice(tier.unitPrice)}
                                        {isActive && ' · Active'}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                )}
                {displayedDiscountPercentage !== null &&
                    displayedDiscountPercentage > 0 && (
                        <p className="mt-3 rounded-lg bg-rose-100/70 px-3 py-2 text-sm font-bold text-rose-700">
                            Extra savings:{' '}
                            {formatPrice(
                                String(
                                    Number(displayedMarketPrice) -
                                        Number(displayedSellingPrice),
                                ),
                            )}
                        </p>
                    )}
            </div>
        </div>
    );

    return (
        <StorefrontLayout
            title={listing.metaTitle ?? listing.title}
            description={listing.metaDescription ?? listing.shortDescription}
            categories={categories}
            activeCategorySlugs={categoryTrail.map((item) => item.slug)}
            showMobileNavigation={false}
        >
            <main className="product-page storefront-container bg-slate-50 pt-3 pb-24 sm:bg-white lg:pt-5 lg:pb-8">
                <div className="mb-5 hidden lg:block">
                    <StorefrontBreadcrumbs
                        items={[
                            { label: 'Home', href: home.url() },
                            ...categoryTrail.map((item) => ({
                                label: item.name,
                                href: categoryShow.url(item.slug),
                            })),
                            { label: listing.title },
                        ]}
                    />
                </div>

                <div className="grid items-start gap-3 lg:grid-cols-2 lg:gap-6 xl:grid-cols-[minmax(0,1.08fr)_minmax(0,1fr)_20rem] xl:gap-5">
                    <ProductGallery
                        key={selectedVariant?.image?.cardUrl ?? 'base-gallery'}
                        listing={listing}
                        featuredImageUrl={selectedVariant?.image?.cardUrl}
                    />
                    <section className="min-w-0 rounded-xl bg-white p-4 shadow-[0_2px_16px_rgba(15,23,42,0.05)] lg:rounded-none lg:p-0 lg:shadow-none">
                        {listing.brand && (
                            <Link
                                href={brandShow(listing.brand.slug)}
                                className="text-sm font-bold tracking-wider text-blue-700 uppercase"
                            >
                                {listing.brand.name}
                            </Link>
                        )}
                        <h1 className="mt-2 text-xl font-black tracking-tight text-slate-950 sm:text-3xl">
                            {listing.title}
                        </h1>
                        <div className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                            {reviewFlags.product && (
                                <>
                                    <span className="flex items-center gap-1 font-bold text-amber-500">
                                        <Star className="size-4 fill-current" />
                                        {listing.ratingAverage?.toFixed(1) ??
                                            'New'}
                                    </span>
                                    <span className="text-slate-400">
                                        ({listing.reviewCount} reviews)
                                    </span>
                                </>
                            )}
                            <a
                                href="#qa"
                                className="text-slate-500 hover:text-[#ff5a00]"
                            >
                                {questions.length} answered questions
                            </a>
                        </div>
                        <div className="mt-4">{offerSummary}</div>
                        {listing.shortDescription && (
                            <p className="mt-4 text-base leading-6 text-slate-600">
                                {listing.shortDescription}
                            </p>
                        )}
                        <dl className="mt-4 grid gap-2 border-t border-slate-100 pt-4 text-sm text-slate-600">
                            {[
                                ['Brand', listing.brand?.name],
                                ['Model', listing.model],
                                ['Category', listing.category?.name],
                            ]
                                .filter(([, value]) => Boolean(value))
                                .map(([label, value]) => (
                                    <div key={label} className="flex gap-2">
                                        <Check className="size-4 shrink-0 text-slate-400" />
                                        <dt className="font-bold">{label}:</dt>
                                        <dd>{value}</dd>
                                    </div>
                                ))}
                        </dl>
                        <div className="mt-5 flex flex-wrap items-center gap-3 text-sm">
                            <span
                                className={`font-bold ${isOutOfStock ? 'text-red-600' : 'text-emerald-600'}`}
                            >
                                {isOutOfStock
                                    ? 'Out of stock'
                                    : listing.stockStatus === 'backorder'
                                      ? 'Available on backorder'
                                      : 'In stock'}
                            </span>
                            <span className="text-slate-500 capitalize">
                                {listing.condition} condition
                            </span>
                        </div>

                        {listing.productType === 'variant' && (
                            <div className="mt-6 grid gap-4">
                                {listing.variantOptions.map((option) => (
                                    <fieldset key={option.id}>
                                        <legend className="text-sm font-bold">
                                            {option.name}
                                        </legend>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {option.values.map((value) => (
                                                <button
                                                    aria-pressed={
                                                        selections[
                                                            option.name
                                                        ] === value
                                                    }
                                                    key={value}
                                                    type="button"
                                                    onClick={() => {
                                                        const nextSelections = {
                                                            ...selections,
                                                            [option.name]:
                                                                value,
                                                        };
                                                        setSelections(
                                                            nextSelections,
                                                        );

                                                        if (
                                                            purchaseContext.channel ===
                                                            'wholesale'
                                                        ) {
                                                            const nextVariant =
                                                                listing.variants.find(
                                                                    (variant) =>
                                                                        Object.entries(
                                                                            variant.selections,
                                                                        ).every(
                                                                            ([
                                                                                name,
                                                                                selectedValue,
                                                                            ]) =>
                                                                                nextSelections[
                                                                                    name
                                                                                ] ===
                                                                                selectedValue,
                                                                        ),
                                                                );
                                                            const nextMinimum =
                                                                nextVariant
                                                                    ?.wholesaleTiers[0]
                                                                    ?.minimumQuantity;

                                                            if (nextMinimum) {
                                                                setQuantity(
                                                                    (current) =>
                                                                        Math.max(
                                                                            current,
                                                                            nextMinimum,
                                                                        ),
                                                                );
                                                            }
                                                        }
                                                    }}
                                                    className={`min-h-11 rounded-lg border px-4 py-2 text-sm font-bold ${selections[option.name] === value ? 'border-[#ff5a00] text-[#ff5a00] ring-1 ring-orange-100' : 'border-slate-200'}`}
                                                >
                                                    {value}
                                                </button>
                                            ))}
                                        </div>
                                    </fieldset>
                                ))}
                            </div>
                        )}

                        {listing.retailEnabled && (
                            <div className="xl:hidden">
                                <ProductPurchase
                                    {...purchaseProps}
                                    instanceId="responsive"
                                />
                            </div>
                        )}
                        {listing.auction &&
                            (listing.auction.canBid ? (
                                <Form
                                    {...placeBid.form(listing.auction.id)}
                                    className="mt-6 grid grid-cols-[1fr_auto] gap-2"
                                >
                                    <input
                                        type="number"
                                        step="0.01"
                                        min={
                                            listing.auction.type === 'blind'
                                                ? Number(
                                                      listing.auction
                                                          .viewerBid ??
                                                          listing.auction
                                                              .startingPrice,
                                                  ) +
                                                  (listing.auction.viewerBid
                                                      ? Number(
                                                            listing.auction
                                                                .minimumIncrement ??
                                                                0,
                                                        )
                                                      : 0)
                                                : Number(
                                                      listing.auction
                                                          .currentPrice ??
                                                          listing.auction
                                                              .startingPrice,
                                                  ) +
                                                  (listing.auction.currentPrice
                                                      ? Number(
                                                            listing.auction
                                                                .minimumIncrement ??
                                                                0,
                                                        )
                                                      : 0)
                                        }
                                        name="amount"
                                        placeholder="Your bid"
                                        className="rounded-lg border px-3 text-base"
                                    />
                                    <button className="rounded-lg bg-[#ff5a00] px-5 py-3 text-sm font-bold text-white">
                                        Place Bid
                                    </button>
                                    <p className="col-span-2 text-sm text-slate-500">
                                        {listing.auction.type === 'blind' ? (
                                            'Blind auction'
                                        ) : (
                                            <>
                                                Current bid{' '}
                                                {formatPrice(
                                                    listing.auction
                                                        .currentPrice ??
                                                        listing.auction
                                                            .startingPrice,
                                                )}
                                            </>
                                        )}{' '}
                                        · {listing.auction.bidCount ?? 0} bids ·
                                        Ends{' '}
                                        {new Date(
                                            listing.auction.endsAt,
                                        ).toLocaleString()}
                                    </p>
                                </Form>
                            ) : (
                                <div className="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                                    This auction is read-only. New bids are
                                    currently unavailable, but existing auction
                                    offers and payments continue normally.
                                </div>
                            ))}

                        <div className="mt-4 flex flex-wrap gap-5 text-sm text-slate-500">
                            {auth.user ? (
                                <Form
                                    {...(isWishlisted
                                        ? removeWish.form(listing.slug)
                                        : addWish.form(listing.slug))}
                                >
                                    <button className="flex items-center gap-1 hover:text-[#ff5a00]">
                                        <Heart
                                            className={`size-4 ${isWishlisted ? 'fill-[#ff5a00] text-[#ff5a00]' : ''}`}
                                        />
                                        {isWishlisted
                                            ? 'Remove from Wishlist'
                                            : 'Add to Wishlist'}
                                    </button>
                                </Form>
                            ) : (
                                <Link
                                    href={login()}
                                    className="flex items-center gap-1"
                                >
                                    <Heart className="size-4" /> Add to Wishlist
                                </Link>
                            )}
                            <button
                                onClick={() => comparison.toggle(listing.id)}
                                className="flex items-center gap-1 hover:text-[#ff5a00]"
                            >
                                <GitCompareArrows className="size-4" />
                                {comparison.contains(listing.id)
                                    ? 'Remove comparison'
                                    : 'Compare'}
                            </button>
                            <button
                                onClick={() => void share()}
                                className="flex items-center gap-1 hover:text-[#ff5a00]"
                            >
                                <Share2 className="size-4" />
                                Share
                            </button>
                        </div>
                        <div className="mt-5 flex flex-wrap gap-x-5 gap-y-2 border-t border-slate-200/70 pt-4 text-sm text-slate-600 xl:hidden">
                            <Link
                                href={shipping()}
                                className="inline-flex min-h-8 items-center gap-2 hover:text-orange-700"
                            >
                                <Truck className="size-4 text-orange-600" />
                                Shipping information
                            </Link>
                            {listing.retailEnabled &&
                                categoryPolicies?.codEnabled && (
                                    <span className="inline-flex items-center">
                                        Cash on Delivery available
                                    </span>
                                )}
                        </div>
                        {sellerSummary && (
                            <div className="xl:hidden">
                                <SellerSummary seller={sellerSummary} />
                            </div>
                        )}
                    </section>
                    <aside
                        className="hidden overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-24 xl:block"
                        aria-label="Price and seller information"
                    >
                        <div className="-mx-5 -mt-5 bg-emerald-50 px-5 py-3 text-base font-black text-emerald-700">
                            Service commitment
                        </div>
                        {listing.retailEnabled && (
                            <ProductPurchase
                                {...purchaseProps}
                                instanceId="desktop"
                            />
                        )}
                        <div className="mt-5 grid gap-4 border-t border-slate-100 pt-5 text-sm">
                            <div className="flex gap-3">
                                <Truck className="size-4 shrink-0 text-[#ff5a00]" />
                                <div>
                                    <strong className="block">
                                        Islandwide Delivery
                                    </strong>
                                    <Link
                                        href={shipping()}
                                        className="mt-1 inline-block text-slate-500 underline underline-offset-4"
                                    >
                                        Delivery information
                                    </Link>
                                </div>
                            </div>
                            {listing.warranty && (
                                <div className="flex gap-3">
                                    <ShieldCheck className="size-4 shrink-0 text-[#ff5a00]" />
                                    <div>
                                        <strong className="block">
                                            Warranty
                                        </strong>
                                        <p className="mt-1 text-slate-500">
                                            {listing.warranty}
                                        </p>
                                    </div>
                                </div>
                            )}
                            <div className="flex gap-3">
                                <RotateCcw className="size-4 shrink-0 text-[#ff5a00]" />
                                <div>
                                    <strong className="block">
                                        {categoryPolicies?.returnWindowDays
                                            ? `${categoryPolicies.returnWindowDays} Days Easy Returns`
                                            : 'Returns policy'}
                                    </strong>
                                    <Link
                                        href={returns()}
                                        className="mt-1 inline-block text-slate-500 underline underline-offset-4"
                                    >
                                        Conditions apply
                                    </Link>
                                </div>
                            </div>
                            <div className="flex gap-3">
                                <CreditCard className="size-4 shrink-0 text-[#ff5a00]" />
                                <div>
                                    <strong className="block">
                                        {categoryPolicies?.codEnabled
                                            ? 'Cash on Delivery'
                                            : 'Secure payments'}
                                    </strong>
                                    <p className="mt-1 text-slate-500">
                                        {categoryPolicies?.codEnabled
                                            ? 'Available for eligible orders'
                                            : 'Choose your method at checkout'}
                                    </p>
                                </div>
                            </div>
                        </div>
                        {sellerSummary && (
                            <SellerSummary seller={sellerSummary} compact />
                        )}
                    </aside>
                </div>
                <ProductDetails
                    listing={listing}
                    reviews={reviews}
                    reviewsEnabled={reviewFlags.product}
                    questions={questions}
                    pendingQuestions={pendingQuestions}
                    categoryPolicies={categoryPolicies}
                />

                {sellerListings.length > 0 && (
                    <section className="mt-6">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <h2 className="text-lg font-black tracking-tight">
                                More from{' '}
                                {sellerSummary ? (
                                    <Link
                                        href={storeShow(sellerSummary.slug)}
                                        className="hover:text-orange-700"
                                    >
                                        {sellerSummary.store_name}
                                    </Link>
                                ) : (
                                    'this seller'
                                )}
                            </h2>
                            {sellerSummary && (
                                <Link
                                    href={storeShow(sellerSummary.slug)}
                                    className="inline-flex min-h-11 items-center text-sm font-bold text-orange-700"
                                >
                                    View store →
                                </Link>
                            )}
                        </div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            {sellerListings.map((sellerListing) => (
                                <ListingCard
                                    key={sellerListing.id}
                                    listing={sellerListing}
                                />
                            ))}
                        </div>
                    </section>
                )}

                {relatedListings.length > 0 && (
                    <section className="mt-6">
                        <div className="mb-3 flex items-center border-b pb-2">
                            <h2 className="text-lg font-black tracking-tight">
                                Related items
                            </h2>
                            <Link
                                href={
                                    listing.category
                                        ? listingsIndex({
                                              query: {
                                                  category:
                                                      listing.category.slug,
                                              },
                                          })
                                        : listingsIndex()
                                }
                                className="ml-auto text-sm font-bold text-slate-500 transition hover:text-[#FF6D00]"
                            >
                                View All
                            </Link>
                        </div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            {relatedListings.map((related) => (
                                <ListingCard
                                    key={related.id}
                                    listing={related}
                                />
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </StorefrontLayout>
    );
}
