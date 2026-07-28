#!/usr/bin/env bash
# Deploy Rafin ke server produksi, dijalankan DI SERVER.
#
#   bash /opt/rafin/deploy/docker-deploy.sh
#
# Aman dijalankan berulang. Rahasia dibangkitkan sekali lalu dipakai terus:
# APP_KEY yang diganti akan membuat setiap sesi dan setiap nilai terenkripsi
# yang sudah tersimpan tidak bisa dibaca lagi, jadi skrip ini tidak pernah
# menimpa .env yang sudah ada.

set -euo pipefail

AKAR="${AKAR:-/opt/rafin}"
BERKAS_ENV="$AKAR/deploy/.env"
COMPOSE="docker compose --project-directory $AKAR/deploy -f $AKAR/deploy/docker-compose.yml"

# Satu-satunya port host yang dipakai Rafin.
PORT_APP=3400
PORT_VHOST=8095

tebal() { printf '\033[1m▸ %s\033[0m\n' "$1"; }
oke()   { printf '\033[32m  ✓ %s\033[0m\n' "$1"; }
warn()  { printf '\033[33m  ! %s\033[0m\n' "$1"; }
mati()  { printf '\033[31m✘ %s\033[0m\n' "$1" >&2; exit 1; }

# ── 0. Prasyarat ───────────────────────────────────────────────────────
tebal 'Memeriksa prasyarat'
command -v docker >/dev/null || mati 'docker tidak terpasang.'
docker compose version >/dev/null 2>&1 || mati 'docker compose v2 tidak tersedia.'
[ -f "$AKAR/deploy/docker-compose.yml" ] || mati "Kode tidak ada di $AKAR."
oke "docker $(docker --version | awk '{print $3}' | tr -d ,) · compose $(docker compose version --short)"

# ── 1. Bentrok port ────────────────────────────────────────────────────
#
# Dijalankan sebelum apa pun dinyalakan. Kalau port yang dituju sudah
# dipakai orang lain, compose akan gagal di tengah dan meninggalkan
# sebagian kontainer hidup — lebih baik berhenti sekarang.
tebal 'Memeriksa bentrok port'

# Skrip ini dijalankan berulang di atas susunan yang sudah hidup, jadi "port
# terpakai" saja bukan bentrokan. Yang menentukan adalah SIAPA yang memakainya.
#
#   3400  seharusnya kontainer rafin-web
#   8095  seharusnya nginx, yang memang menaruh vhost Rafin di sana
#
# Apa pun selain itu berarti proses lain menempati port yang dituju, dan
# compose akan gagal separuh jalan sambil meninggalkan sebagian kontainer
# hidup. Lebih baik berhenti di sini.
periksa_port() {
    local port="$1" pemilik_sah="$2"
    local baris
    baris="$(ss -tlnp 2>/dev/null | grep -E "[:.]${port}\b" || true)"

    if [ -z "$baris" ]; then
        oke "$port bebas"
        return
    fi

    if printf '%s' "$baris" | grep -q "$pemilik_sah"; then
        oke "$port dipakai ${pemilik_sah} — memang seharusnya"
        return
    fi

    printf '%s\n' "$baris" >&2
    mati "Port $port dipakai proses lain (diharapkan ${pemilik_sah}). Ubah portnya di docker-compose.yml dan vhost nginx."
}

periksa_port "$PORT_APP"   'docker-proxy'
periksa_port "$PORT_VHOST" 'nginx'

# PostgreSQL dan Redis sengaja tidak dipublikasikan. Diperiksa supaya
# perubahan di compose tidak diam-diam membuka keduanya ke LAN.
if grep -qE '^\s*-\s*"?[0-9.]*:?(5432|6379):' "$AKAR/deploy/docker-compose.yml"; then
    mati 'docker-compose.yml mempublikasikan port database. Docker melewati ufw — itu akan membuka database ke seluruh LAN.'
fi
oke 'postgres dan redis tidak dipublikasikan'

# ── 2. Rahasia ─────────────────────────────────────────────────────────
tebal 'Menyiapkan .env'
if [ -f "$BERKAS_ENV" ]; then
    oke '.env sudah ada, dipakai apa adanya (APP_KEY tidak pernah diganti)'
else
    # Heksadesimal, bukan base64: nilai env_file dibaca apa adanya oleh
    # compose, dan karakter seperti $ atau " di dalamnya menimbulkan
    # kegagalan yang sulit dilacak.
    acak() { openssl rand -hex 24; }

    DOMAIN="${DOMAIN:-rafinance.raf.my.id}"

    umask 077
    cat > "$BERKAS_ENV" <<EOF
# Dibangkitkan $(date -Is) oleh docker-deploy.sh
# JANGAN commit berkas ini. Jangan salin isinya ke chat atau tiket.

APP_NAME=Rafin
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://${DOMAIN}

APP_TIMEZONE=UTC
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID

RAFIN_DEFAULT_TIMEZONE=Asia/Jakarta
RAFIN_DEFAULT_CURRENCY=IDR

APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=rafin
DB_USERNAME=rafin_app
DB_PASSWORD=$(acak)
DB_MIGRATE_USERNAME=rafin_owner
DB_MIGRATE_PASSWORD=$(acak)
DB_SUPER_USERNAME=rafin_super
DB_SUPER_PASSWORD=$(acak)
DB_TEST_DATABASE=

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_PATH=/
SESSION_DOMAIN=${DOMAIN}
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

QUEUE_CONNECTION=redis
CACHE_STORE=redis
BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=$(acak)
REDIS_DB=0
REDIS_CACHE_DB=1

# Rantai proksi: kontainer nginx, nginx host, cloudflared. Semuanya
# beralamat privat. Tanpa ini setiap pengguna tercatat beralamat sama.
TRUSTED_PROXIES=127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16

