<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\TikTok\PrivacyLevel;
use App\Support\PostPlatformMetaRules;
use Illuminate\Support\Facades\Validator;

test('there are no custom meta messages', function () {
    expect(PostPlatformMetaRules::messages())->toBe([]);
});

test('custom meta attributes use translated field names', function () {
    expect(PostPlatformMetaRules::attributes())->toBe([
        'platforms.*.meta.title' => __('posts.form.youtube.title'),
        'platforms.*.meta.caption' => __('posts.form.tiktok.caption'),
        'platforms.*.meta.description' => __('posts.form.youtube.description'),
    ]);
});

test('youtube description text limit rejects multibyte overflow', function () {
    expect(PostPlatformMetaRules::textLimitViolations(Platform::YouTube, ['description' => str_repeat('é', 2501)]))
        ->toBe(['description' => __('posts.form.youtube.description_max')]);
});

test('youtube content standing in for the description must fit the description limit once a title lifts the content cap', function (array $meta, ?string $key) {
    $violations = PostPlatformMetaRules::textLimitViolations(Platform::YouTube, $meta, str_repeat('é', 2501));

    expect(data_get($violations, 'description'))->toBe($key === null ? null : __($key));
})->with([
    'title, no description' => [['title' => 'My Short'], 'posts.form.youtube.description_from_content_max'],
    'title and description' => [['title' => 'My Short', 'description' => 'Short and sweet'], null],
    'no title, so the content cap applies instead' => [[], null],
]);

test('tiktok text limits only check the text the content type publishes', function (ContentType $contentType, array $expected) {
    $meta = ['caption' => str_repeat('a', 2201), 'title' => str_repeat('a', 91)];

    expect(array_keys(PostPlatformMetaRules::textLimitViolations(Platform::TikTok, $meta, null, $contentType)))->toBe($expected);
})->with([
    'video checks the caption' => [ContentType::TikTokVideo, ['caption']],
    'photo checks the title' => [ContentType::TikTokPhoto, ['title']],
]);

test('facebook reel text gets the shared cap only, since meta documents no limit', function () {
    $meta = ['title' => str_repeat('é', 3000), 'description' => str_repeat('é', 6000)];

    expect(PostPlatformMetaRules::textLimitViolations(Platform::Facebook, $meta))->toBe([])
        ->and(Validator::make(['platforms' => [['meta' => $meta]]], PostPlatformMetaRules::rules())->passes())->toBeTrue();
});

test('shared text rules cap titles and descriptions at the content limit', function (string $field) {
    $validator = Validator::make(['platforms' => [['meta' => [$field => str_repeat('a', 10001)]]]], PostPlatformMetaRules::rules());

    expect($validator->errors()->has("platforms.0.meta.{$field}"))->toBeTrue();
})->with(['title', 'description', 'caption']);

test('stored youtube description is checked without requiring it for other networks', function () {
    expect(PostPlatformMetaRules::requiredMetaViolation(Platform::YouTube, ['description' => str_repeat('a', 5001)]))
        ->toBe(['description', __('posts.form.youtube.description_max')])
        ->and(PostPlatformMetaRules::requiredMetaViolation(Platform::YouTube, []))->toBeNull()
        ->and(PostPlatformMetaRules::requiredMetaViolation(Platform::Facebook, []))->toBeNull();
});

test('shared meta rules cover the facebook, tiktok and youtube fields only', function () {
    expect(array_keys(PostPlatformMetaRules::rules()))->toEqualCanonicalizing([
        'platforms.*.meta',
        'platforms.*.meta.aspect_ratio',
        'platforms.*.meta.privacy_level',
        'platforms.*.meta.auto_add_music',
        'platforms.*.meta.allow_comments',
        'platforms.*.meta.allow_duet',
        'platforms.*.meta.allow_stitch',
        'platforms.*.meta.is_aigc',
        'platforms.*.meta.disclose',
        'platforms.*.meta.brand_content_toggle',
        'platforms.*.meta.brand_organic_toggle',
        'platforms.*.meta.title',
        'platforms.*.meta.description',
        'platforms.*.meta.caption',
    ]);
});

