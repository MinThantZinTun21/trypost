<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Post\CreateComposedPost;
use App\Actions\Post\DeletePost;
use App\Actions\Post\SyncPostPlatforms;
use App\Actions\Post\UpdatePost;
use App\Enums\Post\Action as PostAction;
use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status as PostStatus;
use App\Enums\SocialAccount\Platform;
use App\Enums\TikTok\Delivery;
use App\Http\Requests\App\Post\CreatePostRequest;
use App\Http\Requests\App\Post\ShowCalendarRequest;
use App\Http\Requests\App\Post\StorePostRequest;
use App\Http\Requests\App\Post\UpdatePostRequest;
use App\Http\Resources\App\PlatformConfigResource;
use App\Http\Resources\App\PostResource;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Social\TikTokCreatorInfo;
use App\Support\PostStatusRules;
use App\Support\UnsavedPost;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function index(Request $request, ?string $status = null): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('view', $workspace);

        $query = $workspace->posts()
            ->with(['postPlatforms' => fn ($query) => $query->enabled()->with('socialAccount'), 'user']);

        if ($status) {
            $query = match ($status) {
                PostStatus::Scheduled->value => $query->scheduled(),
                PostStatus::Published->value => $query->published(),
                default => $query,
            };
        }

        if ($search = $request->input('search')) {
            $query->whereLike('content', "%{$search}%");
        }

        return Inertia::render('posts/Index', [
            'workspace' => $workspace,
            'posts' => Inertia::scroll(fn () => $query->latest('scheduled_at')->paginate(config('app.pagination.default'))),
            'currentStatus' => $status,
            'filters' => [
                'search' => $request->input('search', ''),
            ],
        ]);
    }

    public function calendar(ShowCalendarRequest $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('view', $workspace);

        $tz = 'UTC';
        $view = $request->input('view', 'week');

        $currentDay = $request->input('day')
            ? Carbon::parse($request->input('day'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $weekStart = $request->input('week')
            ? Carbon::parse($request->input('week'), $tz)->startOfWeek()
            : Carbon::now($tz)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $monthDate = $request->input('month')
            ? Carbon::parse($request->input('month'), $tz)->startOfMonth()
            : Carbon::now($tz)->startOfMonth();
        $monthStart = $monthDate->copy()->startOfMonth()->startOfWeek();
        $monthEnd = $monthDate->copy()->endOfMonth()->endOfWeek();

        $rangeStart = match ($view) {
            'day' => $currentDay,
            'month' => $monthStart,
            default => $weekStart,
        };
        $rangeEnd = match ($view) {
            'day' => $currentDay->copy()->endOfDay(),
            'month' => $monthEnd,
            default => $weekEnd,
        };

        $accountId = $request->accountId();

        $posts = $workspace->posts()
            ->with(['postPlatforms' => fn ($query) => $query->enabled()->with('socialAccount')])
            ->when($accountId, fn ($query) => $query->onSocialAccount($accountId))
            ->whereBetween('scheduled_at', [$rangeStart->copy()->utc(), $rangeEnd->copy()->utc()])
            ->orderBy('scheduled_at')
            ->get()
            ->groupBy(fn ($post) => $post->scheduled_at?->setTimezone($tz)->format('Y-m-d'));

        return Inertia::render('posts/Calendar', [
            'workspace' => $workspace,
            'posts' => $posts,
            'currentDay' => $currentDay->format('Y-m-d'),
            'currentWeekStart' => $weekStart->format('Y-m-d'),
            'currentMonth' => $monthDate->format('Y-m-d'),
            'view' => $view,
            'socialAccounts' => $workspace->socialAccounts()
                ->orderBy('platform')
                ->orderBy('display_name')
                ->get()
                ->map(fn (SocialAccount $account): array => [
                    'id' => $account->id,
                    'platform' => $account->platform->value,
                    'display_label' => $account->display_label,
                ]),
            'filters' => [
                'account' => $accountId,
            ],
        ]);
    }

    /**
     * Opens the composer for a new Post, optionally on a calendar day or as a
     * copy of another Post. Nothing is saved until the Owner schedules it or
     * posts it now (ADR 0011).
     */
    public function create(CreatePostRequest $request): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $socialAccounts = $workspace->socialAccounts()->active()->get();

        if ($socialAccounts->isEmpty()) {
            session()->flash('flash.banner', __('posts.flash.connect_first'));
            session()->flash('flash.bannerStyle', 'danger');

            return redirect()->route('app.accounts');
        }

        $original = $request->original();

        if ($original) {
            $this->authorize('duplicate', $original);
        }

        $date = $request->validated('date');
        $scheduledAt = $date === null ? null : Carbon::parse($date, 'UTC')->setTime(9, 0);

        return $this->composer($workspace, UnsavedPost::make($socialAccounts, $original, $scheduledAt), $socialAccounts);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('createPost', $workspace);

        $result = CreateComposedPost::execute($workspace, $request->user(), $request->composerPayload(), CreatedVia::Web);
        $post = data_get($result, 'post');

        if (data_get($result, 'action') === PostAction::Scheduled) {
            session()->flash('flash.banner', __('posts.flash.scheduled'));
            session()->flash('flash.bannerStyle', 'success');
        }

        return redirect()->route('app.posts.show', $post);
    }

    public function show(Request $request, Post $post): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('view', $post);

        if ($post->status === PostStatus::Scheduled) {
            return redirect()->route('app.posts.edit', $post);
        }

        $post->load(['postPlatforms.socialAccount', 'postPlatforms.insight']);

        return Inertia::render('posts/Show', [
            'workspace' => $workspace,
            'post' => (new PostResource($post))->resolve(),
        ]);
    }

    public function edit(Request $request, Post $post): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('view', $post);

        if (PostStatusRules::blocksEditing($post)) {
            return redirect()->route('app.posts.show', $post);
        }

        if ($request->user()->can('update', $post)) {
            SyncPostPlatforms::execute($post);
        }

        $post->load('postPlatforms.socialAccount');

        return $this->composer($workspace, $post, $workspace->socialAccounts()->active()->get());
    }

    /**
     * The composer page for a stored Post or an UnsavedPost.
     *
     * @param  Collection<int, SocialAccount>  $socialAccounts
     */
    private function composer(Workspace $workspace, Post $post, Collection $socialAccounts): Response
    {
        $platformConfigs = $socialAccounts->mapWithKeys(fn ($account) => [
            $account->id => new PlatformConfigResource($account),
        ]);

        $tiktokCreatorInfos = $socialAccounts
            ->where('platform', Platform::TikTok)
            ->reject(fn () => Delivery::viaTelegram())
            ->mapWithKeys(fn ($account) => [
                $account->id => rescue(
                    fn () => app(TikTokCreatorInfo::class)->fetch($account),
                    null,
                    report: false,
                ),
            ])
            ->filter();

        return Inertia::render('posts/Edit', [
            'workspace' => $workspace,
            'post' => $post,
            'socialAccounts' => $socialAccounts,
            'platformConfigs' => $platformConfigs,
            'tiktokCreatorInfos' => $tiktokCreatorInfos,
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('update', $post);

        $result = UpdatePost::execute($workspace, $post, $request->validated());

        $action = data_get($result, 'action');

        if ($action === PostAction::Finalized) {
            session()->flash('flash.banner', __('posts.flash.cannot_edit_finalized'));
            session()->flash('flash.bannerStyle', 'danger');

            return back();
        }

        if ($action === PostAction::Publishing) {
            return redirect()->route('app.posts.show', $post);
        }

        if ($action === PostAction::Scheduled) {
            session()->flash('flash.banner', __('posts.flash.scheduled'));
            session()->flash('flash.bannerStyle', 'success');

            return redirect()->route('app.posts.show', $post);
        }

        return back();
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('delete', $post);

        if (PostStatusRules::blocksDeletion($post)) {
            session()->flash('flash.banner', __('posts.flash.cannot_delete_published'));
            session()->flash('flash.bannerStyle', 'danger');

            return back();
        }

        DeletePost::execute($post);

        session()->flash('flash.banner', __('posts.flash.deleted'));
        session()->flash('flash.bannerStyle', 'success');

        $allowedRedirects = ['app.posts.index', 'app.calendar'];

        if ($redirect = $request->input('redirect')) {
            if (in_array($redirect, $allowedRedirects)) {
                return redirect()->route($redirect);
            }
        }

        return redirect()->route('app.posts.index');
    }
}
