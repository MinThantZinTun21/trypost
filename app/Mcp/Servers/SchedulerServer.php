<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\CompleteMediaUpload;
use App\Mcp\Tools\GetPost;
use App\Mcp\Tools\ListPosts;
use App\Mcp\Tools\ListSocialAccounts;
use App\Mcp\Tools\StartMediaUpload;
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
#[Instructions('Schedules the Owner\'s posts to their Facebook Pages, TikTok accounts and YouTube channels. Start with list_social_accounts to see where you can post.')]
class SchedulerServer extends Server
{
    protected array $tools = [
        ListSocialAccounts::class,
        StartMediaUpload::class,
        CompleteMediaUpload::class,
        ListPosts::class,
        GetPost::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
