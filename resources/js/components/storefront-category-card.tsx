import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { StorefrontCategoryArtwork } from '@/components/storefront-category-artwork';
import { show as categoryShow } from '@/routes/categories';
import type { StorefrontCategoryNode } from '@/types';

export function StorefrontCategoryCard({
    category,
    hasChildren,
}: {
    category: StorefrontCategoryNode;
    hasChildren: boolean;
}) {
    return (
        <Link
            href={categoryShow(category.slug)}
            prefetch
            className="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-3 shadow-sm shadow-slate-200/60 transition duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-xl hover:shadow-primary/10 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none dark:border-slate-800 dark:bg-slate-900 dark:shadow-none dark:focus-visible:ring-offset-slate-950"
        >
            <StorefrontCategoryArtwork category={category} />
            <div className="mt-3 flex items-start justify-between gap-3 px-1 pb-1">
                <div>
                    <h3 className="font-black tracking-tight text-slate-950 transition group-hover:text-primary dark:text-white">
                        {category.name}
                    </h3>
                    <p className="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">
                        {hasChildren ? 'Browse subcategories' : 'View products'}
                    </p>
                </div>
                <span className="grid size-8 shrink-0 place-items-center rounded-full bg-slate-50 dark:bg-slate-800">
                    <ArrowUpRight className="size-5 text-slate-300 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary dark:text-slate-600" />
                </span>
            </div>
        </Link>
    );
}
