import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { CategoryPicker } from '@/components/category-picker';
import type { CategoryOption } from '@/components/category-picker';
import { ListingCard } from '@/components/listing-card';
import { StorefrontLayout } from '@/components/storefront-layout';
import type { StorefrontCategory } from '@/components/storefront-layout';
import { index as listingsIndex } from '@/routes/listings';

export default function ListingsIndex({
    listings,
    categories,
    filters,
    selectedCategory,
}: {
    listings: {
        data: any[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    categories: StorefrontCategory[];
    filters: Record<string, string>;
    selectedCategory: CategoryOption | null;
}) {
    const [category, setCategory] = useState<CategoryOption | null>(
        selectedCategory,
    );

    return (
        <StorefrontLayout title="Browse deals" categories={categories}>
            <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="mb-8">
                    <p className="text-sm font-bold tracking-wider text-primary uppercase">
                        ProDeals.lk marketplace
                    </p>
                    <h1 className="mt-1 text-4xl font-black tracking-tight">
                        Find your next great deal
                    </h1>
                </div>
                <Form
                    {...listingsIndex.form()}
                    className="mb-8 grid items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/50 md:grid-cols-2 lg:grid-cols-5 dark:border-slate-800 dark:bg-slate-900 dark:shadow-none"
                >
                    <input
                        name="search"
                        defaultValue={filters.search}
                        placeholder="Search products"
                        className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm transition outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/20 md:col-span-2 dark:border-slate-700 dark:bg-slate-950"
                    />
                    <div className="md:col-span-2 lg:col-span-2">
                        <input
                            type="hidden"
                            name="category"
                            value={category?.slug ?? ''}
                        />
                        <CategoryPicker
                            label="Browse category"
                            selected={category}
                            onSelect={setCategory}
                            selectionMode="any"
                        />
                    </div>
                    <select
                        name="listing_type"
                        defaultValue={filters.listing_type}
                        className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm transition outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-950"
                    >
                        <option value="">All listing types</option>
                        <option value="auction">Auctions</option>
                        <option value="buy_now">Buy now</option>
                    </select>
                    <button className="rounded-xl bg-primary px-4 py-2 font-bold text-primary-foreground shadow-sm shadow-primary/25 transition hover:bg-primary/90">
                        Apply filters
                    </button>
                </Form>
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {listings.data.map((listing) => (
                        <ListingCard key={listing.id} listing={listing} />
                    ))}
                </div>
                {listings.data.length === 0 && (
                    <p className="rounded-2xl border border-dashed border-primary/30 bg-primary/5 p-12 text-center text-slate-500 dark:border-slate-700 dark:bg-slate-900">
                        No approved listings match those filters.
                    </p>
                )}
            </main>
        </StorefrontLayout>
    );
}
