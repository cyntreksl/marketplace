import { Head } from '@inertiajs/react';
import { store } from '@/actions/App/Http/Controllers/SellerListingController';
import { PortalLayout } from '@/components/portal-layout';
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
        <PortalLayout portal="seller" title="Add New Product">
            <Head title="Add New Product" />
            <main className="mx-auto max-w-[1480px]">
                <SellerProductForm
                    form={store.form()}
                    initialCategory={null}
                    brands={brands}
                    canSubmit={['approved', 'active'].includes(sellerStatus)}
                />
            </main>
        </PortalLayout>
    );
}
