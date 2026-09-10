export type ConsentState = {
    version: 1;
    analytics: boolean;
    marketing: boolean;
    decidedAt: string;
};

type DataLayerValue = Record<string, unknown> | unknown[];

declare global {
    interface Window {
        dataLayer: DataLayerValue[];
        gtag: (...args: unknown[]) => void;
    }
}

export const consentCookieName = 'prodeals_consent';
export const consentVersion = 1;

const containerId = import.meta.env.VITE_GTM_CONTAINER_ID as string | undefined;
let lastPageViewUrl: string | null = null;
let consentDefaultsInitialized = false;

function hasWindow(): boolean {
    return typeof window !== 'undefined' && typeof document !== 'undefined';
}

function ensureConsentDefaults(): void {
    if (!hasWindow() || consentDefaultsInitialized) {
        return;
    }

    consentDefaultsInitialized = true;
    window.dataLayer = window.dataLayer ?? [];
    window.gtag =
        window.gtag ?? ((...args: unknown[]) => window.dataLayer.push(args));
    window.gtag('consent', 'default', {
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        analytics_storage: 'denied',
        functionality_storage: 'granted',
        security_storage: 'granted',
        wait_for_update: 500,
    });
}

function isValidConsent(value: unknown): value is ConsentState {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const consent = value as Partial<ConsentState>;

    return (
        consent.version === consentVersion &&
        typeof consent.analytics === 'boolean' &&
        typeof consent.marketing === 'boolean' &&
        typeof consent.decidedAt === 'string'
    );
}

export function readConsent(): ConsentState | null {
    if (!hasWindow()) {
        return null;
    }

    const value = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith(`${consentCookieName}=`))
        ?.slice(consentCookieName.length + 1);

    if (!value) {
        return null;
    }

    try {
        const consent: unknown = JSON.parse(decodeURIComponent(value));

        return isValidConsent(consent) ? consent : null;
    } catch {
        return null;
    }
}

function writeConsent(consent: ConsentState): void {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${consentCookieName}=${encodeURIComponent(JSON.stringify(consent))}; Path=/; Max-Age=31536000; SameSite=Lax${secure}`;
}

function loadGtm(): void {
    if (
        !hasWindow() ||
        !containerId?.match(/^GTM-[A-Z0-9]+$/) ||
        document.querySelector('script[data-prodeals-gtm]')
    ) {
        return;
    }

    window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
    const script = document.createElement('script');
    script.async = true;
    script.dataset.prodealsGtm = containerId;
    script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(containerId)}`;
    document.head.append(script);
}

function updateConsent(consent: ConsentState): void {
    ensureConsentDefaults();
    window.gtag('consent', 'update', {
        analytics_storage: consent.analytics ? 'granted' : 'denied',
        ad_storage: consent.marketing ? 'granted' : 'denied',
        ad_user_data: consent.marketing ? 'granted' : 'denied',
        ad_personalization: consent.marketing ? 'granted' : 'denied',
    });

    if (consent.analytics || consent.marketing) {
        loadGtm();
    }
}

export function initializeTracking(): ConsentState | null {
    if (!hasWindow()) {
        return null;
    }

    ensureConsentDefaults();
    const consent = readConsent();

    if (consent) {
        updateConsent(consent);
    }

    return consent;
}

export function saveConsent(
    analytics: boolean,
    marketing: boolean,
): ConsentState {
    const consent: ConsentState = {
        version: consentVersion,
        analytics,
        marketing,
        decidedAt: new Date().toISOString(),
    };

    writeConsent(consent);
    updateConsent(consent);

    return consent;
}

export function revokeConsent(): void {
    if (!hasWindow()) {
        return;
    }

    saveConsent(false, false);
    const cookieNames = document.cookie
        .split('; ')
        .map((entry) => entry.split('=')[0])
        .filter(
            (name) =>
                [
                    '_ga',
                    '_gid',
                    '_gat',
                    '_gcl_au',
                    '_fbp',
                    '_fbc',
                    '_clck',
                    '_clsk',
                ].includes(name) || name.startsWith('_ga_'),
        );
    const hostParts = window.location.hostname.split('.');
    const domains = [
        '',
        window.location.hostname,
        `.${hostParts.slice(-2).join('.')}`,
    ];

    for (const name of cookieNames) {
        for (const domain of domains) {
            document.cookie = `${name}=; Path=/; Max-Age=0; SameSite=Lax${domain ? `; Domain=${domain}` : ''}`;
        }
    }

    window.location.reload();
}

export function trackEvent(
    event: string,
    parameters: Record<string, unknown> = {},
    category: 'analytics' | 'marketing' | 'either' = 'either',
): void {
    if (!hasWindow()) {
        return;
    }

    const consent = readConsent();
    const permitted =
        category === 'analytics'
            ? consent?.analytics
            : category === 'marketing'
              ? consent?.marketing
              : consent?.analytics || consent?.marketing;

    if (!permitted) {
        return;
    }

    ensureConsentDefaults();
    window.dataLayer.push({ event, eventModel: parameters });
}

export function buildCatalogItem(
    listingId: number,
    listingVariantId: number | null | undefined,
    parameters: Record<string, unknown> = {},
): Record<string, unknown> {
    return {
        ...parameters,
        item_id: String(listingVariantId ?? listingId),
        item_group_id: String(listingId),
    };
}

export function withMetaEventId(
    parameters: Record<string, unknown>,
    eventId: string | null | undefined,
): Record<string, unknown> {
    return eventId ? { ...parameters, event_id: eventId } : parameters;
}

export function trackPageView(url: string): void {
    const consent = readConsent();

    if (!hasWindow() || (!consent?.analytics && !consent?.marketing)) {
        return;
    }

    const absoluteUrl = new URL(url, window.location.origin).toString();

    if (lastPageViewUrl === absoluteUrl) {
        return;
    }

    lastPageViewUrl = absoluteUrl;
    trackEvent('page_view', {
        page_location: absoluteUrl,
        page_path: new URL(absoluteUrl).pathname,
        page_title: document.title,
    });
}

export function trackPurchase(
    transactionId: string,
    parameters: Record<string, unknown>,
): void {
    if (!hasWindow()) {
        return;
    }

    const storageKey = `prodeals.purchase.${transactionId}`;

    if (window.sessionStorage.getItem(storageKey)) {
        return;
    }

    trackEvent('purchase', {
        ...parameters,
        event_id: `Purchase:${transactionId}`,
        transaction_id: transactionId,
    });

    const consent = readConsent();

    if (consent?.analytics || consent?.marketing) {
        window.sessionStorage.setItem(storageKey, '1');
    }
}
