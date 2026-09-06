import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CreditCard,
    MapPin,
    PackageCheck,
    RotateCcw,
    Star,
    Truck,
} from 'lucide-react';
import { BuyerPortalLayout } from '@/components/buyer-portal-layout';
import { BuyerStatusBadge } from '@/components/buyer-status-badge';
import { Button } from '@/components/ui/button';
import { index as feedbackIndex } from '@/routes/buyer/feedback';
import { index as ordersIndex } from '@/routes/buyer/orders';
import { index as returnsIndex } from '@/routes/buyer/returns';
import { retry } from '@/routes/checkout/card';
import type { BuyerOrder, CheckoutAddress } from '@/types';

function money(value: string): string {
    return `LKR ${Number(value).toLocaleString('en-LK', { minimumFractionDigits: 2 })}`;
}
function Address({
    title,
    address,
}: {
    title: string;
    address: CheckoutAddress | null;
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-4 dark:bg-slate-950">
            <p className="text-xs font-bold tracking-wide text-slate-400 uppercase">
                {title}
            </p>
            {address ? (
                <div className="mt-2 text-sm leading-6">
                    <p className="font-bold">{address.recipient_name}</p>
                    <p>
                        {address.address_line_one}
                        {address.address_line_two
                            ? `, ${address.address_line_two}`
                            : ''}
                    </p>
                    <p>
                        {address.city}
                        {address.postal_code ? ` ${address.postal_code}` : ''}
                    </p>
                    <p>{address.phone}</p>
                </div>
            ) : (
                <p className="mt-2 text-sm text-slate-500">Not available</p>
            )}
        </div>
    );
}

