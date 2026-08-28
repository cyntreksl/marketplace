import { Form, Head, useHttp } from '@inertiajs/react';
import {
    Archive,
    Check,
    ChevronRight,
    FolderTree,
    ImageOff,
    LoaderCircle,
    Plus,
    RotateCcw,
    Search,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    children,
    context,
    search as searchCategories,
} from '@/actions/App/Http/Controllers/AdminCategoryBrowseController';
import {
    destroy,
    destroyImage,
    index,
    restore,
    store,
    storeImage,
    update,
    updateActivation,
} from '@/actions/App/Http/Controllers/AdminCategoryController';
import { PortalLayout } from '@/components/portal-layout';

type Category = {
    id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    path: string;
    google_product_category_id: number | null;
    image_url: string | null;
    commission_percentage: string;
    return_window_days: number;
    cod_enabled: boolean;
    is_active: boolean;
    is_taxonomy_available: boolean | null;
    is_storefront_available: boolean;
    is_selectable: boolean;
    deleted_at: string | null;
    updated_at: string | null;
    has_children: boolean;
    capabilities: {
        can_update: boolean;
        can_manage_artwork: boolean;
        can_update_activation: boolean;
        can_archive: boolean;
        can_restore: boolean;
    };
};

type CategoryColumn = {
    parent_id: number | null;
    categories: Category[];
};

type CategoryContext = {
    selected: Category;
    trail: Category[];
    columns: CategoryColumn[];
};

type StatusFilter =
    | 'all'
    | 'storefront_visible'
    | 'admin_active'
    | 'admin_inactive'
    | 'taxonomy_unavailable'
    | 'archived';

type EditorMode = 'details' | 'create' | 'empty';

const inputClassName =
    'w-full rounded-xl border border-slate-200 bg-white p-3 text-sm outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10 dark:border-slate-700 dark:bg-slate-950';

function requestWasCancelled(caught: unknown): boolean {
    return (
        caught instanceof Error &&
        (caught.name === 'AbortError' || caught.name === 'HttpCancelledError')
    );
}

