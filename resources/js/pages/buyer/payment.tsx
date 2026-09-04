import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    CalendarDays,
    CheckCircle2,
    CircleHelp,
    Clock3,
    CreditCard,
    Headphones,
    Info,
    Landmark,
    LockKeyhole,
    Mail,
    MapPin,
    PackageCheck,
    ShieldCheck,
    Smartphone,
    Truck,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { CheckoutProgress } from '@/components/checkout-progress';
import { StorefrontLayout } from '@/components/storefront-layout';
import { show as cartShow } from '@/routes/cart';
import { show as checkoutShow } from '@/routes/checkout';
import { store as paymentStore } from '@/routes/checkout/payment';
import { terms } from '@/routes/legal';
import type {
    CheckoutCart,
    CheckoutCartItem,
    CheckoutPaymentMethod,
    ShippingAddress,
} from '@/types';

type PaymentMethodOption = {
    value: CheckoutPaymentMethod | 'mobile_wallet' | 'installment';
    title: string;
    description: string;
    icon: LucideIcon;
    disabled?: boolean;
};

const paymentMethods: PaymentMethodOption[] = [
    {
        value: 'stripe',
        title: 'Credit / Debit Card',
        description: 'Visa, Mastercard & more',
        icon: CreditCard,
    },
    {
        value: 'bank_transfer',
        title: 'Bank Transfer',
        description: 'Direct bank payments',
        icon: Landmark,
    },
    {
        value: 'mobile_wallet',
        title: 'Mobile Wallet',
        description: 'Coming soon',
        icon: Smartphone,
        disabled: true,
    },
    {
        value: 'cod',
        title: 'Cash on Delivery',
        description: 'Pay when you receive',
        icon: PackageCheck,
    },
    {
        value: 'installment',
        title: 'Installment Plan',
        description: 'Coming soon',
        icon: CalendarDays,
        disabled: true,
    },
];

const inputClassName =
    'h-12 w-full rounded-lg border border-slate-200 bg-white px-4 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#ff5a00] focus:ring-3 focus:ring-orange-100';

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

function PaymentMethodButton({
    option,
    selected,
    onSelect,
}: {
    option: PaymentMethodOption;
    selected: boolean;
    onSelect: (method: CheckoutPaymentMethod) => void;
}) {
    const Icon = option.icon;

    return (
        <button
            type="button"
            disabled={option.disabled}
            aria-pressed={selected}
            onClick={() => {
                if (!option.disabled) {
                    onSelect(option.value as CheckoutPaymentMethod);
                }
            }}
            className={`flex w-full items-center gap-3 rounded-lg border px-4 py-4 text-left transition focus-visible:ring-3 focus-visible:ring-orange-100 focus-visible:outline-none ${
                selected
                    ? 'border-[#ff5a00] bg-orange-50/60 shadow-sm'
                    : 'border-slate-200 bg-white hover:border-orange-200'
            } disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-55`}
        >
            <span
                className={`grid size-9 shrink-0 place-items-center rounded-lg ${
                    selected
                        ? 'bg-white text-[#ff5a00]'
                        : 'bg-slate-50 text-slate-700'
                }`}
            >
                <Icon className="size-5" />
            </span>
            <span className="min-w-0">
                <strong
                    className={`block text-xs ${
                        selected ? 'text-[#ff5a00]' : 'text-slate-950'
                    }`}
                >
                    {option.title}
                </strong>
                <span className="mt-0.5 block text-[10px] text-slate-500">
                    {option.description}
                </span>
            </span>
        </button>
    );
}

