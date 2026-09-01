import { useHttp } from '@inertiajs/react';
import {
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    FolderTree,
    LoaderCircle,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
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
    const [isOpen, setIsOpen] = useState(false);
    const [options, setOptions] = useState<CategoryOption[]>([]);
    const [breadcrumbs, setBreadcrumbs] = useState<Breadcrumb[]>([]);
    const [loadError, setLoadError] = useState<string | null>(null);
    const request = useHttp<Record<string, never>, { data: CategoryOption[] }>(
        {},
    );
    const currentParent = breadcrumbs.at(-1);
    const currentParentId = currentParent?.id;
    const { cancel, get } = request;
    const pickerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function closeOnOutsideClick(event: PointerEvent): void {
            if (
                pickerRef.current &&
                !pickerRef.current.contains(event.target as Node)
            ) {
                setIsOpen(false);
            }
        }

        document.addEventListener('pointerdown', closeOnOutsideClick);

        return () =>
            document.removeEventListener('pointerdown', closeOnOutsideClick);
    }, []);

    useEffect(() => {
        if (!isOpen) {
            cancel();

            return;
        }

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
    }, [cancel, currentParentId, get, isOpen, query]);

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
        setIsOpen(false);
    }

    return (
        <div ref={pickerRef} className="relative grid gap-2">
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

            <button
                type="button"
                role="combobox"
                aria-expanded={isOpen}
                aria-controls="category-picker-options"
                onClick={() => setIsOpen((open) => !open)}
                className={`flex min-h-12 w-full items-center gap-3 rounded-xl border bg-white px-3 text-left transition focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none dark:bg-slate-950 ${
                    error
                        ? 'border-red-500'
                        : 'border-slate-300 dark:border-slate-700'
                }`}
            >
                <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                    <FolderTree className="size-4" />
                </span>
                <span className="min-w-0 flex-1">
                    <span
                        className={`block truncate text-sm font-semibold ${selected ? '' : 'text-slate-400'}`}
                    >
                        {selected?.name ?? 'Select product category'}
                    </span>
                    {selected && (
                        <span className="block truncate text-xs text-slate-500">
                            {selected.path}
                        </span>
                    )}
                </span>
                {selected ? (
                    <Check className="size-4 shrink-0 text-emerald-600" />
                ) : (
                    <ChevronDown className="size-4 shrink-0 text-slate-400" />
                )}
            </button>

            {isOpen && (
                <div className="absolute top-full right-0 left-0 z-50 mt-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/15 dark:border-slate-700 dark:bg-slate-950">
                    <div className="relative border-b border-slate-200 dark:border-slate-800">
                        <Search className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            id="category-picker-search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search categories"
                            autoComplete="off"
                            autoFocus
                            className="h-12 w-full bg-transparent pr-11 pl-11 text-sm outline-none"
                        />
                        {request.processing && (
                            <LoaderCircle className="absolute top-1/2 right-4 size-4 -translate-y-1/2 animate-spin text-primary" />
                        )}
                    </div>

                    {query.trim() === '' && (
                        <nav
                            aria-label="Category breadcrumbs"
                            className="flex min-h-10 items-center gap-1 overflow-x-auto border-b border-slate-200 px-2 text-xs dark:border-slate-800"
                        >
                            <button
                                type="button"
                                onClick={() => {
                                    setQuery('');
                                    setBreadcrumbs([]);
                                }}
                                className="shrink-0 rounded-md px-2 py-1 font-semibold hover:bg-slate-100 dark:hover:bg-slate-800"
                            >
                                All categories
                            </button>
                            {breadcrumbs.map((crumb, index) => (
                                <span
                                    key={crumb.id}
                                    className="flex shrink-0 items-center gap-1"
                                >
                                    <ChevronRight className="size-3 text-slate-400" />
                                    <button
                                        type="button"
                                        onClick={() => returnTo(index)}
                                        className="rounded-md px-2 py-1 font-semibold hover:bg-slate-100 dark:hover:bg-slate-800"
                                    >
                                        {crumb.name}
                                    </button>
                                </span>
                            ))}
                        </nav>
                    )}

                    <div
                        id="category-picker-options"
                        role="listbox"
                        aria-label="Category options"
                        className="max-h-80 overflow-y-auto p-2"
                    >
                        {currentParent && query.trim() === '' && (
                            <button
                                type="button"
                                onClick={() => {
                                    setQuery('');
                                    setBreadcrumbs((trail) =>
                                        trail.slice(0, -1),
                                    );
                                }}
                                className="mb-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-900"
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
                                <p className="p-6 text-center text-sm text-slate-500">
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
                                    className={`flex items-center gap-1 rounded-xl ${isSelected ? 'bg-primary/10' : 'hover:bg-slate-50 dark:hover:bg-slate-900'}`}
                                >
                                    <button
                                        type="button"
                                        onClick={() => choose(category)}
                                        className="flex min-w-0 flex-1 items-start gap-3 px-3 py-3 text-left"
                                    >
                                        <FolderTree className="mt-0.5 size-4 shrink-0 text-primary" />
                                        <span className="min-w-0">
                                            <span className="block truncate text-sm font-semibold">
                                                {category.name}
                                            </span>
                                            {category.path !== category.name &&
                                                (query.trim() !== '' ||
                                                    !category.is_selectable) && (
                                                    <span className="block truncate text-xs text-slate-500">
                                                        {category.path}
                                                    </span>
                                                )}
                                        </span>
                                        {isSelected && (
                                            <Check className="mt-0.5 ml-auto size-4 shrink-0 text-primary" />
                                        )}
                                    </button>
                                    {category.has_children && (
                                        <button
                                            type="button"
                                            onClick={() => drillInto(category)}
                                            aria-label={`Browse ${category.name}`}
                                            className="mr-1 rounded-lg p-2 text-slate-500 hover:bg-slate-200 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white"
                                        >
                                            <ChevronRight className="size-4" />
                                        </button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}
