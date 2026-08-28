export type StorefrontCategory = {
    id: number;
    name: string;
    slug: string;
    image_url: string | null;
    children: StorefrontCategoryChild[];
};

export type StorefrontCategoryChild = {
    id: number;
    name: string;
    slug: string;
    image_url: string | null;
};

export type StorefrontCategoryNode = {
    id: number;
    name: string;
    slug: string;
    image_url: string | null;
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
    thumbnailUrl: string;
    cardUrl: string;
    card2xUrl: string;
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
    salePrice: string | null;
    effectivePrice: string | null;
    discountPercentage: number | null;
    ratingAverage: number | null;
    reviewCount: number;
    location: string;
    warranty: string | null;
    stockQuantity: number;
    category: { name: string; slug: string } | null;
    brand: { name: string; slug: string } | null;
    media: StorefrontListingMedia[];
    seller: { store_name: string; slug: string } | null;
    auction: StorefrontListingAuction | null;
};

export type StorefrontPromotion = {
    id: number | null;
    title: string;
    imageUrl: string;
    linkUrl: string | null;
};

export type StorefrontHomepageCategory = {
    id: number;
    name: string;
    slug: string;
    image_url: string | null;
    banner_image_url?: string | null;
};

export type StorefrontCategorySection = {
    category: StorefrontHomepageCategory;
    variant: 'image' | 'tinted' | 'clean';
    listings: StorefrontListing[];
};

export type StorefrontReview = {
    id: number;
    rating: number;
    comment: string | null;
    buyerName: string;
    createdAt: string;
    listingTitle?: string;
    listingSlug?: string | null;
};

export type StorefrontSocialProof = {
    summary: { average: number | null; count: number };
    reviews: StorefrontReview[];
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
