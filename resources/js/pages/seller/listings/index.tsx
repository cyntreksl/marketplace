import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import {
    create,
    destroy,
    edit,
    show,
} from '@/actions/App/Http/Controllers/SellerListingController';
import { SellerPageHeader } from '@/components/seller-page-header';
import { SellerPagination } from '@/components/seller-pagination';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { index } from '@/routes/seller/listings';
import {
    create as createWholesale,
    index as wholesaleIndex,
} from '@/routes/seller/wholesale';
import type { SellerPaginator } from '@/types';

type Listing = {
    id: number;
    title: string | null;
    sku: string | null;
    model: string | null;
    status: string;
    moderation_reason: string | null;
    product_type: 'simple' | 'variant';
    price: string | null;
    is_retail_enabled: boolean;
    is_wholesale_enabled: boolean;
    wholesale_price: string | null;
    wholesale_min_quantity: number | null;
    has_orders: boolean;
    created_at: string;
    brand: { name: string } | null;
    brand_name: string | null;
    category: { name: string } | null;
};
type Filters = { q: string; status: string; sort: string };

function Actions({ listing }: { listing: Listing }) {
    return (
        <div className="flex flex-wrap gap-2">
            {listing.status !== 'archived' && (
                <Link
                    href={edit(listing.id)}
                    className="min-h-10 rounded-xl border px-3 py-2 text-xs font-bold"
                >
                    Edit
                </Link>
            )}
            <Link
                href={show(listing.id)}
                className="min-h-10 rounded-xl border px-3 py-2 text-xs font-bold"
            >
                View
            </Link>
            {listing.status !== 'archived' && (
                <Form {...destroy.form(listing.id)}>
                    <button className="min-h-10 rounded-xl border border-rose-200 px-3 py-2 text-xs font-bold text-rose-700">
                        {listing.has_orders ? 'Archive' : 'Remove'}
                    </button>
                </Form>
            )}
        </div>
    );
}

export default function SellerListings({
    sellerStatus,
    listings,
    filters,
    channel = 'retail',
}: {
    sellerStatus: string;
    listings: SellerPaginator<Listing>;
    filters: Filters;
    channel?: 'retail' | 'wholesale';
}) {
    const isWholesale = channel === 'wholesale';
    const title = isWholesale ? 'Wholesale' : 'Products';
    const listRoute = isWholesale ? wholesaleIndex : index;

    return (
        <SellerPortalLayout title={title}>
            <Head title={title} />
            <div className="space-y-6">
                <SellerPageHeader
                    title={title}
                    description={`${isWholesale ? 'Manage bulk pricing and minimum order quantities' : 'Manage retail inventory and pricing'}. Account status: ${sellerStatus.replaceAll('_', ' ')}.`}
                    actions={
                        <Link
                            href={isWholesale ? createWholesale() : create()}
                            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-5 text-sm font-bold text-primary-foreground"
                        >
                            <Plus className="size-4" />{' '}
                            {isWholesale
                                ? 'Add wholesale product'
                                : 'Add product'}
                        </Link>
                    }
                />
                {sellerStatus !== 'approved' && sellerStatus !== 'active' && (
                    <p className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                        You can prepare drafts now. Your account must be
                        approved before submission.
                    </p>
                )}
                <Form
                    {...listRoute.form()}
                    className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-[minmax(0,1fr)_12rem_11rem_auto] dark:bg-slate-900"
                    options={{ preserveScroll: true, preserveState: true }}
                >
                    <label className="relative">
                        <Search className="absolute top-3 left-3 size-4 text-slate-400" />
                        <span className="sr-only">Search products</span>
                        <input
                            name="q"
                            defaultValue={filters.q}
                            placeholder="Product name or SKU"
                            maxLength={100}
                            className="min-h-11 w-full rounded-xl border bg-transparent pr-3 pl-10"
                        />
                    </label>
                    <select
                        name="status"
                        defaultValue={filters.status}
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    >
                        <option value="all">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="pending_review">Pending review</option>
                        <option value="approved">Approved</option>
                        <option value="changes_requested">
                            Changes requested
                        </option>
                        <option value="rejected">Rejected</option>
                        <option value="archived">Archived</option>
                    </select>
                    <select
                        name="sort"
                        defaultValue={filters.sort}
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    >
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                        <option value="title">Title A–Z</option>
                    </select>
                    <button className="min-h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white dark:bg-white dark:text-slate-950">
                        Apply
                    </button>
                </Form>
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="hidden md:block">
                        <table className="w-full table-fixed text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500 uppercase dark:bg-slate-950">
                                <tr>
                                    <th className="w-[28%] px-5 py-3">
                                        Product
                                    </th>
                                    <th className="w-[15%] px-4 py-3">
                                        SKU / model
                                    </th>
                                    <th className="w-[18%] px-4 py-3">
                                        Category
                                    </th>
                                    <th className="w-[13%] px-4 py-3">Type</th>
                                    <th className="w-[12%] px-4 py-3">
                                        Status
                                    </th>
                                    <th className="w-[14%] px-5 py-3">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {listings.data.map((listing) => (
                                    <tr key={listing.id}>
                                        <td className="px-5 py-4">
                                            <p className="truncate font-bold">
                                                {listing.title ??
                                                    'Untitled product'}
                                            </p>
                                            <p className="truncate text-xs text-slate-500">
                                                {listing.brand?.name ??
                                                    listing.brand_name ??
                                                    'No brand'}
                                            </p>
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {listing.is_retail_enabled && (
                                                    <span className="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-700">
                                                        Retail
                                                    </span>
                                                )}
                                                {listing.is_wholesale_enabled && (
                                                    <span className="rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-bold text-orange-700">
                                                        From LKR{' '}
                                                        {Number(
                                                            listing.wholesale_price ??
                                                                0,
                                                        ).toLocaleString(
                                                            'en-LK',
                                                        )}{' '}
                                                        /unit ·{' '}
                                                        {listing.wholesale_min_quantity ??
                                                            '—'}
                                                        + units
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4">
                                            <p>{listing.sku ?? '—'}</p>
                                            <p className="text-xs text-slate-500">
                                                {listing.model ?? 'No model'}
                                            </p>
                                        </td>
                                        <td className="truncate px-4 py-4">
                                            {listing.category?.name ??
                                                'Uncategorised'}
                                        </td>
                                        <td className="px-4 py-4 capitalize">
                                            {listing.product_type}
                                        </td>
                                        <td className="px-4 py-4">
                                            <span className="rounded-full bg-orange-100 px-2.5 py-1 text-xs font-bold text-orange-700 capitalize dark:bg-orange-500/15 dark:text-orange-300">
                                                {listing.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </span>
                                        </td>
                                        <td className="px-5 py-4">
                                            <Actions listing={listing} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="divide-y md:hidden">
                        {listings.data.map((listing) => (
                            <article key={listing.id} className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-black">
                                            {listing.title ??
                                                'Untitled product'}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            {listing.sku ?? 'No SKU'} ·{' '}
                                            {listing.category?.name ??
                                                'Uncategorised'}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-orange-100 px-2.5 py-1 text-xs font-bold text-orange-700 capitalize">
                                        {listing.status.replaceAll('_', ' ')}
                                    </span>
                                </div>
                                <div className="mt-4">
                                    <Actions listing={listing} />
                                </div>
                            </article>
                        ))}
                    </div>
                    {listings.data.length === 0 && (
                        <p className="p-12 text-center text-sm text-slate-500">
                            No products match these filters.
                        </p>
                    )}
                    <SellerPagination paginator={listings} />
                </section>
            </div>
        </SellerPortalLayout>
    );
}
