import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    CheckCircle2,
    CreditCard,
    Landmark,
    LockKeyhole,
    MapPin,
    PackageCheck,
    ShieldCheck,
    Truck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { CheckoutProgress } from '@/components/checkout-progress';
import { StorefrontLayout } from '@/components/storefront-layout';
import { show as cartShow } from '@/routes/cart';
import { show as checkoutShow } from '@/routes/checkout';
import { show as paymentShow } from '@/routes/checkout/payment';
import { store as reviewStore } from '@/routes/checkout/review';
import { terms } from '@/routes/legal';
import type {
    CheckoutCart,
    CheckoutCartItem,
    CheckoutPaymentMethod,
    ShippingAddress,
} from '@/types';

const paymentMethods: Record<
    CheckoutPaymentMethod,
    { title: string; description: string; icon: LucideIcon }
> = {
    stripe: {
        title: 'Credit / Debit Card',
        description: 'Payment is required before the order is released.',
        icon: CreditCard,
    },
    bank_transfer: {
        title: 'Bank Transfer',
        description: 'Your order will wait for transfer confirmation.',
        icon: Landmark,
    },
    cod: {
        title: 'Cash on Delivery',
        description: 'Pay in cash when your order arrives.',
        icon: Banknote,
    },
};

function itemPrice(item: CheckoutCartItem): number {
    return Number(
        item.variant?.selling_price ??
            item.listing.sale_price ??
            item.listing.price,
    );
}

