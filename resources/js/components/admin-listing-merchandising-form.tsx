import { Form } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { updateMerchandising } from '@/actions/App/Http/Controllers/AdminListingController';

export type MerchandisingListing = {
    id: number;
    status: string;
    listing_type: string;
    price: string | null;
    sale_price: string | null;
    is_featured: boolean;
    is_best_offer: boolean;
    is_best_seller: boolean;
    is_new_arrival: boolean;
    is_clearance: boolean;
    sold_count_baseline: number;
    watch_count_baseline: number;
    view_count_baseline: number;
};

export type AdminListingEngagement = {
    sold: EngagementMetric;
    watchers: EngagementMetric;
    views: EngagementMetric;
};

type EngagementMetric = {
    baseline: number;
    actual: number;
    total: number;
};

const placements: {
    name: keyof Pick<
        MerchandisingListing,
        | 'is_featured'
        | 'is_best_offer'
        | 'is_best_seller'
        | 'is_new_arrival'
        | 'is_clearance'
    >;
    label: string;
    description: string;
}[] = [
    {
        name: 'is_featured',
        label: 'Featured',
        description: 'Show in the featured products collection.',
    },
    {
        name: 'is_best_offer',
        label: 'Best Offer',
        description: 'Show in Latest Deals and homepage best offers.',
    },
    {
        name: 'is_best_seller',
        label: 'Best Seller',
        description: 'Show in the best sellers collection.',
    },
    {
        name: 'is_new_arrival',
        label: 'New Arrival',
        description: 'Show in the new arrivals collection.',
    },
    {
        name: 'is_clearance',
        label: 'Clearance',
        description: 'Show in the clearance collection.',
    },
];

export function AdminListingMerchandisingForm({
    listing,
    engagement,
    className = '',
}: {
    listing: MerchandisingListing;
    engagement: AdminListingEngagement;
    className?: string;
}) {
    const hasValidDiscount =
        listing.status === 'approved' &&
        listing.listing_type === 'buy_now' &&
        listing.sale_price !== null &&
        listing.price !== null &&
        Number(listing.sale_price) < Number(listing.price);

    return (
        <section
            className={`rounded-2xl border border-teal-200 bg-white p-5 shadow-sm dark:border-teal-900 dark:bg-slate-900 ${className}`}
        >
            <div className="flex items-start gap-3">
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                    <Sparkles className="size-5" />
                </span>
                <div>
                    <h2 className="text-lg font-black">
                        Product merchandising
                    </h2>
                    <p className="mt-1 text-sm leading-6 text-slate-500">
                        Control where this product is promoted across the
                        storefront.
                    </p>
                </div>
            </div>

            <Form
                {...updateMerchandising.form(listing.id)}
                options={{ preserveScroll: true }}
                className="mt-5 grid gap-4"
            >
                {({ errors, processing, recentlySuccessful }) => (
                    <>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {placements.map((placement) => (
                                <label
                                    key={placement.name}
                                    className="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                                >
                                    <input
                                        type="hidden"
                                        name={placement.name}
                                        value="0"
                                    />
                                    <input
                                        type="checkbox"
                                        name={placement.name}
                                        value="1"
                                        defaultChecked={listing[placement.name]}
                                        className="mt-1 size-4 rounded border-slate-300 text-primary focus:ring-primary"
                                    />
                                    <span>
                                        <span className="block text-sm font-bold">
                                            {placement.label}
                                        </span>
                                        <span className="mt-0.5 block text-xs leading-5 text-slate-500">
                                            {placement.description}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>

                        {!hasValidDiscount && (
                            <p className="rounded-xl bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                Best Offer and Clearance require an approved
                                buy-now product with an offer price below its
                                regular price.
                            </p>
                        )}

                        <div>
                            <h3 className="text-sm font-black">
                                Historical engagement
                            </h3>
                            <p className="mt-1 text-xs leading-5 text-slate-500">
                                Enter documented historical or off-platform
                                figures only. Actual marketplace activity is
                                calculated automatically.
                            </p>
                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                {(
                                    [
                                        [
                                            'sold_count_baseline',
                                            'Sold',
                                            engagement.sold,
                                        ],
                                        [
                                            'watch_count_baseline',
                                            'Watching',
                                            engagement.watchers,
                                        ],
                                        [
                                            'view_count_baseline',
                                            'Views',
                                            engagement.views,
                                        ],
                                    ] as const
                                ).map(([name, label, metric]) => (
                                    <label
                                        key={name}
                                        className="rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                                    >
                                        <span className="block text-sm font-bold">
                                            {label} baseline
                                        </span>
                                        <input
                                            type="number"
                                            name={name}
                                            min={0}
                                            max={10000000}
                                            step={1}
                                            required
                                            defaultValue={listing[name]}
                                            className="mt-2 h-11 w-full rounded-lg border border-slate-300 bg-transparent px-3 font-normal dark:border-slate-700"
                                        />
                                        <span className="mt-2 block text-xs leading-5 text-slate-500">
                                            Actual{' '}
                                            {metric.actual.toLocaleString(
                                                'en-LK',
                                            )}{' '}
                                            · Public total{' '}
                                            <strong className="text-slate-700 dark:text-slate-200">
                                                {metric.total.toLocaleString(
                                                    'en-LK',
                                                )}
                                            </strong>
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <label className="grid gap-1.5 text-sm font-bold">
                            Merchandising reason
                            <textarea
                                required
                                minLength={5}
                                name="reason"
                                rows={3}
                                placeholder="Explain why these placements are changing."
                                className="rounded-xl border border-slate-300 bg-transparent p-3 font-normal dark:border-slate-700"
                            />
                            {errors.reason && (
                                <span className="text-xs font-medium text-red-600">
                                    {errors.reason}
                                </span>
                            )}
                            {Object.entries(errors)
                                .filter(([field]) => field !== 'reason')
                                .map(([field, error]) => (
                                    <span
                                        key={field}
                                        className="text-xs font-medium text-red-600"
                                    >
                                        {error}
                                    </span>
                                ))}
                        </label>

                        <div className="flex items-center gap-3">
                            <button
                                disabled={processing}
                                className="inline-flex h-11 items-center justify-center rounded-xl bg-teal-700 px-5 text-sm font-bold text-white disabled:opacity-50"
                            >
                                {processing ? 'Saving…' : 'Save merchandising'}
                            </button>
                            {recentlySuccessful && (
                                <span className="text-sm font-bold text-teal-700 dark:text-teal-300">
                                    Saved
                                </span>
                            )}
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}
