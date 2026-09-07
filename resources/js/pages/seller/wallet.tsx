import { Form, Head, Link } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/SellerWalletController';
import { SellerPageHeader } from '@/components/seller-page-header';
import { SellerPagination } from '@/components/seller-pagination';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { index as ordersIndex } from '@/routes/seller/orders';
import type { SellerPaginator } from '@/types';

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
    entries: SellerPaginator<Entry>;
    payouts: SellerPaginator<Payout>;
}) {
    return (
        <SellerPortalLayout title="Wallet">
            <Head title="Seller wallet" />
            <main>
                <Link
                    href={ordersIndex()}
                    className="text-sm font-bold text-primary"
                >
                    ← Fulfilment queue
                </Link>
                <div className="mt-4 grid gap-6 lg:grid-cols-[1fr_22rem]">
                    <section>
                        <SellerPageHeader
                            eyebrow="Seller wallet"
                            title={`LKR ${Number(availableBalance).toLocaleString()}`}
                            description="Available balance. Holds and pending settlements are excluded."
                        />
                        <div className="mt-8 overflow-hidden rounded-2xl border border-stone-200 bg-white dark:border-stone-800 dark:bg-stone-900">
                            {entries.data.length === 0 ? (
                                <p className="p-10 text-center text-stone-500">
                                    No ledger entries yet.
                                </p>
                            ) : (
                                <>
                                    <table className="hidden w-full table-fixed text-left text-sm md:table">
                                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase dark:bg-slate-950">
                                            <tr>
                                                <th className="w-1/2 px-5 py-3">
                                                    Transaction
                                                </th>
                                                <th className="px-4 py-3">
                                                    Type
                                                </th>
                                                <th className="px-4 py-3">
                                                    Status
                                                </th>
                                                <th className="px-5 py-3 text-right">
                                                    Amount
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                            {entries.data.map((entry) => (
                                                <tr key={entry.id}>
                                                    <td className="px-5 py-4 font-semibold">
                                                        {entry.reason}
                                                    </td>
                                                    <td className="px-4 py-4 capitalize">
                                                        {entry.type.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-4 capitalize">
                                                        {entry.status}
                                                    </td>
                                                    <td className="px-5 py-4 text-right font-bold">
                                                        LKR{' '}
                                                        {Number(
                                                            entry.amount,
                                                        ).toLocaleString()}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    <ul className="divide-y divide-stone-200 md:hidden dark:divide-stone-800">
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
                                </>
                            )}
                            <SellerPagination paginator={entries} />
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
                                            className="rounded-xl border bg-transparent p-3"
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
                                        className="rounded-xl bg-primary px-4 py-3 font-bold text-primary-foreground"
                                    >
                                        Request payout
                                    </button>
                                </>
                            )}
                        </Form>
                        <h3 className="mt-8 font-bold">Recent requests</h3>
                        <ul className="mt-3 grid gap-2 text-sm">
                            {payouts.data.map((payout) => (
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
                        <SellerPagination paginator={payouts} />
                    </aside>
                </div>
            </main>
        </SellerPortalLayout>
    );
}
