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
RUN install-php-extensions \
   pdo_pgsql \
   redis \
   gd \
   opcache \
   @composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80



# ===============================
# Development
# ===============================
FROM base AS dev

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

# --watch enables HMR-like reload for SSE/stream development
CMD ["frankenphp", "run", "--config", "/app/config/Caddyfile", "--watch"]



# ===============================
# Production
# ===============================
FROM base AS prod

ENV FRANKENPHP_WORKER=1

COPY . .
RUN composer install \
   --no-dev \
   --no-scripts \
   --prefer-dist \
   --optimize-autoloader

CMD ["frankenphp", "run", "--config", "/app/config/Caddyfile.prod"]
