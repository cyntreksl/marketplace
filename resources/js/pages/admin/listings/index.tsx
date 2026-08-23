import { Form, Head, Link } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/AdminListingController';
import { PortalLayout } from '@/components/portal-layout';
import { dashboard } from '@/routes/admin';

type Listing = {
    id: number;
    title: string;
    status: string;
    listing_type: string;
    moderation_reason: string | null;
    seller_profile: { store_name: string };
    category: { name: string };
};
export default function AdminListings({
    listings,
}: {
    listings: { data: Listing[] };
}) {
    return (
        <PortalLayout portal="admin" title="Listing moderation">
            <Head title="Listing moderation" />
            <main className="mx-auto max-w-7xl">
                <Link
                    href={dashboard()}
                    className="text-sm font-bold text-amber-700"
                >
                    ← Operations
                </Link>
                <h1 className="mt-4 text-4xl font-black">Listing moderation</h1>
                <div className="mt-8 grid gap-4">
                    {listings.data.map((listing) => (
                        <article
                            key={listing.id}
                            className="grid gap-4 rounded-2xl border border-stone-200 bg-white p-5 lg:grid-cols-[1fr_auto] dark:border-stone-800 dark:bg-stone-900"
                        >
                            <div>
                                <p className="font-bold">{listing.title}</p>
                                <p className="mt-1 text-sm text-stone-500">
                                    {listing.seller_profile.store_name} ·{' '}
                                    {listing.category.name} ·{' '}
                                    {listing.listing_type}
                                </p>
                                <p className="mt-2 text-sm capitalize">
                                    Current status:{' '}
                                    {listing.status.replace('_', ' ')}
                                </p>
                            </div>
                            <Form
                                {...update.form(listing.id)}
                                className="grid gap-2 sm:grid-cols-[11rem_1fr_auto]"
                            >
                                {({ processing }) => (
                                    <>
                                        <select
                                            name="status"
                                            defaultValue={listing.status}
                                            className="rounded-lg border bg-transparent p-2"
                                        >
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
                                        <input
                                            required
                                            name="reason"
                                            defaultValue={
                                                listing.moderation_reason ?? ''
                                            }
                                            placeholder="Decision reason"
                                            className="rounded-lg border bg-transparent p-2"
                                        />
                                        <button
                                            disabled={processing}
                                            className="rounded-full bg-stone-950 px-4 py-2 text-sm font-bold text-white dark:bg-stone-50 dark:text-stone-950"
                                        >
                                            Save
                                        </button>
                                    </>
                                )}
                            </Form>
                        </article>
                    ))}
                </div>
            </main>
        </PortalLayout>
    );
}
