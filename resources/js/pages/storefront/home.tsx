import { Link, useHttp } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Grid2X2, Zap } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ListingCard } from '@/components/listing-card';
import { StorefrontCategoryArtwork } from '@/components/storefront-category-artwork';
import { StorefrontLayout } from '@/components/storefront-layout';
import type { StorefrontCategory } from '@/components/storefront-layout';
import { show as brandShow } from '@/routes/brands';
import { show as categoryShow } from '@/routes/categories';
import {
    index as listingsIndex,
    recent as recentListings,
} from '@/routes/listings';
import type {
    StorefrontHomepageCategory,
    StorefrontListing,
    StorefrontPromotion,
} from '@/types';

const recentStorageKey = 'prodeals.recentlyViewedListingIds';

type Brand = { id: number; name: string; slug: string; logoUrl: string | null };
type FlashSale = {
    id: number;
    title: string;
    subtitle: string | null;
    endsAt: string;
    listings: StorefrontListing[];
};
type HomeProps = {
    categories: StorefrontCategory[];
    promotions: {
        hero: StorefrontPromotion[];
        secondary: StorefrontPromotion[];
    };
    popularCategories: StorefrontHomepageCategory[];
    featuredDeals: StorefrontListing[];
    bestOffers: StorefrontListing[];
    bestSellers: StorefrontListing[];
    newArrivals: StorefrontListing[];
    topBrands: Brand[];
    flashSale: FlashSale | null;
};

function HeroBanner({ slides }: { slides: StorefrontPromotion[] }) {
    const slide = slides[0];

    if (!slide) {
        return null;
    }

    const heroSizing = slide.containsEmbeddedCopy
        ? 'aspect-[3/1] min-h-[19rem] sm:min-h-[21rem] lg:min-h-0'
        : 'min-h-[19rem] sm:min-h-[21rem] lg:min-h-[22rem]';

    return (
        <section
            className={`relative isolate overflow-hidden rounded-2xl bg-[#fff1e5] shadow-sm ring-1 ring-orange-100 ${heroSizing}`}
            aria-label="Featured offer"
        >
            <img
                src={slide.imageUrl}
                alt={slide.artworkAlt ?? ''}
                className="absolute inset-0 size-full object-cover object-center sm:object-right"
            />
            {!slide.containsEmbeddedCopy && (
                <>
                    <div className="absolute inset-0 bg-[linear-gradient(90deg,#fff8f1_0%,rgba(255,248,241,0.96)_35%,rgba(255,240,225,0.67)_58%,rgba(255,109,0,0.2)_100%)]" />
                    <div className="absolute inset-y-0 right-0 w-1/3 bg-gradient-to-l from-[#FF6D00]/35 to-transparent" />
                    <div className="relative z-10 flex min-h-[19rem] max-w-[38rem] flex-col justify-center px-8 py-10 sm:min-h-[21rem] sm:px-14 lg:min-h-[22rem] lg:px-16">
                        <p className="text-[10px] font-extrabold tracking-[0.14em] text-[#FF6D00] uppercase sm:text-xs">
                            Big tech. Bigger savings.
                        </p>
                        <h1 className="mt-2 max-w-lg text-3xl leading-[0.98] font-black tracking-tight text-slate-950 sm:text-5xl lg:text-[3.4rem]">
                            {slide.title}
                        </h1>
                        <p className="mt-4 max-w-sm text-sm leading-5 text-slate-700 sm:text-base">
                            {slide.subtitle ??
                                'Top brands. Unbeatable prices. Only at prodeals.lk.'}
                        </p>
                        <Link
                            href={slide.linkUrl ?? listingsIndex()}
                            className="mt-6 inline-flex w-max items-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-xs font-bold text-white shadow-lg shadow-slate-950/15 transition hover:-translate-y-0.5 hover:bg-[#FF6D00] motion-reduce:transform-none"
                        >
                            {slide.ctaLabel ?? 'Shop All Deals'}{' '}
                            <ChevronRight className="size-4" />
                        </Link>
                    </div>
                </>
            )}
            {slide.containsEmbeddedCopy && slide.linkUrl && (
                <Link
                    href={slide.linkUrl}
                    className="absolute inset-0 z-10"
                    aria-label={slide.title}
                />
            )}
        </section>
    );
}

