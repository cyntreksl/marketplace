import { Form, Head, Link } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    Circle,
    MapPin,
    PackageCheck,
    Truck,
} from 'lucide-react';
import {
    delivered,
    processing,
    ready,
    shipped,
} from '@/actions/App/Http/Controllers/SellerOrderController';
import { SellerPageHeader } from '@/components/seller-page-header';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { SellerStatusBadge } from '@/components/seller-status-badge';
import { index } from '@/routes/seller/orders';
import type { SellerOrder } from '@/types';

function ActionPanel({ order }: { order: SellerOrder }) {
    if (!order.next_action) {
        return (
            <p className="rounded-xl bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-950">
                This order is read-only. There is no seller action due.
            </p>
        );
    }

    if (order.next_action.action === 'shipped') {
        return (
            <Form {...shipped.form(order.number)} className="grid gap-3">
                <label className="grid gap-1 text-sm font-semibold">
                    Courier name
                    <input
                        name="courier_name"
                        required
                        maxLength={100}
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    />
                </label>
                <label className="grid gap-1 text-sm font-semibold">
                    Tracking number{' '}
                    <span className="font-normal text-slate-500">
                        (optional)
                    </span>
                    <input
                        name="tracking_number"
                        maxLength={100}
                        placeholder="Generated automatically when blank"
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    />
                </label>
                <button className="min-h-11 rounded-xl bg-orange-600 px-5 text-sm font-bold text-white">
                    Dispatch order
                </button>
            </Form>
        );
    }

    const action =
        order.next_action.action === 'processing'
            ? processing.form(order.number)
            : order.next_action.action === 'ready'
              ? ready.form(order.number)
              : delivered.form(order.number);

    return (
        <Form {...action}>
            {({ processing: busy }) => (
                <button
                    disabled={busy}
                    className="min-h-11 w-full rounded-xl bg-orange-600 px-5 text-sm font-bold text-white disabled:opacity-50"
                >
                    {order.next_action?.label}
                </button>
            )}
        </Form>
    );
}

