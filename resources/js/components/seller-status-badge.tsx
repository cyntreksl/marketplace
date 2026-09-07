import { cn } from '@/lib/utils';

const styles: Record<string, string> = {
    pending_payment:
        'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    paid: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
    processing:
        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    ready_to_ship:
        'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
    shipped: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-500/15 dark:text-cyan-300',
    completed:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    expired:
        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
    cancelled:
        'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
};

export function SellerStatusBadge({
    status,
    label,
}: {
    status: string;
    label?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                styles[status] ?? styles.pending_payment,
            )}
        >
            {label ?? status.replaceAll('_', ' ')}
        </span>
    );
}
