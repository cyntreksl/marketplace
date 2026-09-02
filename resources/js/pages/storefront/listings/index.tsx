import { Form, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Filter,
    PackageSearch,
    Search,
    SlidersHorizontal,
    Sparkles,
} from 'lucide-react';
import { ListingCard } from '@/components/listing-card';
import { StorefrontBreadcrumbs } from '@/components/storefront-breadcrumbs';
import { StorefrontCategoryCard } from '@/components/storefront-category-card';
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
    StorefrontCategoryNode,
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
        return [{ label: 'Home', href: home.url() }, { label: 'All products' }];
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

function categoryCards(
    categories: StorefrontCategory[],
    categoryContext: StorefrontCategoryContext | null,
): { category: StorefrontCategoryNode; hasChildren: boolean }[] {
    if (categoryContext) {
        return categoryContext.children.map((category) => ({
            category,
            hasChildren: category.has_children,
        }));
    }

    return categories.map((category) => ({
        category,
        hasChildren: category.children.length > 0,
    }));
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
    const cards = categoryCards(categories, categoryContext);
    const categoryTrail = categoryContext
        ? [...categoryContext.ancestors, categoryContext.current]
        : [];
    const filterCount = activeFilterCount(filters);
    const pageTitle = categoryContext?.current.name ?? 'All products';
    const resultDescription = filters.search
        ? `Search results for “${filters.search}”`
        : categoryContext
          ? `Explore approved listings in ${categoryContext.current.name}`
          : 'Discover approved marketplace listings from local sellers';

    return (
        <StorefrontLayout
            title={pageTitle}
            categories={categories}
            activeCategorySlugs={categoryTrail.map((category) => category.slug)}
        >
            <main className="mx-auto max-w-[90rem] px-4 py-6 sm:px-6 lg:px-8 lg:py-9">
                <StorefrontBreadcrumbs
                    items={breadcrumbItems(categoryContext)}
                />

                <section className="relative mt-5 overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-[#000000] to-primary px-5 py-8 text-white shadow-2xl shadow-slate-950/10 sm:px-8 sm:py-10 lg:px-12">
                    <div className="absolute -top-28 right-0 size-72 rounded-full bg-white/10 blur-3xl" />
                    <div className="absolute -bottom-36 left-1/3 size-80 rounded-full bg-primary/30 blur-3xl" />
                    <div className="relative grid items-end gap-7 lg:grid-cols-[minmax(0,1fr)_minmax(24rem,0.75fr)]">
                        <div>
                            <div className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-black tracking-[0.16em] text-emerald-100 uppercase ring-1 ring-white/15 backdrop-blur">
                                <Sparkles className="size-3.5" />
                                ProDeals.lk marketplace
                            </div>
                            <h1 className="mt-4 max-w-3xl text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl">
                                {pageTitle}
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-200 sm:text-base">
                                {resultDescription}
                            </p>
                            <p className="mt-4 text-sm font-bold text-emerald-100">
                                {listings.total.toLocaleString()}{' '}
                                {listings.total === 1 ? 'product' : 'products'}
                            </p>
                        </div>

                        <Form
                            {...listingsIndex.form()}
                            className="rounded-2xl bg-white/10 p-2 ring-1 ring-white/20 backdrop-blur-md"
                        >
                            <BrowseHiddenInputs
                                filters={filters}
                                omit={['search']}
                            />
                            <label className="relative block">
                                <span className="sr-only">Search products</span>
                                <Search className="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-slate-400" />
                                <input
                                    name="search"
                                    defaultValue={filters.search ?? ''}
                                    placeholder="Search within products"
                                    className="h-13 w-full rounded-xl border-0 bg-white pr-14 pl-12 text-sm text-slate-950 shadow-lg outline-none placeholder:text-slate-400 focus:ring-4 focus:ring-emerald-300/30"
                                />
                                <button
                                    type="submit"
                                    className="absolute top-1.5 right-1.5 grid size-10 place-items-center rounded-lg bg-primary text-primary-foreground transition hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                                    aria-label="Search products"
                                >
                                    <ArrowRight className="size-4" />
                                </button>
                            </label>
                        </Form>
                    </div>
                </section>

                {cards.length > 0 && (
                    <section
                        className="py-10"
                        aria-labelledby="categories-title"
                    >
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <p className="text-xs font-black tracking-[0.16em] text-primary uppercase">
                                    Shop by category
                                </p>
                                <h2
                                    id="categories-title"
                                    className="mt-1 text-2xl font-black tracking-tight text-slate-950 dark:text-white"
                                >
                                    {categoryContext
                                        ? `Explore ${categoryContext.current.name}`
                                        : 'Browse departments'}
                                </h2>
                            </div>
                            {categoryContext && (
                                <Link
                                    href={listingsIndex()}
                                    className="hidden text-sm font-bold text-primary hover:underline sm:block"
                                >
                                    View all categories
                                </Link>
                            )}
                        </div>
                        <div className="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                            {cards.map((card) => (
                                <StorefrontCategoryCard
                                    key={card.category.id}
                                    category={card.category}
                                    hasChildren={card.hasChildren}
                                />
                            ))}
                        </div>
                    </section>
                )}

                <section id="results" className="scroll-mt-40 pb-14">
                    <div className="mb-5 flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between dark:border-slate-800">
                        <div>
                            <div className="flex items-center gap-2">
                                <h2 className="text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                                    Products
                                </h2>
                                {filterCount > 0 && (
                                    <span className="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-black text-primary">
                                        {filterCount}{' '}
                                        {filterCount === 1
                                            ? 'filter'
                                            : 'filters'}
                                    </span>
                                )}
                            </div>
                            <p className="mt-1 text-sm text-slate-500">
                                {listings.from && listings.to
                                    ? `Showing ${listings.from}–${listings.to} of ${listings.total}`
                                    : 'No matching products'}
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className="rounded-xl lg:hidden"
                                    >
                                        <Filter className="size-4" />
                                        Filters
                                        {filterCount > 0 && (
                                            <span className="grid size-5 place-items-center rounded-full bg-primary text-[10px] text-primary-foreground">
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
                                <label className="flex items-center gap-2">
                                    <SlidersHorizontal className="hidden size-4 text-slate-400 sm:block" />
                                    <span className="sr-only">
                                        Sort products
                                    </span>
                                    <select
                                        name="sort"
                                        defaultValue={filters.sort}
                                        onChange={(event) =>
                                            event.currentTarget.form?.requestSubmit()
                                        }
                                        className="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                                    >
                                        <option value="newest">
                                            Newest first
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

                    <div className="grid items-start gap-7 lg:grid-cols-[17rem_minmax(0,1fr)]">
                        <aside className="sticky top-40 hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50 lg:block dark:border-slate-800 dark:bg-slate-900 dark:shadow-none">
                            <StorefrontListingFilters
                                filters={filters}
                                brands={filterOptions.brands}
                                idPrefix="desktop-storefront"
                            />
                        </aside>

                        <div>
                            {listings.data.length > 0 ? (
                                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                    {listings.data.map((listing) => (
                                        <ListingCard
                                            key={listing.id}
                                            listing={listing}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-3xl border border-dashed border-primary/30 bg-white px-6 py-16 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
                                    <span className="mx-auto grid size-14 place-items-center rounded-2xl bg-primary/10 text-primary">
                                        {categoryContext?.children.length ? (
                                            <PackageSearch className="size-7" />
                                        ) : (
                                            <Search className="size-7" />
                                        )}
                                    </span>
                                    <h3 className="mt-5 text-xl font-black text-slate-950 dark:text-white">
                                        No products found
                                    </h3>
                                    <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">
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
