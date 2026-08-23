import { Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Box,
    Cpu,
    Gamepad2,
    Headphones,
    House,
    Smartphone,
    Sparkles,
    Zap,
} from 'lucide-react';
import { ListingCard } from '@/components/listing-card';
import { StorefrontLayout } from '@/components/storefront-layout';
import { index as listingsIndex } from '@/routes/listings';
import { edit as sellerOnboardingEdit } from '@/routes/seller/onboarding';
import { register as vendorRegister } from '@/routes/vendor';

export default function StorefrontHome({
    featuredListings,
    categories,
}: {
    featuredListings: { data: any[] };
    categories: { id: number; name: string; slug: string }[];
}) {
    const categoryIcons = [Smartphone, Cpu, Headphones, Gamepad2, House, Box];
    const { auth } = usePage().props;
    const vendorRegistration = auth.user
        ? sellerOnboardingEdit()
        : vendorRegister();

    return (
        <StorefrontLayout
            title="Discover your next device"
            categories={categories}
        >
            <main className="overflow-hidden bg-slate-100/70 dark:bg-slate-950">
                <section className="px-4 py-7 sm:px-7">
                    <div className="mx-auto grid max-w-none gap-5 xl:grid-cols-2">
                        <div className="relative min-h-[32rem] overflow-hidden rounded-2xl bg-blue-700 p-7 text-white shadow-xl shadow-blue-950/15 sm:p-10 lg:min-h-[38rem]">
                            <div className="absolute -top-24 -right-20 size-64 rounded-full bg-cyan-300/20 blur-2xl" />
                            <div className="absolute -bottom-28 left-1/2 size-72 rounded-full bg-indigo-950/30 blur-2xl" />
                            <div className="relative flex h-full max-w-2xl flex-col justify-center">
                                <p className="flex items-center gap-2 text-sm font-bold tracking-[.18em] text-blue-100 uppercase">
                                    <Sparkles className="size-4" />
                                    Everyday marketplace deals
                                </p>
                                <h1 className="mt-5 text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">
                                    Find something brilliant, every day.
                                </h1>
                                <p className="mt-5 max-w-xl text-base leading-7 text-blue-100 sm:text-lg">
                                    Shop verified electronics, join live
                                    auctions, and get your next great find
                                    delivered to your door.
                                </p>
                                <div className="mt-8 flex flex-wrap gap-3">
                                    <Link
                                        href={listingsIndex()}
                                        className="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3 font-bold text-blue-700 shadow-lg shadow-blue-950/20 transition hover:bg-blue-50"
                                    >
                                        Explore deals
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
                                <div className="absolute -right-10 -bottom-16 size-72 rounded-full border-[30px] border-blue-400/25" />
                                <div className="relative max-w-sm">
                                    <p className="text-sm font-bold tracking-wider text-blue-700 uppercase dark:text-blue-300">
                                        Everyday essentials
                                    </p>
                                    <p className="mt-4 text-4xl font-black tracking-tight sm:text-5xl">
                                        Modern home,
                                        <br />
                                        better living.
                                    </p>
                                    <p className="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                        Discover quality tech and home finds
                                        from trusted local sellers.
                                    </p>
                                    <Link
                                        href={listingsIndex()}
                                        className="mt-7 inline-flex rounded-lg border-2 border-blue-600 px-5 py-2.5 text-sm font-bold text-blue-700 transition hover:bg-blue-600 hover:text-white dark:text-blue-300"
                                    >
                                        Shop now
                                    </Link>
                                </div>
                            </div>
                            <div className="grid gap-5 sm:col-span-2 sm:grid-cols-2 xl:col-span-1">
                                <Link
                                    href={listingsIndex()}
                                    className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-700 via-blue-500 to-cyan-300 p-6 text-white shadow-lg shadow-blue-950/10"
                                >
                                    <p className="text-sm font-bold tracking-wider text-blue-100 uppercase">
                                        Member rewards
                                    </p>
                                    <p className="mt-3 text-3xl font-black">
                                        10% back
                                    </p>
                                    <p className="mt-1 text-sm text-blue-50">
                                        On selected marketplace finds.
                                    </p>
                                </Link>
                                <Link
                                    href={listingsIndex()}
                                    className="relative overflow-hidden rounded-2xl bg-slate-950 p-6 text-white shadow-lg shadow-slate-950/15"
                                >
                                    <p className="text-sm font-bold tracking-wider text-cyan-300 uppercase">
                                        Flexible checkout
                                    </p>
                                    <p className="mt-3 text-3xl font-black">
                                        Buy now.
                                    </p>
                                    <p className="mt-1 text-sm text-slate-300">
                                        Secure checkout and delivery tracking.
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
                                Grow with CircuitMarket
                            </p>
                            <h2 className="mt-2 text-2xl font-black tracking-tight">
                                Have products to sell?
                            </h2>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                                Open your store and submit your details for our
                                vendor review.
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
                            ['Happy customers', '0'],
                            ['Orders delivered', '0'],
                            ['24/7 support', '0'],
                        ].map(([title, value]) => (
                            <div className="text-center" key={title}>
                                <p className="text-4xl font-black text-blue-600">
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
                    <div className="mx-auto flex max-w-none items-center gap-5 overflow-hidden rounded-sm bg-gradient-to-r from-blue-700 via-blue-500 to-cyan-400 px-5 py-3 text-sm font-bold text-white">
                        <span className="flex shrink-0 items-center gap-2 rounded bg-white/15 px-3 py-1.5 text-xs tracking-wider uppercase">
                            <Zap className="size-4" /> Live offers
                        </span>
                        <span className="whitespace-nowrap">
                            New deals added daily
                        </span>
                        <span className="hidden h-1.5 w-1.5 shrink-0 rounded-full bg-white/70 sm:block" />
                        <span className="hidden whitespace-nowrap sm:block">
                            Islandwide delivery available
                        </span>
                        <span className="hidden h-1.5 w-1.5 shrink-0 rounded-full bg-white/70 lg:block" />
                        <span className="hidden whitespace-nowrap lg:block">
                            Secure checkout on every order
                        </span>
                    </div>
                </section>
                <section className="bg-white px-4 py-12 sm:px-7 dark:bg-slate-950">
                    <div className="mx-auto max-w-none">
                        <div className="mb-7 flex items-end justify-between gap-4">
                            <div>
                                <p className="text-sm font-bold tracking-wider text-blue-700 uppercase dark:text-blue-300">
                                    Start exploring
                                </p>
                                <h2 className="mt-1 text-3xl font-black tracking-tight">
                                    Shop by category
                                </h2>
                            </div>
                            <Link
                                className="hidden items-center gap-1 font-bold text-blue-700 hover:text-blue-800 sm:inline-flex dark:text-blue-300"
                                href={listingsIndex()}
                            >
                                View all categories{' '}
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            {categories.map((category, index) => {
                                const CategoryIcon =
                                    categoryIcons[index % categoryIcons.length];

                                return (
                                    <Link
                                        className="group rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-lg hover:shadow-blue-950/5 dark:border-slate-800 dark:bg-slate-900"
                                        href={listingsIndex({
                                            query: {
                                                category: category.slug,
                                            },
                                        })}
                                        key={category.id}
                                    >
                                        <span className="grid size-10 place-items-center rounded-xl bg-blue-50 text-blue-700 transition group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-950 dark:text-blue-300">
                                            <CategoryIcon className="size-5" />
                                        </span>
                                        <span className="mt-5 block font-bold text-slate-900 dark:text-white">
                                            {category.name}
                                        </span>
                                        <span className="mt-1 block text-xs text-slate-500">
                                            Explore deals
                                        </span>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </section>
                <section className="bg-slate-100/70 px-4 py-12 sm:px-7 dark:bg-slate-900/40">
                    <div className="mx-auto max-w-none">
                        <div className="mb-7 flex items-end justify-between gap-4">
                            <div>
                                <p className="text-sm font-bold tracking-wider text-blue-700 uppercase dark:text-blue-300">
                                    Curated for you
                                </p>
                                <h2 className="mt-1 text-3xl font-black tracking-tight">
                                    Fresh marketplace finds
                                </h2>
                            </div>
                            <Link
                                href={listingsIndex()}
                                className="hidden items-center gap-1 font-bold text-blue-700 hover:text-blue-800 sm:inline-flex dark:text-blue-300"
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
