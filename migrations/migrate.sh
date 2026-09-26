#!/usr/bin/env bash
# Apply pending SQL migrations from migrations/ to the dev Postgres container.
#
# Usage:
#   ./migrate.sh run path/to/env      apply pending migrations (already-applied ones are skipped)
#   ./migrate.sh status path/to/env   list applied / pending migrations
#
# Config (read from .docker/.env, overridable from the environment):
#   DB_USER, DB_NAME       Postgres credentials (required)
#   DB_CONTAINER           container name/ID; if unset, uses db service from docker-compose
#   DB_SERVICE             the docker compose service (default: db)

set -euo pipefail

[[ "${1:-}" == run || "${1:-}" == status ]] || { echo "usage: $0 <run|status>" >&2; exit 1; }

# Script lives in <repo>/migrations/, so the repo root is one level up.
MIGRATIONS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(dirname "$MIGRATIONS_DIR")"

# Load .env without clobbering values already set in the environment.
# Optional 2nd arg: env file path (relative to cwd). Defaults to <repo>/.docker/.env.
ENV_FILE="${2:-$ROOT/.docker/.env}"
if [[ -f "$ENV_FILE" ]]; then
  while IFS='=' read -r key value; do
    [[ -z "$key" || "$key" == \#* ]] && continue
    [[ -z "${!key:-}" ]] && export "$key=$value"
  done < "$ENV_FILE"
elif [[ -n "${2:-}" ]]; then
  echo "env file not found: $ENV_FILE" >&2
  exit 1
fi

: "${DB_USER:?DB_USER not set}"
: "${DB_NAME:?DB_NAME not set}"

if [[ -n "${DB_CONTAINER:-}" ]]; then
  EXEC=(docker exec -i "$DB_CONTAINER")
else
  EXEC=(docker compose -f "$ROOT/.docker/docker-compose.yml" exec -T "${DB_SERVICE:-db}")
fi

psql_q() { "${EXEC[@]}" psql -U "$DB_USER" -d "$DB_NAME" -v ON_ERROR_STOP=1 -qtA "$@"; }

# Versions already applied (empty if the tracking table doesn't exist yet).
applied_versions() {
  if [[ "$(psql_q -c "SELECT to_regclass('public.schema_migrations') IS NOT NULL")" == "t" ]]; then
    psql_q -c "SELECT version FROM schema_migrations ORDER BY version"
  fi
}

shopt -s nullglob
files=("$MIGRATIONS_DIR"/[0-9]*.sql)
(( ${#files[@]} )) || { echo "no migrations in $MIGRATIONS_DIR"; exit 0; }

# Fail loudly if the DB is unreachable, instead of treating everything as pending.
psql_q -c "SELECT 1" > /dev/null || { echo "cannot reach database" >&2; exit 1; }

applied="$(applied_versions)"
is_applied() { grep -qxF "$1" <<< "$applied"; }

case "${1:-}" in
  status)
    for f in "${files[@]}"; do
      v="$(basename "$f" .sql)"
      if is_applied "$v"; then echo "  applied  $v"; else echo "  pending  $v"; fi
    done
    ;;
  run)
    count=0
    for f in "${files[@]}"; do
      v="$(basename "$f" .sql)"
      is_applied "$v" && continue
      echo "applying $v"
      # File + its tracking row in ONE transaction: both land or neither does.
      { cat "$f"; printf "\nINSERT INTO schema_migrations (version) VALUES ('%s');\n" "$v"; } \
        | psql_q -1
      count=$((count + 1))
    done
    echo "done: $count applied"
    ;;
  *)
    echo "usage: $0 <run|status>" >&2
    exit 1
    ;;
esac
