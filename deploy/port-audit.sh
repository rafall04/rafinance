#!/usr/bin/env bash
# Audit port pada server produksi sebelum deploy Rafin.
#
# READ-ONLY: tidak mengubah apa pun. Aman dijalankan kapan saja.
# Tujuan: menemukan port kosong agar Rafin tidak bentrok dengan project lain
# yang sudah berjalan di mesin yang sama.
#
#   bash port-audit.sh

set -uo pipefail

bold() { printf '\033[1m%s\033[0m\n' "$1"; }
hr()   { printf '%.0s─' $(seq 1 72); echo; }

bold "══ 1. Port yang SEDANG DIPAKAI (listening) ══"
hr
if command -v ss >/dev/null 2>&1; then
    ss -tlnp 2>/dev/null | awk 'NR==1 || /LISTEN/'
elif command -v netstat >/dev/null 2>&1; then
    netstat -tlnp 2>/dev/null
else
    echo "!! ss dan netstat tidak ada. Pasang: apt install iproute2"
fi
echo

bold "══ 2. Ringkasan: port → proses ══"
hr
if command -v ss >/dev/null 2>&1; then
    ss -tlnpH 2>/dev/null \
      | awk '{
            split($4, a, ":");
            port = a[length(a)];
            proc = "?";
            if (match($0, /users:\(\("[^"]+/)) {
                proc = substr($0, RSTART+9, RLENGTH-9);
            }
            printf "%-8s %-20s %s\n", port, proc, $4;
        }' \
      | sort -n -u
fi
echo

bold "══ 3. Service yang berjalan (kandidat pemilik port) ══"
hr
if command -v systemctl >/dev/null 2>&1; then
    systemctl list-units --type=service --state=running --no-pager --no-legend 2>/dev/null \
      | awk '{print "  " $1}' | head -40
fi
echo

bold "══ 4. Container Docker (sering jadi sumber bentrok tersembunyi) ══"
hr
if command -v docker >/dev/null 2>&1; then
    docker ps --format 'table {{.Names}}\t{{.Ports}}\t{{.Image}}' 2>/dev/null \
      || echo "  (docker ada tapi daemon tidak bisa diakses)"
else
    echo "  (docker tidak terpasang)"
fi
echo

