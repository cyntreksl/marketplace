import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CreditCard,
    PackageCheck,
    ShieldCheck,
    Truck,
} from 'lucide-react';
import {
    cancel,
    delivered,
    processing,
    ready,
    refund,
    shipped,
} from '@/actions/App/Http/Controllers/AdminOrderController';
import { PortalLayout } from '@/components/portal-layout';
import { SellerStatusBadge } from '@/components/seller-status-badge';
import { index } from '@/routes/admin/orders';
import type { AdminOrder, SellerOrder } from '@/types';

function money(value: string): string {
    return `LKR ${Number(value).toLocaleString('en-LK', { minimumFractionDigits: 2 })}`;
}

function Address({
    title,
    address,
}: {
    title: string;
    address: Record<string, string | null> | null;
}) {
    const recipient = address?.recipient_name ?? address?.name;

    return (
        <div className="rounded-xl bg-slate-50 p-4 dark:bg-slate-950">
            <p className="text-xs font-bold tracking-wide text-slate-400 uppercase">
                {title}
            </p>
            {address ? (
                <address className="mt-2 text-sm leading-6 not-italic">
                    <strong>{recipient}</strong>
                    <br />
                    {address.address_line_one ??
                        address.line_1 ??
                        address.line1}
                    <br />
                    {(address.address_line_two ??
                        address.line_2 ??
                        address.line2) && (
                        <>
                            {address.address_line_two ??
                                address.line_2 ??
                                address.line2}
                            <br />
                        </>
                    )}
                    {address.city} {address.postal_code}
                    <br />
                    {address.phone}
                </address>
            ) : (
                <p className="mt-2 text-sm text-slate-500">Not available</p>
            )}
        </div>
    );
}

