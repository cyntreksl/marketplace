import { Form, Head } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BuyerPageHeader } from '@/components/buyer-page-header';
import { BuyerPortalLayout } from '@/components/buyer-portal-layout';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    destroy,
    defaultMethod,
    store,
    update,
} from '@/routes/buyer/addresses';
import type { BuyerAddress } from '@/types';

function AddressFields({ address }: { address?: BuyerAddress }) {
    const fields: {
        name: keyof BuyerAddress;
        label: string;
        required?: boolean;
        className?: string;
    }[] = [
        { name: 'label', label: 'Label', required: true },
        { name: 'recipient_name', label: 'Recipient name', required: true },
        {
            name: 'address_line_one',
            label: 'Address line 1',
            required: true,
            className: 'sm:col-span-2',
        },
        {
            name: 'address_line_two',
            label: 'Address line 2',
            className: 'sm:col-span-2',
        },
        { name: 'city', label: 'City', required: true },
        { name: 'postal_code', label: 'Postal code' },
        { name: 'phone', label: 'Phone number', required: true },
    ];

    return (
        <>
            {fields.map((field) => (
                <label
                    key={field.name}
                    className={`grid gap-1 text-sm font-semibold ${field.className ?? ''}`}
                >
                    {field.label}
                    <input
                        name={field.name}
                        required={field.required}
                        defaultValue={
                            (address?.[field.name] as string | undefined) ?? ''
                        }
                        className="rounded-lg border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                    />
                </label>
            ))}
            <div className="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                <label className="flex items-center gap-2 text-sm font-semibold">
                    <input
                        type="checkbox"
                        name="shipping_enabled"
                        value="1"
                        defaultChecked={address?.shipping_enabled ?? true}
                    />{' '}
                    Use for shipping
                </label>
                <label className="flex items-center gap-2 text-sm font-semibold">
                    <input
                        type="checkbox"
                        name="billing_enabled"
                        value="1"
                        defaultChecked={address?.billing_enabled ?? false}
                    />{' '}
                    Use for billing
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="is_default_shipping"
                        value="1"
                        defaultChecked={address?.is_default_shipping}
                    />{' '}
                    Default shipping address
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="is_default_billing"
                        value="1"
                        defaultChecked={address?.is_default_billing}
                    />{' '}
                    Default billing address
                </label>
            </div>
        </>
    );
}

function AddressDialog({ address }: { address?: BuyerAddress }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={address ? 'outline' : 'default'}
                    className={
                        address
                            ? 'rounded-xl'
                            : 'rounded-xl bg-orange-600 text-white hover:bg-orange-700'
                    }
                >
                    {address ? (
                        <>
                            <Pencil className="size-4" /> Edit
                        </>
                    ) : (
                        <>
                            <Plus className="size-4" /> Add address
                        </>
                    )}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {address ? 'Edit address' : 'Add a saved address'}
                    </DialogTitle>
                    <DialogDescription>
                        Choose whether this address can be used for shipping,
                        billing, or both.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...(address ? update.form(address.id) : store.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4 sm:grid-cols-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <AddressFields address={address} />
                            {Object.values(errors).map((error) => (
                                <p
                                    key={error}
                                    className="text-xs text-red-600 sm:col-span-2"
                                >
                                    {error}
                                </p>
                            ))}
                            <Button
                                disabled={processing}
                                className="rounded-xl bg-orange-600 text-white sm:col-span-2"
                            >
                                {processing ? 'Saving…' : 'Save address'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function BuyerAddresses({
    addresses,
}: {
    addresses: BuyerAddress[];
}) {
    return (
        <BuyerPortalLayout title="Addresses">
            <Head title="Addresses" />
            <div className="space-y-7">
                <BuyerPageHeader
                    eyebrow="Saved details"
                    title="Shipping & billing addresses"
                    description="Keep addresses ready for checkout. You can use one address for both purposes and choose each default independently."
                    actions={<AddressDialog />}
                />
                {addresses.length === 0 ? (
                    <div className="grid min-h-72 place-items-center rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
                        <div>
                            <MapPin className="mx-auto size-10 text-orange-600" />
                            <h2 className="mt-4 font-black">
                                No saved addresses
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                Add one now to make checkout faster.
                            </p>
                            <div className="mt-5">
                                <AddressDialog />
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {addresses.map((address) => (
                            <article
                                key={address.id}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-black">
                                            {address.label}
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {address.is_default_shipping && (
                                                <span className="rounded-full bg-orange-100 px-2 py-1 text-xs font-bold text-orange-800">
                                                    Default shipping
                                                </span>
                                            )}
                                            {address.is_default_billing && (
                                                <span className="rounded-full bg-violet-100 px-2 py-1 text-xs font-bold text-violet-800">
                                                    Default billing
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <MapPin className="size-5 text-orange-600" />
                                </div>
                                <address className="mt-4 text-sm leading-6 text-slate-600 not-italic dark:text-slate-300">
                                    <p className="font-bold text-slate-900 dark:text-white">
                                        {address.recipient_name}
                                    </p>
                                    <p>{address.address_line_one}</p>
                                    {address.address_line_two && (
                                        <p>{address.address_line_two}</p>
                                    )}
                                    <p>
                                        {address.city}
                                        {address.postal_code
                                            ? ` ${address.postal_code}`
                                            : ''}
                                    </p>
                                    <p>{address.phone}</p>
                                </address>
                                <div className="mt-5 flex flex-wrap gap-2">
                                    <AddressDialog address={address} />
                                    {address.shipping_enabled &&
                                        !address.is_default_shipping && (
                                            <Form
                                                {...defaultMethod.form(
                                                    address.id,
                                                )}
                                            >
                                                <input
                                                    type="hidden"
                                                    name="purpose"
                                                    value="shipping"
                                                />
                                                <Button
                                                    variant="outline"
                                                    className="rounded-xl"
                                                >
                                                    Make shipping default
                                                </Button>
                                            </Form>
                                        )}
                                    {address.billing_enabled &&
                                        !address.is_default_billing && (
                                            <Form
                                                {...defaultMethod.form(
                                                    address.id,
                                                )}
                                            >
                                                <input
                                                    type="hidden"
                                                    name="purpose"
                                                    value="billing"
                                                />
                                                <Button
                                                    variant="outline"
                                                    className="rounded-xl"
                                                >
                                                    Make billing default
                                                </Button>
                                            </Form>
                                        )}
                                    <Form
                                        {...destroy.form(address.id)}
                                        options={{ preserveScroll: true }}
                                        className="ml-auto"
                                    >
                                        <Button
                                            variant="ghost"
                                            className="rounded-xl text-red-600"
                                            aria-label={`Delete ${address.label}`}
                                        >
                                            <Trash2 className="size-4" /> Delete
                                        </Button>
                                    </Form>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </BuyerPortalLayout>
    );
}
