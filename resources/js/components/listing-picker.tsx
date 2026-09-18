import { useHttp } from '@inertiajs/react';
import { ArrowDown, ArrowUp, LoaderCircle, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { listings as searchListings } from '@/actions/App/Http/Controllers/AdminCollectionController';

type PickerListing = { id: number; title: string };

function requestWasCancelled(caught: unknown): boolean {
    return (
        caught instanceof Error &&
        (caught.name === 'AbortError' || caught.name === 'HttpCancelledError')
    );
}

export function ListingPicker({
    name,
    initialSelected = [],
}: {
    name: string;
    initialSelected?: PickerListing[];
}) {
    const [selected, setSelected] = useState<PickerListing[]>(initialSelected);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<PickerListing[]>([]);
    const [loadError, setLoadError] = useState<string | null>(null);
    const request = useHttp<Record<string, never>, { data: PickerListing[] }>(
        {},
    );
    const { cancel, get } = request;

    useEffect(() => {
        if (query.trim() === '') {
            cancel();

            return;
        }

        const timeout = window.setTimeout(() => {
            cancel();
            setLoadError(null);

            void get(searchListings.url({ query: { search: query.trim() } }))
                .then((response) => setResults(response.data))
                .catch((caught: unknown) => {
                    if (!requestWasCancelled(caught)) {
                        setLoadError('Products could not be loaded.');
                    }
                });
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            cancel();
        };
    }, [cancel, get, query]);

    function addListing(listing: PickerListing): void {
        setSelected((current) =>
            current.some((item) => item.id === listing.id)
                ? current
                : [...current, listing],
        );
        setQuery('');
    }

    function removeListing(id: number): void {
        setSelected((current) => current.filter((item) => item.id !== id));
    }

    function move(index: number, direction: -1 | 1): void {
        setSelected((current) => {
            const target = index + direction;

            if (target < 0 || target >= current.length) {
                return current;
            }

            const next = [...current];
            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });
    }

    return (
        <div className="grid gap-2">
            {selected.length > 0 && (
                <ul className="grid gap-1.5">
                    {selected.map((listing, index) => (
                        <li
                            key={listing.id}
                            className="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-2 text-sm dark:border-slate-700 dark:bg-slate-950"
                        >
                            <input
                                type="hidden"
                                name={`${name}[]`}
                                value={listing.id}
                            />
                            <span className="min-w-0 flex-1 truncate">
                                {listing.title}
                            </span>
                            <button
                                type="button"
                                onClick={() => move(index, -1)}
                                disabled={index === 0}
                                className="grid size-6 place-items-center rounded text-slate-500 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800"
                                aria-label={`Move ${listing.title} up`}
                            >
                                <ArrowUp className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                onClick={() => move(index, 1)}
                                disabled={index === selected.length - 1}
                                className="grid size-6 place-items-center rounded text-slate-500 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800"
                                aria-label={`Move ${listing.title} down`}
                            >
                                <ArrowDown className="size-3.5" />
                            </button>
                            <button
                                type="button"
                                onClick={() => removeListing(listing.id)}
                                className="grid size-6 place-items-center rounded text-red-600 hover:bg-red-50 dark:hover:bg-red-950"
                                aria-label={`Remove ${listing.title}`}
                            >
                                <X className="size-3.5" />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                <input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Search products to add"
                    autoComplete="off"
                    className="w-full rounded-xl border border-slate-200 bg-white p-3 pl-10 text-sm transition outline-none focus:border-primary focus:ring-4 focus:ring-primary/10 dark:border-slate-700 dark:bg-slate-950"
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
                                No matching products found.
                            </p>
                        )}
                    {results.map((result) => (
                        <button
                            key={result.id}
                            type="button"
                            onClick={() => addListing(result)}
                            className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50 focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none dark:hover:bg-slate-900"
                        >
                            {result.title}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
