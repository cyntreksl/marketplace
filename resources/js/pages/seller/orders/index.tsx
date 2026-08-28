import { Form, Head, Link } from '@inertiajs/react';
import {
    delivered,
    ready,
} from '@/actions/App/Http/Controllers/SellerOrderController';
import { PortalLayout } from '@/components/portal-layout';
import { index as walletIndex } from '@/routes/seller/wallet';

type SellerOrder = {
    id: number;
    number: string;
    status: string;
    subtotal: string;
    items: { title: string; quantity: number }[];
    shipment: { tracking_number: string; status: string } | null;
};
export default function SellerOrders({
    orders,
}: {
    orders: { data: SellerOrder[] };
}) {
    return (
        <PortalLayout portal="seller" title="Seller orders">
            <Head title="Seller orders" />
            <main className="mx-auto max-w-7xl">
                <div className="flex items-end justify-between">
                    <div>
                        <p className="text-sm font-bold tracking-wider text-amber-700 uppercase">
                            Seller portal
                        </p>
                        <h1 className="mt-2 text-4xl font-black">
                            Fulfilment queue
                        </h1>
                    </div>
                    <Link
                        href={walletIndex()}
                        className="text-sm font-bold text-amber-700"
                    >
                        Wallet →
                    </Link>
                </div>
                <div className="mt-8 grid gap-4">
                    {orders.data.length === 0 ? (
                        <p className="rounded-2xl border border-dashed border-stone-300 p-10 text-center text-stone-500">
                            You have no orders to fulfil.
                        </p>
                    ) : (
                        orders.data.map((order) => (
                            <article
                                key={order.id}
                                className="grid gap-4 rounded-2xl border border-stone-200 bg-white p-5 lg:grid-cols-[1fr_auto] dark:border-stone-800 dark:bg-stone-900"
                            >
                                <div>
                                    <p className="font-bold">{order.number}</p>
                                    <p className="mt-1 text-sm text-stone-500">
                                        {order.items
                                            .map(
                                                (item) =>
                                                    `${item.title} × ${item.quantity}`,
                                            )
                                            .join(', ')}
                                    </p>
                                    <p className="mt-2 text-sm font-semibold capitalize">
                                        {order.status.replaceAll('_', ' ')} ·
                                        LKR{' '}
                                        {Number(
                                            order.subtotal,
                                        ).toLocaleString()}
                                    </p>
                                    {order.shipment && (
                                        <p className="mt-2 text-sm text-stone-500">
                                            {order.shipment.status.replace(
                                                '_',
                                                ' ',
                                            )}{' '}
                                            · {order.shipment.tracking_number}
                                        </p>
                                    )}
                                </div>
                                {order.status === 'paid' && (
                                    <Form
                                        {...ready.form(order.id)}
                                        className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                                    >
                                        {({ processing }) => (
                                            <>
                                                <input
                                                    name="courier_name"
                                                    placeholder="Courier name"
                                                    className="rounded-lg border bg-transparent p-2"
                                                />
                                                <input
                                                    name="tracking_number"
                                                    placeholder="Tracking number (optional)"
                                                    className="rounded-lg border bg-transparent p-2"
                                                />
                                                <button
                                                    disabled={processing}
                                                    className="rounded-full bg-amber-400 px-4 py-2 text-sm font-bold text-stone-950"
                                                >
                                                    Ready to ship
                                                </button>
                                            </>
                                        )}
                                    </Form>
                                )}
                                {order.status === 'ready_to_ship' && (
                                    <Form {...delivered.form(order.id)}>
                                        {({ processing }) => (
                                            <button
                                                disabled={processing}
                                                className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-50"
                                            >
                                                Confirm delivery
                                            </button>
                                        )}
                                    </Form>
                                )}
                            </article>
                        ))
                    )}
                </div>
            </main>
        </PortalLayout>
    );
}
