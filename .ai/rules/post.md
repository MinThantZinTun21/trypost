---
paths:
  - app/Actions/Post/FinalizePostPublication.php
  - app/Jobs/PublishPost.php
  - app/Actions/Post/UpdatePost.php
---

# Post

## FinalizePostPublication is the only post settler
handle() takes the Post, not a dummy PostPlatform. Every path that can finish the last enabled target must call it: PublishToSocialPlatform, RecoverStuckPosts and PublishPost::failed. No enabled targets on a draft or scheduled post is a no-op — do not mark the post published. A Publishing post with no enabled targets is abandoned in-flight: mark it Failed so it does not sit non-editable forever. Do not mark the post Published / PartiallyPublished / Failed by hand outside Finalize.

## Only failures notify
Finalize notifies the owner only when the post failed fully or partly, with a single in-app PostFailed Notification listing the failed platforms. A fully published post sends nothing. The app sends no email. Resolve title/body through lang/en/notifications.php (post_failed); do not hardcode English strings here.

## Finalize is idempotent once the post is settled
handle() lockForUpdates the post and returns without notifying when status is already Published, PartiallyPublished, or Failed (Status::isSettled()). Two paths (e.g. RecoverStuckPosts and a late job) can both finish the last target; the second call must not send a second notification. Dispatch SendNotification only after the transaction commits.
