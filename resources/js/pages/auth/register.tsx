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

                            <Button
                                type="submit"
                                className="mt-2 h-12 w-full rounded-xl text-sm font-semibold shadow-lg shadow-primary/20"
                                tabIndex={5}
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
                                className="font-semibold text-primary decoration-primary/30"
                                tabIndex={6}
                            >
                                Log in
                            </TextLink>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Want to sell on ProDeals.lk?{' '}
                            <TextLink
                                href={sellerRegister()}
                                className="font-semibold text-primary decoration-primary/30"
                                tabIndex={7}
                            >
                                Become a seller
                            </TextLink>
                        </div>
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
