import { Form, Head, Link, router } from '@inertiajs/react';
import { ChevronRight, LayoutGrid, Plus } from 'lucide-react';
import { useState } from 'react';
import {
    show,
    store,
} from '@/actions/App/Http/Controllers/AdminCollectionController';
import { AdminPagination } from '@/components/admin-pagination';
import { ListingPicker } from '@/components/listing-picker';
import { PortalLayout } from '@/components/portal-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { PaginationLink } from '@/types';

type AdminCollectionRow = {
    id: number;
    name: string;
    slug: string;
    type: 'rule' | 'manual';
    rule_key: string | null;
    is_active: boolean;
    show_on_homepage_tile: boolean;
    show_on_homepage_grid: boolean;
    show_in_navigation: boolean;
    sort_order: number;
    listings_count: number;
    deleted_at: string | null;
};

type AdminCollectionPaginator = {
    data: AdminCollectionRow[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    next_page_url: string | null;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

function StatusBadge({ collection }: { collection: AdminCollectionRow }) {
    if (collection.deleted_at) {
        return <Badge variant="destructive">Archived</Badge>;
    }

    return collection.is_active ? (
        <Badge>Active</Badge>
    ) : (
        <Badge variant="outline">Inactive</Badge>
    );
}

function HomepageFlags({ collection }: { collection: AdminCollectionRow }) {
    const flags = [
        collection.show_on_homepage_tile ? 'Tile' : null,
        collection.show_on_homepage_grid ? 'Grid' : null,
        collection.show_in_navigation ? 'Nav' : null,
    ].filter((flag): flag is string => flag !== null);

    if (flags.length === 0) {
        return <span className="text-slate-400">—</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {flags.map((flag) => (
                <Badge key={flag} variant="secondary">
                    {flag}
                </Badge>
            ))}
        </div>
    );
}

function AddCollectionDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <Plus className="size-4" />
                    Add collection
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Add collection</DialogTitle>
                    <DialogDescription>
                        Group hand-picked products under a named collection
                        (e.g. Men&apos;s, Women&apos;s) with its own storefront
                        page.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...store.form()}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="type" value="manual" />
                            <input
                                required
                                name="name"
                                placeholder="Collection name"
                                className="rounded-xl border p-3 text-sm"
                            />
                            {errors.name && (
                                <p className="text-sm text-red-600">
                                    {errors.name}
                                </p>
                            )}
                            <input
                                name="slug"
                                placeholder="URL slug (optional)"
                                className="rounded-xl border p-3 text-sm"
                            />
                            <textarea
                                name="description"
                                maxLength={2000}
                                placeholder="Description (optional)"
                                className="min-h-16 rounded-xl border p-3 text-sm"
                            />
                            <input type="hidden" name="is_active" value="0" />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    defaultChecked
                                />{' '}
                                Active
                            </label>
                            <input
                                type="hidden"
                                name="show_on_homepage_tile"
                                value="0"
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="show_on_homepage_tile"
                                    value="1"
                                />{' '}
                                Show as homepage tile
                            </label>
                            <input
                                type="hidden"
                                name="show_on_homepage_grid"
                                value="0"
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="show_on_homepage_grid"
                                    value="1"
                                />{' '}
                                Show as homepage product section
                            </label>
                            <input
                                type="hidden"
                                name="show_in_navigation"
                                value="0"
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="show_in_navigation"
                                    value="1"
                                />{' '}
                                Show in header nav &amp; footer
                            </label>
                            <input
                                type="number"
                                min="0"
                                name="sort_order"
                                defaultValue={0}
                                placeholder="Sort order"
                                className="rounded-xl border p-3 text-sm"
                            />
                            <div>
                                <p className="mb-1 text-sm font-bold">
                                    Products
                                </p>
                                <ListingPicker name="listing_ids" />
                                {errors.listing_ids && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.listing_ids}
                                    </p>
                                )}
                            </div>
                            <textarea
                                required
                                name="reason"
                                placeholder="Audit reason"
                                className="min-h-16 rounded-xl border p-3 text-sm"
                            />
                            <DialogFooter>
                                <Button disabled={processing} asChild>
                                    <button type="submit">
                                        Create collection
                                    </button>
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function CollectionsIndex({
    collections,
}: {
    collections: AdminCollectionPaginator;
}) {
    return (
        <PortalLayout portal="admin" title="Catalog collections">
            <Head title="Collections" />
            <div className="space-y-6">
                <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                            Catalog
                        </p>
                        <h1 className="mt-1 text-3xl font-black">
                            Collections
                        </h1>
                        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Manage storefront collections — curated groupings
                            like Men&apos;s or Women&apos;s, plus the built-in
                            Deals, Featured, and other rule-based collections.
                        </p>
                    </div>
                    <AddCollectionDialog />
                </header>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    {collections.data.length === 0 ? (
                        <div className="grid place-items-center gap-2 p-12 text-center">
                            <LayoutGrid className="size-8 text-slate-400" />
                            <p className="font-bold">No collections yet</p>
                            <p className="text-sm text-slate-500">
                                Create your first collection to get started.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950">
                                    <tr>
                                        <th className="px-5 py-3">Name</th>
                                        <th className="px-5 py-3">Type</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3">Homepage</th>
                                        <th className="px-5 py-3 text-right">
                                            Products
                                        </th>
                                        <th className="px-5 py-3 text-right">
                                            Sort
                                        </th>
                                        <th className="px-5 py-3" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                    {collections.data.map((collection) => (
                                        <tr
                                            key={collection.id}
                                            onClick={() =>
                                                router.visit(
                                                    show(collection.id),
                                                )
                                            }
                                            className="cursor-pointer transition hover:bg-slate-50 dark:hover:bg-slate-950/60"
                                        >
                                            <td className="px-5 py-4">
                                                <Link
                                                    href={show(collection.id)}
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                    className="font-bold hover:text-primary"
                                                >
                                                    {collection.name}
                                                </Link>
                                                <p className="mt-0.5 text-xs text-slate-500">
                                                    /collections/
                                                    {collection.slug}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4">
                                                {collection.type === 'rule' ? (
                                                    <Badge variant="secondary">
                                                        Rule ·{' '}
                                                        {collection.rule_key}
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        Manual
                                                    </Badge>
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                <StatusBadge
                                                    collection={collection}
                                                />
                                            </td>
                                            <td className="px-5 py-4">
                                                <HomepageFlags
                                                    collection={collection}
                                                />
                                            </td>
                                            <td className="px-5 py-4 text-right font-bold">
                                                {collection.listings_count}
                                            </td>
                                            <td className="px-5 py-4 text-right text-slate-500">
                                                {collection.sort_order}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <ChevronRight className="ml-auto size-4 text-slate-400" />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <AdminPagination paginator={collections} />
                </div>
            </div>
        </PortalLayout>
    );
}
