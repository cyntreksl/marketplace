import { Form, Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    ChevronDown,
    CreditCard,
    MessageCircle,
    RotateCcw,
    ShieldCheck,
    Star,
    Truck,
} from 'lucide-react';
import type {
    CSSProperties,
    KeyboardEvent as ReactKeyboardEvent,
    ReactNode,
} from 'react';
import { useEffect, useRef, useState } from 'react';
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

type SectionId = 'overview' | 'specs' | 'reviews' | 'qa' | 'shipping';
type Section = { id: SectionId; label: string };

const sectionOrder: SectionId[] = [
    'overview',
    'specs',
    'reviews',
    'qa',
    'shipping',
];

export function resolveProductSection(
    hash: string,
    reviewsEnabled: boolean,
): SectionId {
    const requested = hash.replace(/^#/, '') as SectionId;
    const available = reviewsEnabled
        ? sectionOrder
        : sectionOrder.filter((id) => id !== 'reviews');

    return available.includes(requested) ? requested : 'overview';
}

export function ProductDetails({
    listing,
    reviews,
    reviewsEnabled,
    questions,
    pendingQuestions,
    categoryPolicies,
}: {
    listing: StorefrontListing;
    reviews: StorefrontReview[];
    reviewsEnabled: boolean;
    questions: ProductQuestion[];
    pendingQuestions: ProductQuestion[];
    categoryPolicies: ProductPolicies;
}) {
    const { auth } = usePage().props;
    const headerHeight = useStorefrontHeaderHeight();
    const [active, setActive] = useState<SectionId>('overview');
    const [mobileOpen, setMobileOpen] = useState<SectionId | null>('overview');
    const tabRefs = useRef<Partial<Record<SectionId, HTMLButtonElement>>>({});
    const sections = (
        [
            { id: 'overview', label: 'Overview' },
            { id: 'specs', label: 'Specifications' },
            {
                id: 'reviews',
                label:
                    'Reviews (' +
                    listing.reviewCount.toLocaleString('en-LK') +
                    ')',
            },
            {
                id: 'qa',
                label: 'Q&A (' + questions.length.toLocaleString('en-LK') + ')',
            },
            { id: 'shipping', label: 'Shipping & Returns' },
        ] satisfies Section[]
    ).filter((section) => reviewsEnabled || section.id !== 'reviews');
    const activeSectionIds = sections.map((section) => section.id);

    const focusPanel = (id: SectionId) => {
        window.requestAnimationFrame(() => {
            const desktop = window.matchMedia('(min-width: 768px)').matches;
            const target = document.getElementById(
                desktop ? id + '-panel' : id + '-mobile',
            );
            target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            target?.focus({ preventScroll: true });
        });
    };

    const activate = (id: SectionId, updateHistory = true) => {
        setActive(id);
        setMobileOpen(id);

        if (updateHistory) {
            window.history.pushState(window.history.state, '', '#' + id);
        }

        focusPanel(id);
    };

    useEffect(() => {
        const syncFromHistory = () => {
            const hash = resolveProductSection(
                window.location.hash,
                reviewsEnabled,
            );

            setActive(hash);
            setMobileOpen(hash);

            if (window.location.hash) {
                focusPanel(hash);
            }
        };

        window.addEventListener('hashchange', syncFromHistory);
        window.addEventListener('popstate', syncFromHistory);
        syncFromHistory();

        return () => {
            window.removeEventListener('hashchange', syncFromHistory);
            window.removeEventListener('popstate', syncFromHistory);
        };
    }, [reviewsEnabled]);

    const handleTabKeyDown = (
        event: ReactKeyboardEvent<HTMLButtonElement>,
        id: SectionId,
    ) => {
        const currentIndex = activeSectionIds.indexOf(id);
        let nextIndex: number | null = null;

        if (event.key === 'ArrowRight') {
            nextIndex = (currentIndex + 1) % activeSectionIds.length;
        } else if (event.key === 'ArrowLeft') {
            nextIndex =
                (currentIndex - 1 + activeSectionIds.length) %
                activeSectionIds.length;
        } else if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = activeSectionIds.length - 1;
        }

        if (nextIndex === null) {
            return;
        }

        event.preventDefault();
        const nextId = activeSectionIds[nextIndex];
        tabRefs.current[nextId]?.focus();
        activate(nextId);
    };

    const detailEntries = Object.entries(listing.specifications);
    const richDetails = detailEntries.find(
        ([name]) => name.toLowerCase() === 'details',
    )?.[1];
    const specificationRows = [
        ['Category', listing.category?.name],
        ...detailEntries.filter(
            ([name]) =>
                ![
                    'brand',
                    'model',
                    'condition',
                    'warranty',
                    'details',
                ].includes(name.toLowerCase()),
        ),
    ].filter(
        ([, value]) => value !== null && value !== undefined && value !== '',
    );
    const quickFacts = [
        ['Brand', listing.brand?.name],
        ['Model', listing.model],
        [
            'Condition',
            listing.condition.charAt(0).toUpperCase() +
                listing.condition.slice(1),
        ],
        ['Warranty', listing.warranty],
    ].filter(([, value]) => Boolean(value));

    const panels: Record<SectionId, ReactNode> = {
        overview: <Overview listing={listing} />,
        specs: (
            <div className="grid gap-6">
                {quickFacts.length > 0 && (
                    <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {quickFacts.map(([name, value]) => (
                            <div
                                key={name}
                                className="rounded-2xl border border-orange-100 bg-orange-50/60 p-4"
                            >
                                <dt className="text-xs font-bold tracking-wider text-orange-700 uppercase">
                                    {name}
                                </dt>
                                <dd className="mt-2 text-base font-black break-words text-slate-950">
                                    {String(value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
                {specificationRows.length > 0 && (
                    <dl className="grid gap-x-8 md:grid-cols-2">
                        {specificationRows.map(([name, value]) => (
                            <div
                                key={name}
                                className="grid min-w-0 grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-4 border-b border-slate-100 py-4 text-sm sm:text-base"
                            >
                                <dt className="font-semibold text-slate-800">
                                    {name}
                                </dt>
                                <dd className="min-w-0 break-words text-slate-600">
                                    {String(value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}
                {richDetails !== undefined && richDetails !== '' && (
                    <div className="min-w-0 overflow-hidden rounded-2xl border border-slate-200 p-4 sm:p-6">
                        <h3 className="text-base font-black text-slate-950">
                            Technical details
                        </h3>
                        <RichTextContent
                            value={String(richDetails)}
                            className="product-description mt-4 overflow-x-auto"
                        />
                    </div>
                )}
                {quickFacts.length === 0 &&
                    specificationRows.length === 0 &&
                    !richDetails && (
                        <p className="text-base text-slate-500">
                            No additional specifications are available.
                        </p>
                    )}
            </div>
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
                            ? 'Based on ' +
                              listing.reviewCount.toLocaleString('en-LK') +
                              ' product ' +
                              (listing.reviewCount === 1 ? 'review' : 'reviews')
                            : 'No product reviews yet'}
                    </p>
                </div>
                <div className="grid content-start gap-4">
                    {reviews.length > 0 ? (
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
            <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="grid gap-3">
                    {pendingQuestions.map((question) => (
                        <ProductQuestionRow
                            key={'pending-' + question.id}
                            question={question}
                            pending
                        />
                    ))}
                    {questions.map((question) => (
                        <ProductQuestionRow
                            key={question.id}
                            question={question}
                        />
                    ))}
                    {questions.length === 0 &&
                        pendingQuestions.length === 0 && (
                            <div className="flex gap-3 rounded-2xl border border-dashed border-orange-200 bg-orange-50/60 p-5">
                                <MessageCircle className="size-6 shrink-0 text-orange-500" />
                                <div>
                                    <h3 className="text-lg font-bold">
                                        Need a little more clarity?
                                    </h3>
                                    <p className="mt-2 text-sm leading-6 text-slate-600">
                                        Ask about compatibility, what is
                                        included, or anything else you need to
                                        decide confidently.
                                    </p>
                                </div>
                            </div>
                        )}
                </div>
                <aside className="rounded-2xl bg-slate-950 p-5 text-white">
                    <MessageCircle className="size-6 text-orange-400" />
                    <h3 className="mt-3 text-lg font-black">
                        Ask the verified seller
                    </h3>
                    <p className="mt-2 text-sm leading-6 text-slate-300">
                        Get product-specific information before you place your
                        order.
                    </p>
                    {auth.user ? (
                        <Form
                            {...askQuestion.form(listing.slug)}
                            resetOnSuccess
                            options={{ preserveScroll: true }}
                            className="mt-4"
                        >
                            {({ processing, errors, recentlySuccessful }) => (
                                <>
                                    <label
                                        htmlFor="product-question"
                                        className="text-sm font-semibold"
                                    >
                                        Your question
                                    </label>
                                    <textarea
                                        id="product-question"
                                        required
                                        minLength={10}
                                        maxLength={1000}
                                        name="question"
                                        placeholder="What would you like to know?"
                                        rows={4}
                                        className="mt-2 w-full rounded-xl border border-slate-700 bg-slate-900 p-3 text-base text-white placeholder:text-slate-500"
                                    />
                                    <button
                                        disabled={processing}
                                        className="mt-3 min-h-11 w-full rounded-xl bg-orange-500 px-5 py-3 text-sm font-bold text-white disabled:opacity-50"
                                    >
                                        {processing ? 'Sending…' : 'Ask seller'}
                                    </button>
                                    {Object.values(errors).map((error) => (
                                        <p
                                            key={error}
                                            role="alert"
                                            className="mt-2 text-sm text-red-300"
                                        >
                                            {error}
                                        </p>
                                    ))}
                                    {recentlySuccessful && (
                                        <p
                                            role="status"
                                            className="mt-2 text-sm text-emerald-300"
                                        >
                                            Question sent. The answer will
                                            appear here after the seller
                                            responds.
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>
                    ) : (
                        <Link
                            href={login()}
                            className="mt-4 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-orange-500 px-4 text-sm font-bold text-white"
                        >
                            Sign in to ask a question
                        </Link>
                    )}
                </aside>
            </div>
        ),
        shipping: (
            <div className="grid gap-4 sm:grid-cols-2">
                <Policy
                    icon={<Truck className="size-5" />}
                    title="Delivery information"
                >
                    Check eligible delivery areas and order handling details in
                    our{' '}
                    <Link
                        href={shipping()}
                        className="font-semibold underline underline-offset-4"
                    >
                        shipping policy
                    </Link>
                    .
                </Policy>
                <Policy
                    icon={<RotateCcw className="size-5" />}
                    title={
                        categoryPolicies?.returnWindowDays
                            ? categoryPolicies.returnWindowDays +
                              '-day return window'
                            : 'Returns policy'
                    }
                >
                    Eligibility and conditions are explained in our{' '}
                    <Link
                        href={returns()}
                        className="font-semibold underline underline-offset-4"
                    >
                        returns policy
                    </Link>
                    .
                </Policy>
                <Policy
                    icon={<CreditCard className="size-5" />}
                    title={
                        categoryPolicies?.codEnabled
                            ? 'Cash on Delivery'
                            : 'Payment options'
                    }
                >
                    {categoryPolicies?.codEnabled
                        ? 'Available for eligible orders. Confirm the available payment method at checkout.'
                        : 'Available payment methods are shown before you place the order.'}
                </Policy>
                <Policy
                    icon={<ShieldCheck className="size-5" />}
                    title="Warranty"
                >
                    {listing.warranty ??
                        'No separate product warranty is listed. Your applicable return rights still apply.'}
                </Policy>
            </div>
        ),
    };

    return (
        <div
            className="mt-4 sm:mt-8"
            style={
                { '--detail-offset': headerHeight + 84 + 'px' } as CSSProperties
            }
        >
            <div className="hidden md:block">
                <nav
                    aria-label="Product information"
                    style={{ top: headerHeight }}
                    className="sticky z-30 -mx-1 overflow-x-auto rounded-2xl border border-white bg-white/95 p-1 shadow-sm ring-1 ring-slate-200/80 backdrop-blur-xl"
                >
                    <div className="flex min-w-max items-center gap-1">
                        <div
                            role="tablist"
                            aria-label="Product information sections"
                            className="flex gap-1"
                        >
                            {sections.map((section) => (
                                <button
                                    key={section.id}
                                    ref={(element) => {
                                        tabRefs.current[section.id] =
                                            element ?? undefined;
                                    }}
                                    id={'tab-' + section.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={active === section.id}
                                    aria-controls={section.id + '-panel'}
                                    tabIndex={active === section.id ? 0 : -1}
                                    onClick={() => activate(section.id)}
                                    onKeyDown={(event) =>
                                        handleTabKeyDown(event, section.id)
                                    }
                                    className={
                                        'flex min-h-12 items-center rounded-xl px-4 text-sm font-semibold transition ' +
                                        (active === section.id
                                            ? 'bg-slate-950 text-white shadow-sm'
                                            : 'text-slate-600 hover:bg-orange-50 hover:text-orange-700')
                                    }
                                >
                                    {section.label}
                                </button>
                            ))}
                        </div>
                        <a
                            href="#purchase"
                            className="ml-auto inline-flex min-h-12 items-center rounded-xl px-4 text-sm font-bold text-orange-700 hover:bg-orange-50"
                        >
                            Back to purchase ↑
                        </a>
                    </div>
                </nav>
                <div className="mt-5 rounded-2xl border border-slate-200/80 bg-white p-7">
                    {sections.map((section) => (
                        <section
                            key={section.id}
                            id={section.id + '-panel'}
                            role="tabpanel"
                            aria-labelledby={'tab-' + section.id}
                            tabIndex={-1}
                            hidden={active !== section.id}
                            className="scroll-mt-(--detail-offset) focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:outline-none"
                        >
                            <h2 className="mb-5 text-2xl font-black tracking-tight text-slate-950">
                                {section.label}
                            </h2>
                            {active === section.id && panels[section.id]}
                        </section>
                    ))}
                </div>
            </div>

            <div className="grid gap-3 md:hidden">
                {sections.map((section) => {
                    const isOpen = mobileOpen === section.id;

                    return (
                        <section
                            key={section.id}
                            id={section.id + '-mobile'}
                            tabIndex={-1}
                            className="scroll-mt-(--detail-offset) rounded-xl border border-slate-200/80 bg-white p-4 focus-visible:ring-2 focus-visible:ring-orange-400 focus-visible:outline-none"
                        >
                            <h2>
                                <button
                                    type="button"
                                    aria-expanded={isOpen}
                                    aria-controls={
                                        section.id + '-mobile-content'
                                    }
                                    onClick={() => {
                                        const next = isOpen ? null : section.id;
                                        setMobileOpen(next);

                                        if (next) {
                                            setActive(next);
                                            window.history.pushState(
                                                window.history.state,
                                                '',
                                                '#' + next,
                                            );
                                        }
                                    }}
                                    className="flex min-h-11 w-full items-center justify-between gap-3 text-left text-lg font-bold"
                                >
                                    {section.label}
                                    <ChevronDown
                                        className={
                                            'size-5 shrink-0 transition ' +
                                            (isOpen ? 'rotate-180' : '')
                                        }
                                    />
                                </button>
                            </h2>
                            <div
                                id={section.id + '-mobile-content'}
                                hidden={!isOpen}
                                className="pt-5"
                            >
                                {isOpen && panels[section.id]}
                            </div>
                        </section>
                    );
                })}
            </div>
        </div>
    );
}

function Overview({ listing }: { listing: StorefrontListing }) {
    const contentRef = useRef<HTMLDivElement>(null);
    const plainTextLength = (listing.description ?? '')
        .replace(/<[^>]*>/g, ' ')
        .trim().length;
    const [needsDisclosure, setNeedsDisclosure] = useState(
        plainTextLength > 1800,
    );
    const [expanded, setExpanded] = useState(false);

    useEffect(() => {
        const content = contentRef.current;

        if (!content) {
            return;
        }

        const measure = () => {
            setNeedsDisclosure(
                (current) => current || content.scrollHeight > 720,
            );
        };
        measure();

        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        const observer = new ResizeObserver(measure);
        observer.observe(content);

        return () => observer.disconnect();
    }, [listing.description]);

    return (
        <div
            className={
                'grid items-start gap-8 ' +
                (listing.media.length > 1
                    ? 'lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]'
                    : '')
            }
        >
            <div className="min-w-0">
                <div
                    id="full-product-overview"
                    className={
                        'relative ' +
                        (needsDisclosure && !expanded
                            ? 'max-h-[45rem] overflow-hidden'
                            : '')
                    }
                >
                    <div ref={contentRef} className="max-w-[75ch]">
                        {listing.description ? (
                            <RichTextContent
                                value={listing.description}
                                className={
                                    'product-description text-base leading-7 text-slate-600 ' +
                                    (!/<[a-z][^>]*>/i.test(listing.description)
                                        ? 'whitespace-pre-line'
                                        : 'whitespace-normal')
                                }
                            />
                        ) : (
                            <p className="text-base text-slate-500">
                                The seller has not added a detailed description
                                yet.
                            </p>
                        )}
                    </div>
                    {needsDisclosure && !expanded && (
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-white via-white/95 to-transparent"
                        />
                    )}
                </div>
                {needsDisclosure && (
                    <button
                        type="button"
                        aria-expanded={expanded}
                        aria-controls="full-product-overview"
                        onClick={() => setExpanded((current) => !current)}
                        className="mt-4 min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-800 hover:border-orange-300 hover:text-orange-700"
                    >
                        {expanded ? 'Show less' : 'Show full overview'}
                    </button>
                )}
            </div>
            {listing.media.length > 1 && (
                <div
                    aria-label="More product images"
                    className="grid grid-cols-2 gap-3 lg:grid-cols-1"
                >
                    {listing.media.slice(1, 4).map((media, index) => (
                        <img
                            key={media.path}
                            src={media.cardUrl}
                            alt={listing.title + ', detail ' + (index + 1)}
                            width={640}
                            height={640}
                            loading="lazy"
                            className="aspect-square w-full rounded-2xl border border-slate-100 bg-white object-contain p-4"
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

export function ProductQuestionRow({
    question,
    pending = false,
}: {
    question: ProductQuestion;
    pending?: boolean;
}) {
    const [open, setOpen] = useState(pending);

    return (
        <article className="overflow-hidden rounded-2xl border border-slate-200">
            <h3>
                <button
                    type="button"
                    aria-expanded={open}
                    aria-controls={'question-' + question.id}
                    onClick={() => setOpen((current) => !current)}
                    className="flex min-h-14 w-full items-center justify-between gap-4 p-4 text-left"
                >
                    <span>
                        <span className="block text-base font-bold text-slate-950">
                            {question.question}
                        </span>
                        <span className="mt-1 block text-xs text-slate-500">
                            Asked by {question.askedBy}
                        </span>
                    </span>
                    <ChevronDown
                        className={
                            'size-5 shrink-0 text-slate-400 transition ' +
                            (open ? 'rotate-180' : '')
                        }
                    />
                </button>
            </h3>
            <div
                id={'question-' + question.id}
                hidden={!open}
                className="border-t border-slate-100 bg-slate-50 p-4"
            >
                {pending ? (
                    <div>
                        <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">
                            Your question · awaiting answer
                        </span>
                        <p className="mt-3 text-sm leading-6 text-slate-600">
                            This question is visible only to you until the
                            seller responds.
                        </p>
                    </div>
                ) : (
                    <div>
                        <p className="inline-flex items-center gap-1.5 text-xs font-bold text-blue-700">
                            <BadgeCheck className="size-4" />
                            Verified seller answer
                        </p>
                        <p className="mt-3 text-base leading-7 text-slate-700">
                            {question.answer}
                        </p>
                    </div>
                )}
            </div>
        </article>
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
        <article className="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-orange-600 shadow-sm">
                {icon}
            </span>
            <div>
                <h3 className="text-base font-black text-slate-950">{title}</h3>
                <p className="mt-2 text-sm leading-6 text-slate-600">
                    {children}
                </p>
            </div>
        </article>
    );
}
