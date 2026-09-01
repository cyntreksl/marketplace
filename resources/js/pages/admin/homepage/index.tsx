import { Form, Head } from '@inertiajs/react';
import { GripVertical, Image, Sparkles, Tag, X } from 'lucide-react';
import { useState } from 'react';
import {
    updateCategories,
    updateListing,
} from '@/actions/App/Http/Controllers/AdminHomepageController';
import {
    store as storePromotion,
    update as updatePromotion,
} from '@/actions/App/Http/Controllers/AdminPromotionController';
import { CategoryPicker } from '@/components/category-picker';
import type { CategoryOption } from '@/components/category-picker';
import { PortalLayout } from '@/components/portal-layout';

type SelectedCategory = {
    id: number;
    name: string;
    slug: string;
    is_popular: boolean;
    homepage_order: number | null;
};
type Listing = {
    id: number;
    title: string;
    status: string;
    listing_type: string;
    price: string | null;
    sale_price: string | null;
    is_best_offer: boolean;
    is_featured: boolean;
    is_best_seller: boolean;
    is_new_arrival: boolean;
    is_clearance: boolean;
    seller_profile: { store_name: string };
    category: { name: string };
};
type Promotion = {
    id: number;
    title: string;
    link_url: string | null;
    subtitle: string | null;
    cta_label: string | null;
    visual_theme: 'orange' | 'dark' | 'light';
    artwork_alt: string | null;
    placement: 'hero' | 'secondary' | 'flash_sale';
    sort_order: number;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    listings: { id: number; title: string }[];
};

function asOption(category: SelectedCategory): CategoryOption {
    return {
        id: category.id,
        name: category.name,
        path: category.name,
        slug: category.slug,
        is_selectable: true,
        has_children: false,
        commission_percentage: '0.00',
    };
}

