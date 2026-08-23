import { Form, Head } from '@inertiajs/react';
import { PortalLayout } from '@/components/portal-layout';
import { store } from '@/routes/admin/brands';

export default function Brands({
    brands,
}: {
    brands: {
        data: {
            id: number;
            name: string;
            slug: string;
            deleted_at: string | null;
        }[];
    };
}) {
    return (
        <PortalLayout portal="admin" title="Catalog brands">
            <Head title="Brands" />
            <main className="mx-auto grid max-w-7xl gap-8 xl:grid-cols-[22rem_1fr]">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-sm font-semibold text-primary">
                        Catalog
                    </p>
                    <h1 className="mt-1 text-2xl font-bold">Add brand</h1>
                    <Form {...store.form()} className="mt-5 grid gap-3">
                        {({ errors, processing }) => (
                            <>
                                <input
                                    required
                                    name="name"
                                    placeholder="Brand name"
                                    className="rounded-xl border p-3"
                                />
                                <input
                                    name="slug"
                                    placeholder="URL slug (optional)"
                                    className="rounded-xl border p-3"
                                />
                                <textarea
                                    required
                                    name="reason"
                                    placeholder="Audit reason"
                                    className="min-h-20 rounded-xl border p-3"
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-600">
                                        {errors.name}
                                    </p>
                                )}
                                <button
                                    disabled={processing}
                                    className="rounded-xl bg-primary px-4 py-3 font-semibold text-primary-foreground"
                                >
                                    Create brand
                                </button>
                            </>
                        )}
                    </Form>
                </section>
                <section>
                    <p className="text-sm font-semibold text-primary">
                        {brands.data.length} brands
                    </p>
                    <h2 className="mt-1 text-3xl font-bold">Brand directory</h2>
                    <div className="mt-6 grid gap-3 sm:grid-cols-2">
                        {brands.data.map((brand) => (
                            <article
                                key={brand.id}
                                className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <h3 className="font-semibold">{brand.name}</h3>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    /{brand.slug}
                                </p>
                                <span className="mt-4 inline-block text-xs font-semibold text-primary">
                                    {brand.deleted_at ? 'Archived' : 'Active'}
                                </span>
                            </article>
                        ))}
                    </div>
                </section>
            </main>
        </PortalLayout>
    );
}
