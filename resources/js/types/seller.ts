import type { PaginationLink } from './buyer';

export type SellerOrderStatus =
    | 'pending_payment'
    | 'paid'
    | 'processing'
    | 'ready_to_ship'
    | 'shipped'
    | 'completed'
    | 'expired'
    | 'cancelled';

export type SellerTimelineEntry = {
    status: SellerOrderStatus;
    label: string;
    at: string | null;
    complete: boolean;
    current: boolean;
};

export type SellerOrder = {
    id: number;
    number: string;
    customer_order_number: string;
    status: SellerOrderStatus;
    status_label: string;
    created_at: string;
    recipient_name: string;
    subtotal: string;
    shipping_charge: string;
    seller_earnings: string;
    items: {
        id: number;
        title: string;
        quantity: number;
        unit_price: string;
        total: string;
        variant_options: Record<string, string> | null;
    }[];
    shipment: {
        courier_name: string;
        tracking_number: string;
        status: string;
        status_history:
            { status: string; at: string; reason?: string }[] | null;
    } | null;
    next_action: { action: string; label: string } | null;
    recipient: {
        name: string;
        phone: string | null;
        address_line_one: string | null;
        address_line_two: string | null;
        city: string | null;
        postal_code: string | null;
    } | null;
    payment_method: string | null;
    timeline: SellerTimelineEntry[] | null;
};

export type SellerPaginator<T> = {
    data: T[];
    current_page: number;
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};
