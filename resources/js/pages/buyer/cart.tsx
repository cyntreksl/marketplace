import { Head } from '@inertiajs/react';
import {
    CartCheckout,
    CartContents,
    CartTotals,
} from '@/components/cart-contents';
import { StorefrontLayout } from '@/components/storefront-layout';
import type { CheckoutCart } from '@/types';

export default function BuyerCart({ cart }: { cart: CheckoutCart }) {
    return (
        <StorefrontLayout title="Your cart">
            <Head title="Your cart" />
            <main className="storefront-container py-10">
                <h1 className="text-3xl font-black">Your cart</h1>
                <div className="mt-6 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <CartContents cart={cart} />
                    <aside className="grid gap-6 rounded-2xl border border-slate-200 p-6">
                        <h2 className="text-xl font-bold">Order summary</h2>
                        <CartTotals cart={cart} />
                        <CartCheckout cart={cart} />
                    </aside>
                </div>
            </main>
        </StorefrontLayout>
    );
}
