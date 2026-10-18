<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Enums\Media\Type as MediaType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asks for a presigned URL to upload one Video's file straight to object
 * storage, with the composer's video size limit.
 */
class StartVideoUploadRequest extends FormRequest
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
        $videoSuffixes = collect(MediaType::Video->extensions())->map(fn (string $extension): string => ".{$extension}")->all();

        return [
            'file_name' => ['required', 'string', 'max:255', 'ends_with:'.implode(',', $videoSuffixes)],
            'size' => ['required', 'integer', 'min:1', 'max:'.MediaType::Video->maxSizeInBytes()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file_name.ends_with' => __('videos.not_a_video'),
            'size.max' => __('assets.upload.file_too_large', ['max' => MediaType::Video->maxSizeInMb()]),
        ];
    }
}
