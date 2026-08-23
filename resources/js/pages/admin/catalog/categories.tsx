import { Form, Head } from '@inertiajs/react';
import { PortalLayout } from '@/components/portal-layout';
import { store } from '@/routes/admin/categories';

type Category = {
    id: number;
    name: string;
    slug: string;
    google_product_category_id: number | null;
    is_active: boolean;
    deleted_at: string | null;
    parent: { name: string } | null;
};

export default function Categories({
    categories,
    parents,
}: {
    categories: { data: Category[] };
    parents: { id: number; name: string }[];
}) {
    return (
        <PortalLayout portal="admin" title="Catalog categories">
            <Head title="Categories" />
            <main className="mx-auto grid max-w-7xl gap-8 xl:grid-cols-[22rem_1fr]">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-sm font-semibold text-primary">
                        Catalog
                    </p>
                    <h1 className="mt-1 text-2xl font-bold">Add category</h1>
                    <Form {...store.form()} className="mt-5 grid gap-3">
                        {({ errors, processing }) => (
                            <>
                                <input
                                    required
                                    name="name"
                                    placeholder="Category name"
                                    className="rounded-xl border p-3"
                                />
                                <input
                                    name="slug"
                                    placeholder="URL slug (optional)"
                                    className="rounded-xl border p-3"
                                />
                                <select
                                    name="parent_id"
                                    className="rounded-xl border p-3"
                                >
                                    <option value="">No parent</option>
                                    {parents.map((parent) => (
                                        <option
                                            key={parent.id}
                                            value={parent.id}
                                        >
                                            {parent.name}
                                        </option>
                                    ))}
                                </select>
                                <input
                                    name="google_product_category_id"
                                    type="number"
                                    min="1"
                                    placeholder="Google category ID (optional)"
                                    className="rounded-xl border p-3"
                                />
                                <input
                                    required
                                    name="commission_percentage"
                                    type="number"
                                    step="0.01"
                                    defaultValue="8"
                                    className="rounded-xl border p-3"
                                />
                                <input
                                    required
                                    name="return_window_days"
                                    type="number"
                                    defaultValue="7"
                                    className="rounded-xl border p-3"
                                />
                                <label className="flex gap-2 text-sm">
                                    <input
                                        name="cod_enabled"
                                        type="checkbox"
                                        value="1"
                                        defaultChecked
                                    />{' '}
                                    COD enabled
                                </label>
                                <label className="flex gap-2 text-sm">
                                    <input
                                        name="is_active"
                                        type="checkbox"
                                        value="1"
                                        defaultChecked
                                    />{' '}
                                    Visible to sellers
                                </label>
                                <textarea
                                    required
                                    name="reason"
                                    placeholder="Audit reason"
                                    className="min-h-20 rounded-xl border p-3"
                                />
                                {errors.reason && (
                                    <p className="text-sm text-red-600">
                                        {errors.reason}
                                    </p>
                                )}
                                <button
                                    disabled={processing}
                                    className="rounded-xl bg-primary px-4 py-3 font-semibold text-primary-foreground"
                                >
                                    Create category
                                </button>
                            </>
                        )}
                    </Form>
                </section>
                <section>
                    <p className="text-sm font-semibold text-primary">
                        {categories.data.length} categories
                    </p>
                    <h2 className="mt-1 text-3xl font-bold">Category tree</h2>
                    <div className="mt-6 grid gap-3">
                        {categories.data.map((category) => (
                            <article
                                key={category.id}
                                className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 className="font-semibold">
                                            {category.name}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {category.parent?.name ??
                                                'Top level'}{' '}
                                            · Google ID:{' '}
                                            {category.google_product_category_id ??
                                                'Not mapped'}
                                        </p>
                                    </div>
                                    <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold dark:bg-slate-800">
                                        {category.deleted_at
                                            ? 'Archived'
                                            : category.is_active
                                              ? 'Active'
                                              : 'Hidden'}
                                    </span>
                                </div>
                            </article>
                        ))}
                        {categories.data.length === 0 && (
                            <p className="rounded-2xl border border-dashed p-10 text-center text-muted-foreground">
                                No categories yet.
                            </p>
                        )}
                    </div>
                </section>
            </main>
        </PortalLayout>
    );
}
