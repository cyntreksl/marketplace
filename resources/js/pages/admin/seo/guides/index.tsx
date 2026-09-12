import { Form, Head, Link } from '@inertiajs/react';
import { BookOpen, Plus } from 'lucide-react';
import { AdminPagination } from '@/components/admin-pagination';
import { PortalLayout } from '@/components/portal-layout';
import { create, destroy, edit, index, restore } from '@/routes/admin/guides';

type GuideRow = {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published';
    published_at: string | null;
    updated_at: string;
    deleted_at: string | null;
    categories: { id: number; name: string }[];
};

export default function AdminGuideIndex({
    guides,
    filters,
    canRestore,
}: {
    guides: {
        data: GuideRow[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        next_page_url: string | null;
        prev_page_url: string | null;
    };
    filters: { search?: string; status?: string };
    canRestore: boolean;
}) {
    return (
        <PortalLayout portal="admin" title="Buying guides">
            <Head title="Buying guides" />
            <main className="mx-auto max-w-7xl">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-bold text-primary">
                            SEO content
                        </p>
                        <h1 className="mt-1 text-3xl font-black">
                            Buying guides
                        </h1>
                    </div>
                    <Link
                        href={create()}
                        className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary px-4 font-bold text-primary-foreground"
                    >
                        <Plus className="size-4" /> Add guide
                    </Link>
                </div>

                <Form
                    {...index.form()}
                    className="mt-6 grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-[1fr_13rem_auto] dark:bg-slate-900"
                >
                    <input
                        name="search"
                        defaultValue={filters.search ?? ''}
                        placeholder="Search guides"
                        className="rounded-xl border bg-transparent p-3"
                    />
                    <select
                        name="status"
                        defaultValue={filters.status ?? ''}
                        className="rounded-xl border bg-transparent p-3"
                    >
                        <option value="">All statuses</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                        <option value="archived">Archived</option>
                    </select>
                    <button className="rounded-xl border px-4 font-bold">
                        Filter
                    </button>
                </Form>

                <div className="mt-6 grid gap-4">
                    {guides.data.map((guide) => (
                        <article
                            key={guide.id}
                            className="flex flex-col gap-4 rounded-2xl border bg-white p-5 sm:flex-row sm:items-center dark:bg-slate-900"
                        >
                            <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-orange-50 text-[#FF6D00]">
                                <BookOpen className="size-5" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <h2 className="font-extrabold">
                                    {guide.title}
                                </h2>
                                <p className="mt-1 truncate text-sm text-muted-foreground">
                                    /guides/{guide.slug}
                                </p>
                                <p className="mt-2 text-xs font-bold tracking-wide uppercase">
                                    {guide.deleted_at
                                        ? 'Archived'
                                        : guide.status}
                                </p>
                            </div>
                            {guide.deleted_at && canRestore ? (
                                <Form {...restore.form(guide.id)}>
                                    <button className="rounded-lg border px-4 py-2 text-sm font-bold">
                                        Restore
                                    </button>
                                </Form>
                            ) : !guide.deleted_at ? (
                                <div className="flex gap-2">
                                    <Link
                                        href={edit(guide.id)}
                                        className="rounded-lg border px-4 py-2 text-sm font-bold"
                                    >
                                        Edit
                                    </Link>
                                    <Form {...destroy.form(guide.id)}>
                                        <button className="rounded-lg border border-red-200 px-4 py-2 text-sm font-bold text-red-700">
                                            Archive
                                        </button>
                                    </Form>
                                </div>
                            ) : (
                                <p className="text-sm font-semibold text-muted-foreground">
                                    A super administrator must restore this
                                    guide.
                                </p>
                            )}
                        </article>
                    ))}
                </div>
                <div className="mt-6">
                    <AdminPagination paginator={guides} />
                </div>
            </main>
        </PortalLayout>
    );
}
