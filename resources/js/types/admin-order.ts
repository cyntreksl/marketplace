import type { PaginationLink } from './buyer';
import type { SellerOrder } from './seller';

export type PaymentStatus =
    | 'pending'
    | 'paid'
    | 'failed'
    | 'cancelled'
    | 'partially_refunded'
    | 'refunded';

export type AdminOrderSummary = {
    number: string;
    status: string;
    total: string;
    created_at: string;
    buyer: { name: string; email: string };
    payments: { method: string; status: PaymentStatus; amount: string }[];
    packages: { number: string; status: string; store_name: string }[];
};

export type AdminOrder = {
    number: string;
    status: string;
    subtotal: string;
    shipping_total: string;
    total: string;
    created_at: string;
    buyer: { name: string; email: string };
    shipping_address: Record<string, string | null>;
    billing_address: Record<string, string | null> | null;
    payments: {
        id: number;
        method: string;
        status: PaymentStatus;
        amount: string;
        paid_at: string | null;
    }[];
    packages: (SellerOrder & {
        seller: { id: number; store_name: string };
    })[];
};

export type AdminOrderPaginator = {
    data: AdminOrderSummary[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: PaginationLink[];
    next_page_url: string | null;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};
