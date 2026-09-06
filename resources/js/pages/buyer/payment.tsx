import { Form, Head, Link } from '@inertiajs/react';
import { Banknote, Check, CreditCard, MapPin, ShieldCheck } from 'lucide-react';
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
            : cart.paymentMethods.includes('stripe')
              ? 'stripe'
              : cart.paymentMethods[0];

    return (
        <StorefrontLayout title="Payment">
            <Head title="Payment" />
            <main className="storefront-container py-6 sm:py-7">
                <CheckoutProgress current="payment" />
                <h1 className="mt-6 text-2xl font-black sm:text-3xl">
                    Choose how to pay
                </h1>
                <div className="mt-5 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_21rem]">
                    <Form {...paymentStore.form()} className="grid gap-4">
                        {({ errors, processing }) => (
                            <>
                                <fieldset className="grid gap-3 sm:grid-cols-2">
                                    <legend className="sr-only">
                                        Payment method
                                    </legend>
                                    {cart.paymentMethods.includes('stripe') && (
                                        <label className="group relative cursor-pointer">
                                            <input
                                                type="radio"
                                                name="payment_method"
                                                value="stripe"
                                                defaultChecked={
                                                    selected === 'stripe'
                                                }
                                                className="peer sr-only"
                                            />
                                            <span className="flex min-h-32 gap-3 rounded-xl border border-slate-200 bg-white p-4 transition peer-checked:border-[#ff5a00] peer-checked:bg-orange-50/60 peer-checked:shadow-[0_0_0_1px_#ff5a00] peer-focus-visible:ring-2 peer-focus-visible:ring-orange-500 peer-focus-visible:ring-offset-2 hover:border-orange-300 hover:bg-orange-50/40">
                                                <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-orange-50 text-[#ff5a00] group-hover:bg-white">
                                                    <CreditCard className="size-5" />
                                                </span>
                                                <span className="min-w-0">
                                                    <strong className="block text-base text-slate-950">
                                                        Credit / Debit Card
                                                    </strong>
                                                    <span className="mt-1 block text-sm leading-5 text-slate-600">
                                                        Continue to Stripe after
                                                        reviewing your order.
                                                    </span>
                                                </span>
                                            </span>
                                            <span className="absolute top-3 right-3 hidden size-5 place-items-center rounded-full bg-[#ff5a00] text-white peer-checked:grid">
                                                <Check className="size-3.5" />
                                            </span>
                                        </label>
                                    )}
                                    {cart.paymentMethods.includes('cod') && (
                                        <label className="group relative cursor-pointer">
                                            <input
                                                type="radio"
                                                name="payment_method"
                                                value="cod"
                                                defaultChecked={
                                                    selected === 'cod'
                                                }
                                                className="peer sr-only"
                                            />
                                            <span className="flex min-h-32 gap-3 rounded-xl border border-slate-200 bg-white p-4 transition peer-checked:border-[#ff5a00] peer-checked:bg-orange-50/60 peer-checked:shadow-[0_0_0_1px_#ff5a00] peer-focus-visible:ring-2 peer-focus-visible:ring-orange-500 peer-focus-visible:ring-offset-2 hover:border-orange-300 hover:bg-orange-50/40">
                                                <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-orange-50 text-[#ff5a00] group-hover:bg-white">
                                                    <Banknote className="size-5" />
                                                </span>
                                                <span className="min-w-0">
                                                    <strong className="block text-base text-slate-950">
                                                        Cash on Delivery
                                                    </strong>
                                                    <span className="mt-1 block text-sm leading-5 text-slate-600">
                                                        Pay the total when your
                                                        delivery arrives.
                                                    </span>
                                                </span>
                                            </span>
                                            <span className="absolute top-3 right-3 hidden size-5 place-items-center rounded-full bg-[#ff5a00] text-white peer-checked:grid">
                                                <Check className="size-3.5" />
                                            </span>
                                        </label>
                                    )}
                                </fieldset>
                                {cart.paymentMethods.length === 0 && (
                                    <p
                                        role="alert"
                                        className="text-base text-red-600"
                                    >
                                        No payment method is available for this
                                        order. Please contact support.
                                    </p>
                                )}
                                {Object.entries(errors).map(([key, error]) => (
                                    <p
                                        key={key}
                                        role="alert"
                                        className="text-base text-red-600"
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
                            </>
                        )}
                    </Form>
                    <aside className="grid gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_3px_18px_rgba(15,23,42,0.04)] lg:sticky lg:top-4">
                        <h2 className="text-xl font-extrabold text-slate-950">
                            Order summary
                        </h2>
                        <CartTotals cart={cart} />
                        <section className="border-t border-slate-100 pt-4">
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="font-bold text-slate-950">
                                    Delivery address
                                </h3>
                                <Link
                                    href={checkoutShow()}
                                    className="shrink-0 text-sm font-semibold text-[#e65100] underline-offset-4 hover:underline"
                                >
                                    Change details
                                </Link>
                            </div>
                            <div className="mt-3 flex items-start gap-3">
                                <span className="grid size-9 shrink-0 place-items-center rounded-full bg-orange-50 text-[#ff5a00]">
                                    <MapPin className="size-4" />
                                </span>
                                <address className="text-sm leading-5 text-slate-600 not-italic">
                                    <strong className="block text-base text-slate-950">
                                        {shippingAddress.recipient_name}
                                    </strong>
                                    <span className="block">
                                        {shippingAddress.address_line_one}
                                    </span>
                                    {shippingAddress.address_line_two && (
                                        <span className="block">
                                            {shippingAddress.address_line_two}
                                        </span>
                                    )}
                                    <span className="block">
                                        {shippingAddress.city}
                                        {shippingAddress.postal_code
                                            ? `, ${shippingAddress.postal_code}`
                                            : ''}
                                    </span>
                                    <span className="block">Sri Lanka</span>
                                    <span className="mt-1 block font-semibold text-slate-700">
                                        {shippingAddress.phone}
                                    </span>
                                </address>
                            </div>
                        </section>
                        <p className="flex items-start gap-2 border-t border-slate-100 pt-4 text-sm text-slate-500">
                            <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                            Your order total includes delivery.
                        </p>
                    </aside>
                </div>
            </main>
        </StorefrontLayout>
    );
}
