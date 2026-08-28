import {
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
import { cn } from '@/lib/utils';

type CategoryArtworkData = {
    name: string;
    image_url: string | null;
};

function CategoryVisualIcon({ name }: { name: string }) {
    const className = 'size-8';

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

export function StorefrontCategoryArtwork({
    category,
    fallback = 'icon',
    className,
}: {
    category: CategoryArtworkData;
    fallback?: 'icon' | 'initial';
    className?: string;
}) {
    return (
        <span
            className={cn(
                'grid aspect-square w-full place-items-center overflow-hidden rounded-2xl bg-primary/10 text-2xl font-black text-primary ring-1 ring-primary/10',
                className,
            )}
        >
            {category.image_url ? (
                <img
                    src={category.image_url}
                    alt=""
                    className="size-full object-cover transition duration-500 group-hover:scale-105"
                />
            ) : fallback === 'initial' ? (
                category.name.charAt(0).toUpperCase()
            ) : (
                <CategoryVisualIcon name={category.name} />
            )}
        </span>
    );
}
