#!/usr/bin/env bash
# Memasang satu nilai rahasia ke .env produksi.
#
#   bash deploy/set-secret.sh GOOGLE_CLIENT_SECRET
#
# Nilainya diminta lewat prompt, bukan lewat argumen. Argumen baris perintah
# terlihat di `ps` oleh siapa pun yang sedang masuk ke mesin ini, dan menetap
# di ~/.bash_history sesudahnya. Prompt tidak meninggalkan keduanya.

set -euo pipefail

BERKAS="${BERKAS:-/opt/rafin/deploy/.env}"

tebal() { printf '\033[1m▸ %s\033[0m\n' "$1"; }
mati()  { printf '\033[31m✘ %s\033[0m\n' "$1" >&2; exit 1; }

KUNCI="${1:-}"
[ -n "$KUNCI" ] || mati "Sebutkan kuncinya. Contoh: bash deploy/set-secret.sh GOOGLE_CLIENT_SECRET"
[ -f "$BERKAS" ] || mati "$BERKAS tidak ada. Jalankan deploy/docker-deploy.sh dulu."

grep -qE "^${KUNCI}=" "$BERKAS" || mati "Kunci $KUNCI tidak dikenal di $BERKAS."

# -s: ketikan tidak ditampilkan. -r: garis miring terbalik tidak ditafsirkan.
printf 'Nilai untuk %s (tidak akan tampil): ' "$KUNCI"
read -rs NILAI
printf '\n'
[ -n "$NILAI" ] || mati 'Kosong. Tidak ada yang diubah.'

# Diperiksa sebelum ditulis: rahasia yang tertempel dengan spasi di ujung
# gagal dengan cara yang membingungkan — Google hanya menjawab
# "invalid_client" tanpa menyebut kenapa.
case "$NILAI" in
    *' '*|*"$(printf '\t')"*) mati 'Ada spasi di dalamnya. Salin ulang tanpa spasi.' ;;
esac

case "$KUNCI" in
    GOOGLE_CLIENT_SECRET)
        case "$NILAI" in
            GOCSPX-*) ;;
            *) printf '\033[33m  ! Biasanya diawali GOCSPX-. Lanjut saja kalau Anda yakin.\033[0m\n' ;;
        esac ;;
    TELEGRAM_BOT_TOKEN)
        case "$NILAI" in
            *:*) ;;
            *) printf '\033[33m  ! Token bot biasanya berbentuk angka:huruf. Lanjut saja kalau Anda yakin.\033[0m\n' ;;
        esac ;;
esac

cp -p "$BERKAS" "${BERKAS}.bak"

# Ditulis lewat awk, bukan sed: nilai rahasia bisa memuat & atau | yang
# punya arti khusus di pola pengganti sed dan diam-diam merusak isinya.
NILAI="$NILAI" awk -v k="$KUNCI" '
    index($0, k "=") == 1 { print k "=" ENVIRON["NILAI"]; next }
    { print }
' "$BERKAS" > "${BERKAS}.tmp"

mv "${BERKAS}.tmp" "$BERKAS"
chmod 600 "$BERKAS"

tebal "$KUNCI terpasang (${#NILAI} karakter). Cadangan: ${BERKAS}.bak"
printf 'Terapkan dengan:\n  cd /opt/rafin/deploy && docker compose up -d --force-recreate web queue scheduler\n'
