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
    | Cron Endpoint
    |--------------------------------------------------------------------------
    |
    | GET /cron/run runs the due scheduled tasks for the Owner's external
    | cron, next to schedule:work rather than instead of it: every task is
    | onOneServer, so the two never run one twice. With max_seconds above 0
    | it then works the queue that long, publishing due posts in the call;
    | keep it under PHP's max_execution_time (60 s in production). The queue
    | worker publishes whatever is left. Empty secret = endpoint disabled.
    |
    */

    'cron' => [
        'secret' => env('CRON_SECRET'),
        'max_seconds' => (int) env('CRON_MAX_SECONDS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | POST /mcp lets the Owner's Assistant (e.g. Claude Code) list Social
    | accounts and create Posts. Clients send `Authorization: Bearer <token>`.
    | Empty token = endpoint disabled (404).
    |
    */

    'mcp' => [
        'token' => env('MCP_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile app
    |--------------------------------------------------------------------------
    |
    | The Owner's Android app calls /mobile/v1 with this bearer secret
    | (ADR 0010). Empty = the mobile API is off (404).
    |
    */

    'mobile' => [
        'token' => env('MOBILE_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram channel
    |--------------------------------------------------------------------------
    |
    | With TikTok delivery set to "telegram", TikTok posts are handed off to
    | the Owner's channel by a bot that is an admin there (ADR 0009).
    | Telegram is not a Platform. Empty token or channel = handoff fails.
    |
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'channel_id' => env('TELEGRAM_CHANNEL_ID'),
        'api' => env('TELEGRAM_API', 'https://api.telegram.org'),
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
    | Videos go to object storage in one presigned PUT, which S3 and R2 cap at
    | 5 GB, so keep the video limit below 5120. 4 GB is TikTok's own maximum.
    |
    */

    'media' => [
        'max_size_mb' => [
            'image' => (int) env('MEDIA_IMAGE_MAX_SIZE_MB', 10),
            'video' => (int) env('MEDIA_VIDEO_MAX_SIZE_MB', 4096),
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
            // "api" uploads to TikTok; "telegram" sends the media and caption
            // to the Telegram channel instead, to post by hand (ADR 0009).
            'delivery' => env('TIKTOK_DELIVERY', 'api'),
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
