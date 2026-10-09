<?php

declare(strict_types=1);

namespace App\Http\Requests\App\ContentIdea;

use App\Support\ContentIdeaRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreContentIdeaRequest extends FormRequest
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
        return ContentIdeaRules::content();
    }
}
