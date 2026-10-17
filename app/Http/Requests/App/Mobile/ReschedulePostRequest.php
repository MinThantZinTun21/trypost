<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ReschedulePostRequest extends FormRequest
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
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }

    public function scheduledAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('scheduled_at'));
    }
}
