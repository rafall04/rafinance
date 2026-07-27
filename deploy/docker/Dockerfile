# Image produksi Rafin.
#
# Tiga tahap, supaya yang ikut ke image akhir hanya hasilnya:
#   1. aset  — Node membangun bundel Vite
#   2. vendor — Composer mengunduh dependensi tanpa dev
#   3. runtime — PHP-FPM + nginx, tanpa Node, tanpa Composer, tanpa toolchain
#
# nginx dan php-fpm berbagi satu kontainer di bawah supervisord. Untuk satu
# kotak yang sudah menjalankan dua puluh kontainer lain, satu proses less
# adalah satu titik gagal less — dan nginx tetap perlu membaca public/ yang
# sama dengan yang dieksekusi php-fpm.

# ── 1. Aset ────────────────────────────────────────────────────────────
FROM node:20-bookworm-slim AS aset

WORKDIR /app

# package*.json lebih dulu: selama keduanya tidak berubah, lapisan npm ci
# dipakai ulang dan pembangunan berikutnya melewati unduhan sepenuhnya.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build


# ── 2. Vendor ──────────────────────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts: script post-autoload-dump memanggil artisan, dan artisan
# belum ada di tahap ini. Autoloader dibangkitkan ulang di tahap runtime
# setelah seluruh kode disalin.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative


# ── 3. Runtime ─────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS runtime

# Pustaka yang dibutuhkan saat berjalan. Yang hanya dibutuhkan saat
# mengompilasi ekstensi dipasang sebagai .build-deps dan dibuang lagi di
# perintah yang sama, supaya tidak ikut menetap di lapisan image.
RUN apk add --no-cache \
        nginx \
        supervisor \
        postgresql16-client \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
        libsodium \
        oniguruma \
        tzdata \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        postgresql16-dev \
        icu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libsodium-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        bcmath \
        gd \
        zip \
        sodium \
        pcntl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

WORKDIR /var/www/html

COPY --from=vendor /app ./
COPY --from=aset /app/public/build ./public/build

COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/rafin.ini
COPY deploy/docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-rafin.conf
COPY deploy/docker/nginx.conf /etc/nginx/nginx.conf
COPY deploy/docker/supervisord.conf /etc/supervisord.conf
COPY deploy/docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Berjalan sebagai www-data, bukan root. storage/ dan bootstrap/cache
# adalah satu-satunya tempat yang perlu bisa ditulis.
RUN mkdir -p storage/framework/{cache/data,sessions,views,testing} \
             storage/logs storage/app/private storage/app/public \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u+rwX storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up >/dev/null 2>&1 || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
