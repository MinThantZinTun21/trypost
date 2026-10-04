<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Meta page walk budget
    |--------------------------------------------------------------------------
    |
    | Seconds the Facebook page walk may spend before it returns what
    | it has and reports itself incomplete. It runs inside the OAuth callback,
    | so this must stay well under the web server's request timeout.
    |
    */

    'meta_page_walk_seconds' => (int) env('META_PAGE_WALK_SECONDS', 20),

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | SafeHttpFetcher blocks requests to private/reserved IP ranges (SSRF
    | protection) by default. Self-hosted operators who need to fetch from
    | their own internal network (e.g. an internal webhook endpoint) can
    | opt in here. Leave disabled unless you understand the SSRF risk.
    |
    */

    'security' => [
        'allow_private_network' => (bool) env('TRYPOST_ALLOW_PRIVATE_NETWORK', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Size Limits
    |--------------------------------------------------------------------------
    |
    | Per-type size caps in megabytes. Single source of truth — direct
    | uploads (AssetController::storeChunked), URL
    | fetches (MediaAttacher), and the MediaType enum all read from here.
    |
    */

    'media' => [
        'max_size_mb' => [
            'image' => (int) env('MEDIA_IMAGE_MAX_SIZE_MB', 10),
            'video' => (int) env('MEDIA_VIDEO_MAX_SIZE_MB', 1024),
            'document' => (int) env('MEDIA_DOCUMENT_MAX_SIZE_MB', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound User-Agent
    |--------------------------------------------------------------------------
    |
    | Branded User-Agent applied to outbound HTTP from workspace webhooks so
    | recipients know the request came from TryPost.it. Self-hosters can
    | override it.
    |
    */

    'user_agent' => env('TRYPOST_USER_AGENT', 'TryPost.it/1.0 (+https://trypost.it)'),

    /*
    |--------------------------------------------------------------------------
    | Social Platforms
    |--------------------------------------------------------------------------
    |
    | Configure which social platforms are enabled in the application.
    | Set to false to temporarily disable a platform (e.g., when credentials
    | are revoked, expired, or pending approval).
    |
    */

    'platforms' => [
        'tiktok' => [
            'enabled' => env('TIKTOK_ENABLED', true),
            'api' => env('TIKTOK_API', 'https://open.tiktokapis.com/v2'),
            // OAuth scopes to request. Trim when the TikTok app lacks a product
            // (e.g. no Display API => drop user.info.profile, user.info.stats,
            // video.list; analytics degrade gracefully, username stays empty).
            'scopes' => array_values(array_filter(array_map('trim', explode(',', (string) env('TIKTOK_SCOPES', 'user.info.basic,user.info.profile,user.info.stats,video.publish,video.upload,video.list'))))),
        ],
        'youtube' => [
            'enabled' => env('YOUTUBE_ENABLED', true),
            'data_api' => env('YOUTUBE_DATA_API', 'https://www.googleapis.com/youtube/v3'),
            'oauth_api' => env('YOUTUBE_OAUTH_API', 'https://oauth2.googleapis.com'),
        ],
        'facebook' => [
            'enabled' => env('FACEBOOK_ENABLED', true),
            'graph_api' => env('FACEBOOK_GRAPH_API', 'https://graph.facebook.com/v25.0'),
            'rupload_host' => env('FACEBOOK_RUPLOAD_HOST', 'rupload.facebook.com'),
        ],
    ],

];
