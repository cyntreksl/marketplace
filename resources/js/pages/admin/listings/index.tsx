import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, Download, ImageIcon, Search } from 'lucide-react';
import {
    metaCatalogueExport,
    show,
} from '@/actions/App/Http/Controllers/AdminListingController';
import { AdminPagination } from '@/components/admin-pagination';
import { PortalLayout } from '@/components/portal-layout';
import { dashboard } from '@/routes/admin';
import { index as listingReviewsIndex } from '@/routes/admin/listings';
import { index as productsIndex } from '@/routes/admin/products';
import type { PaginationLink } from '@/types';

type Listing = {
    id: number;
    title: string | null;
    sku: string | null;
    status: string;
    listing_type: 'buy_now' | 'auction';
    product_type: 'simple' | 'variant';
    condition: 'new' | 'used' | 'refurbished' | null;
    short_description: string | null;
    is_retail_enabled: boolean;
    is_wholesale_enabled: boolean;
    wholesale_price: string | null;
    wholesale_min_quantity: number | null;
    media: { id: number; url: string }[];
    seller_profile: { store_name: string };
    category: { name: string } | null;
    seo_score: SeoScore;
};

type SeoScore = {
    score: number;
    maximum: number;
    label: 'Strong' | 'Needs Improvement' | 'Weak';
    checks: {
        key: string;
        label: string;
        points: number;
        maximum: number;
        passed: boolean;
        recommendation: string | null;
    }[];
};

