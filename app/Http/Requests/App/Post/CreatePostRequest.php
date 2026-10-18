<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Opens the composer for a new Post (ADR 0011): optionally on a calendar day,
 * or pre-filled from a Post being duplicated. Nothing is saved.
 */
class CreatePostRequest extends FormRequest
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
            'date' => ['nullable', 'date_format:Y-m-d'],
            'duplicate' => [
                'nullable',
                'uuid',
                Rule::exists('posts', 'id')->where('workspace_id', $this->user()->currentWorkspace?->id),
            ],
        ];
    }

    /**
     * The Post the new one copies, or null for an empty composer.
     */
    public function original(): ?Post
    {
        $id = $this->validated('duplicate');

        return $id === null ? null : Post::query()->find($id);
    }
}
