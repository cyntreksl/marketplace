import { Head, Link } from '@inertiajs/react';
import {
    Boxes,
    CheckCircle2,
    ChevronLeft,
    CircleDollarSign,
    Edit3,
    Eye,
    ImageIcon,
    MapPin,
    Package,
    Search,
    ShieldCheck,
    Tag,
} from 'lucide-react';
import { useState } from 'react';
import { edit } from '@/actions/App/Http/Controllers/SellerListingController';
import { PortalLayout } from '@/components/portal-layout';
import { RichTextContent } from '@/components/rich-text-editor';
import { show as storefrontProduct } from '@/routes/listings';
import { index as productsIndex } from '@/routes/seller/listings';

type ListingMedia = {
    id: number;
    url: string;
};

type VariantOption = {
    id: number;
    name: string;
    values: { id: number; value: string }[];
};

type ListingVariant = {
    id: number;
    sku: string | null;
    gtin: string | null;
    mpn: string | null;
    selling_price: string | null;
    market_price: string | null;
    stock_quantity: number;
    reserved_quantity: number;
    is_active: boolean;
    image: ListingMedia | null;
    option_values: {
        id: number;
        value: string;
        option: { id: number; name: string };
    }[];
};

type Listing = {
    id: number;
    title: string | null;
    slug: string | null;
    status: string;
    moderation_reason: string | null;
    sku: string | null;
    barcode: string | null;
    gtin: string | null;
    mpn: string | null;
    model: string | null;
    short_description: string | null;
    description: string | null;
    specifications: Record<string, string | number | boolean> | null;
    condition: string | null;
    product_type: 'simple' | 'variant';
    listing_type: string;
    location: string | null;
    warranty: string | null;
    stock_quantity: number;
    reserved_quantity: number;
    low_stock_threshold: number;
    allow_backorders: boolean;
    is_active: boolean;
    is_featured: boolean;
    is_best_seller: boolean;
    is_new_arrival: boolean;
    price: string | null;
    sale_price: string | null;
    commission_percentage: string | null;
    meta_title: string | null;
    meta_description: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    created_at: string;
    updated_at: string;
    category: { id: number; name: string } | null;
    brand: { id: number; name: string } | null;
    brand_name: string | null;
    media: ListingMedia[];
    variant_options: VariantOption[];
    variants: ListingVariant[];
};

const editableStatuses = ['draft', 'changes_requested', 'rejected'];

const statusStyles: Record<string, string> = {
    approved:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300',
    archived:
        'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300',
    changes_requested:
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200',
    draft: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/50 dark:text-sky-300',
    pending_review:
        'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/50 dark:text-violet-300',
    rejected:
        'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300',
    suspended:
        'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300',
};

function formatPrice(value: string | null): string {
    return value === null
        ? 'Not set'
        : `LKR ${Number(value).toLocaleString('en-LK', { minimumFractionDigits: 2 })}`;
}

function formatDate(value: string | null): string {
    return value === null
        ? 'Not yet'
        : new Intl.DateTimeFormat('en-LK', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value));
}

