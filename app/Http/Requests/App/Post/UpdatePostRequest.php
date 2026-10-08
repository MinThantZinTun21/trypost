<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use App\Enums\Post\Status;
use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Models\PostPlatform;
use App\Rules\ContentFitsPlatformLimits;
use App\Rules\ContentTypeCompatibleWithMedia;
use App\Support\PostMediaRules;
use App\Support\PostPlatformMetaRules;
use App\Support\PostStatusRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $status = $this->input('status');

        $enforcesMediaCompatibility = in_array(
            $status,
            [Status::Scheduled->value, Status::Publishing->value],
            true,
        );

        return [
            'status' => ['required', 'string', Rule::in([Status::Draft->value, Status::Scheduled->value, Status::Publishing->value])],
            'content' => [
                'nullable',
                'string',
                'max:10000',
                Rule::when(
                    $enforcesMediaCompatibility,
                    [new ContentFitsPlatformLimits($this->resolveContentLimitedPlatforms())]
                ),
            ],
            ...PostMediaRules::rules(hosted: true),
            'scheduled_at' => PostStatusRules::scheduledAtRules($this->route('post'), $status),
            'platforms' => ['sometimes', 'array'],
            'platforms.*.id' => ['required', 'uuid', Rule::exists('post_platforms', 'id')->where('post_id', $this->route('post')->id)],
            'platforms.*.content_type' => [
                $enforcesMediaCompatibility ? 'required' : 'sometimes',
                'string',
                Rule::in(array_column(ContentType::cases(), 'value')),
                Rule::when($enforcesMediaCompatibility, [new ContentTypeCompatibleWithMedia]),
            ],
            ...PostPlatformMetaRules::rules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return PostPlatformMetaRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return PostPlatformMetaRules::attributes();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $platforms = $this->input('platforms', []);
            $platformsById = $this->resolveSelectedPlatforms();
            $resolvePlatform = fn ($platform) => $platformsById[data_get($platform, 'id')] ?? null;

            PostPlatformMetaRules::addTextLimitErrors($validator, $platforms, $resolvePlatform);

            if ($this->isPublishingOrScheduling()) {
                PostPlatformMetaRules::addRequiredOnPublishErrors($validator, $platforms, $resolvePlatform);
            }
        });
    }

    private function isPublishingOrScheduling(): bool
    {
        return in_array(
            $this->input('status'),
            [Status::Scheduled->value, Status::Publishing->value],
            true,
        );
    }

    /**
     * The selected platforms whose content cap applies, judged on the meta the
     * update will store (stored meta merged with the submitted meta, as
     * UpdatePost merges it).
     *
     * @return Collection<int|string, Platform>
     */
    private function resolveContentLimitedPlatforms(): Collection
    {
        $submittedMeta = collect($this->input('platforms', []))
            ->filter(fn ($platform) => is_array($platform) && data_get($platform, 'id'))
            ->mapWithKeys(fn (array $platform) => [data_get($platform, 'id') => data_get($platform, 'meta')]);

        if ($submittedMeta->isEmpty()) {
            return collect();
        }

        return $this->route('post')
            ->postPlatforms()
            ->whereIn('id', $submittedMeta->keys()->all())
            ->get(['id', 'platform', 'meta'])
            ->filter(fn (PostPlatform $postPlatform): bool => PostPlatformMetaRules::contentLimitApplies(
                $postPlatform->platform,
                array_merge($postPlatform->meta ?? [], is_array($submittedMeta[$postPlatform->id]) ? $submittedMeta[$postPlatform->id] : []),
            ))
            ->pluck('platform', 'id');
    }

    /**
     * @return Collection<int|string, Platform>
     */
    private function resolveSelectedPlatforms(): Collection
    {
        $ids = collect($this->input('platforms', []))->pluck('id')->filter()->all();
        if (empty($ids)) {
            return collect();
        }

        return $this->route('post')
            ->postPlatforms()
            ->whereIn('id', $ids)
            ->pluck('platform', 'id');
    }
}
