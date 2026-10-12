<?php

declare(strict_types=1);

return [
    'title' => 'Content library',
    'description' => 'Ready-made content with its Facebook text, TikTok caption and YouTube details, waiting to be scheduled.',
    'new_item' => 'New item',
    'created_via' => [
        'web' => 'Added by you',
        'mcp' => 'From the Assistant',
    ],
    'empty' => [
        'title' => 'Your library is empty',
        'description' => 'Upload a video or images with their texts, or ask your Assistant to add some.',
    ],
    'table' => [
        'item' => 'Item',
        'platforms' => 'Texts',
        'added' => 'Added',
    ],
    'media_count' => '{0} No media|{1} 1 file|[2,*] :count files',
    'texts' => [
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
    ],
    'actions' => [
        'edit' => 'Edit',
        'delete' => 'Delete',
    ],
    'delete_modal' => [
        'title' => 'Delete this item?',
        'description' => 'The item leaves the library. Its files stay in your uploads and Posts made from it are kept.',
        'action' => 'Delete item',
        'cancel' => 'Cancel',
    ],
    'flash' => [
        'deleted' => 'Library item deleted.',
    ],
    'form' => [
        'create_title' => 'New library item',
        'edit_title' => 'Edit library item',
        'name' => 'Name',
        'name_placeholder' => 'What is this content?',
        'media' => 'Media',
        'media_hint' => 'One video, or up to :max images.',
        'upload' => 'Upload files',
        'uploading' => 'Uploading… :progress%',
        'remove_media' => 'Remove',
        'facebook_text' => 'Facebook text',
        'facebook_text_placeholder' => 'The text of the Facebook Post',
        'tiktok_caption' => 'TikTok caption',
        'tiktok_caption_placeholder' => 'Caption with #hashtags',
        'youtube_title' => 'YouTube title',
        'youtube_title_placeholder' => 'Title of the Short',
        'youtube_description' => 'YouTube description',
        'youtube_description_placeholder' => 'Description of the Short',
        'youtube_tags' => 'YouTube tags',
        'youtube_tags_placeholder' => 'cooking, quick dinner, ramen',
        'save' => 'Save item',
        'cancel' => 'Cancel',
    ],
    'used' => '{0} Not used yet|{1} Used once|[2,*] Used :count times',
    'schedule' => [
        'action' => 'Schedule',
        'title' => 'Schedule from the library',
        'description' => 'Pick where to post. A draft opens in the editor with each platform\'s text filled in, ready to schedule.',
        'no_accounts' => 'Connect a Social account first.',
        'submit' => 'Open in editor',
        'cancel' => 'Cancel',
        'accounts_required' => 'Pick at least one Social account.',
        'account_not_found' => 'That Social account is not connected.',
    ],
    'validation' => [
        'media_not_found' => 'That file is not in your uploads.',
        'media_max' => 'A library item holds at most :max files.',
        'media_mix' => 'Use one video, or images only.',
        'media_document' => 'Documents cannot be posted. Use a video or images.',
    ],
];
