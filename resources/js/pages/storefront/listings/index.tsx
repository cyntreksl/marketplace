import { Form, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Clock3,
    Filter,
    LayoutGrid,
    LaptopMinimal,
    PackageSearch,
    Search,
    ShieldCheck,
    Sparkles,
    Store,
    Truck,
} from 'lucide-react';
import { ListingCard } from '@/components/listing-card';
import { StorefrontBreadcrumbs } from '@/components/storefront-breadcrumbs';
import { StorefrontLayout } from '@/components/storefront-layout';
import { StorefrontListingFilters } from '@/components/storefront-listing-filters';
import { StorefrontPagination } from '@/components/storefront-pagination';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { home } from '@/routes';
import { show as categoryShow } from '@/routes/categories';
import { index as listingsIndex } from '@/routes/listings';
import type {
    StorefrontBrand,
    StorefrontBreadcrumbItem,
    StorefrontBrowseFilters,
    StorefrontCategory,
    StorefrontCategoryContext,
    StorefrontListingPaginator,
} from '@/types';

function BrowseHiddenInputs({
    filters,
    omit = [],
}: {
    filters: StorefrontBrowseFilters;
    omit?: (keyof StorefrontBrowseFilters)[];
}) {
    return Object.entries(filters).map(([name, value]) => {
        if (
            omit.includes(name as keyof StorefrontBrowseFilters) ||
            value === null ||
            value === undefined ||
            value === ''
        ) {
            return null;
        }

        return (
            <input key={name} type="hidden" name={name} value={String(value)} />
        );
    });
}

function activeFilterCount(filters: StorefrontBrowseFilters): number {
    return [
        filters.brand,
        filters.condition,
        filters.listing_type,
        filters.location,
        filters.min_price,
        filters.max_price,
    ].filter((value) => value !== null && value !== undefined && value !== '')
        .length;
}

function breadcrumbItems(
    categoryContext: StorefrontCategoryContext | null,
): StorefrontBreadcrumbItem[] {
    if (!categoryContext) {
        return [{ label: 'Home', href: home.url() }, { label: 'Products' }];
    }

    const trail = [...categoryContext.ancestors, categoryContext.current];

    return [
        { label: 'Home', href: home.url() },
        ...trail.map((category, index) => ({
            label: category.name,
            href:
                index === trail.length - 1
                    ? undefined
                    : categoryShow.url(category.slug),
        })),
    ];
}

