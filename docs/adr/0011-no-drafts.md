# No Drafts: a Post is scheduled or going out

Partly supersedes ADR 0010: the Mobile app no longer Unschedules Posts.

The Owner never used Drafts as a place to keep work, and they filled the lists with Posts that would never go out. So Drafts are gone everywhere. A Post exists only once the Owner schedules it or posts it now; until then the web composer holds it in the page and writes nothing. Unschedule goes with them: to stop a Scheduled post, the Owner deletes it. A migration deletes every existing Draft and its Post platforms, and leaves their media files in storage, because other Posts may use the same files.

## Considered Options

- **Keep Drafts but hide them**: rejected. The composer would still write a row on every "New post", and every list, the MCP tools and the Mobile app would keep filtering out a status nobody sees.
- **Unschedule back to an unscheduled Scheduled post**: rejected. A Scheduled post with no time is a Draft by another name.

## Consequences

- "New post" and Duplicate open a create page that saves nothing until Schedule or Post now, and asks before leaving with unsaved work. A Scheduled post is edited in place and saved with a Save button; nothing autosaves any more.
- The MCP `create_post` tool takes only `scheduled` or `now`.
- `DELETE /mobile/v1/posts/{post}/schedule` and the Drafts tab are removed from `/mobile/v1` without a `/mobile/v2`. This is a one-off break of ADR 0010's contract: there is one Owner and one phone, and the new APK is installed when the server deploys.
