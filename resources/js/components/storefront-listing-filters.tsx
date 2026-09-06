import { Form, Link } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
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
    const priceLimit = 1_000_000;
    const initialMinimumPrice = Math.min(
        Number(filters.min_price) || 0,
        priceLimit,
    );
    const initialMaximumPrice = Math.min(
        Number(filters.max_price) || priceLimit,
        priceLimit,
    );
    const [minimumPrice, setMinimumPrice] = useState(
        Math.min(initialMinimumPrice, initialMaximumPrice),
    );
    const [maximumPrice, setMaximumPrice] = useState(
        Math.max(initialMinimumPrice, initialMaximumPrice),
    );
    const resetQuery = {
        ...(filters.search ? { search: filters.search } : {}),
        ...(filters.category ? { category: filters.category } : {}),
    };
    const priceTrackStyle = {
        background: `linear-gradient(to right, #e2e8f0 ${(minimumPrice / priceLimit) * 100}%, #ff6d00 ${(minimumPrice / priceLimit) * 100}%, #ff6d00 ${(maximumPrice / priceLimit) * 100}%, #e2e8f0 ${(maximumPrice / priceLimit) * 100}%)`,
    };
    const formatPrice = (price: number) =>
        new Intl.NumberFormat('en-LK', {
            maximumFractionDigits: 0,
        }).format(price);

    return (
        <Form {...listingsIndex.form()} className={`space-y-7 ${className}`}>
            <HiddenBrowseContext filters={filters} />

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

            <fieldset className="space-y-5">
                <div className="flex items-center justify-between gap-4">
                    <legend className="text-sm font-black text-slate-900 dark:text-white">
                        Price range
                    </legend>
                    <span className="text-xs font-semibold text-slate-400">
                        LKR
                    </span>
                </div>
                <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                    <label className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        <span className="block text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                            Minimum
                        </span>
                        <span className="mt-0.5 block text-sm font-bold text-slate-900 dark:text-white">
                            Rs. {formatPrice(minimumPrice)}
                        </span>
                        <input
                            type="hidden"
                            name="min_price"
                            value={minimumPrice || ''}
                        />
                    </label>
                    <span className="h-px w-3 bg-slate-300" />
                    <label className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                        <span className="block text-[10px] font-bold tracking-wide text-slate-400 uppercase">
                            Maximum
                        </span>
                        <span className="mt-0.5 block text-sm font-bold text-slate-900 dark:text-white">
                            Rs. {formatPrice(maximumPrice)}
                        </span>
                        <input
                            type="hidden"
                            name="max_price"
                            value={
                                maximumPrice === priceLimit ? '' : maximumPrice
                            }
                        />
                    </label>
                </div>
                <div className="relative h-7">
                    <div
                        className="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 rounded-full"
                        style={priceTrackStyle}
                    />
                    <input
                        aria-label="Minimum price"
                        type="range"
                        min="0"
                        max={priceLimit}
                        step="5000"
                        value={minimumPrice}
                        onChange={(event) =>
                            setMinimumPrice(
                                Math.min(
                                    Number(event.target.value),
                                    maximumPrice - 5000,
                                ),
                            )
                        }
                        className="pointer-events-none absolute inset-x-0 top-1/2 z-10 h-1 w-full -translate-y-1/2 appearance-none bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-5 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:bg-[#FF6D00] [&::-moz-range-thumb]:shadow-md [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-[#FF6D00] [&::-webkit-slider-thumb]:shadow-md"
                    />
                    <input
                        aria-label="Maximum price"
                        type="range"
                        min="0"
                        max={priceLimit}
                        step="5000"
                        value={maximumPrice}
                        onChange={(event) =>
                            setMaximumPrice(
                                Math.max(
                                    Number(event.target.value),
                                    minimumPrice + 5000,
                                ),
                            )
                        }
                        className="pointer-events-none absolute inset-x-0 top-1/2 z-20 h-1 w-full -translate-y-1/2 appearance-none bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-5 [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:bg-[#FF6D00] [&::-moz-range-thumb]:shadow-md [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-5 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-[#FF6D00] [&::-webkit-slider-thumb]:shadow-md"
                    />
                </div>
                <div className="flex justify-between text-[11px] font-semibold text-slate-400">
                    <span>Rs. 0</span>
                    <span>Rs. 1,000,000+</span>
                </div>
            </fieldset>

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