export default function BuyerOrderDetail({ order }: { order: BuyerOrder }) {
    return (
        <BuyerPortalLayout title={`Order ${order.number}`}>
            <Head title={`Order ${order.number}`} />
            <div className="space-y-6">
                <Link
                    href={ordersIndex()}
                    className="inline-flex items-center gap-2 text-sm font-bold text-slate-600 hover:text-orange-700 dark:text-slate-300"
                >
                    <ArrowLeft className="size-4" /> Back to orders
                </Link>
                <header className="flex flex-col gap-4 rounded-3xl bg-gradient-to-br from-slate-950 to-slate-800 p-6 text-white sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <div>
                        <p className="text-xs font-bold tracking-[0.16em] text-orange-300 uppercase">
                            Order details
                        </p>
                        <h1 className="mt-2 text-3xl font-black">
                            {order.number}
                        </h1>
                        <p className="mt-2 text-sm text-slate-300">
                            Placed{' '}
                            {order.created_at
                                ? new Intl.DateTimeFormat('en-LK', {
                                      dateStyle: 'long',
                                      timeStyle: 'short',
                                  }).format(new Date(order.created_at))
                                : '—'}
                        </p>
                    </div>
                    <div className="flex flex-col items-start gap-3 sm:items-end">
                        <BuyerStatusBadge
                            status={order.stage}
                            label={order.stage_label}
                        />
                        {order.stage === 'to_pay' && (
                            <Form {...retry.form(order.number)}>
                                <Button
                                    className="rounded-xl bg-orange-600 text-white hover:bg-orange-700"
                                    disabled={
                                        order.status !== 'pending_payment'
                                    }
                                >
                                    Pay {money(order.total)}
                                </Button>
                            </Form>
                        )}
                    </div>
                </header>
                <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                    <div className="space-y-5">
                        {order.seller_orders.map((sellerOrder) => (
                            <section
                                key={sellerOrder.number}
                                className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div className="flex flex-col gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                                    <div>
                                        <p className="font-black">
                                            {sellerOrder.store_name}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            Package {sellerOrder.number}
                                        </p>
                                    </div>
                                    <BuyerStatusBadge
                                        status={sellerOrder.status}
                                    />
                                </div>
                                {sellerOrder.shipment && (
                                    <div className="flex flex-wrap items-center gap-x-5 gap-y-2 bg-orange-50 px-5 py-3 text-sm dark:bg-orange-500/10">
                                        <span className="flex items-center gap-2 font-bold text-orange-800 dark:text-orange-300">
                                            <Truck className="size-4" />{' '}
                                            {sellerOrder.shipment.status.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </span>
                                        {sellerOrder.shipment.courier_name && (
                                            <span>
                                                {
                                                    sellerOrder.shipment
                                                        .courier_name
                                                }
                                            </span>
                                        )}
                                        {sellerOrder.shipment
                                            .tracking_number && (
                                            <span className="font-mono text-xs">
                                                Tracking:{' '}
                                                {
                                                    sellerOrder.shipment
                                                        .tracking_number
                                                }
                                            </span>
                                        )}
                                    </div>
                                )}
                                <div className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {sellerOrder.items.map((item) => (
                                        <article
                                            key={item.id}
                                            className="flex gap-4 p-5"
                                        >
                                            {item.image_url ? (
                                                <img
                                                    src={item.image_url}
                                                    alt=""
                                                    className="size-20 rounded-xl object-cover"
                                                />
                                            ) : (
                                                <span className="grid size-20 shrink-0 place-items-center rounded-xl bg-slate-100 dark:bg-slate-800">
                                                    <PackageCheck className="size-7 text-slate-400" />
                                                </span>
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <p className="font-bold">
                                                    {item.title}
                                                </p>
                                                {item.variant_options && (
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {Object.entries(
                                                            item.variant_options,
                                                        )
                                                            .map(
                                                                ([
                                                                    key,
                                                                    value,
                                                                ]) =>
                                                                    `${key}: ${value}`,
                                                            )
                                                            .join(' · ')}
                                                    </p>
                                                )}
                                                <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                                                    {item.quantity} ×{' '}
                                                    {money(item.unit_price)}
                                                </p>
                                                <div className="mt-3 flex flex-wrap gap-2">
                                                    {item.can_return && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="rounded-lg"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={returnsIndex()}
                                                            >
                                                                <RotateCcw className="size-3.5" />{' '}
                                                                Return item
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {item.can_review && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            className="rounded-lg"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={feedbackIndex()}
                                                            >
                                                                <Star className="size-3.5" />{' '}
                                                                Leave feedback
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {item.review && (
                                                        <span className="inline-flex items-center gap-1 text-xs font-bold text-amber-700">
                                                            <Star className="size-3.5 fill-current" />{' '}
                                                            {item.review.rating}
                                                            /5 submitted
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <p className="shrink-0 font-bold">
                                                {money(item.total)}
                                            </p>
                                        </article>
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                    <aside className="space-y-5">
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="font-black">Order total</h2>
                            <dl className="mt-4 space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Subtotal</dt>
                                    <dd>{money(order.subtotal)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-slate-500">Shipping</dt>
                                    <dd>{money(order.shipping_total)}</dd>
                                </div>
                                <div className="flex justify-between border-t border-slate-100 pt-3 text-base font-black dark:border-slate-800">
                                    <dt>Total</dt>
                                    <dd>{money(order.total)}</dd>
                                </div>
                            </dl>
                        </section>
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="flex items-center gap-2 font-black">
                                <MapPin className="size-5 text-orange-600" />{' '}
                                Addresses
                            </h2>
                            <div className="mt-4 grid gap-3">
                                <Address
                                    title="Shipping"
                                    address={order.shipping_address}
                                />
                                <Address
                                    title="Billing"
                                    address={order.billing_address}
                                />
                            </div>
                        </section>
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="flex items-center gap-2 font-black">
                                <CreditCard className="size-5 text-orange-600" />{' '}
                                Payment attempts
                            </h2>
                            <div className="mt-4 space-y-3">
                                {order.payments
                                    .flatMap((payment) => payment.attempts)
                                    .map((attempt) => (
                                        <div
                                            key={attempt.id}
                                            className="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-950"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-bold capitalize">
                                                    {attempt.method.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </span>
                                                <BuyerStatusBadge
                                                    status={attempt.status}
                                                    label={attempt.status_label}
                                                />
                                            </div>
                                            <p className="mt-1 text-xs text-slate-500">
                                                Attempt {attempt.attempt_number}{' '}
                                                ·{' '}
                                                {attempt.attempted_at
                                                    ? new Intl.DateTimeFormat(
                                                          'en-LK',
                                                          {
                                                              dateStyle:
                                                                  'medium',
                                                              timeStyle:
                                                                  'short',
                                                          },
                                                      ).format(
                                                          new Date(
                                                              attempt.attempted_at,
                                                          ),
                                                      )
                                                    : '—'}
                                            </p>
                                            {attempt.failure_summary && (
                                                <p className="mt-2 text-xs text-red-600">
                                                    {attempt.failure_summary}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                {order.payments.flatMap(
                                    (payment) => payment.attempts,
                                ).length === 0 && (
                                    <p className="text-sm text-slate-500">
                                        No payment attempts recorded.
                                    </p>
                                )}
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </BuyerPortalLayout>
    );
}
