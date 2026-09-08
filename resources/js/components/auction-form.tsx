import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import type {
    AuctionFlags,
    AuctionRecord,
    SellerAuctionListing,
} from '@/types';

type FormTarget = { action: string; method: 'post' | 'put' };

function dateTimeValue(value?: string): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

export function AuctionForm({
    action,
    listings,
    flags,
    auction,
    defaults,
}: {
    action: FormTarget;
    listings: SellerAuctionListing[];
    flags: AuctionFlags;
    auction?: AuctionRecord;
    defaults: {
        durationDays: number;
        extensionMinutes: number;
        startsAt: string;
        endsAt: string;
    };
}) {
    const initialListingId = auction?.listing.id ?? listings[0]?.id ?? 0;
    const [listingId, setListingId] = useState(initialListingId);
    const [type, setType] = useState(auction?.type ?? 'normal');
    const selectedListing = useMemo(
        () => listings.find((listing) => listing.id === listingId),
        [listingId, listings],
    );

    return (
        <Form {...action} className="grid gap-6">
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        {!flags.enabled && (
                            <p className="rounded-xl bg-amber-50 p-4 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                                New auctions are currently disabled by an
                                administrator.
                            </p>
                        )}
                        <label className="grid gap-2 text-sm font-medium">
                            Product
                            {auction && (
                                <input
                                    type="hidden"
                                    name="listing_id"
                                    value={auction.listing.id}
                                />
                            )}
                            <select
                                name="listing_id"
                                value={listingId}
                                onChange={(event) =>
                                    setListingId(Number(event.target.value))
                                }
                                disabled={auction !== undefined}
                                className="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-base disabled:opacity-70 dark:border-slate-700 dark:bg-slate-950"
                            >
                                {listings.map((listing) => (
                                    <option key={listing.id} value={listing.id}>
                                        {listing.title} ({listing.status})
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.listing_id} />
                        </label>

                        {selectedListing?.product_type === 'variant' && (
                            <label className="grid gap-2 text-sm font-medium">
                                Variant
                                <select
                                    name="listing_variant_id"
                                    defaultValue={auction?.variant?.id ?? ''}
                                    className="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-base dark:border-slate-700 dark:bg-slate-950"
                                >
                                    <option value="">Choose a variant</option>
                                    {selectedListing.variants.map((variant) => (
                                        <option
                                            key={variant.id}
                                            value={variant.id}
                                        >
                                            {variant.option_values
                                                ?.map(
                                                    (value) =>
                                                        `${value.option.name}: ${value.value}`,
                                                )
                                                .join(' · ') || variant.sku}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.listing_variant_id}
                                />
                            </label>
                        )}

                        <fieldset className="grid gap-3">
                            <legend className="text-sm font-medium">
                                Auction type
                            </legend>
                            <div className="grid gap-3 md:grid-cols-3">
                                {(
                                    [
                                        [
                                            'normal',
                                            'Normal',
                                            'Bidders see the current price.',
                                        ],
                                        [
                                            'blind',
                                            'Blind',
                                            'Live bids and bidder identities stay private.',
                                        ],
                                        [
                                            'time_extended',
                                            'Time extended',
                                            'Late bids restart the closing window.',
                                        ],
                                    ] as const
                                ).map(([value, label, help]) => (
                                    <label
                                        key={value}
                                        className="rounded-xl border border-slate-200 p-4 text-sm dark:border-slate-700"
                                    >
                                        <span className="flex items-center gap-2 font-semibold">
                                            <input
                                                type="radio"
                                                name="type"
                                                value={value}
                                                checked={type === value}
                                                disabled={!flags.types[value]}
                                                onChange={() => setType(value)}
                                            />
                                            {label}
                                        </span>
                                        <span className="mt-2 block text-slate-500 dark:text-slate-400">
                                            {help}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.type} />
                        </fieldset>

                        <div className="grid gap-5 md:grid-cols-3">
                            <Field
                                label="Lot quantity"
                                name="quantity"
                                type="number"
                                min="1"
                                defaultValue={auction?.quantity ?? 1}
                                error={errors.quantity}
                            />
                            <Field
                                label="Starting price per unit (LKR)"
                                name="starting_price"
                                type="number"
                                min="1"
                                step="0.01"
                                defaultValue={auction?.starting_price ?? ''}
                                error={errors.starting_price}
                            />
                            <Field
                                label="Minimum increment (LKR)"
                                name="minimum_increment"
                                type="number"
                                min="1"
                                step="0.01"
                                defaultValue={auction?.minimum_increment ?? ''}
                                error={errors.minimum_increment}
                            />
                        </div>
                        {type === 'time_extended' && (
                            <Field
                                label="Rolling extension window (minutes)"
                                name="extension_window_minutes"
                                type="number"
                                min="1"
                                max="60"
                                defaultValue={
                                    auction?.extension_window_minutes ??
                                    defaults.extensionMinutes
                                }
                                error={errors.extension_window_minutes}
                            />
                        )}
                        <div className="grid gap-5 md:grid-cols-2">
                            <Field
                                label="Starts at"
                                name="starts_at"
                                type="datetime-local"
                                defaultValue={dateTimeValue(
                                    auction?.starts_at ?? defaults.startsAt,
                                )}
                                error={errors.starts_at}
                            />
                            <Field
                                label="Ends at"
                                name="ends_at"
                                type="datetime-local"
                                defaultValue={dateTimeValue(
                                    auction?.ends_at ?? defaults.endsAt,
                                )}
                                error={errors.ends_at}
                            />
                        </div>
                    </div>
                    <button
                        disabled={
                            processing ||
                            !flags.enabled ||
                            listings.length === 0
                        }
                        className="min-h-11 justify-self-start rounded-xl bg-primary px-6 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                    >
                        {processing
                            ? 'Saving…'
                            : auction
                              ? 'Update auction draft'
                              : 'Create auction'}
                    </button>
                </>
            )}
        </Form>
    );
}

function Field({
    label,
    error,
    ...props
}: React.InputHTMLAttributes<HTMLInputElement> & {
    label: string;
    error?: string;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            {label}
            <input
                {...props}
                className="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-base dark:border-slate-700 dark:bg-slate-950"
            />
            <InputError message={error} />
        </label>
    );
}
