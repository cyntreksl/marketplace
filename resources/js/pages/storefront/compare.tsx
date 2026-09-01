import { Link, useHttp } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect } from 'react';
import { StorefrontLayout } from '@/components/storefront-layout';
import { useProductComparison } from '@/hooks/use-product-comparison';
import {
    index as compareIndex,
    listings as comparisonListings,
} from '@/routes/compare';
import { show as listingShow } from '@/routes/listings';
import type { StorefrontCategory, StorefrontListing } from '@/types';

export default function Compare({
    categories,
}: {
    categories: StorefrontCategory[];
}) {
    const comparison = useProductComparison();
    const request = useHttp<
        { ids: number[] },
        { listings: StorefrontListing[] }
    >(() => ({ ids: comparison.ids }));
    const { get, setData } = request;

    useEffect(() => {
        setData('ids', comparison.ids);

        if (comparison.ids.length) {
            void get(comparisonListings.url()).catch(() => undefined);
        }
    }, [comparison.ids, get, setData]);

    const listings = request.response?.listings ?? [];

    return (
        <StorefrontLayout title="Compare products" categories={categories}>
            <main className="mx-auto max-w-[96rem] px-4 py-8 sm:px-6">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-xs font-bold text-[#ff5a00]">
                            PRODUCT COMPARISON
                        </p>
                        <h1 className="mt-1 text-3xl font-black">
                            Compare up to four products
                        </h1>
                    </div>
                    {comparison.ids.length > 0 && (
                        <button
                            onClick={comparison.clear}
                            className="text-xs font-bold text-slate-500"
                        >
                            Clear all
                        </button>
                    )}
                </div>
                {listings.length === 0 ? (
                    <div className="mt-8 rounded-xl border border-dashed p-12 text-center">
                        <p className="text-sm text-slate-500">
                            Add products using the Compare action on a product
                            page.
                        </p>
                        <Link
                            href="/listings"
                            className="mt-4 inline-block rounded-lg bg-[#ff5a00] px-5 py-3 text-xs font-bold text-white"
                        >
                            Browse products
                        </Link>
                    </div>
                ) : (
                    <div className="mt-7 overflow-x-auto">
                        <table className="w-full min-w-[44rem] border-separate border-spacing-0 text-left text-xs">
                            <thead>
                                <tr>
                                    <th className="w-32 border-b p-3 text-slate-400">
                                        Product
                                    </th>
                                    {listings.map((listing) => (
                                        <th
                                            key={listing.id}
                                            className="relative min-w-48 border-b p-3 align-top"
                                        >
                                            <button
                                                onClick={() =>
                                                    comparison.remove(
                                                        listing.id,
                                                    )
                                                }
                                                className="absolute top-2 right-2 grid size-7 place-items-center rounded-full border"
                                                aria-label={`Remove ${listing.title}`}
                                            >
                                                <X className="size-3" />
                                            </button>
                                            {listing.media[0] && (
                                                <img
                                                    src={
                                                        listing.media[0].cardUrl
                                                    }
                                                    alt=""
                                                    className="h-32 w-full object-contain"
                                                />
                                            )}
                                            <Link
                                                href={listingShow(listing.slug)}
                                                className="mt-2 block font-bold hover:text-[#ff5a00]"
                                            >
                                                {listing.title}
                                            </Link>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {[
                                    [
                                        'Price',
                                        (item: StorefrontListing) =>
                                            `Rs. ${Number(item.effectivePrice ?? 0).toLocaleString()}`,
                                    ],
                                    [
                                        'Brand',
                                        (item: StorefrontListing) =>
                                            item.brand?.name ?? '—',
                                    ],
                                    [
                                        'Condition',
                                        (item: StorefrontListing) =>
                                            item.condition,
                                    ],
                                    [
                                        'Stock',
                                        (item: StorefrontListing) =>
                                            item.stockStatus.replaceAll(
                                                '_',
                                                ' ',
                                            ),
                                    ],
                                    ...Array.from(
                                        new Set(
                                            listings.flatMap((item) =>
                                                Object.keys(
                                                    item.specifications,
                                                ),
                                            ),
                                        ),
                                    ).map(
                                        (name) =>
                                            [
                                                name,
                                                (item: StorefrontListing) =>
                                                    String(
                                                        item.specifications[
                                                            name
                                                        ] ?? '—',
                                                    ),
                                            ] as const,
                                    ),
                                ].map(([label, read]) => (
                                    <tr key={String(label)}>
                                        <th className="border-b bg-slate-50 p-3 font-bold">
                                            {String(label)}
                                        </th>
                                        {listings.map((listing) => (
                                            <td
                                                key={listing.id}
                                                className="border-b p-3 text-slate-600"
                                            >
                                                {(
                                                    read as (
                                                        item: StorefrontListing,
                                                    ) => string
                                                )(listing)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Link href={compareIndex()} className="sr-only">
                    Comparison page
                </Link>
            </main>
        </StorefrontLayout>
    );
}