test('tiktok required meta treats missing and unknown privacy as unpublished', function (?array $meta) {
    expect(PostPlatformMetaRules::requiredMetaViolation(Platform::TikTok, $meta))
        ->toBe(['privacy_level', trans('posts.form.tiktok.privacy_required')]);
})->with([
    'missing' => [[]],
    'blank' => [['privacy_level' => '']],
    'unknown' => [['privacy_level' => 'EVERYONE']],
]);

test('tiktok required meta accepts every privacy level enum value', function (PrivacyLevel $level) {
    expect(PostPlatformMetaRules::requiredMetaViolation(Platform::TikTok, [
        'privacy_level' => $level->value,
    ]))->toBeNull();
})->with(PrivacyLevel::cases());

test('tiktok required meta rejects self only branded content', function () {
    expect(PostPlatformMetaRules::requiredMetaViolation(Platform::TikTok, [
        'privacy_level' => PrivacyLevel::SelfOnly->value,
        'brand_content_toggle' => true,
    ]))->toBe(['privacy_level', trans('posts.form.tiktok.privacy.private_disabled_branded')]);
});

test('youtube title text limits follow the youtube data api', function (mixed $title, ?string $key) {
    expect(PostPlatformMetaRules::textLimitViolations(Platform::YouTube, ['title' => $title]))
        ->toBe($key === null ? [] : ['title' => __($key)]);
})->with([
    'missing' => [null, null],
    'one hundred characters' => [str_repeat('é', 100), null],
    'over one hundred characters' => [str_repeat('é', 101), 'posts.form.youtube.title_max'],
    'less than sign' => ['a < b', 'posts.form.youtube.title_angle_brackets'],
    'greater than sign' => ['a > b', 'posts.form.youtube.title_angle_brackets'],
    'not text' => [['title'], 'posts.form.youtube.title_invalid'],
]);

test('youtube title limits do not apply to other platforms', function (Platform $platform) {
    expect(PostPlatformMetaRules::textLimitViolations($platform, ['title' => str_repeat('<', 90)]))->toBe([]);
})->with([Platform::Facebook, Platform::TikTok]);

test('tiktok text limits count utf-16 runes as the content posting api does', function (string $field, string $text, ?string $key) {
    expect(PostPlatformMetaRules::textLimitViolations(Platform::TikTok, [$field => $text]))
        ->toBe($key === null ? [] : [$field => __($key)]);
})->with([
    'caption at the limit' => ['caption', str_repeat('a', 2200), null],
    'caption over the limit' => ['caption', str_repeat('a', 2201), 'posts.form.tiktok.caption_max'],
    'caption emoji count twice' => ['caption', str_repeat('😀', 1101), 'posts.form.tiktok.caption_max'],
    'photo title at the limit' => ['title', str_repeat('é', 90), null],
    'photo title over the limit' => ['title', str_repeat('a', 91), 'posts.form.tiktok.photo_title_max'],
    'photo description at the limit' => ['description', str_repeat('a', 4000), null],
    'photo description over the limit' => ['description', str_repeat('😀', 2001), 'posts.form.tiktok.photo_description_max'],
]);

test('replacement text lifts the content cap only for the content type that sends it', function (Platform $platform, ?ContentType $contentType, array $meta, bool $applies) {
    expect(PostPlatformMetaRules::contentLimitApplies($platform, $contentType, $meta))->toBe($applies);
})->with([
    'tiktok video with caption' => [Platform::TikTok, ContentType::TikTokVideo, ['caption' => 'Caption'], false],
    'tiktok video with blank caption' => [Platform::TikTok, ContentType::TikTokVideo, ['caption' => '  '], true],
    'tiktok video with photo description' => [Platform::TikTok, ContentType::TikTokVideo, ['description' => 'Text'], true],
    'tiktok photo with description' => [Platform::TikTok, ContentType::TikTokPhoto, ['description' => 'Text'], false],
    'tiktok photo with caption' => [Platform::TikTok, ContentType::TikTokPhoto, ['caption' => 'Caption'], true],
    'youtube with title' => [Platform::YouTube, ContentType::YouTubeShort, ['title' => 'Title'], false],
    'facebook reel with description' => [Platform::Facebook, ContentType::FacebookReel, ['description' => 'Text'], true],
]);
