import { Form, Link } from '@inertiajs/react';
import { Filter, LayoutGrid, PackageSearch, Search, Store } from 'lucide-react';
import { StorefrontBreadcrumbs } from '@/components/storefront-breadcrumbs';
import { StorefrontCategoryArtwork } from '@/components/storefront-category-artwork';
import { StorefrontLayout } from '@/components/storefront-layout';
import { StorefrontListingFilters } from '@/components/storefront-listing-filters';
import { StorefrontProductGrid } from '@/components/storefront-product-grid';
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
import { show as guideShow } from '@/routes/guides';
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
        filters.min_price,
        filters.max_price,
    ].filter((value) => value !== null && value !== undefined && value !== '')
        .length;
}

function breadcrumbItems(
    categoryContext: StorefrontCategoryContext | null,
    pageHeading: string,
    browseUrl: string,
    catalogMode: 'retail' | 'wholesale',
): StorefrontBreadcrumbItem[] {
    if (!categoryContext) {
        return [{ label: 'Home', href: home.url() }, { label: pageHeading }];
    }

    const trail = [...categoryContext.ancestors, categoryContext.current];

    return [
        { label: 'Home', href: home.url() },
        ...trail.map((category, index) => ({
            label: category.name,
            href:
                index === trail.length - 1
                    ? undefined
                    : catalogMode === 'wholesale'
                      ? `${browseUrl}?category=${encodeURIComponent(category.slug)}`
                      : categoryShow.url(category.slug),
        })),
    ];
}

