import { InfiniteScroll } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { ListingCard } from '@/components/listing-card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { StorefrontListingPaginator } from '@/types';

function ProductCardSkeleton() {
    return (
        <div
            aria-hidden
            className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
        >
            <Skeleton className="aspect-[1.02/1] w-full rounded-none bg-orange-50 motion-reduce:animate-none" />
            <div className="space-y-3 p-3 sm:p-4">
                <Skeleton className="h-5 w-4/5 motion-reduce:animate-none" />
                <Skeleton className="h-7 w-3/5 motion-reduce:animate-none" />
                <Skeleton className="h-4 w-2/5 motion-reduce:animate-none" />
            </div>
        </div>
    );
}

function ProductGridLoading({ className }: { className?: string }) {
    return (
        <div role="status" aria-live="polite" className="col-span-full mt-6">
            <span className="sr-only">Loading more products</span>
            <div className={cn('grid grid-cols-2 gap-3', className)}>
                {Array.from({ length: 6 }, (_, index) => (
                    <ProductCardSkeleton key={index} />
                ))}
            </div>
        </div>
    );
}

export function StorefrontProductGrid({
    listings,
    className,
}: {
    listings: StorefrontListingPaginator;
    className?: string;
}) {
    return (
        <InfiniteScroll
            data="listings"
            buffer={800}
            params={{ only: ['seo'] }}
            className={cn('grid grid-cols-2 gap-3', className)}
            previous={({ loading }) =>
                loading ? <ProductGridLoading className={className} /> : null
            }
            next={({ loading, hasMore }) => {
                if (loading) {
                    return <ProductGridLoading className={className} />;
                }

                if (hasMore) {
                    return null;
                }

                return (
                    <p
                        role="status"
                        aria-live="polite"
                        className="col-span-full mt-8 flex items-center justify-center gap-2 text-sm font-semibold text-slate-500"
                    >
                        <Check
                            className="size-4 text-emerald-600"
                            aria-hidden
                        />
                        All {listings.total}{' '}
                        {listings.total === 1 ? 'product' : 'products'} loaded
                    </p>
                );
            }}
        >
            {listings.data.map((listing) => (
                <ListingCard key={listing.id} listing={listing} />
            ))}
        </InfiniteScroll>
    );
}