# Belum ada SMTP. Surel ditulis ke log sampai ada yang dikonfigurasi —
# lebih baik daripada gagal diam-diam saat seseorang lupa kata sandinya.
MAIL_MAILER=log
MAIL_FROM_ADDRESS=halo@${DOMAIN}
MAIL_FROM_NAME=Rafin

# Diisi langsung di server. Jangan pernah lewat chat atau tiket.
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=rafinanceid_bot
TELEGRAM_WEBHOOK_SECRET=$(openssl rand -hex 32)

# Aturan A12: tidak ada LLM di jalur input utama.
RAFIN_FEATURE_LLM_PARSER=false
RAFIN_FEATURE_OCR=false
EOF
    chmod 600 "$BERKAS_ENV"
    oke ".env dibangkitkan dengan rahasia acak (mode 600)"
fi

# ── 3. Build ───────────────────────────────────────────────────────────
tebal 'Membangun image'
$COMPOSE build --pull
oke 'image siap'

# ── 4. Jalankan ────────────────────────────────────────────────────────
tebal 'Menyalakan layanan'
$COMPOSE up -d --remove-orphans
oke 'kontainer berjalan'

# ── 5. Tunggu sehat ────────────────────────────────────────────────────
tebal 'Menunggu aplikasi sehat'
for i in $(seq 1 60); do
    status="$(docker inspect -f '{{.State.Health.Status}}' rafin-web 2>/dev/null || echo starting)"
    [ "$status" = "healthy" ] && break
    if [ "$status" = "unhealthy" ]; then
        $COMPOSE logs --tail=60 web >&2
        mati 'Kontainer web tidak sehat.'
    fi
    sleep 3
done
[ "${status:-}" = "healthy" ] || { $COMPOSE logs --tail=60 web >&2; mati 'Aplikasi tidak sehat setelah 3 menit.'; }
oke 'web sehat'

# ── 6. Bukti, bukan asumsi ─────────────────────────────────────────────
tebal 'Memeriksa hasil'

kode="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT_APP}/up" || echo 000)"
[ "$kode" = "200" ] || mati "/up menjawab $kode, bukan 200."
oke "/up menjawab 200"

# Halaman yang dirender server tetap terlihat utuh meskipun seluruh
# JavaScript-nya tidak pernah dimuat: Livewire menaruh HTML awalnya di dalam
# dokumen, lalu diam. Rilis pertama lolos setiap pemeriksaan di berkas ini
# sambil tidak bisa dipakai sama sekali — tidak ada satu pun tombol yang
# menjawab, dan nol workspace pernah berhasil dibuat.
#
# 200 di /up hanya membuktikan PHP hidup. Yang dibuktikan di sini: halaman
# masuk merujuk sebuah bundel, dan bundel itu benar-benar memuat mesin
# Livewire — bukan hanya config-nya.
tebal 'Memeriksa mesin Livewire ikut terbundel'
berkas="$(curl -s "http://127.0.0.1:${PORT_APP}/login" | grep -oE 'app-[A-Za-z0-9_-]+\.js' | head -1)"
[ -n "$berkas" ] || mati 'Halaman masuk tidak merujuk bundel app.js sama sekali.'

curl -s "http://127.0.0.1:${PORT_APP}/build/assets/${berkas}" | grep -q 'wire:snapshot' \
    || mati "Bundel ${berkas} tidak memuat mesin Livewire — komponen akan dirender lalu diam. Periksa import dan Livewire.start() di resources/js/app.js."
oke "bundel ${berkas} memuat mesin Livewire"

# Aturan A4 tidak boleh hanya diasumsikan berlaku. Kalau role aplikasi
# ternyata bisa melewati RLS, setiap uji isolasi jadi tidak berarti — ia
# akan tetap lulus, hanya tidak lagi menguji apa pun.
#
# Ditanyakan lewat PHP dengan kredensial aplikasi yang sungguhan, bukan
# lewat psql sebagai superuser: yang ingin dibuktikan adalah sifat role
# yang benar-benar dipakai Rafin saat melayani permintaan.
a4="$(docker exec rafin-web php -r '
    $p = new PDO(
        sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE")),
        getenv("DB_USERNAME"),
        getenv("DB_PASSWORD"),
    );
    // Tanpa literal teks sama sekali: kode ini hidup di dalam kutip tunggal
    // shell, dan PostgreSQL memakai kutip tunggal untuk string sementara
    // kutip ganda menandai nama kolom. Boolean yang dicor ke int menghindari
    // keduanya. 0 berarti aman.
    echo $p->query(
        "SELECT (rolsuper OR rolbypassrls)::int FROM pg_roles WHERE rolname = current_user"
    )->fetchColumn();
' 2>&1 || true)"
[ "$a4" = "0" ] || mati "Role aplikasi bisa melewati RLS, atau pemeriksaannya sendiri gagal. Jawaban: ${a4:-kosong}"
oke 'role aplikasi tanpa BYPASSRLS dan tanpa SUPERUSER (A4)'

jml="$(docker exec rafin-web php artisan migrate:status --database=pgsql_migrate 2>/dev/null | grep -c 'Ran' || echo 0)"
oke "$jml migration terpasang"

tebal 'Ringkasan port'
docker ps --filter 'name=rafin' --format '  {{.Names}}  {{.Status}}  {{.Ports}}'

printf '\n\033[1mSelesai.\033[0m Aplikasi ada di http://127.0.0.1:%s (loopback saja).\n' "$PORT_APP"
printf 'Langkah berikutnya: pasang vhost nginx, lalu arahkan ingress cloudflared ke http://localhost:%s\n' "$PORT_VHOST"
