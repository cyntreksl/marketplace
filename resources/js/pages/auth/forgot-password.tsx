import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Forgot password" />

            {status && (
                <div
                    role="status"
                    className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/50 dark:text-emerald-300"
                >
                    {status}
                </div>
            )}

            <div className="space-y-5">
                <Form {...email.form()} className="flex flex-col gap-5">
                    {({ processing, errors }) => (
                        <>
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
                                    name="email"
                                    required
                                    autoComplete="email"
                                    autoFocus
                                    placeholder="email@example.com"
                                    className="h-12 rounded-xl bg-background px-3.5"
                                />

                                <InputError message={errors.email} />
                            </div>

                            <Button
                                className="h-12 w-full rounded-xl font-semibold shadow-lg shadow-primary/20"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                Send reset link
                            </Button>
                        </>
                    )}
                </Form>

                <div className="border-t pt-5 text-center text-sm text-muted-foreground">
                    Remembered your password?{' '}
                    <TextLink
                        href={login()}
                        className="inline-flex min-h-11 items-center font-semibold text-primary decoration-primary/30"
                    >
                        Log in
                    </TextLink>
                </div>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Forgot password',
    description:
        'Enter the email linked to your account and we’ll send you a secure reset link.',
};
