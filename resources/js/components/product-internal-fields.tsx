import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    supplierName: string;
    internalNotes: string;
    costPrice: string;
    variants?: { label: string; costPrice: string }[];
    onSupplierChange: (value: string) => void;
    onNotesChange: (value: string) => void;
    onCostChange: (value: string) => void;
    onVariantCostChange: (index: number, value: string) => void;
    errorFor: (field: string) => string | undefined;
};

export function ProductInternalFields(props: Props) {
    const costInput = (
        id: string,
        label: string,
        value: string,
        onChange: (value: string) => void,
        field: string,
    ) => (
        <div className="grid gap-2" key={id}>
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                min="0"
                max="9999999999.99"
                step="0.01"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={!!props.errorFor(field)}
            />
            {props.errorFor(field) && (
                <p className="text-sm text-destructive">
                    {props.errorFor(field)}
                </p>
            )}
        </div>
    );

    return (
        <section className="grid gap-5 rounded-2xl border bg-card p-5 text-card-foreground">
            <div>
                <h2 className="text-lg font-bold">Internal details</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Private to your store and authorized administrators.
                    Customers cannot see these details.
                </p>
            </div>
            <div className="grid gap-2">
                <Label htmlFor="supplier-name">Supplier name</Label>
                <Input
                    id="supplier-name"
                    maxLength={255}
                    value={props.supplierName}
                    onChange={(event) =>
                        props.onSupplierChange(event.target.value)
                    }
                    aria-invalid={!!props.errorFor('supplier_name')}
                />
                {props.errorFor('supplier_name') && (
                    <p className="text-sm text-destructive">
                        {props.errorFor('supplier_name')}
                    </p>
                )}
            </div>
            {props.variants === undefined ? (
                costInput(
                    'cost-price',
                    'Cost price (LKR)',
                    props.costPrice,
                    props.onCostChange,
                    'cost_price',
                )
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {props.variants.map((variant, index) =>
                        costInput(
                            `variant-cost-${index}`,
                            `${variant.label} — cost price (LKR)`,
                            variant.costPrice,
                            (value) => props.onVariantCostChange(index, value),
                            `variants.${index}.cost_price`,
                        ),
                    )}
                </div>
            )}
            <div className="grid gap-2">
                <Label htmlFor="internal-notes">Internal notes</Label>
                <textarea
                    className="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    id="internal-notes"
                    rows={4}
                    maxLength={10000}
                    value={props.internalNotes}
                    onChange={(event) =>
                        props.onNotesChange(event.target.value)
                    }
                    aria-invalid={!!props.errorFor('internal_notes')}
                />
                {props.errorFor('internal_notes') && (
                    <p className="text-sm text-destructive">
                        {props.errorFor('internal_notes')}
                    </p>
                )}
            </div>
        </section>
    );
}
