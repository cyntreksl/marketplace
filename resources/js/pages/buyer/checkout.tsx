import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Clock3,
    Home,
    LockKeyhole,
    NotebookPen,
    PackageCheck,
    ShieldCheck,
    ShoppingCart,
    Store,
    Truck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { CheckoutProgress } from '@/components/checkout-progress';
import { StorefrontLayout } from '@/components/storefront-layout';
import { useStorefrontHeaderHeight } from '@/hooks/use-storefront-header-height';
import { buildCatalogItem, trackEvent } from '@/lib/tracking';
import { show as cartShow } from '@/routes/cart';
import { store as checkoutStore } from '@/routes/checkout';
import type {
    BuyerAddress,
    CheckoutCart,
    CheckoutCartItem,
    ShippingAddress,
} from '@/types';

type CheckoutSectionProps = {
    number: number;
    title: string;
    children: ReactNode;
    icon?: LucideIcon;
};

const inputClassName =
    'h-11 w-full rounded-lg border border-slate-200 bg-white px-3.5 text-base text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-[#ff5a00] focus:ring-3 focus:ring-orange-100';

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
                <span className="grid size-7 shrink-0 place-items-center rounded-full border border-slate-200 bg-slate-50 text-sm font-black text-slate-700">
                    {number}
                </span>
                <h2 className="text-xl font-extrabold text-slate-950">
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
        <label className="grid gap-1.5 text-sm font-semibold text-slate-700">
            <span>
                {label}
                {required && <span className="ml-0.5 text-[#ff5a00]">*</span>}
            </span>
            <input
                required={required}
                name={name}
                type={type}
                inputMode={type === 'tel' ? 'numeric' : undefined}
                autoComplete={type === 'tel' ? 'tel' : undefined}
                maxLength={type === 'tel' ? 10 : undefined}
                pattern={type === 'tel' ? '0[0-9]{9}' : undefined}
                title={
                    type === 'tel'
                        ? 'Enter a 10-digit phone number starting with 0.'
                        : undefined
                }
                onInput={
                    type === 'tel'
                        ? (event) => {
                              event.currentTarget.value =
                                  event.currentTarget.value
                                      .replace(/\D/g, '')
                                      .slice(0, 10);
                          }
                        : undefined
                }
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${name}-error` : undefined}
                defaultValue={defaultValue}
                placeholder={placeholder}
                className={`${inputClassName} scroll-mt-40`}
            />
            {error && (
                <span id={`${name}-error`} className="text-sm text-red-600">
                    {error}
                </span>
            )}
        </label>
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
                    ? 'cursor-not-allowed border-slate-200 bg-slate-50'
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
                <span className="block text-sm font-extrabold text-slate-900">
                    {title}
                </span>
                <span className="block text-sm text-slate-500">
                    {description}
                </span>
                {disabled && (
                    <span className="mt-1.5 inline-flex rounded-full bg-slate-200/70 px-2.5 py-1 text-sm font-semibold text-slate-600">
                        Coming soon
                    </span>
                )}
            </span>
            {!disabled && (
                <span
                    className={`shrink-0 text-sm font-extrabold ${price === 'FREE' ? 'text-emerald-600' : 'text-slate-700'}`}
                >
                    {price}
                </span>
            )}
        </label>
    );
}

export default function BuyerCheckout({
    cart,
    shippingAddress,
    billingAddress = null,
    savedAddresses,
}: {
    cart: CheckoutCart;
    shippingAddress: ShippingAddress | null;
    billingAddress?: ShippingAddress | null;
    savedAddresses: BuyerAddress[];
}) {
    const { auth } = usePage().props;
    const headerHeight = useStorefrontHeaderHeight();
    const [billingMethod, setBillingMethod] = useState(
        billingAddress ? 'different' : 'shipping',
    );
    const [selectedShipping, setSelectedShipping] = useState(shippingAddress);
    const [selectedBilling, setSelectedBilling] = useState(billingAddress);
    const [shippingAddressKey, setShippingAddressKey] = useState('initial');
    const [billingAddressKey, setBillingAddressKey] = useState('initial');

    const itemPrice = (item: CheckoutCartItem): number =>
        Number(
            item.variant?.selling_price ??
                item.listing.sale_price ??
                item.listing.price,
        );
    const subtotal = Number(cart.subtotal);

    useEffect(() => {
        trackEvent('begin_checkout', {
            currency: 'LKR',
            value: Number(cart.total),
            items: cart.items.map((item) =>
                buildCatalogItem(item.listing_id, item.listing_variant_id, {
                    item_name: item.listing.title,
                    price: Number(item.unitPrice),
                    quantity: item.quantity,
                }),
            ),
        });
    }, [cart.items, cart.total]);

    return (
        <StorefrontLayout title="Checkout" showMobileNavigation={false}>
            <Head title="Checkout" />
            <main
                className={`storefront-container pt-5 sm:pt-7 ${cart.items.length > 0 ? 'pb-44 lg:pb-7' : 'pb-7'}`}
            >
                <section className="rounded-xl bg-gradient-to-r from-[#fff8f3] via-[#fffaf6] to-[#fff5ed] px-5 py-5 sm:px-8">
                    <CheckoutProgress current="shipping" />
                    <p className="mt-5 flex items-center justify-center gap-2 text-center text-sm font-medium text-slate-600">
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
                        <p className="mt-2 text-base text-slate-500">
                            Add a product before continuing to checkout.
                        </p>
                        <Link
                            href={cartShow()}
                            className="mt-6 inline-flex items-center gap-2 rounded-lg bg-[#ff5a00] px-5 py-3 text-base font-bold text-white"
                        >
                            Return to cart
                            <ArrowRight className="size-4" />
                        </Link>
                    </section>
                ) : (
                    <Form
                        {...checkoutStore.form()}
                        className="mt-6"
                        onError={(errors) => {
                            document
                                .getElementsByName(Object.keys(errors)[0])[0]
                                ?.focus();
                        }}
                    >
                        {({ errors, processing }) => (
                            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_23rem] xl:grid-cols-[minmax(0,1fr)_26rem]">
                                <div className="grid gap-4">
                                    <CheckoutSection
                                        number={1}
                                        title="Contact Details"
                                    >
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <label className="grid gap-1.5 text-sm font-semibold text-slate-700">
                                                <span>Email Address</span>
                                                <input
                                                    type="email"
                                                    value={auth.user.email}
                                                    readOnly
                                                    className={`${inputClassName} bg-slate-50 text-slate-500`}
                                                />
                                            </label>
                                            <Field
                                                key={`phone-${shippingAddressKey}`}
                                                label="Phone Number"
                                                name="phone"
                                                type="tel"
                                                defaultValue={
                                                    selectedShipping?.phone
                                                }
                                                placeholder="0771234567"
                                                required
                                                error={errors.phone}
                                            />
                                        </div>
                                        <label className="mt-4 flex items-center gap-2 text-sm text-slate-600">
                                            <input
                                                type="checkbox"
                                                defaultChecked
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
                                        {savedAddresses.some(
                                            (address) =>
                                                address.shipping_enabled,
                                        ) && (
                                            <label className="mb-4 grid gap-1.5 text-sm font-semibold text-slate-700">
                                                Use a saved shipping address
                                                <select
                                                    defaultValue=""
                                                    className={inputClassName}
                                                    onChange={(event) => {
                                                        const address =
                                                            savedAddresses.find(
                                                                (candidate) =>
                                                                    candidate.id ===
                                                                    Number(
                                                                        event
                                                                            .currentTarget
                                                                            .value,
                                                                    ),
                                                            );
                                                        setSelectedShipping(
                                                            address ?? null,
                                                        );
                                                        setShippingAddressKey(
                                                            event.currentTarget
                                                                .value ||
                                                                'manual',
                                                        );
                                                    }}
                                                >
                                                    <option value="">
                                                        Enter a different
                                                        address
                                                    </option>
                                                    {savedAddresses
                                                        .filter(
                                                            (address) =>
                                                                address.shipping_enabled,
                                                        )
                                                        .map((address) => (
                                                            <option
                                                                key={address.id}
                                                                value={
                                                                    address.id
                                                                }
                                                            >
                                                                {address.label}{' '}
                                                                —{' '}
                                                                {
                                                                    address.address_line_one
                                                                }
                                                                , {address.city}
                                                            </option>
                                                        ))}
                                                </select>
                                            </label>
                                        )}
                                        <div
                                            key={shippingAddressKey}
                                            className="grid gap-4"
                                        >
                                            <Field
                                                label="Full Name"
                                                name="recipient_name"
                                                defaultValue={
                                                    selectedShipping?.recipient_name ??
                                                    auth.user.name
                                                }
                                                placeholder="Saman Perera"
                                                required
                                                error={errors.recipient_name}
                                            />
                                            <Field
                                                label="Address Line 1"
                                                name="address_line_one"
                                                defaultValue={
                                                    selectedShipping?.address_line_one
                                                }
                                                placeholder="123, Galle Road"
                                                required
                                                error={errors.address_line_one}
                                            />
                                            <Field
                                                label="Address Line 2 (Optional)"
                                                name="address_line_two"
                                                defaultValue={
                                                    selectedShipping?.address_line_two ??
                                                    undefined
                                                }
                                                placeholder="Apartment 5B, Ocean View Residencies"
                                                error={errors.address_line_two}
                                            />
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <Field
                                                    label="City"
                                                    name="city"
                                                    defaultValue={
                                                        selectedShipping?.city
                                                    }
                                                    placeholder="Colombo"
                                                    required
                                                    error={errors.city}
                                                />
                                                <Field
                                                    label="Postal Code"
                                                    name="postal_code"
                                                    defaultValue={
                                                        selectedShipping?.postal_code ??
                                                        undefined
                                                    }
                                                    placeholder="00300"
                                                    error={errors.postal_code}
                                                />
                                            </div>
                                            <div className="rounded-lg bg-orange-50 p-3">
                                                <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                                    <input
                                                        type="checkbox"
                                                        name="save_shipping_address"
                                                        value="1"
                                                        className="size-4 accent-[#ff5a00]"
                                                    />
                                                    Save these details as a new
                                                    address
                                                </label>
                                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                                    <input
                                                        name="shipping_address_label"
                                                        placeholder="Label, e.g. Home"
                                                        aria-label="New shipping address label"
                                                        className={
                                                            inputClassName
                                                        }
                                                    />
                                                    <label className="flex items-center gap-2 text-sm text-slate-600">
                                                        <input
                                                            type="checkbox"
                                                            name="save_shipping_for_billing"
                                                            value="1"
                                                            className="size-4 accent-[#ff5a00]"
                                                        />
                                                        Also allow for billing
                                                    </label>
                                                </div>
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
                                                price={formatPrice(
                                                    Number(cart.shippingTotal),
                                                )}
                                            />
                                            <DeliveryOption
                                                id="express"
                                                icon={Clock3}
                                                title="Islandwide Express Delivery"
                                                description="Faster delivery to your doorstep"
                                                price=""
                                                disabled
                                            />
                                            <DeliveryOption
                                                id="pickup"
                                                icon={Store}
                                                title="Pick Up from ProDeals Store"
                                                description="Collect your order in store"
                                                price=""
                                                disabled
                                            />
                                        </div>
                                        <p className="mt-3 flex items-center gap-2 rounded-lg bg-orange-50 px-3 py-2.5 text-sm text-amber-900">
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
                                        <div
                                            role="radiogroup"
                                            aria-label="Billing address options"
                                            className="flex flex-col gap-4 text-sm sm:flex-row sm:gap-10"
                                        >
                                            <label className="flex items-center gap-2 font-semibold text-slate-700">
                                                <input
                                                    type="radio"
                                                    name="billing_address"
                                                    value="shipping"
                                                    checked={
                                                        billingMethod ===
                                                        'shipping'
                                                    }
                                                    onChange={() =>
                                                        setBillingMethod(
                                                            'shipping',
                                                        )
                                                    }
                                                    className="size-4 accent-[#ff5a00]"
                                                />
                                                Same as shipping address
                                            </label>
                                            <label className="flex cursor-pointer items-center gap-2 font-semibold text-slate-700">
                                                <input
                                                    type="radio"
                                                    name="billing_address"
                                                    value="different"
                                                    checked={
                                                        billingMethod ===
                                                        'different'
                                                    }
                                                    onChange={() =>
                                                        setBillingMethod(
                                                            'different',
                                                        )
                                                    }
                                                    className="size-4 accent-[#ff5a00]"
                                                />
                                                Use a different billing address
                                            </label>
                                        </div>
                                        {billingMethod === 'shipping' && (
                                            <p className="mt-3 text-sm text-slate-500">
                                                Your shipping details will be
                                                used for your bill.
                                            </p>
                                        )}
                                        <fieldset
                                            disabled={
                                                billingMethod === 'shipping'
                                            }
                                            className={
                                                billingMethod === 'shipping'
                                                    ? 'hidden'
                                                    : 'mt-5 grid gap-4 sm:grid-cols-2'
                                            }
                                        >
                                            <legend className="sr-only">
                                                Different billing address
                                            </legend>
                                            {savedAddresses.some(
                                                (address) =>
                                                    address.billing_enabled,
                                            ) && (
                                                <label className="grid gap-1.5 text-sm font-semibold text-slate-700 sm:col-span-2">
                                                    Use a saved billing address
                                                    <select
                                                        defaultValue=""
                                                        className={
                                                            inputClassName
                                                        }
                                                        onChange={(event) => {
                                                            const address =
                                                                savedAddresses.find(
                                                                    (
                                                                        candidate,
                                                                    ) =>
                                                                        candidate.id ===
                                                                        Number(
                                                                            event
                                                                                .currentTarget
                                                                                .value,
                                                                        ),
                                                                );
                                                            setSelectedBilling(
                                                                address ?? null,
                                                            );
                                                            setBillingAddressKey(
                                                                event
                                                                    .currentTarget
                                                                    .value ||
                                                                    'manual',
                                                            );
                                                        }}
                                                    >
                                                        <option value="">
                                                            Enter a different
                                                            address
                                                        </option>
                                                        {savedAddresses
                                                            .filter(
                                                                (address) =>
                                                                    address.billing_enabled,
                                                            )
                                                            .map((address) => (
                                                                <option
                                                                    key={
                                                                        address.id
                                                                    }
                                                                    value={
                                                                        address.id
                                                                    }
                                                                >
                                                                    {
                                                                        address.label
                                                                    }{' '}
                                                                    —{' '}
                                                                    {
                                                                        address.address_line_one
                                                                    }
                                                                    ,{' '}
                                                                    {
                                                                        address.city
                                                                    }
                                                                </option>
                                                            ))}
                                                    </select>
                                                </label>
                                            )}
                                            {(
                                                [
                                                    {
                                                        key: 'recipient_name',
                                                        label: 'Billing Full Name',
                                                        required: true,
                                                        wide: true,
                                                    },
                                                    {
                                                        key: 'address_line_one',
                                                        label: 'Billing Address Line 1',
                                                        required: true,
                                                        wide: true,
                                                    },
                                                    {
                                                        key: 'address_line_two',
                                                        label: 'Billing Address Line 2 (Optional)',
                                                        required: false,
                                                        wide: true,
                                                    },
                                                    {
                                                        key: 'city',
                                                        label: 'Billing City',
                                                        required: true,
                                                        wide: false,
                                                    },
                                                    {
                                                        key: 'postal_code',
                                                        label: 'Billing Postal Code',
                                                        required: false,
                                                        wide: false,
                                                    },
                                                    {
                                                        key: 'phone',
                                                        label: 'Billing Phone Number',
                                                        required: true,
                                                        wide: true,
                                                    },
                                                ] as const
                                            ).map((field) => (
                                                <div
                                                    key={field.key}
                                                    className={
                                                        field.wide
                                                            ? 'sm:col-span-2'
                                                            : undefined
                                                    }
                                                >
                                                    <Field
                                                        key={`${field.key}-${billingAddressKey}`}
                                                        label={field.label}
                                                        name={`billing_${field.key}`}
                                                        type={
                                                            field.key ===
                                                            'phone'
                                                                ? 'tel'
                                                                : 'text'
                                                        }
                                                        defaultValue={
                                                            selectedBilling?.[
                                                                field.key
                                                            ] ?? undefined
                                                        }
                                                        required={
                                                            field.required
                                                        }
                                                        error={
                                                            errors[
                                                                `billing_${field.key}`
                                                            ]
                                                        }
                                                    />
                                                </div>
                                            ))}
                                            <div className="rounded-lg bg-orange-50 p-3 sm:col-span-2">
                                                <label className="flex items-center gap-2 text-sm font-semibold text-slate-700">
                                                    <input
                                                        type="checkbox"
                                                        name="save_billing_address"
                                                        value="1"
                                                        className="size-4 accent-[#ff5a00]"
                                                    />
                                                    Save this as a new billing
                                                    address
                                                </label>
                                                <input
                                                    name="billing_address_label"
                                                    placeholder="Label, e.g. Office billing"
                                                    aria-label="New billing address label"
                                                    className={`${inputClassName} mt-3`}
                                                />
                                            </div>
                                        </fieldset>
                                    </CheckoutSection>
                                </div>

                                <aside
                                    id="order-summary"
                                    className="scroll-mt-36 lg:sticky"
                                    style={{
                                        top: `${headerHeight + 16}px`,
                                    }}
                                >
                                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-[0_6px_24px_rgba(15,23,42,0.07)]">
                                        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-4 sm:px-5">
                                            <h2 className="text-xl font-extrabold text-slate-950">
                                                Order Summary{' '}
                                                <span className="text-sm font-semibold text-slate-500">
                                                    ({cart.items.length}{' '}
                                                    {cart.items.length === 1
                                                        ? 'item'
                                                        : 'items'}
                                                    )
                                                </span>
                                            </h2>
                                            <Link
                                                href={cartShow()}
                                                className="text-sm font-bold text-[#ff5a00] hover:underline"
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
                                                        className="flex gap-3 py-4 lg:py-3"
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
                                                                <p className="line-clamp-2 text-sm font-semibold text-slate-900">
                                                                    {
                                                                        item
                                                                            .listing
                                                                            .title
                                                                    }
                                                                </p>
                                                                <p className="shrink-0 text-sm font-bold text-slate-900">
                                                                    {formatPrice(
                                                                        itemPrice(
                                                                            item,
                                                                        ) *
                                                                            item.quantity,
                                                                    )}
                                                                </p>
                                                            </div>
                                                            <p className="mt-1 text-sm text-slate-500">
                                                                {
                                                                    item.listing
                                                                        .seller_profile
                                                                        .store_name
                                                                }
                                                            </p>
                                                            {item.variant && (
                                                                <p className="mt-1 truncate text-sm text-slate-500">
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
                                                            <p className="mt-1 text-sm text-slate-500">
                                                                Qty:{' '}
                                                                {item.quantity}
                                                            </p>
                                                        </div>
                                                    </li>
                                                );
                                            })}
                                        </ul>

                                        <div className="border-t border-slate-100 p-4 sm:p-5">
                                            <dl className="grid gap-3 text-sm">
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
                                                        {formatPrice(
                                                            Number(
                                                                cart.shippingTotal,
                                                            ),
                                                        )}
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
                                                    className={`${inputClassName} min-w-0 flex-1 text-sm disabled:cursor-not-allowed disabled:bg-slate-50`}
                                                />
                                                <button
                                                    type="button"
                                                    disabled
                                                    className="cursor-not-allowed rounded-lg bg-slate-300 px-4 text-sm font-bold text-white"
                                                >
                                                    Apply
                                                </button>
                                            </div>
                                        </div>

                                        <div className="bg-gradient-to-br from-[#fff8f2] to-[#fff2e7] p-4 sm:p-5">
                                            <div className="flex items-end justify-between gap-3">
                                                <span>
                                                    <strong className="block text-base text-slate-950">
                                                        Total Payable
                                                    </strong>
                                                    <span className="text-sm text-slate-500">
                                                        Inclusive of VAT
                                                    </span>
                                                </span>
                                                <strong className="text-xl font-black text-[#ff5a00]">
                                                    {formatPrice(
                                                        Number(cart.total),
                                                    )}
                                                </strong>
                                            </div>

                                            {Object.values(errors).length >
                                                0 && (
                                                <div className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                                                    {Object.entries(errors).map(
                                                        ([field, error]) => (
                                                            <p key={field}>
                                                                {error}
                                                            </p>
                                                        ),
                                                    )}
                                                </div>
                                            )}

                                            <div className="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-[0_-4px_24px_rgba(15,23,42,0.10)] lg:static lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none">
                                                <div className="mb-2 flex items-center justify-between gap-3 lg:hidden">
                                                    <div>
                                                        <p className="text-sm text-slate-500">
                                                            Total payable
                                                        </p>
                                                        <strong className="text-lg font-black text-slate-950">
                                                            {formatPrice(
                                                                Number(
                                                                    cart.total,
                                                                ),
                                                            )}
                                                        </strong>
                                                    </div>
                                                    <a
                                                        href="#order-summary"
                                                        className="rounded-md px-2 py-3 text-sm font-semibold text-[#ff5a00] underline underline-offset-4"
                                                    >
                                                        View summary
                                                    </a>
                                                </div>
                                                <button
                                                    type="submit"
                                                    disabled={
                                                        processing ||
                                                        !cart.canCheckout
                                                    }
                                                    className="flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-[#ff5a00] px-4 text-base font-extrabold text-white shadow-[0_8px_20px_rgba(255,90,0,0.24)] transition hover:bg-[#eb5200] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#ff5a00] disabled:cursor-not-allowed disabled:opacity-60 lg:mt-4"
                                                >
                                                    <LockKeyhole className="size-4" />
                                                    {processing
                                                        ? 'Saving delivery...'
                                                        : 'Continue to Payment'}
                                                    <ArrowRight className="ml-auto size-4" />
                                                </button>
                                            </div>
                                            <p className="mt-3 flex items-center justify-center gap-1.5 text-sm text-slate-500">
                                                <ShieldCheck className="size-3.5" />
                                                Safe, secure and encrypted
                                                payments
                                            </p>
                                        </div>
                                    </section>
                                </aside>
                            </div>
                        )}
                    </Form>
                )}
            </main>
        </StorefrontLayout>
    );
}
