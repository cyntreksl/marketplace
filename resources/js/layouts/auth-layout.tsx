import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    compact = false,
    children,
}: {
    title?: string;
    description?: string;
    compact?: boolean;
    children: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            compact={compact}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
