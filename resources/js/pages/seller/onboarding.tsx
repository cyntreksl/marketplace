import { Form, Head } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/SellerOnboardingController';
import { PortalLayout } from '@/components/portal-layout';

export default function SellerOnboarding({
    seller,
}: {
    seller: Record<string, string> | null;
}) {
    return (
        <PortalLayout portal="seller" title="Become a seller">
            <Head title="Seller onboarding" />
            <main className="mx-auto max-w-3xl">
                <p className="text-sm font-bold tracking-wider text-amber-700 uppercase">
                    Seller portal
                </p>
                <h1 className="mt-2 text-4xl font-black">Set up your store</h1>
                <p className="mt-3 text-stone-600 dark:text-stone-300">
                    Complete your details once. Our operations team will review
                    your account before listings can go live.
                </p>
                <Form
                    {...update.form()}
                    className="mt-8 grid gap-5 rounded-2xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-900"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <label className="grid gap-2 font-semibold">
                                    Seller type
                                    <select
                                        name="seller_type"
                                        defaultValue={
                                            seller?.seller_type ?? 'individual'
                                        }
                                        className="rounded-lg border bg-transparent p-3"
                                    >
                                        <option value="individual">
                                            Individual
                                        </option>
                                        <option value="business">
                                            Business / Supplier
                                        </option>
                                    </select>
                                </label>
                                <label className="grid gap-2 font-semibold">
                                    Store name
                                    <input
                                        required
                                        name="store_name"
                                        defaultValue={seller?.store_name}
                                        className="rounded-lg border bg-transparent p-3"
                                    />
                                </label>
                            </div>
                            <label className="grid gap-2 font-semibold">
                                Phone
                                <input
                                    required
                                    name="phone"
                                    defaultValue={seller?.phone}
                                    className="rounded-lg border bg-transparent p-3"
                                />
                            </label>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <label className="grid gap-2 font-semibold">
                                    Bank account name
                                    <input
                                        required
                                        name="bank_account_name"
                                        defaultValue={seller?.bank_account_name}
                                        className="rounded-lg border bg-transparent p-3"
                                    />
                                </label>
                                <label className="grid gap-2 font-semibold">
                                    Bank account details
                                    <input
                                        required
                                        name="bank_account_details"
                                        className="rounded-lg border bg-transparent p-3"
                                    />
                                </label>
                            </div>
                            <label className="flex gap-3 text-sm">
                                <input
                                    required
                                    name="accept_terms"
                                    type="checkbox"
                                />
                                I accept the marketplace terms and commission
                                rules.
                            </label>
                            {Object.values(errors).map((error) => (
                                <p className="text-sm text-red-600" key={error}>
                                    {error}
                                </p>
                            ))}
                            <button
                                disabled={processing}
                                className="rounded-full bg-amber-400 px-5 py-3 font-bold text-stone-950 disabled:opacity-50"
                            >
                                {processing ? 'Saving…' : 'Submit for review'}
                            </button>
                        </>
                    )}
                </Form>
            </main>
        </PortalLayout>
    );
}
