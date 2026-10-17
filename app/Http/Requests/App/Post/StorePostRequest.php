<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use App\Enums\Post\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saves a new Post from the composer (ADR 0011). Only what the composer's
 * update rules cannot check yet is checked here: that it goes out, and to
 * which of the workspace's active Social accounts. CreateComposedPost then
 * runs the composer's own rules on the whole payload.
 */
class StorePostRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in([Status::Scheduled->value, Status::Publishing->value])],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*.social_account_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('social_accounts', 'id')
                    ->where('workspace_id', $this->user()->currentWorkspace?->id)
                    ->where('is_active', true),
            ],
        ];
    }

    /**
     * The composer payload for CreateComposedPost.
     *
     * @return array<string, mixed>
     */
    public function composerPayload(): array
    {
        return [
            ...$this->only(['status', 'content', 'media', 'scheduled_at']),
            'platforms' => array_values((array) $this->input('platforms', [])),
        ];
    }
}
