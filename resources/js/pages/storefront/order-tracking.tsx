import { useHttp } from '@inertiajs/react';
import { PackageSearch } from 'lucide-react';
import { store as trackOrder } from '@/actions/App/Http/Controllers/OrderTrackingController';
import { StorefrontLayout } from '@/components/storefront-layout';
import type { StorefrontCategory } from '@/types';

type TrackingResult = {
    order: {
        number: string;
        status: string;
        placedAt: string;
        shipments: {
            number: string;
            seller: string;
            status: string;
            courier: string | null;
            trackingNumber: string | null;
            shipmentStatus: string | null;
            history: { status: string; at?: string }[];
        }[];
    };
};

export default function OrderTracking({
    categories,
}: {
    categories: StorefrontCategory[];
}) {
    const request = useHttp<{ number: string; email: string }, TrackingResult>({
        number: '',
        email: '',
    });
    const lookupError = (request.errors as Record<string, string>).order;

    return (
        <StorefrontLayout title="Track your order" categories={categories}>
            <main className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
                <div className="text-center">
                    <PackageSearch className="mx-auto size-10 text-[#ff5a00]" />
                    <h1 className="mt-4 text-3xl font-black">
                        Track your order
                    </h1>
                    <p className="mt-2 text-sm text-slate-500">
                        Enter your order number and the email used at checkout.
                    </p>
                </div>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        void request.post(trackOrder.url());
                    }}
                    className="mx-auto mt-8 grid max-w-xl gap-3 rounded-xl border p-5"
                >
                    <label className="text-xs font-bold">
                        Order number
                        <input
                            value={request.data.number}
                            onChange={(event) =>
                                request.setData('number', event.target.value)
                            }
                            required
                            className="mt-2 w-full rounded-lg border px-4 py-3 text-sm font-normal"
                            placeholder="ORD-..."
                        />
                    </label>
                    <label className="text-xs font-bold">
                        Buyer email
                        <input
                            type="email"
                            value={request.data.email}
                            onChange={(event) =>
                                request.setData('email', event.target.value)
                            }
                            required
                            className="mt-2 w-full rounded-lg border px-4 py-3 text-sm font-normal"
                            placeholder="you@example.com"
                        />
                    </label>
                    {lookupError && (
                        <p role="alert" className="text-xs text-red-600">
                            {lookupError}
                        </p>
                    )}
                    <button
                        disabled={request.processing}
                        className="rounded-lg bg-[#ff5a00] py-3 text-xs font-bold text-white disabled:opacity-50"
                    >
                        {request.processing ? 'Checking…' : 'Track order'}
                    </button>
                </form>
                {request.response?.order && (
                    <section className="mt-7 rounded-xl border p-5">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-xs text-slate-500">
                                    {request.response.order.number}
                                </p>
                                <h2 className="mt-1 text-xl font-black capitalize">
                                    {request.response.order.status.replaceAll(
                                        '_',
                                        ' ',
                                    )}
                                </h2>
                            </div>
                            <time className="text-xs text-slate-400">
                                {new Date(
                                    request.response.order.placedAt,
                                ).toLocaleDateString()}
                            </time>
                        </div>
                        <div className="mt-5 grid gap-3">
                            {request.response.order.shipments.map(
                                (shipment) => (
                                    <article
                                        key={shipment.number}
                                        className="rounded-lg bg-slate-50 p-4"
                                    >
                                        <p className="text-xs font-bold">
                                            {shipment.seller} ·{' '}
                                            {shipment.number}
                                        </p>
                                        <p className="mt-1 text-sm capitalize">
                                            {(
                                                shipment.shipmentStatus ??
                                                shipment.status
                                            ).replaceAll('_', ' ')}
                                        </p>
                                        {shipment.courier && (
                                            <p className="mt-2 text-xs text-slate-500">
                                                {shipment.courier} ·{' '}
                                                {shipment.trackingNumber}
                                            </p>
                                        )}
                                        {shipment.history.length > 0 && (
                                            <ol className="mt-3 grid gap-2 border-l pl-4 text-xs text-slate-500">
                                                {shipment.history.map(
                                                    (event, index) => (
                                                        <li
                                                            key={`${event.status}-${index}`}
                                                            className="capitalize"
                                                        >
                                                            {event.status.replaceAll(
                                                                '_',
                                                                ' ',
                                                            )}{' '}
                                                            {event.at &&
                                                                `· ${new Date(event.at).toLocaleString()}`}
                                                        </li>
                                                    ),
                                                )}
                                            </ol>
                                        )}
                                    </article>
                                ),
                            )}
                        </div>
                    </section>
                )}
            </main>
        </StorefrontLayout>
    );
}
