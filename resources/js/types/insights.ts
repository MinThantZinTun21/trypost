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
    refresh_pending: boolean;
    refresh_available_at: string | null;
}

export type PageInsightsDay = { date: string } & Record<
    PageMetricKey,
    number | null
>;

export interface PostInsights {
    views: number | null;
    reach: number | null;
    reactions: number | null;
    clicks: number | null;
    video_views: number | null;
    avg_watch_time_ms: number | null;
    watch_time_ms: number | null;
    reel_plays: number | null;
    read_at: string | null;
}

export interface TopPost {
    post_id: string;
    content: string;
    content_type: string | null;
    published_at: string | null;
    insights: PostInsights;
}
