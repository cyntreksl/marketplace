import { Form, Head, Link } from '@inertiajs/react';
import { Search, ShoppingBag } from 'lucide-react';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/AdminOrderController';
import { AdminPagination } from '@/components/admin-pagination';
import { PortalLayout } from '@/components/portal-layout';
import type { AdminOrderPaginator } from '@/types';

type StatusOption = { value: string; label: string };

function money(value: string): string {
    return `LKR ${Number(value).toLocaleString('en-LK', { minimumFractionDigits: 2 })}`;
}

export default function AdminOrdersIndex({
    orders,
    filters,
    statuses,
}: {
    orders: AdminOrderPaginator;
    filters: { search: string; status: string; sort: string };
    statuses: StatusOption[];
}) {
    return (
        <PortalLayout portal="admin" title="All orders">
            <Head title="All orders" />
            <div className="space-y-6">
                <header>
                    <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                        Marketplace operations
                    </p>
                    <h1 className="mt-2 text-3xl font-bold tracking-tight">
                        All orders
                    </h1>
                    <p className="mt-2 text-sm text-slate-500">
                        Review customer orders and manage every seller package.
                    </p>
                </header>

                <Form
                    {...index.form()}
                    className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[minmax(16rem,1fr)_13rem_11rem_auto] dark:border-slate-800 dark:bg-slate-900"
                >
                    <label className="relative">
                        <span className="sr-only">Search orders</span>
                        <Search className="absolute top-3.5 left-3 size-4 text-slate-400" />
                        <input
                            name="search"
                            defaultValue={filters.search}
                            maxLength={100}
                            placeholder="Order, buyer, seller, or product"
                            className="min-h-11 w-full rounded-xl border bg-transparent pr-3 pl-9 text-sm"
                        />
                    </label>
                    <select
                        name="status"
                        defaultValue={filters.status}
                        className="min-h-11 rounded-xl border bg-transparent px-3 text-sm"
                    >
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                    <select
                        name="sort"
                        defaultValue={filters.sort}
                        className="min-h-11 rounded-xl border bg-transparent px-3 text-sm"
                    >
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                    </select>
                    <button className="min-h-11 rounded-xl bg-primary px-5 text-sm font-bold text-primary-foreground">
                        Apply
                    </button>
                </Form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    {orders.data.length === 0 ? (
                        <div className="grid place-items-center gap-2 p-12 text-center">
                            <ShoppingBag className="size-8 text-slate-400" />
                            <p className="font-bold">No orders found</p>
                            <p className="text-sm text-slate-500">
                                Try changing the search or status filter.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100 dark:divide-slate-800">
                            {orders.data.map((order) => (
                                <Link
                                    key={order.number}
                                    href={show(order.number)}
                                    className="grid gap-4 p-5 transition hover:bg-slate-50 md:grid-cols-[1.1fr_1.2fr_1.5fr_auto] md:items-center dark:hover:bg-slate-950/60"
                                >
                                    <div>
                                        <p className="font-black">
                                            {order.number}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {new Date(
                                                order.created_at,
                                            ).toLocaleString()}
                                        </p>
                                        <p className="mt-2 text-sm font-bold">
                                            {money(order.total)}
                                        </p>
                                    </div>
                                    <div className="text-sm">
                                        <p className="font-bold">
                                            {order.buyer.name}
                                        </p>
                                        <p className="break-all text-slate-500">
                                            {order.buyer.email}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {order.packages.map((sellerPackage) => (
                                            <span
                                                key={sellerPackage.number}
                                                className="rounded-lg bg-slate-100 px-2.5 py-1 text-xs dark:bg-slate-800"
                                            >
                                                <strong>
                                                    {sellerPackage.store_name}
                                                </strong>{' '}
                                                ·{' '}
                                                {sellerPackage.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </span>
                                        ))}
                                    </div>
                                    <div className="text-sm md:text-right">
                                        <p className="font-bold capitalize">
                                            {order.status.replaceAll('_', ' ')}
                                        </p>
                                        {order.payments.map(
                                            (payment, paymentIndex) => (
                                                <p
                                                    key={`${payment.method}-${paymentIndex}`}
                                                    className="mt-1 text-xs text-slate-500 capitalize"
                                                >
                                                    {payment.method.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}{' '}
                                                    ·{' '}
                                                    {payment.status.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </p>
                                            ),
                                        )}
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                    <AdminPagination paginator={orders} />
                </section>
            </div>
        </PortalLayout>
    );
}
