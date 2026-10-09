<?php

return [

    'privacy' => [
        'page_title' => 'Privacy Policy',
        'title' => 'Privacy Policy',
        'description' => 'TryPost is a personal scheduler run by one person to publish their own posts to Facebook Pages, TikTok and YouTube.',
        'sections' => [
            'collect' => [
                'title' => 'What we store',
                'body' => 'The owner\'s login email and password hash; the social accounts the owner connects (account name, ID, avatar and the access tokens the platform issues); the posts the owner writes, with their media files; and, for the owner\'s own Facebook Pages, the Insights Facebook reports (daily follower, view, reach and engagement counts for the Page, and view, reach, reaction, click and watch-time totals for the posts TryPost published). Insights are aggregate numbers; they identify no individual person.',
            ],
            'use' => [
                'title' => 'How it is used',
                'body' => 'Only to publish the owner\'s posts to the connected accounts at the time the owner chooses, to show whether publishing succeeded, and to show the owner how their own Facebook Page and posts perform. Nothing is sold, shared for advertising, or used to track anyone.',
            ],
            'storage' => [
                'title' => 'Where it is kept',
                'body' => 'Data lives in a private PostgreSQL database (Supabase) and media files in a Cloudflare R2 bucket. Media is served from a public URL so the platforms can fetch it while publishing. The only cookies are the login session, its security (CSRF) token and whether the sidebar is open; there are no tracking cookies.',
            ],
            'sharing' => [
                'title' => 'Who it is shared with',
                'body' => 'A post and its media are sent only to the platform it is published to (Meta, TikTok or Google), under that platform\'s own privacy policy.',
            ],
            'deletion' => [
                'title' => 'Deleting your data',
                'body' => 'Disconnecting an account in TryPost deletes its stored tokens and its Page Insights; Insights for posts already published stay with those posts\' history. You can also remove the app from your Facebook settings (Settings & privacy → Settings → Business integrations), TikTok or Google account permissions at any time. To have everything deleted, email the address below and it will be removed within 30 days.',
            ],
            'contact' => [
                'title' => 'Contact',
                'body' => 'Questions or deletion requests:',
            ],
        ],
    ],

    'terms' => [
        'page_title' => 'Terms of Service',
        'title' => 'Terms of Service',
        'description' => 'TryPost is a personal scheduler run by one person to publish their own posts to Facebook Pages, TikTok and YouTube.',
        'sections' => [
            'service' => [
                'title' => 'The service',
                'body' => 'TryPost lets its owner write posts, attach media and schedule them for publishing to the Facebook Pages, TikTok accounts and YouTube channels the owner connects. It is a private tool: there is no public sign-up and no other users.',
            ],
            'accounts' => [
                'title' => 'Connected accounts',
                'body' => 'Connecting an account authorises TryPost to publish on its behalf, using only the permissions the platform grants. You can disconnect an account in TryPost or revoke access in the platform\'s own settings at any time.',
            ],
            'content' => [
                'title' => 'Your content',
                'body' => 'You keep all rights to the posts and media you publish and are responsible for them. Content must follow the rules of the platform it is published to, including the TikTok Terms of Service and Community Guidelines, the Meta Platform Terms and the YouTube Terms of Service.',
            ],
            'availability' => [
                'title' => 'Availability',
                'body' => 'TryPost is provided as is, without warranty. A post can fail or be delayed when a platform rejects it, changes its API or is unavailable; TryPost shows the result of every publish attempt but cannot guarantee delivery.',
            ],
            'changes' => [
                'title' => 'Changes',
                'body' => 'These terms may be updated when the service changes. The current version is always published on this page.',
            ],
            'contact' => [
                'title' => 'Contact',
                'body' => 'Questions about these terms:',
            ],
        ],
    ],

];
