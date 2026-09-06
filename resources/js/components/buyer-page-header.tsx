export function BuyerPageHeader({
    eyebrow,
    title,
    description,
    actions,
}: {
    eyebrow?: string;
    title: string;
    description?: string;
    actions?: React.ReactNode;
}) {
    return (
        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                {eyebrow && (
                    <p className="text-xs font-bold tracking-[0.16em] text-orange-600 uppercase">
                        {eyebrow}
                    </p>
                )}
                <h1 className="mt-1 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl dark:text-white">
                    {title}
                </h1>
                {description && (
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-400">
                        {description}
                    </p>
                )}
            </div>
            {actions}
        </header>
    );
}
