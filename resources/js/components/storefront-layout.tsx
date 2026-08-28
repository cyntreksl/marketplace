import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Box,
    ChevronRight,
    Cpu,
    Gamepad2,
    Globe2,
    Headphones,
    House,
    Menu,
    PackageSearch,
    Search,
    ShoppingBag,
    Smartphone,
    UserRound,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { BrandLogo } from '@/components/brand-logo';
import { dashboard, home, login, register } from '@/routes';
import { show as cartShow } from '@/routes/cart';
import { index as listingsIndex } from '@/routes/listings';
import { edit as sellerOnboardingEdit } from '@/routes/seller/onboarding';
import { register as vendorRegister } from '@/routes/vendor';

type StorefrontCategory = {
    id: number;
    name: string;
    slug: string;
};

export function StorefrontLayout({
    children,
    title,
    categories = [],
}: {
    children: React.ReactNode;
    title: string;
    categories?: StorefrontCategory[];
}) {
    const { auth } = usePage().props;
    const [isCategoryMenuOpen, setIsCategoryMenuOpen] = useState(false);
    const categoryIcons: LucideIcon[] = [Smartphone, Cpu, House, Gamepad2, Box];
    const navigationCategories = categories.length
        ? categories.slice(0, 10).map((category, index) => ({
              ...category,
              href: listingsIndex({ query: { category: category.slug } }),
              icon: categoryIcons[index % categoryIcons.length],
          }))
        : [
              {
                  name: 'Home & living',
                  href: listingsIndex(),
                  icon: House,
              },
              {
                  name: 'Fashion & accessories',
                  href: listingsIndex(),
                  icon: UserRound,
              },
              {
                  name: 'Computers',
                  href: listingsIndex(),
                  icon: Cpu,
              },
              {
                  name: 'Gaming',
                  href: listingsIndex(),
                  icon: Gamepad2,
              },
              {
                  name: 'Mobile hub',
                  href: listingsIndex(),
                  icon: Smartphone,
              },
              {
                  name: 'Electronics & gadgets',
                  href: listingsIndex(),
                  icon: Box,
              },
              {
                  name: 'Browse products',
                  href: listingsIndex(),
                  icon: PackageSearch,
              },
              {
                  name: 'Live auctions',
                  href: listingsIndex({
                      query: { listing_type: 'auction' },
                  }),
                  icon: ChevronRight,
              },
          ];

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50">
                <aside
                    aria-label="Marketplace navigation"
                    className={`fixed inset-y-0 left-0 z-30 hidden overflow-hidden border-r border-slate-200 bg-white shadow-xl shadow-slate-950/5 transition-[width] duration-300 lg:block dark:border-slate-800 dark:bg-slate-950 ${
                        isCategoryMenuOpen ? 'w-[22.75rem]' : 'w-20'
                    }`}
                    onMouseEnter={() => setIsCategoryMenuOpen(true)}
                    onMouseLeave={() => setIsCategoryMenuOpen(false)}
                    onFocusCapture={() => setIsCategoryMenuOpen(true)}
                    onBlur={(event) => {
                        if (
                            !event.currentTarget.contains(event.relatedTarget)
                        ) {
                            setIsCategoryMenuOpen(false);
                        }
                    }}
                >
                    {isCategoryMenuOpen ? (
                        <nav
                            className="h-full overflow-y-auto px-3 py-3"
                            aria-label="Marketplace categories"
                        >
                            <button
                                type="button"
                                onClick={() => setIsCategoryMenuOpen(false)}
                                aria-expanded="true"
                                className="flex h-12 w-full items-center gap-3 rounded-full bg-primary px-4 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/25 transition hover:bg-primary/90 focus:bg-primary/90"
                            >
                                <Menu className="size-6 shrink-0" />
                                All categories
                            </button>
                            <div className="mt-4 space-y-1">
                                {navigationCategories.map((category) => {
                                    const CategoryIcon = category.icon;

                                    return (
                                        <Link
                                            href={category.href}
                                            key={category.name}
                                            className="flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold text-slate-700 transition hover:bg-primary/10 hover:text-primary focus:bg-primary/10 focus:text-primary dark:text-slate-200 dark:hover:bg-slate-900 dark:focus:bg-slate-900"
                                        >
                                            <CategoryIcon className="size-5 shrink-0 text-slate-900 dark:text-slate-100" />
                                            <span>{category.name}</span>
                                            <ChevronRight className="ml-auto size-4 text-slate-400" />
                                        </Link>
                                    );
                                })}
                            </div>
                        </nav>
                    ) : (
                        <div className="flex h-full flex-col items-center py-3">
                            <button
                                type="button"
                                onClick={() => setIsCategoryMenuOpen(true)}
                                aria-label="Browse categories"
                                aria-expanded="false"
                                className="grid size-12 place-items-center rounded-full bg-primary text-primary-foreground shadow-lg shadow-primary/25 transition hover:bg-primary/90 focus:bg-primary/90"
                            >
                                <Menu className="size-6" />
                            </button>
                            <nav
                                className="mt-5 flex flex-col gap-2"
                                aria-label="Quick links"
                            >
                                {[
                                    [home(), House, 'Home'],
                                    [listingsIndex(), UserRound, 'Account'],
                                    [listingsIndex(), Box, 'Products'],
                                    [
                                        listingsIndex({
                                            query: { listing_type: 'auction' },
                                        }),
                                        Gamepad2,
                                        'Auctions',
                                    ],
                                    [
                                        listingsIndex(),
                                        Smartphone,
                                        'Mobile devices',
                                    ],
                                    [listingsIndex(), Cpu, 'Computers'],
                                    [cartShow(), ShoppingBag, 'Cart'],
                                ].map(([href, Icon, label]) => {
                                    const RailIcon = Icon as LucideIcon;

                                    return (
                                        <Link
                                            href={
                                                href as ReturnType<typeof home>
                                            }
                                            key={label as string}
                                            aria-label={label as string}
                                            className="grid size-11 place-items-center rounded-xl text-slate-600 transition hover:bg-primary/10 hover:text-primary focus:bg-primary/10 focus:text-primary dark:text-slate-300 dark:hover:bg-slate-900 dark:focus:bg-slate-900"
                                        >
                                            <RailIcon className="size-5" />
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>
                    )}
                </aside>
                <header
                    className={`sticky top-0 z-20 bg-white shadow-sm transition-[padding] duration-300 dark:bg-slate-950 ${
                        isCategoryMenuOpen ? 'lg:pl-[22.75rem]' : 'lg:pl-20'
                    }`}
                >
                    <nav
                        className="mx-auto flex max-w-none items-center gap-5 px-4 py-4 sm:px-7"
                        aria-label="Main navigation"
                    >
                        <Link
                            href={home()}
                            className="flex shrink-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <BrandLogo className="text-2xl" />
                        </Link>
                        <Form
                            {...listingsIndex.form()}
                            className="hidden min-w-0 flex-1 lg:block"
                        >
                            <label className="relative block">
                                <span className="sr-only">Search products</span>
                                <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    name="search"
                                    placeholder="Search deals, brands and more"
                                    className="h-11 w-full rounded-full border border-slate-200 bg-slate-50 py-2 pr-12 pl-11 text-sm transition outline-none placeholder:text-slate-400 focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/20 dark:border-slate-700 dark:bg-slate-900 dark:focus:bg-slate-950"
                                />
                                <button
                                    type="submit"
                                    className="absolute top-1.5 right-1.5 grid size-8 place-items-center rounded-full bg-primary text-primary-foreground transition hover:bg-primary/90"
                                    aria-label="Search products"
                                >
                                    <ChevronRight className="size-4" />
                                </button>
                            </label>
                        </Form>
                        <div className="ml-auto hidden items-center gap-6 xl:flex">
                            <div className="flex items-center gap-2 text-sm">
                                <Headphones className="size-7 text-primary" />
                                <span>
                                    <span className="block font-bold">
                                        Browse with ease
                                    </span>
                                    <span className="text-primary">
                                        Find something for every day
                                    </span>
                                </span>
                            </div>
                            <div className="flex items-center gap-2 text-sm">
                                <Globe2 className="size-7 text-primary" />
                                <span>
                                    <span className="block font-bold">
                                        Made for Sri Lanka
                                    </span>
                                    <span className="text-primary">
                                        Discover local marketplace finds
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div className="flex items-center gap-1 text-sm font-semibold">
                            <Link
                                className="hidden items-center gap-1 rounded-full px-3 py-2 text-slate-600 transition hover:bg-primary/10 hover:text-primary sm:flex dark:text-slate-300 dark:hover:bg-slate-900"
                                href={
                                    auth.user
                                        ? sellerOnboardingEdit()
                                        : vendorRegister()
                                }
                            >
                                Become a vendor
                            </Link>
                            <Link
                                className="hidden items-center gap-1 rounded-full px-3 py-2 text-slate-600 transition hover:bg-primary/10 hover:text-primary sm:flex dark:text-slate-300 dark:hover:bg-slate-900"
                                href={listingsIndex({
                                    query: { listing_type: 'auction' },
                                })}
                            >
                                Auctions
                            </Link>
                            {auth.user ? (
                                <>
                                    <Link
                                        className="grid size-10 place-items-center rounded-full text-slate-600 transition hover:bg-primary/10 hover:text-primary dark:text-slate-300 dark:hover:bg-slate-900"
                                        href={cartShow()}
                                        aria-label="Shopping bag"
                                    >
                                        <ShoppingBag className="size-5" />
                                    </Link>
                                    <Link
                                        className="hidden items-center gap-2 rounded-full bg-primary px-4 py-2 text-primary-foreground shadow-sm shadow-primary/25 transition hover:bg-primary/90 sm:flex"
                                        href={dashboard()}
                                    >
                                        <UserRound className="size-4" />
                                        Dashboard
                                    </Link>
                                </>
                            ) : (
                                <>
                                    <Link
                                        className="grid size-10 place-items-center rounded-full text-slate-600 transition hover:bg-primary/10 hover:text-primary sm:hidden dark:text-slate-300 dark:hover:bg-slate-900"
                                        href={login()}
                                        aria-label="Sign in"
                                    >
                                        <UserRound className="size-5" />
                                    </Link>
                                    <Link
                                        className="hidden px-3 py-2 text-slate-600 hover:text-primary sm:block dark:text-slate-300"
                                        href={login()}
                                    >
                                        Sign in
                                    </Link>
                                    <Link
                                        className="hidden rounded-full bg-primary px-4 py-2 text-primary-foreground shadow-sm shadow-primary/25 transition hover:bg-primary/90 sm:block"
                                        href={register()}
                                    >
                                        Join now
                                    </Link>
                                </>
                            )}
                        </div>
                    </nav>
                    <div className="border-y border-primary/20 bg-primary/10 dark:border-slate-800 dark:bg-slate-900">
                        <div className="mx-auto flex max-w-none items-center gap-1 overflow-x-auto px-4 py-2 sm:px-7">
                            <Link
                                href={listingsIndex()}
                                className="flex shrink-0 items-center gap-2 rounded-full bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm dark:bg-slate-800 dark:text-slate-100"
                            >
                                <span className="grid size-7 place-items-center rounded-full bg-primary text-primary-foreground">
                                    <Menu className="size-4" />
                                </span>
                                All categories
                            </Link>
                            {[
                                'Mobile hub',
                                'Computers',
                                'Home & living',
                                'Gaming',
                                'Accessories',
                            ].map((category) => (
                                <Link
                                    href={listingsIndex()}
                                    key={category}
                                    className="shrink-0 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-white hover:text-primary dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    {category}
                                </Link>
                            ))}
                            <Link
                                href={cartShow()}
                                className="ml-auto hidden shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-white hover:text-primary sm:flex dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                <ShoppingBag className="size-4" />
                                Cart
                            </Link>
                        </div>
                    </div>
                </header>
                <div
                    className={`transition-[padding] duration-300 ${
                        isCategoryMenuOpen ? 'lg:pl-[22.75rem]' : 'lg:pl-20'
                    }`}
                >
                    {children}
                </div>
                <footer
                    className={`border-t border-primary/20 bg-white py-10 transition-[padding] duration-300 dark:border-slate-800 dark:bg-slate-950 ${
                        isCategoryMenuOpen ? 'lg:pl-[22.75rem]' : 'lg:pl-20'
                    }`}
                >
                    <div className="mx-auto flex max-w-7xl flex-col justify-between gap-3 px-4 text-sm text-slate-500 sm:flex-row sm:px-6 lg:px-8">
                        <p>Better deals. Closer to home.</p>
                        <p>Discover · Compare · Make it yours</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
