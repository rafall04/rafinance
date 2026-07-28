# Rafin

Aplikasi manajemen keuangan untuk pasar Indonesia — dipakai untuk keuangan pribadi maupun usaha kecil.
Dua kanal: bot Telegram [@rafinanceid_bot](https://t.me/rafinanceid_bot) dan aplikasi web yang juga berjalan sebagai PWA.

**Prinsip produk utama:** capture dulu, klasifikasi belakangan. Sistem menerima input seadanya,
menyimpannya sebagai draft, dan membiarkan pengguna melengkapinya nanti dari kanal mana pun.

```
339 test · 2.386 assertion · Pint bersih · migrate:fresh bersih dari nol
14 halaman lolos WCAG AA, mode terang dan gelap, terukur di 360px
```

| Fase | Isi | Status |
|---|---|---|
| 0 | Tenancy, RLS, Money, auth, fondasi desain | ✅ |
| 1 | Ledger inti, double-entry, saldo rail, audit log berantai hash | ✅ |
| 2 | Telegram: webhook, parser rule-based, inbox | ✅ |
| 3 | PWA: service worker, antrean offline, app lock | ✅ |
| 4 | Laporan, anggaran, proyek, ekspor CSV | ✅ |
| 5 | Log aktivitas, perangkat, 2FA, akses dukungan | ✅ |
| 6 | Billing, kuota, panel admin Filament | ✅ |
| 7 | Tagihan, berulang, cash opname, impor, parser bank | ✅ |

---

## Stack

```
Laravel 13.21 · PHP 8.3
PostgreSQL 16.10       — Row Level Security aktif, port 5433
Livewire 4             — UI aplikasi pengguna (/app)
Filament 5             — panel admin platform (/admin) SAJA
Fortify                — backend autentikasi, tanpa satu pun tampilan bawaannya
Tailwind CSS 4 + Vite  — token warna CSS-first, PWA lewat vite-plugin-pwa
Pest 4                 — Unit · Feature · Arch · Security
```

Versi yang berbeda dari dokumen rancangan awal, beserta alasannya:

- **Laravel 13, bukan 11.** Dukungan keamanan Laravel 11 berakhir Maret 2026.
- **Filament 5, bukan 3.** Mengikuti Laravel 13.
- **Livewire 4, bukan 3.** Konsekuensi langsung: `filament/support` v5 mensyaratkan `livewire/livewire: ^4.1`.
  Kalau Livewire 3 wajib, jalannya adalah turun ke Filament 4.
- **Horizon belum dipasang.** Horizon menuntut `ext-pcntl` yang tidak ada di Windows. Dev memakai
  driver `database`; konfigurasi Redis sudah ditulis untuk produksi Linux.

---

## Menjalankan

PostgreSQL 16 terpasang sebagai binary portabel di `C:\pgsql16` (bukan service Windows), berdampingan
dengan PostgreSQL 9.4 yang sudah ada di port 5432.

```bash
pwsh scripts/pg-start.ps1
```

```bash
php artisan serve
```

```bash
npm run dev
```

Buka <http://localhost:8000>. Menghentikan database: `pwsh scripts/pg-stop.ps1`.

### Migration

Koneksi bawaan memakai role `rafin_app` yang **sengaja tidak punya hak DDL**. `php artisan migrate`
polos ditolak dengan pesan yang menjelaskan alasannya.

```bash
php artisan rafin:migrate --fresh --seed
```

### Test

```bash
vendor/bin/pest
```

Berjalan di atas PostgreSQL sungguhan (`rafin_test`), bukan SQLite in-memory — separuh aturan mutlak
Rafin hidup di dalam database, dan test di atas SQLite akan hijau tanpa menguji satu pun dari aturan itu.

### Perintah lain

```bash
php artisan rafin:audit:verify
```

```bash
php artisan rafin:partitions --months=6 --prune
```

```bash
php artisan rafin:berulang
```

```bash
php artisan rafin:telegram:webhook --url=https://contoh.test/webhooks/telegram
```

---

## Aturan mutlak dan cara penegakannya

| # | Aturan | Ditegakkan oleh |
|---|---|---|
| A1 | Uang sebagai BIGINT minor unit | `Money` tanpa jalur float, arch test `strict_types`, pemindai migration |
| A2 | Jumlah entries wajib nol | Constraint trigger PostgreSQL, deferred |
| A3 | Transaksi posted tak bisa diubah | Trigger `BEFORE UPDATE OR DELETE`, hanya izinkan posted→void |
| A4 | Query ter-scope workspace | Global scope Eloquent **dan** RLS dengan `FORCE ROW LEVEL SECURITY` |
| A5 | Admin platform tanpa akses nominal | Arch test pemindai kelas **dan** nama tabel di `app/Filament` |
| A6 | `security_events` tanpa nominal | Penjaga saat penulisan, pemindai isi tabel, pemindai nama kolom |
| A7 | ULID di mana-mana | Arch test menolak `$table->id()` |
| A8 | 404, bukan 403 | Global scope → `ModelNotFoundException` |
| A9 | Idempotency | `update_id` sebagai PK, ULID client sebagai `Idempotency-Key` |
| A10 | `booked_date` terpisah dari `created_at` | Kolom DATE, seluruh laporan memakainya |
| A11 | Attachment di disk privat | Disk `lampiran` tanpa URL, `serve => false` |
| A12 | Tanpa LLM di jalur input | Arch test memindai nama penyedia, feature flag mati |

### Masuk lewat Google

Dua cara masuk, berdampingan: **Google**, atau **surel dan kata sandi** seperti biasa. Keduanya
mengarah ke akun yang sama.

Isi `GOOGLE_CLIENT_ID` dan `GOOGLE_CLIENT_SECRET` di `.env`, dan tombolnya muncul sendiri di halaman
masuk dan daftar. Kosongkan salah satunya, tombolnya hilang — tanpa mengubah kode. Redirect URI yang
didaftarkan di Google Cloud Console: `{APP_URL}/auth/google/callback`.

Apple dan Facebook sudah didukung penuh tapi kredensialnya sengaja dibiarkan kosong. Mengaktifkannya
nanti cukup mengisi `.env`. Catatan untuk Apple: `client_secret`-nya berupa JWT yang kedaluwarsa tiap
enam bulan, jadi perlu perintah yang membuatnya ulang dari kunci `.p8` sebelum dipakai sungguhan.

**Aturan yang menentukan seluruh desainnya:** akun pihak ketiga hanya disambungkan otomatis ke akun
Rafin yang sudah ada kalau penyedianya **menjamin surelnya terverifikasi**.

| Penyedia | Menjamin surel? | Akibatnya |
|---|---|---|
| Google | ya | masuk langsung tersambung ke akun dengan surel yang sama |
| Apple | ya | sama |
| Facebook | **tidak** | ditolak — pemiliknya harus masuk dengan kata sandi dulu, lalu menyambungkan sendiri |

Tanpa aturan itu, siapa pun yang bisa mendaftar di penyedia dengan surel orang lain bisa membuka buku
kas orang itu. Bagi sebagian pengguna Rafin, isi buku itu adalah seluruh pembukuan usahanya.

Hal lain yang ikut dijaga: ID penyedia yang mengikat identitas (bukan surel, jadi mengganti surel di
Google tidak menghilangkan pembukuan), penolakan memutuskan penyedia yang jadi satu-satunya cara
masuk, dan `getAuthPassword()` yang mengembalikan string kosong supaya akun tanpa kata sandi ditolak
dengan rapi alih-alih meledak di pemeriksa hash.

### Isolasi tenant, dua lapis

| Role | Dipakai | Sifat |
|---|---|---|
| `rafin_app` | seluruh lalu lintas aplikasi | bukan pemilik tabel, tanpa `BYPASSRLS`, tanpa hak DDL |
| `rafin_owner` | hanya migration | pemilik skema, `BYPASSRLS` |

Test isolasi sengaja memakai SQL mentah yang melewati Eloquent sepenuhnya. Selama global scope
bekerja, RLS tidak pernah tersentuh — dan kita tidak pernah tahu apakah jaring pengamannya terpasang.

Tanpa konteks tenant, keduanya **gagal tertutup**: nol baris, bukan seluruh tabel.

**Penyimpangan yang dicatat:** dokumen menyebut `SET LOCAL app.workspace_id`, yang hanya hidup di
dalam transaksi. Yang dipakai adalah `set_config(..., false)` se-sesi koneksi, dengan kewajiban
membersihkannya di `terminate()` middleware dan di setiap batas job antrean.

---

## Anggaran performa

| Ukuran | Anggaran | Hasil |
|---|---|---|
| JavaScript awal (gzip) | ≤ 200 KB | **111,3 KB** |
| CSS (gzip) | — | **9,8 KB** |
| Service worker (gzip) | — | 8,1 KB, dimuat terpisah |
| Font (total) | — | 56 KB, self-host, subset latin |

Angka JavaScript itu sudah termasuk Livewire dan Alpine, yang memang dibundel ke dalam `app.js`
alih-alih diunduh sebagai berkas kedua. Angka 19,8 KB yang tercatat di sini sebelumnya bukan hasil
pengoptimalan: Livewire memang tidak ikut sama sekali, dan seluruh komponen di `/app` dirender lalu
diam. Anggaran yang dipenuhi dengan menghilangkan mesinnya bukan anggaran yang dipenuhi.

Terverifikasi di viewport 360px: nol kontrol di bawah 44px, tanpa gulir horizontal, mode gelap dan
mode privasi bekerja, `:focus-visible` dan `prefers-reduced-motion` ada di stylesheet terkompilasi.

### Service worker dan antrean offline

Service worker dilayani dari `/build/sw.js`, bukan dari akar. Daftar precache-nya memakai URL
relatif, yang hanya benar dari dalam `/build/` — jadi memindahkannya ke akar justru mematikan
precache. Yang membuatnya tetap boleh mengendalikan seluruh situs adalah header
`Service-Worker-Allowed: /`, dipasang di nginx kontainer dan diteruskan vhost host.

Tanpa header itu peramban menolak pendaftaran dengan `SecurityError`, dan sampai Juli 2026 itulah
yang terjadi di produksi: nol service worker terdaftar, tanpa satu pun pesan galat karena
kegagalannya ditelan `catch` kosong.

Karena kegagalan seperti itu bisa datang dari mana saja — mode privat, kebijakan perangkat, satu
header yang salah — pengiriman antrean **tidak lagi bergantung pada service worker**. Aturannya ada
di `resources/js/kirim-antrean.js` dan dipakai bersama: service worker memakainya kalau ada, halaman
memakainya kalau tidak. Transaksi yang sudah dibaca pengguna sebagai "tersimpan" tidak boleh
bergantung pada satu mekanisme yang bisa diam-diam tidak terpasang.

LCP di perangkat kelas Moto G Power belum diukur — butuh perangkat sungguhan, bukan emulasi.

### Audit aksesibilitas

Kontras diukur pada halaman yang benar-benar dirender, bukan diperkirakan dari nilai token. Warna
`oklab()` yang dikeluarkan Tailwind v4 dirasterisasi lewat canvas dulu supaya nilai sRGB-nya nyata.

Tiga hal yang ditemukan dan diperbaiki sebelum uji coba pengguna:

| Temuan | Sebelum | Sesudah |
|---|---|---|
| Hijau di atas kartu — dipakai 10 pesan berhasil | 4,41:1 | **5,08 / 4,65** (`#2c7959`) |
| Kuning sebagai teks — lencana antrean offline dan peringatan kata sandi | 2,13:1 | **`--kuning-teks` 5,57 / 5,09** |
| Label bilah navigasi | 11px | **12px** |

Kuning uang kertas Rp1.000 memang terang; ia tetap dipakai untuk bidang berwarna, dan
`--kuning-teks` yang dipakai untuk tulisan di atasnya. Dua token untuk satu warna adalah pola biasa:
satu untuk bidangnya, satu untuk apa yang ditulis di atasnya.

Nilainya dikunci oleh [KontrasWarnaTest](tests/Feature/KontrasWarnaTest.php), yang membaca token
langsung dari `app.css` dan menghitung ulang rasionya — termasuk penjaga yang menolak `text-kuning`
muncul di Blade mana pun.

Ditambahkan juga: `touch-action: manipulation` (menghapus jeda ketuk 300ms) dan
`overscroll-behavior-y: contain` — tarik-untuk-muat-ulang yang tidak disengaja di tengah pengisian
formulir berarti kehilangan nominal yang belum tersimpan.

**Layar mendatar rapat tapi berfungsi.** Papan angka berubah jadi enam kolom (305px → 94px), panel
nominal menempel di atas supaya angka yang diketik selalu terlihat, dan keterangan tambahan
disembunyikan. Susunan dua kolom yang benar-benar memanfaatkan lebar mendatar ditunda bersama
pekerjaan dua kolom ≥1024px.

---

## Yang membentuk keputusan desainnya

**Buku besar adalah halaman utama, bukan dasbor.** Yang orang butuhkan sepuluh kali sehari adalah
"berapa saldo saya dan apa yang barusan saya catat". Kolom saldo berjalan di sisi kanan — IBM Plex
Mono, tabular, rata kanan — membuat daftar transaksi terbaca sebagai pembukuan, bukan sebagai
linimasa.

**Saldo awal adalah transaksi sungguhan.** Kolom `opening_balance_minor` saja akan membuat harta
tanpa asal, dan neraca meleset sejak hari pertama. Saldo awal mendebit akunnya dan mengkredit modal,
seperti pembukuan mana pun.

**Koreksi lewat pembalikan, tidak pernah lewat penghapusan.** Buku kas yang bisa dihapus adalah buku
kas yang tidak bisa dipercaya.

**Parser tanpa LLM, dan itu keputusan produk.** Jalur input utama harus punya waktu tanggap yang bisa
diprediksi: orang mengetik di Telegram sambil berdiri di SPBU. Peta kata kunci memuat merek dan slang
— "gojek", "indomaret", "pertamax" — karena begitulah orang sungguhan mengetik.

**Parser tidak pernah menolak.** Yang tidak terbaca jadi item inbox, bukan pesan galat. Orang yang
ditolak saat mencatat akan berhenti mencatat.

**Kuota membatasi menambah, tidak pernah membaca.** Buku seseorang tidak pernah disandera.

---

## Struktur

```
app/
  Channels/Telegram/     webhook, router perintah, parser notifikasi bank
  Domain/
    Billing/             plan, langganan, kuota, statistik platform
    Budgeting/           anggaran, target, aturan berulang
    Capture/             inbox, parser rule-based, impor CSV
    Contacts/            kontak, tagihan, piutang
    Ledger/              akun, transaksi, entries, laporan
    Logging/             audit berantai hash, jejak keamanan
    Projects/            laba rugi per pekerjaan
    Reconciliation/      penguncian periode, cash opname
    Tenancy/             workspace, keanggotaan, konteks tenant
  Filament/Admin/        panel admin — HANYA metadata
  Livewire/App/          UI pengguna
  Support/               Money, RLS, partisi
```

---

## Yang sengaja belum dikerjakan

- **Integrasi payment gateway.** Antarmukanya sudah ada (`PaymentGateway`); semua plan Rp 0 selama beta.
- **OCR dan parser LLM.** Dimatikan feature flag sesuai aturan A12.
- **Passkey.** Tabel bawaan Fortify memakai auto-increment; menunggu ditulis ulang dengan ULID (A7).
- **Web Push.** Notifikasi utama lewat Telegram — Web Push tidak ada di Safari sebelum iOS 16.4 dan
  hanya jalan setelah dipasang ke layar utama.
- **Horizon.** Menunggu deploy Linux.
