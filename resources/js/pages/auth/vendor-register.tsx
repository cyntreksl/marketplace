import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function VendorRegister({ passwordRules }: Props) {
    return (
        <>
            <Head title="Become a vendor" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="registration_type"
                            value="vendor"
                        />

                        <section className="grid gap-5">
                            <div>
                                <p className="text-sm font-semibold text-primary">
                                    Your account
                                </p>
                                <h2 className="mt-1 text-lg font-bold">
                                    Create your vendor login
                                </h2>
                            </div>

                            <div className="grid gap-2.5">
                                <Label htmlFor="name" className="font-semibold">
                                    Full name
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Full name"
                                    className="h-12 rounded-xl bg-background px-3.5"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2.5">
                                <Label
                                    htmlFor="email"
                                    className="font-semibold"
                                >
                                    Email address
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                    className="h-12 rounded-xl bg-background px-3.5"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2.5">
                                <Label
                                    htmlFor="password"
                                    className="font-semibold"
                                >
                                    Create a password
                                </Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                    className="h-12 rounded-xl bg-background px-3.5"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2.5">
                                <Label
                                    htmlFor="password_confirmation"
                                    className="font-semibold"
                                >
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                    className="h-12 rounded-xl bg-background px-3.5"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>
                        </section>

                        <section className="grid gap-5 border-t pt-6">
                            <div>
                                <p className="text-sm font-semibold text-primary">
                                    Your store
                                </p>
                                <h2 className="mt-1 text-lg font-bold">
                                    Tell us about your business
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    We review each vendor before listings can go
                                    live.
                                </p>
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-2.5">
                                    <Label
                                        htmlFor="seller_type"
                                        className="font-semibold"
                                    >
                                        Vendor type
                                    </Label>
                                    <select
                                        id="seller_type"
                                        name="seller_type"
                                        required
                                        defaultValue="individual"
                                        tabIndex={5}
                                        className="h-12 w-full rounded-xl border bg-background px-3.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    >
                                        <option value="individual">
                                            Individual
                                        </option>
                                        <option value="business">
                                            Business / Supplier
                                        </option>
                                    </select>
                                    <InputError message={errors.seller_type} />
                                </div>

                                <div className="grid gap-2.5">
                                    <Label
                                        htmlFor="store_name"
                                        className="font-semibold"
                                    >
                                        Store name
                                    </Label>
                                    <Input
                                        id="store_name"
                                        type="text"
                                        required
                                        tabIndex={6}
                                        name="store_name"
                                        placeholder="Your store name"
                                        className="h-12 rounded-xl bg-background px-3.5"
                                    />
                                    <InputError message={errors.store_name} />
                                </div>
                            </div>

                            <div className="grid gap-2.5">
                                <Label
                                    htmlFor="phone"
                                    className="font-semibold"
                                >
                                    Phone number
                                </Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    required
                                    tabIndex={7}
                                    autoComplete="tel"
                                    name="phone"
                                    placeholder="077 123 4567"
                                    className="h-12 rounded-xl bg-background px-3.5"
                                />
                                <InputError message={errors.phone} />
                            </div>

                            <div className="grid gap-2.5">
                                <Label
                                    htmlFor="pickup_address"
                                    className="font-semibold"
                                >
                                    Pickup address
                                </Label>
                                <textarea
                                    id="pickup_address"
                                    required
                                    tabIndex={8}
                                    name="pickup_address"
                                    placeholder="Where should collections be made?"
                                    className="min-h-28 w-full rounded-xl border bg-background px-3.5 py-3 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={errors.pickup_address} />
                            </div>

                            <div className="grid gap-2.5">
                                <Label
                                    htmlFor="return_address"
                                    className="font-semibold"
                                >
                                    Return address
                                </Label>
                                <textarea
                                    id="return_address"
                                    required
                                    tabIndex={9}
                                    name="return_address"
                                    placeholder="Where should customer returns be sent?"
                                    className="min-h-28 w-full rounded-xl border bg-background px-3.5 py-3 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError message={errors.return_address} />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div className="grid gap-2.5">
                                    <Label
                                        htmlFor="bank_account_name"
                                        className="font-semibold"
                                    >
                                        Bank account name
                                    </Label>
                                    <Input
                                        id="bank_account_name"
                                        type="text"
                                        required
                                        tabIndex={10}
                                        name="bank_account_name"
                                        placeholder="Account holder name"
                                        className="h-12 rounded-xl bg-background px-3.5"
                                    />
                                    <InputError
                                        message={errors.bank_account_name}
                                    />
                                </div>

                                <div className="grid gap-2.5">
                                    <Label
                                        htmlFor="bank_account_details"
                                        className="font-semibold"
                                    >
                                        Bank account details
                                    </Label>
                                    <Input
                                        id="bank_account_details"
                                        type="text"
                                        required
                                        tabIndex={11}
                                        name="bank_account_details"
                                        placeholder="Bank, branch and account number"
                                        className="h-12 rounded-xl bg-background px-3.5"
                                    />
                                    <InputError
                                        message={errors.bank_account_details}
                                    />
                                </div>
                            </div>

                            <label className="flex items-start gap-3 text-sm leading-6">
                                <input
                                    required
                                    tabIndex={12}
                                    name="accept_terms"
                                    type="checkbox"
                                    className="mt-1 size-4 rounded border-input text-primary focus:ring-ring"
                                />
                                <span>
                                    I accept the marketplace terms and
                                    commission rules.
                                </span>
                            </label>
                            <InputError message={errors.accept_terms} />
                        </section>

                        <Button
                            type="submit"
                            className="h-12 w-full rounded-xl text-sm font-semibold shadow-lg shadow-primary/20"
                            tabIndex={13}
                            data-test="register-vendor-button"
                        >
                            {processing && <Spinner />}
                            Submit vendor application
                        </Button>

                        <div className="border-t pt-5 text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink
                                href={login()}
                                className="font-semibold text-primary decoration-primary/30"
                                tabIndex={14}
                            >
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

VendorRegister.layout = {
    title: 'Become a vendor',
    description: 'Create your account and submit your store for review.',
};
