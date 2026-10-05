---
paths:
  - app/Support/PostPlatformMetaRules.php
---

# Support

## Never use a cross-field Laravel rule (required_unless, required_if, etc.) for a single platform's conditional meta field
`rules()` is shared by every platform via `platforms.*.meta.*` wildcards. Rules like `required_unless`/`required_if` are Laravel "implicit" rules — they validate even when the field itself is absent from the request. Adding one scoped in spirit to a single platform (e.g. a field required only for TikTok) breaks every OTHER platform's create/update, because their requests never send that field at all and the implicit rule still fires. The correct pattern (already used by TikTok's `privacy_level` and YouTube's `description`): keep the field's `rules()` entry unconditional (`sometimes|nullable|...`), and enforce "required" semantics only in `requiredMetaViolation()`'s `match` block, which is evaluated per the resolved `Platform` of the row being checked. This bug shipped once (fixed in commit 8887b3f3) and broke every sibling platform's post updates.
