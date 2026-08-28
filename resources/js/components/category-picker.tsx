import { useHttp } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    FolderTree,
    LoaderCircle,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { search as searchCategories } from '@/routes/categories';

export type CategoryOption = {
    id: number;
    name: string;
    path: string;
    slug: string;
    is_selectable: boolean;
    has_children: boolean;
    commission_percentage: string;
};

type Breadcrumb = Pick<CategoryOption, 'id' | 'name'>;

export function CategoryPicker({
    selected,
    onSelect,
    selectionMode = 'leaf',
    label = 'Category',
    error,
}: {
    selected: CategoryOption | null;
    onSelect: (category: CategoryOption | null) => void;
    selectionMode?: 'leaf' | 'any';
    label?: string;
    error?: string;
}) {
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<CategoryOption[]>([]);
    const [breadcrumbs, setBreadcrumbs] = useState<Breadcrumb[]>([]);
    const [loadError, setLoadError] = useState<string | null>(null);
    const request = useHttp<Record<string, never>, { data: CategoryOption[] }>(
        {},
    );
    const currentParent = breadcrumbs.at(-1);
    const currentParentId = currentParent?.id;
    const { cancel, get } = request;

    useEffect(() => {
        const timeout = window.setTimeout(
            () => {
                cancel();
                setLoadError(null);

                void get(
                    searchCategories.url({
                        query: {
                            ...(query.trim() !== ''
                                ? { search: query.trim() }
                                : currentParentId
                                  ? { parent_id: currentParentId }
                                  : {}),
                        },
                    }),
                )
                    .then((response) => setOptions(response.data))
                    .catch((caught: unknown) => {
                        if (
                            caught instanceof Error &&
                            (caught.name === 'AbortError' ||
                                caught.name === 'HttpCancelledError')
                        ) {
                            return;
                        }

                        setLoadError(
                            'Categories could not be loaded. Try again.',
                        );
                    });
            },
            query.trim() === '' ? 0 : 250,
        );

        return () => {
            window.clearTimeout(timeout);
            cancel();
        };
    }, [cancel, currentParentId, get, query]);

    function drillInto(category: CategoryOption): void {
        setQuery('');
        setBreadcrumbs((trail) => [
            ...trail.filter((crumb) => crumb.id !== category.id),
            { id: category.id, name: category.name },
        ]);
    }

    function returnTo(index: number): void {
        setQuery('');
        setBreadcrumbs((trail) => trail.slice(0, index + 1));
    }

    function choose(category: CategoryOption): void {
        if (selectionMode === 'leaf' && !category.is_selectable) {
            drillInto(category);

            return;
        }

        onSelect(category);
    }

    return (
        <div className="grid gap-3">
            <div className="flex items-center justify-between gap-3">
                <label
                    htmlFor="category-picker-search"
                    className="font-semibold"
                >
                    {label}
                </label>
                {selected && (
                    <button
                        type="button"
                        onClick={() => onSelect(null)}
                        className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-stone-500 transition hover:bg-stone-100 hover:text-stone-900 dark:hover:bg-stone-800 dark:hover:text-stone-100"
                    >
                        <X className="size-3.5" />
                        Clear
                    </button>
                )}
            </div>

            {selected && (
                <div
                    className={`flex items-start gap-3 rounded-xl border p-3 text-sm ${
                        selectionMode === 'leaf' && !selected.is_selectable
                            ? 'border-red-300 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/30 dark:text-red-100'
                            : 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100'
                    }`}
                >
                    <Check className="mt-0.5 size-4 shrink-0" />
                    <div>
                        <span className="font-bold">{selected.name}</span>
                        <span className="mt-0.5 block text-xs opacity-75">
                            {selected.path}
                        </span>
                        {selectionMode === 'leaf' &&
                            !selected.is_selectable && (
                                <span className="mt-1 block font-semibold">
                                    This category can no longer accept listings.
                                    Choose a leaf category below.
                                </span>
                            )}
                    </div>
                </div>
            )}

            <div className="overflow-hidden rounded-xl border border-stone-300 bg-white dark:border-stone-700 dark:bg-stone-950">
                <div className="relative border-b border-stone-200 dark:border-stone-800">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
                    <input
                        id="category-picker-search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search all categories"
                        autoComplete="off"
                        className="w-full bg-transparent py-3 pr-10 pl-10 text-sm outline-none focus:ring-4 focus:ring-amber-100 dark:focus:ring-amber-900/30"
                    />
                    {request.processing && (
                        <LoaderCircle className="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-amber-600" />
                    )}
                </div>

                {query.trim() === '' && (
                    <nav
                        aria-label="Category breadcrumbs"
                        className="flex min-h-10 items-center gap-1 overflow-x-auto border-b border-stone-200 px-2 text-xs dark:border-stone-800"
                    >
                        <button
                            type="button"
                            onClick={() => {
                                setQuery('');
                                setBreadcrumbs([]);
                            }}
                            className="shrink-0 rounded-md px-2 py-1 font-semibold hover:bg-stone-100 dark:hover:bg-stone-800"
                        >
                            Departments
                        </button>
                        {breadcrumbs.map((crumb, index) => (
                            <span
                                key={crumb.id}
                                className="flex shrink-0 items-center gap-1"
                            >
                                <ChevronRight className="size-3 text-stone-400" />
                                <button
                                    type="button"
                                    onClick={() => returnTo(index)}
                                    className="rounded-md px-2 py-1 font-semibold hover:bg-stone-100 dark:hover:bg-stone-800"
                                >
                                    {crumb.name}
                                </button>
                            </span>
                        ))}
                    </nav>
                )}

                <div
                    role="listbox"
                    aria-label="Category options"
                    className="max-h-72 overflow-y-auto p-1"
                >
                    {currentParent && query.trim() === '' && (
                        <button
                            type="button"
                            onClick={() => {
                                setQuery('');
                                setBreadcrumbs((trail) => trail.slice(0, -1));
                            }}
                            className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-stone-500 hover:bg-stone-100 dark:hover:bg-stone-900"
                        >
                            <ChevronLeft className="size-4" />
                            Back
                        </button>
                    )}

                    {!request.processing && loadError && (
                        <p className="p-6 text-center text-sm text-red-600">
                            {loadError}
                        </p>
                    )}

                    {!request.processing &&
                        !loadError &&
                        options.length === 0 && (
                            <p className="p-6 text-center text-sm text-stone-500">
                                No categories found.
                            </p>
                        )}

                    {options.map((category) => {
                        const isSelected = selected?.id === category.id;

                        return (
                            <div
                                key={category.id}
                                role="option"
                                aria-selected={isSelected}
                                className={`flex items-center gap-1 rounded-lg ${isSelected ? 'bg-amber-50 dark:bg-amber-950/30' : 'hover:bg-stone-50 dark:hover:bg-stone-900'}`}
                            >
                                <button
                                    type="button"
                                    onClick={() => choose(category)}
                                    className="flex min-w-0 flex-1 items-start gap-3 px-3 py-2.5 text-left"
                                >
                                    <FolderTree className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                                    <span className="min-w-0">
                                        <span className="block truncate text-sm font-semibold">
                                            {category.name}
                                        </span>
                                        {query.trim() !== '' && (
                                            <span className="block truncate text-xs text-stone-500">
                                                {category.path}
                                            </span>
                                        )}
                                    </span>
                                    {isSelected && (
                                        <Check className="mt-0.5 ml-auto size-4 shrink-0 text-amber-700" />
                                    )}
                                </button>
                                {category.has_children &&
                                    (selectionMode === 'any' ||
                                        category.is_selectable) && (
                                        <button
                                            type="button"
                                            onClick={() => drillInto(category)}
                                            aria-label={`Browse ${category.name}`}
                                            className="mr-1 rounded-lg p-2 text-stone-500 hover:bg-stone-200 hover:text-stone-900 dark:hover:bg-stone-800 dark:hover:text-stone-100"
                                        >
                                            <ChevronRight className="size-4" />
                                        </button>
                                    )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}
