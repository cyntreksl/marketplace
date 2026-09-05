import { Form, Link, usePage } from '@inertiajs/react';
import { retry, returnMethod as paymentReturn } from '@/routes/checkout/card';
import { index as listingsIndex } from '@/routes/listings';

export function OrderPaymentStatus({
    number,
    method,
    status,
}: {
    number: string;
    method?: string;
    status?: string;
}) {
    const { errors } = usePage().props;

    if (method !== 'stripe') {
        return null;
    }

    return (
        <section
            className="my-5 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-800"
            aria-live="polite"
        >
            <strong>
                {status === 'paid'
                    ? 'Payment confirmed'
                    : status === 'expired'
                      ? 'Payment expired'
                      : 'Payment incomplete'}
            </strong>
            {errors.payment && (
                <p role="alert" className="text-red-600">
                    {errors.payment}
                </p>
            )}
            {status === 'pending' && (
                <>
                    <p>
                        Your order is saved. Complete payment to confirm it. If
                        you have already paid, check your payment status.
                    </p>
                    <div className="flex flex-wrap gap-4">
                        <Form {...retry.form(number)}>
                            {({ processing }) => (
                                <button
                                    disabled={processing}
                                    className="rounded-lg bg-[#ff5a00] px-4 py-2 font-bold text-white disabled:opacity-40"
                                >
                                    {processing
                                        ? 'Connecting…'
                                        : 'Continue payment'}
                                </button>
                            )}
                        </Form>
                        <Link
                            href={paymentReturn(number)}
                            className="px-2 py-2 font-semibold text-orange-600"
                        >
                            Check payment status
                        </Link>
                    </div>
                </>
            )}
            {status === 'expired' && (
                <>
                    <p>
                        Your reserved items have been released. Add available
                        items to your cart to place a new order.
                    </p>
                    <Link
                        href={listingsIndex()}
                        className="font-semibold text-orange-600"
                    >
                        Continue shopping
                    </Link>
                </>
            )}
            {status === 'failed' && (
                <p>
                    Your payment could not be completed. Contact support with
                    your order number.
                </p>
            )}
        </section>
    );
}