function CategoryStrip({
    categories,
    categoryContext,
}: {
    categories: StorefrontCategory[];
    categoryContext: StorefrontCategoryContext | null;
}) {
    const items = categoryContext
        ? categoryContext.children.map((category) => ({
              category,
              hasChildren: category.has_children,
          }))
        : categories.map((category) => ({
              category,
              hasChildren: category.children.length > 0,
          }));

    if (items.length === 0) {
        return null;
    }

    return (
        <section className="py-6">
            <div className="mb-4 flex items-end justify-between gap-4">
                <div>
                    <p className="text-xs font-black tracking-[0.16em] text-[#FF6D00] uppercase">
                        Browse categories
                    </p>
                    <h2 className="mt-1 text-xl font-black tracking-tight text-slate-950">
                        {categoryContext
                            ? `Explore ${categoryContext.current.name}`
                            : 'Browse departments'}
                    </h2>
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
                {items.slice(0, 10).map(({ category, hasChildren }) => (
                    <Link
                        key={category.id}
                        href={categoryShow(category.slug)}
                        prefetch
                        className="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-[#FF6D00]/30 hover:shadow-lg hover:shadow-orange-100/40"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-sm font-black text-slate-950 transition group-hover:text-[#FF6D00]">
                                    {category.name}
                                </p>
                                <p className="mt-1 text-xs text-slate-500">
                                    {hasChildren
                                        ? 'Browse subcategories'
                                        : 'View products'}
                                </p>
                            </div>
                            <span className="grid size-8 place-items-center rounded-full bg-slate-50 text-slate-300 transition group-hover:bg-orange-50 group-hover:text-[#FF6D00]">
                                <ArrowRight className="size-4" />
                            </span>
                        </div>
                    </Link>
                ))}
            </div>
        </section>
    );
}

export default function ListingsIndex({
    listings,
    categories,
    filters,
    categoryContext,
    filterOptions,
}: {
    listings: StorefrontListingPaginator;
    categories: StorefrontCategory[];
    filters: StorefrontBrowseFilters;
    categoryContext: StorefrontCategoryContext | null;
    filterOptions: { brands: StorefrontBrand[] };
}) {
    const filterCount = activeFilterCount(filters);
    const pageTitle = categoryContext?.current.name ?? 'Smartphones';
    const resultDescription = filters.search
        ? `Search results for “${filters.search}”`
        : categoryContext
          ? `Explore approved listings in ${categoryContext.current.name}`
          : 'Explore the latest smartphones from top brands. Best performance, cameras, and features - all at unbeatable prices.';
    const totalResults = listings.total.toLocaleString('en-LK');
    const trail = categoryContext
        ? [...categoryContext.ancestors, categoryContext.current]
        : [];

    return (
        <StorefrontLayout
            title={pageTitle}
            categories={categories}
            activeCategorySlugs={trail.map((category) => category.slug)}
        >
            <main className="mx-auto max-w-[90rem] px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <StorefrontBreadcrumbs
                    items={breadcrumbItems(categoryContext)}
                />

                <section className="mt-5 overflow-hidden rounded-[2rem] border border-orange-100 bg-gradient-to-r from-orange-50 via-orange-50/60 to-white shadow-[0_20px_60px_rgba(255,109,0,0.12)]">
                    <div className="grid gap-6 px-5 py-6 lg:grid-cols-[1.25fr_0.85fr] lg:px-8 lg:py-7">
                        <div className="space-y-5">
                            <div className="inline-flex items-center gap-2 rounded-full bg-[#FF6D00] px-3 py-1.5 text-xs font-black tracking-[0.16em] text-white uppercase shadow-lg shadow-orange-200">
                                <Sparkles className="size-3.5" />
                                ProDeals.lk
                            </div>
                            <div>
                                <p className="text-xs font-bold tracking-[0.22em] text-slate-500 uppercase">
                                    Top brands, best prices
                                </p>
                                <h1 className="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                                    {pageTitle}
                                </h1>
                                <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base">
                                    {resultDescription}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <div className="rounded-2xl bg-white px-4 py-3 shadow-sm">
                                    <p className="text-[11px] font-semibold text-slate-500">
                                        Showing
                                    </p>
                                    <p className="text-lg font-black text-slate-950">
                                        {listings.from && listings.to
                                            ? `${listings.from} - ${listings.to}`
                                            : totalResults}
                                    </p>
                                </div>
                                <div className="rounded-2xl bg-white px-4 py-3 shadow-sm">
                                    <p className="text-[11px] font-semibold text-slate-500">
                                        Total results
                                    </p>
                                    <p className="text-lg font-black text-slate-950">
                                        {totalResults}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="rounded-[1.75rem] bg-white p-4 shadow-sm ring-1 ring-orange-100">
                            <div className="mb-4 flex items-center justify-between">
                                <div>
                                    <p className="text-xs font-semibold tracking-[0.2em] text-slate-400 uppercase">
                                        Search products
                                    </p>
                                    <p className="mt-1 text-sm font-bold text-slate-900">
                                        Find the right phone quickly
                                    </p>
                                </div>
                                <span className="grid size-11 place-items-center rounded-2xl bg-orange-50 text-[#FF6D00]">
                                    <LaptopMinimal className="size-5" />
                                </span>
                            </div>
                            <Form {...listingsIndex.form()}>
                                <BrowseHiddenInputs
                                    filters={filters}
                                    omit={['search']}
                                />
                                <label className="relative block">
                                    <span className="sr-only">
                                        Search products
                                    </span>
                                    <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        name="search"
                                        defaultValue={filters.search ?? ''}
                                        placeholder="Search for phones, laptops, TVs and more..."
                                        className="h-12 w-full rounded-2xl border border-slate-200 bg-slate-50 pr-14 pl-12 text-sm text-slate-950 outline-none placeholder:text-slate-400 focus:border-[#FF6D00] focus:bg-white focus:ring-4 focus:ring-orange-100"
                                    />
                                    <button
                                        type="submit"
                                        className="absolute top-1.5 right-1.5 grid size-9 place-items-center rounded-xl bg-[#FF6D00] text-white transition hover:bg-[#e86100] focus-visible:ring-2 focus-visible:ring-[#FF6D00] focus-visible:outline-none"
                                        aria-label="Search products"
                                    >
                                        <ArrowRight className="size-4" />
                                    </button>
                                </label>
                            </Form>
                            <div className="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                <span className="inline-flex items-center gap-1 rounded-full bg-slate-50 px-3 py-1.5">
                                    <ShieldCheck className="size-3.5 text-[#FF6D00]" />
                                    Official warranty
                                </span>
                                <span className="inline-flex items-center gap-1 rounded-full bg-slate-50 px-3 py-1.5">
                                    <Truck className="size-3.5 text-[#FF6D00]" />
                                    Islandwide delivery
                                </span>
                                <span className="inline-flex items-center gap-1 rounded-full bg-slate-50 px-3 py-1.5">
                                    <Clock3 className="size-3.5 text-[#FF6D00]" />
                                    Easy returns
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <CategoryStrip
                    categories={categories}
                    categoryContext={categoryContext}
                />

                <section id="results" className="scroll-mt-40 pt-4 pb-14">
                    <div className="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="flex items-start gap-3">
                            <div className="rounded-2xl bg-orange-50 p-3 text-[#FF6D00]">
                                <Store className="size-5" />
                            </div>
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-2xl font-black tracking-tight text-slate-950">
                                        Products
                                    </h2>
                                    {filterCount > 0 && (
                                        <span className="rounded-full bg-[#FF6D00]/10 px-2.5 py-1 text-xs font-black text-[#FF6D00]">
                                            {filterCount}{' '}
                                            {filterCount === 1
                                                ? 'filter'
                                                : 'filters'}
                                        </span>
                                    )}
                                </div>
                                <p className="mt-1 text-sm text-slate-500">
                                    {listings.from && listings.to
                                        ? `Showing ${listings.from}–${listings.to} of ${listings.total} results`
                                        : 'No matching products'}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className="rounded-xl border-slate-200 bg-white lg:hidden"
                                    >
                                        <Filter className="size-4" />
                                        Filters
                                        {filterCount > 0 && (
                                            <span className="grid size-5 place-items-center rounded-full bg-[#FF6D00] text-[10px] text-white">
                                                {filterCount}
                                            </span>
                                        )}
                                    </Button>
                                </SheetTrigger>
                                <SheetContent
                                    side="left"
                                    className="w-[92vw] overflow-y-auto sm:max-w-md"
                                >
                                    <SheetHeader>
                                        <SheetTitle>Product filters</SheetTitle>
                                        <SheetDescription>
                                            Refine the products shown on this
                                            page.
                                        </SheetDescription>
                                    </SheetHeader>
                                    <StorefrontListingFilters
                                        filters={filters}
                                        brands={filterOptions.brands}
                                        idPrefix="mobile-storefront"
                                        className="px-4 pb-8"
                                    />
                                </SheetContent>
                            </Sheet>

                            <Form
                                {...listingsIndex.form()}
                                className="flex items-center gap-2"
                            >
                                <BrowseHiddenInputs
                                    filters={filters}
                                    omit={['sort']}
                                />
                                <label className="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                                    <LayoutGrid className="size-4 text-slate-400" />
                                    <span className="sr-only">
                                        Sort products
                                    </span>
                                    <select
                                        name="sort"
                                        defaultValue={filters.sort}
                                        onChange={(event) =>
                                            event.currentTarget.form?.requestSubmit()
                                        }
                                        className="bg-transparent text-sm font-semibold text-slate-700 outline-none"
                                    >
                                        <option value="newest">
                                            Sort by: Popularity
                                        </option>
                                        <option value="price_asc">
                                            Price: low to high
                                        </option>
                                        <option value="price_desc">
                                            Price: high to low
                                        </option>
                                    </select>
                                </label>
                                <button type="submit" className="sr-only">
                                    Apply sorting
                                </button>
                            </Form>
                        </div>
                    </div>

                    <div className="grid items-start gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
                        <aside className="sticky top-36 hidden rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm lg:block">
                            <StorefrontListingFilters
                                filters={filters}
                                brands={filterOptions.brands}
                                idPrefix="desktop-storefront"
                            />
                        </aside>

                        <div className="min-w-0">
                            {listings.data.length > 0 ? (
                                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                                    {listings.data.map((listing) => (
                                        <ListingCard
                                            key={listing.id}
                                            listing={listing}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-3xl border border-dashed border-orange-200 bg-white px-6 py-16 text-center shadow-sm">
                                    <span className="mx-auto grid size-14 place-items-center rounded-2xl bg-orange-50 text-[#FF6D00]">
                                        {categoryContext?.children.length ? (
                                            <PackageSearch className="size-7" />
                                        ) : (
                                            <Search className="size-7" />
                                        )}
                                    </span>
                                    <h3 className="mt-5 text-xl font-black text-slate-950">
                                        No products found
                                    </h3>
                                    <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                                        {categoryContext?.children.length
                                            ? 'This category is ready to browse. Explore a subcategory above or adjust your filters.'
                                            : 'Try a broader search, remove a filter, or explore another marketplace category.'}
                                    </p>
                                    <Button asChild className="mt-6 rounded-xl">
                                        <Link
                                            href={listingsIndex({
                                                query: categoryContext
                                                    ? {
                                                          category:
                                                              categoryContext
                                                                  .current.slug,
                                                      }
                                                    : {},
                                            })}
                                        >
                                            Clear filters
                                        </Link>
                                    </Button>
                                </div>
                            )}

                            <StorefrontPagination paginator={listings} />
                        </div>
                    </div>
                </section>
            </main>
        </StorefrontLayout>
    );
}
