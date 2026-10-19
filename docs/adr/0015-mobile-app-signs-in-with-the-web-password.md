# The Mobile app signs in with the web password

Amends ADR 0010: `MOBILE_TOKEN` is gone; the Mobile app signs in with the Owner's web email and password and holds a Mobile token of its own.

`MOBILE_TOKEN` was one long secret from the server's `.env`. The Owner could not remember it, so signing in meant opening the server, copying the secret and typing it on a phone. The same secret sat on the phone and in `.env`, signing out on the phone cancelled nothing, and locking out a lost phone meant editing `.env` and restarting the app.

So `POST /mobile/v1/sessions` takes the Owner's web email and password, checks them with the web login's own rules and throttle, and returns a **Mobile token**: a random secret for that phone, stored on the server only as its SHA-256 hash in `mobile_tokens`. Every other `/mobile/v1` call carries it. Signing out on the phone deletes that token, and changing the Owner's password, from web Settings or `owner:reset-password`, deletes every one.

## Considered Options

- **Keep `MOBILE_TOKEN` and show it in web Settings**: rejected. The phone still types a 48-character secret, and Sign out still cancels nothing.
- **Sanctum or Passport tokens**: rejected, as in ADR 0010. One table and one middleware cover one Owner; a new package does not earn its place.
- **Tokens that expire**: rejected. The Owner signs in once per phone; a password change or Sign out ends a token.

## Consequences

- The mobile API is always on; there is no setting that turns it off. Anyone who reaches it still needs the Owner's password, under the same 5-try throttle as the web login.
- A phone that still holds the old `MOBILE_TOKEN` gets 401 once and goes back to Sign in. The `/mobile/v1` responses do not change, so no `/mobile/v2` is needed.
- Only the Owner, the install's first user, can sign in; another user with the right password is refused like a wrong password.
- `MCP_TOKEN` and `CRON_SECRET` keep the one-secret pattern; `AuthenticatesWithOwnerToken` now serves only the MCP server.
