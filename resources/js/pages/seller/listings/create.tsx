import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { store } from '@/actions/App/Http/Controllers/SellerListingController';
import { PortalLayout } from '@/components/portal-layout';
import { SellerListingForm } from '@/components/seller-listing-form';
import { index } from '@/routes/seller/listings';

type Brand = { id: number; name: string };

export default function CreateSellerListing({
    brands,
    sellerStatus,
}: {
    brands: Brand[];
    sellerStatus: string;
}) {
    return (
        <PortalLayout portal="seller" title="Create listing">
            <Head title="Create listing" />
            <main className="mx-auto max-w-7xl">
                <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-black tracking-[0.2em] text-primary uppercase">
                            New product
                        </p>
                        <h1 className="mt-2 text-4xl font-black tracking-tight sm:text-5xl">
                            Create a listing
                        </h1>
                        <p className="mt-3 max-w-2xl text-base leading-7 text-stone-600 dark:text-stone-300">
                            Build a polished product page step by step, then
                            save it or send it to the marketplace review team.
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
                    form={store.form()}
                    initialCategory={null}
                    brands={brands}
                    canSubmit={['approved', 'active'].includes(sellerStatus)}
                />
            </main>
        </PortalLayout>
    );
}
