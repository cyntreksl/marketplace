import { Form, Head, Link } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/ProductQuestionController';
import { PortalLayout } from '@/components/portal-layout';

type Question = {
    id: number;
    question: string;
    answer: string | null;
    listing: { title: string; slug: string };
    asker: { name: string } | null;
};

export default function ProductQuestions({
    questions,
}: {
    questions: { data: Question[] };
}) {
    return (
        <PortalLayout portal="seller" title="Product questions">
            <Head title="Product questions" />
            <main className="mx-auto max-w-5xl">
                <p className="text-sm font-bold text-primary">
                    Customer support
                </p>
                <h1 className="mt-1 text-3xl font-black">Product questions</h1>
                <div className="mt-6 grid gap-4">
                    {questions.data.map((question) => (
                        <article
                            key={question.id}
                            className="rounded-2xl border bg-white p-5 dark:bg-slate-900"
                        >
                            <Link
                                href={`/listings/${question.listing.slug}`}
                                className="text-xs font-bold text-primary"
                            >
                                {question.listing.title}
                            </Link>
                            <p className="mt-3 font-semibold">
                                {question.question}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                Asked by {question.asker?.name ?? 'Shopper'}
                            </p>
                            {question.answer ? (
                                <p className="mt-4 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-950">
                                    {question.answer}
                                </p>
                            ) : (
                                <Form
                                    {...update.form(question.id)}
                                    className="mt-4 flex gap-2"
                                >
                                    <textarea
                                        required
                                        minLength={2}
                                        name="answer"
                                        placeholder="Write a public answer"
                                        className="min-h-20 flex-1 rounded-xl border bg-transparent p-3 text-sm"
                                    />
                                    <button className="self-end rounded-xl bg-primary px-5 py-3 text-sm font-bold text-primary-foreground">
                                        Publish answer
                                    </button>
                                </Form>
                            )}
                        </article>
                    ))}
                    {questions.data.length === 0 && (
                        <p className="rounded-2xl border border-dashed p-10 text-center text-sm text-slate-500">
                            No customer questions yet.
                        </p>
                    )}
                </div>
            </main>
        </PortalLayout>
    );
}
