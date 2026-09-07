import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    CloudOff,
    Compass,
    HelpCircle,
    Home,
    LockKeyhole,
    PackageSearch,
    RefreshCw,
    SearchX,
    ShieldAlert,
    ShoppingBag,
    TimerReset,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import { help, home } from '@/routes';
import { index as listingsIndex } from '@/routes/listings';
import { index as orderTrackingIndex } from '@/routes/order-tracking';

type HttpErrorPageProps = {
    status: number;
};

type ErrorDetails = {
    title: string;
    description: string;
    icon: LucideIcon;
    retryable?: boolean;
};

const errorDetails: Record<number, ErrorDetails> = {
    400: {
        title: "We couldn't process that request",
        description:
            'Something in the request was not quite right. Check the details and try again.',
        icon: TriangleAlert,
    },
    401: {
        title: 'Please sign in to continue',
        description:
            'Your account needs to be signed in before you can access this page.',
        icon: LockKeyhole,
    },
    403: {
        title: 'This area is not available to you',
        description:
            "You don't have permission to view this page. If this seems wrong, our support team can help.",
        icon: ShieldAlert,
    },
    404: {
        title: 'This page took a detour',
        description:
            'The page may have moved, sold out, or never existed. There are plenty of great finds back at the marketplace.',
        icon: SearchX,
    },
    405: {
        title: 'That action is not available',
        description:
            'This page does not support the action that was requested. Return to the marketplace and try another route.',
        icon: Compass,
    },
    408: {
        title: 'That took a little too long',
        description:
            'The request timed out before it could finish. Your connection may just need another moment.',
        icon: TimerReset,
        retryable: true,
    },
    409: {
        title: 'Things changed while you were here',
        description:
            'The page is out of sync with the latest information. Refresh it to pick up where you left off.',
        icon: RefreshCw,
        retryable: true,
    },
    410: {
        title: 'This page is no longer here',
        description:
            'The item or page has been permanently removed, but you can keep exploring the marketplace.',
        icon: SearchX,
    },
    413: {
        title: 'That request is too large',
        description:
            'The content you submitted is larger than we can accept. Reduce its size and try again.',
        icon: TriangleAlert,
    },
    414: {
        title: 'That link is too long',
        description:
            'The web address contains more information than we can process. Start again from the marketplace.',
        icon: Compass,
    },
    415: {
        title: 'That format is not supported',
        description:
            'We could not read the submitted content. Try a supported format or return to the previous page.',
        icon: TriangleAlert,
    },
    418: {
        title: 'A curious little detour',
        description:
            'You found a corner of the marketplace that was not built for shopping. Let us get you back on track.',
        icon: ShoppingBag,
    },
    419: {
        title: 'Your session has expired',
        description:
            'For your security, this page timed out. Refresh and try the action again.',
        icon: TimerReset,
        retryable: true,
    },
    422: {
        title: "We couldn't complete that request",
        description:
            'Some of the information needs another look. Go back, review the details, and try again.',
        icon: TriangleAlert,
    },
    423: {
        title: 'This item is temporarily locked',
        description:
            'Another update may be in progress. Give it a moment, then try again.',
        icon: LockKeyhole,
        retryable: true,
    },
    425: {
        title: 'That request arrived too soon',
        description:
            'We paused this action to keep it safe. Wait a moment and try again.',
        icon: TimerReset,
        retryable: true,
    },
    429: {
        title: "You're moving a little fast",
        description:
            'We received several requests in a short time. Take a quick pause, then try again.',
        icon: TimerReset,
        retryable: true,
    },
    431: {
        title: 'That request carried too much information',
        description:
            'Your browser sent more header data than we can accept. Clear the site data or try again shortly.',
        icon: TriangleAlert,
    },
    451: {
        title: 'This page is unavailable here',
        description:
            'Legal restrictions prevent us from showing this content in your location.',
        icon: ShieldAlert,
    },
    500: {
        title: 'Something went off track',
        description:
            "We hit an unexpected problem while loading this page. It's on our side, and trying again may resolve it.",
        icon: CloudOff,
        retryable: true,
    },
    501: {
        title: 'That feature is not available yet',
        description:
            'This action is not supported right now. You can continue shopping while we work on it.',
        icon: CloudOff,
    },
    502: {
        title: 'Our connection hit a snag',
        description:
            'A service we rely on returned an invalid response. Please try again in a moment.',
        icon: CloudOff,
        retryable: true,
    },
    503: {
        title: "We're taking a quick service break",
        description:
            'ProDeals is temporarily unavailable while we complete some work. Please check back shortly.',
        icon: CloudOff,
        retryable: true,
    },
    504: {
        title: 'Our connection took too long',
        description:
            'A service did not respond in time. Refresh the page or come back in a few minutes.',
        icon: TimerReset,
        retryable: true,
    },
    505: {
        title: 'This connection version is not supported',
        description:
            'Your browser used a web protocol we cannot accept. Try updating your browser or use another one.',
        icon: CloudOff,
    },
    507: {
        title: 'We need a little more room',
        description:
            'The service is temporarily out of storage capacity. Please try again later.',
        icon: CloudOff,
        retryable: true,
    },
    508: {
        title: 'That request got caught in a loop',
        description:
            'The service could not complete the request safely. Return home and try a different route.',
        icon: RefreshCw,
    },
    511: {
        title: 'Network sign-in is required',
        description:
            'Connect to your network first, then refresh this page to continue.',
        icon: LockKeyhole,
        retryable: true,
    },
};

