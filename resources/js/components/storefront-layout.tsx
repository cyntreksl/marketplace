import { Form, Link, usePage } from '@inertiajs/react';
import {
    CircleHelp,
    Heart,
    PackageSearch,
    Search,
    UserRound,
} from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import { CartDrawer } from '@/components/cart-drawer';
import {
    categoryContainsSlug,
    DesktopStorefrontCategoryMenu,
    MobileStorefrontCategoryMenu,
} from '@/components/storefront-category-menu';
import type { StorefrontCategory } from '@/components/storefront-category-menu';
import { StorefrontFooter } from '@/components/storefront-footer';
import { StorefrontMobileNavigation } from '@/components/storefront-mobile-navigation';
import { home, login } from '@/routes';
import { index as auctionsIndex } from '@/routes/auctions';
import { index as buyerOrdersIndex } from '@/routes/buyer/orders';
import { index as guidesIndex } from '@/routes/guides';
import { index as listingsIndex } from '@/routes/listings';
import {
    dashboard as sellerDashboard,
    register as sellerRegister,
} from '@/routes/seller';
import { index as wholesaleIndex } from '@/routes/wholesale';

export type { StorefrontCategory } from '@/components/storefront-category-menu';

function CountBadge({ count }: { count: number }) {
    if (count < 1) {
        return null;
    }

    return (
        <span className="absolute -top-1 -right-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-[#FF6D00] px-1 text-[9px] font-bold text-white">
            {count > 99 ? '99+' : count}
        </span>
    );
}

