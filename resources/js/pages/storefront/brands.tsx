import { Link } from '@inertiajs/react';
import { StorefrontLayout } from '@/components/storefront-layout';
import type { StorefrontCategory } from '@/types';

type Brand = {
    id: number;
    name: string;
    slug: string;
    logoUrl: string | null;
    listingCount: number;
};
export default function Brands({
    categories,
    brands,
}: {
    categories: StorefrontCategory[];
    brands: Brand[];
}) {
    return (
        <StorefrontLayout title="Brands" categories={categories}>
            <main className="mx-auto max-w-[96rem] px-4 py-8 sm:px-6">
                <p className="text-xs font-bold text-[#ff5a00]">
                    SHOP BY BRAND
                </p>
                <h1 className="mt-1 text-3xl font-black">Brand directory</h1>
                <div className="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    {brands.map((brand) => (
                        <Link
                            key={brand.id}
                            href={`/listings?brand=${encodeURIComponent(brand.slug)}`}
                            className="flex min-h-32 flex-col items-center justify-center rounded-xl border p-5 text-center hover:border-orange-200 hover:shadow"
                        >
                            <>
                                {brand.logoUrl ? (
                                    <img
                                        src={brand.logoUrl}
                                        alt={brand.name}
                                        className="max-h-12 max-w-32 object-contain"
                                    />
                                ) : (
                                    <span className="text-lg font-black">
                                        {brand.name}
                                    </span>
                                )}
                            </>
                            <span className="mt-3 text-[10px] text-slate-400">
                                {brand.listingCount} products
                            </span>
                        </Link>
                    ))}
                </div>
            </main>
        </StorefrontLayout>
    );
}
