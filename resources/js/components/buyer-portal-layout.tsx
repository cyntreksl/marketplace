import { Link, usePage } from '@inertiajs/react';
import {
    CreditCard,
    LayoutDashboard,
    MapPin,
    Menu,
    MessageSquareText,
    Package,
    RotateCcw,
    Settings,
    ShoppingCart,
    Gavel,
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
import { dashboard } from '@/routes/buyer';
import { index as addressesIndex } from '@/routes/buyer/addresses';
import { index as auctionOffersIndex } from '@/routes/buyer/auction-offers';
import { index as feedbackIndex } from '@/routes/buyer/feedback';
import { index as ordersIndex } from '@/routes/buyer/orders';
import { index as paymentsIndex } from '@/routes/buyer/payments';
import { index as returnsIndex } from '@/routes/buyer/returns';
import { edit as settingsEdit } from '@/routes/buyer/settings/profile';
import { show as cartShow } from '@/routes/cart';
import type { Auth } from '@/types';
import type { ReviewFlags } from '@/types/reviews';

type BuyerRoute = ReturnType<typeof dashboard>;
type PageProps = {
    auth: Auth;
    commerce: { cart_quantity: number };
    reviewFlags: ReviewFlags;
};

const navigation: {
    title: string;
    href: BuyerRoute;
    icon: LucideIcon;
    match: string;
    exact?: boolean;
}[] = [
    {
        title: 'Overview',
        href: dashboard(),
        icon: LayoutDashboard,
        match: '/buyer',
        exact: true,
    },
    {
        title: 'Orders',
        href: ordersIndex(),
        icon: Package,
        match: '/buyer/orders',
    },
    {
        title: 'Auction offers',
        href: auctionOffersIndex(),
        icon: Gavel,
        match: '/buyer/auction-offers',
    },
    {
        title: 'Payments',
        href: paymentsIndex(),
        icon: CreditCard,
        match: '/buyer/payments',
    },
    {
        title: 'Returns & refunds',
        href: returnsIndex(),
        icon: RotateCcw,
        match: '/buyer/returns',
    },
    {
        title: 'Feedback',
        href: feedbackIndex(),
        icon: MessageSquareText,
        match: '/buyer/feedback',
    },
    {
        title: 'Addresses',
        href: addressesIndex(),
        icon: MapPin,
        match: '/buyer/addresses',
    },
    {
        title: 'Settings',
        href: settingsEdit(),
        icon: Settings,
        match: '/buyer/settings',
    },
];

function BuyerNavigation({
    productReviewsEnabled,
    onNavigate,
}: {
    productReviewsEnabled: boolean;
    onNavigate?: () => void;
}) {
    const currentPath = usePage().url.split('?')[0];
    const visibleNavigation = productReviewsEnabled
        ? navigation
        : navigation.filter((item) => item.match !== '/buyer/feedback');

    return (
        <nav className="grid gap-1" aria-label="Buyer portal navigation">
            {visibleNavigation.map((item) => {
                const active = item.exact
                    ? currentPath === item.match
                    : currentPath.startsWith(item.match);

                return (
                    <Link
                        key={item.title}
                        href={item.href}
                        prefetch
                        onClick={onNavigate}
                        className={cn(
                            'group flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-600',
                            active
                                ? 'bg-orange-600 text-white shadow-sm shadow-orange-600/20'
                                : 'text-slate-600 hover:bg-orange-50 hover:text-orange-700 dark:text-slate-300 dark:hover:bg-orange-500/10 dark:hover:text-orange-300',
                        )}
                    >
                        <item.icon className="size-[1.125rem]" aria-hidden />
                        {item.title}
                    </Link>
                );
            })}
        </nav>
    );
}

export function BuyerPortalLayout({
    children,
    title,
}: {
    children: React.ReactNode;
    title: string;
}) {
    const { auth, commerce, reviewFlags } = usePage<PageProps>().props;
    const getInitials = useInitials();

    return (
        <div className="min-h-dvh bg-[#f8f7f4] text-slate-950 dark:bg-slate-950 dark:text-slate-50">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-[280px] flex-col border-r border-slate-200/80 bg-white px-5 py-6 lg:flex dark:border-slate-800 dark:bg-slate-950">
                <Link href={home()} className="rounded-xl px-1 py-1">
                    <BrandLogo showTagline />
                </Link>
                <p className="mt-8 px-3 text-[0.68rem] font-bold tracking-[0.14em] text-slate-400 uppercase">
                    Your account
                </p>
                <BuyerNavigation productReviewsEnabled={reviewFlags.product} />
                <div className="mt-auto rounded-2xl bg-gradient-to-br from-orange-500 to-orange-600 p-4 text-white shadow-lg shadow-orange-600/15">
                    <p className="text-sm font-bold">Ready for another deal?</p>
                    <p className="mt-1 text-xs leading-5 text-orange-50">
                        Browse fresh offers from verified sellers.
                    </p>
                    <Link
                        href={home()}
                        className="mt-3 inline-flex min-h-10 items-center rounded-xl bg-white px-3 text-sm font-bold text-orange-700"
                    >
                        Continue shopping
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
                                        Open buyer navigation
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
                                <div className="mt-8">
                                    <BuyerNavigation
                                        productReviewsEnabled={
                                            reviewFlags.product
                                        }
                                    />
                                </div>
                                <Link
                                    href={home()}
                                    className="mt-auto flex min-h-11 items-center gap-2 rounded-xl bg-orange-600 px-4 text-sm font-bold text-white"
                                >
                                    Continue shopping
                                </Link>
                            </SheetContent>
                        </Sheet>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-bold">
                                {title}
                            </p>
                            <p className="hidden text-xs text-slate-500 sm:block">
                                Buyer portal
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-1.5 sm:gap-3">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="relative rounded-xl"
                            asChild
                        >
                            <Link
                                href={cartShow()}
                                aria-label={`Cart with ${commerce.cart_quantity} items`}
                            >
                                <ShoppingCart className="size-5" />
                                {commerce.cart_quantity > 0 && (
                                    <span className="absolute -top-0.5 -right-0.5 grid size-5 place-items-center rounded-full bg-orange-600 text-[0.65rem] font-bold text-white">
                                        {commerce.cart_quantity > 99
                                            ? '99+'
                                            : commerce.cart_quantity}
                                    </span>
                                )}
                            </Link>
                        </Button>
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
                                            {getInitials(auth.user.name)}
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
                                    settingsHref={settingsEdit()}
                                />
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-[1500px] px-4 py-7 sm:px-6 sm:py-9 lg:px-10 lg:py-10">
                    {children}
                </main>
            </div>
        </div>
    );
}
