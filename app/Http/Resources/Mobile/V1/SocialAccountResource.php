<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Models\SocialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SocialAccount
 */
class SocialAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'display_label' => $this->display_label,
            'status' => $this->status->value,
        ];
    }
}
