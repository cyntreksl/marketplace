import { Form, Link, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    MessageCircle,
    Star,
    Truck,
    RotateCcw,
    ShieldCheck,
    CreditCard,
} from 'lucide-react';
import type { ReactNode, CSSProperties } from 'react';
import { useEffect, useState } from 'react';
import { store as askQuestion } from '@/actions/App/Http/Controllers/ProductQuestionController';
import { RichTextContent } from '@/components/rich-text-editor';
import { useStorefrontHeaderHeight } from '@/hooks/use-storefront-header-height';
import { login } from '@/routes';
import { shipping, returns } from '@/routes/policies';
import type { StorefrontListing, StorefrontReview } from '@/types';

export type ProductQuestion = {
    id: number;
    question: string;
    answer: string | null;
    askedBy: string;
    answeredBy: string | null;
    answeredAt: string | null;
};
export type ProductPolicies = {
    returnWindowDays: number;
    codEnabled: boolean;
} | null;
const sectionIds = ['overview', 'specs', 'reviews', 'qa', 'shipping'];

export function ProductDetails({
    listing,
    reviews,
    questions,
    pendingQuestions,
    categoryPolicies,
}: {
    listing: StorefrontListing;
    reviews: StorefrontReview[];
    questions: ProductQuestion[];
    pendingQuestions: ProductQuestion[];
    categoryPolicies: ProductPolicies;
}) {
    const { auth } = usePage().props;
    const headerHeight = useStorefrontHeaderHeight();
    const [active, setActive] = useState('overview');
    const [expanded, setExpanded] = useState<Record<string, boolean>>({
        overview: true,
    });
    useEffect(() => {
        let frame = 0;
        const update = () => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                let current = sectionIds[0];

                for (const id of sectionIds) {
                    if (
                        (document.getElementById(id)?.getBoundingClientRect()
                            .top ?? Infinity) <=
                        headerHeight + 140
                    ) {
                        current = id;
                    }
                }

                setActive(current);
            });
        };
        const openHash = () => {
            const id = window.location.hash.slice(1);

            if (!sectionIds.includes(id)) {
                return;
            }

            setExpanded((current) => ({ ...current, [id]: true }));
            requestAnimationFrame(() => {
                const section = document.getElementById(id);
                section?.scrollIntoView({
                    behavior: 'instant',
                    block: 'start',
                });
                section?.focus({ preventScroll: true });
            });
        };
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('hashchange', openHash);
        openHash();

        return () => {
            window.removeEventListener('scroll', update);
            window.removeEventListener('hashchange', openHash);
            cancelAnimationFrame(frame);
        };
    }, [headerHeight]);
    const sections = [
        ['overview', 'Overview'],
        ['specs', 'Specifications'],
        ['reviews', `Reviews (${listing.reviewCount})`],
        ['qa', `Q&A (${questions.length})`],
        ['shipping', 'Shipping & Returns'],
    ];
    const specificationRows = [
        ['Brand', listing.brand?.name],
        ['Model', listing.model],
        ['Category', listing.category?.name],
        ['Condition', listing.condition],
        ...Object.entries(listing.specifications),
    ].filter(
        ([, value]) => value !== null && value !== undefined && value !== '',
    );
    const panels: Record<string, ReactNode> = {
        overview: (
            <div
                className={`grid items-start gap-8 ${listing.media.length > 1 ? 'lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]' : ''}`}
            >
                <div className="max-w-[75ch] min-w-0">
                    {listing.description ? (
                        <RichTextContent
                            value={listing.description}
                            className={`product-description text-base text-slate-600 ${!/<[a-z][^>]*>/i.test(listing.description) ? 'whitespace-pre-line' : 'whitespace-normal'}`}
                        />
                    ) : (
                        <p className="text-base text-slate-500">
                            The seller has not added a detailed description yet.
                        </p>
                    )}
                </div>
                {listing.media.length > 1 && (
                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-1">
                        {listing.media.slice(1, 3).map((media, index) => (
                            <img
                                key={media.path}
                                src={media.cardUrl}
                                alt={`${listing.title}, detail ${index + 1}`}
                                width={640}
                                height={640}
                                loading="lazy"
                                className="aspect-square w-full rounded-2xl border border-slate-100 bg-white object-contain p-4"
                            />
                        ))}
                    </div>
                )}
            </div>
        ),
        specs: (
            <dl className="grid gap-x-8 md:grid-cols-2">
                {specificationRows.map(([name, value]) => (
                    <div
                        key={name}
                        className="grid min-w-0 grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-4 border-b border-slate-100 py-4 text-base"
                    >
                        <dt className="font-semibold text-slate-800">{name}</dt>
                        <dd className="min-w-0 break-words text-slate-600">
                            {name === 'Details' ? (
                                <RichTextContent
                                    value={String(value)}
                                    className="product-description"
                                />
                            ) : (
                                String(value)
                            )}
                        </dd>
                    </div>
                ))}
            </dl>
        ),
        reviews: (
            <div className="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
                <div className="self-start rounded-2xl bg-orange-50 p-5">
                    <Star className="size-6 text-orange-500" />
                    <p className="mt-3 text-3xl font-black">
                        {listing.reviewCount
                            ? listing.ratingAverage?.toFixed(1)
                            : 'New'}
                    </p>
                    <p className="mt-1 text-sm text-slate-600">
                        {listing.reviewCount
                            ? `Based on ${listing.reviewCount} product reviews`
                            : 'No product reviews yet'}
                    </p>
                </div>
                <div className="grid content-start gap-4">
                    {reviews.length ? (
                        reviews.map((review) => (
                            <article
                                key={review.id}
                                className="border-b border-slate-100 pb-4"
                            >
                                <div className="flex flex-wrap justify-between gap-2 text-sm">
                                    <strong>{review.buyerName}</strong>
                                    <span className="inline-flex items-center gap-1 font-semibold text-orange-700">
                                        <Star className="size-4" />
                                        {review.rating}/5
                                    </span>
                                </div>
                                <p className="mt-3 text-base leading-7 text-slate-600">
                                    {review.comment}
                                </p>
                            </article>
                        ))
                    ) : (
                        <div className="py-3">
                            <h3 className="text-lg font-bold">
                                Be the first to share your experience
                            </h3>
                            <p className="mt-2 text-base leading-7 text-slate-600">
                                Purchased this product? You can leave a review
                                from your orders after delivery.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        ),
        qa: (
            <div className="max-w-3xl">
                <div className="grid gap-4">
                    {[...pendingQuestions, ...questions].map((question) => (
                        <article
                            key={question.id}
                            className="rounded-2xl border border-slate-200 p-4"
                        >
                            <p className="text-base font-semibold">
                                {question.question}
                            </p>
                            <p
                                className={`mt-2 text-base leading-7 ${question.answer ? 'text-slate-600' : 'text-amber-700'}`}
                            >
                                {question.answer ??
                                    'Awaiting seller response — visible only to you.'}
                            </p>
                        </article>
                    ))}
                    {questions.length === 0 &&
                        pendingQuestions.length === 0 && (
                            <div className="flex gap-3">
                                <MessageCircle className="size-6 shrink-0 text-orange-500" />
                                <div>
                                    <h3 className="text-lg font-bold">
                                        A little more clarity before you buy
                                    </h3>
                                    <p className="mt-2 text-base leading-7 text-slate-600">
                                        Ask the seller about this product.
                                        Answered questions help other shoppers
                                        too.
                                    </p>
                                </div>
                            </div>
                        )}
                </div>
                {auth.user ? (
                    <Form
                        {...askQuestion.form(listing.slug)}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="mt-5"
                    >
                        {({ processing, errors, recentlySuccessful }) => (
                            <>
                                <label
                                    htmlFor="product-question"
                                    className="text-sm font-semibold"
                                >
                                    Your question
                                </label>
                                <div className="mt-2 flex flex-col gap-3 sm:flex-row">
                                    <textarea
                                        id="product-question"
                                        required
                                        minLength={10}
                                        maxLength={1000}
                                        name="question"
                                        placeholder="What would you like to know?"
                                        rows={2}
                                        className="min-w-0 flex-1 rounded-xl border border-slate-200 p-3 text-base"
                                    />
                                    <button
                                        disabled={processing}
                                        className="min-h-12 self-start rounded-xl bg-slate-950 px-5 py-3 text-sm font-bold text-white disabled:opacity-50"
                                    >
                                        {processing ? 'Sending…' : 'Ask seller'}
                                    </button>
                                </div>
                                {Object.values(errors).map((error) => (
                                    <p
                                        key={error}
                                        role="alert"
                                        className="mt-2 text-sm text-red-600"
                                    >
                                        {error}
                                    </p>
                                ))}
                                {recentlySuccessful && (
                                    <p
                                        role="status"
                                        className="mt-2 text-sm text-emerald-700"
                                    >
                                        Question sent. Your seller will respond
                                        here.
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                ) : (
                    <Link
                        href={login()}
                        className="mt-4 inline-flex min-h-11 items-center text-sm font-bold text-orange-700"
                    >
                        Sign in to ask a question
                    </Link>
                )}
            </div>
        ),
        shipping: (
            <div className="grid gap-4 sm:grid-cols-2">
                <Policy
                    icon={<Truck className="size-5" />}
                    title="Delivery information"
                >
                    <Link
                        href={shipping()}
                        className="underline underline-offset-4"
                    >
                        View our shipping policy
                    </Link>{' '}
                    for delivery areas and order information.
                </Policy>
                <Policy
                    icon={<RotateCcw className="size-5" />}
                    title={
                        categoryPolicies?.returnWindowDays
                            ? `${categoryPolicies.returnWindowDays}-day return window`
                            : 'Returns policy'
                    }
                >
                    <Link
                        href={returns()}
                        className="underline underline-offset-4"
                    >
                        Read the return conditions
                    </Link>{' '}
                    before placing your order.
                </Policy>
                {listing.warranty && (
                    <Policy
                        icon={<ShieldCheck className="size-5" />}
                        title="Warranty"
                    >
                        {listing.warranty}
                    </Policy>
                )}
                <Policy
                    icon={<CreditCard className="size-5" />}
                    title={
                        categoryPolicies?.codEnabled
                            ? 'Cash on Delivery'
                            : 'Payment options'
                    }
                >
                    {categoryPolicies?.codEnabled
                        ? 'Available for eligible orders. Confirm your payment method at checkout.'
                        : 'Available payment methods are shown at checkout.'}
                </Policy>
            </div>
        ),
    };

    return (
        <div
            className="mt-8"
            style={
                { '--detail-offset': `${headerHeight + 84}px` } as CSSProperties
            }
        >
            <nav
                aria-label="Product information"
                style={{ top: headerHeight }}
                className="sticky z-30 -mx-1 overflow-x-auto rounded-2xl border border-white bg-white/95 p-1 shadow-sm ring-1 ring-slate-200/80 backdrop-blur-xl"
            >
                <div className="flex min-w-max gap-1">
                    {sections.map(([id, title]) => (
                        <a
                            key={id}
                            href={`#${id}`}
                            aria-current={
                                active === id ? 'location' : undefined
                            }
                            onClick={() =>
                                setExpanded((current) => ({
                                    ...current,
                                    [id]: true,
                                }))
                            }
                            className={`flex min-h-12 items-center rounded-xl px-4 text-sm font-semibold transition ${active === id ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-600 hover:bg-orange-50 hover:text-orange-700'}`}
                        >
                            {title}
                        </a>
                    ))}
                </div>
            </nav>
            <div className="mt-5 grid gap-4">
                {sections.map(([id, title]) => (
                    <section
                        key={id}
                        id={id}
                        tabIndex={-1}
                        className="scroll-mt-(--detail-offset) rounded-2xl border border-slate-200/80 bg-white p-5 focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:outline-none sm:p-7"
                    >
                        <h2 className="hidden text-xl font-bold tracking-tight md:block">
                            {title}
                        </h2>
                        <h2 className="md:hidden">
                            <button
                                type="button"
                                aria-expanded={Boolean(expanded[id])}
                                aria-controls={`${id}-content`}
                                onClick={() =>
                                    setExpanded((current) => ({
                                        ...current,
                                        [id]: !current[id],
                                    }))
                                }
                                className="flex min-h-11 w-full items-center justify-between gap-3 text-left text-lg font-bold"
                            >
                                {title}
                                <ChevronDown
                                    className={`size-5 shrink-0 transition ${expanded[id] ? 'rotate-180' : ''}`}
                                />
                            </button>
                        </h2>
                        <div
                            id={`${id}-content`}
                            className={`${expanded[id] ? 'block' : 'hidden'} pt-5 md:block`}
                        >
                            {panels[id]}
                        </div>
                    </section>
                ))}
            </div>
        </div>
    );
}
function Policy({
    icon,
    title,
    children,
}: {
    icon: ReactNode;
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="flex gap-3 rounded-2xl bg-slate-50 p-5">
            <span className="text-orange-600">{icon}</span>
            <div>
                <h3 className="text-base font-bold">{title}</h3>
                <p className="mt-2 text-sm leading-6 text-slate-600">
                    {children}
                </p>
            </div>
        </div>
    );
}
