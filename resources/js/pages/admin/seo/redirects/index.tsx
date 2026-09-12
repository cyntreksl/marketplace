import { Form, Head } from '@inertiajs/react';
import { AdminPagination } from '@/components/admin-pagination';
import { PortalLayout } from '@/components/portal-layout';
import {
    destroy,
    index,
    restore,
    store,
    update,
} from '@/routes/admin/seo-redirects';

type Redirect = {
    id: number;
    source_path: string;
    destination_path: string;
    is_active: boolean;
    hit_count: number;
    last_hit_at: string | null;
    deleted_at: string | null;
};

const inputClass = 'rounded-xl border bg-transparent p-3';

export default function SeoRedirectIndex({
    redirects,
    filters,
    canRestore,
}: {
    redirects: {
        data: Redirect[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: { search?: string; active?: string };
    canRestore: boolean;
}) {
    return (
        <PortalLayout portal="admin" title="SEO redirects">
            <Head title="SEO redirects" />
            <main className="mx-auto grid max-w-7xl gap-8 xl:grid-cols-[22rem_1fr]">
                <section className="h-max rounded-2xl border bg-white p-5 dark:bg-slate-900">
                    <p className="text-sm font-bold text-primary">SEO</p>
                    <h1 className="mt-1 text-2xl font-black">
                        Add legacy redirect
                    </h1>
                    <p className="mt-2 text-sm leading-6 text-muted-foreground">
                        Use internal paths only. Existing chains are collapsed
                        to their final destination automatically.
                    </p>
                    <Form {...store.form()} className="mt-5 grid gap-3">
                        {({ errors, processing }) => (
                            <>
                                <input
                                    required
                                    name="source_path"
                                    placeholder="/old-path"
                                    className={inputClass}
                                />
                                <input
                                    required
                                    name="destination_path"
                                    placeholder="/new-path"
                                    className={inputClass}
                                />
                                <input
                                    type="hidden"
                                    name="is_active"
                                    value="1"
                                />
                                {(errors.source_path ||
                                    errors.destination_path) && (
                                    <p className="text-sm text-red-700">
                                        {errors.source_path ??
                                            errors.destination_path}
                                    </p>
                                )}
                                <button
                                    disabled={processing}
                                    className="rounded-xl bg-primary px-4 py-3 font-bold text-primary-foreground"
                                >
                                    Create redirect
                                </button>
                            </>
                        )}
                    </Form>
                </section>

                <section>
                    <Form
                        {...index.form()}
                        className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-[1fr_12rem_auto] dark:bg-slate-900"
                    >
                        <input
                            name="search"
                            defaultValue={filters.search ?? ''}
                            placeholder="Search paths"
                            className={inputClass}
                        />
                        <select
                            name="active"
                            defaultValue={filters.active ?? ''}
                            className={inputClass}
                        >
                            <option value="">All redirects</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                        <button className="rounded-xl border px-4 font-bold">
                            Filter
                        </button>
                    </Form>

                    <div className="mt-5 grid gap-4">
                        {redirects.data.map((redirect) => (
                            <article
                                key={redirect.id}
                                className="rounded-2xl border bg-white p-5 dark:bg-slate-900"
                            >
                                {!redirect.deleted_at ? (
                                    <>
                                        <Form
                                            {...update.form(redirect.id)}
                                            className="grid gap-3 md:grid-cols-[1fr_1fr_auto]"
                                        >
                                            <input
                                                required
                                                name="source_path"
                                                defaultValue={
                                                    redirect.source_path
                                                }
                                                className={inputClass}
                                            />
                                            <input
                                                required
                                                name="destination_path"
                                                defaultValue={
                                                    redirect.destination_path
                                                }
                                                className={inputClass}
                                            />
                                            <label className="flex items-center gap-2 text-sm font-bold">
                                                <input
                                                    type="hidden"
                                                    name="is_active"
                                                    value="0"
                                                />
                                                <input
                                                    type="checkbox"
                                                    name="is_active"
                                                    value="1"
                                                    defaultChecked={
                                                        redirect.is_active
                                                    }
                                                />
                                                Active
                                            </label>
                                            <p className="text-xs text-muted-foreground md:col-span-2">
                                                {redirect.hit_count} hits · Last
                                                hit{' '}
                                                {redirect.last_hit_at ??
                                                    'never'}
                                            </p>
                                            <button className="rounded-xl border px-4 py-2 text-sm font-bold">
                                                Save
                                            </button>
                                        </Form>
                                        <Form
                                            {...destroy.form(redirect.id)}
                                            className="mt-2"
                                        >
                                            <button className="text-xs font-bold text-red-700">
                                                Archive redirect
                                            </button>
                                        </Form>
                                    </>
                                ) : canRestore ? (
                                    <Form {...restore.form(redirect.id)}>
                                        <p className="font-bold">
                                            {redirect.source_path} →{' '}
                                            {redirect.destination_path}
                                        </p>
                                        <button className="mt-3 rounded-xl border px-4 py-2 text-sm font-bold">
                                            Restore redirect
                                        </button>
                                    </Form>
                                ) : (
                                    <p className="text-sm font-semibold text-muted-foreground">
                                        A super administrator must restore this
                                        redirect.
                                    </p>
                                )}
                            </article>
                        ))}
                    </div>
                    <div className="mt-6">
                        <AdminPagination paginator={redirects} />
                    </div>
                </section>
            </main>
        </PortalLayout>
    );
}
