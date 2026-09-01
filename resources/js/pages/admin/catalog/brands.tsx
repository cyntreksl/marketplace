import { Form, Head } from '@inertiajs/react';
import { PortalLayout } from '@/components/portal-layout';
import { store, update } from '@/routes/admin/brands';

export default function Brands({
    brands,
}: {
    brands: {
        data: {
            id: number;
            name: string;
            slug: string;
            deleted_at: string | null;
            logo_url: string | null;
            is_featured: boolean;
            homepage_order: number | null;
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
                                <input
                                    type="file"
                                    name="logo"
                                    accept="image/*"
                                    className="rounded-xl border p-3"
                                />
                                <input
                                    type="hidden"
                                    name="is_featured"
                                    value="0"
                                />
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="is_featured"
                                        value="1"
                                    />{' '}
                                    Featured on homepage
                                </label>
                                <input
                                    type="number"
                                    min="0"
                                    name="homepage_order"
                                    placeholder="Homepage order"
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
                                {brand.logo_url && (
                                    <img
                                        src={brand.logo_url}
                                        alt={brand.name}
                                        className="mb-3 h-10 max-w-32 object-contain"
                                    />
                                )}
                                <Form
                                    {...update.form(brand.id)}
                                    className="grid gap-2"
                                >
                                    <input
                                        required
                                        name="name"
                                        defaultValue={brand.name}
                                        className="rounded-lg border bg-transparent p-2 text-sm"
                                    />
                                    <input
                                        name="slug"
                                        defaultValue={brand.slug}
                                        className="rounded-lg border bg-transparent p-2 text-sm"
                                    />
                                    <input
                                        type="file"
                                        name="logo"
                                        accept="image/*"
                                        className="rounded-lg border p-2 text-xs"
                                    />
                                    <input
                                        type="hidden"
                                        name="is_featured"
                                        value="0"
                                    />
                                    <label className="flex items-center gap-2 text-xs">
                                        <input
                                            type="checkbox"
                                            name="is_featured"
                                            value="1"
                                            defaultChecked={brand.is_featured}
                                        />{' '}
                                        Featured homepage brand
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        name="homepage_order"
                                        defaultValue={
                                            brand.homepage_order ?? ''
                                        }
                                        placeholder="Homepage order"
                                        className="rounded-lg border bg-transparent p-2 text-sm"
                                    />
                                    <input
                                        required
                                        minLength={5}
                                        name="reason"
                                        placeholder="Update reason"
                                        className="rounded-lg border bg-transparent p-2 text-sm"
                                    />
                                    <button className="rounded-lg bg-primary px-3 py-2 text-xs font-bold text-primary-foreground">
                                        Save brand
                                    </button>
                                </Form>
                                <span className="mt-3 inline-block text-xs font-semibold text-primary">
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
