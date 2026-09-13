import type { CheckoutCart } from '@/types/checkout';

export type ConsentState = {
    version: 1;
    analytics: boolean;
    marketing: boolean;
    decidedAt: string;
};

type DataLayerValue = Record<string, unknown> | IArguments;

export type CommerceEventName =
    'page_view' | 'view_item' | 'add_to_cart' | 'begin_checkout' | 'purchase';

const commerceEvents: readonly CommerceEventName[] = [
    'page_view',
    'view_item',
    'add_to_cart',
    'begin_checkout',
    'purchase',
];

declare global {
    interface Window {
        dataLayer: DataLayerValue[];
        gtag: (...args: unknown[]) => void;
    }
}

export const consentCookieName = 'prodeals_consent';
export const consentVersion = 1;

const containerId = import.meta.env.VITE_GTM_CONTAINER_ID as string | undefined;
const pendingPurchasePrefix = 'prodeals.pending_purchase.';
let lastPageViewUrl: string | null = null;
let consentDefaultsInitialized = false;

function hasWindow(): boolean {
    return typeof window !== 'undefined' && typeof document !== 'undefined';
}

function isExcludedTrackingPath(pathname?: string): boolean {
    const currentPath =
        pathname ??
        (typeof window.location.pathname === 'string'
            ? window.location.pathname
            : new URL(window.location.href).pathname);

    return ['/admin', '/seller', '/settings', '/buyer/settings'].some(
        (prefix) =>
            currentPath === prefix || currentPath.startsWith(`${prefix}/`),
    );
}

function ensureConsentDefaults(): void {
    if (!hasWindow() || consentDefaultsInitialized) {
        return;
    }

    consentDefaultsInitialized = true;
    window.dataLayer = window.dataLayer ?? [];
    window.gtag =
        window.gtag ??
        function (): void {
            // GTM recognizes consent commands only when they use the standard Arguments object.
            // eslint-disable-next-line prefer-rest-params
            window.dataLayer.push(arguments);
        };
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
        isExcludedTrackingPath() ||
        document.documentElement.dataset.environment !== 'production' ||
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
        flushPendingPurchases();
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
    event: CommerceEventName,
    parameters: Record<string, unknown> = {},
    category: 'analytics' | 'marketing' | 'either' = 'either',
): boolean {
    if (
        !commerceEvents.includes(event) ||
        !hasWindow() ||
        isExcludedTrackingPath()
    ) {
        return false;
    }

    const consent = readConsent();
    const permitted =
        category === 'analytics'
            ? consent?.analytics
            : category === 'marketing'
              ? consent?.marketing
              : consent?.analytics || consent?.marketing;

    if (!permitted) {
        return false;
    }

    ensureConsentDefaults();
    loadGtm();
    const eventModel = normalizeEventModel(event, parameters);
    const eventId = eventModel.event_id;
    const deliveryKey =
        typeof eventId === 'string'
            ? `prodeals.event.${event}.${eventId}`
            : null;

    if (deliveryKey && window.sessionStorage.getItem(deliveryKey)) {
        return false;
    }

    window.dataLayer.push({ event, eventModel });

    if (deliveryKey) {
        window.sessionStorage.setItem(deliveryKey, '1');
    }

    return true;
}

