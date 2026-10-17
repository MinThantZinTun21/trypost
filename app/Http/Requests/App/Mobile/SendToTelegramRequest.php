<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

use App\Dto\MediaItem;
use App\Models\Post;
use App\Services\Telegram\TelegramClient;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class SendToTelegramRequest extends FormRequest
{
    /**
     * Another workspace's Post is a 404 before anything about it is validated.
     */
    public function authorize(): Response
    {
        return Gate::inspect('view', $this->route('post'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! app(TelegramClient::class)->isConfigured()) {
                $validator->errors()->add('telegram', __('telegram.send_not_configured'));
            }

            /** @var Post $post */
            $post = $this->route('post');

            if (! $post->media_items->contains(fn (MediaItem $item): bool => $item->isVideo() || $item->isImage())) {
                $validator->errors()->add('media', __('telegram.no_media'));
            }
        });
    }
}
