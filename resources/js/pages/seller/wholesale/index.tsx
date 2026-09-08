import SellerListings from '@/pages/seller/listings/index';
import type { SellerPaginator } from '@/types';

type WholesaleListing = {
    id: number;
    title: string | null;
    sku: string | null;
    model: string | null;
    status: string;
    moderation_reason: string | null;
    product_type: 'simple' | 'variant';
    price: string | null;
    is_retail_enabled: boolean;
    is_wholesale_enabled: boolean;
    wholesale_price: string | null;
    wholesale_min_quantity: number | null;
    has_orders: boolean;
    created_at: string;
    brand: { name: string } | null;
    brand_name: string | null;
    category: { name: string } | null;
};

export default function SellerWholesaleIndex(props: {
    sellerStatus: string;
    listings: SellerPaginator<WholesaleListing>;
    filters: { q: string; status: string; sort: string };
}) {
    return <SellerListings {...props} channel="wholesale" />;
}