export function StorefrontLayout({
    children,
    categories,
    activeCategorySlugs = [],
    showMobileNavigation = true,
}: {
    children: React.ReactNode;
    title: string;
    description?: string | null;
    categories?: StorefrontCategory[];
    activeCategorySlugs?: string[];
    showMobileNavigation?: boolean;
}) {
    const { component, url, props } = usePage<{
        categories?: StorefrontCategory[];
    }>();
    const { auth, commerce } = props;
    const isHomePage = component === 'storefront/home';
    const navigationCategories = categories ?? props.categories ?? [];
    const selectedCategorySlug =
        activeCategorySlugs.findLast((slug) =>
            navigationCategories.some((category) =>
                categoryContainsSlug(category, slug),
            ),
        ) ?? null;
    const isAllProductsSelected =
        component === 'storefront/listings/index' && !selectedCategorySlug;
    const search =
        new URL(url, 'https://storefront.local').searchParams.get('search') ??
        '';
    const isWholesaleCatalog = url.split('?')[0] === wholesaleIndex.url();
    const searchRoute = isWholesaleCatalog ? wholesaleIndex : listingsIndex;
    const categoryMenuProps = {
        categories: navigationCategories,
        selectedCategorySlug,
        isAllProductsSelected,
    };

    const navigation = [
        ['Shop', '/listings'],
        ['Wholesale', wholesaleIndex.url()],
        ...(props.auctionFlags.enabled
            ? ([['Auctions', auctionsIndex.url()]] as const)
            : []),
        ['Deals', '/collections/deals'],
        ['Brands', '/brands'],
        ['Guides', guidesIndex.url()],
        ['Buying', '/buying'],
        ['Selling', '/selling'],
    ] as const;

    return (
        <div
            className={`min-h-screen bg-white text-slate-950 ${
                showMobileNavigation
                    ? 'pb-[calc(4.5rem+env(safe-area-inset-bottom))] lg:pb-0'
                    : ''
            }`}
        >
            <div className="hidden bg-[#FF6D00] text-white lg:block">
                <div className="storefront-container flex min-h-10 items-center justify-between gap-4 overflow-x-auto text-xs whitespace-nowrap">
                    <div className="flex shrink-0 items-center gap-3 font-medium sm:gap-6">
                        <span className="flex min-w-0 items-center">
                            Cash on Delivery
                        </span>
                        <span className="flex min-w-0 items-center border-l border-white/25 pl-3">
                            Islandwide Delivery
                        </span>
                        <Link
                            href="/collections/deals"
                            className="flex min-w-0 items-center border-l border-white/25 pl-3"
                        >
                            Shop Deals
                            <span className="ml-1 text-[10px] opacity-80">
                                →
                            </span>
                        </Link>
                    </div>
                    <div className="flex shrink-0 items-center gap-4">
                        <Link
                            href="/order-tracking"
                            className="flex items-center gap-1 hover:underline"
                        >
                            <PackageSearch className="size-3.5" /> Track Order
                        </Link>
                        <Link
                            href="/help"
                            className="flex items-center gap-1 border-l border-white/25 pl-3 hover:underline sm:flex"
                        >
                            <CircleHelp className="size-3.5" /> Help Centre
                        </Link>
                    </div>
                </div>
            </div>

            <header
                data-storefront-header
                className="sticky top-0 z-40 border-b border-slate-100 bg-white"
            >
                <div className="storefront-container flex items-center gap-2 py-3 sm:gap-3 lg:gap-5">
                    <Link
                        href={home()}
                        className="shrink-0"
                        aria-label="ProDeals.lk home"
                    >
                        <BrandLogo className="gap-0 [&>img]:h-9 [&>img]:w-28 min-[360px]:[&>img]:w-32 sm:[&>img]:h-10 sm:[&>img]:w-40" />
                    </Link>
                    {!isHomePage && (
                        <div className="hidden shrink-0 lg:block">
                            <DesktopStorefrontCategoryMenu
                                {...categoryMenuProps}
                                compact
                            />
                        </div>
                    )}
                    <Form
                        {...searchRoute.form()}
                        role="search"
                        className="hidden min-w-0 flex-1 md:block"
                    >
                        <label className="flex h-10 overflow-hidden rounded-full border-2 border-[#FF6D00] bg-white focus-within:ring-2 focus-within:ring-orange-100">
                            <span className="sr-only">Search products</span>
                            <input
                                key={search}
                                type="search"
                                name="search"
                                aria-label="Search products"
                                defaultValue={search}
                                placeholder="Search products"
                                className="min-w-0 flex-1 px-4 text-sm outline-none"
                            />
                            <button
                                className="m-0.5 grid w-12 shrink-0 place-items-center rounded-full bg-[#FF6D00] text-white transition hover:bg-[#e86100]"
                                aria-label="Search"
                            >
                                <Search className="size-4" />
                            </button>
                        </label>
                    </Form>
                    <div className="ml-auto hidden shrink-0 items-center gap-1 sm:gap-2 lg:flex">
                        <Link
                            href={auth.user ? buyerOrdersIndex() : login()}
                            aria-label={auth.user ? 'My account' : 'Sign in'}
                            className="flex items-center gap-2 rounded-lg p-2 hover:bg-slate-50"
                        >
                            <UserRound className="size-5" />
                            <span className="hidden text-xs leading-4 lg:block">
                                <span className="block text-slate-500">
                                    My Account
                                </span>
                                <strong>
                                    {auth.user
                                        ? auth.user.name.split(' ')[0]
                                        : 'Sign in'}
                                </strong>
                            </span>
                        </Link>
                        <Link
                            href={auth.user ? '/wishlist' : login().url}
                            className="relative grid size-10 place-items-center rounded-lg hover:bg-slate-50"
                            aria-label="Wishlist"
                        >
                            <Heart className="size-5" />
                            <CountBadge count={commerce.wishlist_count} />
                        </Link>
                        <CartDrawer />
                        <Link
                            hidden={isHomePage}
                            style={isHomePage ? { display: 'none' } : undefined}
                            href={
                                auth.is_seller
                                    ? sellerDashboard()
                                    : sellerRegister()
                            }
                            className="hidden items-center rounded-full bg-[#FF6D00] px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#e86100] xl:inline-flex"
                        >
                            {auth.is_seller
                                ? 'Seller Portal'
                                : 'Become a Seller'}
                        </Link>
                    </div>
                    <div className="ml-auto lg:hidden">
                        <MobileStorefrontCategoryMenu {...categoryMenuProps} />
                    </div>
                </div>

                <nav
                    aria-label="Storefront navigation"
                    className="storefront-container hidden items-center gap-5 pb-2 lg:flex"
                >
                    {isHomePage && (
                        <div className="hidden shrink-0 lg:block">
                            <DesktopStorefrontCategoryMenu
                                {...categoryMenuProps}
                            />
                        </div>
                    )}
                    <div className="flex min-w-0 flex-1 [scrollbar-width:none] items-center gap-5 overflow-x-auto text-sm font-bold whitespace-nowrap lg:justify-between lg:gap-3">
                        {navigation.map(([label, href], index) => (
                            <Link
                                key={href}
                                href={href}
                                className="flex items-center gap-1.5 py-2 hover:text-[#FF6D00]"
                            >
                                {label}
                                {index === 2 && (
                                    <span className="rounded-full bg-[#FF6D00] px-1.5 py-0.5 text-[8px] font-black text-white uppercase">
                                        Hot
                                    </span>
                                )}
                            </Link>
                        ))}
                    </div>
                </nav>
                <div className="px-4 pb-3 sm:px-6 md:hidden">
                    <Form {...searchRoute.form()} role="search">
                        <label className="flex h-10 overflow-hidden rounded-full border-2 border-[#FF6D00] focus-within:ring-2 focus-within:ring-orange-100">
                            <span className="sr-only">Search products</span>
                            <input
                                key={search}
                                type="search"
                                name="search"
                                aria-label="Search products"
                                defaultValue={search}
                                placeholder="Search products"
                                className="min-w-0 flex-1 px-3 text-sm outline-none"
                            />
                            <button
                                className="m-0.5 grid w-12 shrink-0 place-items-center rounded-full bg-[#FF6D00] text-white"
                                aria-label="Search"
                            >
                                <Search className="size-4" />
                            </button>
                        </label>
                    </Form>
                </div>
            </header>
            {children}
            <StorefrontFooter />
            {showMobileNavigation && <StorefrontMobileNavigation />}
        </div>
    );
}
