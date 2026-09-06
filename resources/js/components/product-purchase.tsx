import { Form } from '@inertiajs/react';
import { Minus, Plus, ShoppingCart, ArrowRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { store as addCartItem } from '@/actions/App/Http/Controllers/CartController';
import { trackEvent } from '@/lib/tracking';

export function ProductPurchase({
    listingId,
    variantId,
    quantity,
    setQuantity,
    canPurchase,
    isOutOfStock,
    needsVariant,
    price,
    stockLimit,
}: {
    listingId: number;
    variantId?: number;
    quantity: number;
    setQuantity: (quantity: number) => void;
    canPurchase: boolean;
    isOutOfStock: boolean;
    needsVariant: boolean;
    price: string;
    stockLimit: number;
}) {
    const actionsRef = useRef<HTMLDivElement>(null);
    const [showSticky, setShowSticky] = useState(false);
    useEffect(() => {
        const node = actionsRef.current;

        if (!node) {
            return;
        }

        const observer = new IntersectionObserver(([entry]) =>
            setShowSticky(
                !entry.isIntersecting && entry.boundingClientRect.top < 0,
            ),
        );
        observer.observe(node);

        return () => observer.disconnect();
    }, []);
    const formId = `purchase-${listingId}`;
    const maximumQuantity = Math.max(1, Math.min(100, stockLimit));
    const validQuantity =
        Number.isInteger(quantity) &&
        quantity >= 1 &&
        quantity <= maximumQuantity;
    const purchaseDisabled = !canPurchase || isOutOfStock || !validQuantity;
    const message = isOutOfStock
        ? 'This item is currently out of stock.'
        : needsVariant
          ? 'Choose your options to continue.'
          : !validQuantity || !canPurchase
            ? `Enter a quantity from 1 to ${maximumQuantity}.`
            : null;

    return (
        <Form
            {...addCartItem.form()}
            id={formId}
            className="mt-5"
            onSuccess={() =>
                trackEvent('add_to_cart', {
                    currency: 'LKR',
                    value: Number(price.replace(/[^0-9.]/g, '')) * quantity,
                    items: [
                        {
                            item_id: String(variantId ?? listingId),
                            item_group_id: String(listingId),
                            price: Number(price.replace(/[^0-9.]/g, '')),
                            quantity,
                        },
                    ],
                })
            }
        >
            {({ processing, errors }) => (
                <>
                    <input type="hidden" name="listing_id" value={listingId} />
                    <input
                        type="hidden"
                        name="listing_variant_id"
                        value={variantId ?? ''}
                    />
                    <input type="hidden" name="quantity" value={quantity} />
                    <div className="grid justify-start gap-2">
                        <label
                            htmlFor={`${formId}-quantity`}
                            className="text-sm font-bold"
                        >
                            Quantity
                        </label>
                        <div className="flex w-max items-center overflow-hidden rounded-lg border border-slate-200 bg-white focus-within:border-orange-500 focus-within:ring-2 focus-within:ring-orange-100">
                            <button
                                type="button"
                                disabled={processing || quantity <= 1}
                                onClick={() =>
                                    setQuantity(Math.max(1, quantity - 1))
                                }
                                className="grid size-11 place-items-center bg-slate-50 transition hover:bg-orange-50 disabled:opacity-40"
                                aria-label="Decrease quantity"
                            >
                                <Minus className="size-4" />
                            </button>
                            <input
                                id={`${formId}-quantity`}
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={maximumQuantity}
                                step={1}
                                value={quantity || ''}
                                disabled={processing}
                                aria-invalid={!validQuantity}
                                aria-describedby={`${formId}-quantity-help`}
                                onChange={(event) =>
                                    setQuantity(Number(event.target.value))
                                }
                                onBlur={() =>
                                    setQuantity(
                                        Math.max(
                                            1,
                                            Math.min(
                                                maximumQuantity,
                                                Math.floor(quantity) || 1,
                                            ),
                                        ),
                                    )
                                }
                                className="appearance-textfield h-11 w-16 border-x border-slate-200 text-center text-base font-bold outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                            />
                            <button
                                type="button"
                                disabled={
                                    processing || quantity >= maximumQuantity
                                }
                                onClick={() =>
                                    setQuantity(
                                        Math.min(maximumQuantity, quantity + 1),
                                    )
                                }
                                className="grid size-11 place-items-center bg-slate-50 transition hover:bg-orange-50 disabled:opacity-40"
                                aria-label="Increase quantity"
                            >
                                <Plus className="size-4" />
                            </button>
                        </div>
                        <p
                            id={`${formId}-quantity-help`}
                            className="text-sm text-slate-500"
                        >
                            {isOutOfStock
                                ? 'Currently unavailable'
                                : needsVariant
                                  ? 'Choose an option to see availability.'
                                  : `Up to ${maximumQuantity} per order`}
                        </p>
                    </div>
                    {message && (
                        <p
                            role="status"
                            className="mt-3 text-sm text-amber-800"
                        >
                            {message}
                        </p>
                    )}
                    <div
                        ref={actionsRef}
                        className="mt-4 grid grid-cols-2 gap-3"
                    >
                        <button
                            disabled={processing || purchaseDisabled}
                            className="flex min-h-13 items-center justify-center gap-2 rounded-lg border border-[#ff5a00] bg-white text-sm font-bold text-orange-700 transition hover:bg-orange-50 disabled:opacity-50"
                        >
                            <ShoppingCart className="size-5" />
                            {processing ? 'Adding…' : 'Add to Cart'}
                        </button>
                        <button
                            name="buy_now"
                            value="1"
                            disabled={processing || purchaseDisabled}
                            className="flex min-h-13 items-center justify-center gap-2 rounded-lg bg-[#ff5a00] text-sm font-bold text-white transition hover:bg-orange-600 disabled:opacity-50"
                        >
                            Buy Now <ArrowRight className="size-4" />
                        </button>
                    </div>
                    {Object.entries(errors).map(([key, error]) => (
                        <p
                            role="alert"
                            key={key}
                            className="mt-3 text-sm text-red-600"
                        >
                            {error}
                        </p>
                    ))}
                    {showSticky && (
                        <div
                            aria-label="Quick purchase"
                            className="fixed inset-x-0 bottom-0 z-40 border-t border-orange-100 bg-white/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_30px_-12px_rgba(15,23,42,0.15)] backdrop-blur-xl lg:hidden"
                        >
                            <div className="mx-auto flex max-w-3xl items-center gap-3">
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm text-slate-500">
                                        {quantity}{' '}
                                        {quantity === 1 ? 'item' : 'items'}
                                    </p>
                                    <p className="text-lg font-black text-slate-950">
                                        {price}
                                    </p>
                                </div>
                                <button
                                    form={formId}
                                    type="submit"
                                    disabled={processing || purchaseDisabled}
                                    aria-label="Add to Cart"
                                    className="grid size-12 place-items-center rounded-xl border border-orange-300 text-orange-700 disabled:opacity-50"
                                >
                                    <ShoppingCart className="size-5" />
                                </button>
                                <button
                                    form={formId}
                                    name="buy_now"
                                    value="1"
                                    disabled={processing || purchaseDisabled}
                                    className="min-h-12 rounded-xl bg-primary px-5 text-sm font-bold text-white disabled:opacity-50"
                                >
                                    {processing ? 'Adding…' : 'Buy Now'}
                                </button>
                            </div>
                            {message && (
                                <p className="mx-auto mt-1 max-w-3xl text-sm text-amber-800">
                                    {message}
                                </p>
                            )}
                            {Object.values(errors).map((error) => (
                                <p
                                    key={error}
                                    role="alert"
                                    className="mx-auto max-w-3xl text-sm text-red-600"
                                >
                                    {error}
                                </p>
                            ))}
                        </div>
                    )}
                </>
            )}
        </Form>
    );
}
