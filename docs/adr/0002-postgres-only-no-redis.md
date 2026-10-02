# Postgres is the only backing service: database queue and cache, no Redis

The queue and the cache run on Postgres through Laravel's `database` drivers. Horizon and Redis are removed. At one Owner's volume (a few posts a day) the database queue keeps up easily, and Postgres still supports the cache locks the publish jobs rely on (`ShouldBeUnique`, `WithoutOverlapping`, `onOneServer`). That leaves one fewer service to host. The app runs a web process, one `queue:work` worker covering the per-platform `social-*` queues, and the scheduler.

## Considered Options

- **Keep Redis and replace Horizon with plain `queue:work`**: rejected. It still needs a Redis server for no gain at this volume.
