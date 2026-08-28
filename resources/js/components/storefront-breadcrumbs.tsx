import { Link } from '@inertiajs/react';
import { ChevronRight, Home } from 'lucide-react';
import type { StorefrontBreadcrumbItem } from '@/types';

export function StorefrontBreadcrumbs({
    items,
}: {
    items: StorefrontBreadcrumbItem[];
}) {
    return (
        <nav aria-label="Breadcrumb">
            <ol className="flex min-w-0 [scrollbar-width:none] items-center gap-1.5 overflow-x-auto pb-1 text-sm text-slate-500 dark:text-slate-400">
                {items.map((item, index) => {
                    const isCurrent = index === items.length - 1;

                    return (
                        <li
                            key={`${item.label}-${index}`}
                            className="flex shrink-0 items-center gap-1.5"
                        >
                            {index > 0 && (
                                <ChevronRight
                                    className="size-3.5 text-slate-300 dark:text-slate-700"
                                    aria-hidden="true"
                                />
                            )}
                            {item.href && !isCurrent ? (
                                <Link
                                    href={item.href}
                                    prefetch
                                    className="inline-flex items-center gap-1.5 font-medium transition hover:text-primary focus-visible:rounded focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none"
                                >
                                    {index === 0 && (
                                        <Home
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {item.label}
                                </Link>
                            ) : (
                                <span
                                    aria-current={
                                        isCurrent ? 'page' : undefined
                                    }
                                    className={
                                        isCurrent
                                            ? 'max-w-72 truncate font-bold text-slate-900 dark:text-white'
                                            : 'font-medium'
                                    }
                                >
                                    {item.label}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
