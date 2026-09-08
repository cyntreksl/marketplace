export type BuyerOrderStage =
    'all' | 'to_pay' | 'processing' | 'shipped' | 'completed' | 'archived';

export type BuyerAddress = {
    id: number;
    label: string;
    recipient_name: string;
    address_line_one: string;
    address_line_two: string | null;
    city: string;
    postal_code: string | null;
    phone: string;
    shipping_enabled: boolean;
    billing_enabled: boolean;
    is_default_shipping: boolean;
    is_default_billing: boolean;
};

export type PaymentAttempt = {
    id: number;
    attempt_number: number;
    status: 'pending' | 'succeeded' | 'failed' | 'expired';
    status_label: string;
    attempted_at: string | null;
    resolved_at: string | null;
    failure_summary: string | null;
    method: string;
    amount: string;
    order_number: string;
    order_status: string;
};

export type BuyerOrderItem = {
    id: number;
    title: string;
    quantity: number;
    unit_price: string;
    total: string;
    variant_sku: string | null;
    variant_options: Record<string, string> | null;
    pricing_tier: 'retail' | 'wholesale';
    listing_slug: string | null;
    image_url: string | null;
    review: { rating: number; comment: string | null } | null;
    can_review: boolean;
    can_return: boolean;
};

export type BuyerSellerOrder = {
    number: string;
    status: string;
    store_name: string;
    delivered_at: string | null;
    shipment: {
        status: string;
        courier_name: string | null;
        tracking_number: string | null;
        status_history: Record<string, unknown>[] | null;
    } | null;
    items: BuyerOrderItem[];
};

export type BuyerOrder = {
    number: string;
    stage: BuyerOrderStage;
    stage_label: string;
    status: string;
    subtotal: string;
    shipping_total: string;
    total: string;
    created_at: string | null;
    shipping_address: CheckoutAddress | null;
    billing_address: CheckoutAddress | null;
    seller_orders: BuyerSellerOrder[];
    payments: {
        id: number;
        method: string;
        status: string;
        amount: string;
        attempts: PaymentAttempt[];
    }[];
};

export type CheckoutAddress = {
    recipient_name: string;
    address_line_one: string;
    address_line_two: string | null;
    city: string;
    postal_code: string | null;
    phone: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
};

export type FeedbackItem = {
    id: number;
    title: string;
    image_url: string | null;
    store_name: string;
    order_number: string;
    variant_options: Record<string, string> | null;
    delivered_at?: string;
    rating?: number;
    comment?: string | null;
    created_at?: string;
};
