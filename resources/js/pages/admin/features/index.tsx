import { Form, Head } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/AdminFeatureSettingsController';
import { PortalLayout } from '@/components/portal-layout';
import type { ReviewFlags } from '@/types/reviews';

export default function FeatureSettings({ flags }: { flags: ReviewFlags }) {
    return (
        <PortalLayout portal="admin" title="Feature settings">
            <Head title="Feature Settings" />
            <div className="mx-auto max-w-3xl">
                <h1 className="text-2xl font-bold">Customer feedback</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Control product reviews and future seller feedback
                    independently without changing marketplace moderation.
                </p>
                <Form
                    {...update.form()}
                    className="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
                >
                    {({ processing }) => (
                        <>
                            <FeatureToggle
                                name="product"
                                label="Product reviews"
                                description="Show review links, buyer feedback history, and product review content."
                                checked={flags.product}
                            />
                            <FeatureToggle
                                name="seller"
                                label="Seller feedback"
                                description="Reserved for future customer-facing seller rating and feedback surfaces."
                                checked={flags.seller}
                            />
                            <button
                                disabled={processing}
                                className="mt-2 min-h-11 justify-self-start rounded-xl bg-primary px-6 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                            >
                                {processing
                                    ? 'Saving…'
                                    : 'Save feature settings'}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </PortalLayout>
    );
}

function FeatureToggle({
    name,
    label,
    description,
    checked,
}: {
    name: keyof ReviewFlags;
    label: string;
    description: string;
    checked: boolean;
}) {
    return (
        <label className="flex items-start justify-between gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <span>
                <span className="block font-semibold">{label}</span>
                <span className="mt-1 block text-sm text-slate-500">
                    {description}
                </span>
            </span>
            <input type="hidden" name={name} value="0" />
            <input
                type="checkbox"
                name={name}
                value="1"
                defaultChecked={checked}
                className="mt-1 size-5 accent-primary"
            />
        </label>
    );
}
