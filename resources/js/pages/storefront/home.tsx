import { Link } from '@inertiajs/react';
import { ListingCard } from '@/components/listing-card';
import { StorefrontLayout } from '@/components/storefront-layout';
import { index as listingsIndex } from '@/routes/listings';

export default function StorefrontHome({
    featuredListings,
    categories,
}: {
    featuredListings: { data: any[] };
    categories: { id: number; name: string; slug: string }[];
}) {
    return (
        <StorefrontLayout title="Discover your next device">
            <main>
                <section className="bg-stone-950 px-4 py-20 text-white sm:px-6 lg:px-8">
                    <div className="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.2fr_.8fr]">
                        <div>
                            <p className="mb-4 text-sm font-bold tracking-[.2em] text-amber-400 uppercase">
                                Shop with confidence
                            </p>
                            <h1 className="max-w-3xl text-5xl font-black tracking-tight sm:text-7xl">
                                The electronics you want, from sellers you can
                                trust.
                            </h1>
                            <p className="mt-6 max-w-xl text-lg text-stone-300">
                                Discover new and verified used devices, bid in
                                live auctions, or buy now with secure checkout
                                and delivery tracking.
                            </p>
                            <Link
                                href={listingsIndex()}
                                className="mt-8 inline-flex rounded-full bg-amber-400 px-6 py-3 font-bold text-stone-950"
                            >
                                Explore the marketplace
                            </Link>
                        </div>
                        <div className="rounded-3xl bg-gradient-to-br from-amber-400 to-orange-600 p-8 text-stone-950">
                            <p className="text-sm font-bold tracking-wider uppercase">
                                Live marketplace
                            </p>
                            <p className="mt-10 text-6xl font-black">24/7</p>
                            <p className="mt-3 max-w-xs font-medium">
                                Set your maximum bid. We’ll do the rest,
                                including fair anti-sniping extensions.
                            </p>
                        </div>
                    </div>
                </section>
                <section className="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                    <div className="mb-8 flex items-end justify-between">
                        <div>
                            <p className="text-sm font-bold tracking-wider text-amber-700 uppercase">
                                Start exploring
                            </p>
                            <h2 className="text-3xl font-black">
                                Shop by category
                            </h2>
                        </div>
                        <Link
                            className="font-bold text-amber-700"
                            href={listingsIndex()}
                        >
                            View all
                        </Link>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {categories.map((category) => (
                            <Link
                                className="rounded-full border border-stone-300 bg-white px-5 py-3 font-semibold hover:border-amber-500 dark:border-stone-700 dark:bg-stone-900"
                                href={listingsIndex({
                                    query: { category: category.slug },
                                })}
                                key={category.id}
                            >
                                {category.name}
                            </Link>
                        ))}
                    </div>
                </section>
                <section className="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">
                    <div className="mb-8">
                        <p className="text-sm font-bold tracking-wider text-amber-700 uppercase">
                            Curated for you
                        </p>
                        <h2 className="text-3xl font-black">
                            Fresh marketplace finds
                        </h2>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {featuredListings.data.map((listing) => (
                            <ListingCard key={listing.id} listing={listing} />
                        ))}
                    </div>
                </section>
            </main>
        </StorefrontLayout>
    );
}
