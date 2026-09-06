import { Head, Link } from '@inertiajs/react';
import { CreditCard, ReceiptText } from 'lucide-react';
import { BuyerPageHeader } from '@/components/buyer-page-header';
import { BuyerPagination } from '@/components/buyer-pagination';
import { BuyerPortalLayout } from '@/components/buyer-portal-layout';
import { BuyerStatusBadge } from '@/components/buyer-status-badge';
import { show as orderShow } from '@/routes/buyer/orders';
import { index } from '@/routes/buyer/payments';
import type { Paginated, PaymentAttempt } from '@/types';

const filters = [
    { value: 'all', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'successful', label: 'Successful' },
    { value: 'failed', label: 'Failed' },
];

export default function BuyerPayments({
    attempts,
    status,
}: {
    attempts: Paginated<PaymentAttempt>;
    status: string;
}) {
    return (
        <BuyerPortalLayout title="Payments">
            <Head title="Payments" />
            <div className="space-y-7">
                <BuyerPageHeader
                    eyebrow="Transactions"
                    title="Payment history"
                    description="Every checkout attempt is recorded here. Card details always stay with the hosted payment provider."
                />
                <nav
                    className="flex gap-2 overflow-x-auto"
                    aria-label="Payment filters"
                >
                    {filters.map((filter) => (
                        <Link
                            key={filter.value}
                            href={index({
                                query:
                                    filter.value === 'all'
                                        ? {}
                                        : { status: filter.value },
                            })}
                            className={`shrink-0 rounded-full border px-4 py-2 text-sm font-semibold ${status === filter.value ? 'border-orange-600 bg-orange-600 text-white' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'}`}
                        >
                            {filter.label}
                        </Link>
                    ))}
                </nav>
                {attempts.data.length === 0 ? (
                    <div className="grid min-h-72 place-items-center rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
                        <div>
                            <ReceiptText className="mx-auto size-10 text-orange-600" />
                            <h2 className="mt-4 font-black">
                                No payment activity
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Payment attempts will appear after checkout.
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                        <div className="hidden grid-cols-[1fr_1fr_1fr_1fr_auto] gap-4 border-b border-slate-100 px-5 py-3 text-xs font-bold tracking-wide text-slate-400 uppercase md:grid dark:border-slate-800">
                            <span>Order</span>
                            <span>Method</span>
                            <span>Date</span>
                            <span>Amount</span>
                            <span>Status</span>
                        </div>
                        {attempts.data.map((attempt) => (
                            <article
                                key={attempt.id}
                                className="grid gap-3 border-b border-slate-100 px-5 py-4 last:border-0 md:grid-cols-[1fr_1fr_1fr_1fr_auto] md:items-center dark:border-slate-800"
                            >
                                <Link
                                    href={orderShow(attempt.order_number)}
                                    className="font-bold text-orange-700 dark:text-orange-300"
                                >
                                    {attempt.order_number}
                                </Link>
                                <span className="flex items-center gap-2 text-sm capitalize">
                                    <CreditCard className="size-4 text-slate-400" />
                                    {attempt.method.replaceAll('_', ' ')}
                                </span>
                                <span className="text-sm text-slate-500">
                                    {attempt.attempted_at
                                        ? new Intl.DateTimeFormat('en-LK', {
                                              dateStyle: 'medium',
                                              timeStyle: 'short',
                                          }).format(
                                              new Date(attempt.attempted_at),
                                          )
                                        : '—'}
                                </span>
                                <span className="font-bold">
                                    LKR{' '}
                                    {Number(attempt.amount).toLocaleString(
                                        'en-LK',
                                        { minimumFractionDigits: 2 },
                                    )}
                                </span>
                                <BuyerStatusBadge
                                    status={attempt.status}
                                    label={attempt.status_label}
                                />
                                {attempt.failure_summary && (
                                    <p className="text-xs text-red-600 md:col-span-5">
                                        {attempt.failure_summary}
                                    </p>
                                )}
                            </article>
                        ))}
                    </div>
                )}
                <BuyerPagination links={attempts.links} />
            </div>
        </BuyerPortalLayout>
    );
}
