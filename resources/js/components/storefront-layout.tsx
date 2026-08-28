import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    Globe2,
    Headphones,
    Search,
    ShoppingBag,
    UserRound,
} from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import {
    categoryContainsSlug,
    DesktopStorefrontCategoryMenu,
    MobileStorefrontCategoryMenu,
} from '@/components/storefront-category-menu';
import type { StorefrontCategory } from '@/components/storefront-category-menu';
import { StorefrontFooter } from '@/components/storefront-footer';
import { home, login, register } from '@/routes';
import { index as buyerOrdersIndex } from '@/routes/buyer/orders';
import { show as cartShow } from '@/routes/cart';
import { index as listingsIndex } from '@/routes/listings';
import { register as sellerRegister } from '@/routes/seller';
import { index as sellerListingsIndex } from '@/routes/seller/listings';
import { edit as sellerOnboardingEdit } from '@/routes/seller/onboarding';

export type { StorefrontCategory } from '@/components/storefront-category-menu';

export function StorefrontLayout({
    children,
    title,
    categories = [],
    activeCategorySlugs = [],
}: {
    children: React.ReactNode;
    title: string;
    categories?: StorefrontCategory[];
    activeCategorySlugs?: string[];
}) {
    const page = usePage();
    const { auth } = page.props;
    const requestedCategorySlug = new URLSearchParams(
        page.url.split('?')[1] ?? '',
    ).get('category');
    const requestedCategoryIsVisible = categories.some((category) =>
        categoryContainsSlug(category, requestedCategorySlug),
    );
    const selectedCategorySlug = requestedCategoryIsVisible
        ? requestedCategorySlug
        : ([...activeCategorySlugs]
              .reverse()
              .find((slug) =>
                  categories.some((category) =>
                      categoryContainsSlug(category, slug),
                  ),
              ) ?? null);
    const isAllProductsSelected =
        page.url.split('?')[0] === listingsIndex.url() &&
        requestedCategorySlug === null;

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50">
                <header className="sticky top-0 z-40 bg-white shadow-sm dark:bg-slate-950">
                    <nav
                        className="mx-auto grid max-w-none grid-cols-[2.5rem_minmax(0,1fr)_2.5rem] items-center gap-2 px-4 py-3 sm:flex sm:gap-5 sm:px-7 sm:py-4"
                        aria-label="Main navigation"
                    >
                        <MobileStorefrontCategoryMenu
                            categories={categories}
                            selectedCategorySlug={selectedCategorySlug}
                            isAllProductsSelected={isAllProductsSelected}
                        />
                        <Link
                            href={home()}
                            className="mx-auto flex min-w-0 rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none sm:mx-0 sm:shrink-0"
                        >
                            <BrandLogo className="text-xl min-[400px]:text-2xl" />
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
                                    className="absolute top-1.5 right-1.5 grid size-8 place-items-center rounded-full bg-primary text-primary-foreground transition hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none"
                                    aria-label="Search products"
                                >
                                    <ChevronRight className="size-4" />
                                </button>
                            </label>
                        </Form>
                        <div className="ml-auto hidden items-center gap-6 2xl:flex">
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
                        <div className="flex items-center justify-end gap-1 text-sm font-semibold sm:ml-auto">
                            <Link
                                className="hidden items-center gap-1 rounded-full px-3 py-2 text-slate-600 transition hover:bg-primary/10 hover:text-primary sm:flex dark:text-slate-300 dark:hover:bg-slate-900"
                                href={
                                    auth.is_seller
                                        ? sellerListingsIndex()
                                        : auth.user
                                          ? sellerOnboardingEdit()
                                          : sellerRegister()
                                }
                            >
                                {auth.is_seller
                                    ? 'Seller portal'
                                    : 'Become a seller'}
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
                                        href={buyerOrdersIndex()}
                                    >
                                        <UserRound className="size-4" />
                                        My orders
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
                    <div className="relative hidden border-y border-primary/20 bg-primary/10 lg:block dark:border-slate-800 dark:bg-slate-900">
                        <div className="mx-auto flex max-w-none items-center gap-1 px-7 py-2">
                            <DesktopStorefrontCategoryMenu
                                categories={categories}
                                selectedCategorySlug={selectedCategorySlug}
                                isAllProductsSelected={isAllProductsSelected}
                            />
                            {categories.slice(0, 5).map((category) => {
                                const isSelected = categoryContainsSlug(
                                    category,
                                    selectedCategorySlug,
                                );

                                return (
                                    <Link
                                        key={category.id}
                                        href={listingsIndex({
                                            query: {
                                                category: category.slug,
                                            },
                                        })}
                                        aria-current={
                                            category.slug ===
                                            selectedCategorySlug
                                                ? 'page'
                                                : undefined
                                        }
                                        className={`rounded-full px-4 py-2 text-sm font-bold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none ${
                                            isSelected
                                                ? 'bg-white text-primary shadow-sm dark:bg-slate-800'
                                                : 'text-slate-600 hover:bg-white hover:text-primary dark:text-slate-300 dark:hover:bg-slate-800'
                                        }`}
                                    >
                                        {category.name}
                                    </Link>
                                );
                            })}
                            <Link
                                href={cartShow()}
                                className="ml-auto flex items-center gap-2 rounded-full px-4 py-2 text-sm font-bold text-slate-600 transition hover:bg-white hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                <ShoppingBag className="size-4" />
                                Cart
                            </Link>
                        </div>
                    </div>
                </header>
                <div>{children}</div>
                <StorefrontFooter />
            </div>
        </>
    );
}