function formatPrice(value: number): string {
    return `LKR ${value.toLocaleString('en-LK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function SectionHeading({
    title,
    editHref,
    editLabel,
}: {
    title: string;
    editHref: ReturnType<typeof checkoutShow>;
    editLabel: string;
}) {
    return (
        <div className="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 sm:px-6">
            <h2 className="text-base font-extrabold text-slate-950">{title}</h2>
            <Link
                href={editHref}
                className="text-xs font-bold text-[#ff5a00] hover:underline"
            >
                {editLabel}
            </Link>
        </div>
    );
}

function OrderItems({ cart }: { cart: CheckoutCart }) {
    return (
        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_3px_18px_rgba(15,23,42,0.04)]">
            <SectionHeading
                title={`Order Items (${cart.items.length})`}
                editHref={cartShow()}
                editLabel="Edit cart"
            />
            <ul className="divide-y divide-slate-100 px-5 sm:px-6">
                {cart.items.map((item) => {
                    const media = item.listing.media[0];
                    const imageUrl = media?.cardUrl ?? media?.url;

                    return (
                        <li key={item.id} className="flex gap-4 py-5">
                            <div className="grid size-20 shrink-0 place-items-center overflow-hidden rounded-lg border border-slate-100 bg-slate-50">
                                {imageUrl ? (
                                    <img
                                        src={imageUrl}
                                        srcSet={
                                            media?.card2xUrl
                                                ? `${imageUrl} 640w, ${media.card2xUrl} 1280w`
                                                : undefined
                                        }
                                        alt={item.listing.title}
                                        className="size-full object-contain p-2"
                                    />
                                ) : (
                                    <PackageCheck className="size-7 text-slate-300" />
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 className="text-sm font-bold text-slate-950">
                                            {item.listing.title}
                                        </h3>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {
                                                item.listing.seller_profile
                                                    .store_name
                                            }
                                        </p>
                                    </div>
                                    <strong className="shrink-0 text-sm text-slate-950">
                                        {formatPrice(
                                            itemPrice(item) * item.quantity,
                                        )}
                                    </strong>
                                </div>
                                {item.variant && (
                                    <p className="mt-2 text-xs text-slate-500">
                                        {item.variant.option_values
                                            .map(
                                                (option) =>
                                                    `${option.option.name}: ${option.value}`,
                                            )
                                            .join(' · ')}
                                    </p>
                                )}
                                <p className="mt-2 text-xs font-semibold text-slate-600">
                                    Quantity: {item.quantity}
                                </p>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

function DeliveryDetails({
    shippingAddress,
}: {
    shippingAddress: ShippingAddress;
}) {
    return (
        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_3px_18px_rgba(15,23,42,0.04)]">
            <SectionHeading
                title="Delivery Details"
                editHref={checkoutShow()}
                editLabel="Change address"
            />
            <div className="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                <div className="flex items-start gap-3">
                    <span className="grid size-10 shrink-0 place-items-center rounded-full bg-orange-50 text-[#ff5a00]">
                        <MapPin className="size-5" />
                    </span>
                    <address className="text-xs leading-5 text-slate-600 not-italic">
                        <strong className="block text-sm text-slate-950">
                            {shippingAddress.recipient_name}
                        </strong>
                        <span className="block">
                            {shippingAddress.address_line_one}
                        </span>
                        {shippingAddress.address_line_two && (
                            <span className="block">
                                {shippingAddress.address_line_two}
                            </span>
                        )}
                        <span className="block">
                            {shippingAddress.city}
                            {shippingAddress.postal_code
                                ? `, ${shippingAddress.postal_code}`
                                : ''}
                        </span>
                        <span className="block">Sri Lanka</span>
                        <span className="mt-1 block font-semibold text-slate-700">
                            {shippingAddress.phone}
                        </span>
                    </address>
                </div>
                <div className="flex items-start gap-3 rounded-lg bg-slate-50 p-4">
                    <Truck className="mt-0.5 size-5 shrink-0 text-[#ff5a00]" />
                    <span>
                        <strong className="block text-xs text-slate-950">
                            Islandwide Standard Delivery
                        </strong>
                        <span className="mt-1 block text-[11px] leading-5 text-slate-500">
                            Delivery timing is confirmed after your order is
                            placed.
                        </span>
                    </span>
                </div>
            </div>
        </section>
    );
}

function PaymentDetails({
    paymentMethod,
}: {
    paymentMethod: CheckoutPaymentMethod;
}) {
    const method = paymentMethods[paymentMethod];
    const Icon = method.icon;

    return (
        <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_3px_18px_rgba(15,23,42,0.04)]">
            <SectionHeading
                title="Payment Method"
                editHref={paymentShow()}
                editLabel="Change payment"
            />
            <div className="flex items-start gap-3 p-5 sm:p-6">
                <span className="grid size-11 shrink-0 place-items-center rounded-full bg-orange-50 text-[#ff5a00]">
                    <Icon className="size-5" />
                </span>
                <span>
                    <strong className="block text-sm text-slate-950">
                        {method.title}
                    </strong>
                    <span className="mt-1 block text-xs leading-5 text-slate-500">
                        {method.description}
                    </span>
                </span>
            </div>
        </section>
    );
}

function OrderSummary({
    cart,
    paymentMethod,
    checkoutToken,
    reviewHash,
}: {
    checkoutToken: string;
    reviewHash: string;
    cart: CheckoutCart;
    paymentMethod: CheckoutPaymentMethod;
}) {
    const subtotal = Number(cart.subtotal);

    return (
        <aside className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_6px_24px_rgba(15,23,42,0.07)] lg:sticky lg:top-5">
            <div className="border-b border-slate-100 px-5 py-5">
                <h2 className="text-lg font-black text-slate-950">
                    Order Summary
                </h2>
                <p className="mt-1 text-xs text-slate-500">
                    Review the final total before placing your order.
                </p>
            </div>
            <div className="px-5 py-5">
                <dl className="grid gap-3 text-xs">
                    <div className="flex justify-between gap-4">
                        <dt className="text-slate-600">Subtotal</dt>
                        <dd className="font-bold text-slate-900">
                            {formatPrice(subtotal)}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-slate-600">Delivery</dt>
                        <dd className="font-bold text-emerald-600">
                            {formatPrice(Number(cart.shippingTotal))}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-slate-600">VAT</dt>
                        <dd className="font-bold text-slate-900">Included</dd>
                    </div>
                </dl>
                <div className="mt-5 flex items-end justify-between gap-4 border-t border-slate-100 pt-5">
                    <span>
                        <strong className="block text-sm text-slate-950">
                            Total Payable
                        </strong>
                        <span className="text-[10px] text-slate-500">
                            Inclusive of VAT
                        </span>
                    </span>
                    <strong className="text-xl font-black text-[#ff5a00]">
                        {formatPrice(Number(cart.total))}
                    </strong>
                </div>
            </div>
            <Form
                {...reviewStore.form()}
                className="border-t border-slate-100 bg-orange-50/50 px-5 py-5"
            >
                {({ errors, processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="checkout_token"
                            value={checkoutToken}
                        />
                        <input
                            type="hidden"
                            name="review_hash"
                            value={reviewHash}
                        />
                        {Object.values(errors).length > 0 && (
                            <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                                {Object.entries(errors).map(
                                    ([field, error]) => (
                                        <p key={field}>{error}</p>
                                    ),
                                )}
                            </div>
                        )}
                        <button
                            type="submit"
                            disabled={processing || !cart.canCheckout}
                            className="flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-[#ff5a00] px-4 text-sm font-extrabold text-white shadow-[0_8px_20px_rgba(255,90,0,0.24)] transition hover:bg-[#eb5200] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <LockKeyhole className="size-4" />
                            {processing ? 'Placing order...' : 'Place Order'}
                        </button>
                        <p className="mt-3 text-center text-[10px] leading-5 text-slate-500">
                            By placing your order, you agree to our{' '}
                            <Link
                                href={terms()}
                                className="font-semibold text-[#ff5a00] hover:underline"
                            >
                                Terms & Conditions
                            </Link>
                            .
                        </p>
                        <p className="mt-3 flex items-center justify-center gap-1.5 text-[10px] font-semibold text-emerald-700">
                            <ShieldCheck className="size-3.5" />
                            Secure checkout ·{' '}
                            {paymentMethods[paymentMethod].title}
                        </p>
                    </>
                )}
            </Form>
        </aside>
    );
}

export default function BuyerReview({
    cart,
    shippingAddress,
    paymentMethod,
    checkoutToken,
    reviewHash,
}: {
    checkoutToken: string;
    reviewHash: string;
    cart: CheckoutCart;
    shippingAddress: ShippingAddress;
    paymentMethod: CheckoutPaymentMethod;
}) {
    return (
        <StorefrontLayout title="Review & Place Order">
            <Head title="Review & Place Order" />
            <main className="mx-auto max-w-[82rem] px-4 py-5 sm:px-6 sm:py-7">
                <CheckoutProgress current="review" />

                <header className="mt-7 flex items-center gap-4">
                    <span className="grid size-12 shrink-0 place-items-center rounded-full bg-orange-50 text-[#ff5a00]">
                        <CheckCircle2 className="size-6" />
                    </span>
                    <div>
                        <h1 className="text-2xl font-black tracking-tight text-slate-950">
                            Review & Place Order
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Check your items, delivery details, and payment
                            method.
                        </p>
                    </div>
                </header>

                {cart.items.length === 0 ? (
                    <section className="mx-auto my-10 max-w-xl rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
                        <PackageCheck className="mx-auto size-12 text-slate-300" />
                        <h2 className="mt-4 text-xl font-black text-slate-950">
                            Your cart is empty
                        </h2>
                        <p className="mt-2 text-sm text-slate-500">
                            Add an item before placing your order.
                        </p>
                        <Link
                            href={cartShow()}
                            className="mt-6 inline-flex items-center gap-2 rounded-lg bg-[#ff5a00] px-5 py-3 text-sm font-bold text-white"
                        >
                            <ArrowLeft className="size-4" />
                            Return to cart
                        </Link>
                    </section>
                ) : (
                    <div className="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_23rem] xl:grid-cols-[minmax(0,1fr)_26rem]">
                        <div className="grid gap-5">
                            <OrderItems cart={cart} />
                            <DeliveryDetails
                                shippingAddress={shippingAddress}
                            />
                            <PaymentDetails paymentMethod={paymentMethod} />
                            <section className="flex items-start gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 p-5 text-xs leading-5 text-emerald-900">
                                <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                <span>
                                    <strong className="block">
                                        Order acknowledgment
                                    </strong>
                                    <span className="mt-1 block text-emerald-800/80">
                                        We will email your order number and
                                        summary as soon as the order is placed.
                                    </span>
                                </span>
                            </section>
                        </div>
                        <OrderSummary
                            cart={cart}
                            paymentMethod={paymentMethod}
                            checkoutToken={checkoutToken}
                            reviewHash={reviewHash}
                        />
                    </div>
                )}
            </main>
        </StorefrontLayout>
    );
}
