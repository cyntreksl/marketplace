import { Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    Baby,
    BriefcaseBusiness,
    Camera,
    Footprints,
    Gamepad2,
    Gem,
    House,
    Monitor,
    PackageSearch,
    Shirt,
    ShoppingBag,
    Sparkles,
    Watch,
} from 'lucide-react';
import { index as listingsIndex } from '@/routes/listings';
import type { StorefrontCategoryNode } from '@/types';

function CategoryVisualIcon({ name }: { name: string }) {
    const className = 'size-5';

    if (/baby|toddler/i.test(name)) {
        return <Baby className={className} />;
    }

    if (/jewel|ring|necklace|bracelet/i.test(name)) {
        return <Gem className={className} />;
    }

    if (/shoe|footwear/i.test(name)) {
        return <Footprints className={className} />;
    }

    if (/watch|clock/i.test(name)) {
        return <Watch className={className} />;
    }

    if (/handbag|wallet|bag|luggage/i.test(name)) {
        return <ShoppingBag className={className} />;
    }

    if (/costume|beauty|accessor/i.test(name)) {
        return <Sparkles className={className} />;
    }

    if (/cloth|apparel|shirt|fashion/i.test(name)) {
        return <Shirt className={className} />;
    }

    if (/camera|optic/i.test(name)) {
        return <Camera className={className} />;
    }

    if (/computer|electronic|mobile|monitor/i.test(name)) {
        return <Monitor className={className} />;
    }

    if (/game|toy/i.test(name)) {
        return <Gamepad2 className={className} />;
    }

    if (/home|garden|furniture/i.test(name)) {
        return <House className={className} />;
    }

    if (/business|office/i.test(name)) {
        return <BriefcaseBusiness className={className} />;
    }

    return <PackageSearch className={className} />;
}

export function StorefrontCategoryCard({
    category,
    hasChildren,
}: {
    category: StorefrontCategoryNode;
    hasChildren: boolean;
}) {
    return (
        <Link
            href={listingsIndex({ query: { category: category.slug } })}
            prefetch
            className="group relative min-h-36 overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/60 transition duration-300 hover:-translate-y-1 hover:border-primary/30 hover:shadow-xl hover:shadow-primary/10 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:outline-none dark:border-slate-800 dark:bg-slate-900 dark:shadow-none dark:focus-visible:ring-offset-slate-950"
        >
            <div className="absolute -top-12 -right-12 size-32 rounded-full bg-primary/10 transition duration-500 group-hover:scale-125 dark:bg-primary/10" />
            <div className="relative flex h-full flex-col justify-between gap-5">
                <div className="flex items-start justify-between gap-3">
                    <span className="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/10 transition group-hover:bg-primary group-hover:text-primary-foreground">
                        <CategoryVisualIcon name={category.name} />
                    </span>
                    <ArrowUpRight className="size-5 text-slate-300 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary dark:text-slate-600" />
                </div>
                <div>
                    <h3 className="font-black tracking-tight text-slate-950 transition group-hover:text-primary dark:text-white">
                        {category.name}
                    </h3>
                    <p className="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">
                        {hasChildren ? 'Browse subcategories' : 'View products'}
                    </p>
                </div>
            </div>
        </Link>
    );
}
