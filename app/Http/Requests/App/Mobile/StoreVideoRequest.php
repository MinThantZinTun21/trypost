<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registers a finished upload as a Video with its name.
 */
class StoreVideoRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'upload_id' => ['required', 'string', 'uuid'],
        ];
    }
}
