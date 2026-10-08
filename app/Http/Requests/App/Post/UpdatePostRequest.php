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
    /**
     * @var array<int, mixed>|null
     */
    private ?array $platformsAsStored = null;

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
                'max:'.PostPlatformMetaRules::TEXT_MAX_LENGTH,
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

            $content = $this->has('content') ? $this->input('content') : $this->route('post')->content;

            PostPlatformMetaRules::addTextLimitErrors(
                $validator,
                $this->platformsAsStored(),
                $resolvePlatform,
                is_string($content) ? $content : null,
            );

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
     * update will store.
     *
     * @return Collection<int|string, Platform>
     */
    private function resolveContentLimitedPlatforms(): Collection
    {
        $submitted = collect($this->platformsAsStored())
            ->filter(fn ($platform) => is_array($platform) && data_get($platform, 'id'))
            ->keyBy(fn (array $platform) => data_get($platform, 'id'));

        if ($submitted->isEmpty()) {
            return collect();
        }

        return $this->route('post')
            ->postPlatforms()
            ->whereIn('id', $submitted->keys()->all())
            ->get(['id', 'platform'])
            ->filter(fn (PostPlatform $postPlatform): bool => PostPlatformMetaRules::contentLimitApplies(
                $postPlatform->platform,
                ContentType::tryFrom((string) data_get($submitted->get($postPlatform->id), 'content_type')),
                data_get($submitted->get($postPlatform->id), 'meta'),
            ))
            ->pluck('platform', 'id');
    }

    /**
     * The submitted platforms as the update will store them: the stored meta
     * merged with the submitted meta, as UpdatePost merges it, and the
     * submitted Content type, else the stored one.
     *
     * @return array<int, mixed>
     */
    private function platformsAsStored(): array
    {
        if ($this->platformsAsStored !== null) {
            return $this->platformsAsStored;
        }

        $platforms = $this->input('platforms', []);

        if (! is_array($platforms)) {
            return $this->platformsAsStored = [];
        }

        $stored = $this->route('post')
            ->postPlatforms()
            ->whereIn('id', collect($platforms)->map(fn ($platform) => data_get($platform, 'id'))->filter()->all())
            ->get(['id', 'meta', 'content_type'])
            ->keyBy('id');

        return $this->platformsAsStored = collect($platforms)->map(function (mixed $platform) use ($stored): mixed {
            if (! is_array($platform)) {
                return $platform;
            }

            $submittedMeta = data_get($platform, 'meta');
            $storedPlatform = $stored->get(data_get($platform, 'id'));

            return [
                ...$platform,
                'content_type' => data_get($platform, 'content_type') ?? $storedPlatform?->content_type?->value,
                'meta' => array_filter(
                    array_merge($storedPlatform->meta ?? [], is_array($submittedMeta) ? $submittedMeta : []),
                    fn (mixed $value): bool => $value !== null,
                ),
            ];
        })->all();
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
