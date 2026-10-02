<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\SocialAccount\Status;
use App\Jobs\PostHog\IdentifyConnectedPlatforms;
use App\Models\SocialAccount;
use App\Services\PostHogService;

class SocialAccountObserver
{
    public function created(SocialAccount $socialAccount): void
    {
        $this->identifyConnectedPlatforms($socialAccount);
    }

    public function deleted(SocialAccount $socialAccount): void
    {
        $this->identifyConnectedPlatforms($socialAccount);
    }

    public function updated(SocialAccount $socialAccount): void
    {
        if (! $socialAccount->wasChanged('status')) {
            return;
        }

        $wasConnected = $socialAccount->getRawOriginal('status') === Status::Connected->value;
        $isConnected = $socialAccount->status === Status::Connected;

        if ($wasConnected !== $isConnected) {
            $this->identifyConnectedPlatforms($socialAccount);
        }
    }

    private function identifyConnectedPlatforms(SocialAccount $socialAccount): void
    {
        if (! PostHogService::isEnabled()) {
            return;
        }

        IdentifyConnectedPlatforms::dispatch((string) $socialAccount->workspace_id);
    }
}
