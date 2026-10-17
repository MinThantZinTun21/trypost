# A Videos list, not the Content library

The Owner wants to drop raw footage from their phone where the Assistant can fetch it, for example to cut a recap. A **Video** is a name and a video file, nothing else: it is not part of any Post, has no Platform texts, and cannot be put into a Post by the composer or `create_post`. The Mobile app uploads, lists and deletes Videos; the Assistant lists them, gets one with a Download link, and deletes them over MCP. There is no web page for Videos.

Videos get their own `videos` table and model. Each holds its file as a Media row attached to the Video rather than to the workspace's assets, so a Video's file never shows up as a Post's media, and deleting a Video deletes its file.

## Considered Options

- **Revive the Content library (ADR 0007)**: rejected. It was removed on purpose; a Library item carried per-Platform texts and made Posts, which a Video must not do. `tests/Feature/Removed` still guards it.
- **Keep Videos as workspace assets with a name**: rejected. `create_post` accepts any asset, so a Video could end up in a Post, and deleting the Video would pull the file from under it.

## Consequences

- Uploads need object storage (R2 in production), like `start_media_upload`.
- The Download link is the permanent signed `media.download` link the TikTok handoff uses; it stops working when the Video is deleted.
