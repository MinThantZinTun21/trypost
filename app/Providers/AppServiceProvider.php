<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Account;
use App\Models\ContentIdea;
use App\Models\Media;
use App\Models\Notification;
use App\Models\PageInsightSnapshot;
use App\Models\Post;
use App\Models\PostInsight;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Facebook\FacebookExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
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
            'contentIdea' => ContentIdea::class,
            'media' => Media::class,
            'notification' => Notification::class,
            'pageInsightSnapshot' => PageInsightSnapshot::class,
            'post' => Post::class,
            'postInsight' => PostInsight::class,
            'postPlatform' => PostPlatform::class,
            'socialAccount' => SocialAccount::class,
            'user' => User::class,
            'workspace' => Workspace::class,
        ]);
    }

    protected function configureSocialite(): void
    {
        Event::listen(SocialiteWasCalled::class, FacebookExtendSocialite::class);
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
    }
}