function label(value: string): string {
    return value
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function DetailCard({
    children,
    icon: Icon,
    title,
}: {
    children: React.ReactNode;
    icon: typeof Package;
    title: string;
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="mb-5 flex items-center gap-3">
                <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                    <Icon className="size-5" />
                </span>
                <h2 className="text-lg font-black">{title}</h2>
            </div>
            {children}
        </section>
    );
}

function DetailRow({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div className="grid gap-1 border-b border-slate-100 py-3 last:border-b-0 sm:grid-cols-[9rem_1fr] dark:border-slate-800">
            <dt className="text-sm text-slate-500 dark:text-slate-400">
                {label}
            </dt>
            <dd className="text-sm font-semibold sm:text-right">{value}</dd>
        </div>
    );
}

function ProductGallery({ listing }: { listing: Listing }) {
    const galleryMedia =
        listing.media.length > 0
            ? listing.media
            : listing.variants
                  .map((variant) => variant.image)
                  .filter((media): media is ListingMedia => media !== null);
    const [selectedId, setSelectedId] = useState(galleryMedia[0]?.id ?? null);
    const selected =
        galleryMedia.find((media) => media.id === selectedId) ??
        galleryMedia[0];

    return (
        <div
            className={`grid gap-3 ${
                galleryMedia.length > 1 ? 'sm:grid-cols-[4.5rem_1fr]' : ''
            }`}
        >
            {galleryMedia.length > 1 && (
                <div className="order-2 flex gap-2 overflow-x-auto sm:order-1 sm:flex-col">
                    {galleryMedia.map((media, index) => (
                        <button
                            key={media.id}
                            type="button"
                            onClick={() => setSelectedId(media.id)}
                            aria-label={`View product image ${index + 1}`}
                            aria-pressed={selected?.id === media.id}
                            className={`size-16 shrink-0 overflow-hidden rounded-xl border bg-white p-1 dark:bg-slate-950 ${
                                selected?.id === media.id
                                    ? 'border-primary ring-2 ring-primary/15'
                                    : 'border-slate-200 dark:border-slate-700'
                            }`}
                        >
                            <img
                                src={media.url}
                                alt=""
                                className="size-full object-contain"
                            />
                        </button>
                    ))}
                </div>
            )}
            <div className="order-1 flex aspect-square items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-white sm:order-2 dark:border-slate-800 dark:bg-slate-950">
                {selected ? (
                    <img
                        src={selected.url}
                        alt={listing.title ?? 'Product image'}
                        className="size-full object-contain p-5"
                    />
                ) : (
                    <div className="grid justify-items-center gap-3 text-slate-400">
                        <ImageIcon className="size-10" />
                        <p className="text-sm font-semibold">
                            No product images uploaded
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}

export default function ShowSellerListing({ listing }: { listing: Listing }) {
    const canEdit = editableStatuses.includes(listing.status);
    const brandName = listing.brand?.name ?? listing.brand_name ?? 'Not set';
    const availableStock = Math.max(
        0,
        listing.stock_quantity - listing.reserved_quantity,
    );
    const specifications = listing.specifications ?? {};

    return (
        <PortalLayout
            portal="seller"
            title={listing.title ?? 'Product details'}
        >
            <Head title={listing.title ?? 'Product details'} />
            <main className="mx-auto max-w-[1480px]">
                <Link
                    href={productsIndex()}
                    className="inline-flex items-center gap-1 text-sm font-bold text-primary"
                >
                    <ChevronLeft className="size-4" /> All products
                </Link>

                <div className="mt-4 flex flex-col justify-between gap-5 lg:flex-row lg:items-start">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <span
                                className={`rounded-full border px-3 py-1 text-xs font-bold ${statusStyles[listing.status] ?? statusStyles.archived}`}
                            >
                                {label(listing.status)}
                            </span>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {label(listing.product_type)} product
                            </span>
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {listing.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        <h1 className="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                            {listing.title ?? 'Untitled product'}
                        </h1>
                        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            {listing.category?.name ?? 'No category'} ·{' '}
                            {brandName} · SKU {listing.sku ?? 'not set'}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        {listing.status === 'approved' && listing.slug && (
                            <Link
                                href={storefrontProduct(listing.slug)}
                                className="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-300 px-5 text-sm font-bold dark:border-slate-700"
                            >
                                <Eye className="size-4" /> Storefront
                            </Link>
                        )}
                        {canEdit && (
                            <Link
                                href={edit(listing.id)}
                                className="inline-flex h-11 items-center gap-2 rounded-xl bg-primary px-5 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20"
                            >
                                <Edit3 className="size-4" /> Edit product
                            </Link>
                        )}
                    </div>
                </div>

                {listing.moderation_reason && (
                    <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                        <p className="font-black">Marketplace review note</p>
                        <p className="mt-1">{listing.moderation_reason}</p>
                    </div>
                )}

                <div className="mt-6 grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_23rem]">
                    <div className="grid gap-5">
                        <section className="grid gap-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2 dark:border-slate-800 dark:bg-slate-900">
                            <ProductGallery listing={listing} />
                            <div className="flex flex-col">
                                <p className="text-xs font-bold tracking-wider text-primary uppercase">
                                    {listing.category?.name ?? 'Uncategorised'}
                                </p>
                                <h2 className="mt-2 text-2xl font-black">
                                    {listing.title ?? 'Untitled product'}
                                </h2>
                                {listing.short_description && (
                                    <p className="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                        {listing.short_description}
                                    </p>
                                )}
                                <div className="mt-5 rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                                    <p className="text-sm text-slate-500 dark:text-slate-400">
                                        Selling price
                                    </p>
                                    <p className="mt-1 text-3xl font-black text-primary">
                                        {formatPrice(
                                            listing.sale_price ?? listing.price,
                                        )}
                                    </p>
                                    {listing.sale_price && listing.price && (
                                        <p className="mt-1 text-sm text-slate-500 line-through">
                                            {formatPrice(listing.price)}
                                        </p>
                                    )}
                                </div>
                                <dl className="mt-3">
                                    <DetailRow
                                        label="Availability"
                                        value={`${availableStock} available`}
                                    />
                                    <DetailRow
                                        label="Condition"
                                        value={
                                            listing.condition
                                                ? label(listing.condition)
                                                : 'Not set'
                                        }
                                    />
                                    <DetailRow
                                        label="Location"
                                        value={listing.location ?? 'Not set'}
                                    />
                                    <DetailRow
                                        label="Warranty"
                                        value={listing.warranty ?? 'Not set'}
                                    />
                                </dl>
                            </div>
                        </section>

                        <DetailCard icon={Package} title="Product information">
                            <dl>
                                <DetailRow
                                    label="SKU"
                                    value={listing.sku ?? 'Not set'}
                                />
                                <DetailRow
                                    label="Barcode"
                                    value={listing.barcode ?? 'Not set'}
                                />
                                {listing.product_type === 'simple' && (
                                    <>
                                        <DetailRow
                                            label="GTIN"
                                            value={listing.gtin ?? 'Not set'}
                                        />
                                        <DetailRow
                                            label="MPN"
                                            value={listing.mpn ?? 'Not set'}
                                        />
                                    </>
                                )}
                                <DetailRow
                                    label="Model"
                                    value={listing.model ?? 'Not set'}
                                />
                                <DetailRow label="Brand" value={brandName} />
                                <DetailRow
                                    label="Category"
                                    value={listing.category?.name ?? 'Not set'}
                                />
                                <DetailRow
                                    label="Product type"
                                    value={label(listing.product_type)}
                                />
                                <DetailRow
                                    label="Listing type"
                                    value={label(listing.listing_type)}
                                />
                            </dl>
                            <div className="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800">
                                <h3 className="font-black">Full description</h3>
                                {listing.description ? (
                                    <RichTextContent
                                        value={listing.description}
                                        className="mt-3 text-sm text-slate-600 dark:text-slate-300"
                                    />
                                ) : (
                                    <p className="mt-3 text-sm text-slate-500">
                                        No description added.
                                    </p>
                                )}
                            </div>
                            <div className="mt-5 border-t border-slate-200 pt-5 dark:border-slate-800">
                                <h3 className="font-black">Specifications</h3>
                                {Object.keys(specifications).length > 0 ? (
                                    <dl className="mt-3 grid gap-3">
                                        {Object.entries(specifications).map(
                                            ([name, value]) => (
                                                <DetailRow
                                                    key={name}
                                                    label={name}
                                                    value={
                                                        name === 'Details' ? (
                                                            <RichTextContent
                                                                value={String(
                                                                    value,
                                                                )}
                                                                className="text-sm text-slate-600 dark:text-slate-300"
                                                            />
                                                        ) : (
                                                            <span className="whitespace-pre-line">
                                                                {String(value)}
                                                            </span>
                                                        )
                                                    }
                                                />
                                            ),
                                        )}
                                    </dl>
                                ) : (
                                    <p className="mt-3 text-sm text-slate-500">
                                        No specifications added.
                                    </p>
                                )}
                            </div>
                        </DetailCard>

                        {listing.product_type === 'variant' && (
                            <DetailCard icon={Boxes} title="Product variants">
                                <div className="mb-5 flex flex-wrap gap-2">
                                    {listing.variant_options.flatMap((option) =>
                                        option.values.map((value) => (
                                            <span
                                                key={value.id}
                                                className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold dark:bg-slate-800"
                                            >
                                                {option.name}: {value.value}
                                            </span>
                                        )),
                                    )}
                                </div>
                                <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                                    <table className="w-full min-w-[44rem] text-left text-sm">
                                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950">
                                            <tr>
                                                <th className="px-4 py-3">
                                                    Variant
                                                </th>
                                                <th className="px-4 py-3">
                                                    SKU
                                                </th>
                                                <th className="px-4 py-3">
                                                    GTIN
                                                </th>
                                                <th className="px-4 py-3">
                                                    MPN
                                                </th>
                                                <th className="px-4 py-3">
                                                    Price
                                                </th>
                                                <th className="px-4 py-3">
                                                    Stock
                                                </th>
                                                <th className="px-4 py-3">
                                                    Status
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                            {listing.variants.map((variant) => (
                                                <tr key={variant.id}>
                                                    <td className="px-4 py-3 font-bold">
                                                        {variant.option_values
                                                            .map(
                                                                (value) =>
                                                                    `${value.option.name}: ${value.value}`,
                                                            )
                                                            .join(' · ') ||
                                                            'Default'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {variant.sku ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {variant.gtin ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {variant.mpn ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {formatPrice(
                                                            variant.selling_price,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {variant.stock_quantity -
                                                            variant.reserved_quantity}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {variant.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </DetailCard>
                        )}
                    </div>

                    <aside className="grid gap-5 xl:sticky xl:top-24">
                        <DetailCard
                            icon={CircleDollarSign}
                            title="Pricing & stock"
                        >
                            <dl>
                                <DetailRow
                                    label="Selling price"
                                    value={formatPrice(
                                        listing.sale_price ?? listing.price,
                                    )}
                                />
                                <DetailRow
                                    label="Compare price"
                                    value={
                                        listing.sale_price
                                            ? formatPrice(listing.price)
                                            : 'Not set'
                                    }
                                />
                                <DetailRow
                                    label="Commission"
                                    value={
                                        listing.commission_percentage
                                            ? `${listing.commission_percentage}%`
                                            : 'Not set'
                                    }
                                />
                                <DetailRow
                                    label="Total stock"
                                    value={listing.stock_quantity}
                                />
                                <DetailRow
                                    label="Reserved"
                                    value={listing.reserved_quantity}
                                />
                                <DetailRow
                                    label="Low stock at"
                                    value={listing.low_stock_threshold}
                                />
                                <DetailRow
                                    label="Backorders"
                                    value={
                                        listing.allow_backorders
                                            ? 'Allowed'
                                            : 'Not allowed'
                                    }
                                />
                            </dl>
                        </DetailCard>

                        <DetailCard icon={CheckCircle2} title="Product status">
                            <dl>
                                <DetailRow
                                    label="Marketplace"
                                    value={label(listing.status)}
                                />
                                <DetailRow
                                    label="Product"
                                    value={
                                        listing.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }
                                />
                                <DetailRow
                                    label="Featured"
                                    value={listing.is_featured ? 'Yes' : 'No'}
                                />
                                <DetailRow
                                    label="Best seller"
                                    value={
                                        listing.is_best_seller ? 'Yes' : 'No'
                                    }
                                />
                                <DetailRow
                                    label="New arrival"
                                    value={
                                        listing.is_new_arrival ? 'Yes' : 'No'
                                    }
                                />
                                <DetailRow
                                    label="Submitted"
                                    value={formatDate(listing.submitted_at)}
                                />
                                <DetailRow
                                    label="Approved"
                                    value={formatDate(listing.approved_at)}
                                />
                            </dl>
                        </DetailCard>

                        <DetailCard icon={Search} title="Search information">
                            <dl>
                                <DetailRow
                                    label="Meta title"
                                    value={listing.meta_title ?? 'Not set'}
                                />
                                <DetailRow
                                    label="Description"
                                    value={
                                        listing.meta_description ?? 'Not set'
                                    }
                                />
                            </dl>
                        </DetailCard>

                        <div className="grid grid-cols-3 gap-3">
                            {[
                                [
                                    MapPin,
                                    listing.location
                                        ? 'Location set'
                                        : 'Location optional',
                                ],
                                [
                                    ShieldCheck,
                                    listing.warranty
                                        ? 'Warranty set'
                                        : 'No warranty',
                                ],
                                [Tag, brandName],
                            ].map(([Icon, text]) => {
                                const ItemIcon = Icon as typeof Package;

                                return (
                                    <div
                                        key={String(text)}
                                        className="grid justify-items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3 text-center dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <ItemIcon className="size-5 text-primary" />
                                        <span className="text-[0.68rem] font-bold text-slate-500 dark:text-slate-400">
                                            {String(text)}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </aside>
                </div>
            </main>
        </PortalLayout>
    );
}
