import { Eye, Heart, ShoppingBag } from 'lucide-react';
import type { ComponentType } from 'react';
import type { StorefrontEngagement } from '@/types';

type Metric = {
    key: keyof StorefrontEngagement;
    count: number;
    label: string;
    icon: ComponentType<{ className?: string }>;
};

const numberFormatter = new Intl.NumberFormat('en-LK');

export function ProductEngagement({
    engagement,
}: {
    engagement: StorefrontEngagement;
}) {
    const metrics = (
        [
            {
                key: 'soldCount',
                count: engagement.soldCount,
                label: engagement.soldCount === 1 ? 'item sold' : 'items sold',
                icon: ShoppingBag,
            },
            {
                key: 'watcherCount',
                count: engagement.watcherCount,
                label:
                    engagement.watcherCount === 1
                        ? 'person watching'
                        : 'people watching',
                icon: Heart,
            },
            {
                key: 'viewCount',
                count: engagement.viewCount,
                label: engagement.viewCount === 1 ? 'view' : 'views',
                icon: Eye,
            },
        ] satisfies Metric[]
    ).filter((metric) => {
        if (metric.key === 'soldCount') {
            return metric.count > 0;
        }

        if (metric.key === 'watcherCount') {
            return metric.count >= 2;
        }

        return metric.count >= 10;
    });

    if (metrics.length === 0) {
        return null;
    }

    return (
        <div
            aria-label="Product engagement"
            className="mt-3 flex flex-wrap gap-x-4 gap-y-2 border-t border-rose-100 pt-3 text-xs font-semibold text-slate-600"
        >
            {metrics.map((metric) => {
                const Icon = metric.icon;

                return (
                    <span
                        key={metric.key}
                        className="inline-flex items-center gap-1.5"
                    >
                        <Icon className="size-4 text-orange-600" />
                        <strong className="text-slate-900">
                            {numberFormatter.format(metric.count)}
                        </strong>{' '}
                        {metric.label}
                    </span>
                );
            })}
        </div>
    );
}
