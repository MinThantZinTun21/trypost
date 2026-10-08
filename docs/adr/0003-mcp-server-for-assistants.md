# An MCP server for the Owner's assistant, written from scratch

ADR 0001 removed TryPost's REST API and MCP server. We bring back only an MCP server, because the Owner wants Claude Code to list Social accounts and create Posts (with media, Titles and Descriptions) without opening the composer. It is written from scratch on the official `laravel/mcp` package: none of the removed TryPost MCP code, Passport or OAuth comes back. It lives at `POST /mcp` and is guarded by one bearer secret, `MCP_TOKEN` (empty = MCP off, 404), the same single-Owner pattern as `CRON_SECRET`.

## Considered Options

- **Restore the old TryPost MCP**: rejected. It was built for teams and workspaces and leaned on Passport OAuth, both of which the fork removed.
- **Hand-written JSON-RPC endpoint**: rejected. `laravel/mcp` handles the protocol and is maintained by Laravel; writing it by hand is more code to own.
- **OAuth or database-issued tokens**: rejected. There is one Owner and one client; a single rotating secret is enough.

## Consequences

- MCP tools go through the same validation and actions as the composer, so an Assistant can create nothing the web app could not.
- Media reaches the server by presigned upload to object storage (the Assistant runs `curl`), so the MCP needs R2/S3 storage; it cannot read the Owner's local files.
- "Publish now" from an Assistant queues the Post like the composer; on Vercel it goes out on the next cron run.
