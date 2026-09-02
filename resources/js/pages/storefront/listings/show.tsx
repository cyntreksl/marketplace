import { Form, Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Check,
    ChevronLeft,
    ChevronRight,
    GitCompareArrows,
    Heart,
    Maximize2,
    Minus,
    PackageCheck,
    Plus,
    RotateCcw,
    Share2,
    ShieldCheck,
    ShoppingCart,
    Star,
    Truck,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { store as placeBid } from '@/actions/App/Http/Controllers/AuctionBidController';
import { store as addCartItem } from '@/actions/App/Http/Controllers/CartController';
import { store as askQuestion } from '@/actions/App/Http/Controllers/ProductQuestionController';
import {
    destroy as removeWish,
    store as addWish,
} from '@/actions/App/Http/Controllers/WatchlistController';
import { ListingCard } from '@/components/listing-card';
import { RichTextContent } from '@/components/rich-text-editor';
import { StorefrontLayout } from '@/components/storefront-layout';
import { useProductComparison } from '@/hooks/use-product-comparison';
import { home, login } from '@/routes';
import { index as listingsIndex } from '@/routes/listings';
import type {
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

function Gallery({ listing }: { listing: StorefrontListing }) {
    const [index, setIndex] = useState(0);
    const [fullscreen, setFullscreen] = useState(false);
    const selected = listing.media[index];
    const move = (direction: number) =>
        setIndex(
            (index + direction + listing.media.length) % listing.media.length,
        );

    return (
        <div className="grid gap-3 sm:grid-cols-[4.5rem_1fr]">
            <div className="order-2 flex gap-2 overflow-x-auto sm:order-1 sm:flex-col">
                {listing.media.slice(0, 5).map((media, mediaIndex) => (
                    <button
                        key={media.path}
                        onClick={() => setIndex(mediaIndex)}
                        aria-label={`View image ${mediaIndex + 1}`}
                        aria-pressed={index === mediaIndex}
                        className={`size-16 shrink-0 overflow-hidden rounded-lg border bg-white p-1 ${index === mediaIndex ? 'border-[#ff5a00] ring-1 ring-orange-200' : 'border-slate-200'}`}
                    >
                        <img
                            src={media.thumbnailUrl}
                            alt=""
                            className="size-full object-contain"
                        />
                    </button>
                ))}
            </div>
            <div className="relative order-1 flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl border bg-white sm:order-2">
                {selected ? (
                    <button
                        onClick={() => setFullscreen(true)}
                        className="group size-full cursor-zoom-in overflow-hidden"
                        aria-label="Open fullscreen gallery"
                    >
                        <img
                            src={selected.url}
                            alt={listing.title}
                            className="size-full object-contain p-5 transition duration-300 group-hover:scale-125"
                        />
                    </button>
                ) : (
                    <span className="text-sm text-slate-400">
                        Product image coming soon
                    </span>
                )}
                {selected && (
                    <button
                        onClick={() => setFullscreen(true)}
                        className="absolute right-3 bottom-3 grid size-9 place-items-center rounded-full bg-white shadow"
                        aria-label="Open fullscreen gallery"
                    >
                        <Maximize2 className="size-4" />
                    </button>
                )}
            </div>
            {fullscreen && selected && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label="Product gallery"
                    className="fixed inset-0 z-50 grid place-items-center bg-black/90 p-4"
                >
                    <button
                        onClick={() => setFullscreen(false)}
                        className="absolute top-5 right-5 grid size-10 place-items-center rounded-full bg-white"
                        aria-label="Close gallery"
                    >
                        <X className="size-5" />
                    </button>
                    {listing.media.length > 1 && (
                        <>
                            <button
                                onClick={() => move(-1)}
                                className="absolute left-4 grid size-10 place-items-center rounded-full bg-white"
                                aria-label="Previous image"
                            >
                                <ChevronLeft />
                            </button>
                            <button
                                onClick={() => move(1)}
                                className="absolute right-4 grid size-10 place-items-center rounded-full bg-white"
                                aria-label="Next image"
                            >
                                <ChevronRight />
                            </button>
                        </>
                    )}
                    <img
                        src={selected.url}
                        alt={listing.title}
                        className="max-h-[90vh] max-w-[90vw] object-contain"
                    />
                </div>
            )}
        </div>
    );
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
                    className="rounded border bg-white px-2 py-1 text-xs font-black"
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
}) {
    const { auth } = usePage().props;
    const comparison = useProductComparison();
    const [quantity, setQuantity] = useState(1);
    const [selections, setSelections] = useState<Record<string, string>>({});
    const [activeTab, setActiveTab] = useState('overview');
    const selectedVariant = useMemo(
        () =>
            listing.variants.find((variant) =>
                Object.entries(variant.selections).every(
                    ([name, value]) => selections[name] === value,
                ),
            ),
        [listing.variants, selections],
    );
    const displayedSellingPrice =
        selectedVariant?.sellingPrice ?? listing.effectivePrice;
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
        listing.productType === 'simple' ||
        Boolean(selectedVariant && selectedVariant.stockQuantity >= quantity);

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

        if (navigator.share) {
            await navigator.share(data);
        } else {
            await navigator.clipboard.writeText(window.location.href);
        }
    };

    const facts = [
        [Truck, 'Islandwide Delivery', 'Delivery estimate unavailable'],
        [
            ShieldCheck,
            listing.warranty ?? 'Warranty',
            listing.warranty ?? 'Seller warranty applies',
        ],
        [
            RotateCcw,
            `${categoryPolicies?.returnWindowDays ?? 7} Days Easy Returns`,
            'Conditions apply',
        ],
        [
            PackageCheck,
            categoryPolicies?.codEnabled
                ? 'Cash on Delivery'
                : 'Secure Payments',
            categoryPolicies?.codEnabled
                ? 'Available for eligible orders'
                : 'COD unavailable',
        ],
    ] as const;

    return (
        <StorefrontLayout
            title={listing.metaTitle ?? listing.title}
            description={listing.metaDescription ?? listing.shortDescription}
            categories={categories}
            activeCategorySlugs={categoryTrail.map((item) => item.slug)}
        >
            <main className="mx-auto max-w-[96rem] px-4 py-5 sm:px-6">
                <nav
                    className="mb-5 flex flex-wrap gap-2 text-[10px] text-slate-500"
                    aria-label="Breadcrumb"
                >
                    <Link href={home()}>Home</Link>
                    {categoryTrail.map((item) => (
                        <span key={item.id}>
                            ›{' '}
                            <Link
                                href={listingsIndex({
                                    query: { category: item.slug },
                                })}
                            >
                                {item.name}
                            </Link>
                        </span>
                    ))}
                    <span>› {listing.title}</span>
                </nav>

                <div className="grid items-start gap-7 lg:grid-cols-[minmax(0,1.05fr)_minmax(22rem,0.75fr)_18rem]">
                    <Gallery listing={listing} />
                    <section>
                        {listing.brand && (
                            <Link
                                href={listingsIndex({
                                    query: { brand: listing.brand.slug },
                                })}
                                className="text-xs font-black tracking-wider text-blue-700 uppercase"
                            >
                                {listing.brand.name}
                            </Link>
                        )}
                        <h1 className="mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                            {listing.title}
                        </h1>
                        <p className="mt-1 text-xs text-slate-500">
                            {listing.productType === 'variant'
                                ? listing.variants[0]?.sku
                                : ''}{' '}
                            · {listing.condition}
                        </p>
                        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
                            <span className="flex items-center gap-1 font-bold text-amber-500">
                                <Star className="size-4 fill-current" />
                                {listing.ratingAverage?.toFixed(1) ?? 'New'}
                            </span>
                            <span className="text-slate-400">
                                ({listing.reviewCount} reviews)
                            </span>
                            <button
                                onClick={() => setActiveTab('qa')}
                                className="text-slate-500 hover:text-[#ff5a00]"
                            >
                                {questions.length} answered questions
                            </button>
                        </div>
                        {listing.shortDescription && (
                            <p className="mt-5 text-sm leading-6 text-slate-600">
                                {listing.shortDescription}
                            </p>
                        )}
                        {Object.keys(listing.specifications).length > 0 && (
                            <ul className="mt-5 grid gap-3 text-xs text-slate-600">
                                {Object.entries(listing.specifications)
                                    .slice(0, 4)
                                    .map(([name, value]) => (
                                        <li key={name} className="flex gap-2">
                                            <Check className="size-4 text-slate-400" />
                                            <strong>{name}:</strong>{' '}
                                            {String(value)}
                                        </li>
                                    ))}
                            </ul>
                        )}
                        <p className="mt-5 text-xs">
                            <span
                                className={`font-bold ${listing.stockStatus === 'out_of_stock' ? 'text-red-500' : 'text-emerald-600'}`}
                            >
                                {listing.stockStatus === 'out_of_stock'
                                    ? 'Out of stock'
                                    : 'In Stock'}
                            </span>
                            <span className="ml-2 text-slate-400">
                                Ships when delivery is configured
                            </span>
                        </p>

                        {listing.productType === 'variant' && (
                            <div className="mt-6 grid gap-4">
                                {listing.variantOptions.map((option) => (
                                    <fieldset key={option.id}>
                                        <legend className="text-xs font-bold">
                                            {option.name}
                                        </legend>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {option.values.map((value) => (
                                                <button
                                                    key={value}
                                                    type="button"
                                                    onClick={() =>
                                                        setSelections(
                                                            (current) => ({
                                                                ...current,
                                                                [option.name]:
                                                                    value,
                                                            }),
                                                        )
                                                    }
                                                    className={`rounded-lg border px-4 py-2 text-xs font-bold ${selections[option.name] === value ? 'border-[#ff5a00] text-[#ff5a00] ring-1 ring-orange-100' : 'border-slate-200'}`}
                                                >
                                                    {value}
                                                </button>
                                            ))}
                                        </div>
                                    </fieldset>
                                ))}
                            </div>
                        )}

                        {listing.listingType === 'buy_now' && (
                            <div className="mt-6">
                                <span className="text-xs font-bold">
                                    Quantity
                                </span>
                                <div className="mt-2 flex w-max items-center rounded-lg border">
                                    <button
                                        onClick={() =>
                                            setQuantity((value) =>
                                                Math.max(1, value - 1),
                                            )
                                        }
                                        className="grid size-9 place-items-center"
                                        aria-label="Decrease quantity"
                                    >
                                        <Minus className="size-3" />
                                    </button>
                                    <span className="grid w-10 place-items-center text-xs font-bold">
                                        {quantity}
                                    </span>
                                    <button
                                        onClick={() =>
                                            setQuantity((value) =>
                                                Math.min(100, value + 1),
                                            )
                                        }
                                        className="grid size-9 place-items-center"
                                        aria-label="Increase quantity"
                                    >
                                        <Plus className="size-3" />
                                    </button>
                                </div>
                            </div>
                        )}

                        {listing.listingType === 'buy_now' ? (
                            auth.user ? (
                                <Form
                                    {...addCartItem.form()}
                                    className="mt-5 grid grid-cols-2 gap-2"
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
                                                name="listing_variant_id"
                                                value={
                                                    selectedVariant?.id ?? ''
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="quantity"
                                                value={quantity}
                                            />
                                            <button
                                                disabled={
                                                    processing ||
                                                    !canPurchase ||
                                                    listing.stockStatus ===
                                                        'out_of_stock'
                                                }
                                                className="flex items-center justify-center gap-2 rounded-lg border border-[#ff5a00] py-3 text-xs font-bold text-[#ff5a00] disabled:opacity-40"
                                            >
                                                <ShoppingCart className="size-4" />
                                                Add to Cart
                                            </button>
                                            <button
                                                name="buy_now"
                                                value="1"
                                                disabled={
                                                    processing ||
                                                    !canPurchase ||
                                                    listing.stockStatus ===
                                                        'out_of_stock'
                                                }
                                                className="rounded-lg bg-[#ff5a00] py-3 text-xs font-bold text-white disabled:opacity-40"
                                            >
                                                Buy Now
                                            </button>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <Link
                                    href={login()}
                                    className="mt-5 flex rounded-lg bg-[#ff5a00] py-3 text-center text-xs font-bold text-white"
                                >
                                    Sign in to purchase
                                </Link>
                            )
                        ) : (
                            listing.auction && (
                                <Form
                                    {...placeBid.form(listing.auction.id)}
                                    className="mt-6 grid grid-cols-[1fr_auto] gap-2"
                                >
                                    <input
                                        type="number"
                                        step="0.01"
                                        min={
                                            Number(
                                                listing.auction.currentPrice ??
                                                    0,
                                            ) +
                                            Number(
                                                listing.auction
                                                    .minimumIncrement ?? 0,
                                            )
                                        }
                                        name="amount"
                                        placeholder="Your bid"
                                        className="rounded-lg border px-3 text-sm"
                                    />
                                    <button className="rounded-lg bg-[#ff5a00] px-5 py-3 text-xs font-bold text-white">
                                        Place Bid
                                    </button>
                                    <p className="col-span-2 text-xs text-slate-500">
                                        Current bid{' '}
                                        {formatPrice(
                                            listing.auction.currentPrice,
                                        )}{' '}
                                        · {listing.auction.bidCount ?? 0} bids ·
                                        Ends{' '}
                                        {new Date(
                                            listing.auction.endsAt,
                                        ).toLocaleString()}
                                    </p>
                                </Form>
                            )
                        )}

                        <div className="mt-4 flex flex-wrap gap-5 text-xs text-slate-500">
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
                    </section>

                    <aside className="overflow-hidden rounded-xl border shadow-sm">
                        {activeCampaign && (
                            <div className="bg-[#ff5a00] px-4 py-2 text-center text-xs font-bold text-white">
                                Special Offer
                            </div>
                        )}
                        {activeCampaign && (
                            <div className="flex items-center justify-between bg-orange-50 p-4 text-[10px]">
                                <span>Offer ends in</span>
                                <OfferCountdown
                                    endsAt={activeCampaign.endsAt}
                                />
                            </div>
                        )}
                        <div className="p-5">
                            <div className="text-2xl font-black text-[#ff5a00]">
                                {formatPrice(displayedSellingPrice)}
                            </div>
                            {displayedMarketPrice && (
                                <div className="mt-1 flex items-center gap-2 text-xs">
                                    <span className="text-slate-400 line-through">
                                        {formatPrice(displayedMarketPrice)}
                                    </span>
                                    <strong className="text-[#ff5a00]">
                                        {displayedDiscountPercentage}% OFF
                                    </strong>
                                </div>
                            )}
                            <div className="mt-4 rounded-lg border p-3 text-[10px] text-slate-400">
                                Installment plans are currently unavailable.
                            </div>
                            <div className="mt-4 grid gap-4">
                                {facts.map(([Icon, title, copy]) => (
                                    <div key={title} className="flex gap-3">
                                        <Icon className="size-4 shrink-0 text-[#ff5a00]" />
                                        <span>
                                            <strong className="block text-xs">
                                                {title}
                                            </strong>
                                            <span className="text-[10px] text-slate-500">
                                                {copy}
                                            </span>
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <div className="mt-5 border-t pt-4">
                                <span className="flex items-center gap-2 text-xs">
                                    <BadgeCheck className="size-7 text-blue-600" />
                                    <span>
                                        Sold by
                                        <br />
                                        <strong>
                                            {listing.seller?.store_name ??
                                                'Marketplace seller'}
                                        </strong>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </aside>
                </div>

                <section className="mt-10">
                    <div className="flex gap-6 overflow-x-auto border-b text-xs font-bold whitespace-nowrap">
                        {[
                            ['overview', 'Overview'],
                            ['specs', 'Specifications'],
                            ['reviews', `Reviews (${reviews.length})`],
                            ['qa', `Q&A (${questions.length})`],
                            ['shipping', 'Shipping & Returns'],
                        ].map(([id, label]) => (
                            <button
                                key={id}
                                onClick={() => setActiveTab(id)}
                                className={`border-b-2 px-2 py-3 ${activeTab === id ? 'border-[#ff5a00] text-[#ff5a00]' : 'border-transparent text-slate-500'}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    <div className="py-6">
                        {activeTab === 'overview' && (
                            <div className="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                                <div>
                                    <h2 className="text-xl font-black">
                                        {listing.shortDescription ??
                                            'Product overview'}
                                    </h2>
                                    <div className="mt-3 text-sm leading-7 text-slate-600">
                                        <RichTextContent
                                            value={listing.description ?? ''}
                                        />
                                    </div>
                                </div>
                                {listing.media.length > 1 && (
                                    <div className="grid grid-cols-2 gap-2 overflow-hidden rounded-xl">
                                        {listing.media
                                            .slice(1, 4)
                                            .map((media, index) => (
                                                <img
                                                    key={media.path}
                                                    src={media.url}
                                                    alt={`${listing.title} detail ${index + 1}`}
                                                    className={`size-full object-cover ${index === 0 ? 'row-span-2 min-h-64' : 'min-h-32'}`}
                                                />
                                            ))}
                                    </div>
                                )}
                            </div>
                        )}
                        {activeTab === 'specs' && (
                            <dl className="grid max-w-3xl sm:grid-cols-2">
                                {Object.entries(listing.specifications).map(
                                    ([name, value]) => (
                                        <div
                                            key={name}
                                            className="grid grid-cols-2 border-b p-3 text-xs"
                                        >
                                            <dt className="font-bold">
                                                {name}
                                            </dt>
                                            <dd className="text-slate-500">
                                                {String(value)}
                                            </dd>
                                        </div>
                                    ),
                                )}
                            </dl>
                        )}
                        {activeTab === 'reviews' && (
                            <div className="grid gap-3">
                                {reviews.length ? (
                                    reviews.map((review) => (
                                        <article
                                            key={review.id}
                                            className="rounded-xl border p-4"
                                        >
                                            <span className="text-xs font-bold">
                                                {review.buyerName} ·{' '}
                                                {review.rating}/5
                                            </span>
                                            <p className="mt-2 text-sm text-slate-600">
                                                {review.comment}
                                            </p>
                                        </article>
                                    ))
                                ) : (
                                    <p className="text-sm text-slate-400">
                                        No reviews yet.
                                    </p>
                                )}
                            </div>
                        )}
                        {activeTab === 'qa' && (
                            <div className="max-w-3xl">
                                <div className="grid gap-3">
                                    {[...pendingQuestions, ...questions].map(
                                        (question) => (
                                            <article
                                                key={question.id}
                                                className="rounded-xl border p-4"
                                            >
                                                <p className="text-sm font-bold">
                                                    Q: {question.question}
                                                </p>
                                                {question.answer ? (
                                                    <p className="mt-2 text-sm text-slate-600">
                                                        A: {question.answer}
                                                    </p>
                                                ) : (
                                                    <p className="mt-2 text-xs text-amber-600">
                                                        Awaiting seller response
                                                        — visible only to you.
                                                    </p>
                                                )}
                                            </article>
                                        ),
                                    )}
                                </div>
                                {auth.user ? (
                                    <Form
                                        {...askQuestion.form(listing.slug)}
                                        className="mt-5 flex gap-2"
                                    >
                                        <input
                                            required
                                            minLength={10}
                                            name="question"
                                            placeholder="Ask the seller a question"
                                            className="min-w-0 flex-1 rounded-lg border px-4 py-3 text-sm"
                                        />
                                        <button className="rounded-lg bg-slate-950 px-5 text-xs font-bold text-white">
                                            Ask
                                        </button>
                                    </Form>
                                ) : (
                                    <Link
                                        href={login()}
                                        className="mt-4 inline-block text-xs font-bold text-[#ff5a00]"
                                    >
                                        Sign in to ask a question
                                    </Link>
                                )}
                            </div>
                        )}
                        {activeTab === 'shipping' && (
                            <div className="grid max-w-3xl gap-4 sm:grid-cols-2">
                                {facts.map(([Icon, title, copy]) => (
                                    <div
                                        key={title}
                                        className="flex gap-3 rounded-xl border p-4"
                                    >
                                        <Icon className="size-5 text-[#ff5a00]" />
                                        <span>
                                            <strong className="block text-sm">
                                                {title}
                                            </strong>
                                            <span className="text-xs text-slate-500">
                                                {copy}
                                            </span>
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </section>

                {relatedListings.length > 0 && (
                    <section className="mt-4">
                        <div className="mb-3 flex items-center border-b pb-2">
                            <h2 className="text-base font-black">
                                You May Also Like
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
                                className="ml-auto text-[10px] text-slate-500"
                            >
                                View All
                            </Link>
                        </div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <>
                                {relatedListings.map((related) => (
                                    <ListingCard
                                        key={related.id}
                                        listing={related}
                                        compact
                                    />
                                ))}
                            </>
                        </div>
                    </section>
                )}
            </main>
        </StorefrontLayout>
    );
}