function CategoryThumbnail({
    category,
    size = 'large',
}: {
    category: Category;
    size?: 'small' | 'large';
}) {
    return (
        <div
            className={`grid shrink-0 place-items-center overflow-hidden bg-primary/10 font-black text-primary ring-1 ring-primary/10 ${size === 'large' ? 'size-24 rounded-2xl text-2xl' : 'size-9 rounded-lg text-sm'}`}
        >
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

function StatusBadges({ category }: { category: Category }) {
    return (
        <div className="flex flex-wrap gap-2">
            {category.deleted_at && (
                <span className="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-100">
                    Archived
                </span>
            )}
            <span
                className={`rounded-full px-2.5 py-1 text-xs font-bold ${category.is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200' : 'bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-200'}`}
            >
                Admin {category.is_active ? 'active' : 'inactive'}
            </span>
            <span className="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-bold text-violet-800 dark:bg-violet-950/60 dark:text-violet-200">
                {category.google_product_category_id === null
                    ? 'Manual taxonomy'
                    : category.is_taxonomy_available === false
                      ? 'Taxonomy unavailable'
                      : 'Taxonomy available'}
            </span>
            <span
                className={`rounded-full px-2.5 py-1 text-xs font-bold ${category.is_storefront_available ? 'bg-primary/10 text-primary' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}
            >
                Storefront{' '}
                {category.is_storefront_available ? 'visible' : 'hidden'}
            </span>
            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                {category.is_selectable
                    ? 'Listings allowed'
                    : 'Navigation only'}
            </span>
        </div>
    );
}

function AdminParentPicker({
    category,
    selected,
    onSelect,
}: {
    category: Category | null;
    selected: Category | null;
    onSelect: (category: Category | null) => void;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Category[]>([]);
    const [loadError, setLoadError] = useState<string | null>(null);
    const request = useHttp<Record<string, never>, { data: Category[] }>({});
    const { cancel, get } = request;

    useEffect(() => {
        if (query.trim() === '') {
            cancel();

            return;
        }

        const timeout = window.setTimeout(() => {
            cancel();
            setLoadError(null);

            void get(
                searchCategories.url({
                    query: {
                        query: query.trim(),
                        status: 'all',
                        parent_options: 1,
                        ...(category
                            ? { exclude_subtree_id: category.id }
                            : {}),
                    },
                }),
            )
                .then((response) => setResults(response.data))
                .catch((caught: unknown) => {
                    if (!requestWasCancelled(caught)) {
                        setLoadError('Parent categories could not be loaded.');
                    }
                });
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            cancel();
        };
    }, [cancel, category, get, query]);

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between gap-3">
                <label
                    htmlFor={`parent-search-${category?.id ?? 'new'}`}
                    className="text-sm font-bold"
                >
                    Parent category
                </label>
                {selected && (
                    <button
                        type="button"
                        onClick={() => onSelect(null)}
                        className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                        <X className="size-3.5" />
                        Make root
                    </button>
                )}
            </div>
            {selected && (
                <div className="flex items-start gap-2 rounded-xl border border-primary/20 bg-primary/5 p-3 text-sm">
                    <FolderTree className="mt-0.5 size-4 shrink-0 text-primary" />
                    <span className="min-w-0">
                        <strong className="block truncate">
                            {selected.name}
                        </strong>
                        <span className="block truncate text-xs text-muted-foreground">
                            {selected.path}
                        </span>
                    </span>
                </div>
            )}
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    id={`parent-search-${category?.id ?? 'new'}`}
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search for a different parent"
                    autoComplete="off"
                    className={`${inputClassName} pl-10`}
                />
                {request.processing && (
                    <LoaderCircle className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-primary" />
                )}
            </div>
            {query.trim() !== '' && (
                <div className="max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-700 dark:bg-slate-950">
                    {loadError && (
                        <p className="p-4 text-center text-sm text-red-600">
                            {loadError}
                        </p>
                    )}
                    {!request.processing &&
                        !loadError &&
                        results.length === 0 && (
                            <p className="p-4 text-center text-sm text-muted-foreground">
                                No eligible parent found.
                            </p>
                        )}
                    {results.map((result) => (
                        <button
                            key={result.id}
                            type="button"
                            onClick={() => {
                                onSelect(result);
                                setQuery('');
                            }}
                            className="flex w-full items-start gap-2 rounded-lg px-3 py-2 text-left hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none dark:hover:bg-slate-900"
                        >
                            <FolderTree className="mt-0.5 size-4 shrink-0 text-primary" />
                            <span className="min-w-0">
                                <span className="block truncate text-sm font-bold">
                                    {result.name}
                                </span>
                                <span className="block truncate text-xs text-muted-foreground">
                                    {result.path}
                                </span>
                            </span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function CategoryMetadataForm({
    category,
    parent,
}: {
    category: Category;
    parent: Category | null;
}) {
    const [selectedParent, setSelectedParent] = useState<Category | null>(
        parent,
    );

    return (
        <Form
            {...update.form(category.id, {
                query: { category: category.id },
            })}
            setDefaultsOnSuccess
            className="grid gap-4"
        >
            {({ errors, processing, recentlySuccessful }) => (
                <>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <label className="grid gap-1.5 text-sm font-bold">
                            Name
                            <input
                                required
                                name="name"
                                defaultValue={category.name}
                                className={inputClassName}
                            />
                            {errors.name && (
                                <span className="font-normal text-red-600">
                                    {errors.name}
                                </span>
                            )}
                        </label>
                        <label className="grid gap-1.5 text-sm font-bold">
                            URL slug
                            <input
                                name="slug"
                                defaultValue={category.slug}
                                placeholder="Generated from name when empty"
                                className={inputClassName}
                            />
                            {errors.slug && (
                                <span className="font-normal text-red-600">
                                    {errors.slug}
                                </span>
                            )}
                        </label>
                    </div>
                    <input
                        type="hidden"
                        name="parent_id"
                        value={selectedParent?.id ?? ''}
                    />
                    <AdminParentPicker
                        category={category}
                        selected={selectedParent}
                        onSelect={setSelectedParent}
                    />
                    {errors.parent_id && (
                        <p className="text-sm text-red-600">
                            {errors.parent_id}
                        </p>
                    )}
                    <div className="grid gap-4 sm:grid-cols-3">
                        <label className="grid gap-1.5 text-sm font-bold">
                            Google category ID
                            <input
                                name="google_product_category_id"
                                type="number"
                                min="1"
                                defaultValue={
                                    category.google_product_category_id ?? ''
                                }
                                className={inputClassName}
                            />
                        </label>
                        <label className="grid gap-1.5 text-sm font-bold">
                            Commission %
                            <input
                                required
                                name="commission_percentage"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                defaultValue={category.commission_percentage}
                                className={inputClassName}
                            />
                        </label>
                        <label className="grid gap-1.5 text-sm font-bold">
                            Return window days
                            <input
                                required
                                name="return_window_days"
                                type="number"
                                min="0"
                                max="365"
                                defaultValue={category.return_window_days}
                                className={inputClassName}
                            />
                        </label>
                    </div>
                    <label className="flex items-center gap-2 text-sm font-bold">
                        <input type="hidden" name="cod_enabled" value="0" />
                        <input
                            name="cod_enabled"
                            type="checkbox"
                            value="1"
                            defaultChecked={category.cod_enabled}
                            className="size-4 rounded border-slate-300 text-primary focus:ring-primary"
                        />
                        Cash on delivery enabled
                    </label>
                    <label className="grid gap-1.5 text-sm font-bold">
                        Audit reason
                        <textarea
                            required
                            minLength={5}
                            name="reason"
                            placeholder="Why are these category details changing?"
                            className={`${inputClassName} min-h-24`}
                        />
                    </label>
                    {Object.entries(errors)
                        .filter(
                            ([field]) =>
                                !['name', 'slug', 'parent_id'].includes(field),
                        )
                        .map(([field, error]) => (
                            <p key={field} className="text-sm text-red-600">
                                {error}
                            </p>
                        ))}
                    <div className="flex items-center gap-3">
                        <button
                            disabled={processing}
                            className="rounded-xl bg-primary px-5 py-3 text-sm font-black text-primary-foreground disabled:opacity-50"
                        >
                            {processing ? 'Saving…' : 'Save category details'}
                        </button>
                        {recentlySuccessful && (
                            <span className="inline-flex items-center gap-1 text-sm font-bold text-emerald-700 dark:text-emerald-300">
                                <Check className="size-4" /> Saved
                            </span>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}

function CategoryArtwork({ category }: { category: Category }) {
    return (
        <section className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
            <div className="flex items-center gap-2">
                <Upload className="size-4 text-primary" />
                <h4 className="text-sm font-black">
                    {category.image_url
                        ? 'Replace category image'
                        : 'Upload category image'}
                </h4>
            </div>
            <Form
                {...storeImage.form(category.id, {
                    query: { category: category.id },
                })}
                resetOnSuccess
                onSubmit={(event) => {
                    if (
                        category.image_url &&
                        !window.confirm(
                            'Are you sure you want to replace the current category image?',
                        )
                    ) {
                        event.preventDefault();
                    }
                }}
                className="mt-3 grid gap-3"
            >
                {({ errors, processing, progress }) => (
                    <>
                        <input
                            required
                            type="file"
                            name="image"
                            accept="image/jpeg,image/png,image/webp"
                            className={inputClassName}
                        />
                        <input
                            required
                            minLength={5}
                            name="reason"
                            placeholder="Reason for artwork change"
                            className={inputClassName}
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
                    {...destroyImage.form(category.id, {
                        query: { category: category.id },
                    })}
                    resetOnSuccess
                    onSubmit={(event) => {
                        if (
                            !window.confirm(
                                'Are you sure you want to remove the current category image?',
                            )
                        ) {
                            event.preventDefault();
                        }
                    }}
                    className="mt-3 grid gap-3 border-t border-slate-200 pt-3 dark:border-slate-800"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                required
                                minLength={5}
                                name="reason"
                                placeholder="Reason for removing artwork"
                                className={inputClassName}
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
        </section>
    );
}

function CategoryAvailability({ category }: { category: Category }) {
    const nextActive = !category.is_active;

    return (
        <section className="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
            <h4 className="text-sm font-black">Category availability</h4>
            <p className="mt-1 text-xs leading-5 text-slate-500">
                {nextActive
                    ? 'Activation includes this subtree. If an ancestor is inactive, its entire subtree is activated too.'
                    : 'Deactivation immediately hides this category, every descendant, and their public listings.'}
            </p>
            {category.is_taxonomy_available === false && (
                <p className="mt-2 rounded-xl bg-violet-100 p-3 text-xs font-semibold text-violet-800 dark:bg-violet-950/60 dark:text-violet-200">
                    Taxonomy synchronization currently keeps this category off
                    the storefront, even when admin-active.
                </p>
            )}
            <Form
                {...updateActivation.form(category.id, {
                    query: { category: category.id },
                })}
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
                            className={inputClassName}
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
        </section>
    );
}

function CategoryArchiveControls({
    category,
    canRestore,
}: {
    category: Category;
    canRestore: boolean;
}) {
    if (category.deleted_at) {
        return (
            <section className="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                <h4 className="text-sm font-black">Archived category</h4>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                    Archived records are read-only. Category artwork and links
                    are preserved.
                </p>
                {canRestore ? (
                    <Form
                        {...restore.form(category.id, {
                            query: { category: category.id },
                        })}
                        resetOnSuccess
                        className="mt-3 grid gap-3"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    required
                                    minLength={5}
                                    name="reason"
                                    placeholder="Reason for restoring this category"
                                    className={inputClassName}
                                />
                                {errors.reason && (
                                    <p className="text-sm text-red-600">
                                        {errors.reason}
                                    </p>
                                )}
                                <button
                                    disabled={processing}
                                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-black text-primary-foreground disabled:opacity-50"
                                >
                                    <RotateCcw className="size-4" />
                                    Restore category
                                </button>
                            </>
                        )}
                    </Form>
                ) : (
                    <p className="mt-3 text-sm font-semibold text-muted-foreground">
                        A super administrator must restore this category.
                    </p>
                )}
            </section>
        );
    }

    return (
        <section className="rounded-2xl border border-red-200 p-4 dark:border-red-900">
            <h4 className="text-sm font-black text-red-800 dark:text-red-200">
                Archive category
            </h4>
            <p className="mt-1 text-xs leading-5 text-slate-500">
                The record and artwork are preserved, but this category is
                removed from active administration and the storefront.
            </p>
            <Form
                {...destroy.form(category.id, {
                    query: { category: category.id },
                })}
                resetOnSuccess
                onSubmit={(event) => {
                    if (
                        !window.confirm(
                            'Are you sure you want to archive this category?',
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
                            required
                            minLength={5}
                            name="reason"
                            placeholder="Reason for archiving this category"
                            className={inputClassName}
                        />
                        {errors.reason && (
                            <p className="text-sm text-red-600">
                                {errors.reason}
                            </p>
                        )}
                        <button
                            disabled={processing}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-red-700 px-4 py-2.5 text-sm font-black text-white disabled:opacity-50"
                        >
                            <Archive className="size-4" />
                            Archive category
                        </button>
                    </>
                )}
            </Form>
        </section>
    );
}

function CategoryDetails({
    category,
    trail,
}: {
    category: Category;
    trail: Category[];
}) {
    const parent = trail.at(-2) ?? null;

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                <CategoryThumbnail category={category} />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-primary">
                        Selected category
                    </p>
                    <h2 className="mt-1 text-2xl font-black">
                        {category.name}
                    </h2>
                    <p className="mt-1 text-sm break-words text-muted-foreground">
                        {category.path}
                    </p>
                    <div className="mt-3">
                        <StatusBadges category={category} />
                    </div>
                </div>
            </div>

            {category.deleted_at ? (
                <div className="mt-6">
                    <CategoryArchiveControls
                        category={category}
                        canRestore={category.capabilities.can_restore}
                    />
                </div>
            ) : (
                <div className="mt-6 grid gap-6">
                    <section className="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                        <h3 className="text-lg font-black">Category details</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Update catalog metadata without changing taxonomy or
                            activation state.
                        </p>
                        <div className="mt-4">
                            <CategoryMetadataForm
                                key={category.id}
                                category={category}
                                parent={parent}
                            />
                        </div>
                    </section>
                    <div className="grid gap-4 lg:grid-cols-2">
                        <CategoryArtwork category={category} />
                        <CategoryAvailability category={category} />
                    </div>
                    <CategoryArchiveControls
                        category={category}
                        canRestore={category.capabilities.can_restore}
                    />
                </div>
            )}
        </section>
    );
}

function CreateCategory({
    suggestedParent,
    onCancel,
}: {
    suggestedParent: Category | null;
    onCancel: () => void;
}) {
    const [parent, setParent] = useState<Category | null>(suggestedParent);

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-primary">
                        Catalog
                    </p>
                    <h2 className="mt-1 text-2xl font-black">
                        Create category
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add a root category or place it beneath an existing
                        branch.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={onCancel}
                    className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
                >
                    Cancel
                </button>
            </div>
            <Form {...store.form()} resetOnSuccess className="mt-6 grid gap-4">
                {({ errors, processing, progress }) => (
                    <>
                        <div className="grid gap-4 lg:grid-cols-2">
                            <label className="grid gap-1.5 text-sm font-bold">
                                Name
                                <input
                                    required
                                    name="name"
                                    placeholder="Category name"
                                    className={inputClassName}
                                />
                            </label>
                            <label className="grid gap-1.5 text-sm font-bold">
                                URL slug
                                <input
                                    name="slug"
                                    placeholder="Generated from name when empty"
                                    className={inputClassName}
                                />
                            </label>
                        </div>
                        <input
                            type="hidden"
                            name="parent_id"
                            value={parent?.id ?? ''}
                        />
                        <AdminParentPicker
                            category={null}
                            selected={parent}
                            onSelect={setParent}
                        />
                        <div className="grid gap-4 sm:grid-cols-3">
                            <label className="grid gap-1.5 text-sm font-bold">
                                Google category ID
                                <input
                                    name="google_product_category_id"
                                    type="number"
                                    min="1"
                                    className={inputClassName}
                                />
                            </label>
                            <label className="grid gap-1.5 text-sm font-bold">
                                Commission %
                                <input
                                    required
                                    name="commission_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    defaultValue="8"
                                    className={inputClassName}
                                />
                            </label>
                            <label className="grid gap-1.5 text-sm font-bold">
                                Return window days
                                <input
                                    required
                                    name="return_window_days"
                                    type="number"
                                    min="0"
                                    max="365"
                                    defaultValue="7"
                                    className={inputClassName}
                                />
                            </label>
                        </div>
                        <label className="grid gap-1.5 text-sm font-bold">
                            Category image (optional)
                            <input
                                type="file"
                                name="image"
                                accept="image/jpeg,image/png,image/webp"
                                className={inputClassName}
                            />
                        </label>
                        <div className="flex flex-wrap gap-5">
                            <label className="flex items-center gap-2 text-sm font-bold">
                                <input
                                    type="hidden"
                                    name="cod_enabled"
                                    value="0"
                                />
                                <input
                                    name="cod_enabled"
                                    type="checkbox"
                                    value="1"
                                    defaultChecked
                                    className="size-4 rounded border-slate-300 text-primary focus:ring-primary"
                                />
                                COD enabled
                            </label>
                            <label className="flex items-center gap-2 text-sm font-bold">
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="0"
                                />
                                <input
                                    name="is_active"
                                    type="checkbox"
                                    value="1"
                                    defaultChecked
                                    className="size-4 rounded border-slate-300 text-primary focus:ring-primary"
                                />
                                Admin active
                            </label>
                        </div>
                        <label className="grid gap-1.5 text-sm font-bold">
                            Audit reason
                            <textarea
                                required
                                minLength={5}
                                name="reason"
                                placeholder="Why is this category being created?"
                                className={`${inputClassName} min-h-24`}
                            />
                        </label>
                        {progress && (
                            <progress
                                value={progress.percentage}
                                max="100"
                                className="h-2 w-full"
                            />
                        )}
                        {Object.entries(errors).map(([field, error]) => (
                            <p key={field} className="text-sm text-red-600">
                                {error}
                            </p>
                        ))}
                        <button
                            disabled={processing}
                            className="w-fit rounded-xl bg-primary px-5 py-3 text-sm font-black text-primary-foreground disabled:opacity-50"
                        >
                            {processing ? 'Creating…' : 'Create category'}
                        </button>
                    </>
                )}
            </Form>
        </section>
    );
}

function ColumnBrowser({
    columns,
    trail,
    loadingParentId,
    loadError,
    onSelect,
    onRetry,
}: {
    columns: CategoryColumn[];
    trail: Category[];
    loadingParentId: number | null;
    loadError: { category: Category; columnIndex: number } | null;
    onSelect: (category: Category, columnIndex: number) => void;
    onRetry: (category: Category, columnIndex: number) => void;
}) {
    const browserRef = useRef<HTMLDivElement>(null);
    const selectedIds = useMemo(
        () => new Set(trail.map((category) => category.id)),
        [trail],
    );

    useEffect(() => {
        const browser = browserRef.current;

        if (browser) {
            browser.scrollTo({ left: browser.scrollWidth, behavior: 'smooth' });
        }
    }, [columns.length]);

    return (
        <div
            ref={browserRef}
            aria-label="Category hierarchy"
            className="flex min-h-96 snap-x snap-proximity overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            {columns.map((column, columnIndex) => {
                const parent = trail.find(
                    (category) => category.id === column.parent_id,
                );

                return (
                    <section
                        key={`${column.parent_id ?? 'root'}-${columnIndex}`}
                        className="w-[19rem] shrink-0 snap-start border-r border-slate-200 last:border-r-0 dark:border-slate-800"
                        aria-label={
                            column.parent_id === null
                                ? 'Root categories'
                                : `Children of ${parent?.name ?? 'category'}`
                        }
                    >
                        <div className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/95 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
                            <p className="truncate text-xs font-black tracking-wide text-slate-500 uppercase">
                                {column.parent_id === null
                                    ? 'Root categories'
                                    : parent?.name}
                            </p>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {column.categories.length}{' '}
                                {column.categories.length === 1
                                    ? 'category'
                                    : 'categories'}
                            </p>
                        </div>
                        <div
                            role="listbox"
                            aria-label="Categories"
                            className="max-h-[32rem] overflow-y-auto p-2"
                        >
                            {column.categories.map((category) => {
                                const isSelected = selectedIds.has(category.id);

                                return (
                                    <button
                                        key={category.id}
                                        type="button"
                                        role="option"
                                        aria-selected={isSelected}
                                        onClick={() =>
                                            onSelect(category, columnIndex)
                                        }
                                        className={`group flex w-full items-center gap-2 rounded-xl px-2.5 py-2 text-left transition focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none ${isSelected ? 'bg-primary text-primary-foreground shadow-sm' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}
                                    >
                                        <CategoryThumbnail
                                            category={category}
                                            size="small"
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-bold">
                                                {category.name}
                                            </span>
                                            <span
                                                className={`mt-0.5 flex items-center gap-1.5 text-[11px] ${isSelected ? 'text-primary-foreground/75' : 'text-muted-foreground'}`}
                                            >
                                                <span
                                                    className={`size-1.5 rounded-full ${category.deleted_at ? 'bg-slate-400' : category.is_storefront_available ? 'bg-emerald-500' : category.is_active ? 'bg-violet-500' : 'bg-red-500'}`}
                                                />
                                                {category.deleted_at
                                                    ? 'Archived'
                                                    : category.is_storefront_available
                                                      ? 'Storefront visible'
                                                      : category.is_active
                                                        ? 'Admin active'
                                                        : 'Admin inactive'}
                                            </span>
                                        </span>
                                        {category.has_children && (
                                            <ChevronRight
                                                className={`size-4 shrink-0 ${isSelected ? 'text-primary-foreground' : 'text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-200'}`}
                                            />
                                        )}
                                    </button>
                                );
                            })}
                            {column.categories.length === 0 && (
                                <p className="p-8 text-center text-sm text-muted-foreground">
                                    No categories at this level.
                                </p>
                            )}
                            {loadingParentId !== null &&
                                trail.at(-1)?.id === loadingParentId &&
                                columnIndex === columns.length - 1 && (
                                    <div className="grid place-items-center p-8 text-primary">
                                        <LoaderCircle className="size-5 animate-spin" />
                                    </div>
                                )}
                            {loadError &&
                                loadError.columnIndex === columnIndex && (
                                    <div className="grid gap-2 p-4 text-center text-sm text-red-600">
                                        <span>
                                            Children could not be loaded.
                                        </span>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                onRetry(
                                                    loadError.category,
                                                    loadError.columnIndex,
                                                )
                                            }
                                            className="font-black underline"
                                        >
                                            Try again
                                        </button>
                                    </div>
                                )}
                        </div>
                    </section>
                );
            })}
        </div>
    );
}

