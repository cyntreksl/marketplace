import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardCheck,
    FolderTree,
    LayoutDashboard,
    Menu,
    Package,
    Plus,
    RotateCcw,
    ShieldCheck,
    Tags,
    ShoppingBag,
    Store,
    WalletCards,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminBrandsIndex } from '@/routes/admin/brands';
import { index as adminCategoriesIndex } from '@/routes/admin/categories';
import { index as adminListingsIndex } from '@/routes/admin/listings';
import { index as adminReturnsIndex } from '@/routes/admin/returns';
import { index as adminSellersIndex } from '@/routes/admin/sellers';
import { index as adminTaxonomyIndex } from '@/routes/admin/taxonomy';
import { index as buyerOrdersIndex } from '@/routes/buyer/orders';
import { index as buyerReturnsIndex } from '@/routes/buyer/returns';
import { show as cartShow } from '@/routes/cart';
import {
    create as sellerListingsCreate,
    index as sellerListingsIndex,
} from '@/routes/seller/listings';
import { edit as sellerOnboardingEdit } from '@/routes/seller/onboarding';
import { index as sellerOrdersIndex } from '@/routes/seller/orders';
import { index as sellerReturnsIndex } from '@/routes/seller/returns';
import { index as sellerWalletIndex } from '@/routes/seller/wallet';

type Portal = 'admin' | 'seller' | 'buyer';

type NavigationItem = {
    title: string;
    href: ReturnType<typeof home>;
    icon: LucideIcon;
};

type PortalDetails = {
    label: string;
    description: string;
    icon: LucideIcon;
    navigation: NavigationItem[];
};

const portalDetails: Record<Portal, PortalDetails> = {
    admin: {
        label: 'Admin workspace',
        description: 'Marketplace operations',
        icon: ShieldCheck,
        navigation: [
            {
                title: 'Overview',
                href: adminDashboard(),
                icon: LayoutDashboard,
            },
            {
                title: 'Seller approvals',
                href: adminSellersIndex(),
                icon: Store,
            },
            {
                title: 'Listing reviews',
                href: adminListingsIndex(),
                icon: ClipboardCheck,
            },
            {
                title: 'Returns & refunds',
                href: adminReturnsIndex(),
                icon: RotateCcw,
            },
            {
                title: 'Categories',
                href: adminCategoriesIndex(),
                icon: FolderTree,
            },
            { title: 'Brands', href: adminBrandsIndex(), icon: Tags },
            {
                title: 'Google taxonomy',
                href: adminTaxonomyIndex(),
                icon: FolderTree,
            },
        ],
    },
    seller: {
        label: 'Seller workspace',
        description: 'Manage your store',
        icon: Store,
        navigation: [
            {
                title: 'Your listings',
                href: sellerListingsIndex(),
                icon: Package,
            },
            {
                title: 'Create listing',
                href: sellerListingsCreate(),
                icon: Plus,
            },
            {
                title: 'Orders',
                href: sellerOrdersIndex(),
                icon: ShoppingBag,
            },
            {
                title: 'Returns',
                href: sellerReturnsIndex(),
                icon: RotateCcw,
            },
            {
                title: 'Wallet',
                href: sellerWalletIndex(),
                icon: WalletCards,
            },
            {
                title: 'Store profile',
                href: sellerOnboardingEdit(),
                icon: Store,
            },
        ],
    },
    buyer: {
        label: 'Buyer workspace',
        description: 'Orders and checkout',
        icon: ShoppingBag,
        navigation: [
            {
                title: 'Your cart',
                href: cartShow(),
                icon: ShoppingBag,
            },
            {
                title: 'Orders',
                href: buyerOrdersIndex(),
                icon: ClipboardCheck,
            },
            {
                title: 'Returns',
                href: buyerReturnsIndex(),
                icon: RotateCcw,
            },
        ],
    },
};

