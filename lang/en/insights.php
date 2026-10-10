<?php

declare(strict_types=1);

return [
    'title' => 'Insights',
    'description' => 'How your Facebook Page grows and how people see and engage with what you post.',
    'page_label' => 'Facebook Page',
    'range_days' => ':days days',
    'period' => ':from – :to',
    'metrics' => [
        'followers' => 'Followers',
        'new_follows' => 'New follows',
        'unfollows' => 'Unfollows',
        'views' => 'Views',
        'reach' => 'Reach',
        'engagements' => 'Engagements',
        'video_views' => 'Video views',
    ],
    'hints' => [
        'followers' => 'At the end of the range.',
        'reach' => 'Sum of daily reach: someone reached on two days counts twice.',
    ],
    'comparison' => [
        'up' => '+:percent% vs previous :days days',
        'down' => '−:percent% vs previous :days days',
        'same' => 'Same as previous :days days',
        'new' => 'Nothing in the previous :days days',
        'none' => 'No data for the previous :days days',
    ],
    'no_value' => '—',
    'chart_label' => ':metric per day',
    'read_at' => 'Read from Facebook :time',
    'never_read' => 'Not read from Facebook yet',
    'refresh' => [
        'action' => 'Refresh now',
        'pending' => 'Reading from Facebook…',
        'available_in' => 'Refresh available :time',
        'too_soon' => 'You can refresh again in :minutes minute.|You can refresh again in :minutes minutes.',
        'queued' => 'A read is already running for this Page.',
        'disconnected' => 'Reconnect this Page on the Accounts page to read its Insights again.',
    ],
    'post' => [
        'title' => 'Insights',
        'not_read' => 'Not read from Facebook yet. Insights are read daily for 30 days after a Post publishes.',
        'read_at' => 'Read :time',
        'metrics' => [
            'views' => 'Views',
            'reach' => 'Reach',
            'reactions' => 'Reactions',
            'clicks' => 'Clicks',
            'video_views' => 'Video views',
            'avg_watch_time_ms' => 'Avg. watch time',
            'watch_time_ms' => 'Watch time',
            'reel_plays' => 'Reel plays',
        ],
    ],
    'top_posts' => [
        'title' => 'Top Posts',
        'description' => 'Posts this app published in the range, by views.',
        'empty' => 'No Post published in this range has Insights yet.',
        'untitled' => 'Post without text',
        'views' => ':count view|:count views',
    ],
    'http_error' => 'Facebook answered HTTP :status.',
    'no_feed_post' => 'Facebook has no feed post for video :id yet.',
    'duration' => [
        'seconds' => ':seconds s',
        'minutes' => ':minutes m :seconds s',
        'hours' => ':hours h :minutes m',
    ],
    'error' => [
        'title' => 'Facebook refused the last read',
        'description' => 'The numbers below may be out of date. Facebook said: :message',
    ],
    'empty' => [
        'title' => 'No Facebook Page connected',
        'description' => 'Connect a Facebook Page to see its Insights. TikTok and YouTube have no Insights in this app.',
        'action' => 'Connect a Facebook Page',
    ],
    'not_read' => [
        'title' => 'Insights not read yet',
        'description' => 'The app reads Insights from Facebook every day at 09:00 UTC. The first read fetches the last 90 days.',
    ],
];
