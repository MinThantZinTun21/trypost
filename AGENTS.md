<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project keeps committed, area-grouped rules in `.ai/rules` (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>

# Project-Specific Rules

## What this app is

A hard fork of TryPost cut down to a personal scheduler: one Owner publishes
posts to Facebook Pages, TikTok and YouTube Shorts at a chosen time. The domain
glossary is `CONTEXT.md`; the decisions behind the fork are
`docs/adr/0001-hard-fork-to-minimal-three-platform-scheduler.md` and
`docs/adr/0002-postgres-only-no-redis.md`; where it runs is
`docs/adr/0005-self-host-on-a-vultr-vps.md` and
`docs/adr/0006-share-the-chord-book-server.md`; the TikTok handoff through Telegram is
`docs/adr/0009-hand-tiktok-posts-off-through-telegram.md` (it supersedes
`docs/adr/0008-mirror-published-posts-to-a-telegram-channel.md`); the Android app is
`docs/adr/0010-android-app-on-a-token-guarded-mobile-api.md`. Read them
before adding anything.

- **Removed features stay removed.** AI, Repurpose, analytics, billing, teams and
  workspace UI, the REST API and MCP server, webhooks, signatures, labels,
  comments, the asset library, Content ideas, the Content library, email, live websockets, Horizon/Redis and the 15
  non-English locales were deleted on purpose, along with their packages and
  tables. Do not reintroduce any of them as part of an unrelated change.
- `tests/Feature/Removed/*` guard those removals (routes 404, packages absent,
  config gone). Update a guard only when the Owner deliberately brings a feature
  back.
- New schema changes are new migrations. Never edit or squash an existing
  migration — existing databases upgrade in place.

## MCP server (Assistant)

- `POST /mcp` (`routes/ai.php`, `App\Mcp\Servers\SchedulerServer`) is the
  Owner's Assistant's way in (ADR 0003). It is built on `laravel/mcp` from
  scratch; the old Passport-backed `/mcp/trypost` server and its settings page
  stay removed.
- `AuthenticateMcpToken` guards it with one bearer secret, `MCP_TOKEN`
  (empty = 404), and acts as the Owner. There is no OAuth and no per-client key.
- Tools say "Social account", never "channel". Tool names are snake_case.
  Tools reuse the same actions and validation as the composer; they never
  publish through a separate path.
- `delete_post` reuses the web delete action and refuses what the web refuses
  (`PostStatusRules::blocksDeletion()`: publishing or published Posts).

## Mobile app API (ADR 0010)

- `/mobile/v1` (`routes/mobile.php`, controllers in `App\Http\Controllers\Mobile\V1`)
  is the Owner's Android app (`android/`) way in. It sits outside the web
  group: no session, cookies or CSRF.
- `AuthenticateMobileToken` guards it with one bearer secret, `MOBILE_TOKEN`
  (`config('trypost.mobile.token')`; empty = 404, wrong = 401), and acts as the
  Owner. It shares `AuthenticatesWithOwnerToken` with `AuthenticateMcpToken`;
  the two secrets stay separate so each can be rotated alone.
- The app views Posts, edits their texts, and Reschedules them. It never
  creates Posts or changes media, Social accounts or Content types. There is
  no Unschedule (ADR 0011): `DELETE /mobile/v1/posts/{post}/schedule` and the
  Drafts tab are gone. Every change goes through `UpdatePost` and the shared rules
  (`PostStatusRules`, `PostPlatformMetaRules`); a finalized Post is 409.
- `ReschedulePost` rebuilds the stored Post as a composer
  payload (`PostComposerValidator::storedPayload()`, every enabled Post
  platform with its Content type and meta, since `UpdatePost` disables the ones
  left out) and run the composer's own checks
  (`PostComposerValidator::make()`, shared with the MCP `create_post` tool)
  before `UpdatePost`. The resources list enabled Post platforms in the same
  order (`PostPlatform::inComposerOrder()`), so a `platforms.N.*` error names
  the app's Nth Social account. A new `scheduled_at` must carry its UTC offset.
- `PUT /mobile/v1/posts/{post}` (`UpdatePostTexts`) edits only the content and
  each Post platform's Title, Description, caption and Tags, through the same
  stored payload and composer checks. A text the Content type does not publish
  is refused (`ContentType::textFields()`, which the resource sends as
  `text_fields`, so the app keeps no copy of that map); a null text clears it.
- The release APK is signed only by the manual **Android release** workflow
  (`.github/workflows/android-release.yml`) or locally from
  `android/keystore.properties`; `AndroidReleaseTest` checks both. Never commit
  a keystore or its passwords.
- Responses are the `App\Http\Resources\Mobile\V1` resources, with times in
  ISO 8601 UTC. Treat their fields as a contract with an installed app: add
  fields freely, but rename or remove one only in a new `/mobile/v2`.
- The list tabs are `App\Enums\Mobile\PostTab`, not Post statuses: Scheduled
  includes Publishing, Failed includes Partially published.

## TikTok handoff through Telegram (ADR 0009)

- `TIKTOK_DELIVERY` (`config('trypost.platforms.tiktok.delivery')`,
  `App\Enums\TikTok\Delivery`) is `api` (the default, upload through the
  Content Posting API) or `telegram`. With `telegram`, `PublishToSocialPlatform`
  hands every TikTok target to `TikTokHandoff` instead of `TikTokPublisher`, at
  the same scheduled time, on the same queue. Web and MCP Posts go the same
  way.
- Telegram is **not** a Platform: no Social account, no content type, no
  connect flow (`PlatformsRemovedTest` still forbids `telegram` routes). It is
  one channel from `.env`: `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHANNEL_ID`
  (`config('trypost.telegram')`); a handoff without them fails the target.
- Only TikTok targets go to Telegram. The old mirror of every published Post
  (`SendPostToTelegram`, `posts.telegram_sent_at`, the `TelegramFailed`
  Notification) was removed; do not bring it back.
- The handoff sends, in order: each video with its Download link as the
  caption (the link alone over the Bot API's 50 MB limit), or the photos as
  albums of up to 10 items and 50 MB; then the TikTok caption as its own
  message (a video's `caption` meta, a photo post's title and description,
  else the Post content), split between lines (`TelegramClient::split()`).
  Each step is keyed by what it sends and the keys that went out are cached,
  so a retry never sends the video twice.
- A handed-off target is **Published** with no `platform_url`. It skips the
  TikTok account's token and connection state. Privacy level, interaction
  flags and creator info are not required or fetched (`PostPlatformMetaRules`,
  the MCP `create_post` tool, `PostController::edit`); the composer shows a
  Telegram note instead (`tiktokViaTelegram` shared prop).
  `VerifyUpcomingPostConnections` skips TikTok targets, so a broken TikTok
  token never warns that a handed-off post is at risk.
- The Download link is `media.download` (`routes/media.php`): a permanent
  signed URL that needs no sign-in, so a video over 50 MB is still reachable
  from the channel. On R2 it redirects to a 30-minute presigned URL that forces
  a download; a local disk streams the file.
- `TelegramClient` waits out a 429 of up to 10 s in place (`Sleep`, fake it in
  tests) and resends the same call; a longer one, a 5xx or a lost connection
  retries the target (`PlatformUnavailableException`), because the wait holds
  the one worker that also publishes Posts. Any other 4xx fails the target
  with Telegram's reason, and the usual `PostFailed` Notification follows.
  `php artisan telegram:test` sends a test message and explains a refusal.

## No Drafts (ADR 0011)

- A Post exists only once it is Scheduled or going out. There is no Draft
  status, Drafts list or Unschedule anywhere (web, MCP, Mobile app);
  `tests/Feature/Removed/DraftsRemovedTest.php` guards it.
- New post and Duplicate open the composer on an unsaved Post
  (`GET /posts/create`, `UnsavedPost`); nothing is stored until Schedule or Post
  now, which `POST /posts` saves through `CreateComposedPost`, the same action
  the MCP `create_post` tool uses. A Scheduled post is edited in place and kept
  with Save; nothing autosaves.

## Single Owner

- There is no registration, onboarding, social login, email verification or
  forgot-password flow. The seeder creates the Owner, and
  `php artisan owner:reset-password` sets their password on the server.
- Workspaces and accounts are internal plumbing: the Owner has exactly one of
  each, created with them, and neither appears in the UI. Every Post and Social
  account still belongs to a workspace, and policies still check it, because the
  Owner is always that workspace's owner. Do not add workspace switching,
  members, roles or invites.

## Platforms

- `App\Enums\SocialAccount\Platform` has exactly three cases: `Facebook`,
  `TikTok`, `YouTube` (`tests/Unit/Enums/SupportedPlatformsTest.php` enforces it).
  Content types: Facebook Post, Reel and Story; TikTok Video and Photo; YouTube
  Short (public, Shorts only).
- Each platform publishes on its own queue, `Platform::queue()` =>
  `social-<platform>`. Every worker command lists `default` plus
  `Platform::allQueues()`; `PlatformTest` checks the supervisor configs and the
  `composer run dev` script, so a new queue that no worker reads fails the suite.

## Processes, queue and cache (Postgres only)

- Postgres is the only backing service. The queue and the cache use Laravel's
  `database` drivers; there is no Redis, Horizon or Reverb, and none of them may
  come back as a hidden requirement. Cache locks (`ShouldBeUnique`,
  `WithoutOverlapping`, `onOneServer`, `Cache::lock`) run on the `cache_locks`
  table.
- The app runs three processes: the web server, **one** `queue:work` over
  `default,social-facebook,social-tiktok,social-youtube`, and `schedule:work`.
  `composer run dev` adds `pail` and Vite. The Docker supervisor configs mirror
  this.
- `queue.connections.database.retry_after` (960) must stay above
  `PublishToSocialPlatform::$timeout` (900). Below it, a slow video upload is
  handed to a second worker while the first is still running.
- The schedule in `routes/console.php` is `Schedule::call(fn () => Artisan::call(...))`,
  never `Schedule::command()`. Callbacks run inside the one `schedule:run`
  process, so `ScheduleTest` runs the schedule end to end with faked
  publishers; `Schedule::command()` spawns `php artisan` subprocesses that never
  see the fakes (`ScheduleTest` fails with them).
- Production is the Owner's Vultr server, shared with Chord Book (ADR 0005,
  ADR 0006). `compose.vultr.yaml` runs only the app, capped at 512 MB, with
  Postgres on Supabase (`DB_URL`) and media on R2. Chord Book's Caddy owns 80
  and 443 and forwards TryPost's domain to `trypost:80` on the shared `edge`
  network, so the app publishes no port (it trusts every proxy) and has no
  Caddy of its own. The manual Deploy workflow (`.github/workflows/deploy.yml`)
  builds the image and streams it over SSH; the container migrates on boot.
  `VultrDeployTest` checks both files.
- There is no Vercel deploy (`tests/Feature/Removed/VercelRemovedTest.php`).
- `GET /cron/run` (`routes/cron.php`) is the Owner's choice to also drive the
  schedule from an external cron, with `Authorization: Bearer $CRON_SECRET`,
  `?token=`, or an expiring token from `php artisan cron:token --days=30`
  (signed with `CRON_SECRET`; rotating it revokes them). An empty
  `CRON_SECRET` disables it (404). It runs `schedule:run` next to
  `schedule:work`, never instead of it: every task is `onOneServer()`, so the
  two never run one twice, and posts still publish if the cron stops. With
  `CRON_MAX_SECONDS` above 0 it then works the queue that long, publishing
  due posts in the call; keep it under PHP's `max_execution_time` (60 s in
  production). It returns the next scheduled post.