function normalizeEventModel(
    event: CommerceEventName,
    parameters: Record<string, unknown>,
): Record<string, unknown> {
    const normalized: Record<string, unknown> = {};
    const stringKeys = [
        'event_id',
        'transaction_id',
        'page_location',
        'page_path',
        'page_title',
    ] as const;

    for (const key of stringKeys) {
        if (typeof parameters[key] === 'string') {
            normalized[key] = parameters[key];
        }
    }

    for (const key of ['value', 'shipping'] as const) {
        if (
            typeof parameters[key] === 'number' &&
            Number.isFinite(parameters[key])
        ) {
            normalized[key] = parameters[key];
        }
    }

    if (parameters.currency === 'LKR') {
        normalized.currency = 'LKR';
    }

    if (Array.isArray(parameters.items)) {
        normalized.items = parameters.items.map((item) => {
            if (!item || typeof item !== 'object') {
                return {};
            }

            const source = item as Record<string, unknown>;
            const result: Record<string, unknown> = {};

            for (const key of [
                'item_id',
                'item_group_id',
                'item_name',
                'item_brand',
                'item_category',
            ] as const) {
                if (typeof source[key] === 'string') {
                    result[key] = source[key];
                }
            }

            if (
                typeof source.price === 'number' &&
                Number.isFinite(source.price)
            ) {
                result.price = source.price;
            }

            if (
                typeof source.quantity === 'number' &&
                Number.isInteger(source.quantity) &&
                source.quantity > 0
            ) {
                result.quantity = source.quantity;
            }

            return result;
        });
    }

    if (event !== 'page_view') {
        normalized.currency = 'LKR';
    }

    return normalized;
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

export function buildCheckoutEventModel(
    cart: Pick<CheckoutCart, 'items' | 'total'>,
    parameters: Record<string, unknown> = {},
): Record<string, unknown> {
    return {
        currency: 'LKR',
        value: Number(cart.total),
        items: cart.items.map((item) =>
            buildCatalogItem(item.listing_id, item.listing_variant_id, {
                item_name: item.listing.title,
                price: Number(item.unitPrice),
                quantity: item.quantity,
            }),
        ),
        ...parameters,
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

    if (
        !hasWindow() ||
        isExcludedTrackingPath() ||
        (!consent?.analytics && !consent?.marketing)
    ) {
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

    const normalizedTransactionId = transactionId.trim();

    if (!normalizedTransactionId) {
        return;
    }

    const pendingKey = `${pendingPurchasePrefix}${normalizedTransactionId}`;
    const purchaseKey = `prodeals.purchase.${normalizedTransactionId}`;

    if (window.sessionStorage.getItem(purchaseKey)) {
        window.sessionStorage.removeItem(pendingKey);

        return;
    }

    const eventModel = normalizeEventModel('purchase', {
        ...parameters,
        event_id: `Purchase:${normalizedTransactionId}`,
        transaction_id: normalizedTransactionId,
    });

    window.sessionStorage.setItem(pendingKey, JSON.stringify(eventModel));
    deliverPendingPurchase(eventModel);
}

function flushPendingPurchases(): void {
    if (!hasWindow()) {
        return;
    }

    const pendingKeys: string[] = [];

    for (let index = 0; index < window.sessionStorage.length; index++) {
        const key = window.sessionStorage.key(index);

        if (key?.startsWith(pendingPurchasePrefix)) {
            pendingKeys.push(key);
        }
    }

    for (const key of pendingKeys) {
        const stored = window.sessionStorage.getItem(key);

        if (!stored) {
            continue;
        }

        try {
            const eventModel = JSON.parse(stored) as Record<string, unknown>;
            const transactionId = eventModel.transaction_id;

            if (
                typeof transactionId !== 'string' ||
                key !== `${pendingPurchasePrefix}${transactionId}` ||
                eventModel.event_id !== `Purchase:${transactionId}`
            ) {
                window.sessionStorage.removeItem(key);

                continue;
            }

            deliverPendingPurchase(normalizeEventModel('purchase', eventModel));
        } catch {
            window.sessionStorage.removeItem(key);
        }
    }
}

function deliverPendingPurchase(eventModel: Record<string, unknown>): void {
    const transactionId = eventModel.transaction_id;
    const eventId = eventModel.event_id;

    if (typeof transactionId !== 'string' || typeof eventId !== 'string') {
        return;
    }

    const pendingKey = `${pendingPurchasePrefix}${transactionId}`;
    const purchaseKey = `prodeals.purchase.${transactionId}`;

    trackEvent('purchase', eventModel);

    if (window.sessionStorage.getItem(`prodeals.event.purchase.${eventId}`)) {
        window.sessionStorage.setItem(purchaseKey, '1');
        window.sessionStorage.removeItem(pendingKey);
    }
}