function SelectedList({
    label,
    items,
    onRemove,
    limit,
}: {
    label: string;
    items: CategoryOption[];
    onRemove: (id: number) => void;
    limit: number;
}) {
    return (
        <div>
            <div className="flex items-center justify-between">
                <h3 className="font-black">{label}</h3>
                <span className="text-xs font-bold text-slate-500">
                    {items.length}/{limit}
                </span>
            </div>
            <div className="mt-3 grid gap-2">
                {items.length === 0 && (
                    <p className="rounded-xl border border-dashed p-4 text-sm text-slate-500">
                        No categories selected.
                    </p>
                )}
                {items.map((item, index) => (
                    <div
                        key={item.id}
                        className="flex items-center gap-3 rounded-xl border bg-white p-3 dark:bg-slate-950"
                    >
                        <GripVertical className="size-4 text-slate-400" />
                        <span className="grid size-7 place-items-center rounded-lg bg-primary/10 text-xs font-black text-primary">
                            {index + 1}
                        </span>
                        <span className="min-w-0 flex-1 truncate text-sm font-bold">
                            {item.name}
                        </span>
                        <button
                            type="button"
                            onClick={() => onRemove(item.id)}
                            className="rounded-lg p-1 text-slate-400 hover:bg-red-50 hover:text-red-600"
                        >
                            <X className="size-4" />
                            <span className="sr-only">Remove {item.name}</span>
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function AdminHomepage({
    selectedCategories,
    listings,
    promotions,
}: {
    selectedCategories: SelectedCategory[];
    listings: { data: Listing[] };
    promotions: { data: Promotion[] };
}) {
    const [popular, setPopular] = useState<CategoryOption[]>(() =>
        selectedCategories
            .filter((category) => category.is_popular)
            .map(asOption),
    );
    const [featured, setFeatured] = useState<CategoryOption[]>(() =>
        selectedCategories
            .filter((category) => category.homepage_order !== null)
            .sort((a, b) => Number(a.homepage_order) - Number(b.homepage_order))
            .map(asOption),
    );
    const [popularPicker, setPopularPicker] = useState<CategoryOption | null>(
        null,
    );
    const [featuredPicker, setFeaturedPicker] = useState<CategoryOption | null>(
        null,
    );

    function addUnique(
        items: CategoryOption[],
        option: CategoryOption | null,
        limit: number,
    ): CategoryOption[] {
        if (
            !option ||
            items.some((item) => item.id === option.id) ||
            items.length >= limit
        ) {
            return items;
        }

        return [...items, option];
    }

    return (
        <PortalLayout portal="admin" title="Homepage merchandising">
            <Head title="Homepage merchandising" />
            <main className="mx-auto max-w-7xl space-y-10">
                <div>
                    <p className="text-sm font-black tracking-wider text-primary uppercase">
                        Storefront control
                    </p>
                    <h1 className="mt-2 text-4xl font-black">
                        Homepage merchandising
                    </h1>
                    <p className="mt-2 max-w-3xl text-slate-500">
                        Curate discovery sections without changing listing
                        moderation. Limits are enforced on both this page and
                        the server.
                    </p>
                </div>

                <section className="rounded-3xl border bg-white p-6 dark:bg-slate-900">
                    <div className="flex items-center gap-3">
                        <span className="grid size-11 place-items-center rounded-xl bg-primary/10 text-primary">
                            <Tag className="size-5" />
                        </span>
                        <div>
                            <h2 className="text-xl font-black">
                                Category merchandising
                            </h2>
                            <p className="text-sm text-slate-500">
                                Popular categories and up to five ordered
                                product bands.
                            </p>
                        </div>
                    </div>
                    <div className="mt-6 grid gap-8 lg:grid-cols-2">
                        <div>
                            <CategoryPicker
                                selected={popularPicker}
                                onSelect={(option) => {
                                    setPopularPicker(option);
                                    setPopular((items) =>
                                        addUnique(items, option, 10),
                                    );
                                }}
                                selectionMode="any"
                                label="Add a popular category"
                            />
                            <div className="mt-5">
                                <SelectedList
                                    label="Popular Categories"
                                    items={popular}
                                    limit={10}
                                    onRemove={(id) =>
                                        setPopular((items) =>
                                            items.filter(
                                                (item) => item.id !== id,
                                            ),
                                        )
                                    }
                                />
                            </div>
                        </div>
                        <div>
                            <CategoryPicker
                                selected={featuredPicker}
                                onSelect={(option) => {
                                    setFeaturedPicker(option);
                                    setFeatured((items) =>
                                        addUnique(items, option, 5),
                                    );
                                }}
                                selectionMode="any"
                                label="Add a category section"
                            />
                            <div className="mt-5">
                                <SelectedList
                                    label="Ordered homepage sections"
                                    items={featured}
                                    limit={5}
                                    onRemove={(id) =>
                                        setFeatured((items) =>
                                            items.filter(
                                                (item) => item.id !== id,
                                            ),
                                        )
                                    }
                                />
                            </div>
                        </div>
                    </div>
                    <Form
                        {...updateCategories.form()}
                        transform={(data) => ({
                            ...data,
                            popular_category_ids: popular.map(
                                (item) => item.id,
                            ),
                            featured_category_ids: featured.map(
                                (item) => item.id,
                            ),
                        })}
                        className="mt-7 flex flex-col gap-3 border-t pt-6 sm:flex-row"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    required
                                    minLength={5}
                                    name="reason"
                                    placeholder="Reason for this homepage update"
                                    className="min-h-11 flex-1 rounded-xl border bg-transparent px-4"
                                />
                                <button
                                    disabled={processing}
                                    className="rounded-xl bg-primary px-5 py-3 font-black text-primary-foreground disabled:opacity-50"
                                >
                                    Save categories
                                </button>
                                {Object.values(errors).map((error) => (
                                    <p
                                        key={error}
                                        className="text-sm text-red-600 sm:basis-full"
                                    >
                                        {error}
                                    </p>
                                ))}
                            </>
                        )}
                    </Form>
                </section>

                <section className="rounded-3xl border bg-white p-6 dark:bg-slate-900">
                    <div className="flex items-center gap-3">
                        <span className="grid size-11 place-items-center rounded-xl bg-amber-100 text-amber-700">
                            <Image className="size-5" />
                        </span>
                        <div>
                            <h2 className="text-xl font-black">
                                Banners and promotions
                            </h2>
                            <p className="text-sm text-slate-500">
                                Active, scheduled images override storefront
                                fallback artwork.
                            </p>
                        </div>
                    </div>
                    <Form
                        {...storePromotion.form()}
                        className="mt-6 grid gap-3 rounded-2xl bg-slate-50 p-5 md:grid-cols-2 dark:bg-slate-950"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    required
                                    name="title"
                                    placeholder="Accessible banner title"
                                    className="rounded-xl border bg-transparent p-3"
                                />
                                <input
                                    name="subtitle"
                                    placeholder="Subtitle (optional)"
                                    className="rounded-xl border bg-transparent p-3"
                                />
                                <input
                                    name="cta_label"
                                    placeholder="CTA label (optional)"
                                    className="rounded-xl border bg-transparent p-3"
                                />
                                <input
                                    name="artwork_alt"
                                    placeholder="Artwork alt text"
                                    className="rounded-xl border bg-transparent p-3"
                                />
                                <select
                                    name="visual_theme"
                                    defaultValue="orange"
                                    className="rounded-xl border bg-transparent p-3"
                                >
                                    <option value="orange">Orange theme</option>
                                    <option value="dark">Dark theme</option>
                                    <option value="light">Light theme</option>
                                </select>
                                <input
                                    required
                                    type="file"
                                    name="image"
                                    accept="image/*"
                                    className="rounded-xl border p-3"
                                />
                                <input
                                    name="link_url"
                                    placeholder="Internal link, e.g. /listings"
                                    className="rounded-xl border bg-transparent p-3"
                                />
                                <select
                                    name="placement"
                                    className="rounded-xl border bg-transparent p-3"
                                >
                                    <option value="hero">Hero</option>
                                    <option value="secondary">Secondary</option>
                                    <option value="flash_sale">
                                        Flash sale
                                    </option>
                                </select>
                                <select
                                    multiple
                                    name="listing_ids[]"
                                    aria-label="Flash sale listings"
                                    className="min-h-28 rounded-xl border bg-transparent p-3 md:col-span-2"
                                >
                                    {listings.data
                                        .filter(
                                            (listing) =>
                                                listing.status === 'approved' &&
                                                listing.listing_type ===
                                                    'buy_now',
                                        )
                                        .map((listing) => (
                                            <option
                                                key={listing.id}
                                                value={listing.id}
                                            >
                                                {listing.title}
                                            </option>
                                        ))}
                                </select>
                                <input
                                    type="number"
                                    min="0"
                                    name="sort_order"
                                    defaultValue="0"
                                    className="rounded-xl border bg-transparent p-3"
                                />
                                <select
                                    name="is_active"
                                    defaultValue="1"
                                    className="rounded-xl border bg-transparent p-3"
                                >
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                                <input
                                    type="datetime-local"
                                    name="starts_at"
                                    className="rounded-xl border bg-transparent p-3"
                                />
                                <input
                                    type="datetime-local"
                                    name="ends_at"
                                    className="rounded-xl border bg-transparent p-3"
                                />
                                <input
                                    required
                                    minLength={5}
                                    name="reason"
                                    placeholder="Reason for adding this banner"
                                    className="rounded-xl border bg-transparent p-3 md:col-span-2"
                                />
                                {Object.values(errors).map((error) => (
                                    <p
                                        key={error}
                                        className="text-sm text-red-600 md:col-span-2"
                                    >
                                        {error}
                                    </p>
                                ))}
                                <button
                                    disabled={processing}
                                    className="rounded-xl bg-slate-950 px-5 py-3 font-black text-white md:col-span-2 dark:bg-white dark:text-slate-950"
                                >
                                    Add promotion
                                </button>
                            </>
                        )}
                    </Form>
                    <div className="mt-5 grid gap-4 lg:grid-cols-2">
                        {promotions.data.map((promotion) => (
                            <Form
                                key={promotion.id}
                                {...updatePromotion.form(promotion.id)}
                                className="grid gap-3 rounded-2xl border p-4 sm:grid-cols-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            required
                                            name="title"
                                            defaultValue={promotion.title}
                                            className="rounded-lg border bg-transparent p-2"
                                        />
                                        <input
                                            name="subtitle"
                                            defaultValue={
                                                promotion.subtitle ?? ''
                                            }
                                            placeholder="Subtitle"
                                            className="rounded-lg border bg-transparent p-2"
                                        />
                                        <input
                                            name="cta_label"
                                            defaultValue={
                                                promotion.cta_label ?? ''
                                            }
                                            placeholder="CTA label"
                                            className="rounded-lg border bg-transparent p-2"
                                        />
                                        <input
                                            name="artwork_alt"
                                            defaultValue={
                                                promotion.artwork_alt ?? ''
                                            }
                                            placeholder="Artwork alt"
                                            className="rounded-lg border bg-transparent p-2"
                                        />
                                        <select
                                            name="visual_theme"
                                            defaultValue={
                                                promotion.visual_theme
                                            }
                                            className="rounded-lg border bg-transparent p-2"
                                        >
                                            <option value="orange">
                                                Orange
                                            </option>
                                            <option value="dark">Dark</option>
                                            <option value="light">Light</option>
                                        </select>
                                        <input
                                            type="file"
                                            name="image"
                                            accept="image/*"
                                            className="rounded-lg border p-2"
                                        />
                                        <input
                                            name="link_url"
                                            defaultValue={
                                                promotion.link_url ?? ''
                                            }
                                            className="rounded-lg border bg-transparent p-2"
                                        />
                                        <select
                                            name="placement"
                                            defaultValue={promotion.placement}
                                            className="rounded-lg border bg-transparent p-2"
                                        >
                                            <option value="hero">Hero</option>
                                            <option value="secondary">
                                                Secondary
                                            </option>
                                            <option value="flash_sale">
                                                Flash sale
                                            </option>
                                        </select>
                                        <select
                                            multiple
                                            name="listing_ids[]"
                                            defaultValue={promotion.listings.map(
                                                (listing) => String(listing.id),
                                            )}
                                            aria-label="Promotion listings"
                                            className="min-h-24 rounded-lg border bg-transparent p-2 sm:col-span-2"
                                        >
                                            {listings.data
                                                .filter(
                                                    (listing) =>
                                                        listing.status ===
                                                            'approved' &&
                                                        listing.listing_type ===
                                                            'buy_now',
                                                )
                                                .map((listing) => (
                                                    <option
                                                        key={listing.id}
                                                        value={listing.id}
                                                    >
                                                        {listing.title}
                                                    </option>
                                                ))}
                                        </select>
                                        <input
                                            type="number"
                                            min="0"
                                            name="sort_order"
                                            defaultValue={promotion.sort_order}
                                            className="rounded-lg border bg-transparent p-2"
                                        />
                                        <select
                                            name="is_active"
                                            defaultValue={
                                                promotion.is_active ? '1' : '0'
                                            }
                                            className="rounded-lg border bg-transparent p-2"
                                        >
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                        <input
                                            type="datetime-local"
                                            name="starts_at"
                                            defaultValue={promotion.starts_at?.slice(
                                                0,
                                                16,
                                            )}
                                            className="rounded-lg border bg-transparent p-2"
                                        />
                                        <input
                                            type="datetime-local"
                                            name="ends_at"
                                            defaultValue={promotion.ends_at?.slice(
                                                0,
                                                16,
                                            )}
                                            className="rounded-lg border bg-transparent p-2"
                                        />
                                        <input
                                            required
                                            minLength={5}
                                            name="reason"
                                            placeholder="Update reason"
                                            className="rounded-lg border bg-transparent p-2 sm:col-span-2"
                                        />
                                        {Object.values(errors).map((error) => (
                                            <p
                                                key={error}
                                                className="text-xs text-red-600 sm:col-span-2"
                                            >
                                                {error}
                                            </p>
                                        ))}
                                        <button
                                            disabled={processing}
                                            className="rounded-lg bg-primary px-4 py-2 text-sm font-black text-primary-foreground sm:col-span-2"
                                        >
                                            Save promotion
                                        </button>
                                    </>
                                )}
                            </Form>
                        ))}
                    </div>
                </section>

                <section className="rounded-3xl border bg-white p-6 dark:bg-slate-900">
                    <div className="flex items-center gap-3">
                        <span className="grid size-11 place-items-center rounded-xl bg-teal-100 text-teal-700">
                            <Sparkles className="size-5" />
                        </span>
                        <div>
                            <h2 className="text-xl font-black">
                                Listing merchandising
                            </h2>
                            <p className="text-sm text-slate-500">
                                Best Offer eligibility is enforced independently
                                from moderation.
                            </p>
                        </div>
                    </div>
                    <div className="mt-6 grid gap-3">
                        {listings.data.map((listing) => (
                            <article
                                key={listing.id}
                                className="grid gap-4 rounded-2xl border p-4 lg:grid-cols-[1fr_34rem]"
                            >
                                <div>
                                    <p className="font-black">
                                        {listing.title}
                                    </p>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {listing.seller_profile.store_name} ·{' '}
                                        {listing.category.name} ·{' '}
                                        {listing.status.replace('_', ' ')}
                                    </p>
                                    {listing.sale_price && (
                                        <p className="mt-2 text-xs font-bold text-amber-700">
                                            Rs.{' '}
                                            {Number(
                                                listing.sale_price,
                                            ).toLocaleString()}{' '}
                                            from Rs.{' '}
                                            {Number(
                                                listing.price,
                                            ).toLocaleString()}
                                        </p>
                                    )}
                                </div>
                                <Form
                                    {...updateListing.form(listing.id)}
                                    options={{ preserveScroll: true }}
                                    className="grid gap-2 sm:grid-cols-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <select
                                                name="is_featured"
                                                defaultValue={
                                                    listing.is_featured
                                                        ? '1'
                                                        : '0'
                                                }
                                                className="rounded-lg border bg-transparent p-2"
                                            >
                                                <option value="0">
                                                    Not Featured
                                                </option>
                                                <option value="1">
                                                    Featured Deal
                                                </option>
                                            </select>
                                            <select
                                                name="is_best_offer"
                                                defaultValue={
                                                    listing.is_best_offer
                                                        ? '1'
                                                        : '0'
                                                }
                                                className="rounded-lg border bg-transparent p-2"
                                            >
                                                <option value="0">
                                                    Not Best Offer
                                                </option>
                                                <option value="1">
                                                    Best Offer
                                                </option>
                                            </select>
                                            <select
                                                name="is_best_seller"
                                                defaultValue={
                                                    listing.is_best_seller
                                                        ? '1'
                                                        : '0'
                                                }
                                                className="rounded-lg border bg-transparent p-2"
                                            >
                                                <option value="0">
                                                    Not Best Seller
                                                </option>
                                                <option value="1">
                                                    Best Seller
                                                </option>
                                            </select>
                                            <select
                                                name="is_new_arrival"
                                                defaultValue={
                                                    listing.is_new_arrival
                                                        ? '1'
                                                        : '0'
                                                }
                                                className="rounded-lg border bg-transparent p-2"
                                            >
                                                <option value="0">
                                                    Not New Arrival
                                                </option>
                                                <option value="1">
                                                    New Arrival
                                                </option>
                                            </select>
                                            <select
                                                name="is_clearance"
                                                defaultValue={
                                                    listing.is_clearance
                                                        ? '1'
                                                        : '0'
                                                }
                                                className="rounded-lg border bg-transparent p-2"
                                            >
                                                <option value="0">
                                                    Not Clearance
                                                </option>
                                                <option value="1">
                                                    Clearance
                                                </option>
                                            </select>
                                            <input
                                                required
                                                minLength={5}
                                                name="reason"
                                                placeholder="Merchandising reason"
                                                className="rounded-lg border bg-transparent p-2"
                                            />
                                            <button
                                                disabled={processing}
                                                className="rounded-lg bg-primary px-4 py-2 text-sm font-black text-primary-foreground"
                                            >
                                                Save
                                            </button>
                                            {Object.values(errors).map(
                                                (error) => (
                                                    <p
                                                        key={error}
                                                        className="text-xs text-red-600 sm:col-span-3"
                                                    >
                                                        {error}
                                                    </p>
                                                ),
                                            )}
                                        </>
                                    )}
                                </Form>
                            </article>
                        ))}
                    </div>
                </section>
            </main>
        </PortalLayout>
    );
}
