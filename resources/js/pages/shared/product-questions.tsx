import { Form, Head, Link } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/ProductQuestionController';
import { PortalLayout } from '@/components/portal-layout';
import { SellerPageHeader } from '@/components/seller-page-header';
import { SellerPagination } from '@/components/seller-pagination';
import { SellerPortalLayout } from '@/components/seller-portal-layout';
import { show as listingShow } from '@/routes/listings';
import { index } from '@/routes/product-questions';
import type { SellerPaginator } from '@/types';

type Question = {
    id: number;
    question: string;
    answer: string | null;
    listing: { title: string; slug: string };
    asker: { name: string } | null;
};
type Props = {
    questions: SellerPaginator<Question>;
    filters: { q: string; status: string };
    sellerPortal: boolean;
};

function Content({ questions, filters }: Props) {
    return (
        <div className="space-y-6">
            <SellerPageHeader
                eyebrow="Customer support"
                title="Product questions"
                description="Answer shopper questions publicly and keep your listings clear and trustworthy."
            />
            <Form
                {...index.form()}
                className="grid gap-3 rounded-2xl border bg-white p-4 sm:grid-cols-[1fr_12rem_auto] dark:bg-slate-900"
            >
                <input
                    name="q"
                    defaultValue={filters.q}
                    maxLength={100}
                    placeholder="Search question or product"
                    className="min-h-11 rounded-xl border bg-transparent px-3"
                />
                <select
                    name="status"
                    defaultValue={filters.status}
                    className="min-h-11 rounded-xl border bg-transparent px-3"
                >
                    <option value="all">All questions</option>
                    <option value="unanswered">Unanswered</option>
                    <option value="answered">Answered</option>
                </select>
                <button className="min-h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white dark:bg-white dark:text-slate-950">
                    Apply
                </button>
            </Form>
            <section className="overflow-hidden rounded-2xl border bg-white dark:bg-slate-900">
                <div className="hidden md:block">
                    <table className="w-full table-fixed text-left text-sm">
                        <thead className="bg-slate-50 text-xs text-slate-500 uppercase dark:bg-slate-950">
                            <tr>
                                <th className="w-1/4 px-5 py-3">
                                    Product / shopper
                                </th>
                                <th className="w-1/3 px-5 py-3">Question</th>
                                <th className="px-5 py-3">Answer</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {questions.data.map((question) => (
                                <tr key={question.id}>
                                    <td className="px-5 py-4 align-top">
                                        <Link
                                            href={listingShow(
                                                question.listing.slug,
                                            )}
                                            className="font-bold text-orange-700"
                                        >
                                            {question.listing.title}
                                        </Link>
                                        <p className="text-xs text-slate-500">
                                            {question.asker?.name ?? 'Shopper'}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 align-top font-semibold">
                                        {question.question}
                                    </td>
                                    <td className="px-5 py-4 align-top">
                                        <QuestionAnswer question={question} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div className="divide-y md:hidden">
                    {questions.data.map((question) => (
                        <article key={question.id} className="p-4">
                            <Link
                                href={listingShow(question.listing.slug)}
                                className="text-xs font-bold text-orange-700"
                            >
                                {question.listing.title}
                            </Link>
                            <p className="mt-2 font-semibold">
                                {question.question}
                            </p>
                            <p className="text-xs text-slate-500">
                                Asked by {question.asker?.name ?? 'Shopper'}
                            </p>
                            <div className="mt-4">
                                <QuestionAnswer question={question} />
                            </div>
                        </article>
                    ))}
                </div>
                {questions.data.length === 0 && (
                    <p className="p-10 text-center text-sm text-slate-500">
                        No customer questions match these filters.
                    </p>
                )}
                <SellerPagination paginator={questions} />
            </section>
        </div>
    );
}

function QuestionAnswer({ question }: { question: Question }) {
    return question.answer ? (
        <p className="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-950">
            {question.answer}
        </p>
    ) : (
        <Form {...update.form(question.id)} className="grid gap-2">
            <textarea
                required
                minLength={2}
                name="answer"
                placeholder="Write a public answer"
                className="min-h-20 rounded-xl border bg-transparent p-3 text-sm"
            />
            <button className="min-h-10 rounded-xl bg-orange-600 px-4 text-sm font-bold text-white">
                Publish answer
            </button>
        </Form>
    );
}

export default function ProductQuestions(props: Props) {
    return props.sellerPortal ? (
        <SellerPortalLayout title="Customer questions">
            <Head title="Product questions" />
            <Content {...props} />
        </SellerPortalLayout>
    ) : (
        <PortalLayout portal="admin" title="Product questions">
            <Head title="Product questions" />
            <Content {...props} />
        </PortalLayout>
    );
}