function CardDetails() {
    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 className="text-base font-extrabold text-slate-950">
                        Pay securely with your card
                    </h2>
                    <p className="mt-1 text-xs text-slate-500">
                        Card fields are checked securely in your browser.
                    </p>
                </div>
                <div
                    className="flex items-center gap-2"
                    aria-label="Accepted card networks"
                >
                    <span className="text-base font-black tracking-tighter text-blue-700 italic">
                        VISA
                    </span>
                    <span className="flex -space-x-1">
                        <span className="size-5 rounded-full bg-red-500" />
                        <span className="size-5 rounded-full bg-amber-400 opacity-90" />
                    </span>
                    <span className="rounded bg-cyan-600 px-1.5 py-1 text-[8px] font-black text-white">
                        AMEX
                    </span>
                </div>
            </div>

            <div className="mt-7 grid gap-5">
                <label className="grid gap-2 text-xs font-semibold text-slate-700">
                    <span>Card Number</span>
                    <span className="relative">
                        <input
                            required
                            inputMode="numeric"
                            autoComplete="cc-number"
                            minLength={15}
                            maxLength={19}
                            placeholder="1234 5678 9012 3456"
                            aria-label="Card number"
                            onInput={(event) => {
                                event.currentTarget.value =
                                    event.currentTarget.value
                                        .replace(/\D/g, '')
                                        .slice(0, 16)
                                        .replace(/(.{4})/g, '$1 ')
                                        .trim();
                            }}
                            className={`${inputClassName} pr-11`}
                        />
                        <CreditCard className="pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2 text-slate-400" />
                    </span>
                </label>

                <label className="grid gap-2 text-xs font-semibold text-slate-700">
                    <span>Cardholder Name</span>
                    <input
                        required
                        autoComplete="cc-name"
                        placeholder="As it appears on the card"
                        aria-label="Cardholder name"
                        className={inputClassName}
                    />
                </label>

                <div className="grid gap-5 sm:grid-cols-2">
                    <label className="grid gap-2 text-xs font-semibold text-slate-700">
                        <span>Expiry Date</span>
                        <input
                            required
                            inputMode="numeric"
                            autoComplete="cc-exp"
                            maxLength={7}
                            pattern="(0[1-9]|1[0-2]) / [0-9]{2}"
                            placeholder="MM / YY"
                            aria-label="Expiry date"
                            onInput={(event) => {
                                const value = event.currentTarget.value
                                    .replace(/\D/g, '')
                                    .slice(0, 4);
                                event.currentTarget.value =
                                    value.length > 2
                                        ? `${value.slice(0, 2)} / ${value.slice(2)}`
                                        : value;
                            }}
                            className={inputClassName}
                        />
                    </label>
                    <label className="grid gap-2 text-xs font-semibold text-slate-700">
                        <span>CVV</span>
                        <span className="relative">
                            <input
                                required
                                inputMode="numeric"
                                autoComplete="cc-csc"
                                minLength={3}
                                maxLength={4}
                                pattern="[0-9]{3,4}"
                                placeholder="123"
                                aria-label="CVV"
                                onInput={(event) => {
                                    event.currentTarget.value =
                                        event.currentTarget.value
                                            .replace(/\D/g, '')
                                            .slice(0, 4);
                                }}
                                className={`${inputClassName} pr-11`}
                            />
                            <Info className="pointer-events-none absolute top-1/2 right-4 size-4 -translate-y-1/2 text-slate-400" />
                        </span>
                    </label>
                </div>

                <p className="flex items-start gap-2 rounded-lg bg-slate-50 px-3 py-2.5 text-[11px] leading-5 text-slate-500">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0 text-emerald-600" />
                    For your security, card numbers and CVVs are never stored by
                    ProDeals.lk or included in the order request.
                </p>
            </div>
        </div>
    );
}

function BankTransferDetails() {
    return (
        <div className="grid min-h-80 place-items-center text-center">
            <div className="max-w-sm">
                <span className="mx-auto grid size-16 place-items-center rounded-full bg-orange-50 text-[#ff5a00]">
                    <Landmark className="size-7" />
                </span>
                <h2 className="mt-5 text-lg font-extrabold text-slate-950">
                    Pay by bank transfer
                </h2>
                <p className="mt-2 text-sm leading-6 text-slate-500">
                    Place your order now. The order will remain pending while
                    the transfer is confirmed by our team.
                </p>
            </div>
        </div>
    );
}

