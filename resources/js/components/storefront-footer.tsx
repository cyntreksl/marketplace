import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    BadgeCheck,
    ChevronRight,
    CreditCard,
    Headphones,
    Instagram,
    Landmark,
    Linkedin,
    Mail,
    RotateCcw,
    Youtube,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';
import { about, buying, contact, faq, help, home, selling } from '@/routes';
import { cookies, privacy, terms } from '@/routes/legal';
import { index as listingsIndex } from '@/routes/listings';
import { prohibited, returns, sellers, shipping } from '@/routes/policies';
import { edit as sellerOnboardingEdit } from '@/routes/seller/onboarding';
import { register as vendorRegister } from '@/routes/vendor';

type FooterLink = {
    label: string;
    href: ReturnType<typeof about>;
};

const socialIcons: Record<string, LucideIcon> = {
    instagram: Instagram,
    linkedin: Linkedin,
    youtube: Youtube,
};

const paymentIcons: Record<string, LucideIcon> = {
    'Card payments': CreditCard,
    'Bank transfer': Landmark,
    'Cash on delivery': Banknote,
};

export function StorefrontFooter({ className = '' }: { className?: string }) {
    const { auth, marketplace } = usePage().props;
    const currentYear = new Date().getFullYear();
    const vendorRegistration = auth.user
        ? sellerOnboardingEdit()
        : vendorRegister();
    const navigation: Record<string, FooterLink[]> = {
        Shop: [
            { label: 'Browse all products', href: listingsIndex() },
            {
                label: 'Live auctions',
                href: listingsIndex({ query: { listing_type: 'auction' } }),
            },
            { label: 'Buying guide', href: buying() },
            { label: 'Shipping policy', href: shipping() },
        ],
        Help: [
            { label: 'Help centre', href: help() },
            { label: 'Contact support', href: contact() },
            { label: 'Frequently asked questions', href: faq() },
            { label: 'Returns & refunds', href: returns() },
        ],
        Sell: [
            { label: 'Become a vendor', href: vendorRegistration },
            { label: 'Selling guide', href: selling() },
            { label: 'Seller policy', href: sellers() },
            { label: 'Prohibited items', href: prohibited() },
        ],
        Company: [
            { label: 'About ProDeals.lk', href: about() },
            { label: 'Terms and conditions', href: terms() },
            { label: 'Privacy notice', href: privacy() },
            { label: 'Cookie policy', href: cookies() },
        ],
    };
    const configuredSocials = Object.entries(marketplace.social_urls).filter(
        (entry): entry is [string, string] => Boolean(entry[1]),
    );

    return (
        <footer
            className={`border-t border-primary/20 bg-slate-950 text-slate-200 ${className}`}
        >
            <div className="border-b border-white/10 bg-primary text-primary-foreground">
                <div className="mx-auto grid max-w-none gap-px bg-white/15 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        [
                            BadgeCheck,
                            'Moderated marketplace',
                            'Seller and listing review',
                        ],
                        [
                            CreditCard,
                            'Flexible payment',
                            'Card, transfer and COD',
                        ],
                        [
                            RotateCcw,
                            'Eligible returns',
                            'Seven days after delivery',
                        ],
                        [
                            Headphones,
                            'Support every day',
                            '09:00–18:00 Sri Lanka time',
                        ],
                    ].map(([Icon, title, description]) => {
                        const TrustIcon = Icon as LucideIcon;

                        return (
                            <div
                                key={title as string}
                                className="flex items-center gap-3 bg-primary px-5 py-5 sm:px-7"
                            >
                                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white/15">
                                    <TrustIcon className="size-5" />
                                </span>
                                <span>
                                    <span className="block text-sm font-bold">
                                        {title as string}
                                    </span>
                                    <span className="block text-xs text-primary-foreground/75">
                                        {description as string}
                                    </span>
                                </span>
                            </div>
                        );
                    })}
                </div>
            </div>

            <div className="px-4 py-10 sm:px-7 lg:py-12">
                <div className="mx-auto grid max-w-none gap-10 lg:grid-cols-[minmax(15rem,0.75fr)_minmax(0,2fr)] lg:gap-14 xl:grid-cols-[minmax(17rem,0.8fr)_minmax(0,2.2fr)] xl:gap-20">
                    <div className="max-w-sm">
                        <Link
                            href={home()}
                            className="inline-flex rounded-xl focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:outline-none"
                        >
                            <BrandLogo
                                inverse
                                showTagline
                                className="text-2xl"
                            />
                        </Link>
                        <p className="mt-4 max-w-xs text-sm leading-6 text-slate-400">
                            Discover products from independent sellers across
                            Sri Lanka with clearer marketplace standards,
                            practical support, and transparent order records.
                        </p>
                        {configuredSocials.length > 0 && (
                            <div className="mt-6 flex gap-2">
                                {configuredSocials.map(([network, url]) => {
                                    const SocialIcon = socialIcons[network];

                                    if (!SocialIcon) {
                                        return null;
                                    }

                                    return (
                                        <a
                                            key={network}
                                            href={url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label={`ProDeals.lk on ${network}`}
                                            className="grid size-10 place-items-center rounded-xl border border-white/15 text-slate-300 transition hover:border-cyan-300 hover:text-cyan-300"
                                        >
                                            <SocialIcon className="size-4" />
                                        </a>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-4 lg:gap-x-8">
                        {Object.entries(navigation).map(([heading, links]) => (
                            <nav key={heading} aria-label={`${heading} links`}>
                                <h2 className="text-xs font-bold tracking-[0.16em] text-white uppercase">
                                    {heading}
                                </h2>
                                <ul className="mt-4 grid gap-3 text-sm text-slate-400">
                                    {links.map((link) => (
                                        <li key={link.label}>
                                            <Link
                                                href={link.href}
                                                prefetch
                                                className="transition hover:text-cyan-300 focus-visible:text-cyan-300 focus-visible:outline-none"
                                            >
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </nav>
                        ))}
                    </div>
                </div>

                <aside
                    aria-labelledby="footer-support-heading"
                    className="mx-auto mt-10 grid max-w-none overflow-hidden rounded-2xl border border-cyan-300/20 bg-white/5 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)_auto] md:items-center"
                >
                    <div className="flex items-center gap-4 p-5 sm:p-6">
                        <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-cyan-300 text-slate-950 shadow-lg shadow-cyan-300/10">
                            <Headphones className="size-6" />
                        </span>
                        <div>
                            <h2
                                id="footer-support-heading"
                                className="text-base font-bold text-white"
                            >
                                Need a hand?
                            </h2>
                            <p className="mt-1 text-sm text-slate-400">
                                Friendly marketplace support,{' '}
                                {marketplace.support.days.toLowerCase()}.
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-1 border-t border-white/10 px-5 py-4 sm:px-6 md:border-t-0 md:border-l">
                        <a
                            href={`mailto:${marketplace.support.email}`}
                            className="inline-flex w-fit items-center gap-2 text-sm font-bold text-cyan-300 transition hover:text-cyan-200 focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 focus-visible:outline-none"
                        >
                            <Mail className="size-4" />
                            {marketplace.support.email}
                        </a>
                        <p className="text-xs leading-5 text-slate-400">
                            {marketplace.support.hours} ·{' '}
                            {marketplace.support.timezone}
                        </p>
                    </div>

                    <div className="border-t border-white/10 p-5 sm:p-6 md:border-t-0 md:border-l">
                        <Link
                            href={contact()}
                            className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-cyan-100 focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 focus-visible:outline-none md:w-auto"
                        >
                            Contact support
                            <ChevronRight className="size-4" />
                        </Link>
                    </div>
                </aside>
            </div>

            <div className="border-t border-white/10 px-4 py-6 sm:px-7">
                <div className="mx-auto flex max-w-none flex-col gap-6 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                    <p className="text-center text-sm text-slate-400 sm:text-left">
                        © {currentYear}{' '}
                        <Link
                            href={home()}
                            className="font-extrabold text-white transition hover:text-cyan-300 focus-visible:ring-2 focus-visible:ring-cyan-300 focus-visible:outline-none"
                        >
                            ProDeals.lk
                        </Link>
                        <span className="text-slate-500">
                            . All rights reserved.
                        </span>
                    </p>
                    <div
                        className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center sm:justify-end"
                        aria-label="Accepted payment methods"
                    >
                        <span className="col-span-2 mb-1 text-center text-[0.68rem] font-bold tracking-[0.16em] text-slate-500 uppercase sm:mr-1 sm:mb-0 sm:text-left">
                            Ways to pay
                        </span>
                        {marketplace.payment_methods.map((method) => {
                            const PaymentIcon = paymentIcons[method];

                            if (!PaymentIcon) {
                                return null;
                            }

                            return (
                                <span
                                    key={method}
                                    className="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-white/10 bg-white/5 px-2.5 py-1.5 text-center text-xs font-semibold text-slate-300 last:col-span-2 sm:min-h-0 sm:justify-start sm:text-left sm:last:col-span-1"
                                >
                                    <PaymentIcon
                                        className="size-4 text-cyan-300"
                                        aria-hidden="true"
                                    />
                                    {method}
                                </span>
                            );
                        })}
                    </div>
                </div>
            </div>
        </footer>
    );
}
