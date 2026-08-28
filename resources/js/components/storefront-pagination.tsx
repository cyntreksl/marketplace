import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { StorefrontListingPaginator } from '@/types';

export function StorefrontPagination({
    paginator,
}: {
    paginator: StorefrontListingPaginator;
}) {
    if (paginator.last_page <= 1) {
        return null;
    }

    const pageLinks = paginator.links.slice(1, -1);

    return (
        <nav
            aria-label="Product result pages"
            className="mt-10 flex flex-wrap items-center justify-center gap-2"
        >
            {paginator.prev_page_url ? (
                <Link
                    href={paginator.prev_page_url}
                    aria-label="Previous page"
                    className="grid size-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-primary hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                >
                    <ChevronLeft className="size-4" />
                </Link>
            ) : (
                <span className="grid size-10 place-items-center rounded-xl border border-slate-200 text-slate-300 dark:border-slate-800 dark:text-slate-700">
                    <ChevronLeft className="size-4" />
                </span>
            )}

            {pageLinks.map((link, index) =>
                link.url ? (
                    <Link
                        key={`${link.label}-${index}`}
                        href={link.url}
                        aria-current={link.active ? 'page' : undefined}
                        className={`grid min-w-10 place-items-center rounded-xl border px-3 py-2 text-sm font-bold transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none ${
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
                        }`}
                    >
                        {link.label}
                    </Link>
                ) : (
                    <span
                        key={`${link.label}-${index}`}
                        className="px-1 text-slate-400"
                    >
                        …
                    </span>
                ),
            )}

            {paginator.next_page_url ? (
                <Link
                    href={paginator.next_page_url}
                    aria-label="Next page"
                    className="grid size-10 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-primary hover:text-primary focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                >
                    <ChevronRight className="size-4" />
                </Link>
            ) : (
                <span className="grid size-10 place-items-center rounded-xl border border-slate-200 text-slate-300 dark:border-slate-800 dark:text-slate-700">
                    <ChevronRight className="size-4" />
                </span>
            )}
        </nav>
    );
}
