import { Form, Head } from '@inertiajs/react';
import { Image, RotateCcw } from 'lucide-react';
import { PortalLayout } from '@/components/portal-layout';
import { show as evidenceShow } from '@/routes/returns/evidence';
import { update } from '@/routes/seller/returns';

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

export default function SellerReturns({
    returns,
}: {
    returns: { data: SellerReturn[] };
}) {
    return (
        <PortalLayout portal="seller" title="Returns">
            <Head title="Seller returns" />
            <main className="mx-auto max-w-7xl">
                <header>
                    <p className="text-sm font-bold tracking-wider text-primary uppercase">
                        Seller portal
                    </p>
                    <h1 className="mt-2 text-4xl font-black">
                        Return requests
                    </h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Review requests for your own order lines. Approval means
                        you pay return shipping; support coordinates the
                        physical return offline.
                    </p>
                </header>

                <div className="mt-8 grid gap-4">
                    {returns.data.map((record) => (
                        <article
                            key={record.id}
                            className="rounded-2xl border bg-white p-5 dark:bg-slate-900"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="flex gap-3">
                                    <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                        <RotateCcw className="size-5" />
                                    </span>
                                    <div>
                                        <h2 className="font-bold">
                                            {record.item.title}
                                        </h2>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {record.item.seller_order_number} ·{' '}
                                            {record.buyer.name}
                                        </p>
                                    </div>
                                </div>
                                <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary capitalize">
                                    {record.status.replaceAll('_', ' ')}
                                </span>
                            </div>

                            <dl className="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Reason
                                    </dt>
                                    <dd className="font-bold">
                                        {record.reason_label}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Quantity
                                    </dt>
                                    <dd className="font-bold">
                                        {record.quantity}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        Proposed refund
                                    </dt>
                                    <dd className="font-bold">
                                        LKR{' '}
                                        {Number(
                                            record.refund_amount,
                                        ).toLocaleString()}
                                    </dd>
                                </div>
                            </dl>
                            <p className="mt-4 text-sm text-muted-foreground">
                                {record.description}
                            </p>
                            {record.evidence.length > 0 && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {record.evidence.map((file) => (
                                        <a
                                            key={file.index}
                                            href={
                                                evidenceShow({
                                                    returnRequest: record.id,
                                                    evidence: file.index,
                                                }).url
                                            }
                                            className="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold hover:border-primary hover:text-primary"
                                        >
                                            <Image className="size-4" />
                                            {file.name}
                                        </a>
                                    ))}
                                </div>
                            )}

                            {record.status === 'requested' ? (
                                <Form
                                    {...update.form(record.id)}
                                    className="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 dark:bg-slate-950"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <label className="grid gap-1 text-sm font-semibold">
                                                Response reason
                                                <textarea
                                                    name="response_reason"
                                                    required
                                                    minLength={10}
                                                    maxLength={2000}
                                                    rows={3}
                                                    className="rounded-lg border bg-white px-3 py-2 dark:bg-slate-900"
                                                />
                                            </label>
                                            {errors.response_reason && (
                                                <p className="text-sm text-destructive">
                                                    {errors.response_reason}
                                                </p>
                                            )}
                                            <div className="flex flex-wrap gap-2">
                                                <button
                                                    name="decision"
                                                    value="approved"
                                                    disabled={processing}
                                                    className="rounded-full bg-emerald-600 px-5 py-2 text-sm font-bold text-white disabled:opacity-50"
                                                >
                                                    Approve return
                                                </button>
                                                <button
                                                    name="decision"
                                                    value="rejected"
                                                    disabled={processing}
                                                    className="rounded-full bg-rose-600 px-5 py-2 text-sm font-bold text-white disabled:opacity-50"
                                                >
                                                    Reject return
                                                </button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <p className="mt-5 rounded-xl bg-slate-100 p-4 text-sm dark:bg-slate-950">
                                    Final seller response:{' '}
                                    {record.resolution_reason}
                                </p>
                            )}
                        </article>
                    ))}
                    {returns.data.length === 0 && (
                        <p className="rounded-2xl border border-dashed p-10 text-center text-muted-foreground">
                            No return requests for your orders.
                        </p>
                    )}
                </div>
            </main>
        </PortalLayout>
    );
}