bold "══ 5. Vhost nginx yang sudah ada ══"
hr
if [ -d /etc/nginx/sites-enabled ]; then
    for f in /etc/nginx/sites-enabled/*; do
        [ -e "$f" ] || continue
        echo "  ── $(basename "$f")"
        grep -HnE '^\s*(listen|server_name|root)' "$f" 2>/dev/null | sed 's/^/     /'
    done
elif [ -d /etc/nginx/conf.d ]; then
    grep -rHnE '^\s*(listen|server_name|root)' /etc/nginx/conf.d/ 2>/dev/null | sed 's/^/  /'
else
    echo "  (nginx tidak terpasang / bukan layout Debian)"
fi
echo

bold "══ 6. Pool PHP-FPM yang sudah ada ══"
hr
for d in /etc/php/*/fpm/pool.d; do
    [ -d "$d" ] || continue
    for f in "$d"/*.conf; do
        [ -e "$f" ] || continue
        echo "  ── $f"
        grep -HnE '^\s*(\[|listen\s*=|user\s*=)' "$f" 2>/dev/null | sed 's/^/     /'
    done
done
echo

bold "══ 7. Instance PostgreSQL & Redis ══"
hr
if command -v pg_lsclusters >/dev/null 2>&1; then
    pg_lsclusters 2>/dev/null
else
    echo "  postgres: $(command -v psql >/dev/null 2>&1 && echo 'psql ada' || echo 'tidak terdeteksi')"
fi
echo "  redis  : $(ss -tlnH 2>/dev/null | grep -c ':6379') listener di 6379"
echo

bold "══ 8. PORT KOSONG dari kandidat Rafin ══"
hr
# Kandidat sengaja dijauhkan dari default yang lazim dipakai project lain:
#   8000/8080/3000 (dev server), 5432 (postgres bawaan), 6379 (redis bawaan).
CANDIDATES=(8090 8091 8092 8093 9000 9001 5434 5435 6380 6381 9090 9091)

port_free() {
    local p=$1
    if ss -tlnH 2>/dev/null | awk '{split($4,a,":"); print a[length(a)]}' | grep -qx "$p"; then
        return 1
    fi
    return 0
}

FREE=()
for p in "${CANDIDATES[@]}"; do
    if port_free "$p"; then
        printf '  \033[32m✔ %-6s BEBAS\033[0m\n' "$p"
        FREE+=("$p")
    else
        owner=$(ss -tlnpH 2>/dev/null | grep -E "[:.]$p\b" | grep -oE 'users:\(\("[^"]+' | head -1 | sed 's/users:((\"//')
        printf '  \033[31m✘ %-6s DIPAKAI\033[0m  %s\n' "$p" "${owner:-?}"
    fi
done
echo

bold "══ 9. USULAN ALOKASI PORT RAFIN ══"
hr
# Catatan: pick() TIDAK boleh dipanggil lewat $( ) — command substitution
# menjalankannya di subshell, sehingga PICKED tidak pernah ikut terbarui di
# shell induk dan ketiga layanan akan mendapat port yang sama persis.
# Karena itu hasilnya ditaruh di variabel global REPLY_PORT.
PICKED=""
pick() {
    # $1 = prefiks rentang yang disukai (mis. "80" untuk 8090-an, "54" untuk
    #      5434-an). Kalau tidak ada yang cocok, ambil port bebas mana pun.
    local want="${1:-}" p
    for p in "${FREE[@]:-}"; do
        [ -n "$p" ] || continue
        case " $PICKED " in *" $p "*) continue ;; esac
        [ -n "$want" ] && case "$p" in "$want"*) ;; *) continue ;; esac
        PICKED="$PICKED $p"; REPLY_PORT="$p"; return 0
    done
    # Tidak ada di rentang yang disukai — jatuh ke port bebas mana pun.
    if [ -n "$want" ]; then pick ""; return $?; fi
    REPLY_PORT="-"; return 1
}

pick "80"; HTTP="$REPLY_PORT"
pick "54"; PG="$REPLY_PORT"
pick "63"; RD="$REPLY_PORT"

cat <<EOF
  nginx (HTTP)     : ${HTTP}     → APP_URL / reverse proxy
  PostgreSQL       : ${PG}     → DB_PORT   (instance terpisah, RLS aktif)
  Redis            : ${RD}     → REDIS_PORT (queue + cache + session)
  PHP-FPM          : unix socket /run/php/php8.3-fpm-rafin.sock  (TANPA port TCP)

  Catatan:
  · PHP-FPM sengaja pakai unix socket, bukan port TCP — nol kemungkinan bentrok
    dan lebih cepat daripada loopback TCP.
  · Kalau PostgreSQL/Redis yang sudah ada mau dipakai bersama, port di atas
    tidak perlu; cukup buat DATABASE + role terpisah. Lihat catatan di bawah.
EOF
echo

bold "══ 10. Kalau memakai PostgreSQL/Redis yang SUDAH ADA ══"
hr
cat <<'EOF'
  Rafin butuh DUA role terpisah (aturan A4 — RLS harus benar-benar berlaku):
     rafin_owner  — pemilik skema, hanya untuk migration
     rafin_app    — dipakai aplikasi, TANPA BYPASSRLS, TANPA hak DDL
  Ini bisa hidup di instance PostgreSQL yang sudah ada tanpa mengganggu
  database project lain, asal versinya >= 14 (butuh FORCE ROW LEVEL SECURITY).

  Versi terdeteksi:
EOF
psql --version 2>/dev/null | sed 's/^/     /' || echo "     (psql tidak ada di PATH)"
echo
echo "  Redis: pakai SELECT database berbeda (REDIS_DB) atau prefix key."
echo "  Rafin sudah memakai prefix cache dari APP_NAME, jadi aman berbagi."
echo
hr
bold "Audit selesai. Tidak ada yang diubah."
