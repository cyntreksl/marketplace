import { Form, Head, Link } from '@inertiajs/react';
import { MessageSquareText, Star } from 'lucide-react';
import { BuyerPageHeader } from '@/components/buyer-page-header';
import { BuyerPagination } from '@/components/buyer-pagination';
import { BuyerPortalLayout } from '@/components/buyer-portal-layout';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/buyer/feedback';
import { store as storeReview } from '@/routes/buyer/reviews';
import type { FeedbackItem, Paginated } from '@/types';

export default function BuyerFeedback({
    feedback,
    view,
    pending_count,
}: {
    feedback: Paginated<FeedbackItem>;
    view: 'awaiting' | 'submitted';
    pending_count: number;
}) {
    return (
        <BuyerPortalLayout title="Feedback">
            <Head title="Feedback" />
            <div className="space-y-7">
                <BuyerPageHeader
                    eyebrow="Your voice"
                    title="Product feedback"
                    description="Review delivered products once. Submitted feedback remains visible as a read-only purchase record."
                />
                <nav className="flex gap-2" aria-label="Feedback views">
                    <Link
                        href={index({ query: { view: 'awaiting' } })}
                        className={`rounded-full border px-4 py-2 text-sm font-semibold ${view === 'awaiting' ? 'border-orange-600 bg-orange-600 text-white' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'}`}
                    >
                        Awaiting feedback ({pending_count})
                    </Link>
                    <Link
                        href={index({ query: { view: 'submitted' } })}
                        className={`rounded-full border px-4 py-2 text-sm font-semibold ${view === 'submitted' ? 'border-orange-600 bg-orange-600 text-white' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900'}`}
                    >
                        Submitted
                    </Link>
                </nav>
                {feedback.data.length === 0 ? (
                    <div className="grid min-h-72 place-items-center rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
                        <div>
                            <MessageSquareText className="mx-auto size-10 text-orange-600" />
                            <h2 className="mt-4 font-black">
                                Nothing here yet
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Delivered products waiting for feedback will
                                appear here.
                            </p>
                        </div>
                    </div>
                ) : (
                    <div className="grid gap-4">
                        {feedback.data.map((item) => (
                            <article
                                key={item.id}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div className="flex gap-4">
                                    {item.image_url ? (
                                        <img
                                            src={item.image_url}
                                            alt=""
                                            className="size-20 rounded-xl object-cover"
                                        />
                                    ) : (
                                        <span className="grid size-20 shrink-0 place-items-center rounded-xl bg-slate-100 dark:bg-slate-800">
                                            <MessageSquareText className="size-6 text-slate-400" />
                                        </span>
                                    )}
                                    <div>
                                        <h2 className="font-black">
                                            {item.title}
                                        </h2>
                                        <p className="mt-1 text-sm text-slate-500">
                                            {item.store_name} ·{' '}
                                            {item.order_number}
                                        </p>
                                        {item.variant_options && (
                                            <p className="mt-1 text-xs text-slate-500">
                                                {Object.entries(
                                                    item.variant_options,
                                                )
                                                    .map(
                                                        ([key, value]) =>
                                                            `${key}: ${value}`,
                                                    )
                                                    .join(' · ')}
                                            </p>
                                        )}
                                        {view === 'submitted' && (
                                            <div className="mt-3">
                                                <p className="flex items-center gap-1 text-sm font-bold text-amber-700">
                                                    <Star className="size-4 fill-current" />{' '}
                                                    {item.rating}/5
                                                </p>
                                                {item.comment && (
                                                    <p className="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                                        {item.comment}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                                {view === 'awaiting' && (
                                    <Form
                                        {...storeReview.form(item.id)}
                                        resetOnSuccess
                                        options={{ preserveScroll: true }}
                                        className="mt-5 grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-[9rem_1fr_auto] dark:bg-slate-950"
                                    >
                                        {({ errors, processing }) => (
                                            <>
                                                <label className="grid gap-1 text-sm font-bold">
                                                    Rating
                                                    <select
                                                        name="rating"
                                                        required
                                                        defaultValue=""
                                                        className="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                                                    >
                                                        <option
                                                            value=""
                                                            disabled
                                                        >
                                                            Choose
                                                        </option>
                                                        {[5, 4, 3, 2, 1].map(
                                                            (rating) => (
                                                                <option
                                                                    key={rating}
                                                                    value={
                                                                        rating
                                                                    }
                                                                >
                                                                    {rating}{' '}
                                                                    stars
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </label>
                                                <label className="grid gap-1 text-sm font-bold">
                                                    Comment{' '}
                                                    <span className="sr-only">
                                                        optional
                                                    </span>
                                                    <input
                                                        name="comment"
                                                        maxLength={1000}
                                                        placeholder="What should other buyers know?"
                                                        className="rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                                                    />
                                                </label>
                                                <Button
                                                    disabled={processing}
                                                    className="self-end rounded-lg bg-orange-600 text-white hover:bg-orange-700"
                                                >
                                                    {processing
                                                        ? 'Submitting…'
                                                        : 'Submit'}
                                                </Button>
                                                {Object.values(errors).map(
                                                    (error) => (
                                                        <p
                                                            key={error}
                                                            className="text-xs text-red-600 sm:col-span-3"
                                                        >
                                                            {error}
                                                        </p>
                                                    ),
                                                )}
                                            </>
                                        )}
                                    </Form>
                                )}
                            </article>
                        ))}
                    </div>
                )}
                <BuyerPagination links={feedback.links} />
            </div>
        </BuyerPortalLayout>
    );
}
