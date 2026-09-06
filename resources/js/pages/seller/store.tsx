import { Form, Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { update } from '@/actions/App/Http/Controllers/SellerStoreController';
import { CategoryArtworkUploader } from '@/components/category-artwork-uploader';
import { PortalLayout } from '@/components/portal-layout';
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
        <PortalLayout portal="seller" title="Store settings">
            <main className="mx-auto max-w-4xl">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black tracking-tight">
                            Make your store yours
                        </h1>
                        <p className="mt-2 text-base text-slate-500">
                            Introduce {seller.store_name} with artwork and a
                            short story.
                        </p>
                    </div>
                    <Link
                        href={storeShow(seller.slug)}
                        className="inline-flex min-h-11 items-center gap-2 rounded-xl border px-4 text-sm font-bold"
                    >
                        View public store <ExternalLink className="size-4" />
                    </Link>
                </div>
                <Form
                    {...update.form()}
                    encType="multipart/form-data"
                    onSuccess={() => {
                        setRemoveLogo(false);
                        setRemoveCover(false);
                    }}
                    className="grid gap-6"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing, recentlySuccessful }) => (
                        <>
                            <section className="grid gap-6 rounded-2xl border bg-white p-6 dark:bg-slate-900">
                                <h2 className="text-xl font-bold">
                                    Store identity
                                </h2>
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
                                        removeLogo ? null : seller.logoUrl
                                    }
                                    imageError={errors.logo}
                                    cropError={errors.logo_crop}
                                />
                                {seller.logoUrl && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="remove_logo"
                                            value="1"
                                            checked={removeLogo}
                                            onChange={(event) =>
                                                setRemoveLogo(
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                        Remove current logo
                                    </label>
                                )}
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
                                        removeCover ? null : seller.coverUrl
                                    }
                                    imageError={errors.cover}
                                    cropError={errors.cover_crop}
                                />
                                {seller.coverUrl && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="remove_cover"
                                            value="1"
                                            checked={removeCover}
                                            onChange={(event) =>
                                                setRemoveCover(
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                        Remove current cover
                                    </label>
                                )}
                            </section>
                            <section className="rounded-2xl border bg-white p-6 dark:bg-slate-900">
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
                                    className="mt-4 w-full rounded-xl border p-4 text-base leading-7"
                                />
                            </section>
                            {Object.entries(errors).map(([key, error]) => (
                                <p
                                    role="alert"
                                    key={key}
                                    className="text-sm text-red-600"
                                >
                                    {error}
                                </p>
                            ))}
                            <div className="flex flex-wrap items-center gap-4">
                                <button
                                    disabled={processing}
                                    className="min-h-12 rounded-xl bg-primary px-6 text-sm font-bold text-primary-foreground disabled:opacity-50"
                                >
                                    {processing
                                        ? 'Publishing…'
                                        : 'Save and publish'}
                                </button>
                                <p
                                    role="status"
                                    className="text-sm text-slate-500"
                                >
                                    {recentlySuccessful
                                        ? 'Your store branding is now live.'
                                        : 'Changes appear on your public store when you save.'}
                                </p>
                            </div>
                        </>
                    )}
                </Form>
            </main>
        </PortalLayout>
    );
}
