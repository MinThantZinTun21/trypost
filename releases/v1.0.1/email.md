---
subject: "Changelog: TryPost v1.0.1 — Self-host with the official Docker image"
---

# Changelog: TryPost v1.0.1 — Self-host with the official Docker image

By Paulo Castellano • Release v1.0.1

Hello! Welcome to this week's update. Here's what's new in TryPost.

## Self-host TryPost with one command

You can now run TryPost from an official Docker image. Every release builds one (Intel and ARM), so you pull it and go, no building from source. The compose setup brings up TryPost with its database and Redis alongside it, and your posts, drafts and uploads stay in volumes on your own machine.

Want it on a real domain? There's a built-in option that sorts out HTTPS for you, so you don't have to fight with certificates to get a secure install. And if you'd rather not keep uploads on the box, you can point storage at S3 or Cloudflare R2.

## New features

- An official Docker image you can pull and run (Intel + ARM).
- A one-command self-host stack with PostgreSQL and Redis included.
- Automatic HTTPS for your own domain.
- Media storage on S3 or Cloudflare R2.

Cheers,
Paulo Castellano from TryPost.it