function CashOnDeliveryDetails() {
    return (
        <div className="grid min-h-80 place-items-center text-center">
            <div className="max-w-sm">
                <span className="mx-auto grid size-16 place-items-center rounded-full bg-orange-50 text-[#ff5a00]">
                    <Banknote className="size-7" />
                </span>
                <h2 className="mt-5 text-lg font-extrabold text-slate-950">
                    Pay when your order arrives
                </h2>
                <p className="mt-2 text-sm leading-6 text-slate-500">
                    Cash on delivery is checked against the eligible order limit
                    when you place the order.
                </p>
            </div>
        </div>
    );
}

function OrderSummary({
    cart,
    subtotal,
    shippingAddress,
}: {
    cart: CheckoutCart;
    subtotal: number;
    shippingAddress: ShippingAddress;
}) {
    const { marketplace } = usePage().props;

    return (
        <aside className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_6px_24px_rgba(15,23,42,0.07)] lg:sticky lg:top-5">
            <div className="flex items-center justify-between border-b border-slate-100 px-5 py-5">
                <h2 className="text-base font-extrabold text-slate-950">
                    Order Summary
                </h2>
                <Link
                    href={cartShow()}
                    className="text-xs font-bold text-[#ff5a00] hover:underline"
                >
                    Edit Cart
                </Link>
            </div>

            <ul className="divide-y divide-slate-100 px-5">
                {cart.items.map((item) => {
                    const media = item.listing.media[0];
                    const imageUrl = media?.cardUrl ?? media?.url;

                    return (
                        <li key={item.id} className="flex gap-3 py-4">
                            <div className="grid size-16 shrink-0 place-items-center overflow-hidden rounded-lg border border-slate-100 bg-slate-50">
                                {imageUrl ? (
                                    <img
                                        src={imageUrl}
                                        srcSet={
                                            media?.card2xUrl
                                                ? `${imageUrl} 640w, ${media.card2xUrl} 1280w`
                                                : undefined
                                        }
                                        alt={item.listing.title}
                                        className="size-full object-contain p-1.5"
                                    />
                                ) : (
                                    <PackageCheck className="size-6 text-slate-300" />
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <div className="flex items-start justify-between gap-2">
                                    <p className="line-clamp-2 text-xs font-semibold text-slate-900">
                                        {item.listing.title}
                                    </p>
                                    <p className="shrink-0 text-xs font-bold text-slate-900">
                                        {formatPrice(
                                            itemPrice(item) * item.quantity,
                                        )}
                                    </p>
                                </div>
                                <p className="mt-1 text-[10px] text-slate-500">
                                    {item.listing.seller_profile.store_name}
                                </p>
                                {item.variant && (
                                    <p className="mt-1 truncate text-[10px] text-slate-500">
                                        {item.variant.option_values
                                            .map((option) => option.value)
                                            .join(' / ')}
                                    </p>
                                )}
                                <p className="mt-1 text-[10px] text-slate-500">
                                    Qty: {item.quantity}
                                </p>
                            </div>
                        </li>
                    );
                })}
            </ul>

            <div className="border-t border-slate-100 px-5 py-5">
                <dl className="grid gap-3 text-xs">
                    <div className="flex justify-between gap-4">
                        <dt className="text-slate-600">Subtotal</dt>
                        <dd className="font-bold text-slate-900">
                            {formatPrice(subtotal)}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-slate-600">Delivery Fee</dt>
                        <dd className="font-bold text-emerald-600">FREE</dd>
                    </div>
                    <div className="flex justify-between gap-4">
                        <dt className="text-slate-600">VAT</dt>
                        <dd className="font-bold text-slate-900">Included</dd>
                    </div>
                </dl>
                <div className="mt-5 flex items-end justify-between gap-3 border-t border-slate-100 pt-5">
                    <span>
                        <strong className="block text-sm text-slate-950">
                            Total Amount
                        </strong>
                        <span className="text-[10px] text-slate-500">
                            Inclusive of VAT
                        </span>
                    </span>
                    <strong className="text-xl font-black text-[#ff5a00]">
                        {formatPrice(subtotal)}
                    </strong>
                </div>
            </div>

            <div className="border-t border-slate-100 px-5 py-5">
                <div className="flex items-center justify-between gap-3">
                    <h3 className="text-sm font-extrabold text-slate-950">
                        Delivery Address
                    </h3>
                    <Link
                        href={checkoutShow()}
                        className="text-[11px] font-bold text-[#ff5a00] hover:underline"
                    >
                        Change
                    </Link>
                </div>
                <div className="mt-3 flex items-start gap-3 text-xs leading-5 text-slate-600">
                    <MapPin className="mt-0.5 size-4 shrink-0 text-slate-500" />
                    <address className="not-italic">
                        <strong className="block text-slate-900">
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
                        <span className="block">{shippingAddress.phone}</span>
                    </address>
                </div>
            </div>

            <div className="border-t border-slate-100 px-5 py-5">
                <h3 className="text-sm font-extrabold text-slate-950">
                    Need Help?
                </h3>
                <p className="mt-2 text-[11px] text-slate-500">
                    Our customer support team is here for you.
                </p>
                <div className="mt-4 grid gap-3 text-xs text-slate-600">
                    {marketplace.support.phone && (
                        <a
                            href={`tel:${marketplace.support.phone.replace(/\s/g, '')}`}
                            className="flex items-center gap-3 hover:text-[#ff5a00]"
                        >
                            <Headphones className="size-4" />
                            {marketplace.support.phone}
                        </a>
                    )}
                    <a
                        href={`mailto:${marketplace.support.email}`}
                        className="flex items-center gap-3 hover:text-[#ff5a00]"
                    >
                        <Mail className="size-4" />
                        {marketplace.support.email}
                    </a>
                    <span className="flex items-start gap-3">
                        <Clock3 className="mt-0.5 size-4 shrink-0" />
                        <span>
                            {marketplace.support.days}
                            <span className="block text-[10px] text-slate-400">
                                {marketplace.support.hours}
                            </span>
                        </span>
                    </span>
                </div>
            </div>
        </aside>
    );
}

export default function BuyerPayment({
    cart,
    shippingAddress,
    paymentMethod: savedPaymentMethod,
}: {
    cart: CheckoutCart;
    shippingAddress: ShippingAddress;
    paymentMethod: CheckoutPaymentMethod | null;
}) {
    const [paymentMethod, setPaymentMethod] = useState<CheckoutPaymentMethod>(
        savedPaymentMethod ?? 'stripe',
    );
    const subtotal = cart.items.reduce(
        (total, item) => total + itemPrice(item) * item.quantity,
        0,
    );

    return (
        <StorefrontLayout title="Payment">
            <Head title="Payment" />
            <main className="mx-auto max-w-[82rem] px-4 py-5 sm:px-6 sm:py-7">
                <CheckoutProgress current="payment" />

                <header className="mt-7 flex items-center gap-4">
                    <span className="grid size-12 shrink-0 place-items-center rounded-full bg-orange-50 text-[#ff5a00]">
                        <LockKeyhole className="size-5" />
                    </span>
                    <div>
                        <h1 className="text-2xl font-black tracking-tight text-slate-950">
                            Payment
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Complete your payment securely
                        </p>
                    </div>
                </header>

                {cart.items.length === 0 ? (
                    <section className="mx-auto my-10 max-w-xl rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
                        <WalletCards className="mx-auto size-12 text-slate-300" />
                        <h2 className="mt-4 text-xl font-black text-slate-950">
                            Your cart is empty
                        </h2>
                        <p className="mt-2 text-sm text-slate-500">
                            Return to checkout and add an item before paying.
                        </p>
                        <Link
                            href={checkoutShow()}
                            className="mt-6 inline-flex rounded-lg bg-[#ff5a00] px-5 py-3 text-sm font-bold text-white"
                        >
                            Return to checkout
                        </Link>
                    </section>
                ) : (
                    <div className="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_23rem] xl:grid-cols-[minmax(0,1fr)_26rem]">
                        <div className="grid gap-5">
                            <Form {...paymentStore.form()}>
                                {({ errors, processing }) => (
                                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_3px_18px_rgba(15,23,42,0.04)]">
                                        <input
                                            type="hidden"
                                            name="payment_method"
                                            value={paymentMethod}
                                        />
                                        <div className="grid md:grid-cols-[13rem_minmax(0,1fr)]">
                                            <div className="grid content-start gap-2 border-b border-slate-200 p-3 md:border-r md:border-b-0">
                                                {paymentMethods.map(
                                                    (option) => (
                                                        <PaymentMethodButton
                                                            key={option.value}
                                                            option={option}
                                                            selected={
                                                                paymentMethod ===
                                                                option.value
                                                            }
                                                            onSelect={
                                                                setPaymentMethod
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>

                                            <div className="p-5 sm:p-7">
                                                {paymentMethod === 'stripe' && (
                                                    <CardDetails />
                                                )}
                                                {paymentMethod ===
                                                    'bank_transfer' && (
                                                    <BankTransferDetails />
                                                )}
                                                {paymentMethod === 'cod' && (
                                                    <CashOnDeliveryDetails />
                                                )}

                                                {errors.payment_method && (
                                                    <p className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                                                        {errors.payment_method}
                                                    </p>
                                                )}
                                                {errors.cart && (
                                                    <p className="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                                                        {errors.cart}
                                                    </p>
                                                )}

                                                <button
                                                    type="submit"
                                                    disabled={processing}
                                                    className="mt-6 flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-[#ff5a00] px-4 text-sm font-extrabold text-white shadow-[0_8px_20px_rgba(255,90,0,0.24)] transition hover:bg-[#eb5200] disabled:cursor-not-allowed disabled:opacity-60"
                                                >
                                                    <LockKeyhole className="size-4" />
                                                    {processing
                                                        ? 'Processing...'
                                                        : 'Continue to Review'}
                                                </button>
                                                <p className="mt-3 text-center text-[10px] leading-5 text-slate-500">
                                                    By continuing, you agree to
                                                    our{' '}
                                                    <Link
                                                        href={terms()}
                                                        className="font-semibold text-[#ff5a00] hover:underline"
                                                    >
                                                        Terms & Conditions
                                                    </Link>
                                                    .
                                                </p>
                                            </div>
                                        </div>
                                    </section>
                                )}
                            </Form>

                            <section className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-[0_3px_18px_rgba(15,23,42,0.04)] sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex items-start gap-3">
                                    <span className="grid size-11 shrink-0 place-items-center rounded-full bg-emerald-50 text-emerald-600">
                                        <ShieldCheck className="size-6" />
                                    </span>
                                    <span>
                                        <strong className="block text-xs text-slate-950">
                                            Your payment is 100% secure
                                        </strong>
                                        <span className="mt-1 block text-[11px] leading-5 text-slate-500">
                                            Protected by encrypted checkout and
                                            secure payment controls.
                                        </span>
                                    </span>
                                </div>
                                <div className="flex items-center gap-5 text-xs font-extrabold text-emerald-700">
                                    <span className="flex items-center gap-1.5">
                                        <CheckCircle2 className="size-5" /> SSL
                                        SECURED
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <ShieldCheck className="size-5" /> SAFE
                                        CHECKOUT
                                    </span>
                                </div>
                            </section>

                            <div className="grid gap-3 rounded-xl border border-orange-100 bg-orange-50/50 p-5 sm:grid-cols-3">
                                <div className="flex items-center gap-3">
                                    <Truck className="size-5 text-[#ff5a00]" />
                                    <span className="text-xs font-bold text-slate-700">
                                        Fast islandwide delivery
                                    </span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <ShieldCheck className="size-5 text-[#ff5a00]" />
                                    <span className="text-xs font-bold text-slate-700">
                                        Secure payment options
                                    </span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <CircleHelp className="size-5 text-[#ff5a00]" />
                                    <span className="text-xs font-bold text-slate-700">
                                        Local customer support
                                    </span>
                                </div>
                            </div>
                        </div>

                        <OrderSummary
                            cart={cart}
                            subtotal={subtotal}
                            shippingAddress={shippingAddress}
                        />
                    </div>
                )}
            </main>
        </StorefrontLayout>
    );
}
