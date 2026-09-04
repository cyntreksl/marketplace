import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    CreditCard,
    ShieldCheck,
    ShoppingCart,
} from 'lucide-react';
import { PortalLayout } from '@/components/portal-layout';
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
        media: { cardUrl: string; card2xUrl: string }[];
        seller_profile: { store_name: string };
    };
};

function formatPrice(value: number): string {
    return `LKR ${value.toLocaleString('en-LK')}`;
}

export default function BuyerCheckout({
    cart,
}: {
    cart: { items: CartItem[] };
}) {
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
    const shipping = cart.items.length > 0 ? 250 : 0;
    const tax = Math.round(subtotal * 0.18);
    const total = subtotal + shipping + tax;

    return (
        <PortalLayout portal="buyer" title="Checkout">
            <Head title="Checkout" />
            <main className="mx-auto max-w-7xl">
                <div className="rounded-[2rem] border border-orange-100 bg-gradient-to-r from-orange-50 via-white to-orange-50 p-6 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-bold tracking-[0.18em] text-[#ff5a00] uppercase">
                                Checkout
                            </p>
                            <h1 className="mt-2 text-4xl font-black tracking-tight text-slate-950">
                                Confirm your order
                            </h1>
                        </div>
                        <Link
                            href={cartShow()}
                            className="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:border-[#ff5a00] hover:text-[#ff5a00]"
                        >
                            <ShoppingCart className="size-4" />
                            Back to cart
                        </Link>
                    </div>
                </div>

                <div className="mt-6 rounded-[2rem] border border-orange-100 bg-gradient-to-r from-orange-50 to-amber-50 px-6 py-5 text-slate-700 shadow-sm">
                    <div className="flex flex-wrap items-center gap-3">
                        <CheckCircle2 className="size-5 text-emerald-600" />
                        <p className="font-medium">
                            Cart and payment are protected. Finish this step to
                            save your checkout and place the order.
                        </p>
                    </div>
                </div>

                <Form {...checkoutStore.form()} resetOnSuccess className="mt-8">
                    {({ errors, processing, wasSuccessful }) => (
                        <div className="grid gap-8 lg:grid-cols-[1fr_22rem]">
                            <section className="grid gap-6">
                                <div className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                    <div className="flex items-center gap-3">
                                        <span className="grid size-8 place-items-center rounded-full bg-orange-50 font-black text-[#ff5a00]">
                                            1
                                        </span>
                                        <div>
                                            <h2 className="text-xl font-black text-slate-950">
                                                Contact details
                                            </h2>
                                            <p className="text-sm text-slate-500">
                                                We’ll use this for order
                                                updates.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-6 grid gap-4 sm:grid-cols-2">
                                        <label className="grid gap-2 text-sm font-semibold text-slate-700">
                                            Email address
                                            <input
                                                name="email"
                                                type="email"
                                                defaultValue=""
                                                placeholder="saman.perera@gmail.com"
                                                className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition outline-none focus:border-[#ff5a00] focus:bg-white"
                                            />
                                        </label>
                                        <label className="grid gap-2 text-sm font-semibold text-slate-700">
                                            Phone number
                                            <input
                                                required
                                                name="phone"
                                                placeholder="077 123 4567"
                                                className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition outline-none focus:border-[#ff5a00] focus:bg-white"
                                            />
                                        </label>
                                    </div>
                                </div>

                                <div className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                    <div className="flex items-center gap-3">
                                        <span className="grid size-8 place-items-center rounded-full bg-orange-50 font-black text-[#ff5a00]">
                                            2
                                        </span>
                                        <div>
                                            <h2 className="text-xl font-black text-slate-950">
                                                Shipping address
                                            </h2>
                                            <p className="text-sm text-slate-500">
                                                Save the delivery details for
                                                this order.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-6 grid gap-4">
                                        <label className="grid gap-2 text-sm font-semibold text-slate-700">
                                            Full name
                                            <input
                                                required
                                                name="recipient_name"
                                                placeholder="Saman Perera"
                                                className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition outline-none focus:border-[#ff5a00] focus:bg-white"
                                            />
                                        </label>
                                        <label className="grid gap-2 text-sm font-semibold text-slate-700">
                                            Address line 1
                                            <input
                                                required
                                                name="address_line_one"
                                                placeholder="123, Galle Road"
                                                className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition outline-none focus:border-[#ff5a00] focus:bg-white"
                                            />
                                        </label>
                                        <label className="grid gap-2 text-sm font-semibold text-slate-700">
                                            Address line 2
                                            <input
                                                name="address_line_two"
                                                placeholder="Apartment 5B, Ocean View Residencies"
                                                className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition outline-none focus:border-[#ff5a00] focus:bg-white"
                                            />
                                        </label>
                                        <div className="grid gap-4 sm:grid-cols-3">
                                            <label className="grid gap-2 text-sm font-semibold text-slate-700">
                                                City
                                                <input
                                                    required
                                                    name="city"
                                                    placeholder="Colombo"
                                                    className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition outline-none focus:border-[#ff5a00] focus:bg-white"
                                                />
                                            </label>
                                            <label className="grid gap-2 text-sm font-semibold text-slate-700">
                                                Postal code
                                                <input
                                                    name="postal_code"
                                                    placeholder="00300"
                                                    className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition outline-none focus:border-[#ff5a00] focus:bg-white"
                                                />
                                            </label>
                                            <label className="grid gap-2 text-sm font-semibold text-slate-700">
                                                Save address
                                                <select
                                                    name="save_address"
                                                    className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 transition outline-none focus:border-[#ff5a00] focus:bg-white"
                                                >
                                                    <option value="1">
                                                        Yes
                                                    </option>
                                                    <option value="0">
                                                        No
                                                    </option>
                                                </select>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                    <div className="flex items-center gap-3">
                                        <span className="grid size-8 place-items-center rounded-full bg-orange-50 font-black text-[#ff5a00]">
                                            3
                                        </span>
                                        <div>
                                            <h2 className="text-xl font-black text-slate-950">
                                                Payment method
                                            </h2>
                                            <p className="text-sm text-slate-500">
                                                Pick how you’d like to pay.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-6 grid gap-4 sm:grid-cols-3">
                                        {[
                                            {
                                                value: 'stripe',
                                                title: 'Card',
                                                detail: 'Visa, Mastercard, AMEX',
                                            },
                                            {
                                                value: 'bank_transfer',
                                                title: 'Bank transfer',
                                                detail: 'Manual payment confirmation',
                                            },
                                            {
                                                value: 'cod',
                                                title: 'Cash on delivery',
                                                detail: 'Available for smaller orders',
                                            },
                                        ].map((method, index) => (
                                            <label
                                                key={method.value}
                                                className={`cursor-pointer rounded-2xl border p-4 transition ${index === 0 ? 'border-[#ff5a00] bg-orange-50/60' : 'border-slate-200 bg-slate-50'}`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="payment_method"
                                                    value={method.value}
                                                    defaultChecked={index === 0}
                                                    className="sr-only"
                                                />
                                                <div className="flex items-center gap-3">
                                                    <div className="rounded-2xl bg-white p-2 text-[#ff5a00] shadow-sm">
                                                        <CreditCard className="size-4" />
                                                    </div>
                                                    <div>
                                                        <p className="font-bold text-slate-950">
                                                            {method.title}
                                                        </p>
                                                        <p className="text-xs text-slate-500">
                                                            {method.detail}
                                                        </p>
                                                    </div>
                                                </div>
                                            </label>
                                        ))}
                                    </div>

                                    {Object.values(errors).length > 0 && (
                                        <div className="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                                            {Object.values(errors).map(
                                                (error) => (
                                                    <p key={error}>{error}</p>
                                                ),
                                            )}
                                        </div>
                                    )}

                                    <button
                                        disabled={processing || total <= 0}
                                        className="mt-6 inline-flex items-center justify-center gap-2 rounded-2xl bg-[#ff5a00] px-5 py-4 text-base font-black text-white shadow-lg shadow-orange-200 transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {processing ? (
                                            'Saving checkout...'
                                        ) : (
                                            <>
                                                <ShieldCheck className="size-5" />
                                                Save & place order
                                            </>
                                        )}
                                        <ArrowRight className="size-5" />
                                    </button>

                                    {wasSuccessful && (
                                        <p className="mt-3 text-sm font-semibold text-emerald-600">
                                            Checkout saved successfully.
                                        </p>
                                    )}
                                </div>
                            </section>

                            <aside className="grid h-max gap-4">
                                <div className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <p className="text-sm font-semibold text-slate-500">
                                                Order summary
                                            </p>
                                            <h2 className="text-2xl font-black text-slate-950">
                                                {cart.items.length} items
                                            </h2>
                                        </div>
                                        <div className="rounded-2xl bg-orange-50 p-3 text-[#ff5a00]">
                                            <ShoppingCart className="size-5" />
                                        </div>
                                    </div>

                                    <div className="mt-6 grid gap-4">
                                        {cart.items.map((item) => (
                                            <div
                                                key={item.id}
                                                className="flex gap-3 rounded-2xl border border-slate-100 p-3"
                                            >
                                                <div className="h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-slate-50">
                                                    {item.listing.media[0] && (
                                                        <img
                                                            src={
                                                                item.listing
                                                                    .media[0]
                                                                    .cardUrl
                                                            }
                                                            alt={
                                                                item.listing
                                                                    .title
                                                            }
                                                            className="size-full object-contain p-1"
                                                        />
                                                    )}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm font-bold text-slate-950">
                                                        {item.listing.title}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        Qty {item.quantity}
                                                    </p>
                                                    <p className="mt-1 text-sm font-black text-slate-950">
                                                        {formatPrice(
                                                            itemPrice(item) *
                                                                item.quantity,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        ))}
                                    </div>

                                    <dl className="mt-6 grid gap-3 border-t border-slate-100 pt-5 text-sm">
                                        <div className="flex items-center justify-between">
                                            <dt className="text-slate-500">
                                                Subtotal
                                            </dt>
                                            <dd className="font-bold text-slate-950">
                                                {formatPrice(subtotal)}
                                            </dd>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <dt className="text-slate-500">
                                                Shipping
                                            </dt>
                                            <dd className="font-bold text-slate-950">
                                                {formatPrice(shipping)}
                                            </dd>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <dt className="text-slate-500">
                                                VAT
                                            </dt>
                                            <dd className="font-bold text-slate-950">
                                                {formatPrice(tax)}
                                            </dd>
                                        </div>
                                        <div className="flex items-center justify-between rounded-2xl bg-orange-50 px-4 py-3">
                                            <dt className="font-bold text-slate-950">
                                                Total payable
                                            </dt>
                                            <dd className="text-xl font-black text-[#ff5a00]">
                                                {formatPrice(total)}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <div className="grid gap-3 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                    <div className="flex items-center gap-3">
                                        <div className="rounded-2xl bg-orange-50 p-3 text-[#ff5a00]">
                                            <ShieldCheck className="size-5" />
                                        </div>
                                        <div>
                                            <p className="font-bold text-slate-950">
                                                100% secure checkout
                                            </p>
                                            <p className="text-sm text-slate-500">
                                                SSL encrypted payment and order
                                                data.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    )}
                </Form>
            </main>
        </PortalLayout>
    );
}
