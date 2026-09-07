import { Form, Head, Link } from '@inertiajs/react';
import {
    Boxes,
    CheckCircle2,
    ChevronLeft,
    CircleDollarSign,
    Edit3,
    ImageIcon,
    Package,
    Search,
    ShieldCheck,
    Store,
    Tag,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    edit,
    index as listingIndex,
    update,
} from '@/actions/App/Http/Controllers/AdminListingController';
import { PortalLayout } from '@/components/portal-layout';
import { RichTextContent } from '@/components/rich-text-editor';

type ListingMedia = { id: number; url: string };
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
    option_values: {
        id: number;
        value: string;
        option: { id: number; name: string };
    }[];
};
type SeoScore = {
    score: number;
    maximum: number;
    label: string;
    checks: {
        key: string;
        label: string;
        points: number;
        maximum: number;
        passed: boolean;
        recommendation: string | null;
    }[];
};
type Listing = {
    id: number;
    title: string | null;
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
    category: { id: number; name: string } | null;
    brand: { id: number; name: string } | null;
    brand_name: string | null;
    seller_profile: { id: number; store_name: string; status: string };
    media: ListingMedia[];
    variants: ListingVariant[];
    seo_score: SeoScore;
};

const decisionStatuses = [
    'approved',
    'changes_requested',
    'rejected',
    'suspended',
    'archived',
];

