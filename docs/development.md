# Development guide

Everything you need to work on vwork day to day. For how the system fits together, see [How vwork works](architecture.md).

---

## 🐳 Docker

The dev container is built from `.docker/docker-compose.dev.yml`, which starts everything:

| Container | What it runs | Image built from |
| --- | --- | --- |
| `web` | FrankenPHP + the asset watcher | `web/Dockerfile` |
| `worker` | background jobs, console commands | `worker/Dockerfile` |
| `test` | PHPUnit + Playwright (VS Code attaches here) | `test/Dockerfile` |
| `db` | PostgreSQL | official image |
| `cache` | Valkey | official image |

In dev, your project folder is mounted into every container at `/app`, so code changes apply without rebuilding. Rebuild only after changing a Dockerfile, a Compose file, or `.docker/.env`.

The same Dockerfiles also build production images (`target: prod`), which contain only what's needed to run: no tests, no dev dependencies, no build tools.

### Image pins

Every base image is pinned by digest in `.docker/.env`, e.g.:

```dotenv
PHP_IMAGE=php:8.5-cli-alpine3.24@sha256:9368...
```

Two rules decide how a Dockerfile uses a pinned image:

- **Base images (`FROM`) come in as build args:** `ARG PHP_IMAGE` then `FROM ${PHP_IMAGE}`. Tools like VS Code read `FROM` lines themselves, and they only understand this form.
- **Files copied from images (`COPY --from`) come in as named contexts,** declared under `additional_contexts:` in Compose, e.g. `COPY --from=composer ...`.

### Updating image pins

1. Look up the new digest for the tag you want:

   ```sh
   docker buildx imagetools inspect <image>:<tag> --format '{{json .Manifest.Digest}}'
   ```

2. Put `<image>:<tag>@<digest>` in `.docker/.env`.
3. Rebuild without cache and run all the tests:

   ```sh
   docker compose -f .docker/docker-compose.dev.yml build --no-cache
   ```

Use the digest printed at the top (the "index" digest). It works on every CPU architecture.

---

## 🧪 Testing

| Suite | Tests | Needs | Run with |
| --- | --- | --- | --- |
| Unit | one class at a time, everything else faked | nothing | `composer test:unit` |
| Integration | real Postgres and Valkey, no browser | `db`, `cache` | `composer test:integration` |
| End-to-end | a real browser clicking through the site | the whole stack | `bun run test:web` |

`composer test` runs unit and integration together. Run them all in the VS Code terminal.

Tests live next to the code they test, in each part's `test/` folder (e.g. `web/test/Unit/`).

**The rule for unit tests:** they must never touch the database or cache. If a test needs Postgres to pass, it's an integration test.

To run a single test, pass PHPUnit's filter through Composer:

```sh
composer test:unit -- --filter=ResponseTest
```

---

## 🐞 Debugging

Xdebug is installed in `web` and `worker` in dev, and stays idle until you trigger it.

1. In VS Code, open **Run and Debug** and start the listener you need:
   - **Listen for Xdebug (web)**, on port 9003
   - **Listen for Xdebug (worker)**, on port 9004
2. Set a breakpoint.
3. Trigger it:
   - **web:** add `?XDEBUG_TRIGGER=1` to the URL, or turn on the *Xdebug Helper* browser extension.
   - **worker or console:** set `XDEBUG_TRIGGER=1` for the command, from a terminal outside the containers:

     ```sh
     docker compose -f .docker/docker-compose.dev.yml exec -e XDEBUG_TRIGGER=1 worker php worker/main.php
     ```

Execution pauses at your breakpoint. Use the **Debug Console** to run PHP against the paused code, and **conditional breakpoints** (right-click the gutter) inside loops.

That `exec` command starts a *new* worker process. It doesn't attach to the one already running. To debug the running worker, add `XDEBUG_TRIGGER: "1"` to worker's environment in `docker-compose.dev.yml`, start the listener, then run `docker compose -f .docker/docker-compose.dev.yml restart worker`.

---

## ✅ Code quality

| Command | What it does |
| --- | --- |
| `composer lint` | fixes code style (php-cs-fixer) |
| `composer lint:check` | reports style problems without changing files |
| `composer analyse:types` | PHPStan at max level, including architecture rules |
| `composer analyse:layer` | Deptrac: checks which layers may use which |
| `composer check` | style check + both analyses. This is what runs before a push |

Their settings live in `.tools/`.

---

## 🎨 Frontend

Each view is three files: PHP for the markup, SCSS for the styles, and TypeScript for the behaviour. There are no inline `<style>` or `<script>` tags.

Sources live in `web/resources/`, and Bun builds them into `web/public/assets/`.

| Command | What it does |
| --- | --- |
| `bun run dev:web` | rebuilds on every change (already running inside `web` in dev) |
| `bun run build:web` | one-off production build |
| `bun run typecheck:web` | type-checks the TypeScript |

Don't start a second watcher while `web` is running: two watchers writing the same files fight each other.
