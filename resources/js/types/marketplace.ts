export type MarketplaceDetails = {
    legal_entity: {
        name: string;
        company_number: string;
        registered_office: string;
        companies_house_url: string;
    };
    support: {
        email: string;
        privacy_email: string;
        hours: string;
        days: string;
        timezone: string;
    };
    legal_effective_date: string;
    payment_methods: string[];
    social_urls: Record<string, string | null>;
};