function PortalNavigation({
    portal,
    className,
}: {
    portal: Portal;
    className?: string;
}) {
    const page = usePage();
    const { navigation } = portalDetails[portal];

    return (
        <nav
            className={cn('grid gap-1', className)}
            aria-label="Portal navigation"
        >
            {navigation.map((item) => {
                const isActive = page.url.split('?')[0] === item.href.url;

                return (
                    <Link
                        key={item.title}
                        href={item.href}
                        prefetch
                        className={cn(
                            'flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium transition-colors',
                            isActive
                                ? 'bg-primary text-primary-foreground shadow-lg shadow-primary/20'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
                        )}
                    >
                        <item.icon className="size-4" />
                        <span>{item.title}</span>
                    </Link>
                );
            })}
        </nav>
    );
}

export function PortalLayout({
    children,
    portal,
    title,
}: {
    children: React.ReactNode;
    portal: Portal;
    title: string;
}) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const details = portalDetails[portal];
    const PortalIcon = details.icon;

    return (
        <div
            className={`portal-theme-${portal} min-h-dvh bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50`}
        >
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-72 flex-col border-r border-slate-200 bg-white p-4 lg:flex dark:border-slate-800 dark:bg-slate-950">
                <Link
                    href={home()}
                    className="flex items-center gap-3 rounded-xl px-2 py-2"
                >
                    <BrandLogo showTagline />
                </Link>

                <div className="mt-8 rounded-2xl bg-slate-100 p-3 dark:bg-slate-900">
                    <div className="flex items-center gap-3">
                        <span className="grid size-9 place-items-center rounded-xl bg-white text-primary shadow-sm dark:bg-slate-800">
                            <PortalIcon className="size-4" />
                        </span>
                        <div>
                            <p className="text-sm font-semibold">
                                {details.label}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {details.description}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="mt-6">
                    <p className="px-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                        Workspace
                    </p>
                    <PortalNavigation portal={portal} className="mt-3" />
                </div>

                <div className="mt-auto border-t border-slate-200 pt-4 dark:border-slate-800">
                    <Link
                        href={home()}
                        className="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        <ShoppingBag className="size-4" />
                        Browse marketplace
                    </Link>
                </div>
            </aside>

            <div className="min-h-dvh lg:pl-72">
                <header className="sticky top-0 z-20 flex min-h-16 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-10 dark:border-slate-800 dark:bg-slate-950/90">
                    <div className="flex min-w-0 items-center gap-3">
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    className="size-10 rounded-xl lg:hidden"
                                >
                                    <Menu className="size-5" />
                                    <span className="sr-only">
                                        Open navigation
                                    </span>
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="w-72 p-4">
                                <SheetHeader className="p-0 text-left">
                                    <SheetTitle className="flex items-center gap-3">
                                        <BrandLogo compact />
                                        <span>{details.label}</span>
                                    </SheetTitle>
                                </SheetHeader>
                                <PortalNavigation
                                    portal={portal}
                                    className="mt-8"
                                />
                                <Link
                                    href={home()}
                                    className="mt-auto flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-muted-foreground"
                                >
                                    <ShoppingBag className="size-4" />
                                    Browse marketplace
                                </Link>
                            </SheetContent>
                        </Sheet>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold">
                                {title}
                            </p>
                            <p className="hidden text-xs text-muted-foreground sm:block">
                                {details.label}
                            </p>
                        </div>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                className="h-10 gap-2 rounded-xl px-1.5 sm:px-2"
                            >
                                <Avatar className="size-7 rounded-lg">
                                    <AvatarImage
                                        src={auth.user?.avatar}
                                        alt={auth.user?.name}
                                    />
                                    <AvatarFallback className="rounded-lg bg-primary/10 text-xs font-semibold text-primary">
                                        {getInitials(auth.user?.name ?? '')}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="hidden max-w-28 truncate text-sm font-medium sm:block">
                                    {auth.user?.name}
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-56" align="end">
                            {auth.user && <UserMenuContent user={auth.user} />}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </header>

                <div className="px-4 py-6 sm:px-6 sm:py-8 lg:px-10 lg:py-10">
                    {children}
                </div>
            </div>
        </div>
    );
}
