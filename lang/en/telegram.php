<?php

declare(strict_types=1);

return [
    'download' => 'Download :name',
    'test_message' => ':app is connected. TikTok posts will appear here.',
    'not_configured' => 'TikTok posts go to Telegram, but Telegram is not set up. Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHANNEL_ID.',
    'refused' => 'Telegram refused the TikTok post: :reason',
    'no_video' => 'There is no video to send to Telegram for TikTok.',
    'no_photos' => 'There are no photos under 10 MB to send to Telegram for TikTok.',
    'sending' => 'Sending to Telegram',
    'send_not_configured' => 'Telegram is not set up. Set TELEGRAM_BOT_TOKEN and TELEGRAM_CHANNEL_ID.',
    'no_media' => 'This post has no video or photo to send to Telegram.',
];
