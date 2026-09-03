import { Form, Head, Link } from '@inertiajs/react';
import {
    create,
    destroy,
    edit,
    show,
} from '@/actions/App/Http/Controllers/SellerListingController';
import { PortalLayout } from '@/components/portal-layout';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type Listing = {
    id: number;
    title: string | null;
    sku: string | null;
    model: string | null;
    status: string;
    moderation_reason: string | null;
    listing_type: string;
    product_type: 'simple' | 'variant';
    price: string | null;
    has_orders: boolean;
    created_at: string;
    brand: { name: string } | null;
    brand_name: string | null;
    category: { name: string } | null;
    auction: { status: string; ends_at: string } | null;
};

const editableStatuses = ['draft', 'changes_requested', 'rejected'];

export default function SellerListings({
    sellerStatus,
    listings,
}: {
    sellerStatus: string;
    listings: { data: Listing[] };
}) {
    return (
        <PortalLayout portal="seller" title="Products">
            <Head title="Products" />
            <main className="mx-auto max-w-7xl">
                <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-bold tracking-wider text-primary uppercase">
                            Seller portal
                        </p>
                        <h1 className="mt-2 text-4xl font-black">Products</h1>
                        <p className="mt-2 text-stone-600 dark:text-stone-300">
                            Account status:{' '}
                            <span className="font-bold capitalize">
                                {sellerStatus.replace('_', ' ')}
                            </span>
                        </p>
                    </div>
                    <Link
                        href={create()}
                        className="rounded-xl bg-primary px-5 py-3 text-center font-bold text-primary-foreground"
                    >
                        Add New Product
                    </Link>
                </div>

                {sellerStatus !== 'approved' && sellerStatus !== 'active' && (
                    <p className="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                        You can prepare drafts now. Your account must be
                        approved before you submit a product for review.
                    </p>
                )}

                <div className="mt-8 overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm dark:border-stone-800 dark:bg-stone-900">
                    {listings.data.length === 0 ? (
                        <div className="p-12 text-center text-stone-500">
                            No products yet. Add your first item when you are
                            ready.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[70rem] text-left text-sm">
                                <thead className="border-b border-stone-200 bg-stone-50 text-xs tracking-wider text-stone-500 uppercase dark:border-stone-800 dark:bg-stone-950 dark:text-stone-400">
                                    <tr>
                                        <th className="px-5 py-4 font-bold">
                                            Name
                                        </th>
                                        <th className="px-4 py-4 font-bold">
                                            SKU
                                        </th>
                                        <th className="px-4 py-4 font-bold">
                                            Model
                                        </th>
                                        <th className="px-4 py-4 font-bold">
                                            Category
                                        </th>
                                        <th className="px-4 py-4 font-bold">
                                            Brand
                                        </th>
                                        <th className="px-4 py-4 font-bold">
                                            Type
                                        </th>
                                        <th className="px-4 py-4 font-bold">
                                            Status
                                        </th>
                                        <th className="px-5 py-4 text-right font-bold">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-stone-200 dark:divide-stone-800">
                                    {listings.data.map((listing) => {
                                        const canEdit =
                                            editableStatuses.includes(
                                                listing.status,
                                            );
                                        const canRemove =
                                            listing.status !== 'archived';

                                        return (
                                            <tr
                                                key={listing.id}
                                                className="transition-colors hover:bg-stone-50/80 dark:hover:bg-stone-800/40"
                                            >
                                                <td className="max-w-72 px-5 py-4 align-top">
                                                    <p className="font-bold text-stone-950 dark:text-stone-50">
                                                        {listing.title ??
                                                            'Untitled product'}
                                                    </p>
                                                    {listing.moderation_reason && (
                                                        <p className="mt-1 line-clamp-2 text-xs text-amber-700 dark:text-amber-300">
                                                            Review note:{' '}
                                                            {
                                                                listing.moderation_reason
                                                            }
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-4 align-top font-medium text-stone-600 dark:text-stone-300">
                                                    {listing.sku ?? '-'}
                                                </td>
                                                <td className="px-4 py-4 align-top text-stone-600 dark:text-stone-300">
                                                    {listing.model ?? '-'}
                                                </td>
                                                <td className="px-4 py-4 align-top text-stone-600 dark:text-stone-300">
                                                    {listing.category?.name ??
                                                        'Uncategorised'}
                                                </td>
                                                <td className="px-4 py-4 align-top text-stone-600 dark:text-stone-300">
                                                    {listing.brand?.name ??
                                                        listing.brand_name ??
                                                        '-'}
                                                </td>
                                                <td className="px-4 py-4 align-top">
                                                    <span className="rounded-full bg-stone-100 px-3 py-1 text-xs font-bold dark:bg-stone-800">
                                                        {listing.product_type ===
                                                        'variant'
                                                            ? 'Config'
                                                            : 'Simple'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-4 align-top">
                                                    <span className="inline-flex rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary capitalize">
                                                        {listing.status.replace(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-4 align-top">
                                                    <div className="flex justify-end gap-2">
                                                        {canEdit ? (
                                                            <Link
                                                                href={edit(
                                                                    listing.id,
                                                                )}
                                                                className="rounded-lg border border-stone-300 px-3 py-2 text-xs font-bold transition hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800"
                                                            >
                                                                Edit
                                                            </Link>
                                                        ) : (
                                                            <button
                                                                disabled
                                                                title="Only draft or returned products can be edited"
                                                                className="cursor-not-allowed rounded-lg border border-stone-200 px-3 py-2 text-xs font-bold text-stone-400 dark:border-stone-800 dark:text-stone-600"
                                                            >
                                                                Edit
                                                            </button>
                                                        )}
                                                        <Link
                                                            href={show(
                                                                listing.id,
                                                            )}
                                                            className="rounded-lg border border-stone-300 px-3 py-2 text-xs font-bold transition hover:bg-stone-100 dark:border-stone-700 dark:hover:bg-stone-800"
                                                        >
                                                            View
                                                        </Link>
                                                        {canRemove ? (
                                                            <Dialog>
                                                                <DialogTrigger
                                                                    asChild
                                                                >
                                                                    <button className="rounded-lg border border-red-300 px-3 py-2 text-xs font-bold text-red-700 transition hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/40">
                                                                        {listing.has_orders
                                                                            ? 'Archive'
                                                                            : 'Remove'}
                                                                    </button>
                                                                </DialogTrigger>
                                                                <DialogContent>
                                                                    <DialogTitle>
                                                                        {listing.has_orders
                                                                            ? 'Archive listing'
                                                                            : 'Remove listing'}
                                                                    </DialogTitle>
                                                                    <DialogDescription>
                                                                        {listing.has_orders
                                                                            ? 'This listing has orders, so it will be archived and hidden from the public marketplace. Its order history will remain available.'
                                                                            : 'This listing has no orders. Removing it will hide it from the public marketplace and remove it from your listings.'}
                                                                    </DialogDescription>
                                                                    <DialogFooter className="gap-2">
                                                                        <DialogClose
                                                                            asChild
                                                                        >
                                                                            <button className="rounded-xl border border-stone-300 px-4 py-2 text-sm font-bold dark:border-stone-700">
                                                                                Cancel
                                                                            </button>
                                                                        </DialogClose>
                                                                        <Form
                                                                            {...destroy.form(
                                                                                listing.id,
                                                                            )}
                                                                        >
                                                                            {({
                                                                                processing,
                                                                            }) => (
                                                                                <button
                                                                                    disabled={
                                                                                        processing
                                                                                    }
                                                                                    className="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40"
                                                                                >
                                                                                    {processing
                                                                                        ? 'Working...'
                                                                                        : listing.has_orders
                                                                                          ? 'Archive listing'
                                                                                          : 'Remove listing'}
                                                                                </button>
                                                                            )}
                                                                        </Form>
                                                                    </DialogFooter>
                                                                </DialogContent>
                                                            </Dialog>
                                                        ) : (
                                                            <button
                                                                disabled
                                                                className="cursor-not-allowed rounded-lg border border-stone-200 px-3 py-2 text-xs font-bold text-stone-400 dark:border-stone-800 dark:text-stone-600"
                                                            >
                                                                Remove
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </main>
        </PortalLayout>
    );
}
