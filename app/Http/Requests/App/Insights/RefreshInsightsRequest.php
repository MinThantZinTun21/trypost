<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Insights;

use App\Models\SocialAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RefreshInsightsRequest extends FormRequest
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
        return [];
    }

    /**
     * Refresh now runs at most once an hour and never while a read is queued,
     * so it cannot spend the Page's rate limit that publishing also needs.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var SocialAccount $account */
                $account = $this->route('socialAccount');

                if ($account->insightsRefreshPending()) {
                    $validator->errors()->add('refresh', __('insights.refresh.queued'));

                    return;
                }

                $availableAt = $account->insightsRefreshAvailableAt();

                if ($availableAt !== null) {
                    $minutes = max(1, (int) ceil(now()->diffInMinutes($availableAt)));

                    $validator->errors()->add('refresh', trans_choice('insights.refresh.too_soon', $minutes, ['minutes' => $minutes]));
                }
            },
        ];
    }
}
