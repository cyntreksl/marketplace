import { Form, Head } from '@inertiajs/react';
import { Image } from 'lucide-react';
import { SellerPageHeader } from '@/components/seller-page-header';
import { SellerPagination } from '@/components/seller-pagination';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { show as evidenceShow } from '@/routes/returns/evidence';
import { index, update } from '@/routes/seller/returns';
import type { SellerPaginator } from '@/types';

type SellerReturn = {
    id: number;
    status: string;
    reason_label: string;
    description: string;
    quantity: number;
    refund_amount: string;
    resolution_reason: string | null;
    evidence: { index: number; name: string }[];
    item: { title: string; seller_order_number: string };
    buyer: { name: string; email: string };
};

function ReturnActions({ record }: { record: SellerReturn }) {
    if (record.status !== 'requested') {
        return (
            <p className="text-sm text-slate-500">
                {record.resolution_reason ?? 'No further seller action.'}
            </p>
        );
    }

    return (
        <Form {...update.form(record.id)} className="grid gap-2">
            <textarea
                name="response_reason"
                required
                minLength={10}
                maxLength={2000}
                rows={2}
                placeholder="Explain your decision"
                className="rounded-xl border bg-transparent p-3 text-sm"
            />
            <div className="flex gap-2">
                <button
                    name="decision"
                    value="approved"
                    className="min-h-10 flex-1 rounded-xl bg-emerald-600 px-3 text-xs font-bold text-white"
                >
                    Approve
                </button>
                <button
                    name="decision"
                    value="rejected"
                    className="min-h-10 flex-1 rounded-xl bg-rose-600 px-3 text-xs font-bold text-white"
                >
                    Reject
                </button>
            </div>
        </Form>
    );
}

function Evidence({ record }: { record: SellerReturn }) {
    if (record.evidence.length === 0) {
        return <span className="text-slate-400">None</span>;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {record.evidence.map((file) => (
                <a
                    key={file.index}
                    href={
                        evidenceShow({
                            returnRequest: record.id,
                            evidence: file.index,
                        }).url
                    }
                    className="inline-flex min-h-10 items-center gap-1 rounded-xl border px-3 text-xs font-bold"
                >
                    <Image className="size-4" /> {file.name}
                </a>
            ))}
        </div>
    );
}

export default function SellerReturns({
    returns,
    filters,
}: {
    returns: SellerPaginator<SellerReturn>;
    filters: { q: string; status: string };
}) {
    return (
        <SellerPortalLayout title="Returns">
            <Head title="Seller returns" />
            <div className="space-y-6">
                <SellerPageHeader
                    title="Return requests"
                    description="Review requests for your order lines. Approval means you pay return shipping; support coordinates the physical return offline."
                />
                <Form
                    {...index.form()}
                    className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-[1fr_12rem_auto] dark:bg-slate-900"
                >
                    <input
                        name="q"
                        defaultValue={filters.q}
                        maxLength={100}
                        placeholder="Order, product, or buyer"
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    />
                    <select
                        name="status"
                        defaultValue={filters.status}
                        className="min-h-11 rounded-xl border bg-transparent px-3"
                    >
                        <option value="all">All statuses</option>
                        <option value="requested">Requested</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="refund_pending">Refund pending</option>
                        <option value="refunded">Refunded</option>
                        <option value="refund_failed">Refund failed</option>
                    </select>
                    <button className="min-h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white dark:bg-white dark:text-slate-950">
                        Apply
                    </button>
                </Form>
                <section className="overflow-hidden rounded-2xl border bg-white dark:bg-slate-900">
                    <div className="hidden lg:block">
                        <table className="w-full table-fixed text-left text-sm">
                            <thead className="bg-slate-50 text-xs text-slate-500 uppercase dark:bg-slate-950">
                                <tr>
                                    <th className="w-[18%] px-4 py-3">
                                        Order / buyer
                                    </th>
                                    <th className="w-[20%] px-4 py-3">
                                        Product
                                    </th>
                                    <th className="w-[15%] px-4 py-3">
                                        Request
                                    </th>
                                    <th className="w-[12%] px-4 py-3">
                                        Refund
                                    </th>
                                    <th className="w-[15%] px-4 py-3">
                                        Evidence
                                    </th>
                                    <th className="w-[20%] px-4 py-3">
                                        Response
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {returns.data.map((record) => (
                                    <tr key={record.id}>
                                        <td className="px-4 py-4 align-top">
                                            <p className="font-bold">
                                                {
                                                    record.item
                                                        .seller_order_number
                                                }
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {record.buyer.name}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <p className="font-semibold">
                                                {record.item.title}
                                            </p>
                                            <p className="mt-1 line-clamp-2 text-xs text-slate-500">
                                                {record.description}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <span className="rounded-full bg-orange-100 px-2.5 py-1 text-xs font-bold text-orange-700 capitalize">
                                                {record.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </span>
                                            <p className="mt-2 text-xs">
                                                {record.reason_label} · Qty{' '}
                                                {record.quantity}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 align-top font-bold">
                                            LKR{' '}
                                            {Number(
                                                record.refund_amount,
                                            ).toLocaleString()}
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <Evidence record={record} />
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <ReturnActions record={record} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="divide-y lg:hidden">
                        {returns.data.map((record) => (
                            <article key={record.id} className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-black">
                                            {record.item.title}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            {record.item.seller_order_number} ·{' '}
                                            {record.buyer.name}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-orange-100 px-2.5 py-1 text-xs font-bold text-orange-700 capitalize">
                                        {record.status.replaceAll('_', ' ')}
                                    </span>
                                </div>
                                <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <dt className="text-xs text-slate-500">
                                            Reason
                                        </dt>
                                        <dd className="font-semibold">
                                            {record.reason_label}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs text-slate-500">
                                            Refund
                                        </dt>
                                        <dd className="font-bold">
                                            LKR{' '}
                                            {Number(
                                                record.refund_amount,
                                            ).toLocaleString()}
                                        </dd>
                                    </div>
                                </dl>
                                <p className="mt-3 text-sm text-slate-600">
                                    {record.description}
                                </p>
                                <div className="mt-3">
                                    <Evidence record={record} />
                                </div>
                                <div className="mt-4">
                                    <ReturnActions record={record} />
                                </div>
                            </article>
                        ))}
                    </div>
                    {returns.data.length === 0 && (
                        <p className="p-12 text-center text-sm text-slate-500">
                            No return requests match these filters.
                        </p>
                    )}
                    <SellerPagination paginator={returns} />
                </section>
            </div>
        </SellerPortalLayout>
    );
}
