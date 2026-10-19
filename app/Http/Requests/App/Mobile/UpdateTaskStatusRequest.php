<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Enums\Task\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(Status::class)],
        ];
    }

    public function status(): Status
    {
        return Status::from((string) $this->validated('status'));
    }
}
