#!/usr/bin/env bash
# Deploy Rafin ke produksi.
#
#   bash deploy/deploy.sh
#
# Model rilis atomik:
#   /var/www/rafin/
#     releases/20260723-1830/   ← rilis baru diekstrak ke sini
#     shared/.env               ← rahasia, bertahan lintas rilis
#     shared/storage/           ← lampiran + log, bertahan lintas rilis
#     current -> releases/…     ← symlink, ditukar di detik terakhir
#
# Rollback = tukar symlink balik. Lihat rollback() di bawah.
#
# PRASYARAT: autentikasi kunci SSH sudah terpasang. Skrip ini TIDAK
# menerima password — password di baris perintah bocor ke shell history,
# ke daftar proses, dan ke log.
#
# Target deploy tidak ditulis di sini. Repo ini publik, dan alamat host
# internal memetakan topologi jaringan bagi siapa pun yang membacanya.
# Salin deploy/deploy.env.example ke deploy/deploy.env (sudah di
# .gitignore) dan isi di sana.

set -euo pipefail

# ── Konfigurasi ────────────────────────────────────────────────────────
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=/dev/null
[ -f "$HERE/deploy.env" ] && . "$HERE/deploy.env"

SSH_HOST="${SSH_HOST:-}"
SSH_USER="${SSH_USER:-root}"
SSH_PORT="${SSH_PORT:-22}"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/rafin_deploy}"

[ -n "$SSH_HOST" ] || {
    printf '\033[31m✘ SSH_HOST belum diisi.\033[0m\n' >&2
    printf '  cp %s/deploy.env.example %s/deploy.env\n' "$HERE" "$HERE" >&2
    printf '  lalu isi SSH_HOST di sana, atau: SSH_HOST=… bash deploy/deploy.sh\n' >&2
    exit 1
}

APP_DIR="/var/www/rafin"
APP_USER="rafin"
RELEASE="$(date +%Y%m%d-%H%M%S)"
KEEP_RELEASES=5

SSH_OPTS=(-i "$SSH_KEY" -p "$SSH_PORT" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)
remote() { ssh "${SSH_OPTS[@]}" "${SSH_USER}@${SSH_HOST}" "$@"; }

bold() { printf '\033[1m▸ %s\033[0m\n' "$1"; }
die()  { printf '\033[31m✘ %s\033[0m\n' "$1" >&2; exit 1; }

# ── 0. Pemeriksaan awal ────────────────────────────────────────────────
bold "Memeriksa koneksi ke ${SSH_USER}@${SSH_HOST}:${SSH_PORT}"
[ -f "$SSH_KEY" ] || die "Kunci SSH tidak ada: $SSH_KEY"
remote true 2>/dev/null || die "Tidak bisa SSH. Pasang kunci publik dulu:
    ssh-copy-id -i ${SSH_KEY}.pub -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"

bold "Memastikan .env produksi sudah ada di server"
remote "test -f ${APP_DIR}/shared/.env" \
    || die "${APP_DIR}/shared/.env belum ada. Salin dari deploy/env.production.template
    dan isi rahasianya LANGSUNG di server."

# ── 1. Build di lokal ──────────────────────────────────────────────────
# Build di mesin dev, bukan di produksi: produksi tidak perlu punya
# node_modules, dan build yang gagal tidak boleh terjadi setelah kode
# sudah mendarat di sana.
bold "Build aset frontend"
npm ci --silent
npm run build

bold "Install dependency PHP (tanpa dev)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ── 2. Paket ───────────────────────────────────────────────────────────
bold "Membuat paket rilis"
TARBALL="/tmp/rafin-${RELEASE}.tar.gz"
tar -czf "$TARBALL" \
    --exclude='./node_modules' \
    --exclude='./.git' \
    --exclude='./tests' \
    --exclude='./storage/logs/*' \
    --exclude='./storage/framework/cache/*' \
    --exclude='./storage/framework/sessions/*' \
    --exclude='./storage/framework/views/*' \
    --exclude='./.env' \
    --exclude='./.env.production' \
    --exclude='./.pest.cache' \
    --exclude='./.phpunit.cache' \
    --exclude='./scripts/*.local.ps1' \
    -C . .
echo "   $(du -h "$TARBALL" | cut -f1)"

# ── 3. Kirim ───────────────────────────────────────────────────────────
bold "Mengirim ke server"
remote "mkdir -p ${APP_DIR}/releases/${RELEASE} ${APP_DIR}/shared/storage"
scp "${SSH_OPTS[@]}" "$TARBALL" "${SSH_USER}@${SSH_HOST}:/tmp/" >/dev/null
remote "tar -xzf /tmp/rafin-${RELEASE}.tar.gz -C ${APP_DIR}/releases/${RELEASE} \
        && rm -f /tmp/rafin-${RELEASE}.tar.gz"
