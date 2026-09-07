import { Form, Head, Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { update } from '@/actions/App/Http/Controllers/SellerStoreController';
import { CategoryArtworkUploader } from '@/components/category-artwork-uploader';
import { SellerPageHeader } from '@/components/seller-page-header';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { show as storeShow } from '@/routes/stores';
import type { PublicSellerSummary } from '@/types';

export default function StoreSettings({
    seller,
}: {
    seller: PublicSellerSummary;
}) {
    const [removeLogo, setRemoveLogo] = useState(false);
    const [removeCover, setRemoveCover] = useState(false);

    return (
        <SellerPortalLayout title="Storefront">
            <Head title="Storefront settings" />
            <div className="space-y-6">
                <SellerPageHeader
                    eyebrow="Storefront settings"
                    title="Make your store yours"
                    description={`Introduce ${seller.store_name} with polished artwork and a short story shoppers can trust.`}
                    actions={
                        <Link
                            href={storeShow(seller.slug)}
                            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-bold shadow-sm transition hover:border-orange-300 hover:text-orange-700 dark:border-slate-700 dark:bg-slate-900"
                        >
                            View public store{' '}
                            <ExternalLink className="size-4" aria-hidden />
                        </Link>
                    }
                />
                <Form
                    {...update.form()}
                    encType="multipart/form-data"
                    onSuccess={() => {
                        setRemoveLogo(false);
                        setRemoveCover(false);
                    }}
                    className="grid gap-5"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <div className="border-b border-slate-100 px-5 py-5 sm:px-6 dark:border-slate-800">
                                    <h2 className="text-xl font-black">
                                        Store identity
                                    </h2>
                                    <p className="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                        Use clear, high-quality artwork that is
                                        easy to recognize on every screen.
                                    </p>
                                </div>
                                <div className="grid gap-7 p-5 sm:p-6">
                                    <div className="grid gap-3">
                                        <CategoryArtworkUploader
                                            key={`logo-${seller.logoUrl}`}
                                            id="store-logo"
                                            name="logo"
                                            cropName="logo_crop"
                                            label="Store logo"
                                            description="Square artwork · JPG, PNG or WebP · up to 5 MB"
                                            aspect={1}
                                            minimumWidth={128}
                                            minimumHeight={128}
                                            existingUrl={
                                                removeLogo
                                                    ? null
                                                    : seller.logoUrl
                                            }
                                            imageError={errors.logo}
                                            cropError={errors.logo_crop}
                                        />
                                        {seller.logoUrl && (
                                            <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                                <input
                                                    type="checkbox"
                                                    name="remove_logo"
                                                    value="1"
                                                    checked={removeLogo}
                                                    onChange={(event) =>
                                                        setRemoveLogo(
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                    className="size-4 accent-orange-600"
                                                />
                                                Remove current logo
                                            </label>
                                        )}
                                    </div>
                                    <div className="border-t border-slate-100 pt-7 dark:border-slate-800">
                                        <CategoryArtworkUploader
                                            key={`cover-${seller.coverUrl}`}
                                            id="store-cover"
                                            name="cover"
                                            cropName="cover_crop"
                                            label="Store cover"
                                            description="Wide 4:1 artwork · at least 800 × 200 pixels · up to 5 MB"
                                            aspect={4}
                                            minimumWidth={800}
                                            minimumHeight={200}
                                            existingUrl={
                                                removeCover
                                                    ? null
                                                    : seller.coverUrl
                                            }
                                            imageError={errors.cover}
                                            cropError={errors.cover_crop}
                                        />
                                        {seller.coverUrl && (
                                            <label className="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                                <input
                                                    type="checkbox"
                                                    name="remove_cover"
                                                    value="1"
                                                    checked={removeCover}
                                                    onChange={(event) =>
                                                        setRemoveCover(
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                    className="size-4 accent-orange-600"
                                                />
                                                Remove current cover
                                            </label>
                                        )}
                                    </div>
                                </div>
                            </section>
                            <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                                <label
                                    htmlFor="store-about"
                                    className="text-xl font-bold"
                                >
                                    About your store
                                </label>
                                <p className="mt-2 text-sm text-slate-500">
                                    Tell shoppers what you sell and what makes
                                    your store special. Up to 2,000 characters.
                                </p>
                                <textarea
                                    id="store-about"
                                    name="about"
                                    maxLength={2000}
                                    defaultValue={seller.about ?? ''}
                                    rows={6}
                                    className="mt-4 w-full rounded-xl border border-slate-200 bg-transparent p-4 text-base leading-7 transition outline-none focus:border-orange-500 focus:ring-3 focus:ring-orange-500/10 dark:border-slate-700"
                                />
                                {errors.about && (
                                    <p className="mt-2 text-sm text-red-600">
                                        {errors.about}
                                    </p>
                                )}
                            </section>
                            <div className="flex flex-col-reverse gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-5 dark:border-slate-800 dark:bg-slate-900">
                                <p
                                    role="status"
                                    className="text-sm leading-6 text-slate-500 dark:text-slate-400"
                                >
                                    {recentlySuccessful
                                        ? 'Your store branding is now live.'
                                        : 'Changes appear on your public store when you save.'}
                                </p>
                                <button
                                    disabled={processing}
                                    className="min-h-12 rounded-xl bg-primary px-6 text-sm font-bold text-primary-foreground shadow-sm transition hover:opacity-90 disabled:opacity-50"
                                >
                                    {processing
                                        ? 'Publishing…'
                                        : 'Save and publish'}
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </SellerPortalLayout>
    );
}
