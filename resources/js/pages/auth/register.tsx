import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, Store } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { register as sellerRegister } from '@/routes/seller';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Register" />
            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2.5">
                                <Label htmlFor="name" className="font-semibold">
                                    Full name
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
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

                            <Button
                                type="submit"
                                className="mt-2 h-12 w-full rounded-xl text-sm font-semibold shadow-lg shadow-primary/20"
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create account
                            </Button>
                        </div>

                        <div className="border-t pt-5 text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink
                                href={login()}
                                className="inline-flex min-h-11 items-center font-semibold text-primary decoration-primary/30"
                            >
                                Log in
                            </TextLink>
                        </div>

                        <Link
                            href={sellerRegister()}
                            className="group flex min-h-16 items-center gap-3 rounded-2xl border border-primary/20 bg-primary/5 p-3.5 text-left transition-colors hover:border-primary/40 hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        >
                            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary text-primary-foreground shadow-sm">
                                <Store className="size-5" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-semibold text-foreground">
                                    Want to sell on ProDeals.lk?
                                </span>
                                <span className="block text-xs leading-5 text-muted-foreground">
                                    Create a seller account and submit your
                                    store for review.
                                </span>
                            </span>
                            <ArrowRight className="size-4 shrink-0 text-primary transition-transform group-hover:translate-x-0.5" />
                        </Link>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Create your account',
    description: 'Join the marketplace and start exploring in minutes.',
};
