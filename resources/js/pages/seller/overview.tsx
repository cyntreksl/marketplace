import { Deferred, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Boxes,
    CircleDollarSign,
    HelpCircle,
    PackageCheck,
    RotateCcw,
    Truck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { SellerPageHeader } from '@/components/seller-page-header';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { SellerStatusBadge } from '@/components/seller-status-badge';
import { Skeleton } from '@/components/ui/skeleton';
import { index as questionIndex } from '@/routes/product-questions';
import {
    index as listingsIndex,
    show as listingShow,
} from '@/routes/seller/listings';
import { index as orderIndex, show as orderShow } from '@/routes/seller/orders';
import { index as returnsIndex } from '@/routes/seller/returns';
import { index as walletIndex } from '@/routes/seller/wallet';
import type { SellerOrder } from '@/types';

type Metrics = {
    monthly_completed_earnings: string;
    orders_needing_action: number;
    ready_to_dispatch: number;
    in_transit: number;
    active_products: number;
    open_returns: number;
    available_balance: string;
};
type Activity = {
    recent_orders: SellerOrder[];
    low_stock_products: {
        id: number;
        title: string;
        available_quantity: number;
    }[];
    unanswered_questions: {
        id: number;
        question: string;
        listing_title: string;
        asker_name: string;
    }[];
};

function Stat({
    label,
    value,
    icon: Icon,
    href,
}: {
    label: string;
    value: string | number;
    icon: LucideIcon;
    href: ReturnType<typeof orderIndex>;
}) {
    return (
        <Link
            href={href}
            className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-orange-300 dark:border-slate-800 dark:bg-slate-900"
        >
            <span className="grid size-10 place-items-center rounded-xl bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">
                <Icon className="size-5" />
            </span>
            <p className="mt-5 text-2xl font-black">{value}</p>
            <p className="mt-1 flex items-center justify-between text-sm font-semibold text-slate-600 dark:text-slate-300">
                {label}
                <ArrowRight className="size-4" />
            </p>
        </Link>
    );
}

export default function SellerOverview({
    metrics,
    activity,
}: {
    metrics: Metrics;
    activity?: Activity;
}) {
    return (
        <SellerPortalLayout title="Overview">
            <Head title="Seller overview" />
            <div className="space-y-8">
                <SellerPageHeader
                    eyebrow="Welcome back"
                    title="Your business, at a glance"
                    description="Prioritize fulfilment, stock, customer service, returns, and cash flow from one workspace."
                />
                <section
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Seller business summary"
                >
                    <Stat
                        label="Monthly earnings"
                        value={`LKR ${Number(metrics.monthly_completed_earnings).toLocaleString()}`}
                        icon={CircleDollarSign}
                        href={walletIndex()}
                    />
                    <Stat
                        label="Orders needing action"
                        value={metrics.orders_needing_action}
                        icon={PackageCheck}
                        href={orderIndex({ query: { status: 'paid' } })}
                    />
                    <Stat
                        label="Ready to dispatch"
                        value={metrics.ready_to_dispatch}
                        icon={Truck}
                        href={orderIndex({
                            query: { status: 'ready_to_ship' },
                        })}
                    />
                    <Stat
                        label="In transit"
                        value={metrics.in_transit}
                        icon={Truck}
                        href={orderIndex({ query: { status: 'shipped' } })}
                    />
                    <Stat
                        label="Active products"
                        value={metrics.active_products}
                        icon={Boxes}
                        href={listingsIndex()}
                    />
                    <Stat
                        label="Open returns"
                        value={metrics.open_returns}
                        icon={RotateCcw}
                        href={returnsIndex()}
                    />
                    <Stat
                        label="Available balance"
                        value={`LKR ${Number(metrics.available_balance).toLocaleString()}`}
                        icon={CircleDollarSign}
                        href={walletIndex()}
                    />
                </section>
                <Deferred
                    data="activity"
                    fallback={
                        <div className="grid gap-5 xl:grid-cols-3">
                            <Skeleton className="h-72 rounded-2xl xl:col-span-2" />
                            <Skeleton className="h-72 rounded-2xl" />
                        </div>
                    }
                >
                    <div className="grid gap-5 xl:grid-cols-3">
                        <section className="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2 dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-black">
                                    Recent orders
                                </h2>
                                <Link
                                    href={orderIndex()}
                                    className="text-sm font-bold text-orange-700"
                                >
                                    View all
                                </Link>
                            </div>
                            <div className="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                                {(activity?.recent_orders ?? []).map(
                                    (order) => (
                                        <Link
                                            key={order.number}
                                            href={orderShow(order.number)}
                                            className="flex items-center justify-between gap-3 py-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-bold">
                                                    {order.number} ·{' '}
                                                    {order.recipient_name}
                                                </p>
                                                <p className="truncate text-xs text-slate-500">
                                                    {order.items
                                                        .map(
                                                            (item) =>
                                                                `${item.title} × ${item.quantity}`,
                                                        )
                                                        .join(', ')}
                                                </p>
                                            </div>
                                            <SellerStatusBadge
                                                status={order.status}
                                                label={order.status_label}
                                            />
                                        </Link>
                                    ),
                                )}
                                {(activity?.recent_orders ?? []).length ===
                                    0 && (
                                    <p className="py-10 text-center text-sm text-slate-500">
                                        No seller orders yet.
                                    </p>
                                )}
                            </div>
                        </section>
                        <div className="grid min-w-0 gap-5">
                            <section className="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <div className="flex items-center justify-between">
                                    <h2 className="font-black">Low stock</h2>
                                    <Link
                                        href={listingsIndex()}
                                        className="text-xs font-bold text-orange-700"
                                    >
                                        View products
                                    </Link>
                                </div>
                                <ul className="mt-3 space-y-3">
                                    {(activity?.low_stock_products ?? []).map(
                                        (item) => (
                                            <li key={item.id}>
                                                <Link
                                                    href={listingShow(item.id)}
                                                    className="flex min-w-0 justify-between gap-3 text-sm"
                                                >
                                                    <span className="min-w-0 truncate font-semibold">
                                                        {item.title}
                                                    </span>
                                                    <span className="shrink-0 text-rose-600">
                                                        {
                                                            item.available_quantity
                                                        }{' '}
                                                        left
                                                    </span>
                                                </Link>
                                            </li>
                                        ),
                                    )}
                                    {(activity?.low_stock_products ?? [])
                                        .length === 0 && (
                                        <li className="text-sm text-slate-500">
                                            Stock levels look healthy.
                                        </li>
                                    )}
                                </ul>
                            </section>
                            <section className="min-w-0 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <div className="flex items-center justify-between">
                                    <h2 className="flex items-center gap-2 font-black">
                                        <HelpCircle className="size-4" />{' '}
                                        Questions
                                    </h2>
                                    <Link
                                        href={questionIndex()}
                                        className="text-xs font-bold text-orange-700"
                                    >
                                        View all
                                    </Link>
                                </div>
                                <ul className="mt-3 space-y-3">
                                    {(activity?.unanswered_questions ?? []).map(
                                        (item) => (
                                            <li
                                                key={item.id}
                                                className="text-sm"
                                            >
                                                <p className="line-clamp-2 font-semibold">
                                                    {item.question}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {item.listing_title} ·{' '}
                                                    {item.asker_name}
                                                </p>
                                            </li>
                                        ),
                                    )}
                                    {(activity?.unanswered_questions ?? [])
                                        .length === 0 && (
                                        <li className="text-sm text-slate-500">
                                            No unanswered questions.
                                        </li>
                                    )}
                                </ul>
                            </section>
                        </div>
                    </div>
                </Deferred>
            </div>
        </SellerPortalLayout>
    );
}