## Publishing status updates (polling, no websockets)

- There is no broadcasting. A page that shows a publishing post reloads its
  props with `usePollWhile()` (`resources/js/composables/usePollWhile.ts`), which
  polls every 5 seconds **only while** the condition holds and stops once the
  post settles. Never poll unconditionally.
- The notification bell fetches when the layout mounts (pages use a
  non-persistent layout, so that is every visit) and when the menu opens.

## Notifications (in-app only)

- The app sends no email: there are no Mailables, mail views, Maizzle build or
  notification preferences, and `MAIL_MAILER` stays `log`. Do not add a mail
  provider.
- `App\Jobs\SendNotification` stores an in-app `Notification` row. There are
  three types: `PostFailed` (full or partial failure; a fully published post
  sends nothing), `AccountDisconnected` and `PostAtRisk`. Titles and bodies
  resolve through `lang/en/notifications.php`.

## Language (English only)

- The UI is English only. There is no locale enum, `SetLocale` middleware or
  language switcher. Keep user-visible strings in `lang/en/*.php` and resolve
  them with `__()` / `trans_choice()` (PHP) or `trans()` from `laravel-vue-i18n`
  (Vue) — the helpers stay so copy lives in one place.

## Social accounts

A workspace may connect several accounts of the same platform. There is no
one-per-network rule — do not reintroduce one.

