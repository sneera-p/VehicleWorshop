#!/bin/sh
# Dev entrypoint for web: Bun's asset watcher + FrankenPHP, side by side.
# Run it again via `docker compose exec` to restart both in place.

set -e

start() {
   # Asset watcher, in the background.
   (
      while true; do
         if ! bun run dev:web; then
            echo "Asset watcher exited — restarting in 2s" >&2
         fi
         sleep 2
      done
   ) &


   # FrankenPHP takes over this process (PID 1), so `docker stop`
   # reaches it directly. Bun is orphaned on shutdown — fine for dev.
   exec frankenphp run --config /app/web/config/Caddyfile.dev --watch
}

restart() {
   # FrankenPHP is PID 1 — reload it; killing it would stop the container.
   frankenphp reload --config /app/web/config/Caddyfile.dev

   # The loop in start() respawns it.
   pkill -f 'build.config.ts' 2>/dev/null || true
}

# Already running → restart. Otherwise → start.
if pgrep -f 'frankenphp run' >/dev/null 2>&1; then
   restart
else
   start
fi
