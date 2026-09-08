import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ImageIcon } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/AdminListingController';
import { PortalLayout } from '@/components/portal-layout';
import { dashboard } from '@/routes/admin';

type Listing = {
    id: number;
    title: string | null;
    status: string;
    listing_type: string;
    short_description: string | null;
    price: string | null;
    sale_price: string | null;
    is_retail_enabled: boolean;
    is_wholesale_enabled: boolean;
    wholesale_price: string | null;
    wholesale_min_quantity: number | null;
    moderation_reason: string | null;
    media: { id: number; url: string }[];
    seller_profile: { store_name: string };
    category: { name: string } | null;
    seo_score: SeoScore;
};
type SeoScore = {
    score: number;
    maximum: number;
    label: 'Strong' | 'Needs Improvement' | 'Weak';
    checks: {
        key: string;
        label: string;
        points: number;
        maximum: number;
        passed: boolean;
        recommendation: string | null;
    }[];
};
export default function AdminListings({
    listings,
}: {
    listings: { data: Listing[] };
}) {
    return (
        <PortalLayout portal="admin" title="Listing moderation">
            <Head title="Listing moderation" />
            <main className="mx-auto max-w-7xl">
                <Link
                    href={dashboard()}
                    className="text-sm font-bold text-primary"
                >
                    ← Operations
                </Link>
                <h1 className="mt-4 text-4xl font-black">Listing moderation</h1>
                <div className="mt-8 grid gap-4">
                    {listings.data.map((listing) => (
                        <article
                            key={listing.id}
                            className="grid gap-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[8rem_minmax(0,1fr)_auto] dark:border-slate-800 dark:bg-slate-900"
                        >
                            <div className="flex aspect-square items-center justify-center overflow-hidden rounded-xl bg-slate-50 dark:bg-slate-950">
                                {listing.media[0] ? (
                                    <img
                                        src={listing.media[0].url}
                                        alt=""
                                        className="size-full object-contain p-3"
                                    />
                                ) : (
                                    <ImageIcon className="size-8 text-slate-300" />
                                )}
                            </div>
                            <div>
                                <p className="text-lg font-black">
                                    {listing.title ?? 'Untitled product'}
                                </p>
                                <p className="mt-1 text-sm text-stone-500">
                                    {listing.seller_profile.store_name} ·{' '}
                                    {listing.category?.name ?? 'No category'} ·{' '}
                                    {listing.listing_type}
                                </p>
                                <p className="mt-2 text-sm capitalize">
                                    Current status:{' '}
                                    {listing.status.replace('_', ' ')}
                                </p>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {listing.is_retail_enabled && (
                                        <span className="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700">
                                            Retail
                                        </span>
                                    )}
                                    {listing.is_wholesale_enabled && (
                                        <span className="rounded-full bg-orange-50 px-2.5 py-1 text-xs font-bold text-orange-700">
                                            Wholesale · LKR{' '}
                                            {Number(
                                                listing.wholesale_price ?? 0,
                                            ).toLocaleString('en-LK')}{' '}
                                            · MOQ{' '}
                                            {listing.wholesale_min_quantity ??
                                                '—'}
                                        </span>
                                    )}
                                </div>
                                {listing.short_description && (
                                    <p className="mt-3 line-clamp-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                        {listing.short_description}
                                    </p>
                                )}
                                <details className="mt-4 rounded-xl bg-stone-50 p-3 dark:bg-stone-950">
                                    <summary className="cursor-pointer text-sm font-bold">
                                        SEO readiness: {listing.seo_score.score}
                                        /{listing.seo_score.maximum} ·{' '}
                                        {listing.seo_score.label}
                                    </summary>
                                    <ul className="mt-3 grid gap-2">
                                        {listing.seo_score.checks.map(
                                            (check) => (
                                                <li
                                                    key={check.key}
                                                    className="text-sm text-stone-600 dark:text-stone-300"
                                                >
                                                    <strong className="text-stone-900 dark:text-white">
                                                        {check.label}:{' '}
                                                        {check.points}/
                                                        {check.maximum}
                                                    </strong>
                                                    {check.recommendation && (
                                                        <span className="mt-0.5 block">
                                                            {
                                                                check.recommendation
                                                            }
                                                        </span>
                                                    )}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </details>
                            </div>
                            <Link
                                href={show(listing.id)}
                                className="inline-flex h-11 items-center justify-center gap-2 self-center rounded-xl bg-primary px-5 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20"
                            >
                                Review details <ArrowRight className="size-4" />
                            </Link>
                        </article>
                    ))}
                </div>
            </main>
        </PortalLayout>
    );
}
