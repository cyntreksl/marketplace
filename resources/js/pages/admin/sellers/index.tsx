import { Form, Head, Link } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/AdminSellerController';
import { PortalLayout } from '@/components/portal-layout';
import { dashboard } from '@/routes/admin';

type Seller = {
    id: number;
    store_name: string;
    seller_type: string;
    status: string;
    review_reason: string | null;
    user: { name: string; email: string };
};
export default function AdminSellers({
    sellers,
}: {
    sellers: { data: Seller[] };
}) {
    return (
        <PortalLayout portal="admin" title="Seller verification">
            <Head title="Seller verification" />
            <main className="mx-auto max-w-7xl">
                <Link
                    href={dashboard()}
                    className="text-sm font-bold text-amber-700"
                >
                    ← Operations
                </Link>
                <h1 className="mt-4 text-4xl font-black">
                    Seller verification
                </h1>
                <div className="mt-8 grid gap-4">
                    {sellers.data.map((seller) => (
                        <article
                            key={seller.id}
                            className="grid gap-4 rounded-2xl border border-stone-200 bg-white p-5 lg:grid-cols-[1fr_auto] dark:border-stone-800 dark:bg-stone-900"
                        >
                            <div>
                                <p className="font-bold">{seller.store_name}</p>
                                <p className="mt-1 text-sm text-stone-500">
                                    {seller.user.name} · {seller.user.email} ·{' '}
                                    {seller.seller_type}
                                </p>
                                <p className="mt-2 text-sm capitalize">
                                    Current status:{' '}
                                    {seller.status.replace('_', ' ')}
                                </p>
                            </div>
                            <Form
                                {...update.form(seller.id)}
                                className="grid gap-2 sm:grid-cols-[10rem_1fr_auto]"
                            >
                                {({ processing }) => (
                                    <>
                                        <select
                                            name="status"
                                            defaultValue={seller.status}
                                            className="rounded-lg border bg-transparent p-2"
                                        >
                                            <option value="approved">
                                                Approve
                                            </option>
                                            <option value="active">
                                                Activate
                                            </option>
                                            <option value="rejected">
                                                Reject
                                            </option>
                                            <option value="suspended">
                                                Suspend
                                            </option>
                                        </select>
                                        <input
                                            required
                                            name="reason"
                                            defaultValue={
                                                seller.review_reason ?? ''
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
