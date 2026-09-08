export type AuctionType = 'normal' | 'blind' | 'time_extended';

export type AuctionStatus =
    | 'draft'
    | 'scheduled'
    | 'live'
    | 'offer_pending'
    | 'sold'
    | 'ended_no_bids'
    | 'bidder_exhausted'
    | 'cancelled';

export type AuctionOfferStatus =
    'waiting' | 'offered' | 'accepted' | 'paid' | 'expired' | 'cancelled';

export type AuctionVariant = {
    id: number;
    sku: string;
    stock_quantity?: number;
    reserved_quantity?: number;
    option_values?: { value: string; option: { name: string } }[];
};

export type SellerAuctionListing = {
    id: number;
    title: string;
    product_type: 'simple' | 'variant';
    stock_quantity: number;
    reserved_quantity: number;
    status: string;
    variants: AuctionVariant[];
};

export type AuctionRecord = {
    id: number;
    type: AuctionType;
    status: AuctionStatus;
    quantity: number;
    starting_price: string;
    current_price: string | null;
    minimum_increment: string;
    extension_window_minutes: number;
    starts_at: string;
    ends_at: string;
    payment_due_at: string | null;
    cancellation_reason: string | null;
    listing: SellerAuctionListing & {
        slug?: string;
        seller_profile?: { store_name: string };
    };
    variant: AuctionVariant | null;
    offers?: { id: number; status: AuctionOfferStatus }[];
};

export type AuctionFlags = {
    enabled: boolean;
    types: Record<AuctionType, boolean>;
};

export type AuctionOffer = {
    id: number;
    rank: number;
    unit_price: string;
    quantity: number;
    status: AuctionOfferStatus;
    offered_at: string | null;
    expires_at: string | null;
    auction: AuctionRecord;
    order: { number: string; status: string } | null;
};