function SectionTitle({
    title,
    href,
    icon,
    onScrollLeft,
    onScrollRight,
}: {
    title: string;
    href?: string;
    icon?: React.ReactNode;
    onScrollLeft?: () => void;
    onScrollRight?: () => void;
}) {
    return (
        <div className="mb-3 flex items-center justify-between border-b border-slate-100 pb-2">
            <div className="flex items-center gap-2">
                {icon}
                <h2 className="text-xl font-extrabold sm:text-2xl">{title}</h2>
            </div>
            <div className="flex items-center gap-3">
                {href && (
                    <Link
                        href={href}
                        className="text-sm font-semibold text-slate-500 transition hover:text-[#FF6D00]"
                    >
                        View All
                    </Link>
                )}
                {onScrollLeft && onScrollRight && (
                    <div className="flex items-center gap-1">
                        <button
                            type="button"
                            onClick={onScrollLeft}
                            className="grid size-7 place-items-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-[#FF6D00] hover:text-[#FF6D00]"
                            aria-label={`Scroll ${title} left`}
                        >
                            <ChevronLeft className="size-3" />
                        </button>
                        <button
                            type="button"
                            onClick={onScrollRight}
                            className="grid size-7 place-items-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-[#FF6D00] hover:text-[#FF6D00]"
                            aria-label={`Scroll ${title} right`}
                        >
                            <ChevronRight className="size-3" />
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

function ProductRow({
    title,
    href,
    icon,
    listings,
}: {
    title?: string;
    href?: string;
    icon?: React.ReactNode;
    listings: StorefrontListing[];
}) {
    const row = useRef<HTMLDivElement>(null);
    const scroll = (direction: number) =>
        row.current?.scrollBy({
            left: direction * Math.max(row.current.clientWidth * 0.8, 260),
            behavior: 'smooth',
        });

    if (listings.length === 0) {
        return (
            <div>
                {title && (
                    <SectionTitle title={title} href={href} icon={icon} />
                )}
                <p className="rounded-xl border border-dashed p-6 text-center text-xs text-slate-600">
                    Products will appear here as the collection is curated.
                </p>
            </div>
        );
    }

    return (
        <div>
            {title && (
                <SectionTitle
                    title={title}
                    href={href}
                    icon={icon}
                    onScrollLeft={
                        listings.length > 4 ? () => scroll(-1) : undefined
                    }
                    onScrollRight={
                        listings.length > 4 ? () => scroll(1) : undefined
                    }
                />
            )}
            <div
                ref={row}
                className="flex snap-x snap-mandatory [scrollbar-width:none] gap-3 overflow-x-auto pb-2"
            >
                {listings.map((listing) => (
                    <div
                        key={listing.id}
                        className="w-[calc((100%-0.75rem)/2)] shrink-0 snap-start sm:w-52 lg:w-[calc((100%-3.75rem)/6)]"
                    >
                        <ListingCard listing={listing} />
                    </div>
                ))}
            </div>
        </div>
    );
}

function Countdown({ endsAt }: { endsAt: string }) {
    const [remaining, setRemaining] = useState(() =>
        Math.max(0, new Date(endsAt).getTime() - Date.now()),
    );
    useEffect(() => {
        const timer = window.setInterval(
            () =>
                setRemaining(
                    Math.max(0, new Date(endsAt).getTime() - Date.now()),
                ),
            1000,
        );

        return () => window.clearInterval(timer);
    }, [endsAt]);
    const total = Math.floor(remaining / 1000);
    const values = [
        Math.floor(total / 3600),
        Math.floor((total % 3600) / 60),
        total % 60,
    ];

    return (
        <span
            className="ml-2 flex gap-1"
            aria-label={`${values[0]} hours ${values[1]} minutes ${values[2]} seconds remaining`}
        >
            {values.map((value, index) => (
                <span
                    key={index}
                    className="rounded border border-orange-200 bg-orange-50 px-2 py-1 text-[10px] font-extrabold text-[#FF6D00]"
                >
                    {String(value).padStart(2, '0')}
                </span>
            ))}
        </span>
    );
}

function readRecentIds(): number[] {
    try {
        const value = JSON.parse(
            window.localStorage.getItem(recentStorageKey) ?? '[]',
        );

        return Array.isArray(value)
            ? value
                  .filter((id): id is number => Number.isInteger(id))
                  .slice(0, 12)
            : [];
    } catch {
        return [];
    }
}

function RecentlyViewed() {
    const http = useHttp<{ ids: number[] }, { listings: StorefrontListing[] }>(
        () => ({ ids: typeof window === 'undefined' ? [] : readRecentIds() }),
    );
    useEffect(() => {
        if (http.data.ids.length > 0) {
            void http.get(recentListings.url()).catch(() => undefined);
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    if (
        http.data.ids.length === 0 ||
        (!http.processing && !http.response?.listings.length)
    ) {
        return null;
    }

    return (
        <section className="mt-7">
            <ProductRow
                title="Recently Viewed"
                listings={http.response?.listings ?? []}
            />
        </section>
    );
}

export default function StorefrontHome({
    categories,
    promotions,
    popularCategories,
    featuredDeals,
    bestOffers,
    newArrivals,
    topBrands,
    flashSale,
}: HomeProps) {
    return (
        <StorefrontLayout
            title="Sri Lanka’s marketplace for better deals"
            categories={categories}
        >
            <main className="mx-auto max-w-[82rem] px-4 py-4 sm:px-6">
                <HeroBanner slides={promotions.hero} />

                <section
                    className="mt-4 flex snap-x snap-mandatory [scrollbar-width:none] gap-3 overflow-x-auto pb-2"
                    aria-label="Popular categories"
                >
                    {popularCategories.slice(0, 8).map((category) => (
                        <Link
                            key={category.id}
                            href={categoryShow(category.slug)}
                            className="group flex h-30 w-28 shrink-0 snap-start flex-col items-center justify-center rounded-xl border border-slate-200 bg-white p-2 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#FF6D00]/40 hover:shadow-md motion-reduce:transform-none sm:h-32 sm:w-[8.6rem]"
                        >
                            <StorefrontCategoryArtwork
                                category={category}
                                className="size-16 rounded-lg bg-slate-50 text-[#FF6D00] ring-0 sm:size-20"
                            />
                            <span className="mt-2 line-clamp-1 text-xs font-bold sm:text-sm">
                                {category.name}
                            </span>
                        </Link>
                    ))}
                    <Link
                        href={listingsIndex()}
                        className="grid h-30 w-24 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-xs font-bold shadow-sm transition hover:border-[#FF6D00]/40 hover:text-[#FF6D00] sm:h-32"
                    >
                        <Grid2X2 className="size-6 text-[#FF6D00]" />
                        View All
                    </Link>
                </section>

                <section className="mt-5">
                    <ProductRow
                        title="Featured Deals"
                        href="/collections/featured"
                        listings={
                            featuredDeals.length ? featuredDeals : bestOffers
                        }
                    />
                </section>

                {topBrands.length > 0 && (
                    <section className="mt-6">
                        <SectionTitle
                            title="Top Brands You Trust"
                            href="/brands"
                        />
                        <div className="flex [scrollbar-width:none] gap-3 overflow-x-auto pb-2">
                            {topBrands.map((brand) => (
                                <Link
                                    key={brand.id}
                                    href={brandShow(brand.slug)}
                                    className="flex h-14 min-w-36 items-center justify-center rounded-lg border px-5 hover:border-orange-200"
                                >
                                    {brand.logoUrl ? (
                                        <img
                                            src={brand.logoUrl}
                                            alt={brand.name}
                                            className="max-h-8 max-w-24 object-contain"
                                        />
                                    ) : (
                                        <span className="text-sm font-black">
                                            {brand.name}
                                        </span>
                                    )}
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {flashSale && (
                    <section className="mt-6">
                        <div className="flex items-center border-b pb-2">
                            <Zap className="size-4 fill-[#FF6D00] text-[#FF6D00]" />
                            <h2 className="ml-2 text-xl font-extrabold sm:text-2xl">
                                {flashSale.title}
                            </h2>
                            <Countdown endsAt={flashSale.endsAt} />
                        </div>
                        <div className="mt-3">
                            <ProductRow listings={flashSale.listings} />
                        </div>
                    </section>
                )}

                {promotions.secondary.length > 0 && (
                    <section className="mt-6 grid gap-4 md:grid-cols-2">
                        {promotions.secondary
                            .slice(0, 2)
                            .map((promotion, index) => (
                                <Link
                                    key={`${promotion.id}-${index}`}
                                    href={promotion.linkUrl ?? listingsIndex()}
                                    className={`relative min-h-40 overflow-hidden rounded-xl ${promotion.visualTheme === 'dark' ? 'bg-slate-950 text-white' : 'bg-orange-500 text-white'}`}
                                >
                                    <img
                                        src={promotion.imageUrl}
                                        alt={promotion.artworkAlt ?? ''}
                                        className="absolute inset-0 size-full object-cover"
                                    />
                                    <div className="absolute inset-0 bg-gradient-to-r from-black/70 to-transparent" />
                                    <div className="relative max-w-xs p-7">
                                        <h2 className="text-2xl font-black">
                                            {promotion.title}
                                        </h2>
                                        <p className="mt-1 text-sm">
                                            {promotion.subtitle}
                                        </p>
                                        <span className="mt-4 inline-flex rounded-full bg-white px-4 py-2 text-xs font-bold text-slate-950">
                                            {promotion.ctaLabel ??
                                                'Explore Now'}
                                        </span>
                                    </div>
                                </Link>
                            ))}
                    </section>
                )}

                <section className="mt-6">
                    <ProductRow
                        title="New Arrivals"
                        href="/collections/new-arrivals"
                        listings={newArrivals}
                    />
                </section>
                <RecentlyViewed />
            </main>
        </StorefrontLayout>
    );
}
