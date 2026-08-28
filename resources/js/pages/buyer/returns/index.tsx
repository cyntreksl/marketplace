import { Form, Head } from '@inertiajs/react';
import { Clock3, Image, PackageCheck } from 'lucide-react';
import { PortalLayout } from '@/components/portal-layout';
import { store } from '@/routes/buyer/returns';
import { show as evidenceShow } from '@/routes/returns/evidence';

type ReturnableItem = {
    id: number;
    title: string;
    purchased_quantity: number;
    remaining_quantity: number;
    unit_price: string;
    seller_order_number: string;
    seller_name: string;
    delivered_at: string | null;
    eligibility_expires_at: string | null;
    is_eligible: boolean;
};

type ReturnRecord = {
    id: number;
    status: string;
    reason_label: string;
    description: string;
    quantity: number;
    refund_amount: string;
    resolution_reason: string | null;
    created_at: string;
    evidence: { index: number; name: string }[];
    item: {
        title: string;
        seller_order_number: string;
        seller_name: string | null;
    };
    refund: { status: string; method: string; amount: string } | null;
};

type Reason = { value: string; label: string };

function formatDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('en-LK', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Not yet confirmed';
}

export default function BuyerReturns({
    items,
    returns,
    reasons,
}: {
    items: { data: ReturnableItem[] };
    returns: { data: ReturnRecord[] };
    reasons: Reason[];
}) {
    return (
        <PortalLayout portal="buyer" title="Returns">
            <Head title="Returns" />
            <main className="mx-auto max-w-7xl space-y-10">
                <header>
                    <p className="text-sm font-bold tracking-wider text-primary uppercase">
                        Buyer portal
                    </p>
                    <h1 className="mt-2 text-4xl font-black">
                        Returns & refunds
                    </h1>
                    <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Eligible quantities can be requested until the exact
                        expiry shown below. Approved return shipping is paid by
                        the seller and coordinated through support.
                    </p>
                </header>

                <section aria-labelledby="eligible-items">
                    <h2 id="eligible-items" className="text-2xl font-black">
                        Purchased items
                    </h2>
                    <div className="mt-4 grid gap-4">
                        {items.data.map((item) => (
                            <article
                                key={item.id}
                                className="rounded-2xl border bg-white p-5 shadow-sm dark:bg-slate-900"
                            >
                                <div className="grid gap-5 xl:grid-cols-[1fr_1.4fr]">
                                    <div>
                                        <div className="flex items-start gap-3">
                                            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                                <PackageCheck className="size-5" />
                                            </span>
                                            <div>
                                                <h3 className="font-bold">
                                                    {item.title}
                                                </h3>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {item.seller_name} ·{' '}
                                                    {item.seller_order_number}
                                                </p>
                                            </div>
                                        </div>
                                        <dl className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Available quantity
                                                </dt>
                                                <dd className="font-bold">
                                                    {item.remaining_quantity} of{' '}
                                                    {item.purchased_quantity}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Return deadline
                                                </dt>
                                                <dd className="font-bold">
                                                    {formatDate(
                                                        item.eligibility_expires_at,
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>

                                    {item.is_eligible ? (
                                        <Form
                                            {...store.form()}
                                            resetOnSuccess
                                            className="grid gap-3 rounded-xl bg-slate-50 p-4 dark:bg-slate-950"
                                        >
                                            {({
                                                errors,
                                                processing,
                                                progress,
                                            }) => (
                                                <>
                                                    <input
                                                        type="hidden"
                                                        name="order_item_id"
                                                        value={item.id}
                                                    />
                                                    <div className="grid gap-3 sm:grid-cols-2">
                                                        <label className="grid gap-1 text-sm font-semibold">
                                                            Quantity
                                                            <input
                                                                name="quantity"
                                                                type="number"
                                                                min={1}
                                                                max={
                                                                    item.remaining_quantity
                                                                }
                                                                defaultValue={1}
                                                                className="rounded-lg border bg-white px-3 py-2 dark:bg-slate-900"
                                                            />
                                                        </label>
                                                        <label className="grid gap-1 text-sm font-semibold">
                                                            Reason
                                                            <select
                                                                name="reason"
                                                                required
                                                                defaultValue=""
                                                                className="rounded-lg border bg-white px-3 py-2 dark:bg-slate-900"
                                                            >
                                                                <option
                                                                    value=""
                                                                    disabled
                                                                >
                                                                    Choose a
                                                                    reason
                                                                </option>
                                                                {reasons.map(
                                                                    (
                                                                        reason,
                                                                    ) => (
                                                                        <option
                                                                            key={
                                                                                reason.value
                                                                            }
                                                                            value={
                                                                                reason.value
                                                                            }
                                                                        >
                                                                            {
                                                                                reason.label
                                                                            }
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                        </label>
                                                    </div>
                                                    <label className="grid gap-1 text-sm font-semibold">
                                                        What happened?
                                                        <textarea
                                                            name="description"
                                                            required
                                                            minLength={10}
                                                            maxLength={2000}
                                                            rows={3}
                                                            className="rounded-lg border bg-white px-3 py-2 dark:bg-slate-900"
                                                        />
                                                    </label>
                                                    <label className="grid gap-1 text-sm font-semibold">
                                                        Evidence (optional)
                                                        <input
                                                            name="evidence[]"
                                                            type="file"
                                                            multiple
                                                            accept="image/jpeg,image/png,image/webp"
                                                            className="rounded-lg border bg-white p-2 text-sm dark:bg-slate-900"
                                                        />
                                                        <span className="text-xs font-normal text-muted-foreground">
                                                            Up to five JPG, PNG
                                                            or WebP files, 5 MB
                                                            each.
                                                        </span>
                                                    </label>
                                                    {Object.values(errors).map(
                                                        (error) => (
                                                            <p
                                                                key={error}
                                                                className="text-sm text-destructive"
                                                            >
                                                                {error}
                                                            </p>
                                                        ),
                                                    )}
                                                    {progress && (
                                                        <p className="text-xs text-muted-foreground">
                                                            Uploading{' '}
                                                            {
                                                                progress.percentage
                                                            }
                                                            %
                                                        </p>
                                                    )}
                                                    <button
                                                        disabled={processing}
                                                        className="justify-self-start rounded-xl bg-primary px-5 py-2 text-sm font-bold text-primary-foreground disabled:opacity-50"
                                                    >
                                                        Submit return request
                                                    </button>
                                                </>
                                            )}
                                        </Form>
                                    ) : (
                                        <div className="flex items-center gap-3 rounded-xl bg-slate-100 p-4 text-sm text-muted-foreground dark:bg-slate-950">
                                            <Clock3 className="size-5" />
                                            {item.delivered_at
                                                ? 'This item has no remaining eligible quantity or its return window has closed.'
                                                : 'Waiting for the seller to confirm delivery.'}
                                        </div>
                                    )}
                                </div>
                            </article>
                        ))}
                        {items.data.length === 0 && (
                            <p className="rounded-2xl border border-dashed p-10 text-center text-muted-foreground">
                                No purchased items are available yet.
                            </p>
                        )}
                    </div>
                </section>

                <section aria-labelledby="request-history">
                    <h2 id="request-history" className="text-2xl font-black">
                        Request history
                    </h2>
                    <div className="mt-4 grid gap-4">
                        {returns.data.map((record) => (
                            <article
                                key={record.id}
                                className="rounded-2xl border bg-white p-5 dark:bg-slate-900"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <h3 className="font-bold">
                                            {record.item.title}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {record.item.seller_name} ·{' '}
                                            {record.item.seller_order_number}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary capitalize">
                                        {record.status.replaceAll('_', ' ')}
                                    </span>
                                </div>
                                <p className="mt-4 text-sm">
                                    {record.reason_label} · Quantity{' '}
                                    {record.quantity} · LKR{' '}
                                    {Number(
                                        record.refund_amount,
                                    ).toLocaleString()}
                                </p>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {record.description}
                                </p>
                                {record.resolution_reason && (
                                    <p className="mt-3 rounded-lg bg-slate-100 p-3 text-sm dark:bg-slate-950">
                                        Seller response:{' '}
                                        {record.resolution_reason}
                                    </p>
                                )}
                                {record.evidence.length > 0 && (
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {record.evidence.map((file) => (
                                            <a
                                                key={file.index}
                                                href={
                                                    evidenceShow({
                                                        returnRequest:
                                                            record.id,
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
                                {record.status === 'rejected' && (
                                    <p className="mt-4 text-sm text-muted-foreground">
                                        Seller decisions are final in the
                                        portal. Email support@prodeals.lk if you
                                        need assistance.
                                    </p>
                                )}
                            </article>
                        ))}
                        {returns.data.length === 0 && (
                            <p className="rounded-2xl border border-dashed p-10 text-center text-muted-foreground">
                                You have not submitted a return request.
                            </p>
                        )}
                    </div>
                </section>
            </main>
        </PortalLayout>
    );
}
