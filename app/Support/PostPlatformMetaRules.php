<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PostPlatform\AspectRatio;
use App\Enums\SocialAccount\Platform;
use App\Enums\TikTok\PrivacyLevel;
use App\Models\Post;
use App\Models\PostPlatform;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Single source of truth for per-platform `PostPlatform.meta` validation, shared
 * by the web, public REST API, and MCP post create/update flows so every entry
 * point accepts the same per-platform settings and enforces the same
 * required-on-publish rules.
 */
class PostPlatformMetaRules
{
    /**
     * Validation rules for `platforms.*.meta` and all its per-platform sub-keys.
     * Spread into a FormRequest/MCP tool rule set as the complete meta contract.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'platforms.*.meta' => ['sometimes', 'nullable', 'array'],

            // Facebook
            'platforms.*.meta.aspect_ratio' => ['sometimes', 'nullable', 'string', Rule::enum(AspectRatio::class)],

            // TikTok
            'platforms.*.meta.privacy_level' => ['sometimes', 'nullable', 'string', Rule::enum(PrivacyLevel::class)],
            'platforms.*.meta.auto_add_music' => ['sometimes', 'boolean'],
            'platforms.*.meta.allow_comments' => ['sometimes', 'boolean'],
            'platforms.*.meta.allow_duet' => ['sometimes', 'boolean'],
            'platforms.*.meta.allow_stitch' => ['sometimes', 'boolean'],
            'platforms.*.meta.is_aigc' => ['sometimes', 'boolean'],
            'platforms.*.meta.disclose' => ['sometimes', 'boolean'],
            'platforms.*.meta.brand_content_toggle' => ['sometimes', 'boolean'],
            'platforms.*.meta.brand_organic_toggle' => ['sometimes', 'boolean'],

            // Facebook Reel and YouTube text. Platform-specific limits live in
            // textLimitViolations(); this cap matches the content's own.
            'platforms.*.meta.title' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'platforms.*.meta.description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * Custom validation messages for meta fields shown in the UI.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [];
    }

    /**
     * Friendly attribute names so default messages never expose `platforms.0.meta.*`.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'platforms.*.meta.title' => __('posts.form.youtube.title'),
            'platforms.*.meta.description' => __('posts.form.youtube.description'),
        ];
    }

    /**
     * Adds validation errors for per-platform meta that becomes mandatory once a
     * post is published or scheduled (TikTok privacy, YouTube description),
     * based on the submitted request platforms. The caller resolves each
     * platform row to its Platform enum, since that lookup differs between create
     * (by social account) and update (by post platform).
     *
     * @param  array<int, mixed>  $platforms
     * @param  callable(mixed, int): ?Platform  $resolvePlatform
     */
    public static function addRequiredOnPublishErrors(Validator $validator, array $platforms, callable $resolvePlatform): void
    {
        foreach ($platforms as $index => $platform) {
            $violation = self::requiredMetaViolation($resolvePlatform($platform, $index), data_get($platform, 'meta'));

            if ($violation !== null) {
                [$field, $message] = $violation;
                $key = "platforms.{$index}.meta.{$field}";

                if (! $validator->errors()->has($key)) {
                    $validator->errors()->add($key, $message);
                }
            }
        }
    }

    /**
     * Adds validation errors for Titles, Descriptions and captions that break
     * their Platform's length or character limits. Unlike the required-on-publish
     * meta, these hold for drafts too, so a saved Post never fails at publish
     * time over text length.
     *
     * @param  array<int, mixed>  $platforms
     * @param  callable(mixed, int): ?Platform  $resolvePlatform
     */
    public static function addTextLimitErrors(Validator $validator, array $platforms, callable $resolvePlatform): void
    {
        foreach ($platforms as $index => $platform) {
            foreach (self::textLimitViolations($resolvePlatform($platform, $index), data_get($platform, 'meta')) as $field => $message) {
                $key = "platforms.{$index}.meta.{$field}";

                if (! $validator->errors()->has($key)) {
                    $validator->errors()->add($key, $message);
                }
            }
        }
    }

    /**
     * Per-Platform text limits, taken from each Platform's API docs. Meta
     * documents no limit for a Facebook Reel's Title or Description, so only
     * the shared cap in rules() applies there.
     *
     * @return array<string, string> field => message
     */
    public static function textLimitViolations(?Platform $platform, mixed $meta): array
    {
        $violations = match ($platform) {
            Platform::YouTube => [
                'title' => YouTubeTitle::violation(data_get($meta, 'title')),
                'description' => YouTubeDescription::violation(data_get($meta, 'description')),
            ],
            default => [],
        };

        return array_map(fn (string $key): string => __($key), array_filter($violations));
    }

    /**
     * Whether the Post's content counts against the Platform's content cap
     * (`Platform::maxContentLength()`). A YouTube Title replaces the content as
     * the video title, so the 100-character cap on the content no longer applies.
     */
    public static function contentLimitApplies(Platform $platform, mixed $meta): bool
    {
        return match ($platform) {
            Platform::YouTube => YouTubeTitle::custom($meta) === null,
            default => true,
        };
    }

    /**
     * Asserts that every ENABLED platform already stored on a post has the meta it
     * needs to publish. Used by entry points that publish a post's stored state
     * without resubmitting platforms (e.g. the MCP publish tool), so a misconfigured
     * post fails fast with a clear message instead of only at publish time.
     *
     * @param  array<int, string>  $platformIds  Submitted order for indexed errors.
     *
     * @throws ValidationException
     */
    public static function assertStoredPostPublishable(Post $post, array $platformIds = []): void
    {
        $platforms = $post->postPlatforms()->enabled()->get()->values();

        if ($platformIds !== []) {
            $platformsById = $platforms->keyBy('id');
            $platforms = collect($platformIds)
                ->map(fn (string $id): ?PostPlatform => $platformsById->get($id))
                ->filter();
        }

        $errors = [];

        foreach ($platforms as $index => $postPlatform) {
            $violation = self::requiredMetaViolation($postPlatform->platform, $postPlatform->meta);

            if ($violation !== null) {
                [$field, $message] = $violation;
                $errors["platforms.{$index}.meta.{$field}"] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The missing required meta field for a platform about to publish, or null when
     * nothing is missing. Single source of "what each platform requires to publish".
     *
     * @return array{0: string, 1: string}|null [field, message]
     */
    public static function requiredMetaViolation(?Platform $platform, mixed $meta): ?array
    {
        return match ($platform) {
            Platform::YouTube => self::youtubeDescriptionViolation($meta),
            Platform::TikTok => self::tiktokPrivacyViolation($meta),
            default => null,
        };
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function youtubeDescriptionViolation(mixed $meta): ?array
    {
        $key = YouTubeDescription::violation(data_get($meta, 'description'));

        return $key === null ? null : ['description', __($key)];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private static function tiktokPrivacyViolation(mixed $meta): ?array
    {
        $privacyLevel = PrivacyLevel::tryFrom((string) data_get($meta, 'privacy_level'));

        if ($privacyLevel === null) {
            return ['privacy_level', trans('posts.form.tiktok.privacy_required')];
        }

        if ($privacyLevel === PrivacyLevel::SelfOnly && data_get($meta, 'brand_content_toggle')) {
            return ['privacy_level', trans('posts.form.tiktok.privacy.private_disabled_branded')];
        }

        return null;
    }
}
