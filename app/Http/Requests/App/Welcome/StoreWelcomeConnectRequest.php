<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Welcome;

use App\Enums\SocialAccount\Status;
use App\Models\SocialAccount;
use Illuminate\Foundation\Http\FormRequest;

class StoreWelcomeConnectRequest extends FormRequest
{
    /**
     * @var list<string>|null
     */
    private ?array $connectedPlatforms = null;

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
     * @return list<string>
     */
    public function connectedPlatforms(): array
    {
        return $this->connectedPlatforms ??= $this->user()->currentWorkspace->socialAccounts()
            ->where('status', Status::Connected)
            ->orderBy('id')
            ->get()
            ->map(fn (SocialAccount $account): string => $account->platform->value)
            ->unique()
            ->values()
            ->all();
    }
}
