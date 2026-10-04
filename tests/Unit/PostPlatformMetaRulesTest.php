<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\TikTok\PrivacyLevel;
use App\Support\PostPlatformMetaRules;
use Illuminate\Support\Facades\Validator;

test('there are no custom meta messages', function () {
    expect(PostPlatformMetaRules::messages())->toBe([]);
});

test('custom meta attributes use translated field names', function () {
    expect(PostPlatformMetaRules::attributes())->toBe([
        'platforms.*.meta.description' => __('posts.form.youtube.description'),
    ]);
});

test('shared description validation rejects multibyte overflow', function () {
    $validator = Validator::make(['platforms' => [['meta' => ['description' => str_repeat('é', 2501)]]]], PostPlatformMetaRules::rules());
    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('platforms.0.meta.description'))->toBeTrue();
});

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
        'platforms.*.meta.description',
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
