<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CompleteMediaUpload;
use App\Mcp\Tools\CreateContentIdea;
use App\Mcp\Tools\CreateLibraryItem;
use App\Mcp\Tools\CreatePost;
use App\Mcp\Tools\DeleteContentIdea;
use App\Mcp\Tools\DeleteLibraryItem;
use App\Mcp\Tools\DeletePost;
use App\Mcp\Tools\GetContentIdea;
use App\Mcp\Tools\GetLibraryItem;
use App\Mcp\Tools\GetPageInsights;
use App\Mcp\Tools\GetPost;
use App\Mcp\Tools\GetPostInsights;
use App\Mcp\Tools\ListContentIdeas;
use App\Mcp\Tools\ListLibraryItems;
use App\Mcp\Tools\ListPosts;
use App\Mcp\Tools\ListSocialAccounts;
use App\Mcp\Tools\ScheduleLibraryItem;
use App\Mcp\Tools\StartMediaUpload;
use App\Mcp\Tools\UpdateContentIdeaStatus;
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
#[Instructions('Schedules the Owner\'s posts to their Social accounts on Facebook Pages, TikTok and YouTube. Start with list_social_accounts to see where you can post. It also keeps the Owner\'s Content ideas: Markdown notes for future Posts, each new, in_progress or done. Its Content library holds ready-made content (media plus the Facebook text, TikTok caption and YouTube title, description and tags): save to it with create_library_item and make Posts from it with schedule_library_item. It can delete a Post that has not gone out, a Content idea or a Library item; ask the Owner first. For Facebook Pages it can read stored Insights with get_page_insights and get_post_insights.')]
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
        ListContentIdeas::class,
        GetContentIdea::class,
        CreateContentIdea::class,
        UpdateContentIdeaStatus::class,
        DeleteContentIdea::class,
        ListLibraryItems::class,
        GetLibraryItem::class,
        CreateLibraryItem::class,
        ScheduleLibraryItem::class,
        DeleteLibraryItem::class,
        GetPageInsights::class,
        GetPostInsights::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
