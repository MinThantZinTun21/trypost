# The Mobile app posts now, sends to Telegram and uploads Videos

Partly supersedes ADR 0010, which said the Mobile app never creates Posts, changes media or publishes now.

The Owner works from their phone more than expected and wants three more things there: **Post now** a Scheduled post, **Send to Telegram** a Post to copy it by hand, and upload a **Video** for the Assistant. The app still never creates a Post or changes a Post's media. Each new action goes through a path the server already has: Post now is the composer's own publish (`UpdatePost` with `status=publishing`, after the composer's checks); Send to Telegram reuses the TikTok handoff's steps; a Video uploads straight to storage through the composer's presigned upload.

Send to Telegram is a manual action on one Post, not ADR 0008's mirror: nothing is sent unless the Owner asks, and the Post's status does not change. It runs as a queued job, because a 50 MB video and Telegram's rate limits can outlast a request, and a refusal leaves an in-app Notification. It is on the Mobile app only.

## Considered Options

- **Send to Telegram inside the request**: rejected. Production stops PHP after 60 seconds; the phone would see a timeout for a send that may still go out.
- **Bring back the mirror for the Owner to turn on per Post**: rejected for ADR 0009's reasons; one button on the Post is what the Owner asked for.

## Consequences

- A fourth Notification type, Send to Telegram failed, joins the three in CLAUDE.md.
- `/mobile/v1` gains `POST posts/{post}/publish`, `POST posts/{post}/telegram`, `GET social-accounts`, an `account` filter on `GET posts`, and the Video endpoints.
