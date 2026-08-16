#!/bin/sh

# docker dev entrypoint — also reusable via `docker compose exec` to
# restart the dev processes without restarting the container.

# Dev only — runs Bun's watcher (app/resources/{ts,scss} -> public/assets/)
# alongside FrankenPHP, so both stay live while developing.

set -e

start() {
   # Backgrounded: watches and recompiles TS/SCSS on change.
   bun run dev &

   # Foregrounded via exec: FrankenPHP replaces this script as the
   # container's main process, so it receives `docker stop`'s SIGTERM
   # directly and shuts down cleanly — same as before this script existed.
   #
   # Trade-off, backgrounded bun process above doesn't get an explicit shutdown signal —
   # it's orphaned when frankenphp exits. Acceptable for a dev-only container
   # that gets torn down wholesale by `docker compose down` anyway

   # --watch enables HMR-like reload for SSE/stream development
   exec frankenphp run --config /app/config/Caddyfile.dev --watch
}

restart() {
   # FrankenPHP is PID 1 here (see start(), above) — reload rather than
   # kill, so the container survives.
   frankenphp reload --config /app/config/Caddyfile.dev

   # bun/sass watchers: no reload signal exists for `bun build --watch`
   # or `sass --watch` — they already pick up file changes live on their
   # own, so "restart" here just means kill and respawn the process tree
   # (e.g. to recover from a crashed or stuck watcher). Not PID 1, so a
   # plain kill is safe.
   pkill -f "bun (run|build) dev|sass --watch" 2>/dev/null || true
   cd /app && nohup bun run dev >/proc/1/fd/1 2>/proc/1/fd/2 &
}

# No args (container entrypoint) → start. "restart" → restart in place.
# Auto-detected either way: if FrankenPHP's already up, always restart,
# regardless of how this script was invoked.
if pgrep -f "frankenphp run" >/dev/null 2>&1; then
   restart
else
   start
fi
