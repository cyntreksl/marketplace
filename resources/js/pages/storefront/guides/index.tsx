import { Link } from '@inertiajs/react';
import { BookOpen, CalendarDays } from 'lucide-react';
import { StorefrontLayout } from '@/components/storefront-layout';
import { show as guideShow } from '@/routes/guides';

type GuideCard = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    heroImageUrl: string | null;
    heroImageAlt: string | null;
    publishedAt: string | null;
    categories: { id: number; name: string; slug: string }[];
};

function paginationLabel(label: string): string {
    return label.replace('&laquo;', '←').replace('&raquo;', '→');
}

export default function GuideIndex({
    guides,
}: {
    guides: {
        data: GuideCard[];
        links: { url: string | null; label: string; active: boolean }[];
        last_page: number;
    };
}) {
    return (
        <StorefrontLayout title="Buying Guides for Sri Lanka">
            <main className="bg-slate-50 py-10 sm:py-14">
                <div className="storefront-container">
                    <header className="max-w-3xl">
                        <p className="text-sm font-extrabold tracking-widest text-[#FF6D00] uppercase">
                            ProDeals buying guides
                        </p>
                        <h1 className="mt-3 text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">
                            Make a more confident product choice
                        </h1>
                        <p className="mt-4 text-lg leading-8 text-slate-600">
                            Practical comparisons and checklists written for Sri
                            Lankan shoppers, with direct links to products
                            currently available on ProDeals.lk.
                        </p>
                    </header>

                    {guides.data.length === 0 ? (
                        <section className="mt-10 rounded-3xl border border-dashed border-slate-300 bg-white p-10 text-center">
                            <BookOpen className="mx-auto size-9 text-[#FF6D00]" />
                            <h2 className="mt-4 text-xl font-black">
                                Guides are being prepared
                            </h2>
                        </section>
                    ) : (
                        <section className="mt-10 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {guides.data.map((guide) => (
                                <article
                                    key={guide.id}
                                    className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"
                                >
                                    {guide.heroImageUrl ? (
                                        <img
                                            src={guide.heroImageUrl}
                                            alt={guide.heroImageAlt ?? ''}
                                            className="aspect-[16/9] w-full object-cover"
                                        />
                                    ) : (
                                        <div className="grid aspect-[16/9] place-items-center bg-gradient-to-br from-orange-50 to-amber-100 text-[#FF6D00]">
                                            <BookOpen className="size-12" />
                                        </div>
                                    )}
                                    <div className="p-6">
                                        {guide.publishedAt && (
                                            <p className="flex items-center gap-2 text-xs font-bold text-slate-500">
                                                <CalendarDays className="size-4" />
                                                {new Date(
                                                    guide.publishedAt,
                                                ).toLocaleDateString('en-LK', {
                                                    day: 'numeric',
                                                    month: 'long',
                                                    year: 'numeric',
                                                })}
                                            </p>
                                        )}
                                        <h2 className="mt-3 text-xl font-black tracking-tight text-slate-950">
                                            <Link
                                                href={guideShow(guide.slug)}
                                                className="hover:text-[#FF6D00]"
                                            >
                                                {guide.title}
                                            </Link>
                                        </h2>
                                        <p className="mt-3 line-clamp-4 text-sm leading-6 text-slate-600">
                                            {guide.excerpt}
                                        </p>
                                        <Link
                                            href={guideShow(guide.slug)}
                                            className="mt-5 inline-flex min-h-11 items-center font-extrabold text-[#C85100]"
                                        >
                                            Read the guide →
                                        </Link>
                                    </div>
                                </article>
                            ))}
                        </section>
                    )}
                    {guides.last_page > 1 && (
                        <nav
                            aria-label="Guide pages"
                            className="mt-10 flex flex-wrap justify-center gap-2"
                        >
                            {guides.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url}
                                        className={`rounded-lg border px-4 py-2 text-sm font-bold ${link.active ? 'border-[#FF6D00] bg-[#FF6D00] text-white' : 'border-slate-200 bg-white text-slate-700'}`}
                                    >
                                        {paginationLabel(link.label)}
                                    </Link>
                                ) : (
                                    <span
                                        key={`${link.label}-${index}`}
                                        className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-400"
                                    >
                                        {paginationLabel(link.label)}
                                    </span>
                                ),
                            )}
                        </nav>
                    )}
                </div>
            </main>
        </StorefrontLayout>
    );
}
