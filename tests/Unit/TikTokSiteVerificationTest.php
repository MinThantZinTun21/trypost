<?php

declare(strict_types=1);

/**
 * TikTok verifies the Owner's URL property by fetching this file from the
 * site root, and may fetch it again later, so it ships in public/.
 */
test('the tiktok site verification file ships at the site root', function () {
    $file = public_path('tiktokdpWhYpttZ2TqbyqngUzyouZFsTipzreR.txt');

    expect($file)->toBeFile()
        ->and(trim((string) file_get_contents($file)))
        ->toBe('tiktok-developers-site-verification=dpWhYpttZ2TqbyqngUzyouZFsTipzreR');
});
