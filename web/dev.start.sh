#!/bin/sh
# Dev entrypoint for web: Bun's asset watcher + FrankenPHP, side by side.
# Run it again via `docker compose exec` to restart both in place.

set -e

start() {
   # Asset watcher, in the background.
   bun run dev:web &

   # FrankenPHP takes over this process (PID 1), so `docker stop`
   # reaches it directly. Bun is orphaned on shutdown — fine for dev.
   exec frankenphp run --config /app/web/config/Caddyfile.dev --watch
}

restart() {
   # FrankenPHP is PID 1 — reload it; killing it would stop the container.
   frankenphp reload --config /app/web/config/Caddyfile.dev

   # The watcher has no reload, so kill and respawn it.
   # Match build.config.ts: that's the real watcher, not the `bun run` parent.
   pkill -f 'build.config.ts' 2>/dev/null || true
   cd /app && nohup bun run dev:web >/proc/1/fd/1 2>/proc/1/fd/2 &
}

# Already running → restart. Otherwise → start.
if pgrep -f 'frankenphp run' >/dev/null 2>&1; then
   restart
else
   start
fi
