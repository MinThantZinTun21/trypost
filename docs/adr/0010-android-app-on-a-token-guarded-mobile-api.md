# An Android app on a small token-guarded mobile API

ADR 0001 removed TryPost's REST API, and ADR 0003 brought back only an MCP server for the Assistant. The Owner now wants a Mobile app on their Android phone to view Posts, edit their texts, and Reschedule or Unschedule them. It needs a way in that a native app can call.

So a narrow mobile API comes back under `/mobile/v1`, guarded by one bearer secret, `MOBILE_TOKEN` (empty = off, 404), the same single-Owner pattern as `MCP_TOKEN` and `CRON_SECRET`. It acts as the Owner and goes through the same actions and validation as the composer, so the Mobile app can do nothing the web app could not. The app is Kotlin with Jetpack Compose and lives in `android/` in this repo, so an endpoint and the screen that uses it land in one pull request.

## Considered Options

- **Call the MCP server from the app**: rejected. MCP is JSON-RPC shaped for an Assistant, with text-first replies; the app would parse prose and the Assistant's tools would bend to fit a phone.
- **Wrap the web app in a WebView**: rejected. It needs no backend work, but the Owner asked for an app, and a WebView keeps the desktop-first composer on a phone.
- **Restore the old REST API, or add Sanctum tokens**: rejected. The old API was built for teams and API keys; Sanctum is a new dependency for one Owner and one phone. One rotating secret is enough.
- **A separate repository**: rejected. Every app feature needs a server change; one repo keeps them in one review.

## Consequences

- The `tests/Feature/Removed` guards keep `/api/*` gone; the mobile API lives only under `/mobile/v1`.
- Rotating `MOBILE_TOKEN` signs the phone out; it does not touch `MCP_TOKEN`.
- The app is online-only and stores the token encrypted with the Android Keystore. It is sideloaded from a signed APK built by GitHub Actions; there is no Play Store listing.
- Times go over the wire as ISO 8601 with an offset; the app shows them in the phone's timezone, as the web app does in the browser's.

ADR 0011 removes the Drafts tab and Unschedule (`DELETE /mobile/v1/posts/{post}/schedule`), and ADR 0012 lets the app Post now, Send to Telegram and upload Videos; those lines above no longer hold.
