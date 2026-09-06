import { Form, Link } from '@inertiajs/react';
import { Search, Store, ArrowRight } from 'lucide-react';
import { ListingCard } from '@/components/listing-card';
import { SellerLogo } from '@/components/seller-summary';
import { StorefrontBreadcrumbs } from '@/components/storefront-breadcrumbs';
import { StorefrontLayout } from '@/components/storefront-layout';
import { StorefrontPagination } from '@/components/storefront-pagination';
import { home } from '@/routes';
import { show as storeShow } from '@/routes/stores';
import type {
    PublicSellerSummary,
    StorefrontCategory,
    StorefrontListingPaginator,
    StorefrontBrowseFilters,
} from '@/types';

export default function StoreShow({
    seller,
    listings,
    categories,
    filters,
}: {
    seller: PublicSellerSummary;
    listings: StorefrontListingPaginator;
    categories: StorefrontCategory[];
    filters: StorefrontBrowseFilters;
}) {
    return (
        <StorefrontLayout title={seller.store_name} categories={categories}>
            <main className="storefront-container py-5">
                <StorefrontBreadcrumbs
                    items={[
                        { label: 'Home', href: home.url() },
                        { label: seller.store_name },
                    ]}
                />
                <section className="mt-5 overflow-hidden rounded-3xl border border-orange-100 bg-white shadow-sm">
                    <div className="relative aspect-4/1 min-h-32 overflow-hidden bg-linear-to-br from-orange-100 via-orange-50 to-amber-100">
                        {seller.coverUrl ? (
                            <img
                                src={seller.coverUrl}
                                alt={`${seller.store_name} store cover`}
                                width={1600}
                                height={400}
                                fetchPriority="high"
                                className="size-full object-cover"
                            />
                        ) : (
                            <div className="absolute inset-0 flex items-center justify-end px-10 text-orange-300/60">
                                <Store className="size-24" strokeWidth={1} />
                            </div>
                        )}
                    </div>
                    <div className="relative px-5 pb-6 sm:px-8">
                        <div className="flex flex-wrap items-end gap-4">
                            <SellerLogo
                                seller={seller}
                                className="-mt-8 size-24 border-4 border-white shadow-sm"
                            />
                            <div className="min-w-40 flex-1 pt-4">
                                <p className="text-sm font-semibold text-orange-700">
                                    Approved marketplace seller
                                </p>
                                <h1 className="mt-1 text-2xl font-black tracking-tight break-words sm:text-3xl">
                                    {seller.store_name}
                                </h1>
                            </div>
                            <a
                                href="#store-products"
                                className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 text-sm font-bold text-white sm:w-auto"
                            >
                                Shop the store <ArrowRight className="size-4" />
                            </a>
                        </div>
                        <div className="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-600">
                            <span>
                                {seller.productCount} available products
                            </span>
                            {seller.sellingSince && (
                                <span>Selling since {seller.sellingSince}</span>
                            )}
                        </div>
                        {seller.about && (
                            <div className="mt-5 border-t border-slate-100 pt-5">
                                <h2 className="text-base font-bold">
                                    About {seller.store_name}
                                </h2>
                                <p className="mt-2 max-w-3xl text-base leading-7 whitespace-pre-line text-slate-600">
                                    {seller.about}
                                </p>
                            </div>
                        )}
                    </div>
                </section>
                <section id="store-products" className="scroll-mt-40 py-8">
                    <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight">
                                Explore the store
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                {listings.total} matching products
                            </p>
                        </div>
                    </div>
                    <Form
                        {...storeShow.form(seller.slug)}
                        className="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-3 sm:grid-cols-[minmax(0,1fr)_auto_auto_auto]"
                    >
                        <label className="flex min-h-11 items-center gap-2 rounded-xl bg-slate-50 px-3">
                            <Search className="size-4 shrink-0 text-slate-400" />
                            <span className="sr-only">Search this store</span>
                            <input
                                key={filters.search ?? ''}
                                name="search"
                                defaultValue={filters.search ?? ''}
                                placeholder="Search this store"
                                className="w-full min-w-0 bg-transparent text-base outline-none"
                            />
                        </label>
                        <label className="grid">
                            <span className="sr-only">Category</span>
                            <select
                                key={filters.category ?? ''}
                                name="category"
                                defaultValue={filters.category ?? ''}
                                className="min-h-11 min-w-0 rounded-xl border border-slate-200 px-3 text-base"
                            >
                                <option value="">All categories</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.slug}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid">
                            <span className="sr-only">Sort products</span>
                            <select
                                key={filters.sort}
                                name="sort"
                                defaultValue={filters.sort}
                                className="min-h-11 rounded-xl border border-slate-200 px-3 text-base"
                            >
                                <option value="newest">Newest first</option>
                                <option value="price_asc">
                                    Price: low to high
                                </option>
                                <option value="price_desc">
                                    Price: high to low
                                </option>
                            </select>
                        </label>
                        <button className="min-h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white">
                            Apply
                        </button>
                    </Form>
                    {listings.data.length ? (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                            {listings.data.map((listing) => (
                                <ListingCard
                                    key={listing.id}
                                    listing={listing}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-3xl border border-dashed border-orange-200 bg-orange-50/40 p-10 text-center">
                            <Store className="mx-auto size-10 text-orange-500" />
                            <h3 className="mt-4 text-xl font-bold">
                                {seller.productCount
                                    ? 'No matching products'
                                    : 'New finds are on their way'}
                            </h3>
                            <p className="mt-2 text-base text-slate-600">
                                {seller.productCount
                                    ? 'Try another search or clear your filters.'
                                    : 'This store has no available products right now. Check back soon.'}
                            </p>
                            {seller.productCount > 0 && (
                                <Link
                                    href={storeShow(seller.slug)}
                                    className="mt-4 inline-block font-bold text-orange-700"
                                >
                                    Clear filters
                                </Link>
                            )}
                        </div>
                    )}
                    <StorefrontPagination paginator={listings} />
                </section>
            </main>
        </StorefrontLayout>
    );
}
