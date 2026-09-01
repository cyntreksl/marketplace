import { Head } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/SellerListingController';
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
