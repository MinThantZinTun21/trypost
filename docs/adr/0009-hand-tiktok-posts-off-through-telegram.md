# Hand TikTok posts off through Telegram

Supersedes the Telegram mirror in ADR 0008.

Until its app passes TikTok's audit, the Content Posting API only posts as "Only me", so a scheduled TikTok post never reaches the Owner's audience. The Owner posts to TikTok by hand from their phone instead, and wants the app to hand them each TikTok post at its scheduled time: the video, a link to download it, and the caption ready to copy. Copying every published Facebook and YouTube post into the channel is no longer wanted.

So a **TikTok handoff** replaces the Telegram mirror. With `TIKTOK_DELIVERY=telegram`, a TikTok target publishes to the Owner's Telegram channel instead of TikTok, through the same publish job and at the same time. A video goes out with its **Download link** as its caption, or the link alone when it is over the Bot API's 50 MB upload limit. Photos go out as albums. Then the TikTok caption follows as a message of its own, so it copies cleanly. Nothing else is sent to Telegram.

The target then counts as **Published**, with no live link: the app has done its part, and the Owner finishes on TikTok. `TIKTOK_DELIVERY=api` (the default) keeps uploading through the Content Posting API.

## Considered Options

- **Keep the mirror and add a TikTok-only mode to it**: rejected. The mirror runs after a Post settles, so the TikTok target would still try the API first, and a failure there would leave nothing to hand off.
- **Bring Telegram back as a fourth Platform**: rejected for the same reasons as in ADR 0008. The Owner still connects a TikTok Social account and picks it per Post; only where it goes changes.
- **Mark the target with a new status such as "Handed off"**: rejected. Every status reader (the calendar, notifications, the MCP tools, `RecoverStuckPosts`) would need it, for a difference the Owner does not need to see.

## Consequences

- The handoff skips the TikTok account's token and connection state; an expired TikTok account still hands off.
- A Telegram refusal fails the TikTok target with Telegram's reason, and a rate limit, a server error or a lost connection retries it like any publish, so the usual Post failed Notification covers it. The `TelegramFailed` Notification type and `posts.telegram_sent_at` are gone.
- Each message is a step keyed by what it sends, and the steps that went out are cached, so a retry never sends the video twice.
- TikTok's privacy level, interaction flags and creator info are not asked for or checked while delivery is Telegram.
- Download links are still signed with `APP_KEY` and carry `APP_URL`; rotating the key or moving the domain breaks every link already in the channel.
