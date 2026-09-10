# syntax=docker/dockerfile:1


# ===============================
# Base PHP + Extensions (Shared)
# ===============================
FROM php:8.5-cli-trixie AS base

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
   pdo_pgsql \
   redis \
   opcache \
   iconv \
   @composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1


# ===============================
# Development
# ===============================
FROM base AS dev

RUN install-php-extensions xdebug
RUN { \
   echo "xdebug.mode=debug,develop"; \
   echo "xdebug.start_with_request=trigger"; \
   echo "xdebug.client_host=localhost"; \
   echo "xdebug.client_port=9003"; \
   echo "xdebug.log_level=0"; \
   } > "${PHP_INI_DIR}/conf.d/99-xdebug.ini"

# web/ is excluded — no HTTP surface, no frontend assets, nothing this
# container will ever run. console/ is intentionally NOT excluded — it
# shares this image and runs via
# `docker compose exec worker php console/console <command>`, never its
# own container.
COPY --exclude=web . .

# --no-dev is deliberately absent here — dev needs PHPUnit, php-cs-fixer,
# etc. present. No --optimize-autoloader either, since source changes
# should be picked up live via the bind mount, not baked into a static map.
RUN composer install \
   --prefer-dist \
   --no-scripts

CMD ["php", "worker/main.php"]


# ===============================
# Production
# ===============================
FROM base AS prod

COPY --exclude=web \
   --exclude=.tools \
   --exclude=package.json \
   --exclude=bun.lock* \
   . .

RUN composer install \
   --no-dev \
   --no-scripts \
   --prefer-dist \
   --optimize-autoloader

CMD ["php", "worker/main.php"]
