import AppLogoIcon from '@/components/app-logo-icon';
import { cn } from '@/lib/utils';

type BrandLogoProps = {
    className?: string;
    compact?: boolean;
    inverse?: boolean;
    showTagline?: boolean;
};

export function BrandLogo({
    className,
    compact = false,
    inverse = false,
    showTagline = false,
}: BrandLogoProps) {
    return (
        <span
            className={cn(
                'inline-flex',
                showTagline
                    ? 'flex-col items-start gap-1'
                    : 'items-center gap-2.5',
                inverse ? 'text-white' : 'text-black dark:text-white',
                className,
            )}
        >
            {!compact ? (
                <img
                    src={
                        inverse
                            ? '/prodeals-logo-inverse.svg'
                            : '/prodeals-logo.svg'
                    }
                    alt="ProDeals.lk"
                    className="h-12 w-48 shrink-0 object-contain"
                />
            ) : (
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-[#ff6000] text-white shadow-lg shadow-[#ff6000]/20">
                    <AppLogoIcon aria-hidden="true" className="size-6" />
                </span>
            )}
            {!compact && (
                <span className="min-w-0 leading-none">
                    {showTagline && (
                        <span
                            className={cn(
                                'mt-1 text-[0.55rem] font-bold tracking-[0.12em] uppercase',
                                inverse
                                    ? 'text-slate-300'
                                    : 'text-slate-500 dark:text-slate-400',
                            )}
                        >
                            Better deals. Closer to home.
                        </span>
                    )}
                </span>
            )}
        </span>
    );
}
