export type CheckoutCartItem = {
    id: number;
    quantity: number;
    variant: {
        sku: string;
        selling_price: string | null;
        option_values: { value: string; option: { name: string } }[];
    } | null;
    listing: {
        title: string;
        price: string;
        sale_price: string | null;
        media: {
            cardUrl?: string;
            card2xUrl?: string;
            url?: string;
        }[];
        seller_profile: { store_name: string };
    };
};

export type CheckoutCart = {
    items: CheckoutCartItem[];
};

export type CheckoutPaymentMethod = 'stripe' | 'bank_transfer' | 'cod';

export type ShippingAddress = {
    recipient_name: string;
    address_line_one: string;
    address_line_two: string | null;
    city: string;
    postal_code: string | null;
    phone: string;
};
