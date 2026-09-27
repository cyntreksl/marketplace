import { Form, Head, Link } from '@inertiajs/react';
import { HelpCircle, Search } from 'lucide-react';
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
                className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[minmax(0,1fr)_12rem_auto] dark:border-slate-800 dark:bg-slate-900"
                options={{ preserveState: true, preserveScroll: true }}
            >
                <label className="relative">
                    <span className="sr-only">Search customer questions</span>
                    <Search className="absolute top-3.5 left-3 size-4 text-slate-400" />
                    <input
                        name="q"
                        defaultValue={filters.q}
                        maxLength={100}
                        placeholder="Search question or product"
                        className="min-h-11 w-full rounded-xl border border-slate-200 bg-transparent pr-3 pl-10 text-sm dark:border-slate-700"
                    />
                </label>
                <select
                    name="status"
                    defaultValue={filters.status}
                    aria-label="Question status"
                    className="min-h-11 rounded-xl border border-slate-200 bg-transparent px-3 text-sm dark:border-slate-700"
                >
                    <option value="all">All questions</option>
                    <option value="unanswered">Unanswered</option>
                    <option value="answered">Answered</option>
                </select>
                <button className="min-h-11 rounded-xl bg-slate-950 px-5 text-sm font-bold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">
                    Apply filters
                </button>
            </Form>
            <section
                className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                aria-label="Customer questions"
            >
                <div className="hidden lg:block">
                    <table className="w-full table-fixed text-left text-sm">
                        <thead className="bg-slate-50 text-xs tracking-wide text-slate-500 uppercase dark:bg-slate-950">
                            <tr>
                                <th className="w-1/4 px-5 py-3">
                                    Product / shopper
                                </th>
                                <th className="w-1/3 px-5 py-3">Question</th>
                                <th className="px-5 py-3">Answer</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
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
                <div className="divide-y divide-slate-100 lg:hidden dark:divide-slate-800">
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
                    <div className="grid place-items-center px-6 py-14 text-center">
                        <span className="grid size-12 place-items-center rounded-2xl bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-300">
                            <HelpCircle className="size-6" aria-hidden />
                        </span>
                        <p className="mt-4 font-bold text-slate-800 dark:text-slate-100">
                            No customer questions found
                        </p>
                        <p className="mt-1 max-w-sm text-sm leading-6 text-slate-500 dark:text-slate-400">
                            New shopper questions will appear here. Try changing
                            the search or status filter.
                        </p>
                    </div>
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
