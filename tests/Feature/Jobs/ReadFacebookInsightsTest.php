<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Status;
use App\Jobs\ReadFacebookInsights;
use App\Models\PageInsightSnapshot;
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
    $this->insightsUrl = config('trypost.platforms.facebook.graph_api').'/1234/insights*';
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
