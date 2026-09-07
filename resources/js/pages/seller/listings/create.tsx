import { Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/SellerListingController';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { SellerProductForm } from '@/components/seller-product-form';

type Brand = { id: number; name: string };

export default function CreateSellerListing({
    brands,
    sellerStatus,
}: {
    brands: Brand[];
    sellerStatus: string;
}) {
    return (
        <SellerPortalLayout title="Add new product">
            <Head title="Add New Product" />
            <main className="mx-auto max-w-[1480px]">
                <SellerProductForm
                    form={store.form()}
                    initialCategory={null}
                    brands={brands}
                    canSubmit={['approved', 'active'].includes(sellerStatus)}
                />
            </main>
        </SellerPortalLayout>
    );
}
