import { Form, Head } from '@inertiajs/react';
import { ImageOff, Upload } from 'lucide-react';
import { useState } from 'react';
import {
    destroyImage,
    store,
    storeImage,
    updateActivation,
} from '@/actions/App/Http/Controllers/AdminCategoryController';
import { CategoryPicker } from '@/components/category-picker';
import type { CategoryOption } from '@/components/category-picker';
import { PortalLayout } from '@/components/portal-layout';

type Category = {
    id: number;
    name: string;
    slug: string;
    google_product_category_id: number | null;
    image_url: string | null;
    is_active: boolean;
    is_taxonomy_available: boolean | null;
    is_storefront_available: boolean;
    deleted_at: string | null;
    parent: { name: string } | null;
};

function CategoryThumbnail({ category }: { category: Category }) {
    return (
        <div className="grid aspect-square w-24 shrink-0 place-items-center overflow-hidden rounded-2xl bg-primary/10 text-2xl font-black text-primary ring-1 ring-primary/10">
            {category.image_url ? (
                <img
                    src={category.image_url}
                    alt=""
                    className="size-full object-cover"
                />
            ) : (
                category.name.charAt(0).toUpperCase()
            )}
        </div>
    );
}

function taxonomyStatusLabel(category: Category): string {
    if (category.google_product_category_id === null) {
        return 'Taxonomy: manual';
    }

    return category.is_taxonomy_available === false
        ? 'Taxonomy: unavailable'
        : 'Taxonomy: available';
}

function CategoryManagement({ category }: { category: Category }) {
    if (category.deleted_at) {
        return null;
    }

    const nextActive = !category.is_active;

    return (
        <div className="mt-5 grid gap-4 border-t border-slate-200 pt-5 lg:grid-cols-2 dark:border-slate-800">
            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                <div className="flex items-center gap-2">
                    <Upload className="size-4 text-primary" />
                    <h4 className="text-sm font-black">
                        {category.image_url
                            ? 'Replace category image'
                            : 'Upload category image'}
                    </h4>
                </div>
                <Form
                    {...storeImage.form(category.id)}
                    resetOnSuccess
                    className="mt-3 grid gap-3"
                >
                    {({ errors, processing, progress }) => (
                        <>
                            <input
                                required
                                type="file"
                                name="image"
                                accept="image/jpeg,image/png,image/webp"
                                className="rounded-xl border border-slate-200 bg-white p-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                            <input
                                required
                                minLength={5}
                                name="reason"
                                placeholder="Reason for artwork change"
                                className="rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                            {progress && (
                                <progress
                                    value={progress.percentage}
                                    max="100"
                                    className="h-2 w-full"
                                />
                            )}
                            {(errors.image || errors.reason) && (
                                <p className="text-sm text-red-600">
                                    {errors.image ?? errors.reason}
                                </p>
                            )}
                            <button
                                disabled={processing}
                                className="rounded-xl bg-primary px-4 py-2.5 text-sm font-black text-primary-foreground disabled:opacity-50"
                            >
                                {processing ? 'Uploading…' : 'Save image'}
                            </button>
                        </>
                    )}
                </Form>
                {category.image_url && (
                    <Form
                        {...destroyImage.form(category.id)}
                        resetOnSuccess
                        className="mt-3 grid gap-3 border-t border-slate-200 pt-3 dark:border-slate-800"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    required
                                    minLength={5}
                                    name="reason"
                                    placeholder="Reason for removing artwork"
                                    className="rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-900"
                                />
                                {errors.reason && (
                                    <p className="text-sm text-red-600">
                                        {errors.reason}
                                    </p>
                                )}
                                <button
                                    disabled={processing}
                                    className="inline-flex items-center justify-center gap-2 rounded-xl border border-red-200 px-4 py-2.5 text-sm font-black text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/30"
                                >
                                    <ImageOff className="size-4" />
                                    Remove image
                                </button>
                            </>
                        )}
                    </Form>
                )}
            </div>

            <div className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                <h4 className="text-sm font-black">Category availability</h4>
                <p className="mt-1 text-xs leading-5 text-slate-500">
                    {nextActive
                        ? 'Activation includes this subtree. If an ancestor is inactive, its entire subtree is activated too.'
                        : 'Deactivation immediately hides this category, every descendant, and their public listings.'}
                </p>
                <Form
                    {...updateActivation.form(category.id)}
                    resetOnSuccess
                    onSubmit={(event) => {
                        const action = nextActive ? 'activate' : 'deactivate';

                        if (
                            !window.confirm(
                                `Are you sure you want to ${action} this category subtree?`,
                            )
                        ) {
                            event.preventDefault();
                        }
                    }}
                    className="mt-3 grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="is_active"
                                value={nextActive ? '1' : '0'}
                            />
                            <input
                                required
                                minLength={5}
                                name="reason"
                                placeholder={`Reason for ${nextActive ? 'activation' : 'deactivation'}`}
                                className="rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-700 dark:bg-slate-900"
                            />
                            {errors.reason && (
                                <p className="text-sm text-red-600">
                                    {errors.reason}
                                </p>
                            )}
                            <button
                                disabled={processing}
                                className={
                                    nextActive
                                        ? 'rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white disabled:opacity-50'
                                        : 'rounded-xl bg-red-700 px-4 py-2.5 text-sm font-black text-white disabled:opacity-50'
                                }
                            >
                                {nextActive
                                    ? 'Activate subtree'
                                    : 'Deactivate subtree'}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}

