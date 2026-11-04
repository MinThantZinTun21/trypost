<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Dto\StreamReadiness;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

/**
 * Runs ffprobe on a Video's URL to tell whether it is stream-ready. Only the
 * first minute's keyframes are read, so a long file is not fetched whole.
 */
class StreamProbe
{
    private const int KEYFRAME_WINDOW_SECONDS = 60;

    public function probe(string $url): StreamReadiness
    {
        $info = json_decode(Process::timeout(120)->run([
            'ffprobe', '-v', 'error',
            '-show_entries', 'stream=codec_type,codec_name:format=bit_rate',
            '-of', 'json', $url,
        ])->throw()->output(), true) ?: [];

        $streams = collect(data_get($info, 'streams', []));
        $bitRate = data_get($info, 'format.bit_rate');

        $keyframes = collect(preg_split('/\R/', trim(Process::timeout(300)->run([
            'ffprobe', '-v', 'error', '-select_streams', 'v:0', '-skip_frame', 'nokey',
            '-read_intervals', '%+'.self::KEYFRAME_WINDOW_SECONDS,
            '-show_entries', 'frame=pts_time', '-of', 'csv=p=0', $url,
        ])->throw()->output())))
            ->map(fn (string $line): string => trim($line, " ,\t"))
            ->filter(fn (string $line): bool => is_numeric($line))
            ->map(fn (string $line): float => (float) $line)
            ->sort()
            ->values();

        $maxGap = $keyframes->count() < 2 ? null : $keyframes->sliding(2)
            ->map(fn (Collection $pair): float => round($pair->last() - $pair->first(), 3))
            ->max();

        return new StreamReadiness(
            videoCodec: data_get($streams->firstWhere('codec_type', 'video'), 'codec_name'),
            audioCodec: data_get($streams->firstWhere('codec_type', 'audio'), 'codec_name'),
            maxKeyframeSeconds: $maxGap,
            bitRate: is_numeric($bitRate) ? (int) $bitRate : null,
        );
    }
}
