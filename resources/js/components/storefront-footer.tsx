import { Link } from '@inertiajs/react';
import { BrandLogo } from '@/components/brand-logo';

const footerFaqs = [
    {
        question: 'How do I track my order?',
        answer: 'Use Track order with your order number and the email used at checkout.',
    },
    {
        question: 'Which payment methods are available?',
        answer: 'Available options are shown at checkout and may include cards, bank transfer, and cash on delivery.',
    },
    {
        question: 'How do returns work?',
        answer: 'Eligible items can be submitted for review from your buyer workspace after delivery.',
    },
] as const;

export function StorefrontFooter({ className = '' }: { className?: string }) {
    return (
        <footer className={`mt-12 bg-white ${className}`}>
            <div className="storefront-container">
                <section
                    aria-labelledby="footer-faq-heading"
                    className="border-y border-slate-100 py-7"
                >
                    <div className="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p className="text-xs font-bold tracking-[0.14em] text-[#c2410c] uppercase">
                                Need help?
                            </p>
                            <h2
                                id="footer-faq-heading"
                                className="mt-1 text-lg font-extrabold"
                            >
                                Quick answers
                            </h2>
                        </div>
                        <Link
                            href="/faq"
                            className="text-xs font-bold text-[#c2410c] hover:text-[#ff5a00]"
                        >
                            View all FAQs →
                        </Link>
                    </div>
                    <div className="mt-4 grid overflow-hidden rounded-xl border border-slate-200 bg-white md:grid-cols-3 md:divide-x md:divide-slate-200">
                        {footerFaqs.map((faq) => (
                            <details
                                key={faq.question}
                                className="group border-b border-slate-200 px-4 py-3 last:border-b-0 open:bg-orange-50/60 md:border-b-0"
                            >
                                <summary className="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-bold marker:hidden">
                                    {faq.question}
                                    <span
                                        aria-hidden="true"
                                        className="shrink-0 text-lg leading-none text-[#ff5a00] transition group-open:rotate-45"
                                    >
                                        +
                                    </span>
                                </summary>
                                <p className="mt-2 pr-6 text-xs leading-5 text-slate-500">
                                    {faq.answer}
                                </p>
                            </details>
                        ))}
                    </div>
                </section>

                <div className="grid gap-8 py-9 md:grid-cols-[1.2fr_2fr]">
                    <div>
                        <BrandLogo className="text-xl" />
                        <p className="mt-3 max-w-sm text-sm leading-5 text-slate-500">
                            Sri Lanka’s marketplace for trusted products,
                            transparent offers, and supported shopping.
                        </p>
                    </div>
                    <nav
                        className="grid grid-cols-2 gap-6 text-sm sm:grid-cols-4"
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
                <div className="border-t py-5 text-xs text-slate-600">
                    <span>
                        © {new Date().getFullYear()} ProDeals.lk. All rights
                        reserved.
                    </span>
                </div>
            </div>
        </footer>
    );
}
