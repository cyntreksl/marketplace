import { Form, Head, Link } from '@inertiajs/react';
import { store as checkout } from '@/actions/App/Http/Controllers/CheckoutController';
import { PortalLayout } from '@/components/portal-layout';
import { index as ordersIndex } from '@/routes/buyer/orders';

type CartItem = {
    id: number;
    quantity: number;
    listing: {
        title: string;
        price: string;
        seller_profile: { store_name: string };
    };
};

export default function BuyerCart({ cart }: { cart: { items: CartItem[] } }) {
    const subtotal = cart.items.reduce(
        (total, item) => total + Number(item.listing.price) * item.quantity,
        0,
    );

    return (
        <PortalLayout portal="buyer" title="Your cart">
            <Head title="Your cart" />
            <main className="mx-auto max-w-6xl">
                <div className="flex items-end justify-between gap-5">
                    <div>
                        <p className="text-sm font-bold tracking-wider text-amber-700 uppercase">
                            Buyer portal
                        </p>
                        <h1 className="mt-2 text-4xl font-black">Your cart</h1>
                    </div>
                    <Link
                        href={ordersIndex()}
                        className="text-sm font-bold text-amber-700"
                    >
                        Order history
                    </Link>
                </div>

                {cart.items.length === 0 ? (
                    <div className="mt-8 rounded-2xl border border-dashed border-stone-300 p-12 text-center text-stone-500 dark:border-stone-700">
                        Your cart is empty. Explore Buy Now listings to get
                        started.
                    </div>
                ) : (
                    <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem]">
                        <section className="overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
                            <ul className="divide-y divide-stone-200 dark:divide-stone-800">
                                {cart.items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-center justify-between gap-4 p-5"
                                    >
                                        <div>
                                            <p className="font-bold">
                                                {item.listing.title}
                                            </p>
                                            <p className="mt-1 text-sm text-stone-500">
                                                Sold by{' '}
                                                {
                                                    item.listing.seller_profile
                                                        .store_name
                                                }{' '}
                                                · Qty {item.quantity}
                                            </p>
                                        </div>
                                        <p className="font-bold">
                                            LKR{' '}
                                            {(
                                                Number(item.listing.price) *
                                                item.quantity
                                            ).toLocaleString()}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </section>
                        <Form
                            {...checkout.form()}
                            className="grid h-max gap-4 rounded-2xl bg-stone-100 p-5 dark:bg-stone-900"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div>
                                        <p className="text-sm text-stone-500">
                                            Subtotal
                                        </p>
                                        <p className="text-3xl font-black">
                                            LKR {subtotal.toLocaleString()}
                                        </p>
                                    </div>
                                    <label className="grid gap-1 text-sm font-semibold">
                                        Payment method
                                        <select
                                            required
                                            name="payment_method"
                                            className="rounded-lg border bg-transparent p-3"
                                        >
                                            <option value="stripe">
                                                Card via Stripe
                                            </option>
                                            <option value="bank_transfer">
                                                Bank transfer
                                            </option>
                                            <option value="cod">
                                                Cash on delivery
                                            </option>
                                        </select>
                                    </label>
                                    <label className="grid gap-1 text-sm font-semibold">
                                        Recipient name
                                        <input
                                            required
                                            name="recipient_name"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                    <label className="grid gap-1 text-sm font-semibold">
                                        Address
                                        <input
                                            required
                                            name="address_line_one"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                    <label className="grid gap-1 text-sm font-semibold">
                                        Address line 2
                                        <input
                                            name="address_line_two"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                    <div className="grid grid-cols-2 gap-3">
                                        <label className="grid gap-1 text-sm font-semibold">
                                            City
                                            <input
                                                required
                                                name="city"
                                                className="rounded-lg border bg-transparent p-3"
                                            />
                                        </label>
                                        <label className="grid gap-1 text-sm font-semibold">
                                            Postcode
                                            <input
                                                name="postal_code"
                                                className="rounded-lg border bg-transparent p-3"
                                            />
                                        </label>
                                    </div>
                                    <label className="grid gap-1 text-sm font-semibold">
                                        Phone
                                        <input
                                            required
                                            name="phone"
                                            className="rounded-lg border bg-transparent p-3"
                                        />
                                    </label>
                                    {Object.values(errors).map((error) => (
                                        <p
                                            className="text-sm text-red-600"
                                            key={error}
                                        >
                                            {error}
                                        </p>
                                    ))}
                                    <button
                                        disabled={processing}
                                        className="rounded-full bg-amber-400 px-5 py-3 font-bold text-stone-950 disabled:opacity-50"
                                    >
                                        {processing
                                            ? 'Creating order…'
                                            : 'Place secure order'}
                                    </button>
                                    <p className="text-xs text-stone-500">
                                        Each seller receives an independent
                                        fulfilment order. We never share your
                                        contact information beyond delivery
                                        needs.
                                    </p>
                                </>
                            )}
                        </Form>
                    </div>
                )}
            </main>
        </PortalLayout>
    );
}
