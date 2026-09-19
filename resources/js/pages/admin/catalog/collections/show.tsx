import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import {
    destroy,
    destroyBannerImage,
    destroyImage,
    destroyVerticalImage,
    index,
    restore,
    storeBannerImage,
    storeImage,
    storeVerticalImage,
    update,
    updateActivation,
} from '@/actions/App/Http/Controllers/AdminCollectionController';
import { CategoryArtworkUploader } from '@/components/category-artwork-uploader';
import { ListingPicker } from '@/components/listing-picker';
import { PortalLayout } from '@/components/portal-layout';
import { Badge } from '@/components/ui/badge';

type AdminCollection = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    type: 'rule' | 'manual';
    rule_key: string | null;
    image_url: string | null;
    banner_image_url: string | null;
    vertical_image_url: string | null;
    homepage_banner_side: 'left' | 'right' | null;
    is_active: boolean;
    show_on_homepage_tile: boolean;
    show_on_homepage_grid: boolean;
    show_in_navigation: boolean;
    sort_order: number;
    seo_title: string | null;
    seo_description: string | null;
    seo_intro: string | null;
    listings: { id: number; title: string }[];
    deleted_at: string | null;
};

const cardClassName =
    'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900';
const inputClassName =
    'rounded-lg border border-slate-200 bg-transparent p-2.5 text-sm dark:border-slate-700';
const labelClassName = 'flex items-center gap-2 text-sm';

function CollectionDetailsForm({
    collection,
}: {
    collection: AdminCollection;
}) {
    return (
        <div className={cardClassName}>
            <h2 className="text-lg font-bold">Details</h2>
            <Form {...update.form(collection.id)} className="mt-4 grid gap-3">
                {({ errors, processing }) => (
                    <>
                        <label className="grid gap-1 text-sm font-bold">
                            Name
                            <input
                                required
                                name="name"
                                defaultValue={collection.name}
                                className={inputClassName}
                            />
                        </label>
                        {errors.name && (
                            <p className="text-sm text-red-600">
                                {errors.name}
                            </p>
                        )}
                        <label className="grid gap-1 text-sm font-bold">
                            URL slug
                            <input
                                name="slug"
                                defaultValue={collection.slug}
                                className={inputClassName}
                            />
                        </label>
                        <label className="grid gap-1 text-sm font-bold">
                            Description
                            <textarea
                                name="description"
                                maxLength={2000}
                                defaultValue={collection.description ?? ''}
                                className={`${inputClassName} min-h-20`}
                            />
                        </label>
                        <label className="grid gap-1 text-sm font-bold">
                            SEO title
                            <input
                                name="seo_title"
                                defaultValue={collection.seo_title ?? ''}
                                placeholder="Falls back to the collection name"
                                className={inputClassName}
                            />
                        </label>
                        <label className="grid gap-1 text-sm font-bold">
                            SEO description
                            <textarea
                                name="seo_description"
                                maxLength={320}
                                defaultValue={collection.seo_description ?? ''}
                                className={`${inputClassName} min-h-16`}
                            />
                        </label>
                        <label className="grid gap-1 text-sm font-bold">
                            Visible intro text
                            <textarea
                                name="seo_intro"
                                maxLength={5000}
                                defaultValue={collection.seo_intro ?? ''}
                                className={`${inputClassName} min-h-20`}
                            />
                        </label>

                        <div className="grid gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                            <input type="hidden" name="is_active" value="0" />
                            <label className={labelClassName}>
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={collection.is_active}
                                />
                                Active
                            </label>
                            <input
                                type="hidden"
                                name="show_on_homepage_tile"
                                value="0"
                            />
                            <label className={labelClassName}>
                                <input
                                    type="checkbox"
                                    name="show_on_homepage_tile"
                                    value="1"
                                    defaultChecked={
                                        collection.show_on_homepage_tile
                                    }
                                />
                                Show as homepage tile
                            </label>
                            <input
                                type="hidden"
                                name="show_on_homepage_grid"
                                value="0"
                            />
                            <label className={labelClassName}>
                                <input
                                    type="checkbox"
                                    name="show_on_homepage_grid"
                                    value="1"
                                    defaultChecked={
                                        collection.show_on_homepage_grid
                                    }
                                />
                                Show as homepage product section
                            </label>
                            <input
                                type="hidden"
                                name="show_in_navigation"
                                value="0"
                            />
                            <label className={labelClassName}>
                                <input
                                    type="checkbox"
                                    name="show_in_navigation"
                                    value="1"
                                    defaultChecked={
                                        collection.show_in_navigation
                                    }
                                />
                                Show in header nav &amp; footer
                            </label>
                        </div>

                        <label className="grid gap-1 text-sm font-bold">
                            Homepage banner side
                            <select
                                name="homepage_banner_side"
                                defaultValue={
                                    collection.homepage_banner_side ?? ''
                                }
                                className={inputClassName}
                            >
                                <option value="">None (grid only)</option>
                                <option value="left">
                                    Banner left, products right
                                </option>
                                <option value="right">
                                    Products left, banner right
                                </option>
                            </select>
                        </label>
                        <p className="text-xs text-slate-500">
                            When set, the collection banner image is shown
                            alongside products on the homepage section.
                        </p>

                        <label className="grid gap-1 text-sm font-bold">
                            Sort order
                            <input
                                type="number"
                                min="0"
                                name="sort_order"
                                defaultValue={collection.sort_order}
                                className={`${inputClassName} max-w-32`}
                            />
                        </label>

                        <div className="border-t border-slate-100 pt-3 dark:border-slate-800">
                            {collection.type === 'manual' ? (
                                <>
                                    <p className="mb-1 text-sm font-bold">
                                        Products
                                    </p>
                                    <ListingPicker
                                        name="listing_ids"
                                        initialSelected={collection.listings}
                                    />
                                    {errors.listing_ids && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.listing_ids}
                                        </p>
                                    )}
                                </>
                            ) : (
                                <p className="rounded-lg bg-slate-50 p-3 text-sm text-slate-500 dark:bg-slate-800">
                                    This is a rule-based collection (
                                    <code>{collection.rule_key}</code>) —
                                    products are determined automatically from
                                    listing merchandising flags, not
                                    hand-picked.
                                </p>
                            )}
                        </div>

                        <label className="grid gap-1 text-sm font-bold">
                            Update reason
                            <input
                                required
                                minLength={5}
                                name="reason"
                                placeholder="Why are you making this change?"
                                className={inputClassName}
                            />
                        </label>
                        <button
                            disabled={processing}
                            className="justify-self-start rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-primary-foreground"
                        >
                            Save collection
                        </button>
                    </>
                )}
            </Form>
        </div>
    );
}

