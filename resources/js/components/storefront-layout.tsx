import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, home, login, register } from '@/routes';
import { index as listingsIndex } from '@/routes/listings';

export function StorefrontLayout({
    children,
    title,
}: {
    children: React.ReactNode;
    title: string;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-stone-50 text-stone-950 dark:bg-stone-950 dark:text-stone-50">
                <header className="sticky top-0 z-20 border-b border-stone-200/80 bg-stone-50/90 backdrop-blur dark:border-stone-800 dark:bg-stone-950/90">
                    <nav
                        className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
                        aria-label="Main navigation"
                    >
                        <Link
                            href={home()}
                            className="text-xl font-black tracking-tight"
                        >
                            Circuit
                            <span className="text-amber-600">Market</span>
                        </Link>
                        <div className="hidden items-center gap-5 text-sm font-medium sm:flex">
                            <Link
                                href={listingsIndex({
                                    query: { listing_type: 'auction' },
                                })}
                            >
                                Auctions
                            </Link>
                            <Link
                                href={listingsIndex({
                                    query: { listing_type: 'buy_now' },
                                })}
                            >
                                Buy Now
                            </Link>
                            <Link href={listingsIndex()}>Explore</Link>
                        </div>
                        <div className="flex items-center gap-2 text-sm font-semibold">
                            {auth.user ? (
                                <Link
                                    className="rounded-full bg-stone-950 px-4 py-2 text-white dark:bg-stone-50 dark:text-stone-950"
                                    href={dashboard()}
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link className="px-3 py-2" href={login()}>
                                        Sign in
                                    </Link>
                                    <Link
                                        className="rounded-full bg-amber-500 px-4 py-2 text-stone-950"
                                        href={register()}
                                    >
                                        Join now
                                    </Link>
                                </>
                            )}
                        </div>
                    </nav>
                </header>
                {children}
                <footer className="border-t border-stone-200 py-10 dark:border-stone-800">
                    <div className="mx-auto max-w-7xl px-4 text-sm text-stone-500 sm:px-6 lg:px-8">
                        Sri Lanka’s trusted marketplace for electronics.
                    </div>
                </footer>
            </div>
        </>
    );
}
