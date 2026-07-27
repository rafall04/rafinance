# Image produksi Rafin.
#
# Empat tahap. Yang penting bukan jumlahnya melainkan bahwa `base` dipakai
# bersama oleh tahap vendor dan runtime: Composer memeriksa versi PHP dan
# ekstensi yang tersedia sebelum memasang apa pun, jadi ia harus melihat
# platform yang persis sama dengan yang nanti menjalankannya. Menjalankan
# Composer di image terpisah adalah cara yang rapi untuk memasang dependensi
# yang salah tanpa ada yang memberi tahu.
#
# nginx dan php-fpm berbagi satu kontainer di bawah supervisord. Untuk satu
# kotak yang sudah menjalankan dua puluh kontainer lain, satu proses lebih
# sedikit adalah satu titik gagal lebih sedikit — dan nginx tetap harus
# membaca public/ yang sama dengan yang dieksekusi php-fpm.

# ── base: PHP + ekstensi ───────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

# Pustaka runtime dipasang permanen; yang hanya dibutuhkan untuk
# mengompilasi ekstensi dipasang sebagai .build-deps dan dibuang lagi di
# perintah yang sama, supaya tidak ikut menetap di lapisan image.
RUN apk add --no-cache \
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
        libpq-dev \
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
    && rm -rf /tmp/pear /usr/local/lib/php/doc

WORKDIR /var/www/html


# ── vendor: dependensi Composer ────────────────────────────────────────
FROM base AS vendor

RUN apk add --no-cache git unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./

# --no-scripts: script post-autoload-dump memanggil artisan, dan artisan
# belum disalin di titik ini. Autoloader dibangkitkan setelah seluruh kode
# ada, di perintah terakhir tahap ini.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && rm -rf /root/.composer /tmp/*


# ── aset: bundel Vite ──────────────────────────────────────────────────
#
# HARUS berada di bawah tahap vendor, dan urutannya bukan soal keterbacaan.
# BuildKit dengan driver docker bawaan menolak menyalin dari tahap yang
# didefinisikan belakangan:
#
#     failed to solve: cannot copy from stage "vendor",
#     it needs to be defined before current stage "aset"
#
# Rujukan maju hanya berlaku di buildx. Menaruh tahap aset lebih dulu terbaca
# lebih rapi — dan mematikan deploy.
FROM node:20-bookworm-slim AS aset

WORKDIR /app

# package*.json lebih dulu: selama keduanya tidak berubah, lapisan npm ci
# dipakai ulang dan pembangunan berikutnya melewati unduhan sepenuhnya.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

# Tema panel admin mengimpor CSS dasar Filament dari vendor/, dan memindai
# Blade-nya untuk tahu kelas mana yang perlu dibangkitkan Tailwind.
#
# vendor/ TIDAK ADA di konteks build — .dockerignore mengecualikannya dengan
# sengaja, supaya salinan lokal tidak ikut terkirim dan menimpa hasil composer
# install. Jadi ia harus datang dari tahap vendor, bukan dari konteks.
#
# Ini yang membuat deploy pertama setelah temanya ditambahkan gagal dengan
# "Can't resolve ../../../../vendor/filament/...". Build lokal tidak
# menangkapnya karena di laptop vendor/ memang ada.
COPY --from=vendor /var/www/html/vendor/filament ./vendor/filament

# app/Filament dipindai tema untuk kelas warna yang ditulis di PHP resource.
COPY app/Filament ./app/Filament

RUN npm run build


# ── runtime ────────────────────────────────────────────────────────────
FROM base AS runtime

RUN apk add --no-cache nginx supervisor

COPY --from=vendor /var/www/html ./
COPY --from=aset   /app/public/build ./public/build

COPY deploy/docker/php.ini        /usr/local/etc/php/conf.d/rafin.ini
COPY deploy/docker/php-fpm.conf   /usr/local/etc/php-fpm.d/zz-rafin.conf
COPY deploy/docker/nginx.conf     /etc/nginx/nginx.conf
COPY deploy/docker/supervisord.conf /etc/supervisord.conf
COPY deploy/docker/entrypoint.sh  /usr/local/bin/entrypoint

# Berjalan sebagai www-data. storage/ dan bootstrap/cache adalah
# satu-satunya tempat yang perlu bisa ditulis; sisanya hanya-baca.
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache/data \
                storage/framework/sessions \
                storage/framework/views \
                storage/logs \
                storage/app/private \
                storage/app/public \
                /tmp/nginx-client \
    && chown -R www-data:www-data storage bootstrap/cache /tmp/nginx-client \
    && chmod -R u+rwX storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=45s --retries=3 \
    CMD wget -qO- http://127.0.0.1/up >/dev/null 2>&1 || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
