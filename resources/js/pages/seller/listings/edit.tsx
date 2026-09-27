import { Head, Link } from '@inertiajs/react';
import {
    show,
    update,
} from '@/actions/App/Http/Controllers/SellerListingController';
import type { CategoryOption } from '@/components/category-picker';
import { SellerInternalDetailsForm } from '@/components/seller-internal-details-form';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { SellerProductForm } from '@/components/seller-product-form';
import type { SellerProductFormListing } from '@/components/seller-product-form';

type Brand = { id: number; name: string };

export default function EditSellerListing({
    listing,
    selectedCategory,
    brands,
    sellerStatus,
    auctionFlags,
    auctionDefaults,
}: {
    listing: SellerProductFormListing & { id: number };
    selectedCategory: CategoryOption | null;
    brands: Brand[];
    sellerStatus: string;
    auctionFlags: {
        enabled: boolean;
        types: Record<'normal' | 'blind' | 'time_extended', boolean>;
    };
    auctionDefaults: {
        durationDays: number;
        extensionMinutes: number;
        startsAt: string;
        endsAt: string;
    };
}) {
    const canEditProduct = ['draft', 'changes_requested', 'rejected'].includes(
        listing.status,
    );

    return (
        <SellerPortalLayout title="Edit product">
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
                            {canEditProduct
                                ? 'Update the product information, inventory, images, and search details.'
                                : 'Update private costs, supplier, and notes. Product approval and public details stay as they are.'}
                        </p>
                    </div>
                    <Link
                        href={show(listing.id)}
                        className="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-bold dark:border-slate-700"
                    >
                        View product
                    </Link>
                </div>
                {listing.status === 'archived' ? (
                    <p className="mt-6">Archived products are read-only.</p>
                ) : !canEditProduct ? (
                    <SellerInternalDetailsForm listing={listing} />
                ) : (
                    <SellerProductForm
                        form={update.form(listing.id)}
                        initialCategory={selectedCategory}
                        brands={brands}
                        listing={listing}
                        canSubmit={['approved', 'active'].includes(
                            sellerStatus,
                        )}
                        auctionFlags={auctionFlags}
                        auctionDefaults={auctionDefaults}
                    />
                )}
            </main>
        </SellerPortalLayout>
    );
}
