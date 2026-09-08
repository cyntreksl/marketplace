import type { PaginationLink } from './buyer';

export type SearchInsightsTerm = {
    term: string;
    normalizedTerm: string;
    searchCount: number;
    averageResultCount: number;
    latestResultCount: number;
    lastSearchedAt: string;
};

export type SearchInsightsRecentSearch = {
    id: number;
    term: string;
    resultCount: number;
    context: string;
    user: { id: number; name: string; email: string } | null;
    searchedAt: string;
};

export type SearchInsightsPaginator = {
    data: SearchInsightsRecentSearch[];
    current_page: number;
    last_page: number;
    links: PaginationLink[];
    total: number;
};

export type SearchInsightsPageProps = {
    periodDays: 7 | 30 | 90;
    metrics: {
        totalSearches: number;
        uniqueTerms: number;
        zeroResultRate: number;
    };
    topTerms: SearchInsightsTerm[];
    zeroResultTerms: SearchInsightsTerm[];
    recentSearches: SearchInsightsPaginator;
};
