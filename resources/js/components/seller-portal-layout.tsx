import { Link, usePage } from '@inertiajs/react';
import {
    Boxes,
    CircleDollarSign,
    HelpCircle,
    LayoutDashboard,
    Menu,
    PackageCheck,
    RotateCcw,
    Settings,
    Store,
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
import { index as questionIndex } from '@/routes/product-questions';
import { dashboard } from '@/routes/seller';
import { index as listingsIndex } from '@/routes/seller/listings';
import { edit as onboardingEdit } from '@/routes/seller/onboarding';
import { index as ordersIndex } from '@/routes/seller/orders';
import { index as returnsIndex } from '@/routes/seller/returns';
import { edit as storeEdit } from '@/routes/seller/store';
import { index as walletIndex } from '@/routes/seller/wallet';
import type { Auth } from '@/types';

type SellerRoute = ReturnType<typeof dashboard>;
type PageProps = { auth: Auth };

const groups: {
    label: string;
    items: {
        title: string;
        href: SellerRoute;
        icon: LucideIcon;
        match: string;
        exact?: boolean;
    }[];
}[] = [
    {
        label: 'Operations',
        items: [
            {
                title: 'Overview',
                href: dashboard(),
                icon: LayoutDashboard,
                match: '/seller',
                exact: true,
            },
            {
                title: 'Orders',
                href: ordersIndex(),
                icon: PackageCheck,
                match: '/seller/orders',
            },
        ],
    },
    {
        label: 'Catalog',
        items: [
            {
                title: 'Products',
                href: listingsIndex(),
                icon: Boxes,
                match: '/seller/listings',
            },
            {
                title: 'Customer questions',
                href: questionIndex(),
                icon: HelpCircle,
                match: '/product-questions',
            },
        ],
    },
    {
        label: 'Finance & service',
        items: [
            {
                title: 'Returns',
                href: returnsIndex(),
                icon: RotateCcw,
                match: '/seller/returns',
            },
            {
                title: 'Wallet',
                href: walletIndex(),
                icon: CircleDollarSign,
                match: '/seller/wallet',
            },
        ],
    },
    {
        label: 'Business',
        items: [
            {
                title: 'Storefront',
                href: storeEdit(),
                icon: Store,
                match: '/seller/store',
            },
            {
                title: 'Business profile',
                href: onboardingEdit(),
                icon: Settings,
                match: '/seller/onboarding',
            },
        ],
    },
];

function SellerNavigation({ onNavigate }: { onNavigate?: () => void }) {
    const path = usePage().url.split('?')[0];

    return (
        <nav className="space-y-5" aria-label="Seller portal navigation">
            {groups.map((group) => (
                <div key={group.label}>
                    <p className="mb-1 px-3 text-[0.65rem] font-bold tracking-[0.14em] text-slate-400 uppercase">
                        {group.label}
                    </p>
                    <div className="grid gap-1">
                        {group.items.map((item) => {
                            const active = item.exact
                                ? path === item.match
                                : path.startsWith(item.match);

                            return (
                                <Link
                                    key={item.title}
                                    href={item.href}
                                    prefetch
                                    onClick={onNavigate}
                                    className={cn(
                                        'flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-orange-600',
                                        active
                                            ? 'bg-orange-600 text-white shadow-sm shadow-orange-600/20'
                                            : 'text-slate-600 hover:bg-orange-50 hover:text-orange-700 dark:text-slate-300 dark:hover:bg-orange-500/10 dark:hover:text-orange-300',
                                    )}
                                >
                                    <item.icon
                                        className="size-[1.125rem]"
                                        aria-hidden
                                    />
                                    {item.title}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            ))}
        </nav>
    );
}

export function SellerPortalLayout({
    children,
    title,
}: {
    children: React.ReactNode;
    title: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const initials = useInitials();

    return (
        <div className="min-h-dvh bg-[#f8f7f4] text-slate-950 dark:bg-slate-950 dark:text-slate-50">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-[280px] flex-col border-r border-slate-200/80 bg-white px-5 py-6 lg:flex dark:border-slate-800 dark:bg-slate-950">
                <Link href={home()} className="rounded-xl px-1 py-1">
                    <BrandLogo showTagline />
                </Link>
                <div className="mt-7 flex-1 overflow-y-auto">
                    <SellerNavigation />
                </div>
                <div className="mt-5 rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 p-4 text-white shadow-lg shadow-orange-600/15">
                    <p className="text-sm font-bold">Grow your storefront</p>
                    <p className="mt-1 text-xs leading-5 text-orange-50">
                        Keep products current and dispatch orders on time.
                    </p>
                    <Link
                        href={storeEdit()}
                        className="mt-3 inline-flex min-h-10 items-center rounded-xl bg-white px-3 text-sm font-bold text-orange-700"
                    >
                        View storefront
                    </Link>
                </div>
            </aside>
            <div className="min-h-dvh lg:pl-[280px]">
                <header className="sticky top-0 z-20 flex min-h-16 items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl sm:px-6 lg:px-10 dark:border-slate-800 dark:bg-slate-950/90">
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
                                        Open seller navigation
                                    </span>
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="left"
                                className="flex w-80 flex-col p-5"
                            >
                                <SheetHeader className="p-0 text-left">
                                    <SheetTitle>
                                        <BrandLogo showTagline />
                                    </SheetTitle>
                                </SheetHeader>
                                <div className="mt-8 overflow-y-auto">
                                    <SellerNavigation />
                                </div>
                            </SheetContent>
                        </Sheet>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-bold">
                                {title}
                            </p>
                            <p className="hidden text-xs text-slate-500 sm:block">
                                Seller portal
                            </p>
                        </div>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                className="h-10 gap-2 rounded-xl px-1.5 sm:px-2"
                            >
                                <Avatar className="size-8 rounded-lg">
                                    <AvatarImage
                                        src={auth.user.avatar}
                                        alt={auth.user.name}
                                    />
                                    <AvatarFallback className="rounded-lg bg-orange-100 text-xs font-bold text-orange-700 dark:bg-orange-500/15 dark:text-orange-300">
                                        {initials(auth.user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="hidden max-w-32 truncate text-sm font-semibold sm:block">
                                    {auth.user.name}
                                </span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-56" align="end">
                            <UserMenuContent
                                user={auth.user}
                                settingsHref={onboardingEdit()}
                            />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </header>
                <main className="mx-auto w-full max-w-[1500px] px-4 py-7 sm:px-6 sm:py-9 lg:px-10 lg:py-10">
                    {children}
                </main>
            </div>
        </div>
    );
}
