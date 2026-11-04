<?php

declare(strict_types=1);

return [
    'not_h264' => 'The Video\'s picture is :codec, not H.264, so a Live stream cannot play it. Export it again as H.264.',
    'not_aac' => 'The Video\'s sound is :codec, not AAC, so a Live stream cannot play it. Export it again with AAC audio.',
    'keyframes_too_far' => 'The Video has keyframes up to :seconds seconds apart; YouTube needs one at least every 4 seconds. Export it again with a keyframe every 2 seconds.',
    'low_bitrate' => 'The Video is :mbps Mbps; YouTube recommends at least 5 Mbps for 1080p, so the Live stream may look soft.',
    'not_youtube' => 'A Live stream plays on YouTube. Pick a YouTube Social account.',
    'no_file' => 'The Video has no file yet.',
    'one_at_a_time' => 'Another Live stream is live. Stop it first, or schedule this one for later.',
    'youtube_refused' => 'YouTube refused the Live stream: :reason',
    'no_disk' => 'The server has :free free disk but needs :needed to hold the Video while it streams.',
    'ffmpeg_exited' => 'ffmpeg stopped (exit :code): :output',
    'not_active' => 'The Live stream has already stopped or failed.',
    'youtube_not_enabled' => 'The YouTube Social account is not enabled for live streaming. Turn on live streaming in YouTube Studio (it can take 24 hours the first time).',
    'youtube_blocked' => 'The YouTube Social account is blocked from live streaming. Check its standing in YouTube Studio.',
];
