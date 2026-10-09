<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Status;
use App\Jobs\ReadFacebookInsights;
use App\Models\PageInsightSnapshot;
use App\Models\Post;
use App\Models\PostInsight;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A Graph Page insights response with one value per metric per day, stamped
 * the way Graph stamps them: at the end of each day.
 *
 * @param  array<string, array<string, int>>  $days  date => [graph metric => value]
 * @return array<string, mixed>
 */
function pageInsightsGraphResponse(array $days): array
{
    $series = [];

    foreach ($days as $date => $metrics) {
        $endOfDay = now()->parse($date)->addDay()->format('Y-m-d');

        foreach ($metrics as $name => $value) {
            $series[$name][] = ['value' => $value, 'end_time' => "{$endOfDay}T07:00:00+0000"];
        }
    }

    return ['data' => collect($series)->map(fn (array $values, string $name): array => [
        'name' => $name,
        'period' => 'day',
        'values' => $values,
    ])->values()->all()];
}

beforeEach(function () {
    $this->travelTo(now()->parse('2026-10-09 10:00:00'));
    $this->account = SocialAccount::factory()->facebook()->create(['platform_user_id' => '1234']);
    $graph = config('trypost.platforms.facebook.graph_api');
    $this->insightsUrl = "{$graph}/1234/insights*";
});

test('the first read backfills 90 days and stores each day', function () {
    Http::fake([$this->insightsUrl => Http::response(pageInsightsGraphResponse([
        '2026-10-07' => ['page_follows' => 500, 'page_media_view' => 1200, 'page_total_media_view_unique' => 800],
        '2026-10-08' => ['page_follows' => 503, 'page_media_view' => 900, 'page_daily_follows_unique' => 4, 'page_daily_unfollows_unique' => 1, 'page_post_engagements' => 70, 'page_video_views' => 300],
    ]))]);

    ReadFacebookInsights::dispatchSync($this->account);

    Http::assertSent(fn (Request $request): bool => $request['since'] === '2026-07-11'
        && $request['until'] === '2026-10-09'
        && $request['period'] === 'day'
        && $request['metric'] === 'page_follows,page_daily_follows_unique,page_daily_unfollows_unique,page_media_view,page_total_media_view_unique,page_post_engagements,page_video_views');

    $snapshots = $this->account->pageInsightSnapshots()->orderBy('date')->get();

    expect($snapshots)->toHaveCount(2)
        ->and($snapshots[0]->date->toDateString())->toBe('2026-10-07')
        ->and($snapshots[0]->followers)->toBe(500)
        ->and($snapshots[0]->views)->toBe(1200)
        ->and($snapshots[0]->reach)->toBe(800)
        ->and($snapshots[0]->new_follows)->toBeNull()
        ->and($snapshots[1]->new_follows)->toBe(4)
        ->and($snapshots[1]->unfollows)->toBe(1)
        ->and($snapshots[1]->engagements)->toBe(70)
        ->and($snapshots[1]->video_views)->toBe(300);

    $this->account->refresh();
    expect($this->account->insights_read_at)->not->toBeNull()
        ->and($this->account->insights_error)->toBeNull();
});

test('later reads re-read the last 3 days and overwrite them', function () {
    PageInsightSnapshot::factory()->create(['social_account_id' => $this->account->id, 'date' => '2026-10-08', 'views' => 10]);

    Http::fake([$this->insightsUrl => Http::response(pageInsightsGraphResponse([
        '2026-10-08' => ['page_media_view' => 950],
    ]))]);

    ReadFacebookInsights::dispatchSync($this->account);

    Http::assertSent(fn (Request $request): bool => $request['since'] === '2026-10-06' && $request['until'] === '2026-10-09');

    expect($this->account->pageInsightSnapshots()->count())->toBe(1)
        ->and($this->account->pageInsightSnapshots()->first()->views)->toBe(950);
});