type CategoriesProps = {
    rootCategories: Category[];
    selectedContext: CategoryContext | null;
    categoryCount: number;
};

export default function Categories(props: CategoriesProps) {
    const selectedVersion =
        props.selectedContext?.selected.updated_at ?? 'root';
    const selectedId = props.selectedContext?.selected.id ?? 'none';

    return (
        <CategoriesPage
            key={`${selectedId}-${selectedVersion}-${props.selectedContext?.selected.deleted_at ?? 'active'}`}
            {...props}
        />
    );
}

function CategoriesPage({
    rootCategories,
    selectedContext,
    categoryCount,
}: CategoriesProps) {
    const [columns, setColumns] = useState<CategoryColumn[]>(
        selectedContext?.columns ?? [
            { parent_id: null, categories: rootCategories },
        ],
    );
    const [selected, setSelected] = useState<Category | null>(
        selectedContext?.selected ?? null,
    );
    const [trail, setTrail] = useState<Category[]>(
        selectedContext?.trail ?? [],
    );
    const [editorMode, setEditorMode] = useState<EditorMode>(
        selectedContext ? 'details' : 'empty',
    );
    const [suggestedParent, setSuggestedParent] = useState<Category | null>(
        null,
    );
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState<StatusFilter>('all');
    const [searchResults, setSearchResults] = useState<Category[]>([]);
    const [searchError, setSearchError] = useState<string | null>(null);
    const [loadingParentId, setLoadingParentId] = useState<number | null>(null);
    const [loadError, setLoadError] = useState<{
        category: Category;
        columnIndex: number;
    } | null>(null);
    const childRequest = useHttp<Record<string, never>, { data: Category[] }>(
        {},
    );
    const searchRequest = useHttp<Record<string, never>, { data: Category[] }>(
        {},
    );
    const contextRequest = useHttp<
        Record<string, never>,
        { data: CategoryContext }
    >({});
    const childRequestId = useRef(0);
    const { cancel: cancelSearch, get: getSearch } = searchRequest;

    useEffect(() => {
        const searchIsActive = query.trim() !== '' || status !== 'all';

        if (!searchIsActive) {
            cancelSearch();

            return;
        }

        const timeout = window.setTimeout(() => {
            cancelSearch();
            setSearchError(null);

            void getSearch(
                searchCategories.url({
                    query: {
                        ...(query.trim() !== '' ? { query: query.trim() } : {}),
                        status,
                    },
                }),
            )
                .then((response) => setSearchResults(response.data))
                .catch((caught: unknown) => {
                    if (!requestWasCancelled(caught)) {
                        setSearchError(
                            'Categories could not be searched. Try again.',
                        );
                    }
                });
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            cancelSearch();
        };
    }, [cancelSearch, getSearch, query, status]);

    function syncSelectedUrl(categoryId: number): void {
        window.history.replaceState(
            window.history.state,
            '',
            index.url({ query: { category: categoryId } }),
        );
    }

    function applyContext(categoryContext: CategoryContext): void {
        setColumns(categoryContext.columns);
        setSelected(categoryContext.selected);
        setTrail(categoryContext.trail);
        setEditorMode('details');
        setQuery('');
        setStatus('all');
        syncSelectedUrl(categoryContext.selected.id);
    }

    function loadChildren(
        category: Category,
        columnIndex: number,
        retainedColumns: CategoryColumn[],
    ): void {
        const requestId = ++childRequestId.current;
        childRequest.cancel();
        setLoadingParentId(category.id);
        setLoadError(null);

        void childRequest
            .get(children.url({ query: { parent_id: category.id } }))
            .then((response) => {
                if (requestId === childRequestId.current) {
                    setColumns([
                        ...retainedColumns,
                        { parent_id: category.id, categories: response.data },
                    ]);
                }
            })
            .catch((caught: unknown) => {
                if (!requestWasCancelled(caught)) {
                    setLoadError({ category, columnIndex });
                }
            })
            .finally(() => {
                if (requestId === childRequestId.current) {
                    setLoadingParentId(null);
                }
            });
    }

    function selectCategory(category: Category, columnIndex: number): void {
        childRequestId.current += 1;
        childRequest.cancel();
        const retainedColumns = columns.slice(0, columnIndex + 1);
        const nextTrail = [...trail.slice(0, columnIndex), category];

        setColumns(retainedColumns);
        setTrail(nextTrail);
        setSelected(category);
        setEditorMode('details');
        setLoadingParentId(null);
        setLoadError(null);
        syncSelectedUrl(category.id);

        if (category.has_children) {
            loadChildren(category, columnIndex, retainedColumns);
        }
    }

    function focusSearchResult(category: Category): void {
        contextRequest.cancel();
        setSearchError(null);

        void contextRequest
            .get(context.url(category.id))
            .then((response) => applyContext(response.data))
            .catch((caught: unknown) => {
                if (!requestWasCancelled(caught)) {
                    setSearchError(
                        'That category trail could not be loaded. Try again.',
                    );
                }
            });
    }

    const searchIsActive = query.trim() !== '' || status !== 'all';

    return (
        <PortalLayout portal="admin" title="Catalog categories">
            <Head title="Categories" />
            <main className="mx-auto grid max-w-7xl gap-6">
                <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            Catalog
                        </p>
                        <h1 className="mt-1 text-3xl font-black">
                            Category browser
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {categoryCount.toLocaleString()} categories · Select
                            a row to browse from root to leaf and manage its
                            details below.
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => {
                            const parent =
                                selected && !selected.deleted_at
                                    ? selected
                                    : null;
                            setSuggestedParent(parent);
                            setEditorMode('create');
                        }}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-sm font-black text-primary-foreground shadow-sm"
                    >
                        <Plus className="size-4" />
                        New category
                    </button>
                </header>

                <section className="relative rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_15rem]">
                        <div className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Search names, paths, slugs, or Google category IDs"
                                autoComplete="off"
                                className={`${inputClassName} pr-10 pl-10`}
                            />
                            {(searchRequest.processing ||
                                contextRequest.processing) && (
                                <LoaderCircle className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-primary" />
                            )}
                        </div>
                        <select
                            value={status}
                            onChange={(event) =>
                                setStatus(event.target.value as StatusFilter)
                            }
                            aria-label="Filter categories by status"
                            className={inputClassName}
                        >
                            <option value="all">All lifecycle states</option>
                            <option value="storefront_visible">
                                Storefront visible
                            </option>
                            <option value="admin_active">Admin active</option>
                            <option value="admin_inactive">
                                Admin inactive
                            </option>
                            <option value="taxonomy_unavailable">
                                Taxonomy unavailable
                            </option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>

                    {searchIsActive && (
                        <div className="absolute right-4 left-4 z-30 mt-2 max-h-96 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-950">
                            {searchError && (
                                <p className="p-6 text-center text-sm text-red-600">
                                    {searchError}
                                </p>
                            )}
                            {!searchRequest.processing &&
                                !searchError &&
                                searchResults.length === 0 && (
                                    <p className="p-6 text-center text-sm text-muted-foreground">
                                        No matching categories.
                                    </p>
                                )}
                            {searchResults.map((category) => (
                                <button
                                    key={category.id}
                                    type="button"
                                    onClick={() => focusSearchResult(category)}
                                    className="flex w-full items-center gap-3 rounded-xl p-2.5 text-left hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none dark:hover:bg-slate-900"
                                >
                                    <CategoryThumbnail
                                        category={category}
                                        size="small"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-black">
                                            {category.name}
                                        </span>
                                        <span className="block truncate text-xs text-muted-foreground">
                                            {category.path}
                                        </span>
                                    </span>
                                    <span className="shrink-0 text-xs font-semibold text-muted-foreground">
                                        {category.deleted_at
                                            ? 'Archived'
                                            : category.is_storefront_available
                                              ? 'Visible'
                                              : category.is_active
                                                ? 'Admin active'
                                                : 'Inactive'}
                                    </span>
                                    <ChevronRight className="size-4 shrink-0 text-slate-400" />
                                </button>
                            ))}
                            {searchResults.length === 50 && (
                                <p className="border-t border-slate-200 p-3 text-center text-xs text-muted-foreground dark:border-slate-800">
                                    Showing the first 50 results. Refine your
                                    search to narrow the list.
                                </p>
                            )}
                        </div>
                    )}
                </section>

                <ColumnBrowser
                    columns={columns}
                    trail={trail}
                    loadingParentId={loadingParentId}
                    loadError={loadError}
                    onSelect={selectCategory}
                    onRetry={(category, columnIndex) =>
                        loadChildren(
                            category,
                            columnIndex,
                            columns.slice(0, columnIndex + 1),
                        )
                    }
                />

                {editorMode === 'create' && (
                    <CreateCategory
                        key={suggestedParent?.id ?? 'root'}
                        suggestedParent={suggestedParent}
                        onCancel={() =>
                            setEditorMode(selected ? 'details' : 'empty')
                        }
                    />
                )}
                {editorMode === 'details' && selected && (
                    <CategoryDetails
                        key={selected.id}
                        category={selected}
                        trail={trail}
                    />
                )}
                {editorMode === 'empty' && (
                    <section className="rounded-2xl border border-dashed border-slate-300 p-10 text-center dark:border-slate-700">
                        <FolderTree className="mx-auto size-8 text-slate-400" />
                        <h2 className="mt-3 text-lg font-black">
                            Select a category
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Its details and management controls will appear
                            here.
                        </p>
                    </section>
                )}
            </main>
        </PortalLayout>
    );
}
