import { Form } from '@inertiajs/react';
import { ListingCard } from '@/components/listing-card';
import { StorefrontLayout } from '@/components/storefront-layout';
import { index as listingsIndex } from '@/routes/listings';

export default function ListingsIndex({
    listings,
    categories,
    filters,
}: {
    listings: {
        data: any[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    categories: { id: number; name: string; slug: string }[];
    filters: Record<string, string>;
}) {
    return (
        <StorefrontLayout title="Browse electronics">
            <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="mb-8">
                    <p className="text-sm font-bold tracking-wider text-amber-700 uppercase">
                        Marketplace
                    </p>
                    <h1 className="text-4xl font-black">
                        Find your next device
                    </h1>
                </div>
                <Form
                    {...listingsIndex.form()}
                    className="mb-8 grid gap-3 rounded-2xl border border-stone-200 bg-white p-4 md:grid-cols-5 dark:border-stone-800 dark:bg-stone-900"
                >
                    <input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search products"
                        className="rounded-lg border bg-transparent px-3 py-2 md:col-span-2"
                    />
                    <select
                        name="category"
                        defaultValue={filters.category}
                        className="rounded-lg border bg-transparent px-3 py-2"
                    >
                        <option value="">All categories</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.slug}>
                                {c.name}
                            </option>
                        ))}
                    </select>
                    <select
                        name="listing_type"
                        defaultValue={filters.listing_type}
                        className="rounded-lg border bg-transparent px-3 py-2"
                    >
                        <option value="">All listing types</option>
                        <option value="auction">Auctions</option>
                        <option value="buy_now">Buy now</option>
                    </select>
                    <button className="rounded-lg bg-stone-950 px-4 py-2 font-bold text-white dark:bg-stone-50 dark:text-stone-950">
                        Apply filters
                    </button>
                </Form>
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {listings.data.map((listing) => (
                        <ListingCard key={listing.id} listing={listing} />
                    ))}
                </div>
                {listings.data.length === 0 && (
                    <p className="rounded-2xl border border-dashed p-12 text-center text-stone-500">
                        No approved listings match those filters.
                    </p>
                )}
            </main>
        </StorefrontLayout>
    );
}