test('a read before Pacific midnight stops at the last day Facebook finished, and drops days outside the window', function () {
    $this->travelTo(now()->parse('2026-10-10 02:00:00'));
    Http::fake([$this->insightsUrl => Http::response(pageInsightsGraphResponse([
        '2026-10-08' => ['page_media_view' => 40],
        '2026-10-09' => ['page_media_view' => 7],
    ]))]);

    ReadFacebookInsights::dispatchSync($this->account);

    Http::assertSent(fn (Request $request): bool => $request['since'] === '2026-07-11' && $request['until'] === '2026-10-09');

    expect($this->account->pageInsightSnapshots()->pluck('date')->map->toDateString()->all())->toBe(['2026-10-08']);
});

test('a refused read records the error, keeps history and leaves the connection alone', function () {
    PageInsightSnapshot::factory()->create(['social_account_id' => $this->account->id, 'date' => '2026-10-01']);
    $this->account->update(['insights_refresh_queued_at' => now()]);

    Http::fake([$this->insightsUrl => Http::response([
        'error' => ['message' => '(#100) The value must be a valid insights metric', 'code' => 100],
    ], 400)]);

    ReadFacebookInsights::dispatchSync($this->account);

    $this->account->refresh();

    expect($this->account->insights_error)->toBe('(#100) The value must be a valid insights metric')
        ->and($this->account->insights_refresh_queued_at)->toBeNull()
        ->and($this->account->insights_read_at)->toBeNull()
        ->and($this->account->insights_attempted_at?->toDateTimeString())->toBe('2026-10-09 10:00:00')
        ->and($this->account->status)->toBe(Status::Connected)
        ->and($this->account->pageInsightSnapshots()->count())->toBe(1);
});

test('an error message never carries the access token', function () {
    Http::fake([$this->insightsUrl => Http::response([
        'error' => ['message' => 'Bad request for access_token=SECRET123', 'code' => 1],
    ], 500)]);

    ReadFacebookInsights::dispatchSync($this->account);

    expect($this->account->refresh()->insights_error)->not->toContain('SECRET123');
});

test('a successful read clears an earlier error', function () {
    $this->account->update(['insights_error' => 'Earlier failure']);

    Http::fake([$this->insightsUrl => Http::response(['data' => []])]);

    ReadFacebookInsights::dispatchSync($this->account);

    expect($this->account->refresh()->insights_error)->toBeNull();
});

test('reads are unique per Social account', function () {
    expect((new ReadFacebookInsights($this->account))->uniqueId())->toBe($this->account->id);
});

// Post insights
test('it reads Post insights for a text or photo Post through its feed post id', function () {
    $graph = config('trypost.platforms.facebook.graph_api');
    $postPlatform = PostPlatform::factory()->facebook()->published()->create([
        'social_account_id' => $this->account->id,
        'platform_post_id' => '1234_555',
    ]);

    Http::fake([
        $this->insightsUrl => Http::response(['data' => []]),
        "{$graph}/1234_555/insights*" => Http::response(['data' => [
            ['name' => 'post_media_view', 'period' => 'lifetime', 'values' => [['value' => 420]]],
            ['name' => 'post_total_media_view_unique', 'period' => 'lifetime', 'values' => [['value' => 300]]],
            ['name' => 'post_reactions_by_type_total', 'period' => 'lifetime', 'values' => [['value' => ['like' => 10, 'love' => 3]]]],
            ['name' => 'post_clicks', 'period' => 'lifetime', 'values' => [['value' => 7]]],
        ]]),
    ]);

    ReadFacebookInsights::dispatchSync($this->account);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '1234_555/insights')
        && $request['metric'] === 'post_media_view,post_total_media_view_unique,post_reactions_by_type_total,post_clicks');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'video_insights'));

    $insight = $postPlatform->insight()->first();

    expect($insight->feed_post_id)->toBe('1234_555')
        ->and($insight->views)->toBe(420)
        ->and($insight->reach)->toBe(300)
        ->and($insight->reactions)->toBe(13)
        ->and($insight->clicks)->toBe(7)
        ->and($insight->video_views)->toBeNull()
        ->and($insight->read_at)->not->toBeNull();
});

