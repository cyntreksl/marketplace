import { Link } from '@inertiajs/react';
import { Check, ShoppingBag, Sparkles } from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
    compact = false,
}: AuthLayoutProps) {
    return (
        <div
            className={
                compact
                    ? 'min-h-dvh bg-slate-50 text-foreground dark:bg-slate-950'
                    : 'min-h-dvh bg-slate-50 text-foreground lg:grid lg:grid-cols-2 dark:bg-slate-950'
            }
        >
            <main
                className={
                    compact
                        ? 'flex min-h-dvh flex-col overflow-x-hidden bg-background px-4 pt-[max(1rem,env(safe-area-inset-top))] pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-8 sm:py-5 lg:px-10 xl:px-16'
                        : 'relative flex min-h-dvh flex-col overflow-x-hidden bg-background px-4 pt-[max(1.25rem,env(safe-area-inset-top))] pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:px-10 sm:py-8 lg:px-14 lg:py-10 xl:px-20'
                }
            >
                <Link
                    href={home()}
                    className="inline-flex min-h-11 w-fit items-center gap-3 rounded-xl text-sm font-semibold text-slate-900 transition-opacity hover:opacity-75 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none dark:text-white"
                >
                    <BrandLogo />
                </Link>

                <div
                    className={
                        compact
                            ? 'mx-auto flex w-full max-w-3xl flex-1 flex-col py-6 sm:justify-center sm:py-8'
                            : 'mx-auto flex w-full max-w-md flex-1 flex-col justify-center py-8 sm:py-12'
                    }
                >
                    <div
                        className={
                            compact
                                ? 'mb-5 space-y-1.5 sm:mb-6'
                                : 'mb-7 space-y-2.5 sm:mb-8 sm:space-y-3'
                        }
                    >
                        <p
                            className={
                                compact
                                    ? 'text-xs font-semibold tracking-wide text-primary uppercase'
                                    : 'text-sm font-semibold tracking-wide text-primary uppercase'
                            }
                        >
                            Your marketplace account
                        </p>
                        <h1
                            className={
                                compact
                                    ? 'text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white'
                                    : 'text-[1.75rem] leading-tight font-bold tracking-tight text-slate-950 sm:text-4xl dark:text-white'
                            }
                        >
                            {title}
                        </h1>
                        <p
                            className={
                                compact
                                    ? 'max-w-2xl text-sm text-muted-foreground'
                                    : 'max-w-sm text-sm leading-6 text-muted-foreground sm:text-base'
                            }
                        >
                            {description}
                        </p>
                    </div>

                    {children}
                </div>

                {!compact && (
                    <p className="mx-auto max-w-md text-center text-xs leading-5 text-muted-foreground sm:mx-0 sm:text-left">
                        Discover great finds from a community of buyers and
                        sellers.
                    </p>
                )}
            </main>

            {!compact && (
                <aside className="relative hidden overflow-hidden bg-slate-950 px-12 py-10 text-white lg:flex lg:flex-col xl:px-20">
                    <div className="absolute -top-24 -right-16 size-80 rounded-full bg-primary/35 blur-3xl" />
                    <div className="absolute right-12 bottom-0 size-72 rounded-full bg-sky-400/15 blur-3xl" />

                    <Link
                        href={home()}
                        className="relative z-10 inline-flex w-fit items-center gap-3 text-sm font-semibold text-white"
                    >
                        <BrandLogo inverse />
                    </Link>

                    <div className="relative z-10 my-auto max-w-lg">
                        <span className="mb-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-slate-200 backdrop-blur-sm">
                            <Sparkles className="size-3.5 text-sky-300" />
                            Better deals, made simple
                        </span>
                        <h2 className="max-w-md text-4xl leading-tight font-semibold tracking-tight xl:text-5xl">
                            Better deals. Closer to home.
                        </h2>
                        <p className="mt-5 max-w-md text-base leading-7 text-slate-300">
                            Find everyday essentials, tech, style, and unique
                            local finds-all in one place.
                        </p>

                        <div className="mt-10 grid max-w-md grid-cols-2 gap-4">
                            <div className="rounded-2xl border border-white/10 bg-white/10 p-4 shadow-2xl shadow-black/10 backdrop-blur-sm">
                                <div className="mb-8 flex size-12 items-center justify-center rounded-xl bg-sky-400 text-slate-950">
                                    <ShoppingBag className="size-5" />
                                </div>
                                <p className="text-sm font-semibold">
                                    Fresh listings
                                </p>
                                <p className="mt-1 text-xs leading-5 text-slate-300">
                                    Explore what’s new today.
                                </p>
                            </div>
                            <div className="mt-8 rounded-2xl border border-white/10 bg-white/10 p-4 shadow-2xl shadow-black/10 backdrop-blur-sm">
                                <div className="mb-8 flex size-12 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                                    <Check className="size-5" />
                                </div>
                                <p className="text-sm font-semibold">
                                    Easy to use
                                </p>
                                <p className="mt-1 text-xs leading-5 text-slate-300">
                                    Shop or sell with ease.
                                </p>
                            </div>
                        </div>
                    </div>

                    <p className="relative z-10 text-sm text-slate-400">
                        A Sri Lankan marketplace for every kind of find.
                    </p>
                </aside>
            )}
        </div>
    );
}
