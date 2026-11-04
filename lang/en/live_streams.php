<?php

declare(strict_types=1);

return [
    'not_h264' => 'The Video\'s picture is :codec, not H.264, so it cannot be copied into a live stream. Export it again as H.264.',
    'not_aac' => 'The Video\'s sound is :codec, not AAC, so it cannot be copied into a live stream. Export it again with AAC audio.',
    'keyframes_too_far' => 'The Video has keyframes up to :seconds seconds apart; YouTube needs one at least every 4 seconds. Export it again with a keyframe every 2 seconds.',
    'low_bitrate' => 'The Video is :mbps Mbps; YouTube recommends at least 5 Mbps for 1080p, so the stream may look soft.',
    'not_youtube' => 'Live streams go to a YouTube channel. Pick a YouTube Social account.',
    'no_file' => 'The Video has no file yet.',
    'one_at_a_time' => 'Another Live stream is live. Stop it first, or schedule this one for later.',
    'youtube_refused' => 'YouTube refused the live stream: :reason',
    'no_disk' => 'The server has :free free disk but needs :needed to hold the Video while it streams.',
    'ffmpeg_exited' => 'ffmpeg stopped (exit :code): :output',
];
