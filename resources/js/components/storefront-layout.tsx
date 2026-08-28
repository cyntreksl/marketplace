import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Armchair,
    Baby,
    BookOpen,
    BriefcaseBusiness,
    Camera,
    Car,
    ChevronRight,
    Church,
    Dumbbell,
    FileText,
    Gamepad2,
    Globe2,
    Headphones,
    HeartPulse,
    House,
    Luggage,
    Menu,
    Monitor,
    PackageSearch,
    Palette,
    PawPrint,
    Puzzle,
    Search,
    Shirt,
    ShoppingBag,
    Sparkles,
    Utensils,
    UserRound,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import { dashboard, home, login, register } from '@/routes';
import { show as cartShow } from '@/routes/cart';
import { index as listingsIndex } from '@/routes/listings';
import { edit as sellerOnboardingEdit } from '@/routes/seller/onboarding';
import { register as vendorRegister } from '@/routes/vendor';

export type StorefrontCategory = {
    id: number;
    name: string;
    slug: string;
};

const categoryIcons: Record<string, LucideIcon> = {
    'animals-pet-supplies': PawPrint,
    'apparel-accessories': Shirt,
    'arts-entertainment': Palette,
    'baby-toddler': Baby,
    'business-industrial': BriefcaseBusiness,
    'cameras-optics': Camera,
    electronics: Monitor,
    'food-beverages-tobacco': Utensils,
    furniture: Armchair,
    hardware: Wrench,
    'health-beauty': HeartPulse,
    'home-garden': House,
    'luggage-bags': Luggage,
    media: BookOpen,
    'office-supplies': FileText,
    'religious-ceremonial': Church,
    software: Gamepad2,
    'sporting-goods': Dumbbell,
    'toys-games': Puzzle,
    'vehicles-parts': Car,
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
    const page = usePage();
    const { auth } = page.props;
    const selectedCategorySlug = new URLSearchParams(
        page.url.split('?')[1] ?? '',
    ).get('category');
    const isAllProductsSelected =
        page.url.split('?')[0] === listingsIndex.url() &&
        selectedCategorySlug === null;
    const navigationCategories = categories.map((category) => ({
        ...category,
        href: listingsIndex({ query: { category: category.slug } }),
        icon: categoryIcons[category.slug] ?? PackageSearch,
    }));

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50">
                <aside
                    aria-label="Marketplace navigation"
                    className="fixed inset-y-0 left-0 z-30 hidden w-72 flex-col border-r border-slate-200 bg-white shadow-xl shadow-slate-950/5 lg:flex dark:border-slate-800 dark:bg-slate-950"
                >
                    <div className="border-b border-slate-200 p-4 dark:border-slate-800">
                        <p className="text-xs font-black tracking-[0.16em] text-primary uppercase">
                            Shop by category
                        </p>
                        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {navigationCategories.length} ways to find your next
                            deal
                        </p>
                        <Link
                            href={listingsIndex()}
                            aria-current={
                                isAllProductsSelected ? 'page' : undefined
                            }
                            className={`mt-4 flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-bold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-offset-slate-950 ${
                                isAllProductsSelected
                                    ? 'bg-primary text-primary-foreground shadow-md shadow-primary/20'
                                    : 'bg-primary/10 text-primary hover:bg-primary/15 dark:bg-primary/15'
                            }`}
                        >
                            <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-white/15">
                                <Menu className="size-4" />
                            </span>
                            <span>All products</span>
                            <ChevronRight className="ml-auto size-4" />
                        </Link>
                    </div>
                    <nav
                        className="flex-1 overflow-y-auto px-3 py-3"
                        aria-label="Marketplace categories"
                    >
                        <div className="flex flex-col gap-1">
                            {navigationCategories.map((category) => {
                                const CategoryIcon = category.icon;
                                const isSelected =
                                    selectedCategorySlug === category.slug;

                                return (
                                    <Link
                                        href={category.href}
                                        key={category.id}
                                        aria-current={
                                            isSelected ? 'page' : undefined
                                        }
                                        className={`group flex min-h-12 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none dark:focus-visible:ring-offset-slate-950 ${
                                            isSelected
                                                ? 'bg-primary/10 text-primary dark:bg-primary/15'
                                                : 'text-slate-700 hover:bg-slate-100 hover:text-primary dark:text-slate-200 dark:hover:bg-slate-900'
                                        }`}
                                    >
                                        <span
                                            className={`grid size-8 shrink-0 place-items-center rounded-lg transition ${
                                                isSelected
                                                    ? 'bg-primary text-primary-foreground shadow-sm shadow-primary/20'
                                                    : 'bg-slate-100 text-slate-600 group-hover:bg-primary/10 group-hover:text-primary dark:bg-slate-900 dark:text-slate-300'
                                            }`}
                                        >
                                            <CategoryIcon className="size-4" />
                                        </span>
                                        <span className="min-w-0 flex-1 leading-5">
                                            {category.name}
                                        </span>
                                        <ChevronRight
                                            className={`size-4 shrink-0 ${
                                                isSelected
                                                    ? 'text-primary'
                                                    : 'text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-primary dark:text-slate-600'
                                            }`}
                                        />
                                    </Link>
                                );
                            })}
                        </div>
                    </nav>
                    <div className="border-t border-slate-200 p-3 dark:border-slate-800">
                        <Link
                            href={listingsIndex({
                                query: { listing_type: 'auction' },
                            })}
                            className="flex min-h-12 items-center gap-3 rounded-xl bg-slate-950 px-3 text-sm font-bold text-white transition hover:bg-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none dark:bg-slate-900 dark:hover:bg-primary dark:focus-visible:ring-offset-slate-950"
                        >
                            <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-white/10 text-cyan-300">
                                <Sparkles className="size-4" />
                            </span>
                            <span>Browse live auctions</span>
                            <ChevronRight className="ml-auto size-4 text-slate-400" />
                        </Link>
                    </div>
                </aside>
                <header className="sticky top-0 z-20 bg-white shadow-sm lg:pl-72 dark:bg-slate-950">
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
                    <div className="border-y border-primary/20 bg-primary/10 lg:hidden dark:border-slate-800 dark:bg-slate-900">
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
                            {navigationCategories.map((category) => (
                                <Link
                                    href={category.href}
                                    key={category.id}
                                    aria-current={
                                        selectedCategorySlug === category.slug
                                            ? 'page'
                                            : undefined
                                    }
                                    className="shrink-0 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-white hover:text-primary dark:text-slate-300 dark:hover:bg-slate-800"
                                >
                                    {category.name}
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
                <div className="lg:pl-72">{children}</div>
                <footer className="border-t border-primary/20 bg-white py-10 lg:pl-72 dark:border-slate-800 dark:bg-slate-950">
                    <div className="mx-auto flex max-w-7xl flex-col justify-between gap-3 px-4 text-sm text-slate-500 sm:flex-row sm:px-6 lg:px-8">
                        <p>Better deals. Closer to home.</p>
                        <p>Discover · Compare · Make it yours</p>
                    </div>
                </footer>
            </div>
        </>
    );
}
