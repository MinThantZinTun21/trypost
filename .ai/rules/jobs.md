---
paths:
  - app/Console/Commands/RecoverStuckPosts.php
---

# Jobs

## RecoverStuckPosts settles through FinalizePostPublication
When RecoverStuckPosts finishes a post (no still-active targets), call FinalizePostPublication so the owner is notified — do not mark the post Failed/Published by hand.
