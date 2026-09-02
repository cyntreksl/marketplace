import { Head, Link } from '@inertiajs/react';
import {
    show,
    update,
} from '@/actions/App/Http/Controllers/SellerListingController';
import type { CategoryOption } from '@/components/category-picker';
import { PortalLayout } from '@/components/portal-layout';
import { SellerProductForm } from '@/components/seller-product-form';
import type { SellerProductFormListing } from '@/components/seller-product-form';

type Brand = { id: number; name: string };

export default function EditSellerListing({
    listing,
    selectedCategory,
    brands,
    sellerStatus,
}: {
    listing: SellerProductFormListing & { id: number };
    selectedCategory: CategoryOption | null;
    brands: Brand[];
    sellerStatus: string;
}) {
    return (
        <PortalLayout portal="seller" title="Edit Product">
            <Head title="Edit Product" />
            <main className="mx-auto max-w-[1480px]">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <Link
                            href={show(listing.id)}
                            className="text-sm font-bold text-primary"
                        >
                            ← Product details
                        </Link>
                        <h1 className="mt-3 text-3xl font-black sm:text-4xl">
                            Edit {listing.title ?? 'Product'}
                        </h1>
                        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Update the product information, inventory, images,
                            and search details.
                        </p>
                    </div>
                    <Link
                        href={show(listing.id)}
                        className="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-bold dark:border-slate-700"
                    >
                        View product
                    </Link>
                </div>
                <SellerProductForm
                    form={update.form(listing.id)}
                    initialCategory={selectedCategory}
                    brands={brands}
                    listing={listing}
                    canSubmit={['approved', 'active'].includes(sellerStatus)}
                />
            </main>
        </PortalLayout>
    );
}