test('it resolves a Reel feed post id once and reads its video insights', function () {
    $graph = config('trypost.platforms.facebook.graph_api');
    $postPlatform = PostPlatform::factory()->facebookReel()->published()->create([
        'social_account_id' => $this->account->id,
        'platform_post_id' => '9001',
    ]);

    Http::fake([
        $this->insightsUrl => Http::response(['data' => []]),
        "{$graph}/9001/video_insights*" => Http::response(['data' => [
            ['name' => 'post_video_avg_time_watched', 'values' => [['value' => 5400]]],
            ['name' => 'post_video_view_time', 'values' => [['value' => 81000]]],
            ['name' => 'blue_reels_play_count', 'values' => [['value' => 640]]],
        ]]),
        "{$graph}/9001*" => Http::response(['post_id' => '777', 'id' => '9001']),
        "{$graph}/1234_777/insights*" => Http::response(['data' => [
            ['name' => 'post_media_view', 'values' => [['value' => 700]]],
            ['name' => 'post_video_views', 'values' => [['value' => 610]]],
        ]]),
    ]);

    ReadFacebookInsights::dispatchSync($this->account);

    $insight = $postPlatform->insight()->first();

    expect($insight->feed_post_id)->toBe('1234_777')
        ->and($insight->views)->toBe(700)
        ->and($insight->video_views)->toBe(610)
        ->and($insight->avg_watch_time_ms)->toBe(5400)
        ->and($insight->watch_time_ms)->toBe(81000)
        ->and($insight->reel_plays)->toBe(640);

    ReadFacebookInsights::dispatchSync($this->account);

    $feedPostLookups = Http::recorded(fn (Request $request): bool => data_get($request->data(), 'fields') === 'post_id');

    expect($feedPostLookups)->toHaveCount(1);
});

test('one refused Post does not stop the others, and Stories and old or other Posts are skipped', function () {
    $graph = config('trypost.platforms.facebook.graph_api');
    $refused = PostPlatform::factory()->facebook()->published()->create(['social_account_id' => $this->account->id, 'platform_post_id' => '1234_1']);
    $second = PostPlatform::factory()->facebook()->published()->create(['social_account_id' => $this->account->id, 'platform_post_id' => '1234_2']);
    PostPlatform::factory()->facebookStory()->published()->create(['social_account_id' => $this->account->id, 'platform_post_id' => '1234_3']);
    $old = PostPlatform::factory()->facebook()->published()->create(['social_account_id' => $this->account->id, 'platform_post_id' => '1234_4', 'published_at' => now()->subDays(40)]);
    PostInsight::factory()->create(['post_platform_id' => $old->id, 'views' => 5]);
    PostPlatform::factory()->facebook()->published()->create(['social_account_id' => $this->account->id, 'platform_post_id' => '1234_6', 'published_at' => now()->subDays(40)]);
    PostPlatform::factory()->facebook()->published()->create(['platform_post_id' => '9999_5']);

    Http::fake([
        $this->insightsUrl => Http::response(['data' => []]),
        "{$graph}/1234_1/insights*" => Http::response(['error' => ['message' => 'Unsupported get request', 'code' => 100]], 400),
        "{$graph}/1234_2/insights*" => Http::response(['data' => [['name' => 'post_media_view', 'values' => [['value' => 9]]]]]),
    ]);

    ReadFacebookInsights::dispatchSync($this->account);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '1234_3')
        || str_contains($request->url(), '1234_4')
        || str_contains($request->url(), '1234_6')
        || str_contains($request->url(), '9999_5'));

    expect(PostInsight::query()->whereNotNull('read_at')->count())->toBe(2)
        ->and($refused->insight()->first()->read_at)->toBeNull()
        ->and($second->insight()->first()->views)->toBe(9)
        ->and($old->insight()->first()->views)->toBe(5)
        ->and($this->account->refresh()->insights_error)->toBeNull();
});

