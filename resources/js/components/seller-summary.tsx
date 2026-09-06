import { Link } from '@inertiajs/react';
import { ArrowUpRight, Store } from 'lucide-react';
import { show as storeShow } from '@/routes/stores';
import type { PublicSellerSummary } from '@/types';

export function SellerLogo({
    seller,
    className = 'size-12',
}: {
    seller: PublicSellerSummary;
    className?: string;
}) {
    return (
        <span
            className={`grid shrink-0 place-items-center overflow-hidden rounded-2xl border border-orange-100 bg-orange-50 text-lg font-bold text-orange-800 ${className}`}
        >
            {seller.logoUrl ? (
                <img
                    src={seller.logoUrl}
                    alt={`${seller.store_name} logo`}
                    width={128}
                    height={128}
                    className="size-full object-cover"
                />
            ) : (
                seller.store_name
                    .split(/\s+/)
                    .slice(0, 2)
                    .map((word) => word[0])
                    .join('')
                    .toUpperCase()
            )}
        </span>
    );
}

export function SellerSummary({
    seller,
    compact = false,
}: {
    seller: PublicSellerSummary;
    compact?: boolean;
}) {
    return (
        <aside
            aria-label="About the seller"
            className={
                compact
                    ? 'mt-5 border-t border-slate-200 pt-4'
                    : 'mt-5 rounded-xl border border-slate-200 bg-white p-4'
            }
        >
            <div className="flex flex-wrap items-center gap-3">
                <SellerLogo seller={seller} />
                <div className="min-w-24 flex-1">
                    <p className="text-sm text-slate-500">Sold by</p>
                    <Link
                        href={storeShow(seller.slug)}
                        className="font-bold text-slate-950 hover:text-orange-700"
                    >
                        {seller.store_name}
                    </Link>
                </div>
                <Link
                    href={storeShow(seller.slug)}
                    className={
                        compact
                            ? 'inline-flex min-h-10 w-full items-center justify-between text-sm font-bold text-orange-700'
                            : 'inline-flex min-h-11 shrink-0 items-center gap-1 text-sm font-bold text-orange-700'
                    }
                >
                    Visit store <ArrowUpRight className="size-4" />
                </Link>
            </div>
            <div className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                <span className="inline-flex items-center gap-1.5">
                    <Store className="size-4" />
                    Approved seller
                </span>
                <span>{seller.productCount} available products</span>
                {seller.sellingSince && (
                    <span>Selling since {seller.sellingSince}</span>
                )}
            </div>
            {seller.about && (
                <p className="mt-3 line-clamp-2 text-sm leading-6 text-slate-600">
                    {seller.about}
                </p>
            )}
        </aside>
    );
}
