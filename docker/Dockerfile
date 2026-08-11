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
    opcache

# PUT TO COMPOSE FILE
ENV FRANKENPHP_WORKER=1

WORKDIR /app

COPY composer.json composer.lock ./

EXPOSE 80


# ===============================
# Development
# ===============================
FROM base AS dev

# RUN composer install \
#     --prefer-dist \
#     --no-scripts

# --watch enables HMR-like reload for SSE/stream development
COPY . .
CMD frankenphp run --config /app/config/Caddyfile --watch


# ===============================
# Production
# ===============================
FROM base AS prod
# RUN composer install \
#     --no-dev \
#     --no-scripts \
#     --prefer-dist \
#     --optimize-autoloader

COPY . .
CMD frankenphp run --config /app/config/Caddyfile