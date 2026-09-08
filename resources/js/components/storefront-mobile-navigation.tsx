import { Link, usePage } from '@inertiajs/react';
import { Heart, House, ShoppingBag, Store, UserRound } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { home, login } from '@/routes';
import { dashboard as buyerDashboard } from '@/routes/buyer';
import { show as cartShow } from '@/routes/cart';
import { index as listingsIndex } from '@/routes/listings';
import { index as wishlistIndex } from '@/routes/wishlist';

type MobileNavigationItem = {
    label: string;
    href: string;
    icon: LucideIcon;
    isActive: boolean;
    count?: number;
};

function NavigationCount({ count }: { count?: number }) {
    if (!count) {
        return null;
    }

    return (
        <span className="absolute -top-1 -right-2 grid min-h-4 min-w-4 place-items-center rounded-full bg-[#ff5a00] px-1 text-[9px] font-black text-white ring-2 ring-white">
            {count > 99 ? '99+' : count}
        </span>
    );
}

export function StorefrontMobileNavigation() {
    const { url, props } = usePage();
    const path = url.split('?')[0];
    const { auth, commerce } = props;
    const isShopPage = [
        '/listings',
        '/categories',
        '/brands',
        '/stores',
        '/wholesale',
    ].some((prefix) => path.startsWith(prefix));
    const navigation: MobileNavigationItem[] = [
        {
            label: 'Home',
            href: home.url(),
            icon: House,
            isActive: path === home.url(),
        },
        {
            label: 'Shop',
            href: listingsIndex.url(),
            icon: Store,
            isActive: isShopPage,
        },
        {
            label: 'Cart',
            href: cartShow.url(),
            icon: ShoppingBag,
            isActive: path.startsWith(cartShow.url()),
            count: commerce.cart_quantity,
        },
        {
            label: 'Saved',
            href: auth.user ? wishlistIndex.url() : login.url(),
            icon: Heart,
            isActive: path.startsWith(wishlistIndex.url()),
            count: commerce.wishlist_count,
        },
        {
            label: 'Account',
            href: auth.user ? buyerDashboard.url() : login.url(),
            icon: UserRound,
            isActive: path.startsWith('/buyer'),
        },
    ];

    return (
        <nav
            aria-label="Mobile app navigation"
            className="fixed inset-x-0 bottom-0 z-50 border-t border-slate-200/80 bg-white/95 px-2 pt-1.5 pb-[max(0.5rem,env(safe-area-inset-bottom))] shadow-[0_-8px_30px_-18px_rgba(15,23,42,0.45)] backdrop-blur-xl lg:hidden"
        >
            <div className="mx-auto grid h-16 max-w-lg grid-cols-5 gap-1">
                {navigation.map(
                    ({ label, href, icon: Icon, isActive, count }) => (
                        <Link
                            key={label}
                            href={href}
                            prefetch
                            aria-current={isActive ? 'page' : undefined}
                            className={`flex min-w-0 flex-col items-center justify-center gap-1 rounded-xl px-1 py-1 text-[11px] font-bold transition ${
                                isActive
                                    ? 'bg-orange-50 text-[#ff5a00]'
                                    : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'
                            }`}
                        >
                            <span className="relative grid size-6 place-items-center">
                                <Icon className="size-5" aria-hidden />
                                <NavigationCount count={count} />
                            </span>
                            <span className="truncate">{label}</span>
                        </Link>
                    ),
                )}
            </div>
        </nav>
    );
}
