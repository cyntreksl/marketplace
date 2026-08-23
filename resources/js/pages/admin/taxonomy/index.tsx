import { Form, Head } from '@inertiajs/react';
import { PortalLayout } from '@/components/portal-layout';
import { activate, store } from '@/routes/admin/taxonomy';

type Version = {
    id: number;
    version: string;
    locale: string;
    source_filename: string;
    node_count: number;
    is_active: boolean;
    created_at: string;
    importer: { name: string } | null;
};
export default function Taxonomy({
    versions,
}: {
    versions: { data: Version[] };
}) {
    return (
        <PortalLayout portal="admin" title="Google taxonomy">
            <Head title="Google taxonomy" />
            <main className="mx-auto grid max-w-7xl gap-8 xl:grid-cols-[24rem_1fr]">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-sm font-semibold text-primary">
                        Super admin
                    </p>
                    <h1 className="mt-1 text-2xl font-bold">Import taxonomy</h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Upload Google’s official UTF-8 text file. Nodes are
                        imported, never manually edited.
                    </p>
                    <Form
                        {...store.form()}
                        className="mt-5 grid gap-3"
                        encType="multipart/form-data"
                    >
                        {({ errors, processing, progress }) => (
                            <>
                                <input
                                    required
                                    name="version"
                                    placeholder="Taxonomy version"
                                    className="rounded-xl border p-3"
                                />
                                <input type="hidden" name="locale" value="en" />
                                <input
                                    required
                                    name="taxonomy_file"
                                    type="file"
                                    accept=".txt,text/plain"
                                    className="rounded-xl border p-3"
                                />
                                {progress && (
                                    <progress
                                        value={progress.percentage}
                                        max="100"
                                    />
                                )}
                                {errors.taxonomy_file && (
                                    <p className="text-sm text-red-600">
                                        {errors.taxonomy_file}
                                    </p>
                                )}
                                <button
                                    disabled={processing}
                                    className="rounded-xl bg-primary px-4 py-3 font-semibold text-primary-foreground"
                                >
                                    Import for review
                                </button>
                            </>
                        )}
                    </Form>
                </section>
                <section>
                    <p className="text-sm font-semibold text-primary">
                        Versioned imports
                    </p>
                    <h2 className="mt-1 text-3xl font-bold">
                        Taxonomy history
                    </h2>
                    <div className="mt-6 grid gap-3">
                        {versions.data.map((version) => (
                            <article
                                key={version.id}
                                className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 className="font-semibold">
                                            {version.version}{' '}
                                            {version.is_active && (
                                                <span className="ml-2 text-sm text-primary">
                                                    Active
                                                </span>
                                            )}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {version.node_count.toLocaleString()}{' '}
                                            nodes · {version.source_filename} ·{' '}
                                            {version.importer?.name ?? 'System'}
                                        </p>
                                    </div>
                                    {!version.is_active && (
                                        <Form
                                            {...activate.form(version.id)}
                                            className="flex gap-2"
                                        >
                                            {({ processing }) => (
                                                <>
                                                    <input
                                                        required
                                                        name="reason"
                                                        placeholder="Activation reason"
                                                        className="rounded-xl border p-2 text-sm"
                                                    />
                                                    <button
                                                        disabled={processing}
                                                        className="rounded-xl border border-primary px-3 py-2 text-sm font-semibold text-primary"
                                                    >
                                                        Activate
                                                    </button>
                                                </>
                                            )}
                                        </Form>
                                    )}
                                </div>
                            </article>
                        ))}
                        {versions.data.length === 0 && (
                            <p className="rounded-2xl border border-dashed p-10 text-center text-muted-foreground">
                                No taxonomy version has been imported.
                            </p>
                        )}
                    </div>
                </section>
            </main>
        </PortalLayout>
    );
}