export default function SellerOrderShow({ order }: { order: SellerOrder }) {
    const recipient = order.recipient;

    return (
        <SellerPortalLayout title={`Order ${order.number}`}>
            <Head title={`Seller order ${order.number}`} />
            <div className="space-y-6">
                <Link
                    href={index()}
                    className="inline-flex items-center gap-1 text-sm font-bold text-orange-700"
                >
                    <ChevronLeft className="size-4" /> Back to orders
                </Link>
                <SellerPageHeader
                    eyebrow="Order detail"
                    title={order.number}
                    description={`Customer order ${order.customer_order_number} · Placed ${new Date(order.created_at).toLocaleString()}`}
                    actions={
                        <SellerStatusBadge
                            status={order.status}
                            label={order.status_label}
                        />
                    }
                />
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">
                    <div className="space-y-6">
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="text-lg font-black">
                                Order journey
                            </h2>
                            <ol className="mt-6 grid gap-0 sm:grid-cols-5">
                                {order.timeline?.map((step, index) => (
                                    <li
                                        key={step.status}
                                        className="relative flex gap-3 pb-6 sm:block sm:pb-0"
                                    >
                                        <div
                                            className={`absolute top-4 bottom-0 left-4 w-px sm:top-4 sm:right-0 sm:bottom-auto sm:left-4 sm:h-px sm:w-auto ${index === (order.timeline?.length ?? 0) - 1 ? 'hidden' : ''} ${step.complete ? 'bg-orange-500' : 'bg-slate-200 dark:bg-slate-700'}`}
                                        />
                                        <span
                                            className={`relative z-10 grid size-8 place-items-center rounded-full border-2 ${step.complete ? 'border-orange-600 bg-orange-600 text-white' : 'border-slate-300 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-900'}`}
                                        >
                                            {step.complete ? (
                                                <Check className="size-4" />
                                            ) : (
                                                <Circle className="size-3" />
                                            )}
                                        </span>
                                        <div className="sm:mt-3 sm:pr-3">
                                            <p
                                                className={`text-sm font-bold ${step.current ? 'text-orange-700 dark:text-orange-300' : ''}`}
                                            >
                                                {step.label}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {step.at
                                                    ? new Date(
                                                          step.at,
                                                      ).toLocaleString()
                                                    : 'Not yet recorded'}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </section>
                        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                                <h2 className="text-lg font-black">Products</h2>
                            </div>
                            <div className="divide-y divide-slate-100 dark:divide-slate-800">
                                {order.items.map((item) => (
                                    <div
                                        key={item.id}
                                        className="grid gap-3 p-5 sm:grid-cols-[1fr_auto]"
                                    >
                                        <div>
                                            <p className="font-bold">
                                                {item.title}
                                            </p>
                                            {item.variant_options && (
                                                <p className="mt-1 text-sm text-slate-500">
                                                    {Object.entries(
                                                        item.variant_options,
                                                    )
                                                        .map(
                                                            ([key, value]) =>
                                                                `${key}: ${value}`,
                                                        )
                                                        .join(' · ')}
                                                </p>
                                            )}
                                            <p className="mt-1 text-sm text-slate-500">
                                                Quantity {item.quantity} × LKR{' '}
                                                {Number(
                                                    item.unit_price,
                                                ).toLocaleString()}
                                            </p>
                                        </div>
                                        <p className="font-black">
                                            LKR{' '}
                                            {Number(
                                                item.total,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <dl className="space-y-2 border-t border-slate-200 p-5 text-sm dark:border-slate-800">
                                <div className="flex justify-between">
                                    <dt>Subtotal</dt>
                                    <dd>
                                        LKR{' '}
                                        {Number(
                                            order.subtotal,
                                        ).toLocaleString()}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Shipping charge</dt>
                                    <dd>
                                        LKR{' '}
                                        {Number(
                                            order.shipping_charge,
                                        ).toLocaleString()}
                                    </dd>
                                </div>
                                <div className="flex justify-between pt-2 text-base font-black">
                                    <dt>Your earnings</dt>
                                    <dd>
                                        LKR{' '}
                                        {Number(
                                            order.seller_earnings,
                                        ).toLocaleString()}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                    </div>
                    <aside className="space-y-5">
                        <section className="rounded-2xl border border-orange-200 bg-orange-50 p-5 dark:border-orange-500/20 dark:bg-orange-500/10">
                            <h2 className="font-black">Next action</h2>
                            <p className="mt-1 mb-4 text-sm text-slate-600 dark:text-slate-300">
                                {order.next_action?.label ??
                                    'No action required'}
                            </p>
                            <ActionPanel order={order} />
                        </section>
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="flex items-center gap-2 font-black">
                                <MapPin className="size-4" /> Delivery
                            </h2>
                            <address className="mt-3 text-sm leading-6 text-slate-600 not-italic dark:text-slate-300">
                                <strong className="text-slate-950 dark:text-white">
                                    {recipient?.name}
                                </strong>
                                <br />
                                {recipient?.address_line_one}
                                <br />
                                {recipient?.address_line_two && (
                                    <>
                                        {recipient.address_line_two}
                                        <br />
                                    </>
                                )}
                                {recipient?.city} {recipient?.postal_code}
                                <br />
                                {recipient?.phone}
                            </address>
                        </section>
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <h2 className="flex items-center gap-2 font-black">
                                <Truck className="size-4" /> Courier & payment
                            </h2>
                            <dl className="mt-3 space-y-3 text-sm">
                                <div>
                                    <dt className="text-slate-500">
                                        Payment method
                                    </dt>
                                    <dd className="font-semibold capitalize">
                                        {order.payment_method?.replaceAll(
                                            '_',
                                            ' ',
                                        ) ?? 'Not available'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-slate-500">Courier</dt>
                                    <dd className="font-semibold">
                                        {order.shipment?.courier_name ??
                                            'Not assigned'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-slate-500">Tracking</dt>
                                    <dd className="font-mono text-xs break-all">
                                        {order.shipment?.tracking_number ??
                                            'Not generated'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-slate-500">
                                        Shipment state
                                    </dt>
                                    <dd className="flex items-center gap-2 font-semibold capitalize">
                                        <PackageCheck className="size-4" />{' '}
                                        {order.shipment?.status.replaceAll(
                                            '_',
                                            ' ',
                                        ) ?? 'Pending'}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                    </aside>
                </div>
            </div>
        </SellerPortalLayout>
    );
}
