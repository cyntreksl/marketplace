import { Form, Head } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Email verification" />

            <div className="mb-5 flex items-start gap-3 rounded-2xl border bg-muted/40 p-4 text-sm text-muted-foreground">
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                    <MailCheck className="size-5" />
                </span>
                <p className="leading-6">
                    Open the email from ProDeals.lk and tap the verification
                    button. You can safely return to this page afterwards.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div
                    role="status"
                    className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/50 dark:text-emerald-300"
                >
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <Form {...send.form()} className="space-y-4 text-center">
                {({ processing }) => (
                    <>
                        <Button
                            disabled={processing}
                            className="h-12 w-full rounded-xl font-semibold shadow-lg shadow-primary/20"
                        >
                            {processing && <Spinner />}
                            Resend verification email
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto inline-flex min-h-11 items-center justify-center px-4 text-sm font-semibold text-muted-foreground"
                        >
                            Log out
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Email verification',
    description: 'Verify your email address to finish setting up your account.',
};
