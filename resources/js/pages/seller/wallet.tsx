import { Form, Head, Link } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/SellerWalletController';
import { PortalLayout } from '@/components/portal-layout';
import { index as ordersIndex } from '@/routes/seller/orders';

type Entry = {
    id: number;
    type: string;
    status: string;
    amount: string;
    reason: string;
    available_at: string | null;
};
type Payout = {
    id: number;
    amount: string;
    status: string;
    created_at: string;
};
export default function SellerWallet({
    availableBalance,
    entries,
    payouts,
}: {
    availableBalance: string;
    entries: { data: Entry[] };
    payouts: Payout[];
}) {
    return (
        <PortalLayout portal="seller" title="Seller wallet">
            <Head title="Seller wallet" />
            <main className="mx-auto max-w-7xl">
                <Link
                    href={ordersIndex()}
                    className="text-sm font-bold text-amber-700"
                >
                    ← Fulfilment queue
                </Link>
                <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_22rem]">
                    <section>
                        <p className="text-sm font-bold tracking-wider text-amber-700 uppercase">
                            Seller wallet
                        </p>
                        <h1 className="mt-2 text-4xl font-black">
                            LKR {Number(availableBalance).toLocaleString()}
                        </h1>
                        <p className="mt-2 text-stone-500">
                            Available balance. Holds and pending settlements are
                            excluded.
                        </p>
                        <div className="mt-8 overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
                            {entries.data.length === 0 ? (
                                <p className="p-10 text-center text-stone-500">
                                    No ledger entries yet.
                                </p>
                            ) : (
                                <ul className="divide-y divide-stone-200 dark:divide-stone-800">
                                    {entries.data.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="flex items-center justify-between gap-3 p-4"
                                        >
                                            <div>
                                                <p className="font-semibold">
                                                    {entry.reason}
                                                </p>
                                                <p className="text-sm text-stone-500 capitalize">
                                                    {entry.type} ·{' '}
                                                    {entry.status}
                                                </p>
                                            </div>
                                            <p className="font-bold">
                                                LKR{' '}
                                                {Number(
                                                    entry.amount,
                                                ).toLocaleString()}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </section>
                    <aside className="h-max rounded-2xl bg-stone-100 p-5 dark:bg-stone-900">
                        <h2 className="text-xl font-black">Request a payout</h2>
                        <p className="mt-2 text-sm text-stone-500">
                            Minimum LKR 5,000. Only available ledger funds are
                            eligible.
                        </p>
                        <Form {...store.form()} className="mt-5 grid gap-3">
                            {({ errors, processing }) => (
                                <>
                                    <label className="grid gap-1 text-sm font-semibold">
                                        Amount
                                        <input
                                            required
                                            name="amount"
                                            type="number"
                                            min="5000"
                                            step="0.01"
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
                                        className="rounded-full bg-amber-400 px-4 py-3 font-bold text-stone-950"
                                    >
                                        Request payout
                                    </button>
                                </>
                            )}
                        </Form>
                        <h3 className="mt-8 font-bold">Recent requests</h3>
                        <ul className="mt-3 grid gap-2 text-sm">
                            {payouts.map((payout) => (
                                <li
                                    key={payout.id}
                                    className="flex justify-between"
                                >
                                    <span className="capitalize">
                                        {payout.status}
                                    </span>
                                    <span>
                                        LKR{' '}
                                        {Number(payout.amount).toLocaleString()}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </aside>
                </div>
            </main>
        </PortalLayout>
    );
}