function detailsFor(status: number): ErrorDetails {
    if (errorDetails[status]) {
        return errorDetails[status];
    }

    if (status >= 500) {
        return {
            title: 'The marketplace needs a moment',
            description:
                'An unexpected service problem interrupted your visit. Please try again shortly.',
            icon: CloudOff,
            retryable: true,
        };
    }

    return {
        title: "We couldn't open this page",
        description:
            'The request could not be completed. Return to the marketplace or go back and try another route.',
        icon: TriangleAlert,
    };
}

export default function HttpErrorPage({ status }: HttpErrorPageProps) {
    const details = detailsFor(status);
    const StatusIcon = details.icon;

    return (
        <div className="relative min-h-svh overflow-hidden bg-[#07162f] text-white">
            <Head>
                <title>{`${status} · ${details.title}`}</title>
                <meta
                    head-key="robots"
                    name="robots"
                    content="noindex, nofollow"
                />
            </Head>

            <div
                aria-hidden="true"
                className="absolute inset-0 bg-[radial-gradient(circle_at_16%_12%,rgba(255,109,0,0.24),transparent_30%),radial-gradient(circle_at_88%_76%,rgba(39,100,168,0.32),transparent_34%)]"
            />
            <div
                aria-hidden="true"
                className="absolute -top-24 right-[8%] size-72 rounded-full border border-white/10 sm:size-96"
            />
            <div
                aria-hidden="true"
                className="absolute -right-28 -bottom-36 size-80 rounded-full border border-[#ff6d00]/25 sm:size-[32rem]"
            />

            <header className="relative z-10 storefront-container flex min-h-20 items-center justify-between border-b border-white/10 py-4">
                <Link
                    href={home()}
                    aria-label="ProDeals.lk home"
                    className="rounded-md focus-visible:ring-2 focus-visible:ring-[#ff6d00] focus-visible:ring-offset-4 focus-visible:ring-offset-[#07162f] focus-visible:outline-none"
                >
                    <BrandLogo
                        inverse
                        className="[&>img]:h-9 [&>img]:w-36 sm:[&>img]:h-10 sm:[&>img]:w-40"
                    />
                </Link>
                <Link
                    href={help()}
                    className="inline-flex min-h-11 items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 text-sm font-bold text-white transition hover:border-white/30 hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-[#ff6d00] focus-visible:outline-none"
                >
                    <HelpCircle className="size-4" />
                    <span className="hidden sm:inline">Help centre</span>
                    <span className="sm:hidden">Help</span>
                </Link>
            </header>

            <main className="relative z-10 storefront-container grid min-h-[calc(100svh-5rem)] items-center gap-10 py-10 lg:grid-cols-[minmax(0,1fr)_minmax(24rem,0.9fr)] lg:gap-16 lg:py-16">
                <section aria-labelledby="error-title" className="max-w-2xl">
                    <div className="inline-flex items-center gap-2 rounded-full border border-[#ff6d00]/30 bg-[#ff6d00]/10 px-3 py-1.5 text-sm font-bold text-orange-200">
                        <span className="size-2 rounded-full bg-[#ff6d00] shadow-[0_0_0_4px_rgba(255,109,0,0.14)]" />
                        Error {status}
                    </div>

                    <h1
                        id="error-title"
                        className="mt-6 max-w-xl text-4xl leading-[1.05] font-black tracking-[-0.04em] text-balance sm:text-5xl lg:text-6xl"
                    >
                        {details.title}
                    </h1>
                    <p className="mt-5 max-w-xl text-base leading-7 text-slate-300 sm:text-lg sm:leading-8">
                        {details.description}
                    </p>

                    <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        {details.retryable ? (
                            <button
                                type="button"
                                onClick={() => window.location.reload()}
                                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#ff6d00] px-5 text-sm font-black text-white shadow-lg shadow-orange-950/30 transition hover:bg-[#e86100] focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#07162f] focus-visible:outline-none"
                            >
                                <RefreshCw className="size-4" />
                                Try again
                            </button>
                        ) : (
                            <Link
                                href={home()}
                                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#ff6d00] px-5 text-sm font-black text-white shadow-lg shadow-orange-950/30 transition hover:bg-[#e86100] focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#07162f] focus-visible:outline-none"
                            >
                                <Home className="size-4" />
                                Go to homepage
                            </Link>
                        )}

                        {details.retryable && (
                            <Link
                                href={home()}
                                className="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/5 px-5 text-sm font-black text-white transition hover:border-white/35 hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-[#ff6d00] focus-visible:outline-none"
                            >
                                <Home className="size-4" />
                                Go to homepage
                            </Link>
                        )}

                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl px-4 text-sm font-bold text-slate-300 transition hover:bg-white/5 hover:text-white focus-visible:ring-2 focus-visible:ring-[#ff6d00] focus-visible:outline-none"
                        >
                            <ArrowLeft className="size-4" />
                            Go back
                        </button>
                    </div>

                    <nav
                        aria-label="Helpful links"
                        className="mt-10 grid max-w-xl gap-2 border-t border-white/10 pt-6 sm:grid-cols-2"
                    >
                        <Link
                            href={listingsIndex()}
                            className="group flex min-h-14 items-center gap-3 rounded-xl px-3 text-sm font-bold text-slate-200 transition hover:bg-white/5 hover:text-white focus-visible:ring-2 focus-visible:ring-[#ff6d00] focus-visible:outline-none"
                        >
                            <span className="grid size-9 place-items-center rounded-lg bg-white/10 text-orange-300">
                                <ShoppingBag className="size-4" />
                            </span>
                            Browse all products
                            <ArrowRight className="ml-auto size-4 transition group-hover:translate-x-0.5" />
                        </Link>
                        <Link
                            href={orderTrackingIndex()}
                            className="group flex min-h-14 items-center gap-3 rounded-xl px-3 text-sm font-bold text-slate-200 transition hover:bg-white/5 hover:text-white focus-visible:ring-2 focus-visible:ring-[#ff6d00] focus-visible:outline-none"
                        >
                            <span className="grid size-9 place-items-center rounded-lg bg-white/10 text-sky-300">
                                <PackageSearch className="size-4" />
                            </span>
                            Track an order
                            <ArrowRight className="ml-auto size-4 transition group-hover:translate-x-0.5" />
                        </Link>
                    </nav>
                </section>

                <div
                    aria-hidden="true"
                    className="relative mx-auto w-full max-w-xl lg:max-w-none"
                >
                    <div className="absolute -inset-6 rounded-[2.5rem] bg-[#ff6d00]/10 blur-3xl" />
                    <div className="relative overflow-hidden rounded-[2rem] border border-white/15 bg-white/[0.07] p-3 shadow-2xl shadow-black/25 backdrop-blur-sm sm:p-5">
                        <div className="rounded-[1.4rem] border border-white/10 bg-[#0b1f3f]/90 p-5 sm:p-7">
                            <div className="flex items-center gap-2 border-b border-white/10 pb-5">
                                <span className="size-2.5 rounded-full bg-[#ff6d00]" />
                                <span className="size-2.5 rounded-full bg-amber-300/70" />
                                <span className="size-2.5 rounded-full bg-sky-300/70" />
                                <span className="ml-3 h-7 flex-1 rounded-full border border-white/10 bg-white/5" />
                            </div>

                            <div className="relative grid min-h-64 place-items-center py-8 sm:min-h-80">
                                <div className="absolute inset-4 rounded-full border border-dashed border-white/10" />
                                <div className="absolute inset-12 rounded-full border border-white/10" />
                                <div className="absolute top-8 left-4 rounded-xl border border-white/10 bg-white/10 px-3 py-2 text-xs font-black tracking-widest text-orange-200 uppercase shadow-xl sm:left-8">
                                    Better deals
                                </div>
                                <div className="absolute right-3 bottom-8 rounded-xl bg-[#ff6d00] px-3 py-2 text-xs font-black text-white shadow-xl sm:right-7">
                                    Closer to home
                                </div>

                                <div className="relative grid size-32 place-items-center rounded-[2rem] border border-white/15 bg-white/10 shadow-2xl sm:size-40">
                                    <StatusIcon
                                        className="size-14 text-[#ff7a1a] sm:size-16"
                                        strokeWidth={1.5}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="pointer-events-none absolute -right-1 -bottom-9 font-black tracking-[-0.08em] text-white/[0.08] select-none sm:-right-3 sm:-bottom-14">
                        <span className="text-[7rem] leading-none sm:text-[10rem]">
                            {status}
                        </span>
                    </div>
                </div>
            </main>
        </div>
    );
}
