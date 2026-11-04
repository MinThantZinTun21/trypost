<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * What ffprobe found about a Video, and whether ffmpeg can copy it into a
 * YouTube Live stream without re-encoding (ADR 0017).
 */
final readonly class StreamReadiness
{
    /** YouTube warns past this keyframe gap, and the stream may fail. */
    public const float MAX_KEYFRAME_SECONDS = 4.0;

    /** YouTube's minimum recommended bitrate for 1080p30. */
    public const int MIN_BIT_RATE = 5_000_000;

    public function __construct(
        public ?string $videoCodec,
        public ?string $audioCodec,
        public ?float $maxKeyframeSeconds,
        public ?int $bitRate,
    ) {}

    /**
     * Reasons the Video cannot stream; empty when it can.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        if ($this->videoCodec !== 'h264') {
            $problems[] = __('live_streams.not_h264', ['codec' => $this->videoCodec ?? 'none']);
        }

        if ($this->audioCodec !== 'aac') {
            $problems[] = __('live_streams.not_aac', ['codec' => $this->audioCodec ?? 'none']);
        }

        if ($this->maxKeyframeSeconds === null || $this->maxKeyframeSeconds > self::MAX_KEYFRAME_SECONDS) {
            $problems[] = __('live_streams.keyframes_too_far', ['seconds' => number_format($this->maxKeyframeSeconds ?? 0, 1)]);
        }

        return $problems;
    }

    /**
     * Things that let the Video stream but at a lower quality.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        if ($this->bitRate !== null && $this->bitRate < self::MIN_BIT_RATE) {
            return [__('live_streams.low_bitrate', ['mbps' => number_format($this->bitRate / 1_000_000, 1)])];
        }

        return [];
    }
}
