import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    Banknote,
    CheckCircle2,
    CreditCard,
    Landmark,
    MailCheck,
    MapPin,
    PackageCheck,
    ReceiptText,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { StorefrontLayout } from '@/components/storefront-layout';
import { home } from '@/routes';
import { index as buyerOrdersIndex } from '@/routes/buyer/orders';
import type {
    CheckoutConfirmationOrder,
    CheckoutPaymentMethod,
    ShippingAddress,
} from '@/types';

const paymentMethods: Record<
    CheckoutPaymentMethod,
    { label: string; icon: LucideIcon }
> = {
    stripe: { label: 'Credit / Debit Card', icon: CreditCard },
    bank_transfer: { label: 'Bank Transfer', icon: Landmark },
    cod: { label: 'Cash on Delivery', icon: Banknote },
};

function formatPrice(value: string): string {
    return `LKR ${Number(value).toLocaleString('en-LK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function formatStatus(value: string): string {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function AddressCard({
    title,
    address,
    isBilling = false,
}: {
    title: string;
    address: ShippingAddress;
    isBilling?: boolean;
}) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-[0_3px_18px_rgba(15,23,42,0.04)] sm:p-6">
            <div className="flex items-center gap-3">
                <span className="grid size-9 place-items-center rounded-full bg-orange-50 text-[#ff5a00]">
                    <MapPin className="size-4" />
                </span>
                <div>
                    <h2 className="text-sm font-extrabold text-slate-950">
                        {title}
                    </h2>
                    {isBilling && (
                        <p className="mt-0.5 text-[11px] text-slate-500">
                            Same as shipping address
                        </p>
                    )}
                </div>
            </div>
            <address className="mt-4 text-xs leading-5 text-slate-600 not-italic">
                <strong className="block text-sm text-slate-950">
                    {address.recipient_name}
                </strong>
                <span className="block">{address.address_line_one}</span>
                {address.address_line_two && (
                    <span className="block">{address.address_line_two}</span>
                )}
                <span className="block">
                    {address.city}
                    {address.postal_code ? `, ${address.postal_code}` : ''}
                </span>
                <span className="block">Sri Lanka</span>
                <span className="mt-1 block font-semibold text-slate-700">
                    {address.phone}
                </span>
            </address>
        </section>
    );
}

function OrderItems({ order }: { order: CheckoutConfirmationOrder }) {
    return (
        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_3px_18px_rgba(15,23,42,0.04)]">
            <div className="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
                <div className="flex items-center gap-3">
                    <PackageCheck className="size-5 text-[#ff5a00]" />
                    <h2 className="text-base font-extrabold text-slate-950">
                        Order items
                    </h2>
                </div>
                <span className="text-xs font-semibold text-slate-500">
                    {order.items.reduce(
                        (quantity, item) => quantity + item.quantity,
                        0,
                    )}{' '}
                    item(s)
                </span>
            </div>
            <ul className="divide-y divide-slate-100 px-5 sm:px-6">
                {order.items.map((item) => (
                    <li key={item.id} className="py-5">
                        <div className="flex items-start justify-between gap-4">
                            <div className="min-w-0">
                                <h3 className="text-sm font-bold text-slate-950">
                                    {item.title}
                                </h3>
                                <p className="mt-1 text-xs text-slate-500">
                                    Sold by {item.seller}
                                </p>
                                {item.variantOptions && (
                                    <p className="mt-2 text-xs text-slate-500">
                                        {Object.entries(item.variantOptions)
                                            .map(
                                                ([option, value]) =>
                                                    `${option}: ${value}`,
                                            )
                                            .join(' · ')}
                                    </p>
                                )}
                                {item.variantSku && (
                                    <p className="mt-1 text-[11px] text-slate-400">
                                        SKU: {item.variantSku}
                                    </p>
                                )}
                                <p className="mt-2 text-xs font-semibold text-slate-600">
                                    {item.quantity} ×{' '}
                                    {formatPrice(item.unitPrice)}
                                </p>
                            </div>
                            <strong className="shrink-0 text-sm text-slate-950">
                                {formatPrice(item.total)}
                            </strong>
                        </div>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function OrderSummary({ order }: { order: CheckoutConfirmationOrder }) {
    const paymentMethod = order.payment
        ? paymentMethods[order.payment.method]
        : null;
    const PaymentIcon = paymentMethod?.icon;

    return (
        <aside className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_6px_24px_rgba(15,23,42,0.07)] lg:sticky lg:top-5">
            <div className="border-b border-slate-100 px-5 py-5">
                <div className="flex items-center gap-3">
                    <ReceiptText className="size-5 text-[#ff5a00]" />
                    <h2 className="text-lg font-black text-slate-950">
                        Order summary
                    </h2>
                </div>
            </div>
            <div className="px-5 py-5">
                <dl className="grid gap-3 text-xs">
                    <div className="flex justify-between gap-4">
                        <dt className="text-slate-600">Subtotal</dt>
                        <dd className="font-bold text-slate-900">
                            {formatPrice(order.subtotal)}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-slate-600">Delivery</dt>
                        <dd className="font-bold text-emerald-600">
                            {Number(order.shippingTotal) === 0
                                ? 'FREE'
                                : formatPrice(order.shippingTotal)}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-slate-600">Order status</dt>
                        <dd className="font-bold text-slate-900">
                            {formatStatus(order.status)}
                        </dd>
                    </div>
                </dl>
                <div className="mt-5 flex items-end justify-between gap-4 border-t border-slate-100 pt-5">
                    <span className="text-sm font-bold text-slate-950">
                        Total
                    </span>
                    <strong className="text-xl font-black text-[#ff5a00]">
                        {formatPrice(order.total)}
                    </strong>
                </div>
                {paymentMethod && order.payment && PaymentIcon && (
                    <div className="mt-5 flex items-start gap-3 rounded-lg bg-slate-50 p-4">
                        <PaymentIcon className="mt-0.5 size-4 shrink-0 text-[#ff5a00]" />
                        <span className="text-xs text-slate-600">
                            <strong className="block text-slate-950">
                                {paymentMethod.label}
                            </strong>
                            <span className="mt-1 block">
                                Payment: {formatStatus(order.payment.status)}
                            </span>
                        </span>
                    </div>
                )}
            </div>
        </aside>
    );
}

export default function BuyerThankYou({
    order,
}: {
    order: CheckoutConfirmationOrder;
}) {
    const placedDate = order.placedAt
        ? new Date(order.placedAt).toLocaleDateString('en-LK', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          })
        : null;

    return (
        <StorefrontLayout title="Thank You for Your Order">
            <Head title={`Order ${order.number} confirmed`} />
            <main className="mx-auto max-w-[82rem] px-4 py-7 sm:px-6 sm:py-10">
                <section className="overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-[0_8px_32px_rgba(15,23,42,0.07)]">
                    <div className="bg-emerald-50/80 px-5 py-8 text-center sm:px-8 sm:py-10">
                        <span className="mx-auto grid size-16 place-items-center rounded-full bg-emerald-600 text-white shadow-[0_8px_20px_rgba(5,150,105,0.25)]">
                            <CheckCircle2 className="size-9" />
                        </span>
                        <h1 className="mt-5 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
                            Thank you! Your order has been placed.
                        </h1>
                        <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-600">
                            We have sent an acknowledgement email with your
                            order details. Keep your order number for tracking
                            and support.
                        </p>
                        <div className="mx-auto mt-5 inline-flex flex-col items-center rounded-xl border border-emerald-200 bg-white px-7 py-4 shadow-sm">
                            <span className="text-[11px] font-bold tracking-widest text-slate-500 uppercase">
                                Order number
                            </span>
                            <strong className="mt-1 text-2xl font-black tracking-wide text-[#ff5a00]">
                                {order.number}
                            </strong>
                            {placedDate && (
                                <span className="mt-1 text-xs text-slate-500">
                                    Placed on {placedDate}
                                </span>
                            )}
                        </div>
                    </div>
                    <div className="flex items-start gap-3 border-t border-emerald-100 px-5 py-4 text-xs leading-5 text-emerald-900 sm:px-8">
                        <MailCheck className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                        <span>
                            Your confirmation email may take a few minutes to
                            arrive. Please check your spam folder if you do not
                            see it.
                        </span>
                    </div>
                </section>

                <div className="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_23rem] xl:grid-cols-[minmax(0,1fr)_26rem]">
                    <div className="grid gap-5">
                        <OrderItems order={order} />
                        <div className="grid gap-5 sm:grid-cols-2">
                            <AddressCard
                                title="Shipping address"
                                address={order.shippingAddress}
                            />
                            <AddressCard
                                title="Billing address"
                                address={order.billingAddress}
                                isBilling
                            />
                        </div>
                    </div>
                    <OrderSummary order={order} />
                </div>

                <div className="mt-7 flex flex-col justify-center gap-3 sm:flex-row">
                    <Link
                        href={buyerOrdersIndex()}
                        className="inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-[#ff5a00] px-6 text-sm font-extrabold text-white transition hover:bg-[#eb5200]"
                    >
                        View my orders
                        <ArrowRight className="size-4" />
                    </Link>
                    <Link
                        href={home()}
                        className="inline-flex h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-6 text-sm font-bold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50"
                    >
                        Continue shopping
                    </Link>
                </div>
            </main>
        </StorefrontLayout>
    );
}
