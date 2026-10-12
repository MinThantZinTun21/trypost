<?php

declare(strict_types=1);

namespace App\Http\Requests\App\LibraryItem;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleLibraryItemRequest extends FormRequest
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
        $accountIds = $this->user()->currentWorkspace->socialAccounts()->active()->pluck('id')->all();

        return [
            'social_account_ids' => ['required', 'array', 'min:1'],
            'social_account_ids.*' => ['string', 'distinct', Rule::in($accountIds)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'social_account_ids.required' => __('library.schedule.accounts_required'),
            'social_account_ids.min' => __('library.schedule.accounts_required'),
            'social_account_ids.*.in' => __('library.schedule.account_not_found'),
        ];
    }
}