test('a video Post is read as a video, and a photo Post never is, whatever its id looks like', function () {
    $graph = config('trypost.platforms.facebook.graph_api');
    $videoPost = Post::factory()->published()->create(['media' => [['id' => 'm1', 'path' => 'clips/desk.mp4', 'url' => 'https://example.com/desk.mp4', 'type' => 'video']]]);
    $photoPost = Post::factory()->published()->create(['media' => [['id' => 'm2', 'path' => 'photos/desk.jpg', 'url' => 'https://example.com/desk.jpg', 'type' => 'image']]]);
    $video = PostPlatform::factory()->facebook()->published()->create(['post_id' => $videoPost->id, 'social_account_id' => $this->account->id, 'platform_post_id' => '8001']);
    $photo = PostPlatform::factory()->facebook()->published()->create(['post_id' => $photoPost->id, 'social_account_id' => $this->account->id, 'platform_post_id' => '8002']);

    Http::fake([
        $this->insightsUrl => Http::response(['data' => []]),
        "{$graph}/8001/video_insights*" => Http::response(['data' => [['name' => 'post_video_view_time', 'values' => [['value' => 12000]]]]]),
        "{$graph}/8001*" => Http::response(['post_id' => '1234_888', 'id' => '8001']),
        "{$graph}/1234_888/insights*" => Http::response(['data' => [['name' => 'post_video_views', 'values' => [['value' => 75]]]]]),
        "{$graph}/8002/insights*" => Http::response(['data' => [['name' => 'post_media_view', 'values' => [['value' => 30]]]]]),
    ]);

    ReadFacebookInsights::dispatchSync($this->account);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '1234_888/insights')
        && $request['metric'] === 'post_media_view,post_total_media_view_unique,post_reactions_by_type_total,post_clicks,post_video_views');
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '8002/video_insights')
        || data_get($request->data(), 'fields') === 'post_id' && str_contains($request->url(), '8002'));

    expect($video->insight()->first())
        ->feed_post_id->toBe('1234_888')
        ->video_views->toBe(75)
        ->watch_time_ms->toBe(12000)
        ->and($photo->insight()->first())
        ->feed_post_id->toBe('8002')
        ->views->toBe(30)
        ->video_views->toBeNull();
});

test('a resolved feed post id is kept when its totals are refused, and not looked up again', function () {
    $graph = config('trypost.platforms.facebook.graph_api');
    $reel = PostPlatform::factory()->facebookReel()->published()->create(['social_account_id' => $this->account->id, 'platform_post_id' => '9001']);

    Http::fake([
        $this->insightsUrl => Http::response(['data' => []]),
        "{$graph}/1234_777/insights*" => Http::response(['error' => ['message' => 'Video still processing', 'code' => 100]], 400),
        "{$graph}/9001*" => Http::response(['post_id' => '777', 'id' => '9001']),
    ]);

    ReadFacebookInsights::dispatchSync($this->account);
    ReadFacebookInsights::dispatchSync($this->account);

    expect($reel->insight()->first())
        ->feed_post_id->toBe('1234_777')
        ->read_at->toBeNull()
        ->and(Http::recorded(fn (Request $request): bool => data_get($request->data(), 'fields') === 'post_id'))->toHaveCount(1);
});

test('the refresh mark stays until the Post reads are done', function () {
    $graph = config('trypost.platforms.facebook.graph_api');
    PostPlatform::factory()->facebook()->published()->create(['social_account_id' => $this->account->id, 'platform_post_id' => '1234_1']);
    $this->account->update(['insights_refresh_queued_at' => now()]);
    $markWhilePostsRead = 'not read';

    Http::fake([
        $this->insightsUrl => Http::response(['data' => []]),
        "{$graph}/1234_1/insights*" => function () use (&$markWhilePostsRead) {
            $markWhilePostsRead = $this->account->fresh()->insights_refresh_queued_at;

            return Http::response(['data' => []]);
        },
    ]);

    ReadFacebookInsights::dispatchSync($this->account);

    expect($markWhilePostsRead)->not->toBeNull()
        ->and($markWhilePostsRead)->not->toBe('not read')
        ->and($this->account->refresh()->insights_refresh_queued_at)->toBeNull();
});
