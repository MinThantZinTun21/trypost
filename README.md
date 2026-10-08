<p align="center">
  <img src="public/images/trypost/logo-dark.png" alt="TryPost" width="190">
</p>

<h1 align="center">TryPost (minimal fork)</h1>

<p align="center">
  A personal scheduler that publishes one person's posts to Facebook Pages, TikTok and YouTube Shorts at the time they choose.
</p>

---

This is a hard fork of [trypostit/trypost](https://github.com/trypostit/trypost), cut down to the parts one Owner needs to schedule posts. AI, Repurpose, analytics, billing, teams, the REST API and MCP server, webhooks, email, live websockets, Redis and the extra languages were removed. [ADR 0001](docs/adr/0001-hard-fork-to-minimal-three-platform-scheduler.md) explains why, and [ADR 0002](docs/adr/0002-postgres-only-no-redis.md) explains the Postgres-only setup. Pulling updates from upstream is no longer practical.

## What it does

- **One Owner.** The seeder creates the account. There is no sign-up page, and `php artisan owner:reset-password` resets the password on the server.
- **Three platforms.** Facebook Pages (Post, Reel, Story), TikTok (Video, Photo) and YouTube Shorts.
- **Write once, schedule anywhere.** Pick the accounts, upload images or videos from your computer (large files upload in chunks), and save as a draft or schedule the post.
- **Calendar.** Month, week and day views, with drag and drop to reschedule, plus lists of scheduled, draft and posted posts.
- **Publishing you can see.** Each platform's result shows a link or a failure reason, and the post page refreshes while it publishes.
- **In-app notifications** when a post fails, when an account disconnects, or when an upcoming post's account needs reconnecting. Expiring tokens are refreshed automatically.

## Requirements

- PHP 8.5 and Composer
- Node.js 22 and npm
- PostgreSQL 16 (the only external service — the queue and cache run on it too)
- Developer apps for the platforms you use: [Meta](https://developers.facebook.com), [TikTok](https://developers.tiktok.com) and [Google Cloud](https://console.cloud.google.com) (YouTube Data API v3)

## Run it locally

```bash
composer setup
```

This installs dependencies, copies `.env.example` to `.env`, generates the app key, runs the migrations and builds the frontend. Then:

1. Set the database credentials and `APP_URL` in `.env`.
2. Fill in `FACEBOOK_*`, `TIKTOK_*` and `GOOGLE_*` and register the callback URLs shown there (`${APP_URL}/accounts/<platform>/callback`) with each platform.
3. Set `OWNER_EMAIL` (and `OWNER_PASSWORD`, or leave it empty to have one generated and printed), then create the Owner:

   ```bash
   php artisan db:seed
   ```

4. Start everything:

   ```bash
   composer run dev
   ```

   This runs the web server, the queue worker, the scheduler, the log tail and Vite.

## Deploy

The app runs three processes next to Postgres:

| Process | Command |
| --- | --- |
| Web | PHP-FPM + nginx (or `php artisan serve`) |
| Queue worker | `php artisan queue:work --queue=default,social-facebook,social-tiktok,social-youtube --tries=1 --timeout=930` |
| Scheduler | `php artisan schedule:work` (or `schedule:run` from cron every minute) |

The scheduler publishes due posts every minute, refreshes expiring tokens, recovers posts stuck in publishing, and checks account connections.

With Docker, `compose.prod.yaml` builds the app image from this repository (nginx, PHP-FPM, the queue worker and the scheduler under supervisor) and runs it next to Postgres. Set `APP_KEY`, `APP_URL`, `OWNER_EMAIL`, the passwords marked "change me" and your platform credentials, then:

```bash
docker compose -f compose.prod.yaml up -d --build
```

The first boot runs the migrations and creates the Owner. If `OWNER_PASSWORD` is empty, the generated password is printed once in `docker compose -f compose.prod.yaml logs app`.

For local development in Docker, use `compose.yaml` with `docker/.env.docker.example`.

### Vercel (free) with Supabase and Cloudflare R2

Vercel cannot run the queue worker or the scheduler, so an external cron (for example cron-job.org) calls `GET /cron/run` every minute with `Authorization: Bearer <CRON_SECRET>`, or with a token that expires, from `php artisan cron:token --days=30 --env=production`. Each call runs the due scheduled tasks, publishes for up to 4 minutes, and returns the next scheduled post. Postgres comes from Supabase (use the **session pooler** connection string) and media lives in an R2 bucket (`FILESYSTEM_DISK=r2`). Limits on the free plans: a publish that takes longer than about 5 minutes (a large video) is cut off, and posts go out within a minute of their time.

1. Put the production settings in `.env.production` (git-ignored): `APP_KEY`, `APP_URL`, `DB_URL`, the `R2_*` keys, `CRON_SECRET`, `OWNER_EMAIL` and the platform credentials, with `QUEUE_CONNECTION`, `CACHE_STORE` and `SESSION_DRIVER` set to `database` and `LOG_CHANNEL=stderr`.
2. Create the schema and the Owner from your machine:

   ```bash
   php artisan migrate --force --env=production && php artisan db:seed --force --env=production
   ```

3. Paste `.env.production` into the Vercel project's environment variables (Vercel does not expand `${APP_URL}`, so write the `*_CLIENT_REDIRECT` URLs out in full), then build and deploy:

   ```bash
   npm run build && npx vercel deploy --prod
   ```

   The frontend is built locally because Vercel's build step has no PHP; the `vercel-php` runtime installs the Composer packages on Vercel.

## Tests

```bash
php artisan test --compact
```

```bash
npm run lint && npm run format:check && npm run build
```

## License

[GNU Affero General Public License v3.0](LICENSE.md), the same as upstream TryPost. If you run a modified version as a network service, make your changes available to its users (AGPL §13). Built on [TryPost](https://github.com/trypostit/trypost) by its contributors.
