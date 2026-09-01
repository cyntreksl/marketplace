import { Form, Head, Link } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { store as storeReview } from '@/actions/App/Http/Controllers/BuyerReviewController';
import { PortalLayout } from '@/components/portal-layout';
import { show as cartShow } from '@/routes/cart';

type Order = {
    number: string;
    status: string;
    total: string;
    created_at: string;
    seller_orders: {
        number: string;
        status: string;
        delivered_at: string | null;
        seller_profile: { store_name: string };
        items: {
            id: number;
            title: string;
            quantity: number;
            unit_price: string;
            variant_sku: string | null;
            variant_options: Record<string, string> | null;
            listing: { slug: string } | null;
            review: { rating: number; comment: string | null } | null;
        }[];
    }[];
    payments: { method: string; status: string }[];
};

export default function BuyerOrders({ orders }: { orders: { data: Order[] } }) {
    return (
        <PortalLayout portal="buyer" title="Your orders">
            <Head title="Your orders" />
            <main className="mx-auto max-w-6xl">
                <div className="flex items-end justify-between">
                    <div>
                        <p className="text-sm font-bold tracking-wider text-primary uppercase">
                            Buyer portal
                        </p>
                        <h1 className="mt-2 text-4xl font-black">
                            Your orders
                        </h1>
                    </div>
                    <Link
                        href={cartShow()}
                        className="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-primary-foreground"
                    >
                        View cart
                    </Link>
                </div>
                <div className="mt-8 overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
                    {orders.data.length === 0 ? (
                        <p className="p-12 text-center text-stone-500">
                            No orders yet.
                        </p>
                    ) : (
                        <ul className="divide-y divide-stone-200 dark:divide-stone-800">
                            {orders.data.map((order) => (
                                <li
                                    key={order.number}
                                    className="grid gap-3 p-5 sm:grid-cols-[1fr_auto]"
                                >
                                    <div>
                                        <p className="font-bold">
                                            {order.number}
                                        </p>
                                        <p className="mt-1 text-sm text-stone-500">
                                            {order.seller_orders
                                                .map(
                                                    (sellerOrder) =>
                                                        sellerOrder
                                                            .seller_profile
                                                            .store_name,
                                                )
                                                .join(', ')}
                                        </p>
                                        <p className="mt-1 text-sm text-stone-500">
                                            Payment:{' '}
                                            {order.payments[0]?.method.replace(
                                                '_',
                                                ' ',
                                            )}{' '}
                                            ·{' '}
                                            {order.payments[0]?.status.replace(
                                                '_',
                                                ' ',
                                            )}
                                        </p>
                                        <div className="mt-4 grid gap-3">
                                            {order.seller_orders.flatMap(
                                                (sellerOrder) =>
                                                    sellerOrder.items.map(
                                                        (item) => (
                                                            <div
                                                                key={item.id}
                                                                className="rounded-xl bg-stone-50 p-4 dark:bg-stone-950"
                                                            >
                                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                                    <p className="text-sm font-black">
                                                                        {
                                                                            item.title
                                                                        }{' '}
                                                                        <span className="font-medium text-stone-500">
                                                                            ×{' '}
                                                                            {
                                                                                item.quantity
                                                                            }
                                                                        </span>
                                                                    </p>
                                                                    {item.variant_options && (
                                                                        <p className="mt-1 text-xs text-primary">
                                                                            {Object.entries(
                                                                                item.variant_options,
                                                                            )
                                                                                .map(
                                                                                    ([
                                                                                        name,
                                                                                        value,
                                                                                    ]) =>
                                                                                        `${name}: ${value}`,
                                                                                )
                                                                                .join(
                                                                                    ' · ',
                                                                                )}
                                                                            {item.variant_sku &&
                                                                                ` · ${item.variant_sku}`}
                                                                        </p>
                                                                    )}
                                                                    {item.review && (
                                                                        <span className="flex items-center gap-1 text-xs font-black text-amber-600">
                                                                            <Star className="size-3.5 fill-current" />{' '}
                                                                            {
                                                                                item
                                                                                    .review
                                                                                    .rating
                                                                            }
                                                                            /5
                                                                            verified
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                {sellerOrder.delivered_at &&
                                                                    !item.review && (
                                                                        <Form
                                                                            {...storeReview.form(
                                                                                item.id,
                                                                            )}
                                                                            options={{
                                                                                preserveScroll: true,
                                                                            }}
                                                                            className="mt-3 grid gap-2 sm:grid-cols-[8rem_1fr_auto]"
                                                                        >
                                                                            {({
                                                                                processing,
                                                                                errors,
                                                                            }) => (
                                                                                <>
                                                                                    <label className="grid gap-1 text-xs font-bold">
                                                                                        Rating
                                                                                        <select
                                                                                            name="rating"
                                                                                            required
                                                                                            className="rounded-lg border bg-white px-2 py-2 dark:bg-stone-900"
                                                                                        >
                                                                                            <option value="">
                                                                                                Choose
                                                                                            </option>
                                                                                            {[
                                                                                                5,
                                                                                                4,
                                                                                                3,
                                                                                                2,
                                                                                                1,
                                                                                            ].map(
                                                                                                (
                                                                                                    rating,
                                                                                                ) => (
                                                                                                    <option
                                                                                                        key={
                                                                                                            rating
                                                                                                        }
                                                                                                        value={
                                                                                                            rating
                                                                                                        }
                                                                                                    >
                                                                                                        {
                                                                                                            rating
                                                                                                        }{' '}
                                                                                                        stars
                                                                                                    </option>
                                                                                                ),
                                                                                            )}
                                                                                        </select>
                                                                                    </label>
                                                                                    <label className="grid gap-1 text-xs font-bold">
                                                                                        Comment
                                                                                        (optional)
                                                                                        <input
                                                                                            name="comment"
                                                                                            maxLength={
                                                                                                1000
                                                                                            }
                                                                                            className="rounded-lg border bg-white px-3 py-2 dark:bg-stone-900"
                                                                                            placeholder="What should other buyers know?"
                                                                                        />
                                                                                    </label>
                                                                                    <button
                                                                                        disabled={
                                                                                            processing
                                                                                        }
                                                                                        className="self-end rounded-lg bg-primary px-4 py-2 text-sm font-black text-primary-foreground disabled:opacity-50"
                                                                                    >
                                                                                        {processing
                                                                                            ? 'Saving…'
                                                                                            : 'Review'}
                                                                                    </button>
                                                                                    {Object.values(
                                                                                        errors,
                                                                                    ).map(
                                                                                        (
                                                                                            error,
                                                                                        ) => (
                                                                                            <p
                                                                                                key={
                                                                                                    error
                                                                                                }
                                                                                                className="text-xs text-red-600 sm:col-span-3"
                                                                                            >
                                                                                                {
                                                                                                    error
                                                                                                }
                                                                                            </p>
                                                                                        ),
                                                                                    )}
                                                                                </>
                                                                            )}
                                                                        </Form>
                                                                    )}
                                                            </div>
                                                        ),
                                                    ),
                                            )}
                                        </div>
                                    </div>
                                    <div className="sm:text-right">
                                        <p className="font-black">
                                            LKR{' '}
                                            {Number(
                                                order.total,
                                            ).toLocaleString()}
                                        </p>
                                        <span className="mt-2 inline-flex rounded-full bg-stone-100 px-3 py-1 text-xs font-bold capitalize dark:bg-stone-800">
                                            {order.status.replace('_', ' ')}
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </main>
        </PortalLayout>
    );
}
