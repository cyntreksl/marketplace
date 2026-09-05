import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Headphones,
    RotateCcw,
    ShieldCheck,
    Truck,
} from 'lucide-react';
import { BrandLogo } from '@/components/brand-logo';

export function StorefrontFooter({ className = '' }: { className?: string }) {
    const { marketplace } = usePage().props;

    return (
        <footer className={`mt-12 bg-white ${className}`}>
            <div className="mx-auto max-w-[96rem] px-4 sm:px-6">
                <div className="grid gap-4 rounded-xl bg-orange-50/70 px-5 py-5 sm:grid-cols-2 lg:grid-cols-4">
                    {[
                        [Truck, 'Fast Delivery', 'Islandwide delivery'],
                        [
                            ShieldCheck,
                            '1 Year Warranty',
                            'Genuine products with warranty',
                        ],
                        [
                            BadgeCheck,
                            'Secure Payments',
                            '100% safe & encrypted checkout',
                        ],
                        [
                            Headphones,
                            'Islandwide Support',
                            'Call or chat with our team',
                        ],
                    ].map(([Icon, title, copy]) => {
                        const TrustIcon = Icon as typeof Truck;

                        return (
                            <div
                                key={title as string}
                                className="flex items-center gap-3"
                            >
                                <TrustIcon className="size-5 shrink-0 text-[#ff5a00]" />
                                <span>
                                    <strong className="block text-xs">
                                        {title as string}
                                    </strong>
                                    <span className="text-[10px] text-slate-500">
                                        {copy as string}
                                    </span>
                                </span>
                            </div>
                        );
                    })}
                </div>

                <div className="grid gap-8 py-10 md:grid-cols-[1.2fr_2fr]">
                    <div>
                        <BrandLogo className="text-xl" />
                        <p className="mt-3 max-w-sm text-xs leading-5 text-slate-500">
                            Sri Lanka’s marketplace for trusted products,
                            transparent offers, and supported shopping.
                        </p>
                        <a
                            href={`mailto:${marketplace.support.email}`}
                            className="mt-3 inline-block text-xs font-bold text-[#c2410c]"
                        >
                            {marketplace.support.email}
                        </a>
                    </div>
                    <nav
                        className="grid grid-cols-2 gap-6 text-xs sm:grid-cols-4"
                        aria-label="Footer"
                    >
                        {[
                            [
                                'Shop',
                                [
                                    ['All products', '/listings'],
                                    ['Deals', '/collections/deals'],
                                    ['Brands', '/brands'],
                                    ['Track order', '/order-tracking'],
                                ],
                            ],
                            [
                                'Help',
                                [
                                    ['Help center', '/help'],
                                    ['Contact us', '/contact'],
                                    ['Shipping', '/policies/shipping'],
                                    ['Returns', '/policies/returns-refunds'],
                                ],
                            ],
                            [
                                'Company',
                                [
                                    ['About', '/about'],
                                    ['Selling', '/selling'],
                                    ['Buying', '/buying'],
                                    ['FAQ', '/faq'],
                                ],
                            ],
                            [
                                'Legal',
                                [
                                    ['Terms', '/legal/terms'],
                                    ['Privacy', '/legal/privacy'],
                                    ['Cookies', '/legal/cookies'],
                                    ['Seller policy', '/policies/sellers'],
                                ],
                            ],
                        ].map(([heading, links]) => (
                            <div key={heading as string}>
                                <h2 className="font-extrabold">
                                    {heading as string}
                                </h2>
                                <ul className="mt-3 grid gap-2 text-slate-500">
                                    {(links as string[][]).map(
                                        ([label, href]) => (
                                            <li key={href}>
                                                <Link
                                                    href={href}
                                                    className="hover:text-[#ff5a00]"
                                                >
                                                    {label}
                                                </Link>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        ))}
                    </nav>
                </div>
                <div className="flex flex-col gap-3 border-t py-5 text-[10px] text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                    <span>
                        © {new Date().getFullYear()} ProDeals.lk. All rights
                        reserved.
                    </span>
                    <span className="flex items-center gap-2">
                        <RotateCcw className="size-3" /> Eligible returns ·
                        Secure checkout · LKR payments
                    </span>
                </div>
            </div>
        </footer>
    );
}
