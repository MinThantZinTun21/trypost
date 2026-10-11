# Self-host on one Vultr VPS; Vercel is removed

The app moves from Vercel to a single Vultr VPS (Ubuntu 24.04 LTS, 2 GB RAM) running the production Docker image. That image already runs the three processes the app needs under supervisor: nginx with PHP-FPM, one `queue:work` over `default` and the `social-*` queues, and `schedule:work`. Caddy sits in front for HTTPS on the Owner's domain. Postgres stays on Supabase and media stays in Cloudflare R2, so the move carries no data.

A manual GitHub Actions workflow builds the image and ships it to the server over SSH (`docker save | docker load`); there is no registry to authenticate against. On boot the container runs the migrations, so a deploy also migrates.

Everything that existed only for Vercel is removed: `vercel.json`, `.vercelignore`, `api/index.php`, the `GET /cron/run` endpoint with its `CRON_SECRET` and `cron:token` tokens, and the external cron that called it.

## Considered Options

- **Stay on Vercel**: rejected. Without long-lived processes, a publish is cut off after about 5 minutes, posts wait for an external cron, and every scheduled task runs inside a web request.
- **Move Postgres onto the VPS too**: rejected for now. It means a data migration and backups the Owner has to run; Supabase keeps both.
- **Build on the server, or pull from a registry**: rejected. Building needs Node and more memory than the app; a registry needs credentials on the server. Streaming the image over SSH needs neither.
- **Deploy automatically on every merge**: rejected. The Owner deploys when they choose, from the Actions tab.

## Consequences

- The server is the Owner's to keep patched; the setup script turns on unattended security upgrades, a firewall that allows only SSH, HTTP and HTTPS, and key-only SSH logins (once root has a key).
- `compose.vultr.yaml` names the shipped image and never builds or pulls it: the server holds no source, only the image the workflow loads.
- Changing the app's domain means updating the OAuth redirect URIs in the Meta, TikTok and Google consoles, and the URLs registered for app review.
- `RecoverStuckPosts` stays: a worker can still die mid-upload.
