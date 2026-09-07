import { Head, Link } from '@inertiajs/react';
import {
    show,
    updateDetails,
} from '@/actions/App/Http/Controllers/AdminListingController';
import type { CategoryOption } from '@/components/category-picker';
import { PortalLayout } from '@/components/portal-layout';
import { SellerProductForm } from '@/components/seller-product-form';
import type { SellerProductFormListing } from '@/components/seller-product-form';

type Brand = { id: number; name: string };

export default function EditAdminListing({
    listing,
    selectedCategory,
    brands,
}: {
    listing: SellerProductFormListing & { id: number; title: string | null };
    selectedCategory: CategoryOption | null;
    brands: Brand[];
}) {
    return (
        <PortalLayout portal="admin" title="Edit listing">
            <Head title={`Edit ${listing.title ?? 'listing'}`} />
            <main className="mx-auto max-w-[1480px]">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <Link
                            href={show(listing.id)}
                            className="text-sm font-bold text-primary"
                        >
                            ← Listing review
                        </Link>
                        <h1 className="mt-3 text-3xl font-black sm:text-4xl">
                            Edit {listing.title ?? 'listing'}
                        </h1>
                        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Correct product content, inventory, images,
                            variants, and search information before making a
                            moderation decision.
                        </p>
                    </div>
                    <Link
                        href={show(listing.id)}
                        className="inline-flex h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-bold dark:border-slate-700"
                    >
                        View review
                    </Link>
                </div>
                <SellerProductForm
                    form={updateDetails.form(listing.id)}
                    initialCategory={selectedCategory}
                    brands={brands}
                    listing={listing}
                    canSubmit
                    cancelHref={show.url(listing.id)}
                    mode="admin"
                />
            </main>
        </PortalLayout>
    );
}
