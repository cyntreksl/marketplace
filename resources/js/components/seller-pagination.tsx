import { Link } from '@inertiajs/react';
import type { SellerPaginator } from '@/types';

export function SellerPagination<T>({
    paginator,
}: {
    paginator: SellerPaginator<T>;
}) {
    if (paginator.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 border-t border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <p className="text-sm text-slate-500">
                {paginator.from}–{paginator.to} of {paginator.total}
            </p>
            <nav className="hidden gap-1 sm:flex" aria-label="Pagination">
                {paginator.links.map((link) =>
                    link.url ? (
                        <Link
                            key={`${link.label}-${link.url}`}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={`min-w-10 rounded-lg border px-3 py-2 text-center text-sm ${link.active ? 'border-orange-600 bg-orange-600 font-bold text-white' : 'border-slate-200 bg-white hover:border-orange-300 dark:border-slate-700 dark:bg-slate-900'}`}
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
            <nav
                className="grid grid-cols-2 gap-2 sm:hidden"
                aria-label="Mobile pagination"
            >
                {paginator.prev_page_url ? (
                    <Link
                        href={paginator.prev_page_url}
                        preserveScroll
                        preserveState
                        className="rounded-xl border px-4 py-2 text-center text-sm font-bold"
                    >
                        Previous
                    </Link>
                ) : (
                    <span className="rounded-xl border px-4 py-2 text-center text-sm text-slate-400">
                        Previous
                    </span>
                )}
                {paginator.next_page_url ? (
                    <Link
                        href={paginator.next_page_url}
                        preserveScroll
                        preserveState
                        className="rounded-xl border px-4 py-2 text-center text-sm font-bold"
                    >
                        Next
                    </Link>
                ) : (
                    <span className="rounded-xl border px-4 py-2 text-center text-sm text-slate-400">
                        Next
                    </span>
                )}
            </nav>
        </div>
    );
}
