import { Link, router, useForm } from '@inertiajs/react';
import { Minus, Plus, ShoppingBag, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy, update } from '@/routes/cart/items';
import { show as checkoutShow } from '@/routes/checkout';
import { index as listingsIndex, show as listingShow } from '@/routes/listings';
import type { CheckoutCart, CheckoutCartItem } from '@/types';

export function cartMoney(value: string | number): string {
    return `LKR ${Number(value).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function CartLine({ item }: { item: CheckoutCartItem }) {
    const form = useForm({ quantity: item.quantity });
    const [removing, setRemoving] = useState(false);
    const [imageFailed, setImageFailed] = useState(false);
    const image = item.listing.media[0]?.cardUrl;
    const busy = form.processing || removing;
    function change(quantity: number) {
        form.transform(() => ({ quantity }));
        form.patch(update.url(String(item.id)), { preserveScroll: true });
    }

    return (
        <li className="flex gap-4 border-b border-slate-100 py-5">
            {image && !imageFailed ? (
                <img
                    src={image}
                    onError={() => setImageFailed(true)}
                    alt=""
                    className="size-20 rounded-xl border border-slate-100 object-contain"
                />
            ) : (
                <ShoppingBag className="size-20 shrink-0 rounded-xl bg-slate-50 p-6 text-slate-300" />
            )}
            <div className="min-w-0 flex-1">
                {item.listing.slug ? (
                    <Link
                        href={listingShow(item.listing.slug)}
                        className="text-sm font-bold hover:text-orange-600"
                    >
                        {item.listing.title}
                    </Link>
                ) : (
                    <strong className="text-sm">{item.listing.title}</strong>
                )}
                <p className="mt-1 text-xs text-slate-500">
                    {item.listing.seller_profile.store_name}
                </p>
                {item.variant && (
                    <p className="mt-1 text-xs text-slate-500">
                        {item.variant.option_values
                            .map(
                                (option) =>
                                    `${option.option.name}: ${option.value}`,
                            )
                            .join(' · ')}
                    </p>
                )}
                <p className="mt-2 text-xs text-slate-600">
                    {cartMoney(item.unitPrice)} each
                </p>
                <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <div className="inline-flex items-center rounded-lg border border-slate-200">
                        <button
                            type="button"
                            disabled={busy || item.quantity <= 1}
                            onClick={() => change(item.quantity - 1)}
                            aria-label={`Decrease quantity of ${item.listing.title}`}
                            className="p-2 disabled:opacity-30"
                        >
                            <Minus className="size-4" />
                        </button>
                        <input
                            key={item.quantity}
                            type="number"
                            min={1}
                            max={100}
                            defaultValue={item.quantity}
                            disabled={busy}
                            aria-label={`Quantity for ${item.listing.title}`}
                            onBlur={(event) => {
                                const quantity = Number(
                                    event.currentTarget.value,
                                );

                                if (quantity !== item.quantity) {
                                    change(quantity);
                                }
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.currentTarget.blur();
                                }
                            }}
                            className="w-12 text-center text-sm font-semibold"
                        />
                        <button
                            type="button"
                            disabled={
                                busy || item.quantity >= item.availableQuantity
                            }
                            onClick={() => change(item.quantity + 1)}
                            aria-label={`Increase quantity of ${item.listing.title}`}
                            className="p-2 disabled:opacity-30"
                        >
                            <Plus className="size-4" />
                        </button>
                    </div>
                    <strong className="text-sm">{cartMoney(item.total)}</strong>
                    <button
                        type="button"
                        disabled={busy}
                        onClick={() =>
                            router.delete(destroy.url(String(item.id)), {
                                preserveScroll: true,
                                onStart: () => setRemoving(true),
                                onFinish: () => setRemoving(false),
                            })
                        }
                        aria-label={`Remove ${item.listing.title}`}
                        className="p-2 text-slate-500 hover:text-red-600 disabled:opacity-30"
                    >
                        <Trash2 className="size-4" />
                    </button>
                </div>
                {(item.error || form.errors.quantity) && (
                    <p role="alert" className="mt-2 text-xs text-red-600">
                        {form.errors.quantity || item.error}
                    </p>
                )}
            </div>
        </li>
    );
}

export function CartContents({ cart }: { cart: CheckoutCart }) {
    if (cart.items.length === 0) {
        return (
            <div className="grid justify-items-center gap-4 py-16 text-center">
                <ShoppingBag className="size-12 text-slate-300" />
                <h2 className="text-lg font-bold">Your cart is empty</h2>
                <Link
                    href={listingsIndex()}
                    className="text-sm font-semibold text-orange-600"
                >
                    Explore products
                </Link>
            </div>
        );
    }

    return (
        <ul>
            {cart.items.map((item) => (
                <CartLine key={item.id} item={item} />
            ))}
        </ul>
    );
}

export function CartTotals({ cart }: { cart: CheckoutCart }) {
    return (
        <dl className="grid gap-3 text-sm">
            <div className="flex justify-between">
                <dt>Subtotal</dt>
                <dd>{cartMoney(cart.subtotal)}</dd>
            </div>
            <div className="flex justify-between">
                <dt>Delivery</dt>
                <dd>{cartMoney(cart.shippingTotal)}</dd>
            </div>
            <div className="flex justify-between border-t border-slate-200 pt-4 text-base font-bold">
                <dt>Total</dt>
                <dd>{cartMoney(cart.total)}</dd>
            </div>
        </dl>
    );
}

export function CartCheckout({ cart }: { cart: CheckoutCart }) {
    return cart.canCheckout ? (
        <Link
            href={checkoutShow()}
            className="block rounded-xl bg-[#ff5a00] px-5 py-3 text-center text-sm font-bold text-white hover:bg-orange-600"
        >
            Checkout
        </Link>
    ) : (
        <button
            disabled
            className="w-full rounded-xl bg-slate-200 px-5 py-3 text-sm font-bold text-slate-500"
        >
            {cart.items.length ? 'Update your cart to continue' : 'Checkout'}
        </button>
    );
}