export default function Categories({
    categories,
}: {
    categories: { data: Category[] };
}) {
    const [parent, setParent] = useState<CategoryOption | null>(null);

    return (
        <PortalLayout portal="admin" title="Catalog categories">
            <Head title="Categories" />
            <main className="mx-auto grid max-w-7xl gap-8 xl:grid-cols-[22rem_1fr]">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-sm font-semibold text-primary">
                        Catalog
                    </p>
                    <h1 className="mt-1 text-2xl font-bold">Add category</h1>
                    <Form
                        {...store.form()}
                        resetOnSuccess
                        className="mt-5 grid gap-3"
                    >
                        {({ errors, processing, progress }) => (
                            <>
                                <input
                                    required
                                    name="name"
                                    placeholder="Category name"
                                    className="rounded-xl border p-3"
                                />
                                <input
                                    name="slug"
                                    placeholder="URL slug (optional)"
                                    className="rounded-xl border p-3"
                                />
                                <input
                                    type="hidden"
                                    name="parent_id"
                                    value={parent?.id ?? ''}
                                />
                                <CategoryPicker
                                    label="Parent category (optional)"
                                    selected={parent}
                                    onSelect={setParent}
                                    selectionMode="any"
                                    error={errors.parent_id}
                                />
                                <input
                                    name="google_product_category_id"
                                    type="number"
                                    min="1"
                                    placeholder="Google category ID (optional)"
                                    className="rounded-xl border p-3"
                                />
                                <label className="grid gap-1 text-sm font-semibold">
                                    Category image (optional)
                                    <input
                                        type="file"
                                        name="image"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="rounded-xl border p-3 font-normal"
                                    />
                                </label>
                                <input
                                    required
                                    name="commission_percentage"
                                    type="number"
                                    step="0.01"
                                    defaultValue="8"
                                    className="rounded-xl border p-3"
                                />
                                <input
                                    required
                                    name="return_window_days"
                                    type="number"
                                    defaultValue="7"
                                    className="rounded-xl border p-3"
                                />
                                <label className="flex gap-2 text-sm">
                                    <input
                                        name="cod_enabled"
                                        type="checkbox"
                                        value="1"
                                        defaultChecked
                                    />{' '}
                                    COD enabled
                                </label>
                                <label className="flex gap-2 text-sm">
                                    <input
                                        name="is_active"
                                        type="checkbox"
                                        value="1"
                                        defaultChecked
                                    />{' '}
                                    Visible to sellers
                                </label>
                                <textarea
                                    required
                                    name="reason"
                                    placeholder="Audit reason"
                                    className="min-h-20 rounded-xl border p-3"
                                />
                                {progress && (
                                    <progress
                                        value={progress.percentage}
                                        max="100"
                                        className="h-2 w-full"
                                    />
                                )}
                                {Object.values(errors).map((error) => (
                                    <p
                                        key={error}
                                        className="text-sm text-red-600"
                                    >
                                        {error}
                                    </p>
                                ))}
                                <button
                                    disabled={processing}
                                    className="rounded-xl bg-primary px-4 py-3 font-semibold text-primary-foreground disabled:opacity-50"
                                >
                                    Create category
                                </button>
                            </>
                        )}
                    </Form>
                </section>
                <section>
                    <p className="text-sm font-semibold text-primary">
                        {categories.data.length} categories
                    </p>
                    <h2 className="mt-1 text-3xl font-bold">Category tree</h2>
                    <div className="mt-6 grid gap-4">
                        {categories.data.map((category) => (
                            <article
                                key={category.id}
                                className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                                    <CategoryThumbnail category={category} />
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <h3 className="font-semibold">
                                                    {category.name}
                                                </h3>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {category.parent?.name ??
                                                        'Top level'}{' '}
                                                    · Google ID:{' '}
                                                    {category.google_product_category_id ??
                                                        'Not mapped'}
                                                </p>
                                            </div>
                                            <div className="flex flex-wrap justify-end gap-2">
                                                {category.deleted_at && (
                                                    <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold dark:bg-slate-800">
                                                        Archived
                                                    </span>
                                                )}
                                                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold dark:bg-slate-800">
                                                    Admin:{' '}
                                                    {category.is_active
                                                        ? 'active'
                                                        : 'inactive'}
                                                </span>
                                                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold dark:bg-slate-800">
                                                    {taxonomyStatusLabel(
                                                        category,
                                                    )}
                                                </span>
                                                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold dark:bg-slate-800">
                                                    Storefront:{' '}
                                                    {category.is_storefront_available
                                                        ? 'visible'
                                                        : 'hidden'}
                                                </span>
                                            </div>
                                        </div>
                                        <CategoryManagement
                                            category={category}
                                        />
                                    </div>
                                </div>
                            </article>
                        ))}
                        {categories.data.length === 0 && (
                            <p className="rounded-2xl border border-dashed p-10 text-center text-muted-foreground">
                                No categories yet.
                            </p>
                        )}
                    </div>
                </section>
            </main>
        </PortalLayout>
    );
}