function ArtworkCard({
    title,
    description,
    uploaderId,
    aspect,
    minimumWidth,
    minimumHeight,
    existingUrl,
    storeAction,
    destroyAction,
}: {
    title: string;
    description: string;
    uploaderId: string;
    aspect: number;
    minimumWidth: number;
    minimumHeight: number;
    existingUrl: string | null;
    storeAction: ReturnType<typeof storeImage.form>;
    destroyAction: ReturnType<typeof destroyImage.form>;
}) {
    return (
        <div className={cardClassName}>
            <Form {...storeAction} className="grid gap-3">
                {({ errors, processing }) => (
                    <>
                        <CategoryArtworkUploader
                            id={uploaderId}
                            name="image"
                            cropName="crop"
                            label={title}
                            description={description}
                            aspect={aspect}
                            minimumWidth={minimumWidth}
                            minimumHeight={minimumHeight}
                            existingUrl={existingUrl}
                            imageError={errors.image}
                            cropError={errors.crop}
                        />
                        <input
                            required
                            minLength={5}
                            name="reason"
                            placeholder="Update reason"
                            className={inputClassName}
                        />
                        <button
                            disabled={processing}
                            className="justify-self-start rounded-lg border border-primary/40 px-4 py-2 text-sm font-bold text-primary"
                        >
                            Save
                        </button>
                    </>
                )}
            </Form>
            {existingUrl && (
                <Form
                    {...destroyAction}
                    className="mt-3 grid gap-2 border-t border-slate-100 pt-3 dark:border-slate-800"
                >
                    {({ processing }) => (
                        <>
                            <input
                                required
                                minLength={5}
                                name="reason"
                                placeholder="Removal reason"
                                className={inputClassName}
                            />
                            <button
                                disabled={processing}
                                className="justify-self-start rounded-lg px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-50"
                            >
                                Remove image
                            </button>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}

function ActivationCard({ collection }: { collection: AdminCollection }) {
    return (
        <div className={cardClassName}>
            <h2 className="text-lg font-bold">Availability</h2>
            <p className="mt-1 text-sm text-slate-500">
                {collection.is_active
                    ? 'This collection is live on the storefront.'
                    : 'This collection is hidden from the storefront.'}
            </p>
            <Form
                {...updateActivation.form(collection.id)}
                className="mt-4 grid gap-2"
            >
                {({ processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="is_active"
                            value={collection.is_active ? '0' : '1'}
                        />
                        <input
                            required
                            minLength={5}
                            name="reason"
                            placeholder="Toggle reason"
                            className={inputClassName}
                        />
                        <button
                            disabled={processing}
                            className="justify-self-start rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:border-slate-700"
                        >
                            {collection.is_active ? 'Deactivate' : 'Activate'}
                        </button>
                    </>
                )}
            </Form>
        </div>
    );
}

function ArchiveCard({ collection }: { collection: AdminCollection }) {
    if (collection.deleted_at) {
        return (
            <div className={cardClassName}>
                <h2 className="text-lg font-bold">Archived</h2>
                <p className="mt-1 text-sm text-slate-500">
                    This collection is archived and hidden everywhere. Restore
                    it to make it manageable again.
                </p>
                <Form
                    {...restore.form(collection.id)}
                    className="mt-4 grid gap-2"
                >
                    {({ processing }) => (
                        <>
                            <input
                                required
                                minLength={5}
                                name="reason"
                                placeholder="Restore reason"
                                className={inputClassName}
                            />
                            <button
                                disabled={processing}
                                className="justify-self-start rounded-lg border border-primary/40 px-4 py-2 text-sm font-bold text-primary"
                            >
                                Restore collection
                            </button>
                        </>
                    )}
                </Form>
            </div>
        );
    }

    return (
        <div className={cardClassName}>
            <h2 className="text-lg font-bold">Danger zone</h2>
            <p className="mt-1 text-sm text-slate-500">
                Archiving hides this collection from the storefront, homepage,
                and navigation.
            </p>
            <Form {...destroy.form(collection.id)} className="mt-4 grid gap-2">
                {({ processing }) => (
                    <>
                        <input
                            required
                            minLength={5}
                            name="reason"
                            placeholder="Archive reason"
                            className={inputClassName}
                        />
                        <button
                            disabled={processing}
                            className="justify-self-start rounded-lg px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-50"
                        >
                            Archive collection
                        </button>
                    </>
                )}
            </Form>
        </div>
    );
}

export default function CollectionShow({
    collection,
}: {
    collection: AdminCollection;
}) {
    return (
        <PortalLayout portal="admin" title="Catalog collections">
            <Head title={collection.name} />
            <div className="space-y-6">
                <div>
                    <Link
                        href={index()}
                        className="inline-flex items-center gap-1 text-sm font-bold text-primary"
                    >
                        <ArrowLeft className="size-4" />
                        All collections
                    </Link>
                    <div className="mt-3 flex flex-wrap items-center gap-3">
                        <h1 className="text-3xl font-black">
                            {collection.name}
                        </h1>
                        {collection.deleted_at ? (
                            <Badge variant="destructive">Archived</Badge>
                        ) : collection.is_active ? (
                            <Badge>Active</Badge>
                        ) : (
                            <Badge variant="outline">Inactive</Badge>
                        )}
                        {collection.type === 'rule' ? (
                            <Badge variant="secondary">
                                Rule · {collection.rule_key}
                            </Badge>
                        ) : (
                            <Badge variant="outline">Manual</Badge>
                        )}
                    </div>
                    <p className="mt-1 text-sm text-slate-500">
                        /collections/{collection.slug}
                    </p>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <CollectionDetailsForm collection={collection} />
                    <div className="grid gap-6">
                        <ArtworkCard
                            title="Homepage tile & collection card image"
                            description="1:1 square — minimum 800 × 800 px. Used as the collection card image throughout the site."
                            uploaderId={`collection-image-${collection.id}`}
                            aspect={1}
                            minimumWidth={800}
                            minimumHeight={800}
                            existingUrl={collection.image_url}
                            storeAction={storeImage.form(collection.id)}
                            destroyAction={destroyImage.form(collection.id)}
                        />
                        <ArtworkCard
                            title="Collection page banner"
                            description="16:5 landscape — minimum 1600 × 500 px. Shown at the top of the collection page and as the homepage section banner."
                            uploaderId={`collection-banner-${collection.id}`}
                            aspect={1600 / 500}
                            minimumWidth={1600}
                            minimumHeight={500}
                            existingUrl={collection.banner_image_url}
                            storeAction={storeBannerImage.form(collection.id)}
                            destroyAction={destroyBannerImage.form(
                                collection.id,
                            )}
                        />
                        <ArtworkCard
                            title="More Collections tile (portrait)"
                            description="9:16 portrait — minimum 900 × 1600 px. Used for the “More Collections” tile on collection pages. Falls back to the square image above when not set."
                            uploaderId={`collection-vertical-${collection.id}`}
                            aspect={9 / 16}
                            minimumWidth={900}
                            minimumHeight={1600}
                            existingUrl={collection.vertical_image_url}
                            storeAction={storeVerticalImage.form(collection.id)}
                            destroyAction={destroyVerticalImage.form(
                                collection.id,
                            )}
                        />
                        <ActivationCard collection={collection} />
                        <ArchiveCard collection={collection} />
                    </div>
                </div>
            </div>
        </PortalLayout>
    );
}
