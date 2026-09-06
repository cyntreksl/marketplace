import { Deferred, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CreditCard,
    KeyRound,
    MapPin,
    MessageSquareText,
    PackageCheck,
    RotateCcw,
    ShieldCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { BuyerPageHeader } from '@/components/buyer-page-header';
import { BuyerPortalLayout } from '@/components/buyer-portal-layout';
import { BuyerStatusBadge } from '@/components/buyer-status-badge';
import { Skeleton } from '@/components/ui/skeleton';
import { index as addressesIndex } from '@/routes/buyer/addresses';
import { index as feedbackIndex } from '@/routes/buyer/feedback';
import { index as ordersIndex, show as orderShow } from '@/routes/buyer/orders';
import { index as paymentsIndex } from '@/routes/buyer/payments';
import { index as returnsIndex } from '@/routes/buyer/returns';
import { edit as securityEdit } from '@/routes/buyer/settings/security';
import type {
    BuyerAddress,
    BuyerOrder,
    BuyerOrderStage,
    PaymentAttempt,
} from '@/types';

type Summary = {
    order_counts: Record<BuyerOrderStage, number>;
    pending_feedback_count: number;
    active_return_count: number;
    default_shipping_address: BuyerAddress | null;
    default_billing_address: BuyerAddress | null;
    security: { two_factor_enabled: boolean; passkey_count: number };
};
type Activity = {
    recent_orders: BuyerOrder[];
    recent_payments: PaymentAttempt[];
};

function Stat({
    label,
    value,
    icon: Icon,
    href,
}: {
    label: string;
    value: number;
    icon: LucideIcon;
    href: ReturnType<typeof ordersIndex>;
}) {
    return (
        <Link
            href={href}
            className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-orange-300 dark:border-slate-800 dark:bg-slate-900"
        >
            <span className="grid size-10 place-items-center rounded-xl bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">
                <Icon className="size-5" />
            </span>
            <p className="mt-5 text-3xl font-black">{value}</p>
            <p className="mt-1 flex items-center justify-between text-sm font-semibold text-slate-600 dark:text-slate-300">
                {label}
                <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
            </p>
        </Link>
    );
}

export default function BuyerOverview({
    summary,
    activity,
}: {
    summary: Summary;
    activity?: Activity;
}) {
    const unpaid = summary.order_counts.to_pay ?? 0;

    return (
        <BuyerPortalLayout title="Overview">
            <Head title="Buyer overview" />
            <div className="space-y-8">
                <BuyerPageHeader
                    eyebrow="Welcome back"
                    title="Your shopping, at a glance"
                    description="Track purchases, payments, returns, addresses, and account security from one place."
                />
                {unpaid > 0 && (
                    <Link
                        href={ordersIndex({ query: { stage: 'to_pay' } })}
                        className="flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-amber-500/20 dark:bg-amber-500/10"
                    >
                        <div>
                            <p className="font-bold text-amber-950 dark:text-amber-200">
                                {unpaid} order{unpaid === 1 ? '' : 's'} waiting
                                for payment
                            </p>
                            <p className="mt-1 text-sm text-amber-800 dark:text-amber-300">
                                Complete payment before the checkout session
                                expires.
                            </p>
                        </div>
                        <span className="inline-flex items-center gap-2 text-sm font-bold text-amber-900 dark:text-amber-200">
                            Review orders <ArrowRight className="size-4" />
                        </span>
                    </Link>
                )}
                <section
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Buyer activity summary"
                >
                    <Stat
                        label="To pay"
                        value={unpaid}
                        icon={CreditCard}
                        href={ordersIndex({ query: { stage: 'to_pay' } })}
                    />
                    <Stat
                        label="In progress"
                        value={
                            (summary.order_counts.processing ?? 0) +
                            (summary.order_counts.shipped ?? 0)
                        }
                        icon={PackageCheck}
                        href={ordersIndex({ query: { stage: 'processing' } })}
                    />
                    <Stat
                        label="Awaiting feedback"
                        value={summary.pending_feedback_count}
                        icon={MessageSquareText}
                        href={feedbackIndex()}
                    />
                    <Stat
                        label="Active returns"
                        value={summary.active_return_count}
                        icon={RotateCcw}
                        href={returnsIndex()}
                    />
                </section>
                <Deferred
                    data="activity"
                    fallback={
                        <div className="grid gap-5 lg:grid-cols-2">
                            <Skeleton className="h-64 rounded-2xl" />
                            <Skeleton className="h-64 rounded-2xl" />
                        </div>
                    }
                >
                    <div className="grid gap-5 lg:grid-cols-2">
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-black">
                                    Recent orders
                                </h2>
                                <Link
                                    href={ordersIndex()}
                                    className="text-sm font-bold text-orange-700 dark:text-orange-300"
                                >
                                    View all
                                </Link>
                            </div>
                            <div className="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                                {(activity?.recent_orders ?? []).map(
                                    (order) => (
                                        <Link
                                            key={order.number}
                                            href={orderShow(order.number)}
                                            className="flex items-center justify-between gap-3 py-3"
                                        >
                                            <div>
                                                <p className="text-sm font-bold">
                                                    {order.number}
                                                </p>
                                                <p className="text-xs text-slate-500">
                                                    {order.seller_orders
                                                        .map(
                                                            (seller) =>
                                                                seller.store_name,
                                                        )
                                                        .join(', ')}
                                                </p>
                                            </div>
                                            <BuyerStatusBadge
                                                status={order.stage}
                                                label={order.stage_label}
                                            />
                                        </Link>
                                    ),
                                )}
                                {(activity?.recent_orders ?? []).length ===
                                    0 && (
                                    <p className="py-8 text-center text-sm text-slate-500">
                                        Your recent orders will appear here.
                                    </p>
                                )}
                            </div>
                        </section>
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-black">
                                    Recent payments
                                </h2>
                                <Link
                                    href={paymentsIndex()}
                                    className="text-sm font-bold text-orange-700 dark:text-orange-300"
                                >
                                    View all
                                </Link>
                            </div>
                            <div className="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                                {(activity?.recent_payments ?? []).map(
                                    (attempt) => (
                                        <div
                                            key={attempt.id}
                                            className="flex items-center justify-between gap-3 py-3"
                                        >
                                            <div>
                                                <p className="text-sm font-bold">
                                                    {attempt.order_number}
                                                </p>
                                                <p className="text-xs text-slate-500 capitalize">
                                                    {attempt.method.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}{' '}
                                                    · LKR{' '}
                                                    {Number(
                                                        attempt.amount,
                                                    ).toLocaleString()}
                                                </p>
                                            </div>
                                            <BuyerStatusBadge
                                                status={attempt.status}
                                                label={attempt.status_label}
                                            />
                                        </div>
                                    ),
                                )}
                                {(activity?.recent_payments ?? []).length ===
                                    0 && (
                                    <p className="py-8 text-center text-sm text-slate-500">
                                        No payment activity yet.
                                    </p>
                                )}
                            </div>
                        </section>
                    </div>
                </Deferred>
                <div className="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <div className="flex items-center justify-between">
                            <h2 className="flex items-center gap-2 font-black">
                                <MapPin className="size-5 text-orange-600" />{' '}
                                Default addresses
                            </h2>
                            <Link
                                href={addressesIndex()}
                                className="text-sm font-bold text-orange-700 dark:text-orange-300"
                            >
                                Manage
                            </Link>
                        </div>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            {(
                                [
                                    [
                                        'Shipping',
                                        summary.default_shipping_address,
                                    ],
                                    [
                                        'Billing',
                                        summary.default_billing_address,
                                    ],
                                ] as const
                            ).map(([purpose, address]) => (
                                <div
                                    key={purpose}
                                    className="rounded-xl bg-slate-50 p-4 dark:bg-slate-950"
                                >
                                    <p className="text-xs font-bold tracking-wide text-slate-400 uppercase">
                                        {purpose}
                                    </p>
                                    {address ? (
                                        <>
                                            <p className="mt-2 text-sm font-bold">
                                                {address.recipient_name}
                                            </p>
                                            <p className="mt-1 text-xs leading-5 text-slate-500">
                                                {address.address_line_one},{' '}
                                                {address.city}
                                            </p>
                                        </>
                                    ) : (
                                        <p className="mt-2 text-sm text-slate-500">
                                            No default selected
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                    <Link
                        href={securityEdit()}
                        className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div className="flex items-center justify-between">
                            <h2 className="flex items-center gap-2 font-black">
                                <ShieldCheck className="size-5 text-orange-600" />{' '}
                                Security
                            </h2>
                            <ArrowRight className="size-4" />
                        </div>
                        <div className="mt-4 grid gap-2 text-sm">
                            <p className="flex items-center justify-between">
                                <span>Two-step verification</span>
                                <span className="font-bold">
                                    {summary.security.two_factor_enabled
                                        ? 'On'
                                        : 'Off'}
                                </span>
                            </p>
                            <p className="flex items-center justify-between">
                                <span className="flex items-center gap-1.5">
                                    <KeyRound className="size-4" /> Passkeys
                                </span>
                                <span className="font-bold">
                                    {summary.security.passkey_count}
                                </span>
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </BuyerPortalLayout>
    );
}
