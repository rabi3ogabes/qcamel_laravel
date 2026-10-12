# syntax=docker/dockerfile:1.7
# Qatar Camel Festival ticketing — production images.
#   docker build -f docker/Dockerfile --target runtime -t qcamelmc:latest .
#   docker build -f docker/Dockerfile --target web     -t qcamelmc-web:latest .

# ── 1. Composer dependencies ─────────────────────────────────────────────────
FROM composer:2.8 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# ── 2. Front-end assets (Vite + Tailwind 4; the Filament theme needs vendor/) ─
FROM node:22.11-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY --from=vendor /app ./
RUN npm run build

# ── 3. PHP-FPM runtime (app, horizon, scheduler, reverb) ─────────────────────
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo freetype libwebp oniguruma fcgi tzdata \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev libwebp-dev oniguruma-dev linux-headers \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl gd zip exif pcntl opcache mbstring \
 && pecl install redis-6.1.0 && docker-php-ext-enable redis \
 && apk del .build-deps

COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-qcamelmc.ini

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app ./
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN rm -rf tests node_modules docker \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
 && ln -sfn /var/www/html/storage/app/public public/storage \
 && chown -R www-data:www-data storage bootstrap/cache public

USER www-data
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
EXPOSE 9000
CMD ["php-fpm"]

# ── 4. Nginx serving the same public/ tree ───────────────────────────────────
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/html/public /var/www/html/public
EXPOSE 80
