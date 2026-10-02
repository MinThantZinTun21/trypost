<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Account;
use App\Models\Invite;
use App\Models\Media;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvite;
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
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use SocialiteProviders\Facebook\FacebookExtendSocialite;
use SocialiteProviders\LinkedIn\LinkedInExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Pinterest\PinterestExtendSocialite;
use SocialiteProviders\TikTok\TikTokExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureSocialite();
    }

    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'account' => Account::class,
            'invite' => Invite::class,
            'media' => Media::class,
            'notification' => Notification::class,
            'notificationPreference' => NotificationPreference::class,
            'post' => Post::class,
            'postComment' => PostComment::class,
            'postPlatform' => PostPlatform::class,
            'socialAccount' => SocialAccount::class,
            'user' => User::class,
            'workspace' => Workspace::class,
            'workspaceInvite' => WorkspaceInvite::class,
        ]);
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
