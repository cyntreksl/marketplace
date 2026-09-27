import { useForm } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/SellerListingInternalDetailsController';
import { ProductInternalFields } from '@/components/product-internal-fields';
import type { SellerProductFormListing } from '@/components/seller-product-form';
import { Button } from '@/components/ui/button';

export function SellerInternalDetailsForm({
    listing,
}: {
    listing: SellerProductFormListing & { id: number };
}) {
    const form = useForm({
        supplier_name: listing.supplier_name ?? '',
        internal_notes: listing.internal_notes ?? '',
        cost_price: listing.cost_price ?? '',
        variants: (listing.variants ?? []).map((variant) => ({
            id: variant.id,
            cost_price: variant.cost_price ?? '',
        })),
    });

    return (
        <form
            className="mt-6 grid gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) =>
                    listing.product_type === 'variant'
                        ? {
                              supplier_name: data.supplier_name,
                              internal_notes: data.internal_notes,
                              variants: data.variants,
                          }
                        : {
                              supplier_name: data.supplier_name,
                              internal_notes: data.internal_notes,
                              cost_price: data.cost_price,
                          },
                );
                form.patch(update.url(listing.id), { preserveScroll: true });
            }}
        >
            <ProductInternalFields
                supplierName={form.data.supplier_name}
                internalNotes={form.data.internal_notes}
                costPrice={form.data.cost_price}
                variants={
                    listing.product_type === 'variant'
                        ? form.data.variants.map((variant, index) => ({
                              label:
                                  listing.variants?.[index].sku ??
                                  `Variant ${index + 1}`,
                              costPrice: variant.cost_price,
                          }))
                        : undefined
                }
                onSupplierChange={(value) =>
                    form.setData('supplier_name', value)
                }
                onNotesChange={(value) => form.setData('internal_notes', value)}
                onCostChange={(value) => form.setData('cost_price', value)}
                onVariantCostChange={(index, value) =>
                    form.setData(
                        'variants',
                        form.data.variants.map((variant, row) =>
                            row === index
                                ? { ...variant, cost_price: value }
                                : variant,
                        ),
                    )
                }
                errorFor={(field) =>
                    (form.errors as Record<string, string>)[field]
                }
            />
            {Object.entries(form.errors)
                .filter(
                    ([field]) => field.endsWith('.id') || field === 'variants',
                )
                .map(([field, message]) => (
                    <p className="text-sm text-destructive" key={field}>
                        {message}
                    </p>
                ))}
            <Button
                type="submit"
                disabled={form.processing}
                className="justify-self-start"
            >
                Save internal details
            </Button>
            {form.recentlySuccessful && (
                <p role="status" className="text-sm text-muted-foreground">
                    Internal details saved.
                </p>
            )}
        </form>
    );
}
