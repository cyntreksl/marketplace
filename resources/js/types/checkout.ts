export type CheckoutCartItem = {
    id: number | string;
    quantity: number;
    unitPrice: string;
    total: string;
    error: string | null;
    availableQuantity: number;
    variant: {
        sku: string;
        selling_price: string | null;
        option_values: { value: string; option: { name: string } }[];
    } | null;
    listing: {
        slug: string | null;
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
    subtotal: string;
    shippingTotal: string;
    total: string;
    quantity: number;
    canCheckout: boolean;
    paymentMethods: CheckoutPaymentMethod[];
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

export type CheckoutConfirmationItem = {
    id: number;
    title: string;
    seller: string;
    variantSku: string | null;
    variantOptions: Record<string, string> | null;
    quantity: number;
    unitPrice: string;
    total: string;
};

export type CheckoutConfirmationOrder = {
    number: string;
    status: string;
    placedAt: string | null;
    subtotal: string;
    shippingTotal: string;
    total: string;
    shippingAddress: ShippingAddress;
    billingAddress: ShippingAddress;
    payment: {
        method: CheckoutPaymentMethod;
        status: string;
        amount: string;
    } | null;
    items: CheckoutConfirmationItem[];
};
