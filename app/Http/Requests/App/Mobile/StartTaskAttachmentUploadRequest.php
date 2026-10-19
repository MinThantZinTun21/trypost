<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Enums\Media\Type as MediaType;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Asks for a presigned URL to upload one Task attachment straight to object
 * storage: a photo, a video or audio within its cap, while the Task has room
 * for another.
 */
class StartTaskAttachmentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'file_name' => strtolower((string) $this->input('file_name', '')),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $suffixes = collect(Task::attachmentMediaTypes())
            ->flatMap(fn (MediaType $type): array => $type->extensions())
            ->map(fn (string $extension): string => ".{$extension}")
            ->all();

        return [
            'file_name' => ['required', 'string', 'max:255', 'ends_with:'.implode(',', $suffixes)],
            'size' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file_name.ends_with' => __('tasks.attachment_unsupported'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $type = MediaType::classify(null, (string) $this->input('file_name'));
                $maxMb = $type === null ? 0 : Task::attachmentMaxSizeInMb($type);
                if ((int) $this->input('size') > $maxMb * 1024 * 1024) {
                    $validator->errors()->add('size', __('assets.upload.file_too_large', ['max' => $maxMb]));
                }

                /** @var Task $task */
                $task = $this->route('task');
                $maxCount = (int) config('trypost.tasks.attachments.max_count');
                if ($task->attachments()->count() >= $maxCount) {
                    $validator->errors()->add('file_name', __('tasks.attachments_full', ['max' => $maxCount]));
                }
            },
        ];
    }
}
