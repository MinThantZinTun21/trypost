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
- **Works with Claude Code.** An MCP server at `/mcp` lets an Assistant list your Social accounts and schedule posts for you.
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

### Vultr server with Supabase and Cloudflare R2

The Owner's app runs on the Vultr server it shares with Chord Book (Ubuntu 24.04 LTS, 1 GB, 2 GB of swap), using the production image and `compose.vultr.yaml`: the app (nginx, PHP-FPM, the queue worker and the scheduler), capped at 512 MB. Chord Book's Caddy owns ports 80 and 443 and serves TryPost's domain over HTTPS, forwarding to the app on the shared `edge` Docker network. Postgres comes from Supabase (use the **session pooler** connection string as `DB_URL`) and media lives in an R2 bucket (`FILESYSTEM_DISK=r2`). Deploys are manual: the **Deploy** GitHub Actions workflow builds the image, streams it to the server over SSH and starts it; the container runs the migrations on boot. See [ADR 0005](docs/adr/0005-self-host-on-a-vultr-vps.md) and [ADR 0006](docs/adr/0006-share-the-chord-book-server.md).

1. Make a key pair that only the Deploy workflow uses:

   ```bash
   ssh-keygen -t ed25519 -N "" -C trypost-deploy -f ~/.ssh/trypost_deploy
   ```

2. Run the setup script on the server as root. Chord Book already set up Docker, the firewall, swap and the `deploy` user, so on this server it only adds the key above and `/opt/trypost`:

   ```bash
   scp docker/vultr-setup.sh root@<server-ip>:
   ```

   ```bash
   ssh root@<server-ip> bash vultr-setup.sh "'$(cat ~/.ssh/trypost_deploy.pub)'"
   ```

3. Pick TryPost's domain and point it at the server: an `A` record for, say, `post.example.com` to the server's IP. Without a domain of your own, `trypost.<ip-with-dashes>.sslip.io` already points at the server (the way Chord Book's names work), though Meta and Google may refuse it as an OAuth redirect. Then tell Chord Book's Caddy to serve it, and redeploy Chord Book (its Deploy workflow, or `just deploy` in the Chord Book repo), which also creates the `edge` network:

   ```bash
   ssh root@<server-ip> "echo 'TRYPOST_SITE=<domain>' >> /opt/chordbook/.env"
   ```

4. Put the production settings in `.env.production` (git-ignored): `APP_KEY` (keep the one production already uses: the Social accounts' tokens are encrypted with it), `APP_URL=https://<domain>`, `APP_DOMAIN=<domain>`, `DB_URL`, the `R2_*` keys, `OWNER_EMAIL`, `MCP_TOKEN` and the platform credentials, with the `*_CLIENT_REDIRECT` URLs written out in full on the new domain, `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION`, `CACHE_STORE` and `SESSION_DRIVER` set to `database`, and `LOG_CHANNEL=stderr`. Docker Compose expands `$` in this file, so wrap any value containing `$` (a database password, say) in single quotes. Drop `CRON_SECRET` and `CRON_MAX_SECONDS`; nothing reads them now. If Supabase restricts which addresses may connect, add the server's IP. Copy the file to the server:

   ```bash
   scp .env.production root@<server-ip>:/opt/trypost/.env
   ```

   ```bash
   ssh root@<server-ip> "chown deploy:deploy /opt/trypost/.env && chmod 600 /opt/trypost/.env"
   ```

5. Give the Deploy workflow the server's address, user, key and host key:

   ```bash
   gh secret set VULTR_HOST --body "<server-ip>"
   ```

   ```bash
   gh secret set VULTR_USER --body deploy
   ```

   ```bash
   gh secret set VULTR_SSH_KEY < ~/.ssh/trypost_deploy
   ```

   ```bash
   ssh-keyscan -H <server-ip> | gh secret set VULTR_KNOWN_HOSTS
   ```

   Optionally set the app name compiled into the frontend with `gh variable set VITE_APP_NAME --body "<name>"`.

6. Deploy from the Actions tab (**Deploy** → **Run workflow**), or:

   ```bash
   gh workflow run deploy.yml
   ```

   The run stops early if Chord Book has not created the `edge` network yet. It finishes once the container answers `/up` and `https://<domain>/up` answers through Chord Book's Caddy, so a green run means the certificate, DNS and `TRYPOST_SITE` work too. Follow the app's log with `ssh deploy@<server-ip> docker logs -f trypost`.

#### Moving from Vercel

Do these in order, so the old and new apps never publish side by side:

1. Steps 1 to 5 above (step 3 needs the Chord Book change that adds `TRYPOST_SITE` to be deployed).
2. Stop the external cron (the cron-job.org job that called `/cron/run`). From here until the deploy, scheduled posts wait; they go out as soon as the new scheduler starts.
3. Run the Deploy workflow and check `https://<domain>/up`.
4. Point each platform at the new domain:
   - **Meta** (Facebook app): add `https://<domain>/accounts/facebook/callback` to **Valid OAuth Redirect URIs**, and add the domain to **App Domains**.
   - **TikTok** (developer portal): set the Login Kit redirect URI to `https://<domain>/accounts/tiktok/callback`.
   - **Google** (Cloud Console OAuth client for YouTube): add `https://<domain>/accounts/youtube/callback` to **Authorized redirect URIs**, and the domain to the consent screen's **Authorized domains**.
   - Update the website, privacy policy and terms URLs each app registered for review if they pointed at the Vercel domain.
5. Add `https://<domain>` to the **AllowedOrigins** of the R2 bucket's CORS policy, because uploads go straight from the browser to R2.
6. Re-register the MCP server with Claude Code on the new URL (see below).
7. Delete the Vercel project (**Settings** → **Advanced** → **Delete Project**), which also deletes its copy of the production settings.

## Connect Claude Code

Set `MCP_TOKEN` to a long random string (`openssl rand -hex 32`); an empty token turns the MCP server off. Then register it with Claude Code:

```bash
claude mcp add --transport http trypost https://your-app.example/mcp --header "Authorization: Bearer <MCP_TOKEN>"
```

Every tool acts as the Owner. Start by asking Claude to list your Social accounts.

## Tests

```bash
php artisan test --compact
```

```bash
npm run lint && npm run format:check && npm run build
```

## License

[GNU Affero General Public License v3.0](LICENSE.md), the same as upstream TryPost. If you run a modified version as a network service, make your changes available to its users (AGPL §13). Built on [TryPost](https://github.com/trypostit/trypost) by its contributors.
