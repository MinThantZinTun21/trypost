<?php

declare(strict_types=1);

namespace App\Http\Requests\App\LibraryItem;

use App\Support\LibraryItemRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLibraryItemRequest extends FormRequest
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
        return LibraryItemRules::rules($this->user()->currentWorkspace->getMedia('assets')->get()->keyBy('id'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return LibraryItemRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return LibraryItemRules::attributes();
    }
}
