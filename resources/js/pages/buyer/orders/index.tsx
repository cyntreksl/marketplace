import { Head, Link } from '@inertiajs/react';
import { ArrowRight, PackageOpen, Store } from 'lucide-react';
import { BuyerPageHeader } from '@/components/buyer-page-header';
import { BuyerPagination } from '@/components/buyer-pagination';
import { BuyerPortalLayout } from '@/components/buyer-portal-layout';
import { BuyerStatusBadge } from '@/components/buyer-status-badge';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/buyer/orders';
import type { BuyerOrder, BuyerOrderStage, Paginated } from '@/types';

type StageOption = { value: BuyerOrderStage; label: string };

function money(value: string): string {
    return `LKR ${Number(value).toLocaleString('en-LK', { minimumFractionDigits: 2 })}`;
}

function date(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('en-LK', { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : '—';
}

export default function BuyerOrders({
    orders,
    counts,
    stage,
    stages,
}: {
    orders: Paginated<BuyerOrder>;
    counts: Record<BuyerOrderStage, number>;
    stage: BuyerOrderStage;
    stages: StageOption[];
}) {
    return (
        <BuyerPortalLayout title="Orders">
            <Head title="Orders" />
            <div className="space-y-7">
                <BuyerPageHeader
                    eyebrow="Purchases"
                    title="Your orders"
                    description="Track every order, follow seller packages, and take action when a payment or delivery needs you."
                />
                <nav
                    className="flex gap-2 overflow-x-auto pb-1"
                    aria-label="Order stages"
                >
                    {stages.map((option) => (
                        <Link
                            key={option.value}
                            href={index({
                                query:
                                    option.value === 'all'
                                        ? {}
                                        : { stage: option.value },
                            })}
                            preserveState
                            className={`inline-flex min-h-10 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-semibold transition-colors ${stage === option.value ? 'border-orange-600 bg-orange-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-orange-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'}`}
                        >
                            {option.label}
                            <span
                                className={`rounded-full px-1.5 py-0.5 text-[0.7rem] ${stage === option.value ? 'bg-white/20' : 'bg-slate-100 dark:bg-slate-800'}`}
                            >
                                {counts[option.value] ?? 0}
                            </span>
                        </Link>
                    ))}
                </nav>
                {orders.data.length === 0 ? (
                    <section className="grid min-h-72 place-items-center rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
                        <div>
                            <span className="mx-auto grid size-14 place-items-center rounded-2xl bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">
                                <PackageOpen className="size-7" />
                            </span>
                            <h2 className="mt-4 text-lg font-bold">
                                No orders in this view
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Orders will appear here as their status changes.
                            </p>
                        </div>
                    </section>
                ) : (
                    <div className="grid gap-4">
                        {orders.data.map((order) => (
                            <article
                                key={order.number}
                                className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Link
                                                href={show(order.number)}
                                                className="font-black text-slate-950 hover:text-orange-700 dark:text-white dark:hover:text-orange-300"
                                            >
                                                {order.number}
                                            </Link>
                                            <BuyerStatusBadge
                                                status={order.stage}
                                                label={order.stage_label}
                                            />
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500">
                                            Placed {date(order.created_at)}
                                        </p>
                                    </div>
                                    <div className="sm:text-right">
                                        <p className="font-black">
                                            {money(order.total)}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            {order.seller_orders.reduce(
                                                (total, seller) =>
                                                    total + seller.items.length,
                                                0,
                                            )}{' '}
                                            products
                                        </p>
                                    </div>
                                </div>
                                <div className="grid gap-4 px-5 py-4 md:grid-cols-[1fr_auto] md:items-center">
                                    <div className="space-y-2">
                                        {order.seller_orders.map(
                                            (sellerOrder) => (
                                                <div
                                                    key={sellerOrder.number}
                                                    className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"
                                                >
                                                    <Store className="size-4 text-orange-600" />
                                                    <span className="font-semibold">
                                                        {sellerOrder.store_name}
                                                    </span>
                                                    <span className="text-slate-400">
                                                        ·
                                                    </span>
                                                    <span>
                                                        {
                                                            sellerOrder.items
                                                                .length
                                                        }{' '}
                                                        item
                                                        {sellerOrder.items
                                                            .length === 1
                                                            ? ''
                                                            : 's'}
                                                    </span>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                    <Button
                                        variant="outline"
                                        className="rounded-xl"
                                        asChild
                                    >
                                        <Link href={show(order.number)}>
                                            View details{' '}
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
                <BuyerPagination links={orders.links} />
            </div>
        </BuyerPortalLayout>
    );
}
