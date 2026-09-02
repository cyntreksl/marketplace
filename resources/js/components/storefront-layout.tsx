import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    CircleHelp,
    Heart,
    MapPin,
    PackageSearch,
    Phone,
    Search,
    ShoppingCart,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import { BrandLogo } from '@/components/brand-logo';
import { MobileStorefrontCategoryMenu } from '@/components/storefront-category-menu';
import type { StorefrontCategory } from '@/components/storefront-category-menu';
import { StorefrontFooter } from '@/components/storefront-footer';
import { home, login } from '@/routes';
import { index as buyerOrdersIndex } from '@/routes/buyer/orders';
import { show as cartShow } from '@/routes/cart';
import { index as listingsIndex } from '@/routes/listings';

export type { StorefrontCategory } from '@/components/storefront-category-menu';

const deliveryStorageKey = 'prodeals.deliveryLocation';

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
    title,
    description,
    categories = [],
}: {
    children: React.ReactNode;
    title: string;
    description?: string | null;
    categories?: StorefrontCategory[];
    activeCategorySlugs?: string[];
}) {
    const { auth, commerce, marketplace } = usePage().props;
    const locations = marketplace.storefront.delivery_locations;
    const [location, setLocation] = useState(() => {
        if (typeof window !== 'undefined') {
            const saved = window.localStorage.getItem(deliveryStorageKey);

            if (saved && locations.includes(saved)) {
                return saved;
            }
        }

        return locations[0] ?? 'Colombo 03';
    });

    const setDeliveryLocation = (value: string) => {
        setLocation(value);
        window.localStorage.setItem(deliveryStorageKey, value);
    };

    const navigation = [
        ['Today’s Deals', '/collections/deals'],
        ['Best Sellers', '/collections/best-sellers'],
        ['New Arrivals', '/collections/new-arrivals'],
        ['Brands', '/brands'],
        ['Clearance Sale', '/collections/clearance'],
    ] as const;

    return (
        <div className="min-h-screen bg-white text-slate-950">
            <Head title={title}>
                {description && (
                    <meta name="description" content={description} />
                )}
            </Head>
            <header className="relative z-40 border-b border-slate-100 bg-white">
                <div className="bg-[#FF6D00] text-white">
                    <div className="mx-auto flex min-h-10 max-w-[82rem] items-center justify-between gap-4 px-4 text-[11px] sm:px-6">
                        <div className="flex min-w-0 items-center gap-3 sm:gap-8">
                            <label className="flex min-w-0 items-center gap-1.5 font-semibold">
                                <MapPin className="size-4 shrink-0" />
                                <span className="hidden sm:inline">
                                    Deliver to
                                </span>
                                <select
                                    value={location}
                                    onChange={(event) =>
                                        setDeliveryLocation(event.target.value)
                                    }
                                    className="max-w-28 truncate bg-transparent font-bold outline-none sm:max-w-36"
                                    aria-label="Delivery location"
                                >
                                    {locations.map((item) => (
                                        <option
                                            key={item}
                                            value={item}
                                            className="text-slate-900"
                                        >
                                            {item}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <span className="hidden items-center gap-1.5 border-l border-white/25 pl-8 font-semibold md:flex">
                                LKR · Sri Lankan Rupee
                                <ChevronDown className="size-3" />
                            </span>
                        </div>
                        <div className="flex items-center gap-4 font-semibold">
                            <Link
                                href="/help"
                                className="flex items-center gap-1 hover:underline"
                            >
                                <CircleHelp className="size-3.5" /> Help Center
                            </Link>
                            <Link
                                href="/order-tracking"
                                className="hidden items-center gap-1 hover:underline sm:flex"
                            >
                                <PackageSearch className="size-3.5" /> Order
                                Tracking
                            </Link>
                        </div>
                    </div>
                </div>

                <div className="mx-auto flex max-w-[82rem] items-center gap-3 px-4 py-3 sm:px-6 lg:gap-5 lg:py-4">
                    <div className="lg:hidden">
                        <MobileStorefrontCategoryMenu
                            categories={categories}
                            selectedCategorySlug={null}
                            isAllProductsSelected={false}
                        />
                    </div>
                    <Link
                        href={home()}
                        className="shrink-0"
                        aria-label="ProDeals.lk home"
                    >
                        <BrandLogo className="scale-90 sm:scale-100" />
                    </Link>
                    <Form
                        {...listingsIndex.form()}
                        className="hidden min-w-0 flex-1 md:block"
                    >
                        <label className="flex h-11 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm focus-within:border-[#FF6D00] focus-within:ring-2 focus-within:ring-orange-100">
                            <span className="sr-only">Search products</span>
                            <input
                                name="search"
                                placeholder="Search for phones, laptops, TVs and more..."
                                className="min-w-0 flex-1 px-4 text-sm outline-none"
                            />
                            <select
                                name="category"
                                aria-label="Search category"
                                className="hidden border-l border-slate-100 bg-white px-3 text-xs text-slate-500 outline-none lg:block"
                            >
                                <option value="">All Categories</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.slug}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                            <button
                                className="m-1 grid w-10 place-items-center rounded-md bg-[#FF6D00] text-white transition hover:bg-[#e86100]"
                                aria-label="Search"
                            >
                                <Search className="size-4" />
                            </button>
                        </label>
                    </Form>
                    <div className="ml-auto flex items-center gap-1 sm:gap-3">
                        <Link
                            href={auth.user ? buyerOrdersIndex() : login()}
                            className="hidden items-center gap-2 rounded-lg p-2 hover:bg-slate-50 sm:flex"
                        >
                            <UserRound className="size-5" />
                            <span className="hidden text-[11px] leading-4 lg:block">
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
                        <Link
                            href={auth.user ? cartShow() : login()}
                            className="relative flex items-center gap-2 rounded-lg p-2 hover:bg-slate-50"
                            aria-label="Cart"
                        >
                            <ShoppingCart className="size-5" />
                            <CountBadge count={commerce.cart_quantity} />
                            <span className="hidden text-xs font-bold lg:block">
                                Cart
                            </span>
                        </Link>
                        {marketplace.support.phone ? (
                            <a
                                href={`tel:${marketplace.support.phone}`}
                                className="hidden items-center gap-2 rounded-full border border-slate-200 py-1.5 pr-4 pl-1.5 text-xs font-bold shadow-sm xl:flex"
                            >
                                <span className="grid size-8 place-items-center rounded-full bg-[#FF6D00] text-white">
                                    <Phone className="size-4" />
                                </span>
                                <span>
                                    <span className="block">
                                        {marketplace.support.phone}
                                    </span>
                                    <span className="block text-[9px] font-medium text-slate-500">
                                        Call to Order
                                    </span>
                                </span>
                            </a>
                        ) : (
                            <button
                                disabled
                                title="Call-to-order is not configured"
                                className="hidden cursor-not-allowed rounded-full border px-4 py-2 text-xs text-slate-400 xl:block"
                            >
                                Call to Order
                            </button>
                        )}
                    </div>
                </div>

                <div className="mx-auto max-w-[82rem] px-4 pb-3 sm:px-6">
                    <div className="flex [scrollbar-width:none] items-center gap-5 overflow-x-auto text-xs font-bold whitespace-nowrap lg:justify-between lg:gap-3">
                        <Link
                            href={listingsIndex()}
                            className="rounded-lg border border-slate-200 px-4 py-2.5 shadow-sm hover:border-[#FF6D00] hover:text-[#FF6D00]"
                        >
                            ☰ &nbsp; All Categories
                        </Link>
                        {navigation.map(([label, href], index) => (
                            <Link
                                key={href}
                                href={href}
                                className="flex items-center gap-1.5 py-2 hover:text-[#FF6D00]"
                            >
                                {label}
                                {index === 0 && (
                                    <span className="rounded-full bg-[#FF6D00] px-1.5 py-0.5 text-[8px] font-black text-white uppercase">
                                        Hot
                                    </span>
                                )}
                                {index === 4 && (
                                    <span className="rounded-full bg-red-500 px-1.5 py-0.5 text-[8px] font-black text-white uppercase">
                                        Sale
                                    </span>
                                )}
                            </Link>
                        ))}
                        <button
                            disabled
                            title="Installments are not configured"
                            className="cursor-not-allowed py-2 text-slate-400"
                        >
                            Installment Plans
                        </button>
                        <button
                            disabled
                            title="Business deals are not configured"
                            className="cursor-not-allowed py-2 text-slate-400"
                        >
                            Business Deals
                        </button>
                    </div>
                    <Form {...listingsIndex.form()} className="mt-3 md:hidden">
                        <label className="flex h-10 rounded-lg border border-slate-200">
                            <span className="sr-only">Search products</span>
                            <input
                                name="search"
                                placeholder="Search products"
                                className="min-w-0 flex-1 px-3 text-sm outline-none"
                            />
                            <button
                                className="grid w-10 place-items-center text-[#FF6D00]"
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
        </div>
    );
}
