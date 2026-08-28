import { Link, usePage } from '@inertiajs/react';
import { ArrowRight, Building2, Mail, Printer } from 'lucide-react';
import { StorefrontLayout } from '@/components/storefront-layout';
import { marketplaceDocuments } from '@/content/marketplace-documents';
import type { MarketplaceDocumentSection } from '@/content/marketplace-documents';
import { contact, help } from '@/routes';

function StandardSection({ section }: { section: MarketplaceDocumentSection }) {
    return (
        <section id={section.id} className="scroll-mt-32">
            <h2 className="text-2xl font-black tracking-tight text-slate-950 dark:text-white">
                {section.title}
            </h2>
            <div className="mt-4 grid gap-4 text-[0.95rem] leading-7 text-slate-600 dark:text-slate-300">
                {section.paragraphs.map((paragraph) => (
                    <p key={paragraph}>{paragraph}</p>
                ))}
                {section.bullets && (
                    <ul className="grid gap-3 pl-5 marker:text-primary">
                        {section.bullets.map((bullet) => (
                            <li key={bullet} className="list-disc pl-1">
                                {bullet}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}

function FaqSection({ section }: { section: MarketplaceDocumentSection }) {
    return (
        <details
            id={section.id}
            className="group scroll-mt-32 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm open:border-primary/30 open:shadow-lg open:shadow-primary/5 dark:border-slate-800 dark:bg-slate-900"
        >
            <summary className="cursor-pointer list-none pr-8 text-lg font-bold marker:hidden">
                {section.title}
                <span className="float-right text-primary transition group-open:rotate-45">
                    +
                </span>
            </summary>
            <div className="mt-4 grid gap-4 border-t border-slate-100 pt-4 text-sm leading-7 text-slate-600 dark:border-slate-800 dark:text-slate-300">
                {section.paragraphs.map((paragraph) => (
                    <p key={paragraph}>{paragraph}</p>
                ))}
            </div>
        </details>
    );
}

export default function StorefrontContentPage({
    document: documentKey,
}: {
    document: string;
}) {
    const { marketplace } = usePage().props;
    const document = marketplaceDocuments[documentKey];

    if (!document) {
        throw new Error(`Unknown marketplace document: ${documentKey}`);
    }

    const isFaq = documentKey === 'faq';

    return (
        <StorefrontLayout title={document.title}>
            <main className="bg-slate-100/70 dark:bg-slate-950">
                <section className="border-b border-slate-200 bg-gradient-to-br from-slate-950 via-[#102a5c] to-primary px-4 py-14 text-white sm:px-7 sm:py-20 dark:border-slate-800">
                    <div className="mx-auto max-w-7xl">
                        <p className="text-xs font-bold tracking-[0.18em] text-cyan-300 uppercase">
                            {document.eyebrow}
                        </p>
                        <h1 className="mt-4 max-w-4xl text-4xl font-black tracking-tight sm:text-5xl lg:text-6xl">
                            {document.title}
                        </h1>
                        <p className="mt-5 max-w-3xl text-base leading-7 text-slate-300 sm:text-lg">
                            {document.summary}
                        </p>
                        <div className="mt-7 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-400">
                            <span>
                                Effective {marketplace.legal_effective_date}
                            </span>
                            <span>Plain-language marketplace information</span>
                        </div>
                    </div>
                </section>

                <div className="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-7 lg:grid-cols-[17rem_minmax(0,1fr)] lg:py-16">
                    <aside className="h-max lg:sticky lg:top-32">
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                                On this page
                            </p>
                            <nav
                                aria-label={`${document.title} contents`}
                                className="mt-4"
                            >
                                <ol className="grid gap-2">
                                    {document.sections.map((section, index) => (
                                        <li key={section.id}>
                                            <a
                                                href={`#${section.id}`}
                                                className="flex gap-3 rounded-lg px-2 py-2 text-sm text-slate-600 transition hover:bg-primary/10 hover:text-primary dark:text-slate-300"
                                            >
                                                <span className="text-xs font-bold text-slate-400">
                                                    {String(index + 1).padStart(
                                                        2,
                                                        '0',
                                                    )}
                                                </span>
                                                <span>{section.title}</span>
                                            </a>
                                        </li>
                                    ))}
                                </ol>
                            </nav>
                            <button
                                type="button"
                                onClick={() => window.print()}
                                className="mt-5 flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-600 transition hover:border-primary hover:text-primary dark:border-slate-700 dark:text-slate-300 print:hidden"
                            >
                                <Printer className="size-4" />
                                Print this page
                            </button>
                        </div>
                    </aside>

                    <article className="min-w-0 rounded-3xl border border-slate-200 bg-white px-5 py-7 shadow-sm sm:px-9 sm:py-10 lg:px-12 dark:border-slate-800 dark:bg-slate-900">
                        {isFaq ? (
                            <div className="grid gap-4">
                                {document.sections.map((section) => (
                                    <FaqSection
                                        key={section.id}
                                        section={section}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="grid gap-10 divide-y divide-slate-100 dark:divide-slate-800 [&>section:not(:first-child)]:pt-10">
                                {document.sections.map((section) => (
                                    <StandardSection
                                        key={section.id}
                                        section={section}
                                    />
                                ))}
                            </div>
                        )}

                        <div className="mt-12 grid gap-4 rounded-2xl bg-slate-100 p-5 sm:grid-cols-2 dark:bg-slate-800/70">
                            <Link
                                href={help()}
                                className="group flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm transition hover:-translate-y-0.5 dark:bg-slate-900"
                            >
                                <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                    <Building2 className="size-5" />
                                </span>
                                <span>
                                    <span className="block text-sm font-bold">
                                        Browse the help centre
                                    </span>
                                    <span className="mt-0.5 flex items-center gap-1 text-xs text-primary">
                                        More guidance{' '}
                                        <ArrowRight className="size-3 transition group-hover:translate-x-0.5" />
                                    </span>
                                </span>
                            </Link>
                            <Link
                                href={contact()}
                                className="group flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm transition hover:-translate-y-0.5 dark:bg-slate-900"
                            >
                                <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                    <Mail className="size-5" />
                                </span>
                                <span>
                                    <span className="block text-sm font-bold">
                                        Contact support
                                    </span>
                                    <span className="mt-0.5 flex items-center gap-1 text-xs text-primary">
                                        {marketplace.support.email}{' '}
                                        <ArrowRight className="size-3 transition group-hover:translate-x-0.5" />
                                    </span>
                                </span>
                            </Link>
                        </div>

                        <p className="mt-8 border-t border-slate-100 pt-6 text-xs leading-5 text-slate-500 dark:border-slate-800">
                            This page is provided as operational marketplace
                            information and does not replace legal advice. The
                            final legal text should be reviewed by qualified Sri
                            Lankan and UK counsel before production use.
                        </p>
                    </article>
                </div>
            </main>
        </StorefrontLayout>
    );
}