function CategoryStrip({
    categories,
    categoryContext,
    browseUrl,
    catalogMode,
}: {
    categories: StorefrontCategory[];
    categoryContext: StorefrontCategoryContext | null;
    browseUrl: string;
    catalogMode: 'retail' | 'wholesale';
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
        <section className="mt-5">
            <div className="mb-3 flex items-center justify-between border-b border-slate-100 pb-2">
                <h2 className="text-xl font-extrabold tracking-tight text-slate-950 sm:text-2xl">
                    {categoryContext?.current.name ?? 'Popular categories'}
                </h2>
                <span className="text-xs font-semibold text-slate-400 sm:text-sm">
                    Shop by category
                </span>
            </div>
            <div className="flex snap-x snap-mandatory [scrollbar-width:none] gap-3 overflow-x-auto pb-2">
                {items.slice(0, 10).map(({ category, hasChildren }) => (
                    <Link
                        key={category.id}
                        href={
                            catalogMode === 'wholesale'
                                ? `${browseUrl}?category=${encodeURIComponent(category.slug)}`
                                : categoryShow(category.slug)
                        }
                        prefetch
                        aria-label={`${category.name}${hasChildren ? ', browse subcategories' : ''}`}
                        className="group flex h-30 w-28 shrink-0 snap-start flex-col items-center justify-center rounded-xl border border-slate-200 bg-white p-2 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#FF6D00]/40 hover:shadow-md focus-visible:ring-2 focus-visible:ring-[#FF6D00] focus-visible:ring-offset-2 focus-visible:outline-none motion-reduce:transform-none sm:h-32 sm:w-[8.6rem]"
                    >
                        <StorefrontCategoryArtwork
                            category={category}
                            className="size-16 rounded-lg bg-slate-50 text-[#FF6D00] ring-0 sm:size-20"
                        />
                        <h2 className="mt-2 line-clamp-1 w-full text-xs font-bold text-slate-800 transition group-hover:text-[#FF6D00] sm:text-sm">
                            {category.name}
                        </h2>
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
    pageHeading,
    intro,
    browseUrl,
    relatedGuides = [],
    catalogMode = 'retail',
}: {
    listings: StorefrontListingPaginator;
    categories: StorefrontCategory[];
    filters: StorefrontBrowseFilters;
    categoryContext: StorefrontCategoryContext | null;
    filterOptions: { brands: StorefrontBrand[] };
    pageHeading: string;
    intro: string;
    browseUrl: string;
    relatedGuides?: { title: string; slug: string; excerpt: string }[];
    catalogMode?: 'retail' | 'wholesale';
}) {
    const filterCount = activeFilterCount(filters);
    const pageTitle = pageHeading;
    const trail = categoryContext
        ? [...categoryContext.ancestors, categoryContext.current]
        : [];

    return (
        <StorefrontLayout
            title={pageTitle}
            categories={categories}
            activeCategorySlugs={trail.map((category) => category.slug)}
        >
            <main className="storefront-container py-6 lg:py-8">
                <StorefrontBreadcrumbs
                    items={breadcrumbItems(
                        categoryContext,
                        pageHeading,
                        browseUrl,
                        catalogMode,
                    )}
                />

                <header className="mt-5 max-w-4xl">
                    <h1 className="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
                        {pageHeading}
                    </h1>
                    <div className="mt-3 space-y-3 text-base leading-7 text-slate-600">
                        {intro
                            .split(/\n\s*\n/)
                            .filter(Boolean)
                            .map((paragraph) => (
                                <p key={paragraph}>{paragraph}</p>
                            ))}
                    </div>
                </header>

                <CategoryStrip
                    categories={categories}
                    categoryContext={categoryContext}
                    browseUrl={browseUrl}
                    catalogMode={catalogMode}
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
                                        {catalogMode === 'wholesale'
                                            ? 'Wholesale products'
                                            : 'Products'}
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
                                <p className="mt-1 text-base text-slate-500">
                                    {listings.data.length > 0
                                        ? `${listings.data.length} of ${listings.total} results loaded`
                                        : 'No matching products'}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Sheet>
                                <SheetTrigger asChild>
                                    <Button
                                        variant="outline"
                                        className="h-11 rounded-xl border-slate-200 bg-white px-4 font-bold shadow-sm hover:border-[#FF6D00]/40 hover:bg-orange-50 hover:text-[#FF6D00]"
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
                                    side="right"
                                    className="w-[94vw] gap-0 overflow-y-auto border-l-0 bg-white text-slate-950 shadow-2xl sm:max-w-md dark:bg-slate-900 dark:text-white"
                                >
                                    <SheetHeader className="border-b border-slate-100 px-6 py-5 text-left dark:border-slate-800">
                                        <SheetTitle className="text-xl font-black tracking-tight">
                                            Filter products
                                        </SheetTitle>
                                        <SheetDescription>
                                            Refine results by condition, brand,
                                            and price.
                                        </SheetDescription>
                                    </SheetHeader>
                                    <StorefrontListingFilters
                                        filters={filters}
                                        brands={filterOptions.brands}
                                        browseUrl={browseUrl}
                                        omitCategory={Boolean(categoryContext)}
                                        idPrefix="mobile-storefront"
                                        className="px-6 py-6"
                                    />
                                </SheetContent>
                            </Sheet>

                            <Form
                                action={browseUrl}
                                method="get"
                                className="flex items-center gap-2"
                            >
                                <BrowseHiddenInputs
                                    filters={filters}
                                    omit={
                                        categoryContext
                                            ? ['sort', 'category']
                                            : ['sort']
                                    }
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
                                        className="bg-transparent text-base font-semibold text-slate-700 outline-none"
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

                    <div className="min-w-0">
                        {listings.data.length > 0 ? (
                            <StorefrontProductGrid
                                listings={listings}
                                className="lg:grid-cols-3 xl:grid-cols-6"
                            />
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
                                <p className="mx-auto mt-2 max-w-md text-base leading-7 text-slate-500">
                                    {categoryContext?.children.length
                                        ? 'This category is ready to browse. Explore a subcategory above or adjust your filters.'
                                        : 'Try a broader search, remove a filter, or explore another marketplace category.'}
                                </p>
                                <Button asChild className="mt-6 rounded-xl">
                                    <Link href={browseUrl}>Clear filters</Link>
                                </Button>
                            </div>
                        )}
                    </div>
                </section>

                {relatedGuides.length > 0 && (
                    <section className="border-t border-slate-200 py-12">
                        <h2 className="text-2xl font-black tracking-tight text-slate-950">
                            Related buying guides
                        </h2>
                        <div className="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {relatedGuides.map((guide) => (
                                <Link
                                    key={guide.slug}
                                    href={guideShow(guide.slug)}
                                    className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-[#FF6D00]/40 hover:shadow-md"
                                >
                                    <h3 className="font-extrabold text-slate-950">
                                        {guide.title}
                                    </h3>
                                    <p className="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">
                                        {guide.excerpt}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </StorefrontLayout>
    );
}
