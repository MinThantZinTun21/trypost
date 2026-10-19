# Tasks in the Mobile app

The Owner wants a to-do list next to their Posts: **Tasks** with a due date, reminders that ring on the phone, a timer, **Task tags** in **Tag groups**, photo, video and audio attachments, and voice entry. This widens TryPost past "a scheduler" on purpose, so it is recorded here and kept narrow: Tasks live only in the Mobile app over `/mobile/v1`, with no web page and no MCP tools, like Videos (ADR 0013).

A Task belongs to the Owner's workspace and is stored only on the server. The app stays online-only, as for Posts and Videos: no Room database, no sync endpoint. The one thing the phone keeps is the list of upcoming reminders, so it can set its alarms again after a reboot or while offline. Reminders are exact alarms (`setAlarmClock()` with `USE_EXACT_ALARM`), which the app may use because it is installed from the release APK, not Google Play.

Attachments are Media rows attached to the Task (collection `attachments`), uploaded straight to object storage through the Videos path. Audio becomes a fourth media type for this, accepted only by Task attachments; Post uploads and the composer still refuse it. The server makes no thumbnails (no ffmpeg on a 512 MB server): the app shows images with Coil and opens video and audio in the phone's own player.

Voice uses Android's built-in speech recognizer and a rule-based English parser. Nothing new is added to the app's dependencies.

## Considered Options

- **The spec's offline-first sync (Room, `/sync`, last-write-wins)**: rejected. It rebuilds the app's data layer for one feature, and the Owner is online when they plan their day; alarms already work offline.
- **sherpa-onnx, Vosk or whisper.cpp for voice**: rejected for now. Each adds a library and a model download, and none streams Burmese; the built-in recognizer needs neither.
- **On-device embeddings and a local LLM ("local brain")**: left out. It is the costliest part, nothing depends on it, and it gets its own decision once Tasks exist.
- **Calling them "Tags" or "Labels"**: rejected. **Tags** already means a YouTube Short's keywords, and Labels were removed on purpose (`tests/Feature/Removed`).
- **A separate app or repo**: offered and declined by the Owner; Tasks reuse the Mobile app's sign-in, design and upload path.

## Consequences

- `Media\Type` gains `Audio`; the "exactly three media types" rule becomes "three for Posts, plus Audio for Task attachments".
- The app asks for `RECORD_AUDIO`, `USE_EXACT_ALARM`, `RECEIVE_BOOT_COMPLETED` and `FOREGROUND_SERVICE_SPECIAL_USE` (the running timer).
- Repeating Tasks, a web page, MCP tools and local AI are out of scope.

## Amended: Keep-style Tasks with a rich-text body (#138)

The Owner asked for Tasks to work like Google Keep, with checklists and formatting. This reverses two choices above: the plain-text description and "checklists out of scope".

- **Body:** a Task's `description` holds a small Markdown subset, stored exactly as sent.
  - Blocks: `# ` and `## ` headings, `- ` bullets, `1. ` numbered items, `- [ ] ` / `- [x] ` checkboxes and paragraphs, one per line, no nesting.
  - Inline: `**bold**`, `*italic*` and `~~strike~~`, with `\` escaping a mark.
  - Markdown keeps the body readable as plain text, keeps the API's shape, and suits a later web page or MCP tool. The app parses and renders it itself, with no editor library.
- **Optional title:** a Task needs a title or a body, not both.
- **Colour and pin:** a Task may carry one of the Tag group colours and be pinned. The Open tab puts pinned Tasks first.
- **App layout:** a grid of cards, a "Take a note…" bar, and an editor that saves on Back.
- **Ticking a checkbox:** the app rewrites that line and sends the full `PUT`, so there is no checklist table and no new endpoint.
- **Rejected:** a separate checklist-items table (a second shape for the same text), and `compose-rich-editor` (a new dependency that still needs custom checkbox blocks).
