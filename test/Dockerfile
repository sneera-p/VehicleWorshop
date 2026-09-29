# syntax=docker/dockerfile:1
# test/Dockerfile — needs named contexts: playwright, bun, composer
# (see .docker/docker-compose.test.yml)

ARG PLAYWRIGHT_IMAGE


# ===============================
# Testing (PHPUnit + Playwright)
# ===============================
# Start from Playwright: browsers are the hard part. PHP is easy to add.
FROM ${PLAYWRIGHT_IMAGE} AS test
WORKDIR /app

# Ubuntu 26.04 ships PHP 8.5 in its own repo — no PPA needed.
# Same extensions as prod, minus opcache and gd — tests don't need them.
# Ubuntu 26.04 ships PHP 8.5 in its own repo — no PPA needed.
# Same extensions as prod, minus opcache and gd — tests don't need them.
RUN apt-get update \
   && apt-get install -y --no-install-recommends \
   fish \
   php8.5-cli \
   php8.5-pgsql \
   php8.5-redis \
   php8.5-mbstring \
   php8.5-xml \
   php8.5-curl \
   php8.5-zip \
   && rm -rf /var/lib/apt/lists/*

# CBOR extension: not packaged for Ubuntu, so build it from source.
RUN apt-get update \
   && apt-get install -y --no-install-recommends php8.5-dev build-essential autoconf \
   && mkdir -p /tmp/cbor \
   && curl -fsSL "https://codeload.github.com/ranvis/php-ext-cbor/tar.gz/refs/tags/v0.4.11" \
      | tar -xz -C /tmp/cbor --strip-components=1 \
   && cd /tmp/cbor \
   && phpize8.5 \
   && ./configure --enable-cbor --with-php-config=php-config8.5 \
   && make -j"$(nproc)" \
   && make install \
   && echo 'extension=cbor.so' > /etc/php/8.5/mods-available/cbor.ini \
   && phpenmod -v 8.5 cbor \
   && php -r 'exit(function_exists("cbor_encode") ? 0 : 1);' \
   && cd / && rm -rf /tmp/cbor \
   && apt-get purge -y --auto-remove php8.5-dev build-essential autoconf \
   && rm -rf /var/lib/apt/lists/*

# Composer and Bun: single files from their pinned images.
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY --from=bun /usr/local/bin/bun /usr/local/bin/bun
ENV COMPOSER_ALLOW_SUPERUSER=1

# JS deps first, so this layer survives source edits.
COPY package.json bun.lock* ./
RUN bun install --frozen-lockfile

# Whole repo — worker/ and console/ included, since we test them too.
COPY . .

# With dev deps: that's where PHPUnit lives.
RUN composer install \
   --prefer-dist \
   --no-scripts \
   && rm -rf "$(composer config cache-dir)"

# Terminal look for the devcontainer: fish + Tide prompt.
ARG FISHER_VERSION=4.4.5
ARG TIDE_VERSION=6.1.1
RUN fish -c "curl -sL https://raw.githubusercontent.com/jorgebucaran/fisher/${FISHER_VERSION}/functions/fisher.fish | source \
   && fisher install jorgebucaran/fisher@${FISHER_VERSION} IlanCosman/tide@v${TIDE_VERSION} \
   && tide configure --auto \
      --style=Rainbow \
      --prompt_colors='True color' \
      --show_time=No \
      --rainbow_prompt_separators=Round \
      --powerline_prompt_heads=Round \
      --powerline_prompt_tails=Flat \
      --powerline_prompt_style='One line' \
      --prompt_spacing=Sparse \
      --icons='Few icons' \
      --transient=No \
   && set -U fish_greeting"

# Stay alive; tests run on demand via `docker compose exec`.
CMD ["tail", "-f", "/dev/null"]
