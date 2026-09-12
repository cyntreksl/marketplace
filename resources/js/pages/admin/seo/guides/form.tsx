import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { PortalLayout } from '@/components/portal-layout';
import { index, store, update } from '@/routes/admin/guides';

type Section = { heading: string; paragraphs: string[] };
type Guide = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    quickAnswer: string;
    sections: Section[];
    buyingChecklist: string[];
    heroImageUrl: string | null;
    heroImageAlt: string | null;
    seoTitle: string | null;
    seoDescription: string | null;
    primaryQuery: string | null;
    supportingQueries: string[];
    trendsResearchedAt: string | null;
    status: 'draft' | 'published';
    publishedAt: string | null;
    categories: { id: number; name: string; slug: string }[];
};

const inputClass = 'rounded-xl border bg-transparent p-3';

export default function GuideForm({
    guide,
    categories,
}: {
    guide: Guide | null;
    categories: { id: number; name: string; slug: string }[];
}) {
    const [sections, setSections] = useState<Section[]>(
        guide?.sections.length
            ? guide.sections
            : [{ heading: '', paragraphs: [''] }],
    );
    const [checklist, setChecklist] = useState<string[]>(
        guide?.buyingChecklist.length ? guide.buyingChecklist : [''],
    );
    const [queries, setQueries] = useState<string[]>(
        guide?.supportingQueries.length ? guide.supportingQueries : [''],
    );
    const selectedCategories = new Set(
        guide?.categories.map((category) => category.id) ?? [],
    );
    const submission = guide ? update.form(guide.id) : store.form();

    return (
        <PortalLayout
            portal="admin"
            title={guide ? 'Edit buying guide' : 'Add buying guide'}
        >
            <Head title={guide ? `Edit ${guide.title}` : 'Add buying guide'} />
            <main className="mx-auto max-w-5xl">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="text-sm font-bold text-primary">
                            SEO content
                        </p>
                        <h1 className="mt-1 text-3xl font-black">
                            {guide ? 'Edit buying guide' : 'Add buying guide'}
                        </h1>
                    </div>
                    <Link href={index()} className="font-bold text-primary">
                        Back to guides
                    </Link>
                </div>

                <Form
                    {...submission}
                    className="mt-6 grid gap-6"
                    options={{ preserveScroll: true }}
                >
                    {({ errors, processing }) => (
                        <>
                            <section className="grid gap-4 rounded-2xl border bg-white p-5 dark:bg-slate-900">
                                <h2 className="text-xl font-black">
                                    Guide details
                                </h2>
                                <input
                                    required
                                    name="title"
                                    defaultValue={guide?.title ?? ''}
                                    placeholder="Title"
                                    className={inputClass}
                                />
                                <input
                                    required
                                    name="slug"
                                    defaultValue={guide?.slug ?? ''}
                                    placeholder="guide-url-slug"
                                    className={inputClass}
                                />
                                <textarea
                                    required
                                    name="excerpt"
                                    defaultValue={guide?.excerpt ?? ''}
                                    placeholder="Concise card and opening summary"
                                    className={`${inputClass} min-h-24`}
                                />
                                <textarea
                                    required
                                    name="quick_answer"
                                    defaultValue={guide?.quickAnswer ?? ''}
                                    placeholder="Direct answer to the primary query"
                                    className={`${inputClass} min-h-28`}
                                />
                            </section>

                            <section className="grid gap-4 rounded-2xl border bg-white p-5 dark:bg-slate-900">
                                <div className="flex items-center justify-between">
                                    <h2 className="text-xl font-black">
                                        Ordered content sections
                                    </h2>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSections([
                                                ...sections,
                                                {
                                                    heading: '',
                                                    paragraphs: [''],
                                                },
                                            ])
                                        }
                                        className="inline-flex items-center gap-2 font-bold text-primary"
                                    >
                                        <Plus className="size-4" /> Section
                                    </button>
                                </div>
                                {sections.map((section, sectionIndex) => (
                                    <fieldset
                                        key={sectionIndex}
                                        className="grid gap-3 rounded-xl border p-4"
                                    >
                                        <input
                                            required
                                            name={`sections[${sectionIndex}][heading]`}
                                            value={section.heading}
                                            onChange={(event) => {
                                                const next = [...sections];
                                                next[sectionIndex] = {
                                                    ...section,
                                                    heading: event.target.value,
                                                };
                                                setSections(next);
                                            }}
                                            placeholder="Section heading"
                                            className={inputClass}
                                        />
                                        {section.paragraphs.map(
                                            (paragraph, paragraphIndex) => (
                                                <textarea
                                                    key={paragraphIndex}
                                                    required
                                                    name={`sections[${sectionIndex}][paragraphs][${paragraphIndex}]`}
                                                    value={paragraph}
                                                    onChange={(event) => {
                                                        const next = [
                                                            ...sections,
                                                        ];
                                                        const paragraphs = [
                                                            ...section.paragraphs,
                                                        ];
                                                        paragraphs[
                                                            paragraphIndex
                                                        ] = event.target.value;
                                                        next[sectionIndex] = {
                                                            ...section,
                                                            paragraphs,
                                                        };
                                                        setSections(next);
                                                    }}
                                                    placeholder="Safe text paragraph"
                                                    className={`${inputClass} min-h-28`}
                                                />
                                            ),
                                        )}
                                        <div className="flex gap-3">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    const next = [...sections];
                                                    next[sectionIndex] = {
                                                        ...section,
                                                        paragraphs: [
                                                            ...section.paragraphs,
                                                            '',
                                                        ],
                                                    };
                                                    setSections(next);
                                                }}
                                                className="text-sm font-bold text-primary"
                                            >
                                                Add paragraph
                                            </button>
                                            {sections.length > 1 && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setSections(
                                                            sections.filter(
                                                                (_, index) =>
                                                                    index !==
                                                                    sectionIndex,
                                                            ),
                                                        )
                                                    }
                                                    className="inline-flex items-center gap-1 text-sm font-bold text-red-700"
                                                >
                                                    <Trash2 className="size-4" />
                                                    Remove
                                                </button>
                                            )}
                                        </div>
                                    </fieldset>
                                ))}
                            </section>

                            <section className="grid gap-4 rounded-2xl border bg-white p-5 dark:bg-slate-900">
                                <h2 className="text-xl font-black">
                                    Checklist and category links
                                </h2>
                                {checklist.map((item, itemIndex) => (
                                    <input
                                        key={itemIndex}
                                        required
                                        name={`buying_checklist[${itemIndex}]`}
                                        value={item}
                                        onChange={(event) => {
                                            const next = [...checklist];
                                            next[itemIndex] =
                                                event.target.value;
                                            setChecklist(next);
                                        }}
                                        placeholder="Checklist item"
                                        className={inputClass}
                                    />
                                ))}
                                <button
                                    type="button"
                                    onClick={() =>
                                        setChecklist([...checklist, ''])
                                    }
                                    className="justify-self-start font-bold text-primary"
                                >
                                    Add checklist item
                                </button>
                                <div className="grid max-h-72 gap-2 overflow-y-auto rounded-xl border p-3 sm:grid-cols-2">
                                    {categories.map((category) => (
                                        <label
                                            key={category.id}
                                            className="flex gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                name="category_ids[]"
                                                value={category.id}
                                                defaultChecked={selectedCategories.has(
                                                    category.id,
                                                )}
                                            />
                                            {category.name}
                                        </label>
                                    ))}
                                </div>
                            </section>

                            <section className="grid gap-4 rounded-2xl border bg-white p-5 md:grid-cols-2 dark:bg-slate-900">
                                <h2 className="text-xl font-black md:col-span-2">
                                    Search and Trends research
                                </h2>
                                <input
                                    required
                                    name="seo_title"
                                    defaultValue={guide?.seoTitle ?? ''}
                                    placeholder="SEO title"
                                    className={inputClass}
                                />
                                <input
                                    required
                                    name="primary_query"
                                    defaultValue={guide?.primaryQuery ?? ''}
                                    placeholder="Primary focus query"
                                    className={inputClass}
                                />
                                <textarea
                                    required
                                    name="seo_description"
                                    maxLength={320}
                                    defaultValue={guide?.seoDescription ?? ''}
                                    placeholder="Search result description"
                                    className={`${inputClass} min-h-24 md:col-span-2`}
                                />
                                {queries.map((query, queryIndex) => (
                                    <input
                                        key={queryIndex}
                                        name={`supporting_queries[${queryIndex}]`}
                                        value={query}
                                        onChange={(event) => {
                                            const next = [...queries];
                                            next[queryIndex] =
                                                event.target.value;
                                            setQueries(next);
                                        }}
                                        placeholder="Supporting query"
                                        className={inputClass}
                                    />
                                ))}
                                <button
                                    type="button"
                                    onClick={() => setQueries([...queries, ''])}
                                    className="justify-self-start font-bold text-primary"
                                >
                                    Add supporting query
                                </button>
                                <input
                                    required
                                    type="date"
                                    name="trends_researched_at"
                                    defaultValue={
                                        guide?.trendsResearchedAt ?? ''
                                    }
                                    className={inputClass}
                                />
                            </section>

                            <section className="grid gap-4 rounded-2xl border bg-white p-5 md:grid-cols-2 dark:bg-slate-900">
                                <h2 className="text-xl font-black md:col-span-2">
                                    Hero and publishing
                                </h2>
                                <input
                                    type="file"
                                    name="hero_image"
                                    accept="image/*"
                                    className={inputClass}
                                />
                                <input
                                    name="hero_image_alt"
                                    defaultValue={guide?.heroImageAlt ?? ''}
                                    placeholder="Descriptive hero image alt text"
                                    className={inputClass}
                                />
                                {guide?.heroImageUrl && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="remove_hero_image"
                                            value="1"
                                        />
                                        Remove current hero image
                                    </label>
                                )}
                                <select
                                    name="status"
                                    defaultValue={guide?.status ?? 'draft'}
                                    className={inputClass}
                                >
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                </select>
                                <input
                                    type="datetime-local"
                                    name="published_at"
                                    defaultValue={
                                        guide?.publishedAt?.slice(0, 16) ?? ''
                                    }
                                    className={inputClass}
                                />
                            </section>

                            {Object.keys(errors).length > 0 && (
                                <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                                    {Object.values(errors).join(' ')}
                                </div>
                            )}
                            <button
                                disabled={processing}
                                className="min-h-12 rounded-xl bg-primary px-5 font-extrabold text-primary-foreground disabled:opacity-60"
                            >
                                {processing
                                    ? 'Saving…'
                                    : guide
                                      ? 'Save guide'
                                      : 'Create guide'}
                            </button>
                        </>
                    )}
                </Form>
            </main>
        </PortalLayout>
    );
}
