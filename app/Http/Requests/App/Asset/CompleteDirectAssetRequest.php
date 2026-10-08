<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Asset;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registers a file the browser has finished uploading to its presigned URL.
 */
class CompleteDirectAssetRequest extends FormRequest
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
            // Capped at a day so `1e999` (INF, which JSON cannot encode) never reaches the meta column.
            'duration' => ['nullable', 'numeric', 'min:0', 'max:86400'],
        ];
    }

    public function duration(): ?float
    {
        return transform($this->validated('duration'), fn (mixed $seconds) => (float) $seconds);
    }
}
