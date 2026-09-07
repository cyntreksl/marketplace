import { Form, Head } from '@inertiajs/react';
import { Landmark, ShieldCheck, Store } from 'lucide-react';
import { update } from '@/actions/App/Http/Controllers/SellerOnboardingController';
import { SellerPageHeader } from '@/components/seller-page-header';
import { SellerPortalLayout } from '@/components/seller-portal-layout';

export default function SellerOnboarding({
    seller,
}: {
    seller: Record<string, string> | null;
}) {
    return (
        <SellerPortalLayout title="Business profile">
            <Head title="Seller onboarding" />
            <div className="space-y-6">
                <SellerPageHeader
                    eyebrow="Business profile"
                    title="Set up your store"
                    description="Keep your seller and payout details accurate so our operations team can review your account and help you start selling."
                />
                <Form {...update.form()} className="grid gap-5">
                    {({ errors, processing }) => (
                        <>
                            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <div className="flex items-start gap-3 border-b border-slate-100 px-5 py-5 sm:px-6 dark:border-slate-800">
                                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-300">
                                        <Store className="size-5" aria-hidden />
                                    </span>
                                    <div>
                                        <h2 className="font-black">
                                            Business details
                                        </h2>
                                        <p className="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                            Tell us how shoppers and our team
                                            can identify your store.
                                        </p>
                                    </div>
                                </div>
                                <div className="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                                    <Field
                                        label="Seller type"
                                        error={errors.seller_type}
                                    >
                                        <select
                                            name="seller_type"
                                            defaultValue={
                                                seller?.seller_type ??
                                                'individual'
                                            }
                                            className={controlClass}
                                        >
                                            <option value="individual">
                                                Individual
                                            </option>
                                            <option value="business">
                                                Business / Supplier
                                            </option>
                                        </select>
                                    </Field>
                                    <Field
                                        label="Store name"
                                        error={errors.store_name}
                                    >
                                        <input
                                            required
                                            name="store_name"
                                            defaultValue={seller?.store_name}
                                            className={controlClass}
                                        />
                                    </Field>
                                    <Field
                                        label="Phone"
                                        error={errors.phone}
                                        className="sm:col-span-2"
                                    >
                                        <input
                                            required
                                            name="phone"
                                            inputMode="tel"
                                            autoComplete="tel"
                                            defaultValue={seller?.phone}
                                            className={controlClass}
                                        />
                                    </Field>
                                </div>
                            </section>
                            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                                <div className="flex items-start gap-3 border-b border-slate-100 px-5 py-5 sm:px-6 dark:border-slate-800">
                                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-300">
                                        <Landmark
                                            className="size-5"
                                            aria-hidden
                                        />
                                    </span>
                                    <div>
                                        <h2 className="font-black">
                                            Payout details
                                        </h2>
                                        <p className="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">
                                            Add the account where your seller
                                            earnings should be paid.
                                        </p>
                                    </div>
                                </div>
                                <div className="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                                    <Field
                                        label="Bank account name"
                                        error={errors.bank_account_name}
                                    >
                                        <input
                                            required
                                            name="bank_account_name"
                                            autoComplete="name"
                                            defaultValue={
                                                seller?.bank_account_name
                                            }
                                            className={controlClass}
                                        />
                                    </Field>
                                    <Field
                                        label="Bank account details"
                                        error={errors.bank_account_details}
                                    >
                                        <input
                                            required
                                            name="bank_account_details"
                                            className={controlClass}
                                        />
                                    </Field>
                                </div>
                            </section>
                            <div className="grid gap-4 rounded-2xl border border-orange-200 bg-orange-50/70 p-4 sm:grid-cols-[1fr_auto] sm:items-center sm:p-5 dark:border-orange-500/20 dark:bg-orange-500/10">
                                <label className="flex items-start gap-3 text-sm leading-6 text-slate-700 dark:text-slate-200">
                                    <input
                                        required
                                        name="accept_terms"
                                        type="checkbox"
                                        className="mt-1 size-4 shrink-0 accent-orange-600"
                                    />
                                    <span>
                                        I accept the marketplace terms and
                                        commission rules.
                                        {errors.accept_terms && (
                                            <span className="block text-red-600">
                                                {errors.accept_terms}
                                            </span>
                                        )}
                                    </span>
                                </label>
                                <button
                                    disabled={processing}
                                    className="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-primary px-6 text-sm font-bold text-primary-foreground shadow-sm transition hover:opacity-90 disabled:opacity-50"
                                >
                                    <ShieldCheck
                                        className="size-4"
                                        aria-hidden
                                    />
                                    {processing
                                        ? 'Saving…'
                                        : 'Submit for review'}
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </SellerPortalLayout>
    );
}

const controlClass =
    'min-h-12 w-full rounded-xl border border-slate-200 bg-transparent px-3 text-base outline-none transition focus:border-orange-500 focus:ring-3 focus:ring-orange-500/10 sm:text-sm dark:border-slate-700';

function Field({
    label,
    error,
    className,
    children,
}: {
    label: string;
    error?: string;
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <label className={`grid gap-2 text-sm font-bold ${className ?? ''}`}>
            {label}
            {children}
            {error && <span className="font-normal text-red-600">{error}</span>}
        </label>
    );
}
