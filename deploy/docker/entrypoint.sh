#!/bin/sh
# Entrypoint kontainer Rafin.
#
# Dipakai bersama oleh tiga layanan — web, antrean, penjadwal — dan hanya
# satu di antaranya yang boleh menjalankan migration. Yang menentukan
# adalah RAFIN_MIGRATE=1, dipasang hanya pada layanan web di compose.

set -eu

catat() { printf '\033[1m▸ %s\033[0m\n' "$1" >&2; }
mati()  { printf '\033[31m✘ %s\033[0m\n'  "$1" >&2; exit 1; }

# ── Rahasia yang wajib ada ─────────────────────────────────────────────
#
# Diperiksa di sini, bukan dibiarkan gagal di tengah jalan. Aplikasi yang
# menyala tanpa APP_KEY akan menerima pendaftaran, menulis sesi, lalu
# kehilangan semuanya begitu kunci diisi belakangan.
[ -n "${APP_KEY:-}" ]             || mati 'APP_KEY kosong. Jalankan deploy/docker-deploy.sh yang membangkitkannya sekali lalu menyimpannya.'
[ -n "${DB_PASSWORD:-}" ]         || mati 'DB_PASSWORD kosong.'
[ -n "${DB_MIGRATE_PASSWORD:-}" ] || mati 'DB_MIGRATE_PASSWORD kosong.'

# ── Menunggu database ──────────────────────────────────────────────────
#
# depends_on: condition: service_healthy sudah menangani ini di compose,
# tapi kontainer juga dijalankan sendiri saat perbaikan, dan pesan
# "connection refused" dari PDO tidak menjelaskan apa yang harus ditunggu.
catat "Menunggu PostgreSQL di ${DB_HOST}:${DB_PORT}"
i=0
until pg_isready -h "$DB_HOST" -p "$DB_PORT" -q; do
    i=$((i + 1))
    [ "$i" -lt 60 ] || mati "PostgreSQL tidak menjawab setelah 60 detik."
    sleep 1
done

# ── Direktori yang bisa ditulis ────────────────────────────────────────
#
# storage/ adalah volume, jadi isinya bertahan lintas rilis — dan karena
# itu strukturnya tidak ikut dari image. Dibuat ulang setiap kali start.
mkdir -p storage/framework/cache/data \
         storage/framework/sessions \
         storage/framework/views \
         storage/logs \
         storage/app/private \
         storage/app/public
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# ── Migration ──────────────────────────────────────────────────────────
if [ "${RAFIN_MIGRATE:-0}" = "1" ]; then
    catat 'Menjalankan migration lewat role pemilik skema'
    # rafin:migrate, bukan migrate polos: koneksi bawaan memakai rafin_app
    # yang sengaja tanpa hak DDL, supaya RLS benar-benar mengikat (A4).
    php artisan rafin:migrate --force

    catat 'Memastikan partisi bulan berjalan tersedia'
    php artisan rafin:partitions
fi

# ── Cache ──────────────────────────────────────────────────────────────
#
# Dibangun saat start, bukan saat build image: nilainya bergantung pada
# .env yang baru ada di server. config:cache mematikan pembacaan .env,
# jadi urutannya penting — clear dulu, baru cache.
catat 'Membangun cache konfigurasi, rute, dan view'
php artisan config:clear >/dev/null
php artisan config:cache >/dev/null
php artisan route:cache  >/dev/null
php artisan view:cache   >/dev/null
php artisan event:cache  >/dev/null

# Tautan storage publik. Lampiran TIDAK lewat sini — aturan A11 menaruhnya
# di disk privat dan hanya melayaninya lewat URL bertanda tangan. Tautan ini
# untuk aset publik biasa saja.
[ -L public/storage ] || php artisan storage:link >/dev/null 2>&1 || true

catat 'Siap'
exec "$@"
