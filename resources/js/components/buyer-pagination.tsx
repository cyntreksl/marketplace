import { Link } from '@inertiajs/react';
import type { PaginationLink } from '@/types';

export function BuyerPagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav
            className="flex flex-wrap justify-center gap-1"
            aria-label="Pagination"
        >
            {links.map((link) =>
                link.url ? (
                    <Link
                        key={`${link.label}-${link.url}`}
                        href={link.url}
                        preserveScroll
                        className={`min-w-10 rounded-lg border px-3 py-2 text-center text-sm ${
                            link.active
                                ? 'border-orange-600 bg-orange-600 font-semibold text-white'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-orange-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={link.label}
                        className="min-w-10 rounded-lg border border-slate-200 px-3 py-2 text-center text-sm text-slate-400 dark:border-slate-800"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}
