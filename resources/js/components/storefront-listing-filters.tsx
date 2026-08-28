import { Form, Link } from '@inertiajs/react';
import { RotateCcw, SlidersHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as listingsIndex } from '@/routes/listings';
import type { StorefrontBrand, StorefrontBrowseFilters } from '@/types';

function HiddenBrowseContext({
    filters,
}: {
    filters: StorefrontBrowseFilters;
}) {
    return (
        <>
            {filters.search && (
                <input type="hidden" name="search" value={filters.search} />
            )}
            {filters.category && (
                <input type="hidden" name="category" value={filters.category} />
            )}
            <input type="hidden" name="sort" value={filters.sort} />
        </>
    );
}

function RadioGroup({
    label,
    name,
    value,
    options,
}: {
    label: string;
    name: string;
    value?: string | null;
    options: { label: string; value: string }[];
}) {
    return (
        <fieldset className="space-y-3">
            <legend className="text-sm font-black text-slate-900 dark:text-white">
                {label}
            </legend>
            <div className="grid grid-cols-2 gap-2">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-medium transition has-[:checked]:border-primary has-[:checked]:bg-primary/10 has-[:checked]:text-primary dark:border-slate-700 dark:has-[:checked]:border-primary"
                    >
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            defaultChecked={(value ?? '') === option.value}
                            className="size-4 accent-primary"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

export function StorefrontListingFilters({
    filters,
    brands,
    idPrefix,
    className = '',
}: {
    filters: StorefrontBrowseFilters;
    brands: StorefrontBrand[];
    idPrefix: string;
    className?: string;
}) {
    const brandInputId = `${idPrefix}-brand`;
    const locationInputId = `${idPrefix}-location`;
    const resetQuery = {
        ...(filters.search ? { search: filters.search } : {}),
        ...(filters.category ? { category: filters.category } : {}),
    };

    return (
        <Form {...listingsIndex.form()} className={`space-y-6 ${className}`}>
            <HiddenBrowseContext filters={filters} />
            <div className="flex items-center gap-2">
                <span className="grid size-9 place-items-center rounded-xl bg-primary/10 text-primary">
                    <SlidersHorizontal className="size-4" />
                </span>
                <div>
                    <h2 className="font-black text-slate-950 dark:text-white">
                        Filter products
                    </h2>
                    <p className="text-xs text-slate-500">
                        Narrow down your results
                    </p>
                </div>
            </div>

            <RadioGroup
                label="Listing type"
                name="listing_type"
                value={filters.listing_type}
                options={[
                    { label: 'All', value: '' },
                    { label: 'Buy now', value: 'buy_now' },
                    { label: 'Auction', value: 'auction' },
                ]}
            />

            <RadioGroup
                label="Condition"
                name="condition"
                value={filters.condition}
                options={[
                    { label: 'Any', value: '' },
                    { label: 'New', value: 'new' },
                    { label: 'Used', value: 'used' },
                    { label: 'Refurbished', value: 'refurbished' },
                ]}
            />

            <div className="space-y-3">
                <label
                    htmlFor={brandInputId}
                    className="text-sm font-black text-slate-900 dark:text-white"
                >
                    Brand
                </label>
                <select
                    id={brandInputId}
                    name="brand"
                    defaultValue={filters.brand ?? ''}
                    className="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-sm transition outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/15 dark:border-slate-700 dark:bg-slate-950 dark:focus:bg-slate-900"
                >
                    <option value="">All brands</option>
                    {brands.map((brand) => (
                        <option key={brand.id} value={brand.slug}>
                            {brand.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="space-y-3">
                <span className="text-sm font-black text-slate-900 dark:text-white">
                    Price range
                </span>
                <div className="grid grid-cols-2 gap-2">
                    <label>
                        <span className="sr-only">Minimum price</span>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            name="min_price"
                            defaultValue={filters.min_price ?? ''}
                            placeholder="Min"
                            className="h-11 rounded-xl bg-slate-50 dark:bg-slate-950"
                        />
                    </label>
                    <label>
                        <span className="sr-only">Maximum price</span>
                        <Input
                            type="number"
                            min="0"
                            step="0.01"
                            name="max_price"
                            defaultValue={filters.max_price ?? ''}
                            placeholder="Max"
                            className="h-11 rounded-xl bg-slate-50 dark:bg-slate-950"
                        />
                    </label>
                </div>
            </div>

            <div className="space-y-3">
                <label
                    htmlFor={locationInputId}
                    className="text-sm font-black text-slate-900 dark:text-white"
                >
                    Location
                </label>
                <Input
                    id={locationInputId}
                    name="location"
                    defaultValue={filters.location ?? ''}
                    placeholder="e.g. Colombo"
                    className="h-11 rounded-xl bg-slate-50 dark:bg-slate-950"
                />
            </div>

            <div className="grid grid-cols-2 gap-2 pt-1">
                <Button type="submit" className="h-11 rounded-xl font-bold">
                    Apply filters
                </Button>
                <Button asChild variant="outline" className="h-11 rounded-xl">
                    <Link href={listingsIndex({ query: resetQuery })}>
                        <RotateCcw className="size-4" />
                        Reset
                    </Link>
                </Button>
            </div>
        </Form>
    );
}
