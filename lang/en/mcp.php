<?php

return [
    'post' => [
        'not_found' => 'No Post with that id exists for the Owner.',
        'account_not_found' => 'No connected Social account with that id. Use an id from list_social_accounts.',
        'media_not_found' => 'No uploaded media with that id. Use an id from complete_media_upload.',
        'scheduled_at_offset' => 'The schedule time needs a UTC offset, e.g. 2026-10-09T18:00:00+07:00 or 2026-10-09T11:00:00Z.',
        'content_type_platform' => 'The :type Content type is not available on :account. Use one of its content_types from list_social_accounts.',
        'tiktok_privacy_required' => 'Choose a privacy_level for :account. Until the TikTok app passes its audit, only SELF_ONLY ("Only me") works.',
        'goes_out_now' => 'It is publishing now. Check its result with get_post.',
        'goes_out_at' => 'It will publish at :time.',
        'goes_out_never' => 'It is a draft and will not publish until it is scheduled in the composer.',
    ],
    'upload' => [
        'not_object_storage' => 'Uploads from an Assistant need object storage (for example Cloudflare R2 or S3) as the default disk. Upload the file in the composer instead.',
    ],
];
