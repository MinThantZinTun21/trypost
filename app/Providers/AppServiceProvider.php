<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\StripeEventListener;
use App\Models\Account;
use App\Models\AiUsageLog;
use App\Models\Invite;
use App\Models\Media;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Plan;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;
use App\Models\WorkspaceLabel;
use App\Models\WorkspaceSignature;
use App\Services\PostHogService;
use App\Socialite\DiscordProvider;
use App\Socialite\InstagramProvider;
use App\Socialite\LinkedInPageExtendSocialite;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Records\CacheEvent;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use PostHog\PostHog;
use SocialiteProviders\Facebook\FacebookExtendSocialite;
use SocialiteProviders\LinkedIn\LinkedInExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Pinterest\PinterestExtendSocialite;
use SocialiteProviders\TikTok\TikTokExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configurePostHog();
        $this->configureSocialite();
        $this->configureStripeWebhooks();

        Cashier::useCustomerModel(Account::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);
        Cashier::keepPastDueSubscriptionsActive();
    }

    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'account' => Account::class,
            'aiUsageLog' => AiUsageLog::class,
            'invite' => Invite::class,
            'media' => Media::class,
            'notification' => Notification::class,
            'notificationPreference' => NotificationPreference::class,
            'plan' => Plan::class,
            'post' => Post::class,
            'postComment' => PostComment::class,
            'postPlatform' => PostPlatform::class,
            'socialAccount' => SocialAccount::class,
            'subscription' => Subscription::class,
            'subscriptionItem' => SubscriptionItem::class,
            'user' => User::class,
            'webhook' => Webhook::class,
            'webhookLog' => WebhookLog::class,
            'workspace' => Workspace::class,
            'workspaceInvite' => WorkspaceInvite::class,
            'workspaceLabel' => WorkspaceLabel::class,
            'workspaceSignature' => WorkspaceSignature::class,
        ]);
    }

    protected function configurePostHog(): void
    {
        if (! PostHogService::isEnabled()) {
            return;
        }

        PostHog::init(config('services.posthog.api_key'), [
            'host' => config('services.posthog.host'),
        ]);
    }

    protected function configureStripeWebhooks(): void
    {
        Event::listen(WebhookHandled::class, StripeEventListener::class);
    }

    protected function configureSocialite(): void
    {
        // Google Auth (login/signup) - separate from YouTube OAuth
        Socialite::extend('google-auth', function ($app) {
            $config = $app['config']['services.google-auth'];

            return Socialite::buildProvider(GoogleProvider::class, $config);
        });

        // Google Business Profile — dedicated app, separate from 'google' (YouTube).
        Socialite::extend('google-business', function ($app) {
            $config = $app['config']['services.google-business'];

            return Socialite::buildProvider(GoogleProvider::class, $config);
        });

        // Instagram Business Login
        Socialite::extend('instagram', function ($app) {
            $config = $app['config']['services.instagram'];

            return Socialite::buildProvider(InstagramProvider::class, $config);
        });

        Socialite::extend('discord', function ($app) {
            $config = $app['config']['services.discord'];

            return Socialite::buildProvider(DiscordProvider::class, $config);
        });

        Event::listen(SocialiteWasCalled::class, FacebookExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, LinkedInExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, LinkedInPageExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, PinterestExtendSocialite::class);
        Event::listen(SocialiteWasCalled::class, TikTokExtendSocialite::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Disable wrapping of JSON resources
        JsonResource::withoutWrapping();
        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );

        Nightwatch::rejectCacheEvents(function (CacheEvent $cacheEvent) {
            return in_array($cacheEvent->key, [
                'illuminate:foundation:down',
                'illuminate:queue:restart',
                'illuminate:schedule:interrupt',
            ]);
        });

        // Custom email verification template
        VerifyEmail::toMailUsing(function (User $user, string $url) {
            return (new MailMessage)
                ->from(config('mail.from.address'), config('mail.from.name'))
                ->subject(__('mail.email_verification.subject'))
                ->view('mail.email-verification', [
                    'title' => __('mail.email_verification.subject'),
                    'previewText' => __('mail.email_verification.preview'),
                    'user' => $user,
                    'url' => $url,
                ]);
        });

        // Custom password reset template
        ResetPassword::toMailUsing(function (User $user, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->from(config('mail.from.address'), config('mail.from.name'))
                ->subject(__('mail.password_reset.subject'))
                ->view('mail.password-reset', [
                    'title' => __('mail.password_reset.subject'),
                    'previewText' => __('mail.password_reset.preview'),
                    'user' => $user,
                    'url' => $url,
                ]);
        });
    }
}
