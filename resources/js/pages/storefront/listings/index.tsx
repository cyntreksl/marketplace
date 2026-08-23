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
        <StorefrontLayout title="Browse electronics" categories={categories}>
            <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="mb-8">
                    <p className="text-sm font-bold tracking-wider text-blue-700 uppercase dark:text-blue-300">
                        Marketplace
                    </p>
                    <h1 className="mt-1 text-4xl font-black tracking-tight">
                        Find your next device
                    </h1>
                </div>
                <Form
                    {...listingsIndex.form()}
                    className="mb-8 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/50 md:grid-cols-5 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none"
                >
                    <input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search products"
                        className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100 md:col-span-2 dark:border-slate-700 dark:bg-slate-950 dark:focus:ring-blue-950"
                    />
                    <select
                        name="category"
                        defaultValue={filters.category}
                        className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-950 dark:focus:ring-blue-950"
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
                        className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm transition outline-none focus:border-blue-500 focus:bg-white focus:ring-4 focus:ring-blue-100 dark:border-slate-700 dark:bg-slate-950 dark:focus:ring-blue-950"
                    >
                        <option value="">All listing types</option>
                        <option value="auction">Auctions</option>
                        <option value="buy_now">Buy now</option>
                    </select>
                    <button className="rounded-xl bg-blue-600 px-4 py-2 font-bold text-white shadow-sm shadow-blue-600/25 transition hover:bg-blue-700">
                        Apply filters
                    </button>
                </Form>
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {listings.data.map((listing) => (
                        <ListingCard key={listing.id} listing={listing} />
                    ))}
                </div>
                {listings.data.length === 0 && (
                    <p className="rounded-2xl border border-dashed border-blue-200 bg-blue-50/50 p-12 text-center text-slate-500 dark:border-slate-700 dark:bg-slate-900">
                        No approved listings match those filters.
                    </p>
                )}
            </main>
        </StorefrontLayout>
    );
}
