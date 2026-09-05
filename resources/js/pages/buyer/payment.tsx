import { Form, Head, Link } from '@inertiajs/react';
import { Banknote, CreditCard, ShieldCheck } from 'lucide-react';
import { CartTotals } from '@/components/cart-contents';
import { CheckoutProgress } from '@/components/checkout-progress';
import { StorefrontLayout } from '@/components/storefront-layout';
import { show as checkoutShow } from '@/routes/checkout';
import { store as paymentStore } from '@/routes/checkout/payment';
import type {
    CheckoutCart,
    CheckoutPaymentMethod,
    ShippingAddress,
} from '@/types';

export default function BuyerPayment({
    cart,
    paymentMethod,
    shippingAddress,
}: {
    cart: CheckoutCart;
    paymentMethod: CheckoutPaymentMethod | null;
    shippingAddress: ShippingAddress;
}) {
    const selected =
        paymentMethod && cart.paymentMethods.includes(paymentMethod)
            ? paymentMethod
            : cart.paymentMethods[0];

    return (
        <StorefrontLayout title="Payment">
            <Head title="Payment" />
            <main className="mx-auto max-w-6xl px-4 py-7 sm:px-6">
                <CheckoutProgress current="payment" />
                <h1 className="mt-8 text-3xl font-black">Choose how to pay</h1>
                <div className="mt-6 grid items-start gap-8 lg:grid-cols-[1fr_22rem]">
                    <Form {...paymentStore.form()} className="grid gap-5">
                        {({ errors, processing }) => (
                            <>
                                {cart.paymentMethods.includes('stripe') && (
                                    <label className="flex cursor-pointer gap-4 rounded-2xl border border-slate-200 p-6">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="stripe"
                                            defaultChecked={
                                                selected === 'stripe'
                                            }
                                            className="mt-1 accent-orange-600"
                                        />
                                        <CreditCard className="size-6 shrink-0 text-orange-600" />
                                        <span>
                                            <strong>Credit / Debit Card</strong>
                                            <span className="mt-2 block text-sm text-slate-600">
                                                After reviewing your order,
                                                you’ll continue to Stripe to pay
                                                securely.
                                            </span>
                                        </span>
                                    </label>
                                )}
                                {cart.paymentMethods.includes('cod') && (
                                    <label className="flex cursor-pointer gap-4 rounded-2xl border border-slate-200 p-6">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="cod"
                                            defaultChecked={selected === 'cod'}
                                            className="mt-1 accent-orange-600"
                                        />
                                        <Banknote className="size-6 shrink-0 text-orange-600" />
                                        <span>
                                            <strong>Cash on Delivery</strong>
                                            <span className="mt-2 block text-sm text-slate-600">
                                                Pay the full order total when
                                                your delivery arrives.
                                            </span>
                                        </span>
                                    </label>
                                )}
                                {cart.paymentMethods.length === 0 && (
                                    <p
                                        role="alert"
                                        className="text-sm text-red-600"
                                    >
                                        No payment method is available for this
                                        order. Please contact support.
                                    </p>
                                )}
                                {Object.entries(errors).map(([key, error]) => (
                                    <p
                                        key={key}
                                        role="alert"
                                        className="text-sm text-red-600"
                                    >
                                        {error}
                                    </p>
                                ))}
                                <button
                                    disabled={
                                        processing ||
                                        !cart.canCheckout ||
                                        !selected
                                    }
                                    className="rounded-xl bg-[#ff5a00] px-5 py-3 font-bold text-white disabled:opacity-40"
                                >
                                    {processing
                                        ? 'Continuing…'
                                        : 'Continue to Review'}
                                </button>
                                <Link
                                    href={checkoutShow()}
                                    className="text-sm text-orange-600"
                                >
                                    Change delivery details
                                </Link>
                            </>
                        )}
                    </Form>
                    <aside className="grid gap-6 rounded-2xl border border-slate-200 p-6">
                        <h2 className="text-lg font-bold">Order summary</h2>
                        <CartTotals cart={cart} />
                        <p className="text-sm text-slate-600">
                            Deliver to {shippingAddress.recipient_name},{' '}
                            {shippingAddress.city}
                        </p>
                        <p className="flex gap-2 text-xs text-slate-500">
                            <ShieldCheck className="size-4" />
                            Your order total includes delivery.
                        </p>
                    </aside>
                </div>
            </main>
        </StorefrontLayout>
    );
}
