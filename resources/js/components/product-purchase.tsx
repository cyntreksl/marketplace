import { Form } from '@inertiajs/react';
import { Minus, Plus, ShoppingCart, ArrowRight } from 'lucide-react';
import { store as addCartItem } from '@/actions/App/Http/Controllers/CartController';
import { buildCatalogItem, trackEvent } from '@/lib/tracking';

export function ProductPurchase({
    listingId,
    variantId,
    quantity,
    setQuantity,
    canPurchase,
    isOutOfStock,
    needsVariant,
    unitPrice,
    stockLimit,
    minimumQuantity = 1,
    instanceId = 'inline',
}: {
    listingId: number;
    variantId?: number;
    quantity: number;
    setQuantity: (quantity: number) => void;
    canPurchase: boolean;
    isOutOfStock: boolean;
    needsVariant: boolean;
    unitPrice: string | number;
    stockLimit: number;
    minimumQuantity?: number;
    instanceId?: string;
}) {
    const formId = `purchase-${listingId}-${instanceId}`;
    const maximumQuantity = Math.max(
        minimumQuantity,
        Math.min(100000, stockLimit),
    );
    const validQuantity =
        Number.isInteger(quantity) &&
        quantity >= minimumQuantity &&
        quantity <= maximumQuantity;
    const purchaseDisabled = !canPurchase || isOutOfStock || !validQuantity;
    const message = isOutOfStock
        ? 'This item is currently out of stock.'
        : needsVariant
          ? 'Choose your options to continue.'
          : !validQuantity || !canPurchase
            ? `Enter a quantity from ${minimumQuantity} to ${maximumQuantity}.`
            : null;

    return (
        <Form
            {...addCartItem.form()}
            id={formId}
            className="mt-5"
            onSuccess={() =>
                trackEvent(
                    'add_to_cart',
                    buildAddToCartParameters(
                        listingId,
                        variantId,
                        unitPrice,
                        quantity,
                    ),
                )
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
                                disabled={
                                    processing || quantity <= minimumQuantity
                                }
                                onClick={() =>
                                    setQuantity(
                                        Math.max(minimumQuantity, quantity - 1),
                                    )
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
                                min={minimumQuantity}
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
                                            minimumQuantity,
                                            Math.min(
                                                maximumQuantity,
                                                Math.floor(quantity) ||
                                                    minimumQuantity,
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
                    <div className="mt-4 hidden grid-cols-2 gap-3 lg:grid">
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
                            Checkout <ArrowRight className="size-4" />
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
                    <div
                        aria-label="Quick purchase"
                        className="fixed inset-x-0 bottom-0 z-50 border-t border-orange-100 bg-white/95 px-4 pt-2.5 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_30px_-12px_rgba(15,23,42,0.15)] backdrop-blur-xl lg:hidden"
                    >
                        <div className="mx-auto max-w-3xl">
                            <div className="grid grid-cols-2 gap-2">
                                <button
                                    form={formId}
                                    type="submit"
                                    disabled={processing || purchaseDisabled}
                                    className="flex min-h-12 items-center justify-center gap-2 rounded-xl border border-orange-300 bg-white px-3 text-sm font-bold text-orange-700 transition hover:bg-orange-50 disabled:opacity-50"
                                >
                                    <ShoppingCart className="size-4" />
                                    {processing ? 'Adding…' : 'Add to Cart'}
                                </button>
                                <button
                                    form={formId}
                                    name="buy_now"
                                    value="1"
                                    disabled={processing || purchaseDisabled}
                                    className="flex min-h-12 items-center justify-center gap-2 rounded-xl bg-primary px-3 text-sm font-bold text-white transition hover:bg-orange-600 disabled:opacity-50"
                                >
                                    {processing ? 'Adding…' : 'Checkout'}
                                    <ArrowRight className="size-4" />
                                </button>
                            </div>
                            {message && (
                                <p className="mt-1.5 text-sm text-amber-800">
                                    {message}
                                </p>
                            )}
                            {Object.values(errors).map((error) => (
                                <p
                                    key={error}
                                    role="alert"
                                    className="text-sm text-red-600"
                                >
                                    {error}
                                </p>
                            ))}
                        </div>
                    </div>
                </>
            )}
        </Form>
    );
}

export function buildAddToCartParameters(
    listingId: number,
    variantId: number | undefined,
    unitPrice: string | number,
    quantity: number,
): Record<string, unknown> {
    const numericUnitPrice = Number(unitPrice);

    return {
        currency: 'LKR',
        value: numericUnitPrice * quantity,
        items: [
            buildCatalogItem(listingId, variantId, {
                price: numericUnitPrice,
                quantity,
            }),
        ],
    };
}
