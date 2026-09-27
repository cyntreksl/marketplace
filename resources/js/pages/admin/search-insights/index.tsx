import { Head, Link } from '@inertiajs/react';
import { Search, SearchX, Users } from 'lucide-react';
import { PortalLayout } from '@/components/portal-layout';
import { cn } from '@/lib/utils';
import { index as searchInsightsIndex } from '@/routes/admin/search-insights';
import type { SearchInsightsPageProps, SearchInsightsTerm } from '@/types';

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function TermTable({
    title,
    terms,
    emptyMessage,
}: {
    title: string;
    terms: SearchInsightsTerm[];
    emptyMessage: string;
}) {
    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 className="text-lg font-bold">{title}</h2>
            </div>
            {terms.length === 0 ? (
                <p className="p-6 text-sm text-muted-foreground">
                    {emptyMessage}
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950/50">
                            <tr>
                                <th className="px-5 py-3">Term</th>
                                <th className="px-5 py-3 text-right">
                                    Searches
                                </th>
                                <th className="px-5 py-3 text-right">
                                    Avg. results
                                </th>
                                <th className="px-5 py-3 text-right">Latest</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {terms.map((term) => (
                                <tr key={term.normalizedTerm}>
                                    <td className="px-5 py-4">
                                        <p className="font-semibold">
                                            {term.term}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {formatDate(term.lastSearchedAt)}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-right font-semibold">
                                        {term.searchCount}
                                    </td>
                                    <td className="px-5 py-4 text-right">
                                        {term.averageResultCount}
                                    </td>
                                    <td className="px-5 py-4 text-right">
                                        {term.latestResultCount}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

export default function SearchInsights({
    periodDays,
    metrics,
    topTerms,
    zeroResultTerms,
    recentSearches,
}: SearchInsightsPageProps) {
    const cards = [
        {
            label: 'Total searches',
            value: metrics.totalSearches.toLocaleString(),
            icon: Search,
        },
        {
            label: 'Unique terms',
            value: metrics.uniqueTerms.toLocaleString(),
            icon: Users,
        },
        {
            label: 'Zero-result rate',
            value: `${metrics.zeroResultRate}%`,
            icon: SearchX,
        },
    ];

    return (
        <PortalLayout portal="admin" title="Search insights">
            <Head title="Search insights" />
            <main className="mx-auto max-w-7xl">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                            Customer demand
                        </p>
                        <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Search insights
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            See what shoppers want and where the catalog has
                            gaps.
                        </p>
                    </div>
                    <div className="flex rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900">
                        {([7, 30, 90] as const).map((days) => (
                            <Link
                                key={days}
                                href={searchInsightsIndex({ query: { days } })}
                                preserveScroll
                                className={cn(
                                    'rounded-lg px-4 py-2 text-sm font-semibold transition',
                                    periodDays === days
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {days} days
                            </Link>
                        ))}
                    </div>
                </div>

                <div className="mt-8 grid gap-4 sm:grid-cols-3">
                    {cards.map((card) => (
                        <article
                            key={card.label}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                        >
                            <card.icon className="size-5 text-primary" />
                            <p className="mt-4 text-sm font-medium text-muted-foreground">
                                {card.label}
                            </p>
                            <p className="mt-1 text-3xl font-bold tracking-tight">
                                {card.value}
                            </p>
                        </article>
                    ))}
                </div>

                <div className="mt-6 grid gap-6 xl:grid-cols-2">
                    <TermTable
                        title="Top searches"
                        terms={topTerms}
                        emptyMessage="No searches have been recorded in this period."
                    />
                    <TermTable
                        title="Zero-result searches"
                        terms={zeroResultTerms}
                        emptyMessage="Every recorded search returned at least one result."
                    />
                </div>

                <section className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 className="text-lg font-bold">Recent searches</h2>
                    </div>
                    {recentSearches.data.length === 0 ? (
                        <p className="p-6 text-sm text-muted-foreground">
                            Recent shopper searches will appear here.
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950/50">
                                    <tr>
                                        <th className="px-5 py-3">Search</th>
                                        <th className="px-5 py-3">Shopper</th>
                                        <th className="px-5 py-3">Context</th>
                                        <th className="px-5 py-3 text-right">
                                            Results
                                        </th>
                                        <th className="px-5 py-3">Searched</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {recentSearches.data.map((search) => (
                                        <tr key={search.id}>
                                            <td className="px-5 py-4 font-semibold">
                                                {search.term}
                                            </td>
                                            <td className="px-5 py-4">
                                                {search.user ? (
                                                    <>
                                                        <p>
                                                            {search.user.name}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {search.user.email}
                                                        </p>
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Guest
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-5 py-4 capitalize">
                                                {search.context}
                                            </td>
                                            <td className="px-5 py-4 text-right font-semibold">
                                                {search.resultCount}
                                            </td>
                                            <td className="px-5 py-4 text-muted-foreground">
                                                {formatDate(search.searchedAt)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {recentSearches.last_page > 1 && (
                        <nav className="flex flex-wrap justify-center gap-2 border-t border-slate-200 p-4 dark:border-slate-800">
                            {recentSearches.links.map((link, index) =>
                                link.url ? (
                                    <Link
                                        key={`${link.label}-${index}`}
                                        href={link.url}
                                        preserveScroll
                                        className={cn(
                                            'rounded-lg border px-3 py-2 text-sm font-semibold',
                                            link.active
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-slate-200 dark:border-slate-700',
                                        )}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ) : (
                                    <span
                                        key={`${link.label}-${index}`}
                                        className="rounded-lg border border-slate-100 px-3 py-2 text-sm text-slate-400 dark:border-slate-800"
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                            )}
                        </nav>
                    )}
                </section>
            </main>
        </PortalLayout>
    );
}
