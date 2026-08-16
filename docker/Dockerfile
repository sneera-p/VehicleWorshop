# syntax=docker/dockerfile:1


# ===============================
# Base PHP + Extensions (Shared)
# ===============================
FROM dunglas/frankenphp:1.12.7-php8.5-trixie@sha256:4b0713ddad6ca7eb21eb82ac6bdb7cb41de5192a930b615d89af6e15d74e82f8 AS base

# Extensions mapped directly from ER & Activity diagrams:
# pdo_pgsql    → Vehicle, Job, Appointment, Invoice, Supplier Bill entities
# redis        → Valkey pub/sub (notifications, SSE streams, session tokens)
# gd           → Vehicle Damage.Picture, User.Picture (ER diagram)
# opcache      → Production performance
# iconv        → required by symfony/polyfill-mbstring (php-cs-fixer dep)
RUN install-php-extensions \
   pdo_pgsql \
   redis \
   gd \
   opcache \
   iconv \
   @composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80



# ===============================
# Development
# ===============================
FROM base AS dev

# netcat: dev-only debugging aid (port/connectivity checks). Not needed in prod.
RUN apt-get update \
   && apt-get install -y --no-install-recommends netcat-traditional \
   && rm -rf /var/lib/apt/lists/*

# Xdebug is dev-only — it carries a significant runtime performance cost and
# must never ship to production. Installed but idle by default: it only
# activates when a debug session triggers it (start_with_request=trigger),
# so normal dev requests aren't slowed down.
RUN install-php-extensions xdebug
RUN { \
   echo "xdebug.mode=debug,develop"; \
   echo "xdebug.start_with_request=trigger"; \
   echo "xdebug.client_host=localhost"; \
   echo "xdebug.client_port=9003"; \
   echo "xdebug.log_level=0"; \
   } > "${PHP_INI_DIR}/conf.d/99-xdebug.ini"

COPY . .
RUN composer install \
   --prefer-dist \
   --no-scripts

# Bun: frontend asset toolchain (TypeScript + SCSS bundling into public/).
# Copied from the official image rather than curl-installed — keeps the
# version pinned and avoids a network fetch during build.
COPY --from=oven/bun:1.3-debian@sha256:9dba1a1b43ce28c9d7931bfc4eb00feb63b0114720a0277a8f939ae4dfc9db6f /usr/local/bin/bun /usr/local/bin/bun
RUN bun install --frozen-lockfile

# Runs both FrankenPHP and Bun's asset watcher concurrently. NOT done as
# CMD ["bun","run","dev","&","frankenphp",...] — exec-form CMD never invokes a shell
RUN chmod +x ./dev.start.sh

CMD [ "sh", "dev.start.sh"]



# ===============================
# Asset Build (prod assets only)
# ===============================
# Separate stage so Bun and node_modules never land in the prod image —
# only the compiled output in public/assets/ gets copied forward.
FROM oven/bun:1.3-debian@sha256:9dba1a1b43ce28c9d7931bfc4eb00feb63b0114720a0277a8f939ae4dfc9db6f AS assets

WORKDIR /app

COPY package.json bun.lock* ./
RUN bun install --frozen-lockfile

COPY tools/tsconfig*.json ./tools/
COPY app/resources/ts ./app/resources/ts
COPY app/resources/scss ./app/resources/scss

RUN bun run build



# ===============================
# E2E Testing (Playwright + Bun)
# ===============================
# Microsoft's image brings the browsers + their system deps; Bun is copied
# in the same way as the `assets` stage above since this image has no
# package manager for the project's bun.lock. Only what playwright.config.ts
# + the e2e suite need is copied in — not the whole repo.
FROM mcr.microsoft.com/playwright:v1.62.1-noble AS playwright

WORKDIR /app

COPY --from=oven/bun:1.3-debian@sha256:9dba1a1b43ce28c9d7931bfc4eb00feb63b0114720a0277a8f939ae4dfc9db6f \
   /usr/local/bin/bun /usr/local/bin/bun

COPY package.json bun.lock* ./
RUN bun install --frozen-lockfile

COPY tools/playwright.config.ts tools/tsconfig.base.json tools/tsconfig.test.json ./tools/
COPY tests/e2e ./tests/e2e

CMD [ "tail", "-f", "/dev/null" ]



# ===============================
# Production
# ===============================
FROM base AS prod

ENV FRANKENPHP_WORKER=1

# Bun itself was never installed in this stage; the compiled output
# arrives separately below, via COPY --from=assets.
COPY --exclude=app/resources/ts \
   --exclude=app/resources/scss \
   --exclude=package.json \
   --exclude=bun.lock* \
   --exclude=tools \
   --exclude=dev.start.sh \
   . .

RUN composer install \
   --no-dev \
   --no-scripts \
   --prefer-dist \
   --optimize-autoloader

COPY --from=assets /app/public/assets ./public/assets

CMD ["frankenphp", "run", "--config", "/app/config/Caddyfile.prod"]
