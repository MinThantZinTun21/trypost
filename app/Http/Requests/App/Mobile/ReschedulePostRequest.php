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
            'scheduled_at' => ['required', 'date', 'regex:/(Z|[+-]\d{2}:?\d{2})$/i', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scheduled_at.regex' => __('mobile.scheduled_at_offset'),
        ];
    }

    /**
     * The new publish time; the required offset makes it exact whatever the
     * server's timezone.
     */
    public function scheduledAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->validated('scheduled_at'));
    }
}
