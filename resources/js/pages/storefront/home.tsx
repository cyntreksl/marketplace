import { Link, useHttp } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Grid2X2,
    Pause,
    Play,
    Zap,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ListingCard } from '@/components/listing-card';
import { StorefrontCategoryArtwork } from '@/components/storefront-category-artwork';
import { StorefrontLayout } from '@/components/storefront-layout';
import type { StorefrontCategory } from '@/components/storefront-layout';
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

function HeroCarousel({ slides }: { slides: StorefrontPromotion[] }) {
    const [active, setActive] = useState(0);
    const [paused, setPaused] = useState(false);
    const reducedMotion =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const slide = slides[active];

    useEffect(() => {
        if (paused || reducedMotion || slides.length < 2) {
            return;
        }

        const timer = window.setInterval(
            () => setActive((value) => (value + 1) % slides.length),
            6500,
        );

        return () => window.clearInterval(timer);
    }, [paused, reducedMotion, slides.length]);

    if (!slide) {
        return null;
    }

    const move = (direction: number) =>
        setActive((active + direction + slides.length) % slides.length);

    return (
        <section
            className="relative isolate min-h-[19rem] overflow-hidden rounded-2xl bg-[#fff1e5] shadow-sm ring-1 ring-orange-100 sm:min-h-[21rem] lg:min-h-[22rem]"
            aria-roledescription="carousel"
            aria-label="Featured offers"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onFocus={() => setPaused(true)}
            onBlur={() => setPaused(false)}
        >
            <img
                src={slide.imageUrl}
                alt={slide.artworkAlt ?? ''}
                className="absolute inset-0 size-full object-cover object-center sm:object-right"
            />
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
            {slides.length > 1 && (
                <>
                    <button
                        onClick={() => move(-1)}
                        className="absolute top-1/2 left-3 z-20 grid size-9 -translate-y-1/2 place-items-center rounded-full border border-white bg-white/90 shadow transition hover:bg-white"
                        aria-label="Previous slide"
                    >
                        <ChevronLeft className="size-4" />
                    </button>
                    <button
                        onClick={() => move(1)}
                        className="absolute top-1/2 right-3 z-20 grid size-9 -translate-y-1/2 place-items-center rounded-full border border-white bg-white/90 shadow transition hover:bg-white"
                        aria-label="Next slide"
                    >
                        <ChevronRight className="size-4" />
                    </button>
                    <div className="absolute inset-x-0 bottom-3 flex items-center justify-center gap-1.5">
                        {slides.map((item, index) => (
                            <button
                                key={`${item.id}-${index}`}
                                onClick={() => setActive(index)}
                                aria-label={`Show slide ${index + 1}`}
                                aria-current={index === active}
                                className={`h-2 rounded-full shadow-sm transition-all ${index === active ? 'w-5 bg-[#FF6D00]' : 'w-2 bg-white'}`}
                            />
                        ))}
                        <button
                            onClick={() => setPaused((value) => !value)}
                            className="ml-2 grid size-6 place-items-center rounded-full bg-white"
                            aria-label={
                                paused ? 'Play carousel' : 'Pause carousel'
                            }
                        >
                            {paused ? (
                                <Play className="size-3" />
                            ) : (
                                <Pause className="size-3" />
                            )}
                        </button>
                    </div>
                </>
            )}
        </section>
    );
}

function SectionTitle({
    title,
    href,
    icon,
}: {
    title: string;
    href?: string;
    icon?: React.ReactNode;
}) {
    return (
        <div className="mb-3 flex items-center gap-2 border-b border-slate-100 pb-2">
            <span className="h-0.5 w-6 bg-[#FF6D00]" />
            {icon}
            <h2 className="text-sm font-extrabold">{title}</h2>
            {href && (
                <Link
                    href={href}
                    className="ml-auto text-[10px] font-semibold text-slate-500 hover:text-[#FF6D00]"
                >
                    View All
                </Link>
            )}
        </div>
    );
}

function ProductRow({ listings }: { listings: StorefrontListing[] }) {
    const row = useRef<HTMLDivElement>(null);
    const scroll = (direction: number) =>
        row.current?.scrollBy({
            left: direction * Math.max(row.current.clientWidth * 0.8, 260),
            behavior: 'smooth',
        });

    if (listings.length === 0) {
        return (
            <p className="rounded-xl border border-dashed p-6 text-center text-xs text-slate-600">
                Products will appear here as the collection is curated.
            </p>
        );
    }

    return (
        <div className="relative">
            <div
                ref={row}
                className="flex snap-x snap-mandatory [scrollbar-width:none] gap-3 overflow-x-auto pb-2"
            >
                {listings.map((listing) => (
                    <div
                        key={listing.id}
                        className="w-[68vw] max-w-56 shrink-0 snap-start sm:w-52 lg:w-[calc(20%-0.6rem)] lg:max-w-none"
                    >
                        <ListingCard listing={listing} />
                    </div>
                ))}
            </div>
            {listings.length > 4 && (
                <div className="absolute -top-11 right-0 flex gap-1">
                    <button
                        onClick={() => scroll(-1)}
                        className="grid size-7 place-items-center rounded-full border bg-white"
                        aria-label="Scroll products left"
                    >
                        <ChevronLeft className="size-3" />
                    </button>
                    <button
                        onClick={() => scroll(1)}
                        className="grid size-7 place-items-center rounded-full border bg-white"
                        aria-label="Scroll products right"
                    >
                        <ChevronRight className="size-3" />
                    </button>
                </div>
            )}
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
            <SectionTitle title="Recently Viewed" />
            <ProductRow listings={http.response?.listings ?? []} />
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
                <HeroCarousel slides={promotions.hero} />

                <section
                    className="mt-4 flex snap-x snap-mandatory [scrollbar-width:none] gap-3 overflow-x-auto pb-2"
                    aria-label="Popular categories"
                >
                    {popularCategories.slice(0, 8).map((category) => (
                        <Link
                            key={category.id}
                            href={listingsIndex({
                                query: { category: category.slug },
                            })}
                            className="group flex h-30 w-28 shrink-0 snap-start flex-col items-center justify-center rounded-xl border border-slate-200 bg-white p-2 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#FF6D00]/40 hover:shadow-md motion-reduce:transform-none sm:h-32 sm:w-[8.6rem]"
                        >
                            <StorefrontCategoryArtwork
                                category={category}
                                className="size-16 rounded-lg bg-slate-50 text-[#FF6D00] ring-0 sm:size-20"
                            />
                            <span className="mt-2 line-clamp-1 text-[10px] font-bold sm:text-[11px]">
                                {category.name}
                            </span>
                        </Link>
                    ))}
                    <Link
                        href={listingsIndex()}
                        className="grid h-30 w-24 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-[10px] font-bold shadow-sm transition hover:border-[#FF6D00]/40 hover:text-[#FF6D00] sm:h-32"
                    >
                        <Grid2X2 className="size-6 text-[#FF6D00]" />
                        View All
                    </Link>
                </section>

                <section className="mt-5">
                    <SectionTitle
                        title="Featured Deals"
                        href="/collections/featured"
                    />
                    <ProductRow
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
                                    href={listingsIndex({
                                        query: { brand: brand.slug },
                                    })}
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
                            <h2 className="ml-2 text-sm font-extrabold">
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
                    <SectionTitle
                        title="New Arrivals"
                        href="/collections/new-arrivals"
                    />
                    <ProductRow listings={newArrivals} />
                </section>
                <RecentlyViewed />
            </main>
        </StorefrontLayout>
    );
}
