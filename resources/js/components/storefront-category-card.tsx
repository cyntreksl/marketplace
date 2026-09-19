import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { StorefrontCategoryArtwork } from '@/components/storefront-category-artwork';
import { cn } from '@/lib/utils';

export function StorefrontCategoryCard({
    category,
    href,
    ariaLabel,
    className,
}: {
    category: { name: string; image_url: string | null };
    href: InertiaLinkProps['href'];
    ariaLabel?: string;
    className?: string;
}) {
    return (
        <Link
            href={href}
            prefetch
            aria-label={ariaLabel}
            className={cn(
                'group flex min-w-0 shrink-0 snap-start flex-col overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_2px_rgb(15_23_42/0.04)] transition duration-300 hover:-translate-y-1 hover:border-slate-300 hover:shadow-lg hover:shadow-slate-900/5 focus-visible:ring-2 focus-visible:ring-[#FF6D00] focus-visible:ring-offset-2 focus-visible:outline-none motion-reduce:transform-none',
                className,
            )}
        >
            {/* Uploaded artwork ships with a thin rounded frame near its edges; the slight zoom crops it out. */}
            <StorefrontCategoryArtwork
                category={category}
                className="aspect-square rounded-none bg-slate-50 text-[#FF6D00] ring-0"
                imageClassName="scale-[1.08] group-hover:scale-[1.13]"
            />
            <div className="flex flex-1 items-start justify-between gap-2 border-t border-slate-100 px-3.5 pt-3 pb-3.5">
                <h3 className="line-clamp-2 min-h-10 text-sm leading-5 font-semibold text-slate-900 transition group-hover:text-[#FF6D00]">
                    {category.name}
                </h3>
                <ArrowRight className="mt-0.5 size-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-[#FF6D00]" />
            </div>
        </Link>
    );
}
