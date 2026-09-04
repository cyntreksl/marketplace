import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CreditCard, ShieldCheck, ShoppingBag } from 'lucide-react';
import { PortalLayout } from '@/components/portal-layout';
import { show as checkoutShow } from '@/routes/checkout';

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

export default function BuyerCart({ cart }: { cart: { items: CartItem[] } }) {
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
        <PortalLayout portal="buyer" title="Your cart">
            <Head title="Your cart" />
            <main className="mx-auto max-w-7xl">
                <div className="rounded-[2rem] border border-orange-100 bg-gradient-to-r from-orange-50 via-white to-orange-50 p-6 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p className="text-sm font-bold tracking-[0.18em] text-[#ff5a00] uppercase">
                                Buyer checkout
                            </p>
                            <h1 className="mt-2 text-4xl font-black tracking-tight text-slate-950">
                                Cart and checkout
                            </h1>
                        </div>
                        <Link
                            href={checkoutShow()}
                            className="inline-flex items-center gap-2 rounded-2xl bg-[#ff5a00] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-orange-200 transition hover:-translate-y-0.5"
                        >
                            <CreditCard className="size-4" />
                            Go to checkout
                            <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </div>

                {cart.items.length === 0 ? (
                    <div className="mt-8 rounded-3xl border border-dashed border-slate-200 bg-white p-12 text-center shadow-sm">
                        <ShoppingBag className="mx-auto size-12 text-slate-300" />
                        <h2 className="mt-4 text-2xl font-black text-slate-950">
                            Your cart is empty
                        </h2>
                        <p className="mt-2 text-sm text-slate-500">
                            Add a buy-now product to begin checkout.
                        </p>
                        <Link
                            href={checkoutShow()}
                            className="mt-6 inline-flex rounded-2xl bg-slate-950 px-5 py-3 text-sm font-bold text-white"
                        >
                            Review checkout page
                        </Link>
                    </div>
                ) : (
                    <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_22rem]">
                        <section className="overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-200 px-6 py-5">
                                <h2 className="text-xl font-black text-slate-950">
                                    Cart items
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Review what you have saved before you pay.
                                </p>
                            </div>
                            <ul className="divide-y divide-slate-100">
                                {cart.items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex flex-col gap-5 p-6 sm:flex-row"
                                    >
                                        <div className="h-24 w-24 shrink-0 overflow-hidden rounded-2xl border border-slate-100 bg-slate-50">
                                            {item.listing.media[0] ? (
                                                <img
                                                    src={
                                                        item.listing.media[0]
                                                            .cardUrl
                                                    }
                                                    srcSet={`${item.listing.media[0].cardUrl} 640w, ${item.listing.media[0].card2xUrl} 1280w`}
                                                    alt={item.listing.title}
                                                    className="size-full object-contain p-2"
                                                />
                                            ) : (
                                                <div className="grid size-full place-items-center text-xs text-slate-400">
                                                    No image
                                                </div>
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-lg font-bold text-slate-950">
                                                        {item.listing.title}
                                                    </p>
                                                    <p className="mt-1 text-sm text-slate-500">
                                                        Sold by{' '}
                                                        {
                                                            item.listing
                                                                .seller_profile
                                                                .store_name
                                                        }
                                                    </p>
                                                </div>
                                                <p className="text-lg font-black text-slate-950">
                                                    {formatPrice(
                                                        itemPrice(item) *
                                                            item.quantity,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                                                <span className="rounded-full bg-slate-100 px-3 py-1 font-semibold">
                                                    Qty {item.quantity}
                                                </span>
                                                {item.variant && (
                                                    <span className="rounded-full bg-orange-50 px-3 py-1 font-semibold text-[#ff5a00]">
                                                        {item.variant.option_values
                                                            .map(
                                                                (value) =>
                                                                    `${value.option.name}: ${value.value}`,
                                                            )
                                                            .join(' · ')}
                                                    </span>
                                                )}
                                                {item.variant?.sku && (
                                                    <span className="rounded-full bg-slate-100 px-3 py-1 font-semibold">
                                                        SKU {item.variant.sku}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
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
                                        <ShieldCheck className="size-5" />
                                    </div>
                                </div>

                                <dl className="mt-6 grid gap-3 text-sm">
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
                                            Calculated at checkout
                                        </dd>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <dt className="text-slate-500">
                                            Secure checkout
                                        </dt>
                                        <dd className="font-bold text-emerald-600">
                                            Enabled
                                        </dd>
                                    </div>
                                </dl>

                                <Link
                                    href={checkoutShow()}
                                    className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-[#ff5a00] px-5 py-4 text-base font-black text-white shadow-lg shadow-orange-200 transition hover:-translate-y-0.5"
                                >
                                    Continue to checkout
                                    <ArrowRight className="size-5" />
                                </Link>

                                <p className="mt-4 text-xs leading-5 text-slate-500">
                                    We will save your cart, shipping address,
                                    and payment choice when you submit the
                                    checkout form.
                                </p>
                            </div>

                            <div className="grid gap-3 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-2xl bg-orange-50 p-3 text-[#ff5a00]">
                                        <ShieldCheck className="size-5" />
                                    </div>
                                    <div>
                                        <p className="font-bold text-slate-950">
                                            Safe and encrypted
                                        </p>
                                        <p className="text-sm text-slate-500">
                                            Every checkout is protected.
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="rounded-2xl bg-orange-50 p-3 text-[#ff5a00]">
                                        <ShoppingBag className="size-5" />
                                    </div>
                                    <div>
                                        <p className="font-bold text-slate-950">
                                            Direct buy-now flow
                                        </p>
                                        <p className="text-sm text-slate-500">
                                            Add items first or jump into
                                            checkout from a listing.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>
                )}
            </main>
        </PortalLayout>
    );
}
