<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Models\Post;
use App\Models\PostPlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The texts the Android app edits: the Post content and each enabled Post
 * platform's Title, Description, caption and Tags. Lengths and the publish
 * checks are the composer's, run by UpdatePostTexts.
 */
class UpdatePostTextsRequest extends FormRequest
{
    public const TEXT_FIELDS = ['title', 'description', 'caption', 'tags'];

    /**
     * @var Collection<string, PostPlatform>|null
     */
    private ?Collection $enabledPlatforms = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'nullable', 'string'],
            'platforms' => ['sometimes', 'array'],
            'platforms.*.id' => ['required', 'string', 'distinct', Rule::in($this->enabledPlatforms()->keys()->all())],
            'platforms.*.meta' => ['sometimes', 'array'],
            'platforms.*.meta.title' => ['sometimes', 'nullable', 'string'],
            'platforms.*.meta.description' => ['sometimes', 'nullable', 'string'],
            'platforms.*.meta.caption' => ['sometimes', 'nullable', 'string'],
            'platforms.*.meta.tags' => ['sometimes', 'nullable', 'array'],
            'platforms.*.meta.tags.*' => ['string'],
        ];
    }

    /**
     * Refuses a text the Post platform's Content type does not publish, as the
     * MCP create_post tool does, rather than storing text that never goes out.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('platforms', []) as $index => $platform) {
                $contentType = $this->enabledPlatforms()->get((string) data_get($platform, 'id'))?->content_type;

                if ($contentType === null) {
                    continue;
                }

                foreach (array_keys((array) data_get($platform, 'meta', [])) as $field) {
                    if (in_array($field, self::TEXT_FIELDS, true) && ! in_array($field, $contentType->textFields(), true)) {
                        $validator->errors()->add("platforms.{$index}.meta.{$field}", __('mobile.text_field_unused', [
                            'type' => $contentType->label(),
                            'field' => $field,
                        ]));
                    }
                }
            }
        });
    }

    /**
     * @return array<string, array<string, mixed>> the submitted texts keyed by Post platform id
     */
    public function platformTexts(): array
    {
        return collect($this->validated('platforms', []))
            ->mapWithKeys(fn (array $platform): array => [
                (string) data_get($platform, 'id') => array_intersect_key((array) data_get($platform, 'meta', []), array_flip(self::TEXT_FIELDS)),
            ])
            ->all();
    }

    /**
     * @return Collection<string, PostPlatform>
     */
    private function enabledPlatforms(): Collection
    {
        /** @var Post $post */
        $post = $this->route('post');

        return $this->enabledPlatforms ??= $post->postPlatforms()->enabled()->get(['id', 'content_type'])->keyBy('id');
    }
}
