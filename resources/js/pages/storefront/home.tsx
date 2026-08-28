import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Sparkles, Zap } from 'lucide-react';
import { ListingCard } from '@/components/listing-card';
import { StorefrontLayout } from '@/components/storefront-layout';
import type { StorefrontCategory } from '@/components/storefront-layout';
import { index as listingsIndex } from '@/routes/listings';
import { edit as sellerOnboardingEdit } from '@/routes/seller/onboarding';
import { register as vendorRegister } from '@/routes/vendor';

export default function StorefrontHome({
    featuredListings,
    categories,
}: {
    featuredListings: { data: any[] };
    categories: StorefrontCategory[];
}) {
    const { auth } = usePage().props;
    const vendorRegistration = auth.user
        ? sellerOnboardingEdit()
        : vendorRegister();

    return (
        <StorefrontLayout title="Discover local deals" categories={categories}>
            <main className="overflow-hidden bg-slate-100/70 dark:bg-slate-950">
                <section className="px-4 py-7 sm:px-7">
                    <div className="mx-auto grid max-w-none gap-5 xl:grid-cols-2">
                        <div className="relative min-h-[32rem] overflow-hidden rounded-2xl bg-primary p-7 text-primary-foreground shadow-xl shadow-primary/15 sm:p-10 lg:min-h-[38rem]">
                            <div className="absolute -top-24 -right-20 size-64 rounded-full bg-cyan-300/20 blur-2xl" />
                            <div className="absolute -bottom-28 left-1/2 size-72 rounded-full bg-indigo-950/30 blur-2xl" />
                            <div className="relative flex h-full max-w-2xl flex-col justify-center">
                                <p className="flex items-center gap-2 text-sm font-bold tracking-[.18em] text-primary-foreground/80 uppercase">
                                    <Sparkles className="size-4" />
                                    Sri Lanka’s marketplace for more
                                </p>
                                <h1 className="mt-5 text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">
                                    Better deals are closer than you think.
                                </h1>
                                <p className="mt-5 max-w-xl text-base leading-7 text-primary-foreground/80 sm:text-lg">
                                    Discover everyday essentials, tech, style,
                                    and unique finds from sellers across Sri
                                    Lanka.
                                </p>
                                <div className="mt-8 flex flex-wrap gap-3">
                                    <Link
                                        href={listingsIndex()}
                                        className="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 font-bold text-primary shadow-lg shadow-primary/20 transition hover:bg-primary-foreground/10"
                                    >
                                        Explore all deals
                                        <ArrowRight className="size-4" />
                                    </Link>
                                    <Link
                                        href={listingsIndex({
                                            query: { listing_type: 'auction' },
                                        })}
                                        className="inline-flex items-center gap-2 rounded-xl border border-white/30 px-5 py-3 font-bold text-white transition hover:bg-white/10"
                                    >
                                        Browse auctions
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div className="grid min-h-[32rem] gap-5 sm:grid-cols-2 lg:min-h-[38rem] xl:grid-cols-1 xl:grid-rows-[1.85fr_1fr]">
                            <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-50 via-amber-50 to-stone-200 p-7 text-slate-950 shadow-lg shadow-slate-950/5 dark:from-slate-900 dark:via-slate-900 dark:to-slate-800 dark:text-white">
                                <div className="absolute -right-10 -bottom-16 size-72 rounded-full border-[30px] border-primary/25" />
                                <div className="relative max-w-sm">
                                    <p className="text-sm font-bold tracking-wider text-primary uppercase">
                                        More to discover
                                    </p>
                                    <p className="mt-4 text-4xl font-black tracking-tight sm:text-5xl">
                                        Better everyday,
                                        <br />
                                        brilliantly found.
                                    </p>
                                    <p className="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                        Browse a growing range of local finds,
                                        from practical essentials to your next
                                        favourite thing.
                                    </p>
                                    <Link
                                        href={listingsIndex()}
                                        className="mt-7 inline-flex rounded-lg border-2 border-primary px-5 py-2.5 text-sm font-bold text-primary transition hover:bg-primary hover:text-primary-foreground"
                                    >
                                        See what’s new
                                    </Link>
                                </div>
                            </div>
                            <div className="grid gap-5 sm:col-span-2 sm:grid-cols-2 xl:col-span-1">
                                <Link
                                    href={listingsIndex()}
                                    className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary/80 to-primary/50 p-6 text-primary-foreground shadow-lg shadow-primary/10"
                                >
                                    <p className="text-sm font-bold tracking-wider text-primary-foreground/80 uppercase">
                                        More to explore
                                    </p>
                                    <p className="mt-3 text-3xl font-black">
                                        Fresh finds
                                    </p>
                                    <p className="mt-1 text-sm text-primary-foreground/80">
                                        Discover something worth sharing.
                                    </p>
                                </Link>
                                <Link
                                    href={listingsIndex()}
                                    className="relative overflow-hidden rounded-2xl bg-slate-950 p-6 text-white shadow-lg shadow-slate-950/15"
                                >
                                    <p className="text-sm font-bold tracking-wider text-cyan-300 uppercase">
                                        Browse your way
                                    </p>
                                    <p className="mt-3 text-3xl font-black">
                                        Make it yours.
                                    </p>
                                    <p className="mt-1 text-sm text-slate-300">
                                        Compare listings and choose with
                                        confidence.
                                    </p>
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
                <section className="px-4 pb-7 sm:px-7">
                    <div className="mx-auto flex max-w-none flex-col justify-between gap-5 rounded-2xl bg-slate-950 px-6 py-7 text-white shadow-xl shadow-slate-950/15 sm:flex-row sm:items-center sm:px-8">
                        <div>
                            <p className="text-sm font-bold tracking-wider text-cyan-300 uppercase">
                                Grow with ProDeals.lk
                            </p>
                            <h2 className="mt-2 text-2xl font-black tracking-tight">
                                Have products to sell?
                            </h2>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                                Share what you sell with shoppers looking for
                                their next great find.
                            </p>
                        </div>
                        <Link
                            href={vendorRegistration}
                            className="inline-flex h-12 shrink-0 items-center justify-center gap-2 rounded-xl bg-cyan-300 px-5 font-bold text-slate-950 transition hover:bg-cyan-200"
                        >
                            Become a vendor
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </section>
                <section className="px-4 pb-7 sm:px-7">
                    <div className="mx-auto grid max-w-none gap-4 rounded-2xl bg-white px-6 py-7 shadow-sm sm:grid-cols-3 dark:bg-slate-900">
                        {[
                            ['A marketplace made for discovery', 'Explore'],
                            ['All kinds of everyday finds', 'Browse'],
                            ['A simple way to start selling', 'Sell'],
                        ].map(([title, value]) => (
                            <div className="text-center" key={title}>
                                <p className="text-sm font-black tracking-[0.16em] text-primary uppercase">
                                    {value}
                                </p>
                                <p className="mt-2 text-sm font-bold text-slate-800 capitalize dark:text-white">
                                    {title}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>
                <section className="px-4 pb-7 sm:px-7">
                    <div className="mx-auto flex max-w-none items-center gap-5 overflow-hidden rounded-sm bg-gradient-to-r from-primary via-primary/80 to-primary/50 px-5 py-3 text-sm font-bold text-primary-foreground">
                        <span className="flex shrink-0 items-center gap-2 rounded bg-white/15 px-3 py-1.5 text-xs tracking-wider uppercase">
                            <Zap className="size-4" /> Live offers
                        </span>
                        <span className="whitespace-nowrap">
                            Fresh marketplace finds
                        </span>
                        <span className="hidden h-1.5 w-1.5 shrink-0 rounded-full bg-white/70 sm:block" />
                        <span className="hidden whitespace-nowrap sm:block">
                            Shop across every category
                        </span>
                        <span className="hidden h-1.5 w-1.5 shrink-0 rounded-full bg-white/70 lg:block" />
                        <span className="hidden whitespace-nowrap lg:block">
                            Better deals. Closer to home.
                        </span>
                    </div>
                </section>
                <section className="bg-slate-100/70 px-4 py-12 sm:px-7 dark:bg-slate-900/40">
                    <div className="mx-auto max-w-none">
                        <div className="mb-7 flex items-end justify-between gap-4">
                            <div>
                                <p className="text-sm font-bold tracking-wider text-primary uppercase">
                                    Worth a look
                                </p>
                                <h2 className="mt-1 text-3xl font-black tracking-tight">
                                    Deals to discover
                                </h2>
                            </div>
                            <Link
                                href={listingsIndex()}
                                className="hidden items-center gap-1 font-bold text-primary hover:text-primary/80 sm:inline-flex"
                            >
                                See all products{' '}
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {featuredListings.data.map((listing) => (
                                <ListingCard
                                    key={listing.id}
                                    listing={listing}
                                />
                            ))}
                        </div>
                    </div>
                </section>
            </main>
        </StorefrontLayout>
    );
}
