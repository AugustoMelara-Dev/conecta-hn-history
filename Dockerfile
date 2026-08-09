FROM node:22.13-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM dunglas/frankenphp:1-php8.4-bookworm AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=8080 \
    CONTAINER_ROLE=web

WORKDIR /app

RUN install-php-extensions \
    bcmath \
    exif \
    gd \
    intl \
    pcntl \
    pdo_mysql \
    pdo_pgsql \
    redis \
    zip

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p \
      bootstrap/cache \
      storage/app/private \
      storage/framework/cache \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
    && composer install \
      --prefer-dist \
      --no-interaction \
      --no-progress \
      --optimize-autoloader \
      --classmap-authoritative \
    && php artisan config:clear --quiet \
    && php artisan route:clear --quiet \
    && php artisan view:clear --quiet \
    && mkdir -p \
      /config/caddy \
      /data/caddy \
    && chown -R www-data:www-data /config/caddy /data/caddy bootstrap/cache storage

COPY docker/Caddyfile /etc/caddy/Caddyfile
RUN chmod +x /app/docker/entrypoint.sh /app/docker/healthcheck.sh

EXPOSE 8080

USER www-data

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["/app/docker/healthcheck.sh"]

ENTRYPOINT ["/app/docker/entrypoint.sh"]
