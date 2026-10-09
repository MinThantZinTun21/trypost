<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Insights;

use App\Enums\Insights\Range;
use App\Enums\SocialAccount\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowInsightsRequest extends FormRequest
{
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
            'account' => [
                'nullable',
                'uuid',
                Rule::exists('social_accounts', 'id')
                    ->where('workspace_id', $this->user()->currentWorkspace?->id)
                    ->where('platform', Platform::Facebook->value),
            ],
            'range' => ['nullable', 'integer', Rule::in(Range::values())],
        ];
    }

    public function range(): Range
    {
        return Range::tryFrom($this->integer('range')) ?? Range::DEFAULT;
    }
}