- Reconnecting the same `platform` + `platform_user_id` updates the existing row
  (`SocialAccount::connectIdentity()`), and the identity pickers drop identities
  already connected.
- `accounts.popup_callback.network_taken` stays because
  `NetworkAlreadyConnectedException` still uses it for a reconnect that collides
  on the unique identity index.
- `VerifyWorkspaceConnections` is the **only** thing that promotes an account
  back to `Connected`, because it does so after a real `verify()` call. A
  successful token refresh is not that proof, so `RefreshSocialToken` must not
  promote.

## Inertia SSR

- This project does **not** run Inertia SSR. `config/inertia.php` defaults `ssr.enabled` to `false` and nothing in the repo sets `INERTIA_SSR_ENABLED`.
- Keep it off. With it on, every test rendering an Inertia page issues a real HTTP request to the SSR endpoint, which fails silently and falls back to client rendering — slow, and it hides missing `Http::fake()` stubs.
- The build wiring is still shipped (`resources/js/ssr.ts`, `vite.config.ts`, `npm run build:ssr` in `docker/Dockerfile`). Turning SSR on means building that bundle and running `inertia:start-ssr` alongside the app, not just flipping the env.

## Frontend (Vue/TypeScript)

- Always use arrow functions in Vue components and TypeScript files. Never use `function` declarations.
- Build UI from the shadcn-vue components in `resources/js/components/ui` (Button, Badge, Card, Alert, Field, InputGroup, Empty/`EmptyState`, Spinner, Separator, Collapsible, Table, NativeSelect, Textarea, Progress, …) instead of hand-rolled markup. Restyle them with `class`; do not copy their classes onto a raw element. Add a missing component with the shadcn-vue CLI, never by hand (a new npm package still needs approval).
    - Exceptions: custom widgets the components can't express (the composer's caption textarea with its overflow mirror) and the platform previews, which imitate each network's own UI.
    - `tests/Unit/ShadcnComponentsTest.php` fails on a raw `<table>`, `<select>`, `<textarea>`, `<hr>`, `<progress>` or non-file `<input>` outside `components/ui`; list a deliberate exception there with its reason.

