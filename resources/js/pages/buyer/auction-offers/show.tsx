import { Form, Head, Link } from '@inertiajs/react';
import { accept } from '@/actions/App/Http/Controllers/BuyerAuctionOfferController';
import { BuyerPortalLayout } from '@/components/buyer-portal-layout';
import { show as orderShow } from '@/routes/buyer/orders';
import type { AuctionOffer } from '@/types';

export default function AuctionOfferDetails({
    offer,
}: {
    offer: AuctionOffer;
}) {
    const total = Number(offer.unit_price) * offer.quantity;

    return (
        <BuyerPortalLayout title="Auction offer">
            <Head title={`Auction Offer · ${offer.auction.listing.title}`} />
            <div className="mx-auto max-w-3xl">
                <p className="text-sm font-semibold text-primary capitalize">
                    {offer.status}
                </p>
                <h1 className="mt-1 text-2xl font-bold">
                    {offer.auction.listing.title}
                </h1>
                <div className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-sm text-slate-500">
                        Your fixed auction price
                    </p>
                    <p className="mt-1 text-3xl font-bold">
                        LKR {offer.unit_price}{' '}
                        <span className="text-sm font-normal text-slate-500">
                            per unit
                        </span>
                    </p>
                    <p className="mt-2 text-sm">
                        {offer.quantity} unit(s) · LKR {total.toFixed(2)} before
                        shipping
                    </p>
                    {offer.expires_at && (
                        <p className="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                            Pay before{' '}
                            {new Date(offer.expires_at).toLocaleString()} or the
                            offer passes to the next bidder.
                        </p>
                    )}
                </div>
                {offer.order ? (
                    <Link
                        href={orderShow(offer.order.number)}
                        className="mt-6 inline-flex rounded-xl bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground"
                    >
                        View order and payment
                    </Link>
                ) : offer.status === 'offered' ? (
                    <Form
                        {...accept.form(offer.id)}
                        className="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
                    >
                        {({ errors, processing }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="payment_method"
                                    value="stripe"
                                />
                                <h2 className="text-lg font-semibold">
                                    Delivery details
                                </h2>
                                <AddressField
                                    name="recipient_name"
                                    label="Recipient name"
                                    error={errors.recipient_name}
                                />
                                <AddressField
                                    name="address_line_one"
                                    label="Address"
                                    error={errors.address_line_one}
                                />
                                <AddressField
                                    name="address_line_two"
                                    label="Address line 2 (optional)"
                                    error={errors.address_line_two}
                                />
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <AddressField
                                        name="city"
                                        label="City"
                                        error={errors.city}
                                    />
                                    <AddressField
                                        name="postal_code"
                                        label="Postal code"
                                        error={errors.postal_code}
                                    />
                                </div>
                                <AddressField
                                    name="phone"
                                    label="Phone"
                                    error={errors.phone}
                                />
                                <input
                                    type="hidden"
                                    name="billing_address"
                                    value="shipping"
                                />
                                <p className="text-sm text-slate-500">
                                    Card payment via Stripe only. COD and bank
                                    transfer are not available for auctions.
                                </p>
                                <button
                                    disabled={processing}
                                    className="min-h-11 rounded-xl bg-primary px-6 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                                >
                                    {processing
                                        ? 'Opening payment…'
                                        : 'Accept offer and pay by card'}
                                </button>
                            </>
                        )}
                    </Form>
                ) : null}
            </div>
        </BuyerPortalLayout>
    );
}

function AddressField({
    name,
    label,
    error,
}: {
    name: string;
    label: string;
    error?: string;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            {label}
            <input
                name={name}
                className="min-h-11 rounded-xl border border-slate-300 bg-white px-3 text-base dark:border-slate-700 dark:bg-slate-950"
            />
            {error && <span className="text-sm text-red-600">{error}</span>}
        </label>
    );
}
