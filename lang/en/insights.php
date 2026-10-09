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
    'read_at' => 'Read from Facebook :time',
    'never_read' => 'Not read from Facebook yet',
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
        'description' => 'The app reads Insights from Facebook every day at 03:00 UTC. The first read fetches the last 90 days.',
    ],
];
