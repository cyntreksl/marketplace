import { Form, Head, Link, router } from '@inertiajs/react';
import { Eye, Search } from 'lucide-react';
import { SellerPageHeader } from '@/components/seller-page-header';
import { SellerPagination } from '@/components/seller-pagination';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { SellerStatusBadge } from '@/components/seller-status-badge';
import { index, show } from '@/routes/seller/orders';
import type { SellerOrder, SellerPaginator } from '@/types';

type Filters = { q: string; status: string; sort: string };
type Status = { value: string; label: string };

export default function SellerOrders({
    orders,
    counts,
    filters,
    statuses,
}: {
    orders: SellerPaginator<SellerOrder>;
    counts: Record<string, number>;
    filters: Filters;
    statuses: Status[];
}) {
    const filter = (values: Partial<Filters>) =>
        router.get(
            index.url(),
            { ...filters, ...values },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const itemSummary = (order: SellerOrder) =>
        order.items
            .map((item) => `${item.title} × ${item.quantity}`)
            .join(', ');

    return (
        <SellerPortalLayout title="Orders">
            <Head title="Seller orders" />
            <div className="space-y-6">
                <SellerPageHeader
                    title="Orders & fulfilment"
                    description="Move paid orders through processing, dispatch, and delivery with a complete customer-visible history."
                />
                <div
                    className="flex gap-2 overflow-x-auto pb-1"
                    role="tablist"
                    aria-label="Order status filters"
                >
                    {statuses.map((status) => (
                        <button
                            key={status.value}
                            role="tab"
                            aria-selected={filters.status === status.value}
                            onClick={() => filter({ status: status.value })}
                            className={`min-h-10 shrink-0 rounded-xl px-3 text-sm font-bold ${filters.status === status.value ? 'bg-orange-600 text-white' : 'border border-slate-200 bg-white text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'}`}
                        >
                            {status.label}{' '}
                            <span className="ml-1 opacity-75">
                                {status.value === 'all'
                                    ? Object.values(counts).reduce(
                                          (sum, count) => sum + count,
                                          0,
                                      )
                                    : (counts[status.value] ?? 0)}
                            </span>
                        </button>
                    ))}
                </div>
                <Form
                    {...index.form()}
                    className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[minmax(0,1fr)_12rem_auto] dark:border-slate-800 dark:bg-slate-900"
                    options={{ preserveState: true, preserveScroll: true }}
                >
                    <label className="relative">
                        <span className="sr-only">Search orders</span>
                        <Search className="absolute top-3 left-3 size-4 text-slate-400" />
                        <input
                            name="q"
                            defaultValue={filters.q}
                            maxLength={100}
                            placeholder="Order, recipient, tracking, or product"
                            className="min-h-11 w-full rounded-xl border border-slate-200 bg-transparent pr-3 pl-10 text-sm dark:border-slate-700"
                        />
                    </label>
                    <select
                        name="sort"
                        defaultValue={filters.sort}
                        className="min-h-11 rounded-xl border border-slate-200 bg-transparent px-3 text-sm dark:border-slate-700"
                    >
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                    </select>
                    <input type="hidden" name="status" value={filters.status} />
                    <button className="min-h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white dark:bg-white dark:text-slate-950">
                        Apply filters
                    </button>
                </Form>
                <section
                    className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    aria-label="Seller orders"
                >
                    <div className="hidden lg:block">
                        <table className="w-full table-fixed text-left text-sm">
                            <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950">
                                <tr>
                                    <th className="w-[16%] px-4 py-3">
                                        Order / date
                                    </th>
                                    <th className="w-[14%] px-4 py-3">
                                        Recipient
                                    </th>
                                    <th className="w-[22%] px-4 py-3">Items</th>
                                    <th className="w-[12%] px-4 py-3">
                                        Earnings
                                    </th>
                                    <th className="w-[14%] px-4 py-3">
                                        Courier
                                    </th>
                                    <th className="w-[12%] px-4 py-3">
                                        Status
                                    </th>
                                    <th className="w-[10%] px-4 py-3 text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                {orders.data.map((order) => (
                                    <tr
                                        key={order.number}
                                        className="hover:bg-orange-50/40 dark:hover:bg-orange-500/5"
                                    >
                                        <td className="px-4 py-4">
                                            <Link
                                                href={show(order.number)}
                                                className="font-bold hover:text-orange-700"
                                            >
                                                {order.number}
                                            </Link>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {new Date(
                                                    order.created_at,
                                                ).toLocaleDateString()}
                                            </p>
                                        </td>
                                        <td className="truncate px-4 py-4 font-semibold">
                                            {order.recipient_name}
                                        </td>
                                        <td className="px-4 py-4">
                                            <p className="line-clamp-2 text-slate-600 dark:text-slate-300">
                                                {itemSummary(order)}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 font-bold">
                                            LKR{' '}
                                            {Number(
                                                order.seller_earnings,
                                            ).toLocaleString()}
                                        </td>
                                        <td className="px-4 py-4">
                                            <p className="truncate">
                                                {order.shipment?.courier_name ??
                                                    'Not assigned'}
                                            </p>
                                            <p className="truncate text-xs text-slate-500">
                                                {
                                                    order.shipment
                                                        ?.tracking_number
                                                }
                                            </p>
                                        </td>
                                        <td className="px-4 py-4">
                                            <SellerStatusBadge
                                                status={order.status}
                                                label={order.status_label}
                                            />
                                        </td>
                                        <td className="px-4 py-4 text-right">
                                            <Link
                                                href={show(order.number)}
                                                className="inline-flex min-h-10 items-center gap-1 rounded-xl border border-slate-200 px-3 text-xs font-bold hover:border-orange-300 hover:text-orange-700 dark:border-slate-700"
                                            >
                                                <Eye className="size-4" /> Open
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="divide-y divide-slate-100 lg:hidden dark:divide-slate-800">
                        {orders.data.map((order) => (
                            <article key={order.number} className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <Link
                                            href={show(order.number)}
                                            className="font-black"
                                        >
                                            {order.number}
                                        </Link>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {new Date(
                                                order.created_at,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                    <SellerStatusBadge
                                        status={order.status}
                                        label={order.status_label}
                                    />
                                </div>
                                <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt className="text-xs text-slate-500">
                                            Recipient
                                        </dt>
                                        <dd className="font-semibold">
                                            {order.recipient_name}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-slate-500">
                                            Earnings
                                        </dt>
                                        <dd className="font-bold">
                                            LKR{' '}
                                            {Number(
                                                order.seller_earnings,
                                            ).toLocaleString()}
                                        </dd>
                                    </div>
                                    <div className="col-span-2">
                                        <dt className="text-xs text-slate-500">
                                            Items
                                        </dt>
                                        <dd className="line-clamp-2">
                                            {itemSummary(order)}
                                        </dd>
                                    </div>
                                    <div className="col-span-2">
                                        <dt className="text-xs text-slate-500">
                                            Courier
                                        </dt>
                                        <dd>
                                            {order.shipment
                                                ? `${order.shipment.courier_name} · ${order.shipment.tracking_number}`
                                                : 'Not assigned'}
                                        </dd>
                                    </div>
                                </dl>
                                <Link
                                    href={show(order.number)}
                                    className="mt-4 flex min-h-11 items-center justify-center gap-2 rounded-xl bg-orange-600 px-4 text-sm font-bold text-white"
                                >
                                    <Eye className="size-4" /> View order & next
                                    action
                                </Link>
                            </article>
                        ))}
                    </div>
                    {orders.data.length === 0 && (
                        <p className="p-12 text-center text-sm text-slate-500">
                            No orders match these filters.
                        </p>
                    )}
                    <SellerPagination paginator={orders} />
                </section>
            </div>
        </SellerPortalLayout>
    );
}
