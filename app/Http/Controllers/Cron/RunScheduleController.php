<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cron;

use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\CronToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;

class RunScheduleController extends Controller
{
    /**
     * Stand-in for schedule:work and queue:work on hosts that cannot run
     * long-lived processes: run what is due, then drain the queue. Cron
     * services stop waiting long before an upload finishes (cron-job.org
     * after 30 s), so the run ignores the caller hanging up.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('trypost.cron.secret');

        abort_if($secret === '', Response::HTTP_NOT_FOUND);
        abort_unless(
            CronToken::accepts($secret, (string) ($request->bearerToken() ?? $request->query('token', ''))),
            Response::HTTP_UNAUTHORIZED,
        );

        ignore_user_abort(true);

        $queues = ['default', ...Platform::allQueues()];

        Artisan::call('schedule:run');
        Artisan::call('queue:work', [
            '--queue' => implode(',', $queues),
            '--stop-when-empty' => true,
            '--max-time' => (int) config('trypost.cron.max_seconds'),
            '--tries' => 1,
        ]);

        $nextPost = Post::query()
            ->where('status', PostStatus::Scheduled)
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->first(['id', 'scheduled_at']);

        return response()->json([
            'ran_at' => now()->toIso8601String(),
            'jobs_remaining' => array_sum(array_map(fn (string $queue): int => Queue::size($queue), $queues)),
            'next_scheduled_post' => $nextPost ? [
                'id' => $nextPost->id,
                'scheduled_at' => $nextPost->scheduled_at->toIso8601String(),
            ] : null,
        ]);
    }
}
