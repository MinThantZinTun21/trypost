# A Content library of ready-made posts

Withdrawn: the Owner removed the Content library and Content ideas on 2026-10-17. Their tables and `posts.library_item_id` are dropped; Posts made from a Library item keep their content and media.

ADR 0001 removed TryPost's asset library. The Owner now deliberately wants a place to keep finished content before it is scheduled: a video or images with the text each Platform needs, written once (often by the Assistant) and scheduled later, sometimes more than once.

A Library item holds a name, its media, the Facebook text, the TikTok caption, and the YouTube title, description and tags. Scheduling one does not publish from the library. It creates a Post through the same actions as the composer: on the web a Draft that opens in the composer, where the Owner checks the previews and picks the time; through MCP the same path `create_post` takes. The item stays in the library and remembers the Posts made from it.

## Considered Options

- **Bring back TryPost's asset library**: rejected. It was a media browser with stock-photo search and no per-Platform text, which is the part the Owner wants.
- **Extend Content ideas with media and Platform text**: rejected. An idea is a Markdown note that moves from New to Done; a Library item is finished, reusable content. Mixing them would give ideas fields they never use and a status that means nothing for content.
- **Store ready content as Draft posts**: rejected. A Draft is tied to its Social accounts and becomes a Post once scheduled, so it cannot be reused, and the Owner's Drafts list would fill with content that is not meant to go out yet.
- **Schedule straight from the library on the web**: rejected. The composer already checks every Platform's rules and shows previews; a second scheduling form would repeat or skip those checks.

## Consequences

- YouTube tags become a Post platform setting (`meta.tags`) for YouTube Shorts, so a Library item's tags reach YouTube. The composer and `create_post` can set them too.
- Media uploaded for a Library item uses the same uploads as the composer and is stored once. Deleting a Library item leaves its files in place, because Posts made from it may still use them.
- Deleting a Library item keeps the Posts made from it; they only lose the link back.