export default function ShowAdminListing({ listing }: { listing: Listing }) {
    const [selectedImage, setSelectedImage] = useState(0);
    const specifications = listing.specifications ?? {};
    const availableStock = Math.max(
        0,
        listing.stock_quantity - listing.reserved_quantity,
    );
    const brandName = listing.brand?.name ?? listing.brand_name ?? 'Not set';

    return (
        <PortalLayout portal="admin" title="Listing review">
            <Head title={`Review ${listing.title ?? 'listing'}`} />
            <main className="mx-auto max-w-[1480px]">
                <Link
                    href={listingIndex()}
                    className="inline-flex items-center gap-1 text-sm font-bold text-primary"
                >
                    <ChevronLeft className="size-4" /> Listing moderation
                </Link>

                <div className="mt-4 flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={listing.status} />
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                {label(listing.product_type)} product
                            </span>
                        </div>
                        <h1 className="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                            {listing.title ?? 'Untitled product'}
                        </h1>
                        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Submitted by {listing.seller_profile.store_name} ·{' '}
                            {listing.category?.name ?? 'No category'} · SKU{' '}
                            {listing.sku ?? 'not set'}
                        </p>
                    </div>
                    <Link
                        href={edit(listing.id)}
                        className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-primary px-5 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20"
                    >
                        <Edit3 className="size-4" /> Edit product
                    </Link>
                </div>

                <div className="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <div className="grid gap-6">
                        <section className="grid gap-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-2 dark:border-slate-800 dark:bg-slate-900">
                            <div>
                                <div className="flex aspect-square items-center justify-center overflow-hidden rounded-2xl bg-slate-50 dark:bg-slate-950">
                                    {listing.media[selectedImage] ? (
                                        <img
                                            src={
                                                listing.media[selectedImage].url
                                            }
                                            alt={listing.title ?? 'Product'}
                                            className="size-full object-contain p-5"
                                        />
                                    ) : (
                                        <ImageIcon className="size-14 text-slate-300" />
                                    )}
                                </div>
                                {listing.media.length > 1 && (
                                    <div className="mt-3 grid grid-cols-5 gap-2">
                                        {listing.media.map(
                                            (media, imageIndex) => (
                                                <button
                                                    key={media.id}
                                                    type="button"
                                                    onClick={() =>
                                                        setSelectedImage(
                                                            imageIndex,
                                                        )
                                                    }
                                                    className={`aspect-square overflow-hidden rounded-xl border bg-white p-1 ${selectedImage === imageIndex ? 'border-primary ring-2 ring-primary/20' : 'border-slate-200 dark:border-slate-700'}`}
                                                >
                                                    <img
                                                        src={media.url}
                                                        alt=""
                                                        className="size-full object-contain"
                                                    />
                                                </button>
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>
                            <div className="flex flex-col">
                                <p className="text-xs font-bold tracking-wider text-primary uppercase">
                                    {listing.category?.name ?? 'Uncategorised'}
                                </p>
                                <h2 className="mt-2 text-2xl font-black">
                                    {listing.title ?? 'Untitled product'}
                                </h2>
                                <p className="mt-3 text-base leading-7 text-slate-600 dark:text-slate-300">
                                    {listing.short_description ??
                                        'No short description provided.'}
                                </p>
                                <div className="mt-5 rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                                    <p className="text-sm text-slate-500">
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
                                        value={label(
                                            listing.condition ?? 'not set',
                                        )}
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
                            <dl className="grid gap-x-8 md:grid-cols-2">
                                <DetailRow
                                    label="SKU"
                                    value={listing.sku ?? 'Not set'}
                                />
                                <DetailRow
                                    label="Barcode"
                                    value={listing.barcode ?? 'Not set'}
                                />
                                <DetailRow
                                    label="GTIN"
                                    value={listing.gtin ?? 'Not set'}
                                />
                                <DetailRow
                                    label="MPN"
                                    value={listing.mpn ?? 'Not set'}
                                />
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
                                    label="Commission"
                                    value={
                                        listing.commission_percentage
                                            ? `${listing.commission_percentage}%`
                                            : 'Not set'
                                    }
                                />
                            </dl>
                        </DetailCard>

                        <DetailCard icon={ShieldCheck} title="Full description">
                            {listing.description ? (
                                <RichTextContent
                                    value={listing.description}
                                    className="product-description text-base text-slate-600 dark:text-slate-300"
                                />
                            ) : (
                                <p className="text-slate-500">
                                    No description added.
                                </p>
                            )}
                        </DetailCard>

                        <DetailCard icon={Tag} title="Specifications">
                            {Object.keys(specifications).length > 0 ? (
                                <div className="grid gap-4">
                                    {Object.entries(specifications).map(
                                        ([name, value]) => (
                                            <div key={name}>
                                                {name !== 'Details' && (
                                                    <p className="mb-2 font-bold">
                                                        {name}
                                                    </p>
                                                )}
                                                {name === 'Details' ? (
                                                    <RichTextContent
                                                        value={String(value)}
                                                        className="product-description text-base text-slate-600 dark:text-slate-300"
                                                    />
                                                ) : (
                                                    <p className="text-base text-slate-600 dark:text-slate-300">
                                                        {String(value)}
                                                    </p>
                                                )}
                                            </div>
                                        ),
                                    )}
                                </div>
                            ) : (
                                <p className="text-slate-500">
                                    No specifications added.
                                </p>
                            )}
                        </DetailCard>

                        {listing.product_type === 'variant' && (
                            <DetailCard icon={Boxes} title="Product variants">
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
                                                    Identifiers
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
                                                        {variant.sku ?? '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {[
                                                            variant.gtin,
                                                            variant.mpn,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ') || '-'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {formatPrice(
                                                            variant.selling_price,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {Math.max(
                                                            0,
                                                            variant.stock_quantity -
                                                                variant.reserved_quantity,
                                                        )}
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
                        <section className="rounded-2xl border border-primary/30 bg-white p-5 shadow-lg shadow-primary/5 dark:bg-slate-900">
                            <h2 className="text-lg font-black">
                                Moderation decision
                            </h2>
                            <p className="mt-1 text-sm leading-6 text-slate-500">
                                Review all product details, edit anything that
                                needs correction, then record the decision and
                                reason.
                            </p>
                            <Form
                                {...update.form(listing.id)}
                                options={{ preserveScroll: true }}
                                className="mt-5 grid gap-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <label className="grid gap-1.5 text-sm font-bold">
                                            Decision
                                            <select
                                                name="status"
                                                defaultValue={listing.status}
                                                className="h-11 rounded-xl border border-slate-300 bg-transparent px-3 font-normal dark:border-slate-700"
                                            >
                                                {!decisionStatuses.includes(
                                                    listing.status,
                                                ) && (
                                                    <option
                                                        value={listing.status}
                                                        disabled
                                                    >
                                                        Current:{' '}
                                                        {label(listing.status)}
                                                    </option>
                                                )}
                                                <option value="approved">
                                                    Approve
                                                </option>
                                                <option value="changes_requested">
                                                    Request changes
                                                </option>
                                                <option value="rejected">
                                                    Reject
                                                </option>
                                                <option value="suspended">
                                                    Suspend
                                                </option>
                                                <option value="archived">
                                                    Archive
                                                </option>
                                            </select>
                                            {errors.status && (
                                                <span className="text-xs font-medium text-red-600">
                                                    {errors.status}
                                                </span>
                                            )}
                                        </label>
                                        <label className="grid gap-1.5 text-sm font-bold">
                                            Decision reason
                                            <textarea
                                                required
                                                name="reason"
                                                defaultValue={
                                                    listing.moderation_reason ??
                                                    ''
                                                }
                                                rows={5}
                                                placeholder="Explain the approval or what the seller needs to change."
                                                className="rounded-xl border border-slate-300 bg-transparent p-3 font-normal dark:border-slate-700"
                                            />
                                            {errors.reason && (
                                                <span className="text-xs font-medium text-red-600">
                                                    {errors.reason}
                                                </span>
                                            )}
                                        </label>
                                        <button
                                            disabled={processing}
                                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-primary px-5 text-sm font-bold text-primary-foreground disabled:opacity-50"
                                        >
                                            <CheckCircle2 className="size-4" />
                                            {processing
                                                ? 'Saving…'
                                                : 'Save decision'}
                                        </button>
                                    </>
                                )}
                            </Form>
                        </section>

                        <DetailCard icon={Store} title="Seller">
                            <dl>
                                <DetailRow
                                    label="Store"
                                    value={listing.seller_profile.store_name}
                                />
                                <DetailRow
                                    label="Seller status"
                                    value={label(listing.seller_profile.status)}
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

                        <DetailCard icon={CircleDollarSign} title="Inventory">
                            <dl>
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
                                <DetailRow
                                    label="Product visibility"
                                    value={
                                        listing.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }
                                />
                            </dl>
                        </DetailCard>

                        <DetailCard icon={Search} title="SEO readiness">
                            <p className="text-2xl font-black">
                                {listing.seo_score.score}/
                                {listing.seo_score.maximum}
                            </p>
                            <p className="mt-1 text-sm text-slate-500">
                                {listing.seo_score.label}
                            </p>
                            <ul className="mt-4 grid gap-3">
                                {listing.seo_score.checks.map((check) => (
                                    <li key={check.key} className="text-sm">
                                        <p className="font-bold">
                                            {check.label}: {check.points}/
                                            {check.maximum}
                                        </p>
                                        {check.recommendation && (
                                            <p className="mt-1 leading-5 text-slate-500">
                                                {check.recommendation}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            <div className="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800">
                                <DetailRow
                                    label="Meta title"
                                    value={listing.meta_title ?? 'Not set'}
                                />
                                <DetailRow
                                    label="Meta description"
                                    value={
                                        listing.meta_description ?? 'Not set'
                                    }
                                />
                            </div>
                        </DetailCard>
                    </aside>
                </div>
            </main>
        </PortalLayout>
    );
}

function StatusBadge({ status }: { status: string }) {
    const color =
        status === 'approved'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : status === 'rejected' || status === 'suspended'
              ? 'border-red-200 bg-red-50 text-red-700'
              : 'border-amber-200 bg-amber-50 text-amber-800';

    return (
        <span
            className={`rounded-full border px-3 py-1 text-xs font-bold ${color}`}
        >
            {label(status)}
        </span>
    );
}

function DetailCard({
    children,
    icon: Icon,
    title,
}: {
    children: ReactNode;
    icon: typeof Package;
    title: string;
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="mb-4 flex items-center gap-2">
                <Icon className="size-5 text-primary" />
                <h2 className="text-lg font-black">{title}</h2>
            </div>
            {children}
        </section>
    );
}

function DetailRow({
    label: rowLabel,
    value,
}: {
    label: string;
    value: ReactNode;
}) {
    return (
        <div className="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-4 border-b border-slate-100 py-3 text-sm last:border-0 dark:border-slate-800">
            <dt className="font-semibold text-slate-700 dark:text-slate-200">
                {rowLabel}
            </dt>
            <dd className="min-w-0 break-words text-slate-500 dark:text-slate-400">
                {value}
            </dd>
        </div>
    );
}

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
