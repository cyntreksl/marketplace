import type { CSSProperties } from 'react';
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
    const iconStyle = {
        '--prodeals-spark': '#f6c65b',
    } as CSSProperties;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-2.5',
                inverse ? 'text-white' : 'text-slate-950 dark:text-white',
                className,
            )}
        >
            <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-[#102a5c] text-white shadow-lg shadow-[#102a5c]/20">
                <AppLogoIcon
                    aria-hidden="true"
                    className="size-6"
                    style={iconStyle}
                />
            </span>
            {!compact && (
                <span className="grid min-w-0 leading-none">
                    <span className="font-black tracking-[-0.055em]">
                        ProDeals
                        <span className="text-[#0f766e]">.lk</span>
                    </span>
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