rm -f "$TARBALL"

# ── 4. Tautkan shared ──────────────────────────────────────────────────
bold "Menautkan .env dan storage bersama"
remote "cd ${APP_DIR}/releases/${RELEASE} \
        && rm -rf storage \
        && ln -sfn ${APP_DIR}/shared/storage storage \
        && ln -sfn ${APP_DIR}/shared/.env .env"

# ── 5. Migration ───────────────────────────────────────────────────────
# `php artisan migrate` polos DITOLAK — koneksi bawaan memakai rafin_app
# yang sengaja tidak punya hak DDL (aturan A4). Perintah kustom rafin:migrate
# yang berpindah ke role rafin_owner.
bold "Menjalankan migration (rafin:migrate, sebagai rafin_owner)"
remote "cd ${APP_DIR}/releases/${RELEASE} && php artisan rafin:migrate --force"

# ── 6. Cache ───────────────────────────────────────────────────────────
bold "Membangun cache produksi"
remote "cd ${APP_DIR}/releases/${RELEASE} \
        && php artisan config:cache \
        && php artisan route:cache \
        && php artisan view:cache \
        && php artisan event:cache \
        && php artisan filament:cache-components"

bold "Menyiapkan partisi ke depan"
remote "cd ${APP_DIR}/releases/${RELEASE} && php artisan rafin:partitions --months=6"

# ── 7. Kepemilikan ─────────────────────────────────────────────────────
bold "Menyetel kepemilikan berkas"
remote "chown -R ${APP_USER}:${APP_USER} ${APP_DIR}/releases/${RELEASE} ${APP_DIR}/shared \
        && find ${APP_DIR}/releases/${RELEASE} -type f -exec chmod 644 {} + \
        && find ${APP_DIR}/releases/${RELEASE} -type d -exec chmod 755 {} + \
        && chmod -R 775 ${APP_DIR}/shared/storage \
        && chmod 640 ${APP_DIR}/shared/.env \
        && chmod +x ${APP_DIR}/releases/${RELEASE}/artisan"

# ── 8. Tukar symlink — titik tanpa kembali ─────────────────────────────
bold "Menukar ke rilis baru"
remote "ln -sfn ${APP_DIR}/releases/${RELEASE} ${APP_DIR}/current.new \
        && mv -Tf ${APP_DIR}/current.new ${APP_DIR}/current"

# ── 9. Muat ulang service ──────────────────────────────────────────────
# reload, bukan restart: koneksi yang sedang jalan diselesaikan dulu.
bold "Memuat ulang PHP-FPM dan nginx"
remote "systemctl reload php8.3-fpm && nginx -t && systemctl reload nginx"

# Worker HARUS restart, bukan reload: proses lama memegang kode lama
# di memori dan konteks tenant di koneksinya.
bold "Merestart worker antrean"
remote "systemctl restart rafin-queue"

# ── 10. Verifikasi ─────────────────────────────────────────────────────
bold "Health check"
sleep 3
if remote "curl -fsS -o /dev/null -w '%{http_code}' http://127.0.0.1:\$(grep -oP 'listen\s+\K[0-9]+' /etc/nginx/sites-enabled/rafin | head -1)/up" | grep -q 200; then
    printf '\033[32m   ✔ /up menjawab 200\033[0m\n'
else
    printf '\033[31m   ✘ health check GAGAL — pertimbangkan rollback\033[0m\n'
    remote "tail -20 /var/log/nginx/rafin-error.log" || true
    exit 1
fi

bold "Memverifikasi rantai audit"
remote "cd ${APP_DIR}/current && php artisan rafin:audit:verify" || \
    printf '\033[33m   ! verifikasi audit melaporkan masalah — periksa manual\033[0m\n'

# ── 11. Bersih-bersih ──────────────────────────────────────────────────
bold "Menyisakan ${KEEP_RELEASES} rilis terakhir"
remote "cd ${APP_DIR}/releases && ls -1t | tail -n +$((KEEP_RELEASES+1)) | xargs -r rm -rf"

printf '\n\033[32m✔ Deploy selesai: %s\033[0m\n' "$RELEASE"
remote "ls -1t ${APP_DIR}/releases | head -5 | sed 's/^/   /'"

cat <<EOF

Rollback kalau ada masalah:
    ssh -i ${SSH_KEY} ${SSH_USER}@${SSH_HOST} \\
      'ln -sfn ${APP_DIR}/releases/<RILIS_SEBELUMNYA> ${APP_DIR}/current.new \\
       && mv -Tf ${APP_DIR}/current.new ${APP_DIR}/current \\
       && systemctl reload php8.3-fpm && systemctl restart rafin-queue'
EOF
