# Live streams loop a Video to YouTube from the server

The Owner wants a pre-made Video, such as a 2-hour Dhamma talk, to go live on their YouTube channel and repeat until they stop it, without their computer staying on. A **Live stream** is its own model, not a Post: it runs for hours, can be stopped, and has a lifecycle (scheduled, live, stopped, failed) that a Post's one-shot publish does not. The app creates and binds the YouTube broadcast through the Live Streaming API with the `youtube.force-ssl` scope it already holds, so the Owner never copies a stream key.

The server streams with ffmpeg in copy mode (`-stream_loop -1 -re -c copy`), never re-encoding: the 2 GB VPS cannot encode 1080p in real time. So a Video must already be stream-ready (H.264 with a keyframe at least every 4 seconds, and AAC audio), which the app checks when a Live stream is created. ffmpeg runs in its own supervisor program, a `queue:work` on a `live` queue with no timeout, and only one Live stream runs at a time. The worker copies the Video from R2 to local disk first, so a network hiccup to R2 cannot break a stream that lasts hours.

## Considered Options

- **Post Content type "YouTube Live"**: rejected. Posts publish once through the per-platform publishers; a stream needs Stop, restarts and a running state.
- **Paste a stream key from YouTube Studio**: rejected. It needs manual setup in Studio for every stream, and the API is cheap (about 4 quota units a stream).
- **Re-encode on the server**: rejected. Real-time 1080p encoding needs far more CPU than the VPS has; files are made stream-ready when they are rendered.
- **Run ffmpeg in the existing worker**: rejected. Its 930-second timeout would kill the stream, and a stream would block every publish behind it.
- **Stream from the Owner's computer, a paid restream service, or a YouTube Premiere**: rejected. The first needs the computer and its connection up for hours, the second puts channel access with a third party, and the third cannot be set up through the API.

## Consequences

- The production image gains `ffmpeg`, and supervisor gains a second worker.
- YouTube only keeps a recording of streams under 12 hours; a Live stream left running longer may leave no recording.
- A deploy or restart kills ffmpeg; the worker resumes Live streams that were live, from the start of the Video.
- A Video used by a scheduled or live Live stream cannot be deleted.
- A fifth Notification type, Live stream failed, joins the others.
