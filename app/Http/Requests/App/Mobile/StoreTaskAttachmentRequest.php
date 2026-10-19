<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registers a finished upload as one of the Task's attachments.
 */
class StoreTaskAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'upload_id' => ['required', 'string', 'uuid'],
        ];
    }
}
