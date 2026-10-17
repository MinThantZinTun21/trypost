<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Enums\Mobile\PostTab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPostsRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(PostTab::class)],
            'account' => [
                'nullable',
                'uuid',
                Rule::exists('social_accounts', 'id')->where('workspace_id', $this->user()->resolveCurrentWorkspace()?->id),
            ],
        ];
    }

    public function tab(): PostTab
    {
        return PostTab::from((string) $this->validated('status'));
    }

    /**
     * The Social account the list is filtered to, or null for all of them.
     */
    public function accountId(): ?string
    {
        return $this->validated('account');
    }
}
