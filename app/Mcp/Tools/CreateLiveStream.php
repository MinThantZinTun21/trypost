<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\LiveStream\CreateLiveStream as CreateLiveStreamAction;
use App\Enums\LiveStream\Privacy;
use App\Models\Media;
use App\Models\SocialAccount;
use App\Models\Video;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create_live_stream')]
#[Description('Creates a Live stream: plays one Video on repeat as a live broadcast on a YouTube channel, right away or from starts_at, until it is Stopped or stops_at arrives. The Video must be H.264 with a keyframe at least every 4 seconds and AAC audio. A scheduled one shows as Upcoming on YouTube. Only one Live stream is live at a time. Confirm the channel and time with the Owner first.')]
class CreateLiveStream extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $workspace = $request->user()->resolveCurrentWorkspace();

        $validated = $request->validate([
            'video_id' => ['required', 'string', 'uuid', Rule::exists(Video::class, 'id')->where('workspace_id', $workspace->id)],
            'social_account_id' => ['required', 'string', 'uuid', Rule::exists(SocialAccount::class, 'id')->where('workspace_id', $workspace->id)],
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'privacy' => ['nullable', Rule::enum(Privacy::class)],
            'mode' => ['required', Rule::in(['now', 'scheduled'])],
            'starts_at' => ['required_if:mode,scheduled', 'prohibited_if:mode,now', 'nullable', 'string', 'regex:/(Z|[+-]\d{2}:?\d{2})$/i', 'date', 'after:now'],
            'stops_at' => ['nullable', 'string', 'regex:/(Z|[+-]\d{2}:?\d{2})$/i', 'date', 'after:'.($request->get('starts_at') ?: 'now')],
            'thumbnail_media_id' => ['nullable', 'string', 'uuid', Rule::exists(Media::class, 'id')->where('mediable_id', $workspace->id)->where('collection', 'assets')->where('type', 'image')],
        ], [
            'starts_at.regex' => __('mcp.post.scheduled_at_offset'),
            'stops_at.regex' => __('mcp.post.scheduled_at_offset'),
        ]);

        $startsAt = data_get($validated, 'starts_at');
        $stopsAt = data_get($validated, 'stops_at');
        $thumbnailId = data_get($validated, 'thumbnail_media_id');

        $result = CreateLiveStreamAction::execute(
            $workspace,
            $request->user(),
            $workspace->videos()->with('file')->findOrFail(data_get($validated, 'video_id')),
            $workspace->socialAccounts()->findOrFail(data_get($validated, 'social_account_id')),
            [
                'title' => (string) data_get($validated, 'title'),
                'description' => data_get($validated, 'description'),
                'privacy' => Privacy::tryFrom((string) data_get($validated, 'privacy')) ?? Privacy::Public,
                'starts_at' => $startsAt ? Carbon::parse($startsAt)->utc() : null,
                'stops_at' => $stopsAt ? Carbon::parse($stopsAt)->utc() : null,
                'thumbnail' => $thumbnailId ? $workspace->getMedia('assets')->find($thumbnailId) : null,
            ],
        );

        return Response::structured([
            'live_stream' => ListLiveStreams::summary($result['live_stream']->load('video')),
            'warnings' => $result['warnings'],
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'video_id' => $schema->string()->description('The Video to play, from list_videos or create_video.')->required(),
            'social_account_id' => $schema->string()->description('A YouTube Social account\'s id from list_social_accounts.')->required(),
            'title' => $schema->string()->description('The broadcast\'s title, at most 100 characters.')->required(),
            'description' => $schema->string()->description('The broadcast\'s description.'),
            'privacy' => $schema->string()->enum(array_column(Privacy::cases(), 'value'))->description('Who can watch. Defaults to public.'),
            'mode' => $schema->string()->enum(['now', 'scheduled'])->description('now goes live right away; scheduled goes live at starts_at.')->required(),
            'starts_at' => $schema->string()->description('When to go live, as ISO 8601 with an offset, e.g. 2026-11-05T18:00:00+06:30. Required for scheduled.'),
            'stops_at' => $schema->string()->description('Optional: when to Stop it, as ISO 8601 with an offset. Without it, it repeats until Stopped.'),
            'thumbnail_media_id' => $schema->string()->description('Optional: an image\'s media id from complete_media_upload, used as the thumbnail.'),
        ];
    }
}
