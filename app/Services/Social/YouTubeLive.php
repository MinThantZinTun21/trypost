<?php

declare(strict_types=1);

namespace App\Services\Social;

use App\Enums\LiveStream\Privacy;
use App\Exceptions\Social\YouTubeLiveException;
use App\Models\Media;
use App\Models\SocialAccount;
use App\Services\Social\Concerns\CreatesGoogleClient;
use Carbon\CarbonInterface;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\YouTube;
use Google\Service\YouTube\CdnSettings;
use Google\Service\YouTube\LiveBroadcast;
use Google\Service\YouTube\LiveBroadcastContentDetails;
use Google\Service\YouTube\LiveBroadcastSnippet;
use Google\Service\YouTube\LiveBroadcastStatus;
use Google\Service\YouTube\LiveStream as YouTubeLiveStream;
use Google\Service\YouTube\LiveStreamSnippet;
use Illuminate\Support\Facades\Storage;

/**
 * The YouTube Live Streaming API calls a Live stream needs (ADR 0017), made
 * with the channel's `youtube.force-ssl` token. Each failure is a
 * YouTubeLiveException carrying YouTube's reason.
 */
class YouTubeLive
{
    use CreatesGoogleClient;

    /**
     * Creates a broadcast that starts when video arrives and keeps going
     * until it is completed, plus a stream bound to it.
     *
     * @param  array{title: string, description: ?string, privacy: Privacy, starts_at: CarbonInterface}  $details
     * @return array{broadcast_id: string, stream_id: string, ingest_url: string}
     *
     * @throws YouTubeLiveException
     */
    public function createBroadcast(SocialAccount $account, array $details): array
    {
        return $this->call($account, function (YouTube $youtube) use ($details): array {
            $broadcast = $youtube->liveBroadcasts->insert('snippet,status,contentDetails', new LiveBroadcast([
                'snippet' => new LiveBroadcastSnippet([
                    'title' => $details['title'],
                    'description' => (string) $details['description'],
                    'scheduledStartTime' => $details['starts_at']->toIso8601ZuluString(),
                ]),
                'status' => new LiveBroadcastStatus([
                    'privacyStatus' => $details['privacy']->value,
                    'selfDeclaredMadeForKids' => false,
                ]),
                'contentDetails' => new LiveBroadcastContentDetails([
                    'enableAutoStart' => true,
                    'enableAutoStop' => false,
                    'enableDvr' => true,
                    'recordFromStart' => true,
                ]),
            ]));

            $stream = $youtube->liveStreams->insert('snippet,cdn,contentDetails', new YouTubeLiveStream([
                'snippet' => new LiveStreamSnippet(['title' => $details['title']]),
                'cdn' => new CdnSettings([
                    'ingestionType' => 'rtmp',
                    'resolution' => 'variable',
                    'frameRate' => 'variable',
                ]),
            ]));

            $youtube->liveBroadcasts->bind($broadcast->getId(), 'id,contentDetails', ['streamId' => $stream->getId()]);

            $ingestion = $stream->getCdn()->getIngestionInfo();
            $address = $ingestion->getRtmpsIngestionAddress() ?: $ingestion->getIngestionAddress();

            return [
                'broadcast_id' => $broadcast->getId(),
                'stream_id' => $stream->getId(),
                'ingest_url' => rtrim((string) $address, '/').'/'.$ingestion->getStreamName(),
            ];
        });
    }

    /**
     * @throws YouTubeLiveException
     */
    public function setThumbnail(SocialAccount $account, string $broadcastId, Media $image): void
    {
        $this->call($account, fn (YouTube $youtube) => $youtube->thumbnails->set($broadcastId, [
            'data' => Storage::get($image->path),
            'mimeType' => $image->mime_type,
            'uploadType' => 'media',
        ]));
    }

    /**
     * Ends a live broadcast; YouTube keeps its recording.
     *
     * @throws YouTubeLiveException
     */
    public function complete(SocialAccount $account, string $broadcastId): void
    {
        $this->call($account, fn (YouTube $youtube) => $youtube->liveBroadcasts->transition('complete', $broadcastId, 'status'));
    }

    /**
     * Removes a broadcast that never went live.
     *
     * @throws YouTubeLiveException
     */
    public function delete(SocialAccount $account, string $broadcastId): void
    {
        $this->call($account, fn (YouTube $youtube) => $youtube->liveBroadcasts->delete($broadcastId));
    }

    /**
     * @template T
     *
     * @param  callable(YouTube): T  $callback
     * @return T
     *
     * @throws YouTubeLiveException
     */
    private function call(SocialAccount $account, callable $callback): mixed
    {
        if ($account->needsProactiveTokenRefresh()) {
            app(ConnectionVerifier::class)->refreshToken($account);
            $account->refresh();
        }

        try {
            return $callback(new YouTube($this->createGoogleClient($account)));
        } catch (GoogleServiceException $e) {
            throw YouTubeLiveException::fromGoogleException($e);
        }
    }
}
