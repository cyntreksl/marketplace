import { Form, Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    CalendarClock,
    ImageOff,
    MapPin,
    PackageCheck,
    ShieldCheck,
    ShoppingBag,
    Star,
    Store,
    Tag,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { store as addCartItem } from '@/actions/App/Http/Controllers/CartController';
import { RichTextContent } from '@/components/rich-text-editor';
import { StorefrontBreadcrumbs } from '@/components/storefront-breadcrumbs';
import { StorefrontLayout } from '@/components/storefront-layout';
import { Button } from '@/components/ui/button';
import { home, login } from '@/routes';
import { index as listingsIndex } from '@/routes/listings';
import type {
    StorefrontBreadcrumbItem,
    StorefrontCategory,
    StorefrontCategoryNode,
    StorefrontListing,
    StorefrontReview,
} from '@/types';

const recentStorageKey = 'prodeals.recentlyViewedListingIds';

function productBreadcrumbs(
    categoryTrail: StorefrontCategoryNode[],
    productTitle: string,
): StorefrontBreadcrumbItem[] {
    return [
        { label: 'Home', href: home.url() },
        ...categoryTrail.map((category) => ({
            label: category.name,
            href: listingsIndex.url({
                query: { category: category.slug },
            }),
        })),
        { label: productTitle },
    ];
}

function ProductGallery({ listing }: { listing: StorefrontListing }) {
    const [selectedMediaIndex, setSelectedMediaIndex] = useState(0);
    const selectedMedia = listing.media[selectedMediaIndex];

    return (
        <div className="space-y-3">
            <div className="relative aspect-square overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-primary/15 via-white to-slate-100 shadow-xl shadow-slate-950/5 dark:border-slate-800 dark:from-primary/10 dark:via-slate-900 dark:to-slate-950">
                {selectedMedia ? (
                    <img
                        className="h-full w-full object-contain p-3 sm:p-5"
                        src={selectedMedia.url}
                        alt={listing.title}
                    />
                ) : (
                    <div className="grid h-full place-items-center p-8 text-center">
                        <div>
                            <span className="mx-auto grid size-16 place-items-center rounded-2xl bg-primary/10 text-primary">
                                <ImageOff className="size-8" />
                            </span>
                            <p className="mt-4 font-bold text-slate-700 dark:text-slate-200">
                                Images are coming soon
                            </p>
                            <p className="mt-1 text-sm text-slate-500">
                                The seller has not added a product image yet.
                            </p>
                        </div>
                    </div>
                )}
            </div>

            {listing.media.length > 1 && (
                <div
                    className="grid grid-cols-5 gap-2 sm:grid-cols-6"
                    aria-label="Product images"
                >
                    {listing.media.map((media, index) => (
                        <button
                            key={`${media.path}-${index}`}
                            type="button"
                            onClick={() => setSelectedMediaIndex(index)}
                            aria-label={`View product image ${index + 1}`}
                            aria-pressed={selectedMediaIndex === index}
                            className={`aspect-square overflow-hidden rounded-xl border bg-white p-1 transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none dark:bg-slate-900 ${
                                selectedMediaIndex === index
                                    ? 'border-primary ring-2 ring-primary/20'
                                    : 'border-slate-200 hover:border-primary/50 dark:border-slate-700'
                            }`}
                        >
                            <img
                                src={media.url}
                                alt=""
                                className="h-full w-full rounded-lg object-cover"
                            />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function ProductFact({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof MapPin;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                <Icon className="size-4" />
            </span>
            <div className="min-w-0">
                <p className="text-xs font-bold tracking-wide text-slate-500 uppercase">
                    {label}
                </p>
                <p className="mt-1 text-sm font-black break-words text-slate-900 dark:text-white">
                    {value}
                </p>
            </div>
        </div>
    );
}

export default function ListingShow({
    listing,
    categories,
    categoryTrail,
    reviews,
}: {
    listing: StorefrontListing;
    categories: StorefrontCategory[];
    categoryTrail: StorefrontCategoryNode[];
    reviews: StorefrontReview[];
}) {
    const price = listing.effectivePrice;
    const { auth } = usePage().props;
    const conditionLabel =
        listing.condition.charAt(0).toUpperCase() + listing.condition.slice(1);

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

    return (
        <StorefrontLayout
            title={listing.title}
            categories={categories}
            activeCategorySlugs={categoryTrail.map((category) => category.slug)}
        >
            <main className="mx-auto max-w-[90rem] px-4 py-6 sm:px-6 lg:px-8 lg:py-9">
                <StorefrontBreadcrumbs
                    items={productBreadcrumbs(categoryTrail, listing.title)}
                />

                <div className="mt-6 grid items-start gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(24rem,0.85fr)] xl:gap-12">
                    <ProductGallery listing={listing} />

                    <div className="lg:sticky lg:top-40">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1.5 text-xs font-black tracking-wide text-primary uppercase">
                                {listing.listingType === 'auction' ? (
                                    <CalendarClock className="size-3.5" />
                                ) : (
                                    <ShoppingBag className="size-3.5" />
                                )}
                                {listing.listingType === 'auction'
                                    ? 'Live auction'
                                    : 'Buy now'}
                            </span>
                            <span className="rounded-full bg-slate-200/70 px-3 py-1.5 text-xs font-black tracking-wide text-slate-600 uppercase dark:bg-slate-800 dark:text-slate-300">
                                {conditionLabel}
                            </span>
                        </div>

                        <h1 className="mt-5 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl lg:text-5xl dark:text-white">
                            {listing.title}
                        </h1>

                        <div className="mt-3 flex items-center gap-2 text-sm">
                            <span className="flex items-center gap-1 font-black text-amber-600 dark:text-amber-400">
                                <Star className="size-4 fill-current" />{' '}
                                {listing.ratingAverage?.toFixed(1) ?? 'New'}
                            </span>
                            <span className="text-slate-500">
                                {listing.reviewCount} verified{' '}
                                {listing.reviewCount === 1
                                    ? 'review'
                                    : 'reviews'}
                            </span>
                        </div>

                        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                            {listing.category && (
                                <Link
                                    href={listingsIndex({
                                        query: {
                                            category: listing.category.slug,
                                        },
                                    })}
                                    className="inline-flex items-center gap-1.5 font-bold text-primary hover:underline"
                                >
                                    <Tag className="size-4" />
                                    {listing.category.name}
                                </Link>
                            )}
                            {listing.brand && (
                                <Link
                                    href={listingsIndex({
                                        query: { brand: listing.brand.slug },
                                    })}
                                    className="inline-flex items-center gap-1.5 font-bold text-slate-600 hover:text-primary dark:text-slate-300"
                                >
                                    <BadgeCheck className="size-4" />
                                    {listing.brand.name}
                                </Link>
                            )}
                        </div>

                        <section className="mt-7 overflow-hidden rounded-3xl border border-primary/20 bg-white shadow-xl shadow-primary/5 dark:border-slate-800 dark:bg-slate-900">
                            <div className="bg-gradient-to-br from-primary/10 via-white to-primary/5 p-6 sm:p-7 dark:from-primary/10 dark:via-slate-900 dark:to-slate-900">
                                <p className="text-xs font-black tracking-[0.14em] text-slate-500 uppercase">
                                    {listing.auction ? 'Current bid' : 'Price'}
                                </p>
                                <p className="mt-2 text-4xl font-black tracking-tight text-primary sm:text-5xl">
                                    Rs. {Number(price ?? 0).toLocaleString()}
                                </p>
                                {listing.salePrice && (
                                    <div className="mt-2 flex items-center gap-3">
                                        <span className="text-sm font-semibold text-slate-400 line-through">
                                            Rs.{' '}
                                            {Number(
                                                listing.price ?? 0,
                                            ).toLocaleString()}
                                        </span>
                                        <span className="rounded-full bg-amber-300 px-2.5 py-1 text-xs font-black text-slate-950">
                                            Save {listing.discountPercentage}%
                                        </span>
                                    </div>
                                )}
                                {listing.auction && (
                                    <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm font-medium text-slate-600 dark:text-slate-300">
                                        <span>
                                            {listing.auction.bidCount ?? 0} bids
                                        </span>
                                        <span>
                                            Ends{' '}
                                            {new Date(
                                                listing.auction.endsAt,
                                            ).toLocaleString()}
                                        </span>
                                    </div>
                                )}
                            </div>

                            <div className="border-t border-slate-200 p-6 dark:border-slate-800">
                                {!auth.user ? (
                                    <Button
                                        asChild
                                        className="h-12 w-full rounded-xl text-base font-black"
                                    >
                                        <Link href={login()}>
                                            {listing.auction
                                                ? 'Sign in to bid'
                                                : 'Sign in to buy'}
                                        </Link>
                                    </Button>
                                ) : listing.auction ? (
                                    <div className="rounded-xl bg-slate-950 px-5 py-3.5 text-center font-bold text-white dark:bg-slate-50 dark:text-slate-950">
                                        Bidding is available from the auction
                                        panel.
                                    </div>
                                ) : (
                                    <Form {...addCartItem.form()}>
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
                                                    disabled={processing}
                                                    className="h-12 w-full rounded-xl text-base font-black"
                                                >
                                                    <ShoppingBag className="size-5" />
                                                    {processing
                                                        ? 'Adding…'
                                                        : 'Add to cart'}
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                )}

                                <div className="mt-4 flex items-center justify-center gap-2 text-xs font-semibold text-slate-500">
                                    <ShieldCheck className="size-4 text-primary" />
                                    Shop with marketplace buyer protection
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <div className="mt-12 grid gap-8 lg:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.65fr)] lg:items-start">
                    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                        <p className="text-xs font-black tracking-[0.16em] text-primary uppercase">
                            Product details
                        </p>
                        <h2 className="mt-2 text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                            About this listing
                        </h2>
                        <RichTextContent
                            value={listing.description ?? ''}
                            className="mt-5 text-base leading-8 text-slate-600 dark:text-slate-300"
                        />
                    </section>

                    <section aria-labelledby="listing-facts-title">
                        <h2 id="listing-facts-title" className="sr-only">
                            Listing information
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                            <ProductFact
                                icon={Store}
                                label="Seller"
                                value={
                                    listing.seller?.store_name ??
                                    'Marketplace seller'
                                }
                            />
                            <ProductFact
                                icon={MapPin}
                                label="Location"
                                value={listing.location}
                            />
                            <ProductFact
                                icon={PackageCheck}
                                label="Availability"
                                value={`${listing.stockQuantity} available`}
                            />
                            <ProductFact
                                icon={ShieldCheck}
                                label="Warranty"
                                value={listing.warranty ?? 'Not specified'}
                            />
                        </div>
                    </section>
                </div>

                {reviews.length > 0 && (
                    <section
                        className="mt-12 rounded-3xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900"
                        aria-labelledby="verified-reviews-title"
                    >
                        <p className="text-xs font-black tracking-[0.16em] text-primary uppercase">
                            Verified purchases only
                        </p>
                        <h2
                            id="verified-reviews-title"
                            className="mt-2 text-2xl font-black tracking-tight"
                        >
                            Buyer reviews
                        </h2>
                        <div className="mt-6 grid gap-4 md:grid-cols-2">
                            {reviews.map((review) => (
                                <article
                                    key={review.id}
                                    className="rounded-2xl bg-slate-50 p-5 dark:bg-slate-950"
                                >
                                    <div
                                        className="flex gap-0.5 text-amber-500"
                                        aria-label={`${review.rating} out of 5 stars`}
                                    >
                                        {Array.from(
                                            { length: 5 },
                                            (_, index) => (
                                                <Star
                                                    key={index}
                                                    className={`size-4 ${index < review.rating ? 'fill-current' : 'opacity-25'}`}
                                                />
                                            ),
                                        )}
                                    </div>
                                    {review.comment && (
                                        <p className="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                            {review.comment}
                                        </p>
                                    )}
                                    <p className="mt-4 text-sm font-black">
                                        {review.buyerName}{' '}
                                        <span className="ml-1 text-xs font-semibold text-primary">
                                            Verified buyer
                                        </span>
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </StorefrontLayout>
    );
}
