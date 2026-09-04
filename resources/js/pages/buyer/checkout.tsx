import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    Banknote,
    Check,
    CheckCircle2,
    CircleHelp,
    Clock3,
    CreditCard,
    Headphones,
    Home,
    LockKeyhole,
    MapPin,
    NotebookPen,
    PackageCheck,
    ShieldCheck,
    ShoppingCart,
    Store,
    Truck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { StorefrontLayout } from '@/components/storefront-layout';
import { show as cartShow } from '@/routes/cart';
import { store as checkoutStore } from '@/routes/checkout';

type CartItem = {
    id: number;
    quantity: number;
    variant: {
        sku: string;
        selling_price: string | null;
        option_values: { value: string; option: { name: string } }[];
    } | null;
    listing: {
        title: string;
        price: string;
        sale_price: string | null;
        media: {
            cardUrl?: string;
            card2xUrl?: string;
            url?: string;
        }[];
        seller_profile: { store_name: string };
    };
};

type CheckoutSectionProps = {
    number: number;
    title: string;
    children: ReactNode;
    icon?: LucideIcon;
};

const inputClassName =
    'h-11 w-full rounded-lg border border-slate-200 bg-white px-3.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#ff5a00] focus:ring-3 focus:ring-orange-100';

function formatPrice(value: number): string {
    return `LKR ${value.toLocaleString('en-LK')}`;
}

function CheckoutSection({
    number,
    title,
    children,
    icon: Icon,
}: CheckoutSectionProps) {
    return (
        <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-[0_3px_18px_rgba(15,23,42,0.04)] sm:p-5">
            <div className="flex items-center gap-2.5">
                <span className="grid size-7 shrink-0 place-items-center rounded-full border border-slate-200 bg-slate-50 text-xs font-black text-slate-700">
                    {number}
                </span>
                <h2 className="text-base font-extrabold text-slate-950">
                    {title}
                </h2>
                {Icon && <Icon className="ml-auto size-4 text-slate-400" />}
            </div>
            <div className="mt-4">{children}</div>
        </section>
    );
}

function Field({
    label,
    name,
    placeholder,
    defaultValue,
    required = false,
    type = 'text',
    error,
}: {
    label: string;
    name: string;
    placeholder?: string;
    defaultValue?: string;
    required?: boolean;
    type?: 'text' | 'tel';
    error?: string;
}) {
    return (
        <label className="grid gap-1.5 text-xs font-semibold text-slate-700">
            <span>
                {label}
                {required && <span className="ml-0.5 text-[#ff5a00]">*</span>}
            </span>
            <input
                required={required}
                name={name}
                type={type}
                defaultValue={defaultValue}
                placeholder={placeholder}
                className={inputClassName}
            />
            {error && <span className="text-[11px] text-red-600">{error}</span>}
        </label>
    );
}

function ProgressStep({
    icon: Icon,
    label,
    state,
}: {
    icon: LucideIcon;
    label: string;
    state: 'complete' | 'active' | 'upcoming';
}) {
    const isActive = state === 'active';
    const isComplete = state === 'complete';

    return (
        <div
            className="flex min-w-max items-center gap-2.5"
            aria-current={isActive ? 'step' : undefined}
        >
            <span
                className={`grid size-10 place-items-center rounded-full border-2 ${
                    isActive
                        ? 'border-[#ff5a00] bg-white text-[#ff5a00]'
                        : isComplete
                          ? 'border-slate-300 bg-white text-slate-600'
                          : 'border-slate-200 bg-white text-slate-400'
                }`}
            >
                {isComplete ? (
                    <Check className="size-4" strokeWidth={3} />
                ) : (
                    <Icon className="size-4" />
                )}
            </span>
            <span
                className={`text-sm font-bold ${isActive ? 'text-[#ff5a00]' : 'text-slate-700'}`}
            >
                {label}
            </span>
        </div>
    );
}

function DeliveryOption({
    id,
    icon: Icon,
    title,
    description,
    price,
    disabled = false,
}: {
    id: string;
    icon: LucideIcon;
    title: string;
    description: string;
    price: string;
    disabled?: boolean;
}) {
    return (
        <label
            className={`relative flex items-center gap-3 rounded-lg border px-3.5 py-3 ${
                disabled
                    ? 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-55'
                    : 'cursor-pointer border-[#ff5a00] bg-orange-50/45'
            }`}
        >
            <input
                type="radio"
                name="delivery_method"
                value={id}
                defaultChecked={!disabled}
                disabled={disabled}
                className="size-4 accent-[#ff5a00]"
            />
            <Icon className="size-6 shrink-0 text-slate-700" />
            <span className="min-w-0 flex-1">
                <span className="block text-xs font-extrabold text-slate-900">
                    {title}
                </span>
                <span className="block text-[11px] text-slate-500">
                    {description}
                </span>
            </span>
            <span
                className={`shrink-0 text-xs font-extrabold ${price === 'FREE' ? 'text-emerald-600' : 'text-slate-700'}`}
            >
                {price}
            </span>
        </label>
    );
}

