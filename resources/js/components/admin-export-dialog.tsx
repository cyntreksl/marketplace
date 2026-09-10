import { Download } from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export type ExportColumnOption = {
    key: string;
    label: string;
    defaultSelected: boolean;
};

export function AdminExportDialog({
    action,
    filters,
    columns,
    title,
}: {
    action: string;
    filters: Record<string, string>;
    columns: ExportColumnOption[];
    title: string;
}) {
    const [selectedColumns, setSelectedColumns] = useState<string[]>(
        columns
            .filter((column) => column.defaultSelected)
            .map((column) => column.key),
    );

    function toggleColumn(key: string, checked: boolean): void {
        setSelectedColumns((selected) =>
            checked
                ? [...selected, key]
                : selected.filter((column) => column !== key),
        );
    }

    return (
        <Dialog>
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-primary px-4 text-sm font-bold text-primary transition hover:bg-primary hover:text-primary-foreground"
                >
                    <Download className="size-4" />
                    Export Excel
                </button>
            </DialogTrigger>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <form action={action} method="get">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>
                            Choose the columns to include. The workbook will
                            contain every result matching the current filters.
                        </DialogDescription>
                    </DialogHeader>

                    {Object.entries(filters).map(([name, value]) => (
                        <input
                            key={name}
                            type="hidden"
                            name={name}
                            value={value}
                        />
                    ))}

                    <div className="mt-5 flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() =>
                                setSelectedColumns(
                                    columns.map((column) => column.key),
                                )
                            }
                            className="rounded-lg border px-3 py-2 text-sm font-semibold"
                        >
                            Select all
                        </button>
                        <button
                            type="button"
                            onClick={() => setSelectedColumns([])}
                            className="rounded-lg border px-3 py-2 text-sm font-semibold"
                        >
                            Clear
                        </button>
                    </div>

                    <div className="mt-4 grid gap-2 sm:grid-cols-2">
                        {columns.map((column) => (
                            <label
                                key={column.key}
                                className="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 text-sm dark:border-slate-800"
                            >
                                <input
                                    type="checkbox"
                                    name="columns[]"
                                    value={column.key}
                                    checked={selectedColumns.includes(
                                        column.key,
                                    )}
                                    onChange={(event) =>
                                        toggleColumn(
                                            column.key,
                                            event.target.checked,
                                        )
                                    }
                                    className="size-4 rounded border-slate-300 text-primary focus:ring-primary"
                                />
                                <span>{column.label}</span>
                            </label>
                        ))}
                    </div>

                    <DialogFooter className="mt-6">
                        <button
                            type="submit"
                            disabled={selectedColumns.length === 0}
                            className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-5 text-sm font-bold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Download className="size-4" />
                            Export {selectedColumns.length} column
                            {selectedColumns.length === 1 ? '' : 's'}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