## Design (shadcn Sera preset)

- The look is the shadcn **Sera** preset (`b5VuIW3jXk`), ported by hand: tokens in `resources/css/app.css`, component styles in `resources/js/components/ui`. Change the look there, not page by page.
- Square corners (`--radius: 0`), hairline borders, `shadow-sm` on surfaces; overlays (dialogs, popovers, menus) keep the preset's `shadow-md`. Buttons, badges, labels and table heads are uppercase, tracked `text-xs font-semibold`; inputs are underlined. Roboto for text, Roboto Slab (`font-display`) for headings.
- Lime `bg-primary` always pairs with `text-primary-foreground`. For lime **text** on white use `text-primary-text` (or `text-brand`); plain `text-primary` is unreadable. No violet, fuchsia or pink, and no coloured shadows.
- `rounded-full` stays only for avatars (with an avatar stack's `+N` chip and an empty avatar slot), dots and spinners. Tabler icons stay; the preset's icon library is not used.
- The platform previews (`components/posts/previews`) imitate each network and keep their own styling.
- A floating control must not cover a page's sticky mobile action bar: pass `mobile-action-bar` to `AppLayout` so the help button lifts above it. `tests/Browser/MobileOverflowTest.php` checks overflow and that overlap on a phone and a desktop.

## Dialogs

- In `<DialogFooter>`, put the **primary action button first** in the markup, then secondary/cancel (e.g. Save → Cancel). `DialogFooter` uses `flex-col` on mobile (primary on top, cancel at the bottom) and `sm:flex-row sm:justify-start` on desktop, so the first child is the leftmost action on larger screens.
- Match sibling dialogs in the same feature area before inventing a new footer layout.

## Icons (@tabler/icons-vue)

- This project uses `@tabler/icons-vue` for all icons. NEVER use `lucide-vue-next`.
- All Tabler icons are prefixed with `Icon`, e.g. `IconCheck`, `IconChevronRight`, `IconMail`.
- Import icons from `@tabler/icons-vue`: `import { IconCheck, IconX } from '@tabler/icons-vue'`.
- Browse available icons at https://tabler.io/icons

## Dates

- For date manipulation, always use `@/dayjs` (pre-configured dayjs instance with utc, timezone, relativeTime plugins).
- For formatting dates for display (formatDate, formatDateTime, formatTime, diffForHumans), always use `@/date` which centralizes all formatting logic with proper timezone handling.
- Never use raw `new Date()` for date calculations — use dayjs.

## Routing (Wayfinder)

- This project uses Laravel Wayfinder for type-safe frontend routing.
- ALWAYS use Wayfinder-generated route helpers in Vue pages (e.g. `login()`, `dashboard()`). NEVER hardcode URL strings like `href="/login"`.
- After creating or modifying PHP routes/controllers, run `php artisan wayfinder:generate` to regenerate the TypeScript route helpers.
- Import routes from `@/routes/...` (e.g. `import { store } from '@/routes/login'`).

## Pagination

- Always use normal pagination (`->paginate()`). NEVER use cursor pagination (`->cursorPaginate()`).
- All paginated lists must use Inertia's scroll pagination (`Inertia::scroll()` on the backend with `<InfiniteScroll>` on the frontend). NEVER use traditional page-based pagination with page links/buttons.
- The page size ALWAYS comes from `config('app.pagination.default')` — never a magic number, and never a `perPage`/`per_page` value supplied by the request or frontend. Action/service list methods must NOT accept a `$perPage` parameter; call `->paginate((int) config('app.pagination.default'))` directly.

## Form Validation

- NEVER use HTML5 validation attributes (`required`, `minlength`, `pattern`, etc.) on form inputs. Always rely solely on backend validation.

## Backend Validation

- Validation rules always live in a dedicated `Illuminate\Foundation\Http\FormRequest` subclass under `app/Http/Requests/App/<Group>/`. Controller actions must type-hint the FormRequest as the parameter — NEVER call `$request->validate([...])` inline in the controller.
- Naming: `<Verb><Resource>Request.php` (e.g. `StorePostRequest`, `UpdatePostRequest`).

## Database engines (PostgreSQL + MySQL)

Postgres is what this fork deploys on, but CI still runs the suite on **both PostgreSQL and MySQL**, so every query, migration and test must work on both.

- **What the app supports is the intersection of the two engines, never the superset of one.** When they differ, take the narrower behaviour.
- Never use an engine-specific operator or function. Search uses `whereLike()` (Laravel handles the case-insensitive form per driver), never `ilike` or a raw `LOWER(...)` comparison.
- Traps that only surface on MySQL:
    - **JSON object key order is not preserved.** MySQL reorders object keys on storage (by length, then lexicographically); PostgreSQL keeps insertion order. Assert JSON read back from the database with `toEqual` (recursive, order-independent), never `toBe`/`assertSame`. Array *element* order is preserved on both.
    - **`$table->timestamp()` tops out at 2038-01-19.** Nothing written to a `timestamp()` column may go past it — scheduled posts, expiry sentinels and test fixtures alike. `2037-12-31` reads as "far future" and works on both.
    - **Raw query-builder reads carry no Eloquent cast**, so the driver's native shape leaks through: `DB::table(...)->value('some_bool')` is `true` on PostgreSQL and `1` on MySQL. Read through the model, or use `assertDatabaseHas`.
    - **Identifier quoting differs** — PostgreSQL emits `"post_platforms"`, MySQL emits backticks. Never match logged SQL (`DB::listen`) against a quoted identifier.
    - **MySQL refuses to drop the only index backing a foreign key** (SQLSTATE `1553`). A migration `down()` that drops a unique whose leftmost prefix is an FK column must create a standalone index for that column first.
    - **DDL implicitly commits**, which defeats `RefreshDatabase`'s rollback: schema changes made inside a test leak into the tests that follow. Keep them idempotent.

## Per-Platform Post Meta (`PostPlatform.meta`)

- All `platforms.*.meta` validation (the parent array rule AND every per-platform sub-key: `aspect_ratio`, TikTok `privacy_level`/flags, YouTube `description`, etc.) lives in ONE place: `App\Support\PostPlatformMetaRules`. Post requests spread `...PostPlatformMetaRules::rules()`; NEVER add a per-platform meta rule inline to a single request.
    - Why: `FormRequest::validated()` STRIPS any key without a rule, so a meta field defined anywhere else is silently dropped.
- Required-on-publish meta lives there too: `addRequiredOnPublishErrors()` for request-driven flows and `assertStoredPostPublishable()` for flows that publish stored state. Add new required-meta rules to `requiredMetaViolation()`, not inline.
- Cover a new meta field in `tests/Unit/PostPlatformMetaRulesTest.php`.

## Media Types (image / video / document)

- A media item is one of exactly three types: **image**, **video**, **document** (PDF). There is no standalone "audio" media type.
- Media-type detection lives in ONE place per side — NEVER hand-write `type === 'image'`, `mime_type === 'application/pdf'`, `mime.startsWith('video/')`, or extension checks inline.
    - Backend: `App\Enums\Media\Type` — `classify()`, `fromMime()`, `fromExtension()`, `isGif()`, plus the `allowedMimeTypes()` / `extensions()` allow-lists. Use these, never a raw MIME/extension comparison.
    - Frontend: `resources/js/lib/mediaType.ts` — the mirror of the backend enum: the `MediaType` union, `classify()`, `fromMimeType()` (for a browser `File.type`), `fromExtension()`, `isImage()`/`isVideo()`/`isDocument()`/`isGif()`. `@/composables/useMedia` re-exports `isImageMedia`/`isVideoMedia`/`isDocumentMedia` aliases for legacy call sites.
    - Detection trusts the explicit `type` first, then the MIME, then the filename extension.
- The `type` field on every media-ish interface is the `MediaType` union, never `string`.
- The upload `accept` attribute for "everything we allow" comes from `acceptAttribute()` (frontend) / `Media\Type::allowedMimeTypes()` (backend) — never a hardcoded MIME list. Per-capability `accept` builders driven by content-type rules (e.g. `image/*,video/*`) are fine; those aren't detection.

## Pest / Feature Tests

- ALWAYS use named routes via the `route()` helper in feature tests. NEVER hardcode URL strings like `'/posts/create'`.
    - Example: `$this->postJson(route('app.posts.store'))` instead of `$this->postJson('/posts')`.
    - Exception: the `tests/Feature/Removed` guards request removed URLs by path, because their route names no longer exist.

## Browser Tests (Pest + Playwright)

Browser tests live in `tests/Browser` and run on `pestphp/pest-plugin-browser` driving Playwright. **Laravel Dusk is not installed** — there is no `DuskTestCase`, no `$browser` object, and no `browse()`. Do not add `dusk="..."` attributes; they select nothing.

- ALWAYS use named routes via `route()`. NEVER hardcode URLs like `'https://trypost.test/login'`.
- ALWAYS target elements by `data-testid`. NEVER use CSS classes (`.text-red-600`), tag names, or text strings.
    - `@my-element` resolves to `[data-testid="my-element"]`, so add `data-testid="my-element"` in the Vue component and use `$page->click('@my-element')`.
    - Bind it for repeated elements: `:data-testid="`connect-${platform.value}`"`.
- Assertions do NOT auto-wait on SPA paint. Wait for the element to mount and lay out first — see the `waitFor*TestId()` helper at the top of `tests/Browser/AccountsPageTest.php` and copy the pattern under a file-unique name (these helpers are global functions; a duplicated name collides across test files).
- **Never `sleep()` in a browser test.** The HTTP server that serves the page runs inside the same PHP process (an Amp loop that only ticks while Pest awaits Playwright), so a blocking `sleep()` starves every asset request. Poll from the page with `$page->script(...)` (as the `waitFor*TestId()` helpers do).
- `BrowserTestCase` sets `$fakesVite = false` on purpose: these tests load real built assets, so faking Vite blanks the app.
- End page assertions with `->assertNoJavaScriptErrors()`.
- CI runs them un-parallelised (`php artisan test tests/Browser --compact`) against `npm run build` output, so keep them independent of a running dev server.

## Array Data Access

- In Action classes and similar service classes, ALWAYS use Laravel's `data_get()` helper instead of direct array access.
    - Example: `data_get($data, 'name')` instead of `$data['name']`.
    - Use the third parameter for fallback values: `data_get($data, 'username', $sender->username)` instead of `$data['username'] ?? $sender->username`.

## Eloquent Models & Morph Map

- EVERY Eloquent model in `app/Models` MUST be registered in `Relation::enforceMorphMap([...])` inside `AppServiceProvider::configureMorphMap()`, keyed by a camelCase alias (e.g. `'postPlatform' => PostPlatform::class`).
- When you add a new model, add it to the morph map in the same change. `tests/Unit/MorphMapTest.php` fails if any model is missing.
- The alias is persisted in polymorphic columns, so never rename or remove an existing alias for a model that has stored rows.

## Imports

- NEVER use inline class references (e.g., `\DB::listen`, `\Str::uuid()`). ALWAYS import classes at the top of the file with a `use` statement.
    - PHP: `use Illuminate\Support\Facades\DB;` then `DB::listen(...)`
    - TypeScript/Vue: `import { ref } from 'vue'` then `ref(...)`

## Response Status Codes

- When returning JSON responses with explicit status codes, always use `Symfony\Component\HttpFoundation\Response` constants instead of magic numbers.
    - Example: `Response::HTTP_CREATED` instead of `201`, `Response::HTTP_NO_CONTENT` instead of `204`.

## String Interpolation

- When injecting variables into strings, prefer **double-quoted interpolation** with curly braces over concatenation with `.`.
    - PHP: `"workspace.{$workspace->id}"` instead of `'workspace.'.$workspace->id`.
    - Use curly braces `{}` even for simple variables to keep the boundary explicit and to allow object/array access without ambiguity.
    - Single quotes are still preferred when the string has no interpolation.

## External Service URLs

- NEVER hardcode third-party API hosts, OAuth endpoints, or per-platform service URLs (e.g. `https://graph.facebook.com`, `https://open.tiktokapis.com`). They live in `config/trypost.php` under `platforms.<name>` with a matching `env(...)` default, so self-hosted users can override them and we have a single source of truth.
    - Production code: `config('trypost.platforms.facebook.graph_api')."/{$pageId}/feed"`, never the literal URL.
    - Tests: use the same `config(...)` value in `Http::fake([...])`. Tests with hardcoded URLs drift silently when the config changes.
    - Path/route segments after the host are part of the provider's protocol spec — those stay inline next to the call. Only the host comes from config.

## Social Platform API Documentation (official sources)

**Always consult the official docs below before implementing or changing OAuth, publishing, deletion, rate-limit, or any other platform-specific behavior — never guess endpoints, scopes, rate limits, or capabilities from memory.**

- **Facebook (Meta Graph API)**:
    - General error handling / codes 1, 2, 4, 17, 190: https://developers.facebook.com/docs/graph-api/guides/error-handling/
    - Rate limiting — Platform Rate Limits (app/user tokens, codes 4/17) vs. Business Use Case (BUC) Rate Limits (Page tokens, codes 80000–80014, e.g. `80001` Pages API; BUC rejections come back as plain HTTP 400, not 429): https://developers.facebook.com/docs/graph-api/overview/rate-limiting/
    - `App\Services\Social\Meta\GraphError` (used by `ConnectionVerifier`'s verify/refresh calls) has the full rationale and code table in its class docblock — check there before changing transient-vs-confirmed-rejection classification.
    - Facebook `SocialAccount`s use a Page access token, so BUC limits apply.
- **YouTube**: Data API v3 — https://developers.google.com/youtube/v3/docs
- **TikTok**: Content Posting API — https://developers.tiktok.com/doc/content-posting-api-reference-direct-post — **no delete/unpublish endpoint exists**; a published post can only be removed manually inside the TikTok app.

## Git

- NEVER add `Co-Authored-By` lines to commit messages.
- NEVER commit, push, or open PRs unless explicitly asked by the user.
- Always create a new branch for feature work before making changes.
