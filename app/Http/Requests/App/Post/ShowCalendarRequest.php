<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowCalendarRequest extends FormRequest
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
                    ->where('workspace_id', $this->user()->currentWorkspace?->id),
            ],
        ];
    }

    /**
     * The Social account the calendar is filtered to, or null for all of them.
     */
    public function accountId(): ?string
    {
        return $this->validated('account');
    }
}
