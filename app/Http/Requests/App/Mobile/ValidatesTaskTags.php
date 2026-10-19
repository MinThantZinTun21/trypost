<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Task tag ids a request may name: only those in the Owner's workspace.
 */
trait ValidatesTaskTags
{
    protected function taskTagInWorkspace(): Exists
    {
        $tagGroupIds = $this->user()->resolveCurrentWorkspace()?->tagGroups()->pluck('id')->all() ?? [];

        return Rule::exists('task_tags', 'id')->whereIn('tag_group_id', $tagGroupIds);
    }
}
