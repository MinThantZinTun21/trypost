# Share the Chord Book server; its Caddy serves TryPost

ADR 0005 put TryPost on its own Vultr server with its own Caddy. The Owner already runs Chord Book on a Vultr server (1 vCPU, 1 GB, 2 GB of swap) whose Caddy holds ports 80 and 443, and chose to run TryPost there too, at its current size, rather than pay for a second server or a bigger plan.

Only one process can hold 80 and 443, so Chord Book's Caddy is the server's single entry point: it gets a third site, `TRYPOST_SITE`, that forwards to `trypost:80`. The two Compose projects share one Docker network, `edge`, which Chord Book's stack creates; TryPost's `compose.vultr.yaml` joins it as an external network and runs only the app, with no Caddy of its own and no published port. TryPost's app is capped at 512 MB so a slow upload cannot starve Chord Book's database.

Everything else in ADR 0005 stands: Supabase, R2, the manual Deploy workflow and the setup script, which is safe to run on the shared server (Docker, the firewall, swap and the `deploy` user are already there; it adds TryPost's deploy key and `/opt/trypost`).

## Considered Options

- **A second server for TryPost (ADR 0005 as written)**: rejected by the Owner on cost.
- **Resize the shared server to 2 GB**: recommended, and still the first fix if memory runs short; the Owner chose to stay at 1 GB for now.
- **TryPost's Caddy in front of both apps**: rejected. Chord Book's Caddy carries its scans, its rate limit and its own ADR (Chord Book 0032); moving the front door would couple Chord Book to TryPost's deploys.

## Consequences

- Chord Book deploys first: until its stack has created `edge`, the TryPost Deploy workflow stops with an error saying so.
- TryPost's domain is set in two places that must match: `APP_DOMAIN` in `/opt/trypost/.env` and `TRYPOST_SITE` in `/opt/chordbook/.env`.
- Both apps share 1 GB. If TryPost's uploads or Chord Book slow down, or containers are OOM-killed, resize the server in the Vultr console; nothing in either repo changes.
