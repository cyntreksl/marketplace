import { Form, Head } from '@inertiajs/react';
import { update } from '@/actions/App/Http/Controllers/AdminAuctionSettingsController';
import { PortalLayout } from '@/components/portal-layout';
import type { AuctionFlags } from '@/types';

export default function AuctionSettings({ flags }: { flags: AuctionFlags }) {
    return (
        <PortalLayout portal="admin" title="Auction settings">
            <Head title="Auction Settings" />
            <div className="mx-auto max-w-3xl">
                <h1 className="text-2xl font-bold">Auction feature flags</h1>
                <p className="mt-1 text-sm text-slate-500">
                    Disabling flags blocks discovery, creation, and bidding.
                    Existing offers, payment, and fulfillment continue.
                </p>
                <Form
                    {...update.form()}
                    className="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
                >
                    {({ processing }) => (
                        <>
                            <Toggle
                                name="enabled"
                                label="Enable auctions"
                                description="Master switch for all new auction activity."
                                checked={flags.enabled}
                            />
                            <hr className="border-slate-200 dark:border-slate-800" />
                            <Toggle
                                name="types[normal]"
                                label="Normal auctions"
                                description="Public current bid with direct bidding."
                                checked={flags.types.normal}
                            />
                            <Toggle
                                name="types[blind]"
                                label="Blind auctions"
                                description="Hide live bid values and bidder identities."
                                checked={flags.types.blind}
                            />
                            <Toggle
                                name="types[time_extended]"
                                label="Time-extended auctions"
                                description="Restart the rolling close window after a late bid."
                                checked={flags.types.time_extended}
                            />
                            <button
                                disabled={processing}
                                className="mt-2 min-h-11 justify-self-start rounded-xl bg-primary px-6 text-sm font-semibold text-primary-foreground"
                            >
                                Save auction settings
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </PortalLayout>
    );
}

function Toggle({
    name,
    label,
    description,
    checked,
}: {
    name: string;
    label: string;
    description: string;
    checked: boolean;
}) {
    return (
        <label className="flex items-start justify-between gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
            <span>
                <span className="block font-semibold">{label}</span>
                <span className="mt-1 block text-sm text-slate-500">
                    {description}
                </span>
            </span>
            <input type="hidden" name={name} value="0" />
            <input
                type="checkbox"
                name={name}
                value="1"
                defaultChecked={checked}
                className="mt-1 size-5 accent-primary"
            />
        </label>
    );
}
