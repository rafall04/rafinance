{{--
    Halaman depan Rafin.

    Susunannya mengikuti dua pola: hero dengan perangkat yang memperlihatkan
    produknya, lalu kisi bento untuk manfaat. Yang membuat halaman terasa
    digarap bukan hiasan, melainkan bahwa ada sesuatu untuk DILIHAT — orang
    menilai aplikasi keuangan dari tampilannya sebelum membaca satu kalimat
    pun tentangnya.

    Perangkatnya digambar dengan CSS, bukan tangkapan layar. Ia jadi ikut
    berganti ke mode gelap sendiri, tetap tajam di kerapatan layar berapa pun,
    dan berbobot sekitar dua kilobyte alih-alih seratus.

    Tetap tanpa testimoni dan tanpa jumlah pengguna. Rafin belum punya
    keduanya, dan mengarangnya untuk aplikasi keuangan adalah cara tercepat
    kehilangan hal yang justru sedang dibangun.
--}}

<x-layouts.publik>

    {{-- ── Hero ────────────────────────────────────────────────────────── --}}
    <section class="aurora pt-6 pb-14 md:pt-10">
        <div class="items-center gap-10 md:flex">

            <div class="prosa min-w-0 flex-1">
                <p class="pita">
                    <span class="titik-hidup" aria-hidden="true"></span>
                    Beta terbuka · semua paket Rp 0
                </p>

                <h1 class="hero-judul mt-5">
                    Catat dulu.<br><em>Rapikan nanti.</em>
                </h1>

                <p class="text-ink-soft mt-5 text-[17px] leading-7">
                    Buku kas untuk pribadi dan usaha kecil. Tulis pengeluaran apa
                    adanya dalam dua detik — kategori, catatan, dan struknya bisa
                    menyusul kapan saja Anda sempat.
                </p>

                <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="tombol-utama w-full sm:w-auto">
                            Mulai gratis
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                                <path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>
                            </svg>
                        </a>
                    @endif

                    <a href="{{ route('login') }}" class="tombol-halus tap w-full justify-center sm:w-auto">
                        Sudah punya akun
                    </a>
                </div>

                <p class="text-ink-soft mt-4 text-[13px]">
                    Tanpa kartu kredit. Data Anda bisa diunduh kapan saja.
                </p>
            </div>

            {{-- Perangkat. Disembunyikan dari pembaca layar: ia hiasan yang
                 memperlihatkan, dan seluruh isinya sudah dikatakan teks di
                 sebelahnya. Membacakannya hanya menambah kebisingan. --}}
            <div class="mt-12 flex justify-center md:mt-0 md:flex-1" aria-hidden="true">
                <div class="ponsel">
                    <div class="ponsel-poni"></div>

                    <p class="tanda">Saldo semua akun</p>
                    {{-- Bukan .nominal-lg. Kelas itu berbobot 600, dan baris
                         transaksi di bawah memakai 500 — dua bobot berarti dua
                         berkas font, 30 KB untuk maket yang sama. Ukuran saja
                         sudah cukup membedakan saldo dari daftarnya. --}}
                    <p class="nominal nominal-kiri mt-1 text-[25px] leading-8 tracking-tight">
                        <span class="angka">Rp 4.280.000</span>
                    </p>

                    <div class="mt-4 flex gap-2">
                        <span class="pita pita-kecil">
                            <span class="titik-hidup"></span>
                            Masuk 6,2jt
                        </span>
                        <span class="pita pita-merah pita-kecil">
                            Keluar 1,9jt
                        </span>
                    </div>

                    <p class="tanda mt-5">Hari ini</p>

                    <div class="mt-1">
                        <div class="baris-transaksi">
                            <span class="min-w-0 truncate">Kopi</span>
                            <span class="nominal nominal-keluar text-[12px]"><span class="angka">−25.000</span></span>
                        </div>
                        <div class="baris-transaksi">
                            <span class="min-w-0 truncate">Setoran pelanggan</span>
                            <span class="nominal nominal-masuk text-[12px]"><span class="angka">+850.000</span></span>
                        </div>
                        <div class="baris-transaksi">
                            <span class="text-kuning-teks min-w-0 truncate">Bensin · draf</span>
                            <span class="nominal nominal-keluar text-[12px]"><span class="angka">−50.000</span></span>
                        </div>
                        <div class="baris-transaksi">
                            <span class="min-w-0 truncate">Bayar listrik</span>
                            <span class="nominal nominal-keluar text-[12px]"><span class="angka">−312.500</span></span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- ── Manfaat ──────────────────────────────────────────────────────
         Bagian ini menjawab "apa untungnya buat saya", dan itu pertanyaan
         yang berbeda dari "apa bedanya dengan yang lain". Double-entry dan
         Row Level Security adalah jawaban untuk pertanyaan kedua; keduanya
         tidak berarti apa-apa bagi orang yang sedang menimbang apakah
         hidupnya jadi lebih mudah. --}}
    <section class="pt-4 pb-14" aria-labelledby="manfaat">
        <p class="tanda">Untuk apa</p>
        <h2 id="manfaat" class="judul-bagian prosa mt-2">
            Tiga hal yang berubah setelah mencatat rutin
        </h2>

        <ul class="mt-7 grid gap-6 md:grid-cols-3">
            <li>
                <span class="manfaat-ikon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>
                    </svg>
                </span>
                <h3 class="mt-3 text-[17px] font-semibold">Akhir bulan berhenti jadi teka-teki</h3>
                <p class="text-ink-soft mt-1.5">
                    "Ke mana perginya?" terjawab sendiri, tanpa Anda perlu mengingat
                    apa pun atau mengumpulkan nota yang sudah tercecer.
                </p>
            </li>

            <li>
                <span class="manfaat-ikon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M3 7h18"/><path d="M3 7v12a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V7"/>
                        <path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M12 12v4"/>
                    </svg>
                </span>
                <h3 class="mt-3 text-[17px] font-semibold">Tahu usaha Anda untung berapa</h3>
                <p class="text-ink-soft mt-1.5">
                    Uang warung dan uang pribadi berhenti tercampur. Yang selama ini
                    terasa "ramai tapi kok tidak sisa" jadi kelihatan sebabnya.
                </p>
            </li>

            <li>
                <span class="manfaat-ikon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M14 3v5h5"/>
                        <path d="M19 21H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h9l6 6v11a1 1 0 0 1-1 1Z"/>
                        <path d="M8 13h8"/><path d="M8 17h5"/>
                    </svg>
                </span>
                <h3 class="mt-3 text-[17px] font-semibold">Angkanya siap saat ditanya</h3>
                <p class="text-ink-soft mt-1.5">
                    Mengajukan pinjaman, menyetor pajak, atau membagi hasil dengan
                    rekan — laporannya sudah ada, tinggal dibuka.
                </p>
            </li>
        </ul>
    </section>

    {{-- ── Cara kerjanya ────────────────────────────────────────────────
         Diperagakan, bukan dijelaskan. Kalimat "input cepat" ada di setiap
         aplikasi keuangan; yang membedakan adalah melihat sendiri bahwa
         catatan setengah jadi memang diterima, bukan ditolak. --}}
    <section class="py-14" aria-labelledby="cara">
        <p class="tanda">Cara kerjanya</p>
        <h2 id="cara" class="judul-bagian prosa mt-2">
            Yang lain menolak catatan setengah jadi
        </h2>
        <p class="text-ink-soft prosa mt-3">
            Rafin menerimanya, lalu mengingatkan Anda nanti.
        </p>

        <div class="mt-7 grid gap-5 md:grid-cols-2 md:items-start">
            <div class="peraga muncul">
                <p class="tanda">Anda ketik, di web atau di Telegram</p>

                <p class="gelembung mt-2">kopi 25rb</p>

                <div class="peraga-panah">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M12 5v14"/><path d="m6 13 6 6 6-6"/>
                    </svg>
                </div>

                <p class="tanda">Tersimpan, meski belum lengkap</p>

                <div class="bg-paper rule-t mt-2 rounded-[10px] border border-[var(--rule)] px-4 py-3">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="font-medium">Kopi</span>
                        {{-- Public Sans, bukan .nominal. Di sini angkanya berdiri
                             sendiri dan tidak perlu berbaris dengan kolom mana
                             pun, jadi lebar seragam bawaan Public Sans sudah
                             cukup. Maket ponsel di atas memang memakai mono —
                             di sana angka-angkanya harus berbaris. --}}
                        <span class="text-merah font-semibold tabular-nums">Rp 25.000</span>
                    </div>

                    <p class="text-kuning-teks mt-1.5 inline-flex items-center gap-1.5 text-[13px]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 shrink-0" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/><path d="M12 8v4"/><path d="M12 16h.01"/>
                        </svg>
                        Draf — kategori belum diisi
                    </p>
                </div>
            </div>

            <div class="prosa">
                <p class="text-ink-soft">
                    Tidak ada formulir yang harus diselesaikan saat Anda sedang
                    berdiri di kasir. Draf menumpuk di Inbox, dan dirapikan
                    sekaligus saat Anda santai.
                </p>
                <p class="text-ink-soft mt-4">
                    Yang Rafin tebak sendiri: nominal, jenis, tanggal, dan akun
                    yang biasa Anda pakai. Yang selalu Anda putuskan: apakah
                    tebakannya benar.
                </p>
            </div>
        </div>
    </section>

    {{-- ── Tanpa sinyal ─────────────────────────────────────────────────
         Pertanyaan yang paling sering muncul, dijawab di halaman depan alih-alih
         disimpan di dokumentasi: apa harus memasang aplikasi dulu, dan apa
         yang terjadi pada catatan yang belum sempat terkirim. --}}
    <section class="py-14" aria-labelledby="sinyal">
        <p class="tanda">Tanpa sinyal</p>
        <h2 id="sinyal" class="judul-bagian prosa mt-2">
            Sinyal paling sering hilang justru di tempat uang keluar
        </h2>
        <p class="text-ink-soft prosa mt-3">
            Parkiran basement, pasar, jalan antarkota. Rafin tetap menerima
            catatan Anda di sana, lalu mengurus sisanya sendiri.
        </p>

        <ol class="bento mt-7">
            <li class="bento-kartu muncul">
                <p class="tanda">Langkah 1</p>
                <h3 class="text-[17px] font-semibold">Catat seperti biasa</h3>
                <p class="text-ink-soft">
                    Tombol simpan tidak pernah menolak, ada sinyal atau tidak.
                    Catatannya tersimpan di ponsel Anda lebih dulu.
                </p>
            </li>

            <li class="bento-kartu muncul">
                <p class="tanda">Langkah 2</p>
                <h3 class="text-[17px] font-semibold">Menunggu, dan terlihat menunggu</h3>
                <p class="text-ink-soft">
                    Muncul penanda kecil supaya Anda tahu persis berapa yang belum
                    terkirim — bukan diam-diam hilang.
                </p>
                <p class="pita pita-kecil mt-1 self-start">
                    <span class="titik-hidup"></span>
                    2 catatan menunggu terkirim
                </p>
            </li>

            <li class="bento-kartu bento-lebar muncul">
                <p class="tanda">Langkah 3</p>
                <h3 class="text-[17px] font-semibold">Terkirim sendiri, dan tidak pernah dua kali</h3>
                <p class="text-ink-soft prosa">
                    Begitu sinyal kembali, antreannya berangkat tanpa Anda perlu
                    menekan apa pun. Setiap catatan membawa nomor uniknya sendiri,
                    jadi kalau kiriman pertama sebenarnya sampai tapi balasannya
                    hilang di jalan, percobaan berikutnya tidak menambah pengeluaran
                    kedua. Untuk buku kas, itu bedanya antara berguna dan berbahaya.
                </p>
            </li>
        </ol>

        <p class="text-ink-soft prosa mt-6">
            <strong class="text-ink">Tidak perlu memasang apa pun.</strong> Cukup buka
            Rafin sekali lewat peramban, dan ia sudah bisa dipakai tanpa sinyal
            sesudahnya. Memasangnya ke layar utama menambah ikon sendiri, pintasan
            tekan-tahan, dan kemampuan membagikan foto struk dari aplikasi lain
            langsung ke Rafin.
        </p>
    </section>

    {{-- ── Bento: bagaimana ia dibangun ─────────────────────────────────
         Ini menjawab "kenapa saya percaya", bukan "apa untungnya" — dua
         pertanyaan berbeda yang sering dijawab dengan bagian yang sama, lalu
         keduanya jadi setengah terjawab. --}}
    <section class="py-14" aria-labelledby="beda">
        <p class="tanda">Yang membedakan</p>
        <h2 id="beda" class="judul-bagian prosa mt-2">
            Dibangun seperti pembukuan, bukan seperti catatan belanja
        </h2>

        <div class="bento mt-7">

            <div class="bento-kartu bento-lebar muncul">
                <span class="manfaat-ikon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M12 3v18"/><path d="M5 7h14"/>
                        <path d="M5 7 2.5 13a3.5 3.5 0 0 0 5 0L5 7Z"/>
                        <path d="M19 7l-2.5 6a3.5 3.5 0 0 0 5 0L19 7Z"/>
                    </svg>
                </span>
                <h3 class="text-[17px] font-semibold">Angkanya tidak bisa diam-diam salah</h3>
                <p class="text-ink-soft prosa">
                    Setiap transaksi dicatat berpasangan seperti pembukuan sungguhan,
                    dan database menolak menyimpannya kalau tidak seimbang. Rupiah
                    disimpan utuh sampai satuan terkecil — tidak ada pembulatan yang
                    menggerogoti saldo pelan-pelan selama berbulan-bulan.
                </p>
            </div>

            <div class="bento-kartu muncul">
                <span class="manfaat-ikon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M4.5 5.5h15A1.5 1.5 0 0 1 21 7v10a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17V7a1.5 1.5 0 0 1 1.5-1.5Z"/>
                        <path d="m3.5 7 8.5 6 8.5-6"/>
                    </svg>
                </span>
                <h3 class="text-[17px] font-semibold">Catat lewat Telegram juga</h3>
                <p class="text-ink-soft">
                    Kirim pesan ke bot, selesai. Tidak perlu membuka aplikasi apa
                    pun — dan buku kasnya tetap satu, dari kanal mana pun Anda
                    mencatat.
                </p>
            </div>

            <div class="bento-kartu muncul">
                <span class="manfaat-ikon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
                        <path d="M12 3 4.5 6v6c0 4.5 3 7.8 7.5 9 4.5-1.2 7.5-4.5 7.5-9V6L12 3Z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                </span>
                <h3 class="text-[17px] font-semibold">Privasi yang bisa diperiksa</h3>
                <p class="text-ink-soft">
                    Kami tidak bisa melihat nominal transaksi Anda. Bukan berjanji
                    tidak melihat — panel admin memang tidak punya jalan ke sana.
                </p>
                <a href="{{ route('transparansi') }}"
                   class="text-biru tap mt-auto inline-flex items-center gap-1 font-medium underline underline-offset-4">
                    Baca apa yang kami simpan
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                        <path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>
                    </svg>
                </a>
            </div>

        </div>
    </section>

    {{-- ── Jujur soal tahapnya ──────────────────────────────────────────
         Menyebut kekurangan lebih dulu justru menaikkan kepercayaan, dan
         menghindari kekecewaan yang jauh lebih mahal setelah orang
         memindahkan pembukuannya ke sini. --}}
    <section class="py-14" aria-labelledby="tahap">
        <div class="bento-kartu">
            <p class="tanda">Terus terang</p>
            <h2 id="tahap" class="judul-bagian mt-1">Rafin masih beta</h2>

            <p class="prosa mt-2">
                Semua paket berharga Rp 0 selama masa ini, dan Anda bisa mengunduh
                seluruh data Anda kapan saja. Belum ada aplikasi Android — Rafin
                berjalan di peramban dan bisa dipasang ke layar utama seperti
                aplikasi biasa.
            </p>
            <p class="text-ink-soft prosa mt-3">
                Kalau ada yang tidak beres, katakan. Beta berarti kami masih bisa
                mengubahnya.
            </p>
        </div>
    </section>

    {{-- ── Ajakan terakhir ──────────────────────────────────────────────
         Satu tombol. Halaman ini punya satu tujuan. --}}
    <section class="rule-t pt-14 pb-4">
        <div class="prosa">
            <h2 class="judul-bagian">Mulai dari satu catatan</h2>
            <p class="text-ink-soft mt-3">
                Tidak perlu menyiapkan apa pun lebih dulu. Buat akun, catat satu
                pengeluaran hari ini, dan lihat apakah ini cocok untuk Anda.
            </p>

            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="tombol-utama mt-6 w-full sm:w-auto">
                    Buat akun gratis
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                        <path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>
                    </svg>
                </a>
            @endif
        </div>
    </section>

</x-layouts.publik>
