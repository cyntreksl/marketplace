import { Deferred, Link, useHttp, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    ChevronRight,
    ShieldCheck,
    Sparkles,
    Star,
    Store,
    Truck,
} from 'lucide-react';
import { useEffect } from 'react';
import { ListingCard } from '@/components/listing-card';
import { StorefrontLayout } from '@/components/storefront-layout';
import type { StorefrontCategory } from '@/components/storefront-layout';
import {
    index as listingsIndex,
    recent as recentListings,
    show as listingShow,
} from '@/routes/listings';
import { register as sellerRegister } from '@/routes/seller';
import { index as sellerListingsIndex } from '@/routes/seller/listings';
import { edit as sellerOnboardingEdit } from '@/routes/seller/onboarding';
import type {
    StorefrontCategorySection,
    StorefrontHomepageCategory,
    StorefrontListing,
    StorefrontPromotion,
    StorefrontSocialProof,
} from '@/types';

const recentStorageKey = 'prodeals.recentlyViewedListingIds';

type HomeProps = {
    categories: StorefrontCategory[];
    promotions: {
        hero: StorefrontPromotion[];
        secondary: StorefrontPromotion[];
    };
    popularCategories: StorefrontHomepageCategory[];
    bestOffers: StorefrontListing[];
    newArrivals: StorefrontListing[];
    categorySections?: StorefrontCategorySection[];
    socialProof?: StorefrontSocialProof;
};

function readRecentListingIds(): number[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const stored = JSON.parse(
            window.localStorage.getItem(recentStorageKey) ?? '[]',
        );

        return Array.isArray(stored)
            ? stored
                  .filter((id): id is number => Number.isInteger(id) && id > 0)
                  .slice(0, 12)
            : [];
    } catch {
        return [];
    }
}

function SectionHeading({
    eyebrow,
    title,
    href,
    copy,
}: {
    eyebrow: string;
    title: string;
    href?: ReturnType<typeof listingsIndex>;
    copy?: string;
}) {
    return (
        <div className="mb-6 flex items-end justify-between gap-4">
            <div>
                <p className="text-xs font-black tracking-[0.18em] text-primary uppercase">
                    {eyebrow}
                </p>
                <h2 className="mt-1 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl dark:text-white">
                    {title}
                </h2>
                {copy && (
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        {copy}
                    </p>
                )}
            </div>
            {href && (
                <Link
                    href={href}
                    className="hidden shrink-0 items-center gap-1 text-sm font-black text-primary hover:underline sm:flex"
                >
                    View all <ArrowRight className="size-4" />
                </Link>
            )}
        </div>
    );
}

function ProductRow({ listings }: { listings: StorefrontListing[] }) {
    return (
        <div className="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-3 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-4">
            {listings.map((listing) => (
                <div
                    key={listing.id}
                    className="w-[78vw] max-w-72 shrink-0 snap-start sm:w-auto sm:max-w-none"
                >
                    <ListingCard listing={listing} />
                </div>
            ))}
        </div>
    );
}

function DeferredSkeleton() {
    return (
        <div
            className="mx-auto max-w-[90rem] px-4 py-10 sm:px-6 lg:px-8"
            aria-label="Loading more homepage products"
        >
            <div className="h-4 w-28 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
            <div className="mt-3 h-8 w-72 max-w-full animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
            <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                {[0, 1, 2, 3].map((item) => (
                    <div
                        key={item}
                        className="aspect-[3/4] animate-pulse rounded-2xl bg-slate-200 dark:bg-slate-800"
                    />
                ))}
            </div>
        </div>
    );
}

