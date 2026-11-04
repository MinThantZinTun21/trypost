<?php

declare(strict_types=1);

use App\Dto\StreamReadiness;
use App\Services\Media\StreamProbe;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

test('probe reads codecs, bitrate and the widest keyframe gap from ffprobe', function () {
    Process::fake([
        '*show_entries*stream=*' => Process::result(json_encode([
            'streams' => [['codec_type' => 'video', 'codec_name' => 'h264'], ['codec_type' => 'audio', 'codec_name' => 'aac']],
            'format' => ['bit_rate' => '6100000'],
        ])),
        '*skip_frame*' => Process::result("0.000000\n2.000000\n4.000000\n7.500000\n9.500000\n"),
    ]);

    $readiness = app(StreamProbe::class)->probe('https://bucket.example.test/talk.mp4');

    expect($readiness)->toEqual(new StreamReadiness('h264', 'aac', 3.5, 6_100_000));
    Process::assertRan(fn (PendingProcess $process) => in_array('https://bucket.example.test/talk.mp4', (array) $process->command, true));
});

test('readiness lists what keeps a video from streaming and what only warns', function () {
    expect((new StreamReadiness('h264', 'aac', 4.0, 5_000_000))->problems())->toBe([])
        ->and((new StreamReadiness('h264', 'aac', 4.0, 5_000_000))->warnings())->toBe([])
        ->and((new StreamReadiness('vp9', 'aac', 2.0, 6_000_000))->problems())->toBe([__('live_streams.not_h264', ['codec' => 'vp9'])])
        ->and((new StreamReadiness('h264', 'aac', 2.0, null))->warnings())->toBe([]);
});
