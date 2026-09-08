import { Link } from '@inertiajs/react';
import type { PaginationLink } from '@/types';

type AdminPaginator = {
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    next_page_url: string | null;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

export function AdminPagination({ paginator }: { paginator: AdminPaginator }) {
    if (paginator.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-3 border-t border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <p className="text-sm text-slate-500">
                Showing {paginator.from}–{paginator.to} of {paginator.total}
            </p>
            {paginator.last_page > 1 && (
                <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                    {paginator.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url}
                                preserveScroll
                                preserveState
                                aria-current={link.active ? 'page' : undefined}
                                className={`min-w-10 rounded-lg border px-3 py-2 text-center text-sm font-semibold ${link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-slate-200 bg-white hover:border-primary/50 dark:border-slate-700 dark:bg-slate-900'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                key={`${link.label}-${index}`}
                                className="min-w-10 rounded-lg border border-slate-100 px-3 py-2 text-center text-sm text-slate-400 dark:border-slate-800"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </nav>
            )}
        </div>
    );
}