type Filters = {
    search: string;
    status: string;
    listing_type: string;
    product_type: string;
    condition: string;
    sort: string;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    next_page_url: string | null;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

const reviewStatuses = [
    ['all', 'All review statuses'],
    ['pending_review', 'Pending review'],
    ['changes_requested', 'Changes requested'],
    ['rejected', 'Rejected'],
    ['suspended', 'Suspended'],
] as const;

const productStatuses = [
    ['all', 'All statuses'],
    ['draft', 'Draft'],
    ['pending_review', 'Pending review'],
    ['approved', 'Approved'],
    ['changes_requested', 'Changes requested'],
    ['rejected', 'Rejected'],
    ['suspended', 'Suspended'],
    ['archived', 'Archived'],
] as const;

function label(value: string): string {
    return value.replaceAll('_', ' ');
}

export default function AdminListings({
    listings,
    filters,
    view,
}: {
    listings: Paginator<Listing>;
    filters: Filters;
    view: 'moderation' | 'all';
}) {
    const isModeration = view === 'moderation';
    const listRoute = isModeration ? listingReviewsIndex : productsIndex;
    const title = isModeration ? 'Listing reviews' : 'All products';
    const defaultStatus = isModeration ? 'pending_review' : 'all';
    const hasActiveFilters =
        filters.search !== '' ||
        filters.status !== defaultStatus ||
        filters.listing_type !== 'all' ||
        filters.product_type !== 'all' ||
        filters.condition !== 'all' ||
        filters.sort !== 'newest';

    return (
        <PortalLayout portal="admin" title={title}>
            <Head title={title} />
            <main className="mx-auto max-w-7xl">
                <Link
                    href={dashboard()}
                    className="text-sm font-bold text-primary"
                >
                    ← Operations
                </Link>
                <div className="mt-4 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <h1 className="text-3xl font-black tracking-tight sm:text-4xl">
                            {title}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {isModeration
                                ? 'Review submitted products and follow up on previous moderation decisions.'
                                : 'Search and manage every product in the marketplace catalog.'}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        {!isModeration && (
                            <a
                                href={metaCatalogueExport.url()}
                                className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-primary px-4 text-sm font-bold text-primary transition hover:bg-primary hover:text-primary-foreground"
                            >
                                <Download className="size-4" />
                                Meta catalogue export
                            </a>
                        )}
                        <div className="flex rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900">
                            <Link
                                href={listingReviewsIndex()}
                                className={`rounded-lg px-4 py-2 text-sm font-semibold ${isModeration ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                            >
                                Listing reviews
                            </Link>
                            <Link
                                href={productsIndex()}
                                className={`rounded-lg px-4 py-2 text-sm font-semibold ${!isModeration ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                            >
                                All products
                            </Link>
                        </div>
                    </div>
                </div>

                <Form
                    {...listRoute.form()}
                    options={{
                        preserveScroll: true,
                        preserveState: true,
                        replace: true,
                    }}
                    className="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 xl:grid-cols-[minmax(15rem,1fr)_11rem_11rem_11rem_10rem_11rem_auto] dark:border-slate-800 dark:bg-slate-900"
                >
                    <label className="relative sm:col-span-2 xl:col-span-1">
                        <Search className="absolute top-3.5 left-3 size-4 text-slate-400" />
                        <span className="sr-only">Search products</span>
                        <input
                            name="search"
                            defaultValue={filters.search}
                            maxLength={100}
                            placeholder="Product, SKU, or seller"
                            className="min-h-11 w-full rounded-xl border bg-transparent pr-3 pl-10"
                        />
                    </label>
                    <select
                        name="status"
                        defaultValue={filters.status}
                        aria-label="Status"
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    >
                        {(isModeration ? reviewStatuses : productStatuses).map(
                            ([value, optionLabel]) => (
                                <option key={value} value={value}>
                                    {optionLabel}
                                </option>
                            ),
                        )}
                    </select>
                    <select
                        name="listing_type"
                        defaultValue={filters.listing_type}
                        aria-label="Selling format"
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    >
                        <option value="all">All selling formats</option>
                        <option value="buy_now">Buy now</option>
                        <option value="auction">Auction</option>
                    </select>
                    <select
                        name="product_type"
                        defaultValue={filters.product_type}
                        aria-label="Product type"
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    >
                        <option value="all">All product types</option>
                        <option value="simple">Simple</option>
                        <option value="variant">Variant</option>
                    </select>
                    <select
                        name="condition"
                        defaultValue={filters.condition}
                        aria-label="Condition"
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    >
                        <option value="all">All conditions</option>
                        <option value="new">New</option>
                        <option value="used">Used</option>
                        <option value="refurbished">Refurbished</option>
                    </select>
                    <select
                        name="sort"
                        defaultValue={filters.sort}
                        aria-label="Sort products"
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    >
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                        <option value="title">Title A–Z</option>
                    </select>
                    <button className="min-h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white dark:bg-white dark:text-slate-950">
                        Apply
                    </button>
                    {hasActiveFilters && (
                        <Link
                            href={listRoute()}
                            className="text-center text-sm font-semibold text-primary sm:col-span-2 xl:col-span-7"
                        >
                            Clear filters
                        </Link>
                    )}
                </Form>

                <p className="mt-4 text-sm text-muted-foreground">
                    {listings.total.toLocaleString()} product
                    {listings.total === 1 ? '' : 's'}
                </p>

                <section className="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="grid divide-y divide-slate-100 dark:divide-slate-800">
                        {listings.data.map((listing) => (
                            <article
                                key={listing.id}
                                className="grid gap-5 p-4 md:grid-cols-[7rem_minmax(0,1fr)_auto] md:p-5"
                            >
                                <div className="flex aspect-square items-center justify-center overflow-hidden rounded-xl bg-slate-50 dark:bg-slate-950">
                                    {listing.media[0] ? (
                                        <img
                                            src={listing.media[0].url}
                                            alt=""
                                            className="size-full object-contain p-3"
                                        />
                                    ) : (
                                        <ImageIcon className="size-8 text-slate-300" />
                                    )}
                                </div>
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="truncate text-lg font-black">
                                            {listing.title ??
                                                'Untitled product'}
                                        </p>
                                        <span className="rounded-full bg-orange-100 px-2.5 py-1 text-xs font-bold text-orange-700 capitalize dark:bg-orange-500/15 dark:text-orange-300">
                                            {label(listing.status)}
                                        </span>
                                        {listing.is_retail_enabled && (
                                            <span className="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700">
                                                Retail
                                            </span>
                                        )}
                                        {listing.is_wholesale_enabled && (
                                            <span className="rounded-full bg-orange-50 px-2.5 py-1 text-xs font-bold text-orange-700">
                                                From LKR{' '}
                                                {Number(
                                                    listing.wholesale_price ??
                                                        0,
                                                ).toLocaleString('en-LK')}{' '}
                                                /unit ·{' '}
                                                {listing.wholesale_min_quantity ??
                                                    '—'}
                                                + units
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-1 text-sm text-stone-500">
                                        {listing.seller_profile.store_name} ·{' '}
                                        {listing.category?.name ??
                                            'No category'}{' '}
                                        · {label(listing.listing_type)} ·{' '}
                                        {label(listing.product_type)} ·{' '}
                                        {listing.condition
                                            ? label(listing.condition)
                                            : 'No condition'}
                                    </p>
                                    <p className="mt-1 text-xs text-stone-500">
                                        SKU {listing.sku ?? 'not set'}
                                    </p>
                                    {listing.short_description && (
                                        <p className="mt-3 line-clamp-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                            {listing.short_description}
                                        </p>
                                    )}
                                    <details className="mt-4 rounded-xl bg-stone-50 p-3 dark:bg-stone-950">
                                        <summary className="cursor-pointer text-sm font-bold">
                                            SEO readiness:{' '}
                                            {listing.seo_score.score}/
                                            {listing.seo_score.maximum} ·{' '}
                                            {listing.seo_score.label}
                                        </summary>
                                        <ul className="mt-3 grid gap-2">
                                            {listing.seo_score.checks.map(
                                                (check) => (
                                                    <li
                                                        key={check.key}
                                                        className="text-sm text-stone-600 dark:text-stone-300"
                                                    >
                                                        <strong className="text-stone-900 dark:text-white">
                                                            {check.label}:{' '}
                                                            {check.points}/
                                                            {check.maximum}
                                                        </strong>
                                                        {check.recommendation && (
                                                            <span className="mt-0.5 block">
                                                                {
                                                                    check.recommendation
                                                                }
                                                            </span>
                                                        )}
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    </details>
                                </div>
                                <Link
                                    href={show(listing.id)}
                                    className="inline-flex h-11 items-center justify-center gap-2 self-center rounded-xl bg-primary px-5 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20"
                                >
                                    {isModeration
                                        ? 'Review details'
                                        : 'View product'}{' '}
                                    <ArrowRight className="size-4" />
                                </Link>
                            </article>
                        ))}
                    </div>
                    {listings.data.length === 0 && (
                        <p className="p-12 text-center text-sm text-slate-500">
                            No products match these filters.
                        </p>
                    )}
                    <AdminPagination paginator={listings} />
                </section>
            </main>
        </PortalLayout>
    );
}