function PaymentOption({
    value,
    title,
    description,
    icon: Icon,
    defaultChecked = false,
}: {
    value: string;
    title: string;
    description: string;
    icon: LucideIcon;
    defaultChecked?: boolean;
}) {
    return (
        <label className="relative cursor-pointer">
            <input
                type="radio"
                name="payment_method"
                value={value}
                defaultChecked={defaultChecked}
                className="peer sr-only"
            />
            <span className="flex h-full items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 transition peer-checked:border-[#ff5a00] peer-checked:bg-orange-50/50 peer-focus-visible:ring-3 peer-focus-visible:ring-orange-100">
                <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-slate-50 text-slate-600 peer-checked:text-[#ff5a00]">
                    <Icon className="size-4" />
                </span>
                <span>
                    <span className="block text-xs font-extrabold text-slate-900">
                        {title}
                    </span>
                    <span className="block text-[10px] leading-4 text-slate-500">
                        {description}
                    </span>
                </span>
            </span>
        </label>
    );
}

function TrustItem({
    icon: Icon,
    title,
    description,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <span className="grid size-10 shrink-0 place-items-center rounded-full bg-orange-50 text-[#ff5a00]">
                <Icon className="size-5" />
            </span>
            <span>
                <strong className="block text-xs text-slate-900">
                    {title}
                </strong>
                <span className="mt-0.5 block text-[11px] leading-4 text-slate-500">
                    {description}
                </span>
            </span>
        </div>
    );
}

export default function BuyerCheckout({
    cart,
}: {
    cart: { items: CartItem[] };
}) {
    const { auth, marketplace } = usePage().props;
    const itemPrice = (item: CartItem): number =>
        Number(
            item.variant?.selling_price ??
                item.listing.sale_price ??
                item.listing.price,
        );
    const subtotal = cart.items.reduce(
        (total, item) => total + itemPrice(item) * item.quantity,
        0,
    );

    return (
        <StorefrontLayout title="Checkout">
            <Head title="Checkout" />
            <main className="mx-auto max-w-[82rem] px-4 py-5 sm:px-6 sm:py-7">
                <section className="[scrollbar-width:none] overflow-x-auto rounded-xl bg-gradient-to-r from-[#fff8f3] via-[#fffaf6] to-[#fff5ed] px-5 py-5 sm:px-8">
                    <div className="mx-auto flex max-w-4xl min-w-[620px] items-center justify-between gap-4">
                        <ProgressStep
                            icon={ShoppingCart}
                            label="Cart"
                            state="complete"
                        />
                        <span className="h-px min-w-8 flex-1 border-t border-dashed border-orange-300" />
                        <ProgressStep
                            icon={MapPin}
                            label="Checkout"
                            state="active"
                        />
                        <span className="h-px min-w-8 flex-1 border-t border-dashed border-slate-300" />
                        <ProgressStep
                            icon={CreditCard}
                            label="Payment"
                            state="upcoming"
                        />
                        <span className="h-px min-w-8 flex-1 border-t border-dashed border-slate-300" />
                        <ProgressStep
                            icon={CheckCircle2}
                            label="Confirmation"
                            state="upcoming"
                        />
                    </div>
                    <p className="mt-5 flex items-center justify-center gap-2 text-center text-xs font-medium text-slate-600">
                        <LockKeyhole className="size-3.5" />
                        You're in safe hands. All transactions are secure and
                        encrypted.
                    </p>
                </section>

                {cart.items.length === 0 ? (
                    <section className="mx-auto my-10 max-w-xl rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center shadow-sm">
                        <span className="mx-auto grid size-14 place-items-center rounded-full bg-orange-50 text-[#ff5a00]">
                            <ShoppingCart className="size-6" />
                        </span>
                        <h1 className="mt-4 text-xl font-black text-slate-950">
                            Your cart is empty
                        </h1>
                        <p className="mt-2 text-sm text-slate-500">
                            Add a product before continuing to checkout.
                        </p>
                        <Link
                            href={cartShow()}
                            className="mt-6 inline-flex items-center gap-2 rounded-lg bg-[#ff5a00] px-5 py-3 text-sm font-bold text-white"
                        >
                            Return to cart
                            <ArrowRight className="size-4" />
                        </Link>
                    </section>
                ) : (
                    <Form {...checkoutStore.form()} className="mt-6">
                        {({ errors, processing }) => (
                            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_23rem] xl:grid-cols-[minmax(0,1fr)_26rem]">
                                <div className="grid gap-4">
                                    <CheckoutSection
                                        number={1}
                                        title="Contact Details"
                                    >
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <label className="grid gap-1.5 text-xs font-semibold text-slate-700">
                                                <span>Email Address</span>
                                                <input
                                                    type="email"
                                                    value={auth.user.email}
                                                    readOnly
                                                    className={`${inputClassName} bg-slate-50 text-slate-500`}
                                                />
                                            </label>
                                            <Field
                                                label="Phone Number"
                                                name="phone"
                                                type="tel"
                                                placeholder="077 123 4567"
                                                required
                                                error={errors.phone}
                                            />
                                        </div>
                                        <label className="mt-4 flex items-center gap-2 text-[11px] text-slate-600">
                                            <input
                                                type="checkbox"
                                                className="size-4 rounded accent-[#ff5a00]"
                                            />
                                            Keep me updated on deals, offers and
                                            new arrivals via email.
                                        </label>
                                    </CheckoutSection>

                                    <CheckoutSection
                                        number={2}
                                        title="Shipping Address"
                                        icon={Home}
                                    >
                                        <div className="grid gap-4">
                                            <Field
                                                label="Full Name"
                                                name="recipient_name"
                                                defaultValue={auth.user.name}
                                                placeholder="Saman Perera"
                                                required
                                                error={errors.recipient_name}
                                            />
                                            <Field
                                                label="Address Line 1"
                                                name="address_line_one"
                                                placeholder="123, Galle Road"
                                                required
                                                error={errors.address_line_one}
                                            />
                                            <Field
                                                label="Address Line 2 (Optional)"
                                                name="address_line_two"
                                                placeholder="Apartment 5B, Ocean View Residencies"
                                                error={errors.address_line_two}
                                            />
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <Field
                                                    label="City"
                                                    name="city"
                                                    placeholder="Colombo"
                                                    required
                                                    error={errors.city}
                                                />
                                                <Field
                                                    label="Postal Code"
                                                    name="postal_code"
                                                    placeholder="00300"
                                                    error={errors.postal_code}
                                                />
                                            </div>
                                        </div>
                                    </CheckoutSection>

                                    <CheckoutSection
                                        number={3}
                                        title="Delivery Options"
                                    >
                                        <div className="grid gap-2.5">
                                            <DeliveryOption
                                                id="standard"
                                                icon={Truck}
                                                title="Islandwide Standard Delivery"
                                                description="Delivery timing is confirmed after order placement"
                                                price="FREE"
                                            />
                                            <DeliveryOption
                                                id="express"
                                                icon={Clock3}
                                                title="Islandwide Express Delivery"
                                                description="Express delivery is coming soon"
                                                price="UNAVAILABLE"
                                                disabled
                                            />
                                            <DeliveryOption
                                                id="pickup"
                                                icon={Store}
                                                title="Pick Up from ProDeals Store"
                                                description="Store pickup is coming soon"
                                                price="UNAVAILABLE"
                                                disabled
                                            />
                                        </div>
                                        <p className="mt-3 flex items-center gap-2 rounded-lg bg-orange-50 px-3 py-2.5 text-[11px] text-amber-900">
                                            <ShieldCheck className="size-4 shrink-0" />
                                            We deliver islandwide with care and
                                            keep you updated on order progress.
                                        </p>
                                    </CheckoutSection>

                                    <CheckoutSection
                                        number={4}
                                        title="Order Notes (Optional)"
                                        icon={NotebookPen}
                                    >
                                        <textarea
                                            disabled
                                            rows={3}
                                            placeholder="Order notes will be available soon."
                                            className={`${inputClassName} h-auto resize-none py-3 disabled:cursor-not-allowed disabled:bg-slate-50`}
                                        />
                                    </CheckoutSection>

                                    <CheckoutSection
                                        number={5}
                                        title="Billing Address"
                                    >
                                        <div className="flex flex-col gap-4 text-xs sm:flex-row sm:gap-10">
                                            <label className="flex items-center gap-2 font-semibold text-slate-700">
                                                <input
                                                    type="radio"
                                                    name="billing_address"
                                                    defaultChecked
                                                    className="size-4 accent-[#ff5a00]"
                                                />
                                                Same as shipping address
                                            </label>
                                            <label className="flex cursor-not-allowed items-center gap-2 text-slate-400">
                                                <input
                                                    type="radio"
                                                    name="billing_address"
                                                    disabled
                                                    className="size-4"
                                                />
                                                Use a different billing address
                                            </label>
                                        </div>
                                    </CheckoutSection>

                                    <CheckoutSection
                                        number={6}
                                        title="Payment Method"
                                        icon={CreditCard}
                                    >
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            <PaymentOption
                                                value="stripe"
                                                title="Card Payment"
                                                description="Visa, Mastercard and AMEX"
                                                icon={CreditCard}
                                                defaultChecked
                                            />
                                            <PaymentOption
                                                value="bank_transfer"
                                                title="Bank Transfer"
                                                description="Manual payment confirmation"
                                                icon={Banknote}
                                            />
                                            <PaymentOption
                                                value="cod"
                                                title="Cash on Delivery"
                                                description="Available for eligible totals"
                                                icon={Truck}
                                            />
                                        </div>
                                        {errors.payment_method && (
                                            <p className="mt-3 text-xs text-red-600">
                                                {errors.payment_method}
                                            </p>
                                        )}
                                    </CheckoutSection>
                                </div>

                                <aside className="grid gap-4 lg:sticky lg:top-5">
                                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_6px_24px_rgba(15,23,42,0.07)]">
                                        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-4 sm:px-5">
                                            <h2 className="text-base font-extrabold text-slate-950">
                                                Order Summary{' '}
                                                <span className="text-xs font-semibold text-slate-500">
                                                    ({cart.items.length}{' '}
                                                    {cart.items.length === 1
                                                        ? 'item'
                                                        : 'items'}
                                                    )
                                                </span>
                                            </h2>
                                            <Link
                                                href={cartShow()}
                                                className="text-xs font-bold text-[#ff5a00] hover:underline"
                                            >
                                                Edit Cart
                                            </Link>
                                        </div>

                                        <ul className="divide-y divide-slate-100 px-4 sm:px-5">
                                            {cart.items.map((item) => {
                                                const media =
                                                    item.listing.media[0];
                                                const imageUrl =
                                                    media?.cardUrl ??
                                                    media?.url;

                                                return (
                                                    <li
                                                        key={item.id}
                                                        className="flex gap-3 py-4"
                                                    >
                                                        <div className="grid size-16 shrink-0 place-items-center overflow-hidden rounded-lg border border-slate-100 bg-slate-50">
                                                            {imageUrl ? (
                                                                <img
                                                                    src={
                                                                        imageUrl
                                                                    }
                                                                    alt={
                                                                        item
                                                                            .listing
                                                                            .title
                                                                    }
                                                                    className="size-full object-contain p-1.5"
                                                                />
                                                            ) : (
                                                                <PackageCheck className="size-6 text-slate-300" />
                                                            )}
                                                        </div>
                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex items-start justify-between gap-2">
                                                                <p className="line-clamp-2 text-xs font-semibold text-slate-900">
                                                                    {
                                                                        item
                                                                            .listing
                                                                            .title
                                                                    }
                                                                </p>
                                                                <p className="shrink-0 text-xs font-bold text-slate-900">
                                                                    {formatPrice(
                                                                        itemPrice(
                                                                            item,
                                                                        ) *
                                                                            item.quantity,
                                                                    )}
                                                                </p>
                                                            </div>
                                                            <p className="mt-1 text-[10px] text-slate-500">
                                                                {
                                                                    item.listing
                                                                        .seller_profile
                                                                        .store_name
                                                                }
                                                            </p>
                                                            {item.variant && (
                                                                <p className="mt-1 truncate text-[10px] text-slate-500">
                                                                    {item.variant.option_values
                                                                        .map(
                                                                            (
                                                                                option,
                                                                            ) =>
                                                                                option.value,
                                                                        )
                                                                        .join(
                                                                            ' / ',
                                                                        )}
                                                                </p>
                                                            )}
                                                            <p className="mt-1 text-[10px] text-slate-500">
                                                                Qty:{' '}
                                                                {item.quantity}
                                                            </p>
                                                        </div>
                                                    </li>
                                                );
                                            })}
                                        </ul>

                                        <div className="border-t border-slate-100 p-4 sm:p-5">
                                            <dl className="grid gap-3 text-xs">
                                                <div className="flex justify-between gap-4">
                                                    <dt className="text-slate-600">
                                                        Subtotal
                                                    </dt>
                                                    <dd className="font-bold text-slate-900">
                                                        {formatPrice(subtotal)}
                                                    </dd>
                                                </div>
                                                <div className="flex justify-between gap-4">
                                                    <dt className="text-slate-600">
                                                        Shipping
                                                    </dt>
                                                    <dd className="font-bold text-emerald-600">
                                                        FREE
                                                    </dd>
                                                </div>
                                                <div className="flex justify-between gap-4">
                                                    <dt className="text-slate-600">
                                                        VAT
                                                    </dt>
                                                    <dd className="font-bold text-slate-900">
                                                        Included
                                                    </dd>
                                                </div>
                                            </dl>

                                            <div className="mt-4 flex gap-2">
                                                <input
                                                    disabled
                                                    aria-label="Coupon code"
                                                    placeholder="Coupon codes coming soon"
                                                    className={`${inputClassName} min-w-0 flex-1 text-xs disabled:cursor-not-allowed disabled:bg-slate-50`}
                                                />
                                                <button
                                                    type="button"
                                                    disabled
                                                    className="cursor-not-allowed rounded-lg bg-slate-300 px-4 text-xs font-bold text-white"
                                                >
                                                    Apply
                                                </button>
                                            </div>
                                        </div>

                                        <div className="bg-gradient-to-br from-[#fff8f2] to-[#fff2e7] p-4 sm:p-5">
                                            <div className="flex items-end justify-between gap-3">
                                                <span>
                                                    <strong className="block text-sm text-slate-950">
                                                        Total Payable
                                                    </strong>
                                                    <span className="text-[10px] text-slate-500">
                                                        Inclusive of VAT
                                                    </span>
                                                </span>
                                                <strong className="text-xl font-black text-[#ff5a00]">
                                                    {formatPrice(subtotal)}
                                                </strong>
                                            </div>

                                            {Object.values(errors).length >
                                                0 && (
                                                <div className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-[11px] text-red-700">
                                                    {Object.entries(errors).map(
                                                        ([field, error]) => (
                                                            <p key={field}>
                                                                {error}
                                                            </p>
                                                        ),
                                                    )}
                                                </div>
                                            )}

                                            <button
                                                type="submit"
                                                disabled={processing}
                                                className="mt-4 flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-[#ff5a00] px-4 text-sm font-extrabold text-white shadow-[0_8px_20px_rgba(255,90,0,0.24)] transition hover:bg-[#eb5200] disabled:cursor-not-allowed disabled:opacity-60"
                                            >
                                                <LockKeyhole className="size-4" />
                                                {processing
                                                    ? 'Placing order...'
                                                    : 'Continue to Payment'}
                                                <ArrowRight className="ml-auto size-4" />
                                            </button>
                                            <p className="mt-3 flex items-center justify-center gap-1.5 text-[10px] text-slate-500">
                                                <ShieldCheck className="size-3.5" />
                                                Safe, secure and encrypted
                                                payments
                                            </p>
                                        </div>
                                    </section>

                                    <section className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-[0_3px_18px_rgba(15,23,42,0.04)]">
                                        <TrustItem
                                            icon={LockKeyhole}
                                            title="100% Secure Checkout"
                                            description="Your checkout data is protected with SSL encryption."
                                        />
                                        <TrustItem
                                            icon={BadgeCheck}
                                            title="Genuine Products"
                                            description="Shop approved products from verified sellers."
                                        />
                                        <TrustItem
                                            icon={Truck}
                                            title="Islandwide Delivery"
                                            description="Reliable delivery service across Sri Lanka."
                                        />
                                        <TrustItem
                                            icon={Headphones}
                                            title="Need Help?"
                                            description={
                                                marketplace.support.phone
                                                    ? `Call us on ${marketplace.support.phone}.`
                                                    : 'Contact our support team for help.'
                                            }
                                        />
                                    </section>

                                    <p className="flex items-center justify-center gap-1.5 text-[10px] text-slate-400">
                                        <CircleHelp className="size-3.5" />
                                        Questions? Our support team is ready to
                                        help.
                                    </p>
                                </aside>
                            </div>
                        )}
                    </Form>
                )}
            </main>
        </StorefrontLayout>
    );
}
