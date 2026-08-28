import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    CreditCard,
    Headphones,
    Instagram,
    Linkedin,
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

            <div className="px-4 py-12 sm:px-7 lg:py-16">
                <div className="mx-auto grid max-w-none gap-10 2xl:grid-cols-[1.1fr_2fr_1fr]">
                    <div className="max-w-md">
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
                        <p className="mt-5 text-sm leading-6 text-slate-400">
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

                    <div className="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-4">
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

                    <div className="rounded-2xl border border-white/10 bg-white/5 p-5">
                        <Headphones className="size-7 text-cyan-300" />
                        <h2 className="mt-4 font-bold text-white">
                            Need a hand?
                        </h2>
                        <p className="mt-2 text-sm leading-6 text-slate-400">
                            Our marketplace support team is available{' '}
                            {marketplace.support.days.toLowerCase()}.
                        </p>
                        <a
                            href={`mailto:${marketplace.support.email}`}
                            className="mt-4 inline-flex text-sm font-bold text-cyan-300 hover:text-cyan-200"
                        >
                            {marketplace.support.email}
                        </a>
                        <p className="mt-2 text-xs leading-5 text-slate-500">
                            {marketplace.support.hours} ·{' '}
                            {marketplace.support.timezone}
                        </p>
                    </div>
                </div>
            </div>

            <div className="border-t border-white/10 px-4 py-6 sm:px-7">
                <div className="mx-auto flex max-w-none flex-col gap-5 text-xs leading-5 text-slate-500 xl:flex-row xl:items-end xl:justify-between">
                    <div className="max-w-4xl">
                        <p className="font-semibold text-slate-300">
                            {marketplace.legal_entity.name} · Company number{' '}
                            <a
                                href={
                                    marketplace.legal_entity.companies_house_url
                                }
                                target="_blank"
                                rel="noopener noreferrer"
                                className="underline decoration-slate-600 underline-offset-2 hover:text-cyan-300"
                            >
                                {marketplace.legal_entity.company_number}
                            </a>
                        </p>
                        <p className="mt-1">
                            Registered office:{' '}
                            {marketplace.legal_entity.registered_office}. This
                            is not a customer returns address.
                        </p>
                        <p className="mt-1">
                            © {currentYear} ProDeals.lk. All rights reserved.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 xl:justify-end">
                        {marketplace.payment_methods.map((method) => (
                            <span
                                key={method}
                                className="rounded-lg border border-white/10 bg-white/5 px-2.5 py-1.5 text-slate-400"
                            >
                                {method}
                            </span>
                        ))}
                    </div>
                </div>
            </div>
        </footer>
    );
}
