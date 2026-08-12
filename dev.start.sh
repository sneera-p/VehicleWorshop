#!/bin/sh

# docker dev entrypoint

# Dev only — runs Bun's watcher (app/resources/{ts,scss} -> public/assets/)
# alongside FrankenPHP, so both stay live while developing.

set -e

# Backgrounded: watches and recompiles TS/SCSS on change.
bun run dev &

# Foregrounded via exec: FrankenPHP replaces this script as the
# container's main process, so it receives `docker stop`'s SIGTERM
# directly and shuts down cleanly — same as before this script existed.
#
# Trade-off, stated plainly: exec-ing frankenphp means the backgrounded
# bun process above doesn't get an explicit shutdown signal — it's
# orphaned when frankenphp exits. Acceptable for a dev-only container
# that gets torn down wholesale by `docker compose down` anyway; not a
# pattern to carry into anything that needs precise multi-process
# shutdown guarantees.

# --watch enables HMR-like reload for SSE/stream development
exec frankenphp run --config /app/config/Caddyfile --watch
