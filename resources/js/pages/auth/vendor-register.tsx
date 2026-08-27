import { Form, Head } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Landmark,
    Store,
    UserRound,
} from 'lucide-react';
import { useRef, useState } from 'react';
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

const steps = [
    {
        title: 'Account',
        description: 'Create your login',
        icon: UserRound,
    },
    {
        title: 'Store',
        description: 'Tell us about your business',
        icon: Store,
    },
    {
        title: 'Payout',
        description: 'Add payment details',
        icon: Landmark,
    },
];

const accountFields = ['name', 'email', 'password', 'password_confirmation'];
const storeFields = [
    'seller_type',
    'store_name',
    'phone',
    'pickup_address',
    'return_address',
];

export default function VendorRegister({ passwordRules }: Props) {
    const [currentStep, setCurrentStep] = useState(1);
    const stepReferences = useRef<Record<number, HTMLElement | null>>({});
    const passwordInputReference = useRef<HTMLInputElement>(null);

    function validateCurrentStep(): boolean {
        const currentSection = stepReferences.current[currentStep];

        if (!currentSection) {
            return false;
        }

        const passwordConfirmation =
            currentSection.querySelector<HTMLInputElement>(
                '#password_confirmation',
            );

        if (passwordConfirmation) {
            passwordConfirmation.setCustomValidity(
                passwordConfirmation.value ===
                    passwordInputReference.current?.value
                    ? ''
                    : 'Password confirmation does not match.',
            );
        }

        const fields = currentSection.querySelectorAll<
            HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
        >('input, select, textarea');

        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();

                return false;
            }
        }

        return true;
    }

    function moveToNextStep(): void {
        if (validateCurrentStep()) {
            setCurrentStep((step) => Math.min(step + 1, steps.length));
        }
    }

    function showFirstStepWithErrors(errors: Record<string, string>): void {
        if (accountFields.some((field) => field in errors)) {
            setCurrentStep(1);

            return;
        }

        if (storeFields.some((field) => field in errors)) {
            setCurrentStep(2);

            return;
        }

        setCurrentStep(3);
    }

    return (
        <>
            <Head title="Become a vendor" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                onError={showFirstStepWithErrors}
                className="flex flex-col gap-3"
            >
                {({ processing, errors }) => (
                    <>
                        <input
                            type="hidden"
                            name="registration_type"
                            value="vendor"
                        />

                        <div className="rounded-xl border bg-slate-50/80 p-3 dark:bg-slate-900/50">
                            <div className="mb-2 flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-semibold text-foreground">
                                        Vendor application
                                    </p>
                                    <p className="hidden text-xs text-muted-foreground sm:block">
                                        A few details, then we’ll review your
                                        store.
                                    </p>
                                </div>
                                <span className="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary">
                                    Step {currentStep} of {steps.length}
                                </span>
                            </div>

                            <ol
                                aria-label="Vendor registration progress"
                                className="relative grid grid-cols-3 gap-2"
                            >
                                <span
                                    aria-hidden="true"
                                    className="absolute top-4 right-[16.67%] left-[16.67%] h-px bg-border"
                                />
                                <span
                                    aria-hidden="true"
                                    className="absolute top-4 left-[16.67%] h-px bg-primary transition-all duration-300"
                                    style={{
                                        width: `${((currentStep - 1) / (steps.length - 1)) * 66.66}%`,
                                    }}
                                />
                                {steps.map((step, index) => {
                                    const stepNumber = index + 1;
                                    const isComplete = stepNumber < currentStep;
                                    const isCurrent =
                                        stepNumber === currentStep;
                                    const Icon = step.icon;

                                    return (
                                        <li
                                            key={step.title}
                                            className="relative z-10"
                                        >
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    if (isComplete) {
                                                        setCurrentStep(
                                                            stepNumber,
                                                        );
                                                    }
                                                }}
                                                disabled={!isComplete}
                                                aria-current={
                                                    isCurrent
                                                        ? 'step'
                                                        : undefined
                                                }
                                                className="group flex w-full flex-col items-center gap-1 text-center disabled:cursor-default"
                                            >
                                                <span
                                                    className={`grid size-8 place-items-center rounded-full border-2 bg-background transition-all duration-200 ${
                                                        isComplete
                                                            ? 'border-primary bg-primary text-primary-foreground shadow-md shadow-primary/20'
                                                            : isCurrent
                                                              ? 'border-primary text-primary shadow-lg ring-4 shadow-primary/20 ring-primary/10'
                                                              : 'border-border text-muted-foreground'
                                                    }`}
                                                >
                                                    {isComplete ? (
                                                        <Check className="size-4" />
                                                    ) : (
                                                        <Icon className="size-4" />
                                                    )}
                                                </span>
                                                <span
                                                    className={`text-xs font-bold transition-colors ${
                                                        isCurrent || isComplete
                                                            ? 'text-foreground'
                                                            : 'text-muted-foreground'
                                                    }`}
                                                >
                                                    {step.title}
                                                </span>
                                                <span className="hidden text-[11px] leading-4 text-muted-foreground md:block">
                                                    {step.description}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ol>
                        </div>

                        <div className="rounded-xl border bg-card p-4 shadow-sm sm:p-5 dark:shadow-none">
                            <section
                                ref={(element) => {
                                    stepReferences.current[1] = element;
                                }}
                                hidden={currentStep !== 1}
                                aria-labelledby="account-step-title"
                                className="grid gap-3.5"
                            >
                                <div className="flex items-center gap-2.5 border-b pb-3">
                                    <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                        <UserRound className="size-4" />
                                    </span>
                                    <div>
                                        <p className="text-xs font-bold tracking-wide text-primary uppercase">
                                            Account details
                                        </p>
                                        <h2
                                            id="account-step-title"
                                            className="text-base font-bold"
                                        >
                                            Create your vendor login
                                        </h2>
                                    </div>
                                </div>

                                <div className="grid gap-3.5 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="name"
                                            className="font-semibold"
                                        >
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

                                    <div className="grid gap-1.5">
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

                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="password"
                                            className="font-semibold"
                                        >
                                            Create a password
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            ref={passwordInputReference}
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

                                    <div className="grid gap-1.5">
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
                                            message={
                                                errors.password_confirmation
                                            }
                                        />
                                    </div>
                                </div>
                            </section>

                            <section
                                ref={(element) => {
                                    stepReferences.current[2] = element;
                                }}
                                hidden={currentStep !== 2}
                                aria-labelledby="store-step-title"
                                className="grid gap-3.5"
                            >
                                <div className="flex items-center gap-2.5 border-b pb-3">
                                    <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                        <Store className="size-4" />
                                    </span>
                                    <div>
                                        <p className="text-xs font-bold tracking-wide text-primary uppercase">
                                            Store details
                                        </p>
                                        <h2
                                            id="store-step-title"
                                            className="text-base font-bold"
                                        >
                                            Tell us about your business
                                        </h2>
                                        <p className="hidden text-sm text-muted-foreground md:block">
                                            We review each vendor before
                                            listings can go live.
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-3.5 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
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
                                        <InputError
                                            message={errors.seller_type}
                                        />
                                    </div>

                                    <div className="grid gap-1.5">
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
                                        <InputError
                                            message={errors.store_name}
                                        />
                                    </div>
                                </div>

                                <div className="grid gap-1.5">
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

                                <div className="grid gap-1.5">
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
                                        className="min-h-20 w-full rounded-xl border bg-background px-3.5 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                    <InputError
                                        message={errors.pickup_address}
                                    />
                                </div>

                                <div className="grid gap-1.5">
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
                                        className="min-h-20 w-full rounded-xl border bg-background px-3.5 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    />
                                    <InputError
                                        message={errors.return_address}
                                    />
                                </div>
                            </section>

                            <section
                                ref={(element) => {
                                    stepReferences.current[3] = element;
                                }}
                                hidden={currentStep !== 3}
                                aria-labelledby="payout-step-title"
                                className="grid gap-3.5"
                            >
                                <div className="flex items-center gap-2.5 border-b pb-3">
                                    <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                        <Landmark className="size-4" />
                                    </span>
                                    <div>
                                        <p className="text-xs font-bold tracking-wide text-primary uppercase">
                                            Payout details
                                        </p>
                                        <h2
                                            id="payout-step-title"
                                            className="text-base font-bold"
                                        >
                                            Add your payout details
                                        </h2>
                                        <p className="hidden text-sm text-muted-foreground md:block">
                                            Check your details, then submit your
                                            vendor application for review.
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-3.5 sm:grid-cols-2">
                                    <div className="grid gap-1.5">
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

                                    <div className="grid gap-1.5">
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
                                            message={
                                                errors.bank_account_details
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="rounded-xl border bg-muted/30 p-3 text-sm text-muted-foreground">
                                    Your account and store information will be
                                    reviewed before you can publish listings.
                                </div>

                                <label className="flex items-start gap-3 text-sm leading-5">
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

                            <div className="flex gap-3 border-t pt-3">
                                {currentStep > 1 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setCurrentStep((step) => step - 1)
                                        }
                                        className="h-10 flex-1 rounded-xl text-sm font-semibold"
                                    >
                                        <ArrowLeft className="size-4" />
                                        Back
                                    </Button>
                                )}

                                {currentStep < steps.length ? (
                                    <Button
                                        type="button"
                                        onClick={moveToNextStep}
                                        className="h-10 flex-1 rounded-xl text-sm font-semibold shadow-lg shadow-primary/20"
                                    >
                                        Continue
                                        <ArrowRight className="size-4" />
                                    </Button>
                                ) : (
                                    <Button
                                        type="submit"
                                        className="h-10 flex-1 rounded-xl text-sm font-semibold shadow-lg shadow-primary/20"
                                        tabIndex={13}
                                        data-test="register-vendor-button"
                                    >
                                        {processing && <Spinner />}
                                        Submit vendor application
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="text-center text-xs text-muted-foreground">
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
    compact: true,
};