function PackageActions({
    customerOrderNumber,
    order,
}: {
    customerOrderNumber: string;
    order: SellerOrder;
}) {
    const args = {
        customerOrder: customerOrderNumber,
        sellerOrder: order.number,
    };

    return (
        <div className="space-y-4 rounded-2xl border border-orange-200 bg-orange-50 p-5 dark:border-orange-500/20 dark:bg-orange-500/10">
            <div className="flex gap-3">
                <ShieldCheck className="mt-0.5 size-5 shrink-0 text-orange-700" />
                <div>
                    <h3 className="font-black">Acting on behalf of seller</h3>
                    <p className="mt-1 text-xs text-slate-600 dark:text-slate-300">
                        Actions use the seller workflow and record you as the
                        administrator actor.
                    </p>
                </div>
            </div>

            {order.next_action?.action === 'shipped' ? (
                <Form {...shipped.form(args)} className="grid gap-3">
                    {({ errors, processing: busy }) => (
                        <>
                            <input
                                name="courier_name"
                                required
                                maxLength={100}
                                placeholder="Courier name"
                                className="min-h-11 rounded-xl border bg-white px-3 text-sm dark:bg-slate-950"
                            />
                            <input
                                name="tracking_number"
                                maxLength={100}
                                placeholder="Tracking number (optional)"
                                className="min-h-11 rounded-xl border bg-white px-3 text-sm dark:bg-slate-950"
                            />
                            {errors.courier_name && (
                                <p className="text-xs text-red-600">
                                    {errors.courier_name}
                                </p>
                            )}
                            <button
                                disabled={busy}
                                className="min-h-11 rounded-xl bg-orange-600 px-4 text-sm font-bold text-white disabled:opacity-50"
                            >
                                Dispatch package
                            </button>
                        </>
                    )}
                </Form>
            ) : order.next_action ? (
                <Form
                    {...(order.next_action.action === 'processing'
                        ? processing.form(args)
                        : order.next_action.action === 'ready'
                          ? ready.form(args)
                          : delivered.form(args))}
                >
                    {({ processing: busy }) => (
                        <button
                            disabled={busy}
                            className="min-h-11 w-full rounded-xl bg-orange-600 px-4 text-sm font-bold text-white disabled:opacity-50"
                        >
                            {order.next_action?.label}
                        </button>
                    )}
                </Form>
            ) : (
                <p className="rounded-xl bg-white/70 p-3 text-sm text-slate-500 dark:bg-slate-950/50">
                    No fulfillment action is currently available.
                </p>
            )}

            {order.can_cancel && (
                <Form
                    {...cancel.form(args)}
                    className="grid gap-2 border-t border-orange-200 pt-4"
                >
                    {({ errors, processing: busy }) => (
                        <>
                            <label className="text-sm font-bold">
                                Cancellation reason
                                <textarea
                                    name="reason"
                                    required
                                    minLength={10}
                                    maxLength={1000}
                                    rows={3}
                                    placeholder="For example: item is out of stock"
                                    className="mt-1 w-full rounded-xl border bg-white p-3 font-normal dark:bg-slate-950"
                                />
                            </label>
                            {errors.reason && (
                                <p className="text-xs text-red-600">
                                    {errors.reason}
                                </p>
                            )}
                            {errors.order && (
                                <p className="text-xs text-red-600">
                                    {errors.order}
                                </p>
                            )}
                            <button
                                disabled={busy}
                                className="min-h-11 rounded-xl border border-red-300 bg-white px-4 text-sm font-bold text-red-700 disabled:opacity-50 dark:bg-slate-950"
                            >
                                Cancel package
                            </button>
                        </>
                    )}
                </Form>
            )}

            {order.refund?.status === 'pending' && (
                <Form
                    {...refund.form(args)}
                    className="grid gap-2 border-t border-orange-200 pt-4"
                >
                    {({ errors, processing: busy }) => (
                        <>
                            <p className="text-sm font-black">
                                Record manual refund
                            </p>
                            <input
                                name="amount"
                                type="number"
                                required
                                min="0.01"
                                step="0.01"
                                placeholder="Actual refund amount"
                                className="min-h-11 rounded-xl border bg-white px-3 text-sm dark:bg-slate-950"
                            />
                            <input
                                name="reference"
                                required
                                maxLength={255}
                                placeholder="Manual/external reference"
                                className="min-h-11 rounded-xl border bg-white px-3 text-sm dark:bg-slate-950"
                            />
                            {(errors.amount ||
                                errors.reference ||
                                errors.refund) && (
                                <p className="text-xs text-red-600">
                                    {errors.amount ??
                                        errors.reference ??
                                        errors.refund}
                                </p>
                            )}
                            <button
                                disabled={busy}
                                className="min-h-11 rounded-xl bg-slate-950 px-4 text-sm font-bold text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
                            >
                                Mark payment refunded
                            </button>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}

export default function AdminOrderShow({ order }: { order: AdminOrder }) {
    return (
        <PortalLayout portal="admin" title={`Order ${order.number}`}>
            <Head title={`Order ${order.number}`} />
            <div className="space-y-6">
                <Link
                    href={index()}
                    className="inline-flex items-center gap-2 text-sm font-bold text-orange-700"
                >
                    <ArrowLeft className="size-4" /> Back to all orders
                </Link>
                <header className="flex flex-col gap-4 rounded-3xl bg-slate-950 p-6 text-white sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-wider text-orange-300 uppercase">
                            Customer order
                        </p>
                        <h1 className="mt-2 text-3xl font-black">
                            {order.number}
                        </h1>
                        <p className="mt-2 text-sm text-slate-300">
                            {order.buyer.name} · {order.buyer.email} ·{' '}
                            {new Date(order.created_at).toLocaleString()}
                        </p>
                    </div>
                    <div className="sm:text-right">
                        <p className="text-2xl font-black">
                            {money(order.total)}
                        </p>
                        <p className="mt-1 text-sm text-slate-300 capitalize">
                            {order.status.replaceAll('_', ' ')}
                        </p>
                    </div>
                </header>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Address
                        title="Shipping address"
                        address={order.shipping_address}
                    />
                    <Address
                        title="Billing address"
                        address={order.billing_address}
                    />
                    <section className="rounded-xl bg-slate-50 p-4 dark:bg-slate-950">
                        <h2 className="flex items-center gap-2 text-xs font-bold tracking-wide text-slate-400 uppercase">
                            <CreditCard className="size-4" /> Payments
                        </h2>
                        <div className="mt-3 space-y-3">
                            {order.payments.map((payment) => (
                                <div key={payment.id} className="text-sm">
                                    <p className="font-bold capitalize">
                                        {payment.method.replaceAll('_', ' ')} ·{' '}
                                        {payment.status.replaceAll('_', ' ')}
                                    </p>
                                    <p className="text-slate-500">
                                        {money(payment.amount)}
                                        {payment.paid_at
                                            ? ` · Paid ${new Date(payment.paid_at).toLocaleString()}`
                                            : ''}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>
                </div>

                {order.packages.map((sellerPackage) => (
                    <section
                        key={sellerPackage.number}
                        className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div className="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                            <div>
                                <h2 className="text-lg font-black">
                                    {sellerPackage.seller.store_name}
                                </h2>
                                <p className="text-xs text-slate-500">
                                    Package {sellerPackage.number}
                                </p>
                            </div>
                            <SellerStatusBadge
                                status={sellerPackage.status}
                                label={sellerPackage.status_label}
                            />
                        </div>
                        <div className="grid gap-6 p-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                            <div className="space-y-5">
                                <div className="divide-y divide-slate-100 rounded-xl border dark:divide-slate-800">
                                    {sellerPackage.items.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex justify-between gap-4 p-4 text-sm"
                                        >
                                            <div>
                                                <p className="font-bold">
                                                    {item.title}
                                                </p>
                                                <p className="text-slate-500">
                                                    {item.quantity} ×{' '}
                                                    {money(item.unit_price)}
                                                </p>
                                            </div>
                                            <p className="font-black">
                                                {money(item.total)}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                                <dl className="grid gap-2 text-sm sm:grid-cols-3">
                                    <div>
                                        <dt className="text-slate-500">
                                            Subtotal
                                        </dt>
                                        <dd className="font-bold">
                                            {money(sellerPackage.subtotal)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-slate-500">
                                            Shipping
                                        </dt>
                                        <dd className="font-bold">
                                            {money(
                                                sellerPackage.shipping_charge,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-slate-500">
                                            Seller earnings
                                        </dt>
                                        <dd className="font-bold">
                                            {money(
                                                sellerPackage.seller_earnings,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                                {sellerPackage.shipment && (
                                    <div className="rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-950">
                                        <p className="flex items-center gap-2 font-black">
                                            <Truck className="size-4" />{' '}
                                            Shipment
                                        </p>
                                        <p className="mt-2 capitalize">
                                            {sellerPackage.shipment.status.replaceAll(
                                                '_',
                                                ' ',
                                            )}{' '}
                                            ·{' '}
                                            {sellerPackage.shipment
                                                .courier_name ??
                                                'No courier'}{' '}
                                            ·{' '}
                                            {sellerPackage.shipment
                                                .tracking_number ??
                                                'No tracking number'}
                                        </p>
                                    </div>
                                )}
                                {sellerPackage.cancellation && (
                                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200">
                                        <p className="font-black">
                                            Package cancelled
                                        </p>
                                        <p className="mt-1">
                                            {sellerPackage.cancellation.reason}
                                        </p>
                                        <p className="mt-2 text-xs">
                                            {new Date(
                                                sellerPackage.cancellation
                                                    .cancelled_at,
                                            ).toLocaleString()}{' '}
                                            ·{' '}
                                            {sellerPackage.cancellation
                                                .cancelled_by?.name ??
                                                'Unknown actor'}
                                        </p>
                                    </div>
                                )}
                                {sellerPackage.refund && (
                                    <div className="rounded-xl border p-4 text-sm">
                                        <p className="flex items-center gap-2 font-black">
                                            <PackageCheck className="size-4" />{' '}
                                            Refund:{' '}
                                            <span className="capitalize">
                                                {sellerPackage.refund.status}
                                            </span>
                                        </p>
                                        <p className="mt-1 text-slate-500">
                                            {sellerPackage.refund.amount
                                                ? money(
                                                      sellerPackage.refund
                                                          .amount,
                                                  )
                                                : 'Awaiting amount'}
                                            {sellerPackage.refund
                                                .manual_reference
                                                ? ` · ${sellerPackage.refund.manual_reference}`
                                                : ''}
                                        </p>
                                    </div>
                                )}
                            </div>
                            <PackageActions
                                customerOrderNumber={order.number}
                                order={sellerPackage}
                            />
                        </div>
                    </section>
                ))}
            </div>
        </PortalLayout>
    );
}
