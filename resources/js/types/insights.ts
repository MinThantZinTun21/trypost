export type PageMetricKey =
    | 'followers'
    | 'new_follows'
    | 'unfollows'
    | 'views'
    | 'reach'
    | 'engagements'
    | 'video_views';

export interface PageMetricTotal {
    key: PageMetricKey;
    current: number | null;
    previous: number | null;
}

export interface PageInsightsSummary {
    range: number;
    from: string;
    to: string;
    metrics: PageMetricTotal[];
    days: PageInsightsDay[];
}

export interface InsightsAccountOption {
    id: string;
    name: string;
    avatar_url: string | null;
}

export interface InsightsAccount {
    id: string;
    name: string;
    read_at: string | null;
    error: string | null;
    has_snapshots: boolean;
}

export type PageInsightsDay = { date: string } & Record<
    PageMetricKey,
    number | null
>;
