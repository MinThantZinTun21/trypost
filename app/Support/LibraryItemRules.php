<?php

declare(strict_types=1);

namespace App\Support;

use App\Dto\MediaItem;
use App\Enums\Media\Type;
use App\Enums\SocialAccount\Platform;
use App\Models\Media;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The one place that validates a Library item, shared by the web FormRequests
 * and the MCP tools. Each platform text has the limit the composer applies to
 * the same text on a Post, so an item can always be scheduled as written.
 */
class LibraryItemRules
{
    public const int NAME_MAX = 255;

    /**
     * Enough images for a Facebook Post, the only Content type that takes several.
     */
    public const int MEDIA_MAX = 10;

    /**
     * @param  Collection<string, Media>  $assets  the workspace's assets, keyed by id
     * @return array<string, array<int, mixed>>
     */
    public static function rules(Collection $assets): array
    {
        return [
            'name' => ['required', 'string', 'max:'.self::NAME_MAX],
            'facebook_text' => ['nullable', 'string', 'max:'.Platform::Facebook->maxContentLength()],
            'tiktok_caption' => ['nullable', self::text(fn (mixed $caption): ?string => TikTokText::violation($caption, TikTokText::CAPTION_MAX, 'posts.form.tiktok.caption_max'))],
            'youtube_title' => ['nullable', self::text(YouTubeTitle::violation(...))],
            'youtube_description' => ['nullable', self::text(YouTubeDescription::violation(...))],
            'youtube_tags' => ['nullable', self::text(YouTubeTags::violation(...))],
            'media_ids' => ['nullable', 'array', 'max:'.self::MEDIA_MAX, self::mediaMix($assets)],
            'media_ids.*' => ['string', 'distinct', Rule::in($assets->keys()->all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'media_ids.*.in' => __('library.validation.media_not_found'),
            'media_ids.max' => __('library.validation.media_max', ['max' => self::MEDIA_MAX]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => __('library.form.name'),
            'facebook_text' => __('library.form.facebook_text'),
            'tiktok_caption' => __('library.form.tiktok_caption'),
            'youtube_title' => __('library.form.youtube_title'),
            'youtube_description' => __('library.form.youtube_description'),
            'youtube_tags' => __('library.form.youtube_tags'),
            'media_ids' => __('library.form.media'),
        ];
    }

    /**
     * A rule that fails with the translation key a platform text check returns.
     *
     * @param  Closure(mixed): ?string  $violation
     */
    private static function text(Closure $violation): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($violation): void {
            $key = $violation($value);

            if ($key !== null) {
                $fail($key)->translate();
            }
        };
    }

    /**
     * One video, or images only; documents are never posted.
     *
     * @param  Collection<string, Media>  $assets
     */
    private static function mediaMix(Collection $assets): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($assets): void {
            $kinds = collect(is_array($value) ? $value : [])
                ->map(fn (mixed $id): ?Media => is_string($id) ? $assets->get($id) : null)
                ->filter()
                ->map(fn (Media $media): ?Type => MediaItem::fromMedia($media)->kind());

            if ($kinds->contains(Type::Document)) {
                $fail('library.validation.media_document')->translate();

                return;
            }

            if ($kinds->contains(Type::Video) && $kinds->count() > 1) {
                $fail('library.validation.media_mix')->translate();
            }
        };
    }
}
