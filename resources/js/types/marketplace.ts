export type MarketplaceDetails = {
    legal_entity: {
        name: string;
        company_number: string;
        registered_office: string;
        companies_house_url: string;
    };
    support: {
        email: string;
        phone: string | null;
        privacy_email: string;
        hours: string;
        days: string;
        timezone: string;
    };
    legal_effective_date: string;
    payment_methods: string[];
    social_urls: Record<string, string | null>;
    storefront: {
        currency: 'LKR';
        delivery_locations: string[];
        newsletter_url: string | null;
        google_play_url: string | null;
        app_store_url: string | null;
        installments_url: string | null;
        business_deals_url: string | null;
    };
};
