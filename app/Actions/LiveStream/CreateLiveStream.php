<?php

declare(strict_types=1);

namespace App\Actions\LiveStream;

use App\Enums\LiveStream\Privacy;
use App\Enums\LiveStream\Status;
use App\Enums\SocialAccount\Platform;
use App\Exceptions\Social\YouTubeLiveException;
use App\Jobs\RunLiveStream;
use App\Models\LiveStream;
use App\Models\Media;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Media\StreamProbe;
use App\Services\Social\YouTubeLive;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class CreateLiveStream
{
    /**
     * Checks the Video is stream-ready, creates and binds the YouTube
     * broadcast, then stores the Live stream (ADR 0017) and, when it starts
     * now, hands it to the live worker. Nothing is stored when YouTube refuses.
     *
     * @param  array{title: string, description: ?string, privacy: Privacy, starts_at: ?CarbonInterface, stops_at: ?CarbonInterface, thumbnail: ?Media}  $details  A null starts_at starts it now.
     * @return array{live_stream: LiveStream, warnings: list<string>}
     *
     * @throws ValidationException
     */
    public static function execute(Workspace $workspace, User $user, Video $video, SocialAccount $account, array $details): array
    {
        if ($account->platform !== Platform::YouTube) {
            throw ValidationException::withMessages(['social_account_id' => __('live_streams.not_youtube')]);
        }

        if ($video->file === null) {
            throw ValidationException::withMessages(['video_id' => __('live_streams.no_file')]);
        }

        $startsNow = $details['starts_at'] === null;

        if ($startsNow && LiveStream::anotherIsPlaying()) {
            throw ValidationException::withMessages(['mode' => __('live_streams.one_at_a_time')]);
        }

        $readiness = app(StreamProbe::class)->probe($video->file->getTemporaryUrl());

        if ($readiness->problems() !== []) {
            throw ValidationException::withMessages(['video_id' => $readiness->problems()]);
        }

        $startsAt = $details['starts_at'] ?? now();
        $youtube = app(YouTubeLive::class);

        try {
            $broadcast = $youtube->createBroadcast($account, [
                'title' => $details['title'],
                'description' => $details['description'],
                'privacy' => $details['privacy'],
                'starts_at' => $startsAt,
            ]);

        } catch (YouTubeLiveException $e) {
            throw ValidationException::withMessages(['social_account_id' => __('live_streams.youtube_refused', ['reason' => $e->getMessage()])]);
        }

        try {
            if ($details['thumbnail'] !== null) {
                $youtube->setThumbnail($account, $broadcast['broadcast_id'], $details['thumbnail']);
            }
        } catch (YouTubeLiveException $e) {
            rescue(fn () => $youtube->delete($account, $broadcast['broadcast_id']), report: false);

            throw ValidationException::withMessages(['social_account_id' => __('live_streams.youtube_refused', ['reason' => $e->getMessage()])]);
        }

        $liveStream = $workspace->liveStreams()->create([
            'user_id' => $user->id,
            'video_id' => $video->id,
            'social_account_id' => $account->id,
            'title' => $details['title'],
            'description' => $details['description'],
            'privacy' => $details['privacy'],
            'status' => Status::Scheduled,
            'starts_at' => $startsAt,
            'stops_at' => $details['stops_at'],
            'youtube_broadcast_id' => $broadcast['broadcast_id'],
            'youtube_stream_id' => $broadcast['stream_id'],
            'ingest_url' => $broadcast['ingest_url'],
        ]);

        if ($startsNow) {
            $liveStream->update(['heartbeat_at' => now()]);
            RunLiveStream::dispatch($liveStream);
        }

        return ['live_stream' => $liveStream, 'warnings' => $readiness->warnings()];
    }
}
