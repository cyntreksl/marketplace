import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { update } from '@/actions/App/Http/Controllers/SellerListingController';
import type { CategoryOption } from '@/components/category-picker';
import { PortalLayout } from '@/components/portal-layout';
import { SellerListingForm } from '@/components/seller-listing-form';
import type { SellerListingFormListing } from '@/components/seller-listing-form';
import { index } from '@/routes/seller/listings';

type Brand = { id: number; name: string };

export default function EditSellerListing({
    listing,
    selectedCategory,
    brands,
    sellerStatus,
}: {
    listing: SellerListingFormListing & { id: number };
    selectedCategory: CategoryOption;
    brands: Brand[];
    sellerStatus: string;
}) {
    return (
        <PortalLayout portal="seller" title="Edit listing">
            <Head title="Edit listing" />
            <main className="mx-auto max-w-7xl">
                <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-black tracking-[0.2em] text-amber-700 uppercase dark:text-amber-400">
                            Listing studio
                        </p>
                        <h1 className="mt-2 text-4xl font-black tracking-tight sm:text-5xl">
                            Edit listing
                        </h1>
                        <p className="mt-3 max-w-2xl text-base leading-7 text-stone-600 dark:text-stone-300">
                            Refine the product story, selling details, and
                            photos before sending the draft for another review.
                        </p>
                    </div>
                    <Link
                        href={index()}
                        className="inline-flex shrink-0 items-center justify-center gap-2 self-start rounded-xl border border-stone-300 bg-white px-4 py-3 text-sm font-bold transition hover:border-stone-400 hover:bg-stone-50 sm:self-auto dark:border-stone-700 dark:bg-stone-900 dark:hover:bg-stone-800"
                    >
                        <ArrowLeft className="size-4" />
                        Your listings
                    </Link>
                </header>
                <SellerListingForm
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
