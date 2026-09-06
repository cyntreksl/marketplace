import { useEffect, useState } from 'react';
import {
    readConsent,
    revokeConsent,
    saveConsent,
    trackPageView,
} from '@/lib/tracking';
import type { ConsentState } from '@/lib/tracking';

export function ConsentManager() {
    const [consent, setConsent] = useState<ConsentState | null>(null);
    const [ready, setReady] = useState(false);
    const [managing, setManaging] = useState(false);
    const [analytics, setAnalytics] = useState(false);
    const [marketing, setMarketing] = useState(false);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            const current = readConsent();
            setConsent(current);
            setAnalytics(current?.analytics ?? false);
            setMarketing(current?.marketing ?? false);
            setReady(true);
        }, 0);

        return () => window.clearTimeout(timeout);
    }, []);

    const choose = (nextAnalytics: boolean, nextMarketing: boolean) => {
        const hadVendorConsent = Boolean(
            readConsent()?.analytics || readConsent()?.marketing,
        );

        if (hadVendorConsent && !nextAnalytics && !nextMarketing) {
            revokeConsent();

            return;
        }

        const next = saveConsent(nextAnalytics, nextMarketing);
        setConsent(next);
        setAnalytics(next.analytics);
        setMarketing(next.marketing);
        setManaging(false);
        trackPageView(window.location.href);
    };

    if (!ready) {
        return null;
    }

    if (consent && !managing) {
        return (
            <button
                type="button"
                onClick={() => setManaging(true)}
                className="fixed bottom-3 left-3 z-50 rounded-full border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-700 shadow-lg hover:border-orange-400 hover:text-orange-700"
            >
                Cookie preferences
            </button>
        );
    }

    return (
        <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="consent-title"
            className="fixed inset-x-3 bottom-3 z-50 mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl sm:p-6"
        >
            <h2
                id="consent-title"
                className="text-xl font-black text-slate-950"
            >
                Your privacy choices
            </h2>
            <p className="mt-2 text-base leading-6 text-slate-600">
                Required cookies keep ProDeals secure. With your permission,
                analytics helps us improve shopping and marketing helps measure
                relevant campaigns.
            </p>

            {managing && (
                <div className="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4">
                    <label className="flex items-start gap-3 text-base text-slate-700">
                        <input
                            type="checkbox"
                            checked={analytics}
                            onChange={(event) =>
                                setAnalytics(event.target.checked)
                            }
                            className="mt-1 size-4 accent-orange-600"
                        />
                        <span>
                            <strong className="block text-slate-950">
                                Analytics
                            </strong>
                            GA4 and Microsoft Clarity performance measurement.
                        </span>
                    </label>
                    <label className="flex items-start gap-3 text-base text-slate-700">
                        <input
                            type="checkbox"
                            checked={marketing}
                            onChange={(event) =>
                                setMarketing(event.target.checked)
                            }
                            className="mt-1 size-4 accent-orange-600"
                        />
                        <span>
                            <strong className="block text-slate-950">
                                Marketing
                            </strong>
                            Meta Pixel campaign and conversion measurement.
                        </span>
                    </label>
                </div>
            )}

            <div className="mt-5 grid gap-2 sm:grid-cols-3">
                <button
                    type="button"
                    onClick={() => choose(true, true)}
                    className="min-h-11 rounded-xl bg-orange-600 px-4 text-sm font-black text-white hover:bg-orange-700"
                >
                    Accept all
                </button>
                <button
                    type="button"
                    onClick={() => choose(false, false)}
                    className="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-black text-slate-800 hover:border-slate-500"
                >
                    Reject all
                </button>
                {managing ? (
                    <button
                        type="button"
                        onClick={() => choose(analytics, marketing)}
                        className="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-black text-slate-800 hover:border-orange-500"
                    >
                        Save preferences
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={() => setManaging(true)}
                        className="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-black text-slate-800 hover:border-orange-500"
                    >
                        Manage preferences
                    </button>
                )}
            </div>
        </section>
    );
}
