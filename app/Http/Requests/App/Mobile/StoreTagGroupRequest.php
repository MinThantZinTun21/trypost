<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Enums\Task\TagColor;
use App\Models\TagGroup;
use App\Rules\UniqueNameIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTagGroupRequest extends FormRequest
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
        $tagGroup = $this->route('tagGroup');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                new UniqueNameIgnoringCase(
                    TagGroup::query()->where('workspace_id', $this->user()->resolveCurrentWorkspace()?->id),
                    $tagGroup instanceof TagGroup ? $tagGroup->id : null,
                ),
            ],
            'color' => ['required', 'string', Rule::enum(TagColor::class)],
        ];
    }
}
