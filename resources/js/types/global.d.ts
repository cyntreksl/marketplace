import type { SeoPayload } from '@/components/seo-head';
import type { Auth } from '@/types/auth';
import type { MarketplaceDetails } from '@/types/marketplace';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            commerce: { cart_quantity: number; wishlist_count: number };
            marketplace: MarketplaceDetails;
            seo: SeoPayload;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
