<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Asset;

use App\Enums\Media\Type as MediaType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Asks for a presigned URL to upload one file straight to object storage.
 * The file name is lowercased like the chunked upload's, so the extension
 * check is case-insensitive.
 */
class StoreDirectAssetRequest extends FormRequest
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
        $allowedSuffixes = collect([MediaType::Image, MediaType::Video, MediaType::Document])
            ->flatMap(fn (MediaType $type) => $type->extensions())
            ->map(fn (string $ext) => ".{$ext}")
            ->all();

        return [
            'file_name' => ['required', 'string', 'max:255', 'ends_with:'.implode(',', $allowedSuffixes)],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.MediaType::Video->maxSizeInBytes()],
            'upload_id' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'total_size.max' => __('assets.upload.file_too_large', ['max' => MediaType::Video->maxSizeInMb()]),
        ];
    }
}
