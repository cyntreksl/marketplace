import { Link, router, usePage } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    CartCheckout,
    CartContents,
    CartTotals,
} from '@/components/cart-contents';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { show as cartShow } from '@/routes/cart';

export function CartDrawer() {
    const { commerce } = usePage().props;
    const [open, setOpen] = useState(false);
    useEffect(
        () =>
            router.on('success', (event) => {
                if (event.detail.page.props.commerce?.cart_added) {
                    setOpen(true);
                }
            }),
        [],
    );

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <button
                    type="button"
                    aria-label="Cart"
                    className="relative flex items-center gap-2 rounded-lg p-2 hover:bg-slate-50"
                >
                    <ShoppingCart className="size-5" />
                    {commerce.cart_quantity > 0 && (
                        <span className="absolute -top-1 -right-1 rounded-full bg-[#ff5a00] px-1.5 text-[10px] font-bold text-white">
                            {commerce.cart_quantity}
                        </span>
                    )}
                    <span className="hidden text-xs font-bold lg:block">
                        Cart
                    </span>
                </button>
            </SheetTrigger>
            <SheetContent className="w-full gap-0 bg-white text-slate-950 sm:max-w-lg">
                <SheetHeader className="border-b border-slate-100 p-6">
                    <SheetTitle>
                        Your cart ({commerce.cart_quantity})
                    </SheetTitle>
                    <SheetDescription>
                        Review your items before checkout.
                    </SheetDescription>
                </SheetHeader>
                <div className="min-h-0 flex-1 overflow-y-auto px-6">
                    <CartContents cart={commerce.cart} />
                </div>
                <div className="grid gap-4 border-t border-slate-100 p-6">
                    <CartTotals cart={commerce.cart} />
                    <CartCheckout cart={commerce.cart} />
                    <Link
                        href={cartShow()}
                        onClick={() => setOpen(false)}
                        className="text-center text-sm font-bold text-orange-600"
                    >
                        View Cart
                    </Link>
                    <button
                        type="button"
                        onClick={() => setOpen(false)}
                        className="text-sm text-slate-600"
                    >
                        Continue Shopping
                    </button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
