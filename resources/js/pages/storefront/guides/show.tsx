import { Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { StorefrontBreadcrumbs } from '@/components/storefront-breadcrumbs';
import { StorefrontLayout } from '@/components/storefront-layout';
import { home } from '@/routes';
import { show as categoryShow } from '@/routes/categories';
import { index as guidesIndex } from '@/routes/guides';
import { show as listingShow } from '@/routes/listings';

type Guide = {
    title: string;
    slug: string;
    excerpt: string;
    quickAnswer: string;
    sections: { heading: string; paragraphs: string[] }[];
    buyingChecklist: string[];
    heroImageUrl: string | null;
    heroImageAlt: string | null;
    publishedAt: string | null;
    updatedAt: string | null;
    categories: { id: number; name: string; slug: string }[];
};

type RelatedListing = {
    id: number;
    title: string;
    slug: string;
    price: string | null;
    imageUrl: string | null;
};

export default function GuideShow({
    guide,
    relatedListings,
}: {
    guide: Guide;
    relatedListings: RelatedListing[];
}) {
    return (
        <StorefrontLayout title={guide.title}>
            <main className="bg-slate-50 py-8 sm:py-12">
                <div className="storefront-container">
                    <StorefrontBreadcrumbs
                        items={[
                            { label: 'Home', href: home.url() },
                            {
                                label: 'Buying guides',
                                href: guidesIndex.url(),
                            },
                            { label: guide.title },
                        ]}
                    />
                    <article className="mx-auto mt-6 max-w-4xl overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        {guide.heroImageUrl && (
                            <img
                                src={guide.heroImageUrl}
                                alt={guide.heroImageAlt ?? ''}
                                className="aspect-[16/9] w-full object-cover"
                            />
                        )}
                        <div className="px-6 py-8 sm:px-10 sm:py-12">
                            <p className="text-sm font-extrabold tracking-widest text-[#FF6D00] uppercase">
                                Buying guide
                            </p>
                            <h1 className="mt-3 text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">
                                {guide.title}
                            </h1>
                            <p className="mt-5 text-lg leading-8 text-slate-600">
                                {guide.excerpt}
                            </p>
                            <div className="mt-7 rounded-2xl border border-orange-200 bg-orange-50 p-5">
                                <h2 className="font-black text-slate-950">
                                    Quick answer
                                </h2>
                                <p className="mt-2 leading-7 text-slate-700">
                                    {guide.quickAnswer}
                                </p>
                            </div>

                            <div className="mt-10 space-y-10">
                                {guide.sections.map((section) => (
                                    <section key={section.heading}>
                                        <h2 className="text-2xl font-black tracking-tight text-slate-950">
                                            {section.heading}
                                        </h2>
                                        <div className="mt-4 space-y-4 text-base leading-8 text-slate-700">
                                            {section.paragraphs.map(
                                                (paragraph) => (
                                                    <p key={paragraph}>
                                                        {paragraph}
                                                    </p>
                                                ),
                                            )}
                                        </div>
                                    </section>
                                ))}
                            </div>

                            <section className="mt-10 rounded-2xl bg-slate-950 p-6 text-white">
                                <h2 className="text-2xl font-black">
                                    Buying checklist
                                </h2>
                                <ul className="mt-5 grid gap-3">
                                    {guide.buyingChecklist.map((item) => (
                                        <li
                                            key={item}
                                            className="flex gap-3 leading-7 text-slate-200"
                                        >
                                            <CheckCircle2 className="mt-1 size-5 shrink-0 text-orange-400" />
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </section>

                            {guide.categories.length > 0 && (
                                <section className="mt-10">
                                    <h2 className="text-2xl font-black text-slate-950">
                                        Browse related categories
                                    </h2>
                                    <div className="mt-4 flex flex-wrap gap-3">
                                        {guide.categories.map((category) => (
                                            <Link
                                                key={category.id}
                                                href={categoryShow(
                                                    category.slug,
                                                )}
                                                className="rounded-full border border-orange-200 bg-orange-50 px-4 py-2 font-bold text-[#C85100]"
                                            >
                                                {category.name}
                                            </Link>
                                        ))}
                                    </div>
                                </section>
                            )}

                            {relatedListings.length > 0 && (
                                <section className="mt-10">
                                    <h2 className="text-2xl font-black text-slate-950">
                                        Products to compare
                                    </h2>
                                    <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        {relatedListings.map((listing) => (
                                            <Link
                                                key={listing.id}
                                                href={listingShow(listing.slug)}
                                                className="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                                            >
                                                {listing.imageUrl && (
                                                    <img
                                                        src={listing.imageUrl}
                                                        alt={listing.title}
                                                        className="aspect-square w-full object-cover"
                                                    />
                                                )}
                                                <div className="p-4">
                                                    <h3 className="line-clamp-2 font-extrabold text-slate-950">
                                                        {listing.title}
                                                    </h3>
                                                    {listing.price && (
                                                        <p className="mt-2 font-black text-[#C85100]">
                                                            Rs.{' '}
                                                            {Number(
                                                                listing.price,
                                                            ).toLocaleString(
                                                                'en-LK',
                                                            )}
                                                        </p>
                                                    )}
                                                </div>
                                            </Link>
                                        ))}
                                    </div>
                                </section>
                            )}
                        </div>
                    </article>
                </div>
            </main>
        </StorefrontLayout>
    );
}