function CategorySections({
    sections,
}: {
    sections: StorefrontCategorySection[];
}) {
    return sections.map((section, index) => {
        const isTinted = section.variant === 'tinted';
        const isImageLed = section.variant === 'image';

        return (
            <section
                key={section.category.id}
                className={
                    isTinted
                        ? 'bg-teal-50/80 dark:bg-teal-950/15'
                        : 'bg-white dark:bg-slate-950'
                }
            >
                <div
                    className={`mx-auto max-w-[90rem] px-4 py-12 sm:px-6 lg:px-8 ${isImageLed ? 'lg:grid lg:grid-cols-[18rem_1fr] lg:gap-7' : ''}`}
                >
                    <div
                        className={
                            isImageLed
                                ? 'mb-6 flex flex-col justify-end overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-teal-950 to-primary p-7 text-white lg:mb-0'
                                : ''
                        }
                    >
                        <p
                            className={`text-xs font-black tracking-[0.18em] uppercase ${isImageLed ? 'text-amber-300' : 'text-primary'}`}
                        >
                            Curated category {index + 1}
                        </p>
                        <h2 className="mt-2 text-2xl font-black tracking-tight sm:text-3xl">
                            {section.category.name}
                        </h2>
                        <p
                            className={`mt-3 text-sm leading-6 ${isImageLed ? 'text-slate-300' : 'text-slate-500 dark:text-slate-400'}`}
                        >
                            Hand-picked public listings across this category and
                            its subcategories.
                        </p>
                        <Link
                            href={listingsIndex({
                                query: { category: section.category.slug },
                            })}
                            className={`mt-5 inline-flex items-center gap-2 text-sm font-black ${isImageLed ? 'text-white' : 'text-primary'}`}
                        >
                            Explore category <ArrowRight className="size-4" />
                        </Link>
                    </div>
                    <div className={isImageLed ? '' : 'mt-6'}>
                        <div className="-mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-3 sm:mx-0 sm:grid sm:grid-cols-2 sm:px-0 lg:grid-cols-3">
                            {section.listings.map((listing) => (
                                <div
                                    key={listing.id}
                                    className="w-[78vw] max-w-72 shrink-0 snap-start sm:w-auto sm:max-w-none"
                                >
                                    <ListingCard listing={listing} />
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>
        );
    });
}

function Reviews({ socialProof }: { socialProof?: StorefrontSocialProof }) {
    if (
        !socialProof ||
        socialProof.summary.count === 0 ||
        socialProof.reviews.length === 0
    ) {
        return null;
    }

    return (
        <section className="bg-slate-950 text-white">
            <div className="mx-auto max-w-[90rem] px-4 py-14 sm:px-6 lg:px-8">
                <div className="grid gap-8 lg:grid-cols-[18rem_1fr]">
                    <div>
                        <div className="flex items-center gap-2 text-amber-300">
                            <BadgeCheck className="size-5" />
                            <span className="text-xs font-black tracking-[0.16em] uppercase">
                                Verified purchases
                            </span>
                        </div>
                        <h2 className="mt-4 text-3xl font-black tracking-tight">
                            Trusted by real buyers
                        </h2>
                        <div className="mt-5 flex items-end gap-3">
                            <span className="text-5xl font-black">
                                {socialProof.summary.average?.toFixed(1)}
                            </span>
                            <span className="pb-1 text-sm text-slate-400">
                                from {socialProof.summary.count} verified{' '}
                                {socialProof.summary.count === 1
                                    ? 'review'
                                    : 'reviews'}
                            </span>
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {socialProof.reviews.map((review) => (
                            <article
                                key={review.id}
                                className="rounded-2xl border border-white/10 bg-white/5 p-5"
                            >
                                <div
                                    className="flex gap-0.5 text-amber-300"
                                    aria-label={`${review.rating} out of 5 stars`}
                                >
                                    {Array.from({ length: 5 }, (_, index) => (
                                        <Star
                                            key={index}
                                            className={`size-4 ${index < review.rating ? 'fill-current' : 'opacity-25'}`}
                                        />
                                    ))}
                                </div>
                                {review.comment && (
                                    <p className="mt-4 line-clamp-4 text-sm leading-6 text-slate-200">
                                        “{review.comment}”
                                    </p>
                                )}
                                <p className="mt-5 text-sm font-black">
                                    {review.buyerName}
                                </p>
                                {review.listingSlug && (
                                    <Link
                                        href={listingShow(review.listingSlug)}
                                        className="mt-1 block truncate text-xs text-teal-300 hover:underline"
                                    >
                                        {review.listingTitle}
                                    </Link>
                                )}
                            </article>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}

function RecentlyViewed() {
    const http = useHttp<{ ids: number[] }, { listings: StorefrontListing[] }>(
        () => ({ ids: readRecentListingIds() }),
    );

    useEffect(() => {
        if (http.data.ids.length > 0) {
            void http.get(recentListings.url()).catch(() => undefined);
        }
        // The stored IDs are intentionally read once per homepage mount.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (
        http.data.ids.length === 0 ||
        (!http.processing && (http.response?.listings.length ?? 0) === 0)
    ) {
        return null;
    }

    return (
        <section className="bg-slate-100 dark:bg-slate-900/50">
            <div className="mx-auto max-w-[90rem] px-4 py-12 sm:px-6 lg:px-8">
                <SectionHeading
                    eyebrow="Continue browsing"
                    title="Recently viewed"
                    copy="Pick up where you left off. This history stays on this device."
                />
                {http.processing && !http.response ? (
                    <DeferredSkeleton />
                ) : (
                    <ProductRow listings={http.response?.listings ?? []} />
                )}
            </div>
        </section>
    );
}

export default function StorefrontHome({
    categories,
    promotions,
    popularCategories,
    bestOffers,
    newArrivals,
    categorySections,
    socialProof,
}: HomeProps) {
    const { auth } = usePage().props;
    const hero = promotions.hero[0];
    const sellerDestination = auth.is_seller
        ? sellerListingsIndex()
        : auth.user
          ? sellerOnboardingEdit()
          : sellerRegister();

    return (
        <StorefrontLayout
            title="Sri Lanka’s marketplace for better deals"
            categories={categories}
        >
            <main className="overflow-hidden bg-[#f7f5ef] dark:bg-slate-950">
                <section className="mx-auto max-w-[90rem] px-4 py-5 sm:px-6 lg:px-8 lg:py-7">
                    <div className="grid gap-4 lg:grid-cols-[1.7fr_1fr]">
                        {hero && (
                            <Link
                                href={hero.linkUrl ?? listingsIndex()}
                                className="group relative min-h-[28rem] overflow-hidden rounded-3xl bg-slate-950 text-white shadow-2xl shadow-slate-950/15 sm:min-h-[34rem]"
                            >
                                <img
                                    src={hero.imageUrl}
                                    alt={hero.title}
                                    className="absolute inset-0 h-full w-full object-cover opacity-80 transition duration-700 group-hover:scale-[1.02]"
                                />
                                <div className="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/65 to-transparent" />
                                <div className="relative flex h-full max-w-2xl flex-col justify-end p-7 sm:p-10 lg:p-12">
                                    <span className="inline-flex w-max items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-black tracking-[0.16em] text-teal-200 uppercase ring-1 ring-white/20 backdrop-blur">
                                        <Sparkles className="size-4" /> Discover
                                        ProDeals
                                    </span>
                                    <h1 className="mt-5 text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">
                                        {hero.title}
                                    </h1>
                                    <p className="mt-4 max-w-xl text-base leading-7 text-slate-200">
                                        Shop trusted local listings, visible
                                        savings, and unique finds from sellers
                                        across Sri Lanka.
                                    </p>
                                    <span className="mt-7 inline-flex w-max items-center gap-2 rounded-xl bg-amber-400 px-5 py-3 font-black text-slate-950 transition group-hover:bg-amber-300">
                                        Explore deals{' '}
                                        <ArrowRight className="size-4" />
                                    </span>
                                </div>
                            </Link>
                        )}
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                            {promotions.secondary.map((promotion, index) => (
                                <Link
                                    key={`${promotion.id ?? 'fallback'}-${index}`}
                                    href={promotion.linkUrl ?? listingsIndex()}
                                    className="group relative min-h-56 overflow-hidden rounded-3xl bg-primary text-white shadow-lg"
                                >
                                    <img
                                        src={promotion.imageUrl}
                                        alt={promotion.title}
                                        className="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                    />
                                    <div className="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/25 to-transparent" />
                                    <div className="absolute inset-x-0 bottom-0 p-6">
                                        <p className="text-2xl font-black tracking-tight">
                                            {promotion.title}
                                        </p>
                                        <span className="mt-2 inline-flex items-center gap-1 text-sm font-bold text-teal-200">
                                            Shop collection{' '}
                                            <ChevronRight className="size-4" />
                                        </span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                    <div className="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3 dark:border-slate-800 dark:bg-slate-900">
                        {[
                            [
                                ShieldCheck,
                                'Buyer-first marketplace',
                                'Shop approved public listings',
                            ],
                            [
                                Truck,
                                'Islandwide discovery',
                                'Find sellers across Sri Lanka',
                            ],
                            [
                                Store,
                                'Local seller variety',
                                'New choices added by sellers',
                            ],
                        ].map(([Icon, title, copy]) => (
                            <div
                                key={String(title)}
                                className="flex items-center gap-3 px-2 py-1"
                            >
                                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-teal-50 text-primary dark:bg-teal-950">
                                    <Icon className="size-5" />
                                </span>
                                <div>
                                    <p className="text-sm font-black">
                                        {String(title)}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        {String(copy)}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                {popularCategories.length > 0 && (
                    <section className="bg-white dark:bg-slate-950">
                        <div className="mx-auto max-w-[90rem] px-4 py-11 sm:px-6 lg:px-8">
                            <SectionHeading
                                eyebrow="Shop your way"
                                title="Popular Categories"
                                copy="Jump into the departments shoppers are exploring now."
                            />
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-10">
                                {popularCategories.map((category, index) => (
                                    <Link
                                        key={category.id}
                                        href={listingsIndex({
                                            query: { category: category.slug },
                                        })}
                                        className="group flex min-h-28 flex-col items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 p-3 text-center transition hover:-translate-y-1 hover:border-primary/40 hover:bg-teal-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-teal-950/40"
                                    >
                                        <span
                                            className={`grid size-11 place-items-center rounded-2xl text-lg font-black ${index % 3 === 0 ? 'bg-amber-100 text-amber-800' : index % 3 === 1 ? 'bg-teal-100 text-teal-800' : 'bg-slate-200 text-slate-700'}`}
                                        >
                                            {category.name.charAt(0)}
                                        </span>
                                        <span className="mt-3 line-clamp-2 text-xs font-black text-slate-800 group-hover:text-primary dark:text-slate-100">
                                            {category.name}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                {bestOffers.length > 0 && (
                    <section className="bg-amber-50/80 dark:bg-amber-950/10">
                        <div className="mx-auto max-w-[90rem] px-4 py-12 sm:px-6 lg:px-8">
                            <SectionHeading
                                eyebrow="Limited-time value"
                                title="Best Offers"
                                href={listingsIndex({
                                    query: { sort: 'price_asc' },
                                })}
                                copy="Admin-curated discounts from approved Buy Now listings."
                            />
                            <ProductRow listings={bestOffers} />
                        </div>
                    </section>
                )}
                {newArrivals.length > 0 && (
                    <section className="bg-white dark:bg-slate-950">
                        <div className="mx-auto max-w-[90rem] px-4 py-12 sm:px-6 lg:px-8">
                            <SectionHeading
                                eyebrow="Just listed"
                                title="New Arrivals"
                                href={listingsIndex()}
                                copy="Freshly curated listings worth seeing before everyone else."
                            />
                            <ProductRow listings={newArrivals} />
                        </div>
                    </section>
                )}

                <Deferred
                    data={['categorySections', 'socialProof']}
                    fallback={<DeferredSkeleton />}
                >
                    <CategorySections sections={categorySections ?? []} />
                    <Reviews socialProof={socialProof} />
                </Deferred>

                <section className="bg-primary text-primary-foreground">
                    <div className="mx-auto flex max-w-[90rem] flex-col justify-between gap-5 px-4 py-9 sm:flex-row sm:items-center sm:px-6 lg:px-8">
                        <div>
                            <p className="text-xs font-black tracking-[0.16em] text-teal-100 uppercase">
                                Grow with ProDeals
                            </p>
                            <h2 className="mt-2 text-2xl font-black">
                                Turn your products into someone’s next great
                                find.
                            </h2>
                        </div>
                        <Link
                            href={sellerDestination}
                            className="inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-5 font-black text-primary"
                        >
                            {auth.is_seller ? 'Seller portal' : 'Start selling'}{' '}
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </section>

                <RecentlyViewed />
            </main>
        </StorefrontLayout>
    );
}
