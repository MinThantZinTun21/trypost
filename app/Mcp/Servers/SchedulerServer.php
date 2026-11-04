<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CompleteMediaUpload;
use App\Mcp\Tools\CreateLiveStream;
use App\Mcp\Tools\CreatePost;
use App\Mcp\Tools\CreateVideo;
use App\Mcp\Tools\DeletePost;
use App\Mcp\Tools\DeleteVideo;
use App\Mcp\Tools\GetLiveStream;
use App\Mcp\Tools\GetPageInsights;
use App\Mcp\Tools\GetPost;
use App\Mcp\Tools\GetPostInsights;
use App\Mcp\Tools\GetVideo;
use App\Mcp\Tools\ListLiveStreams;
use App\Mcp\Tools\ListPosts;
use App\Mcp\Tools\ListSocialAccounts;
use App\Mcp\Tools\ListVideos;
use App\Mcp\Tools\StartMediaUpload;
use App\Mcp\Tools\StartVideoUpload;
use App\Mcp\Tools\StopLiveStream;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The Owner's Assistant (e.g. Claude Code) talks to the scheduler through
 * this server. Every tool acts as the Owner on the Owner's workspace (ADR 0003).
 */
#[Name('TryPost')]
#[Version('1.0.0')]
#[Instructions('Schedules the Owner\'s posts to their Social accounts on Facebook Pages, TikTok and YouTube. Start with list_social_accounts to see where you can post. It can delete a Post that has not gone out with delete_post; ask the Owner first. For Facebook Pages it can read stored Insights with get_page_insights and get_post_insights. Videos are files the Owner uploads from their phone, or that you upload with start_video_upload and create_video; they are not Posts: list them with list_videos, download one through get_video\'s Download link, and delete one with delete_video after asking the Owner. A Live stream plays a Video on repeat as a live broadcast on a YouTube Social account: confirm the Social account and time with the Owner before create_live_stream, and ask before stop_live_stream.')]
class SchedulerServer extends Server
{
    protected array $tools = [
        ListSocialAccounts::class,
        StartMediaUpload::class,
        CompleteMediaUpload::class,
        ListPosts::class,
        GetPost::class,
        CreatePost::class,
        DeletePost::class,
        GetPageInsights::class,
        GetPostInsights::class,
        ListVideos::class,
        GetVideo::class,
        StartVideoUpload::class,
        CreateVideo::class,
        ListLiveStreams::class,
        GetLiveStream::class,
        CreateLiveStream::class,
        StopLiveStream::class,
        DeleteVideo::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
