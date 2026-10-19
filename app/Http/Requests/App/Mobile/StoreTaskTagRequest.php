<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Models\TagGroup;
use App\Models\TaskTag;
use App\Rules\UniqueNameIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskTagRequest extends FormRequest
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
        $taskTag = $this->route('taskTag');
        $tagGroup = $taskTag instanceof TaskTag ? $taskTag->tagGroup : $this->route('tagGroup');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                new UniqueNameIgnoringCase(
                    TaskTag::query()->where('tag_group_id', $tagGroup instanceof TagGroup ? $tagGroup->id : null),
                    $taskTag instanceof TaskTag ? $taskTag->id : null,
                ),
            ],
        ];
    }
}
