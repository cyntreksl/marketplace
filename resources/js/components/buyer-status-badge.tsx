import { cn } from '@/lib/utils';

const styles: Record<string, string> = {
    to_pay: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    pending:
        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    processing: 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
    shipped:
        'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
    completed:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    succeeded:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    successful:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
    failed: 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
    expired:
        'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    archived:
        'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
};

export function BuyerStatusBadge({
    status,
    label,
}: {
    status: string;
    label?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold',
                styles[status] ??
                    'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
            )}
        >
            {label ?? status.replaceAll('_', ' ')}
        </span>
    );
}
