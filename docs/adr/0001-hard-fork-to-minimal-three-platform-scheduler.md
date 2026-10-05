# Hard fork to a minimal single-owner scheduler for Facebook, TikTok and YouTube

This fork of TryPost deletes every feature outside scheduling and publishing posts to Facebook Pages, TikTok and YouTube for a single Owner, rather than hiding them behind config. We chose deletion because the goal is a smaller app with fewer server requirements, and hidden code keeps both the size and the infrastructure. The cost is accepted: pulling future updates from upstream `trypostit/trypost` is no longer practical, so this repo is maintained as an independent fork.

## Considered Options

- **Hide features in the UI and switch them off in config**: rejected. It keeps every package, table and background process, so the app is no lighter to run.

## Consequences

- Removed for good: the other ten platforms, AI, Repurpose, analytics, the asset library (Unsplash, Giphy), signatures, labels, comments, teams and invites, multiple workspaces, billing, the REST API and MCP server, outgoing webhooks, social login, product analytics, live updates over websockets, email notifications, and every language except English.
- There is no public sign-up. The Owner account is created by the seeder.
- The app sends no email at all, not even for password resets. A forgotten password is reset on the server with an artisan command, so no mail provider is needed.
- Workspaces and accounts stay in the database as internal plumbing, with exactly one of each per Owner, and are never shown in the UI. Removing them would mean rewriting every model and policy.
