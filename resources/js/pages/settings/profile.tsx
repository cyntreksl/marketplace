import { Form, Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update as updateBuyerProfile } from '@/routes/buyer/settings/profile';
import { edit, update as updateProfile } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
    buyer = false,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    buyer?: boolean;
}) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name and review your email address"
                />

                <Form
                    {...(buyer
                        ? updateBuyerProfile.form()
                        : updateProfile.form())}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full disabled:cursor-not-allowed disabled:opacity-70"
                                    defaultValue={auth.user.email}
                                    disabled
                                    autoComplete="username"
                                    aria-describedby="email-restriction"
                                />

                                <p
                                    id="email-restriction"
                                    className="text-sm text-muted-foreground"
                                >
                                    Email address cannot be changed.
                                </p>
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <Alert
                                        className="border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200"
                                        data-test="email-verification-needed"
                                    >
                                        <CircleAlert />
                                        <AlertTitle>
                                            Email verification needed
                                        </AlertTitle>
                                        <AlertDescription className="text-amber-800 dark:text-amber-300">
                                            <p>
                                                You can continue using ProDeals,
                                                but please verify your email
                                                address.{' '}
                                            </p>
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="font-medium text-amber-950 underline underline-offset-4 dark:text-amber-200"
                                            >
                                                Resend verification email
                                            </Link>

                                            {status ===
                                                'verification-link-sent' && (
                                                <p className="font-medium text-green-700 dark:text-green-400">
                                                    A new verification link has
                                                    been sent to your email
                                                    address.
                                                </p>
                                            )}
                                        </AlertDescription>
                                    </Alert>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
