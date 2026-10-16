# Mirror published posts to a Telegram channel

Superseded by ADR 0009: only TikTok posts go to Telegram now, as a TikTok handoff.

ADR 0001 removed Telegram as a Platform. The Owner now wants every Post that goes live to also land in their own Telegram channel, with its text, its video or images, and the links to the live Facebook, TikTok and YouTube posts. Picking Telegram per Post is not wanted; the channel is a running record of what was published.

So Telegram comes back as a **Telegram mirror**, not a Platform. One bot token and one channel id in the server's environment turn it on. When a Post settles as Published or Partially published, a queued job sends it to the channel once. Failed Posts are not sent.

The Bot API takes uploads up to 50 MB (10 MB for photos), and Posts may carry videos up to 4 GB. Files within the limits are uploaded into the channel. Every video also gets a **Download link**: a permanent signed URL to this app that needs no sign-in and returns the file as a download, so a video over 50 MB is still one tap away.

## Considered Options

- **Bring Telegram back as a fourth Platform**: rejected. It would reopen the connect flow, the composer, previews, a publish queue and the removal guards, for a destination the Owner wants every time anyway.
- **Run a local Bot API server for 2 GB uploads**: rejected. It is a second long-running container on the shared 1 GB server (ADR 0006), where the app is capped at 512 MB.
- **Link to the media's public storage URL**: rejected. It plays in the browser instead of downloading, and it changes if storage moves. The signed app link stays stable and forces a download.

## Consequences

- The Platform enum, the three publish queues and the `tests/Feature/Removed` guards stay as they are. The mirror sends on the `default` queue and adds no route with "telegram" in its name.
- A Post records when it was mirrored, so a second settle never sends it twice.
- A mirror that still fails after its retries leaves an in-app Notification; it never changes the Post's status.
- Download links are signed with `APP_KEY` and carry `APP_URL`; rotating the key or moving the domain breaks every link already posted in the channel.
