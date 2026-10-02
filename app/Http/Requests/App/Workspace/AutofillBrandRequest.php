<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class AutofillBrandRequest extends FormRequest
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
            'url' => ['required', 'string', 'max:255'],
        ];
    }
}
