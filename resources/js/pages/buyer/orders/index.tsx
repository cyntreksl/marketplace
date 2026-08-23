import { Head, Link } from '@inertiajs/react';
import { StorefrontLayout } from '@/components/storefront-layout';
import { show as cartShow } from '@/routes/cart';

type Order = {
    number: string;
    status: string;
    total: string;
    created_at: string;
    seller_orders: {
        number: string;
        status: string;
        seller_profile: { store_name: string };
    }[];
    payments: { method: string; status: string }[];
};

export default function BuyerOrders({ orders }: { orders: { data: Order[] } }) {
    return (
        <StorefrontLayout title="Your orders">
            <Head title="Your orders" />
            <main className="mx-auto max-w-5xl px-4 py-10 sm:px-6">
                <div className="flex items-end justify-between">
                    <div>
                        <p className="text-sm font-bold tracking-wider text-amber-700 uppercase">
                            Buyer portal
                        </p>
                        <h1 className="mt-2 text-4xl font-black">
                            Your orders
                        </h1>
                    </div>
                    <Link
                        href={cartShow()}
                        className="rounded-full bg-stone-950 px-4 py-2 text-sm font-bold text-white dark:bg-stone-50 dark:text-stone-950"
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
        </StorefrontLayout>
    );
}
