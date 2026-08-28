export type StorefrontCategory = {
    id: number;
    name: string;
    slug: string;
    children: StorefrontCategoryChild[];
};

export type StorefrontCategoryChild = {
    id: number;
    name: string;
    slug: string;
};

export type StorefrontCategoryNode = {
    id: number;
    name: string;
    slug: string;
};

export type StorefrontSubcategory = StorefrontCategoryNode & {
    has_children: boolean;
};

export type StorefrontCategoryContext = {
    current: StorefrontCategoryNode;
    ancestors: StorefrontCategoryNode[];
    children: StorefrontSubcategory[];
};

export type StorefrontBrand = {
    id: number;
    name: string;
    slug: string;
};

export type StorefrontBrowseFilters = {
    search?: string | null;
    category?: string | null;
    brand?: string | null;
    condition?: 'new' | 'used' | 'refurbished' | null;
    listing_type?: 'buy_now' | 'auction' | null;
    location?: string | null;
    min_price?: string | number | null;
    max_price?: string | number | null;
    sort: 'newest' | 'price_asc' | 'price_desc';
};

export type StorefrontListingMedia = {
    path: string;
    type: string;
    url: string;
};

export type StorefrontListingAuction = {
    id: number;
    status: string;
    currentPrice: string | null;
    minimumIncrement: string | null;
    endsAt: string;
    bidCount: number | null;
};

export type StorefrontListing = {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    condition: 'new' | 'used' | 'refurbished';
    listingType: 'buy_now' | 'auction';
    price: string | null;
    location: string;
    warranty: string | null;
    stockQuantity: number;
    category: { name: string; slug: string } | null;
    brand: { name: string; slug: string } | null;
    media: StorefrontListingMedia[];
    seller: { store_name: string; slug: string } | null;
    auction: StorefrontListingAuction | null;
};

export type StorefrontPaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type StorefrontListingPaginator = {
    data: StorefrontListing[];
    current_page: number;
    from: number | null;
    last_page: number;
    links: StorefrontPaginationLink[];
    next_page_url: string | null;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};

export type StorefrontBreadcrumbItem = {
    label: string;
    href?: string;
};
