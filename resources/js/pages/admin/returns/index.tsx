import { Form, Head } from '@inertiajs/react';
import { AlertTriangle, BadgeCheck, CreditCard, RotateCcw } from 'lucide-react';
import { PortalLayout } from '@/components/portal-layout';
import { manual, ready, refund as processRefund } from '@/routes/admin/returns';

type OperationsReturn = {
    id: number;
    status: string;
    quantity: number;
    refund_amount: string;
    refund_ready_at: string | null;
    seller_response: string | null;
    item: {
        title: string;
        seller_order_number: string;
        seller_name: string;
    };
    buyer: { name: string; email: string };
    refund: {
        id: number;
        method: string;
        status: string;
        amount: string;
        provider_reference: string | null;
        manual_reference: string | null;
        failure_details: string | null;
        completed_at: string | null;
    } | null;
};

export default function AdminReturns({
    returns,
}: {
    returns: { data: OperationsReturn[] };
}) {
    return (
        <PortalLayout portal="admin" title="Returns & refunds">
            <Head title="Returns & refunds" />
            <main className="mx-auto max-w-7xl">
                <header>
                    <p className="text-sm font-bold tracking-wider text-primary uppercase">
                        Operations
                    </p>
                    <h1 className="mt-2 text-4xl font-black">
                        Returns & refund queue
                    </h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Coordinate approved physical returns offline, then
                        release the calculated refund. Seller decisions cannot
                        be changed here.
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
                                            Return #{record.id} ·{' '}
                                            {record.item.seller_order_number} ·{' '}
                                            {record.item.seller_name}
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
                                        Buyer
                                    </dt>
                                    <dd className="font-bold">
                                        {record.buyer.name}
                                    </dd>
                                    <dd className="text-muted-foreground">
                                        {record.buyer.email}
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
                                        Calculated refund
                                    </dt>
                                    <dd className="font-bold">
                                        LKR{' '}
                                        {Number(
                                            record.refund_amount,
                                        ).toLocaleString()}
                                    </dd>
                                </div>
                            </dl>

                            {record.seller_response && (
                                <p className="mt-4 rounded-xl bg-slate-100 p-3 text-sm dark:bg-slate-950">
                                    Seller approval: {record.seller_response}
                                </p>
                            )}

                            {record.status === 'approved' && (
                                <Form
                                    {...ready.form(record.id)}
                                    className="mt-5"
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            {errors.refund && (
                                                <p className="mb-3 text-sm text-destructive">
                                                    {errors.refund}
                                                </p>
                                            )}
                                            <button
                                                disabled={processing}
                                                className="rounded-full bg-primary px-5 py-2 text-sm font-bold text-primary-foreground disabled:opacity-50"
                                            >
                                                Physical return received —
                                                prepare refund
                                            </button>
                                        </>
                                    )}
                                </Form>
                            )}

                            {record.refund && (
                                <div className="mt-5 rounded-xl border p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p className="text-sm font-bold capitalize">
                                                {record.refund.method.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}{' '}
                                                ·{' '}
                                                {record.refund.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Idempotent refund record #
                                                {record.refund.id}
                                            </p>
                                        </div>
                                        {record.refund.status ===
                                            'succeeded' && (
                                            <BadgeCheck className="size-6 text-emerald-600" />
                                        )}
                                        {record.refund.status === 'failed' && (
                                            <AlertTriangle className="size-6 text-rose-600" />
                                        )}
                                    </div>

                                    {record.refund.failure_details && (
                                        <p className="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950/30 dark:text-rose-200">
                                            {record.refund.failure_details}
                                        </p>
                                    )}

                                    {record.status !== 'refunded' &&
                                        record.refund.method === 'stripe' && (
                                            <Form
                                                {...processRefund.form(
                                                    record.id,
                                                )}
                                                className="mt-4"
                                            >
                                                {({ errors, processing }) => (
                                                    <>
                                                        {errors.refund && (
                                                            <p className="mb-3 text-sm text-destructive">
                                                                {errors.refund}
                                                            </p>
                                                        )}
                                                        <button
                                                            disabled={
                                                                processing
                                                            }
                                                            className="inline-flex items-center gap-2 rounded-full bg-slate-950 px-5 py-2 text-sm font-bold text-white disabled:opacity-50 dark:bg-white dark:text-slate-950"
                                                        >
                                                            <CreditCard className="size-4" />
                                                            {record.status ===
                                                            'refund_failed'
                                                                ? 'Retry card refund'
                                                                : 'Process card refund'}
                                                        </button>
                                                    </>
                                                )}
                                            </Form>
                                        )}

                                    {record.status !== 'refunded' &&
                                        ['bank_transfer', 'cod'].includes(
                                            record.refund.method,
                                        ) && (
                                            <Form
                                                {...manual.form(record.id)}
                                                className="mt-4 flex flex-col gap-2 sm:flex-row"
                                            >
                                                {({ errors, processing }) => (
                                                    <>
                                                        <label className="grid flex-1 gap-1 text-sm font-semibold">
                                                            Bank or cash refund
                                                            reference
                                                            <input
                                                                name="reference"
                                                                required
                                                                minLength={3}
                                                                maxLength={255}
                                                                className="rounded-lg border bg-transparent px-3 py-2"
                                                            />
                                                            {errors.reference && (
                                                                <span className="text-sm text-destructive">
                                                                    {
                                                                        errors.reference
                                                                    }
                                                                </span>
                                                            )}
                                                        </label>
                                                        <button
                                                            disabled={
                                                                processing
                                                            }
                                                            className="self-end rounded-full bg-emerald-600 px-5 py-2 text-sm font-bold text-white disabled:opacity-50"
                                                        >
                                                            Record completion
                                                        </button>
                                                    </>
                                                )}
                                            </Form>
                                        )}

                                    {(record.refund.provider_reference ||
                                        record.refund.manual_reference) && (
                                        <p className="mt-3 text-xs text-muted-foreground">
                                            Reference:{' '}
                                            {record.refund.provider_reference ??
                                                record.refund.manual_reference}
                                        </p>
                                    )}
                                </div>
                            )}
                        </article>
                    ))}
                    {returns.data.length === 0 && (
                        <p className="rounded-2xl border border-dashed p-10 text-center text-muted-foreground">
                            No approved returns are awaiting operations.
                        </p>
                    )}
                </div>
            </main>
        </PortalLayout>
    );
}
