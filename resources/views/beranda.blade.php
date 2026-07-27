{{--
    Halaman depan Rafin.

    Satu kolom, satu ajakan. Pola "Minimal Single Column": tanpa navigasi yang
    ramai, tanpa carousel, tanpa gambar besar. Sasarannya Android kelas bawah
    dengan kuota tipis, dan halaman inilah yang mereka bayar lebih dulu sebelum
    tahu apakah Rafin berguna bagi mereka.

    Tidak ada testimoni dan tidak ada angka jumlah pengguna. Rafin masih beta
    dan belum punya keduanya; mengarangnya untuk aplikasi keuangan adalah cara
    tercepat kehilangan hal yang justru sedang dibangun. Yang dipakai sebagai
    bukti hanya yang bisa diperiksa sendiri oleh pembacanya.
--}}

<x-layouts.publik>

    {{-- ── Hero ────────────────────────────────────────────────────────── --}}
    <section class="pt-6 pb-12">
        <h1 class="hero-judul">
            Catat dulu.<br>Rapikan nanti.
        </h1>

        <p class="text-ink-soft mt-4 text-[17px] leading-7">
            Buku kas untuk pribadi dan usaha kecil. Tulis pengeluaran apa adanya
            dalam dua detik — kategori, catatan, dan struknya bisa menyusul kapan
            saja Anda sempat.
        </p>

        <div class="mt-7 flex flex-col gap-3">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="tombol-utama w-full">Mulai gratis</a>
            @endif

            <a href="{{ route('login') }}" class="tombol-halus tap w-full justify-center">
                Sudah punya akun
            </a>
        </div>

        <p class="text-ink-soft mt-3 text-[13px]">
            Gratis sepenuhnya selama beta. Tanpa kartu kredit.
        </p>
    </section>

    {{-- ── Cara kerjanya ────────────────────────────────────────────────
         Diperagakan, bukan dijelaskan. Kalimat "input cepat" ada di setiap
         aplikasi keuangan; yang membedakan adalah melihat sendiri bahwa
         catatan setengah jadi memang diterima, bukan ditolak. --}}
    <section class="py-12" aria-labelledby="cara">
        <h2 id="cara" class="judul">Yang lain menolak catatan setengah jadi</h2>
        <p class="text-ink-soft mt-2">
            Rafin menerimanya, lalu mengingatkan Anda nanti.
        </p>

        <div class="peraga mt-6">
            <p class="label text-ink-soft">Anda ketik, di web atau di Telegram</p>

            <p class="gelembung mt-2">kopi 25rb</p>

            <div class="peraga-panah" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                    <path d="M12 5v14"/><path d="m6 13 6 6 6-6"/>
                </svg>
            </div>

            <p class="label text-ink-soft">Tersimpan, meski belum lengkap</p>

            <div class="kartu mt-2 px-4 py-3">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="font-medium">Kopi</span>
                    {{-- Sengaja BUKAN .nominal. Kelas itu memakai IBM Plex Mono,
                         dan memuatnya di sini berarti mengunduh 14,8 KB font
                         untuk satu angka — 17% berat halaman, dibayar juga oleh
                         orang yang cuma melihat sekilas lalu pergi. Public Sans
                         punya angka berlebar seragam sendiri, dan pada 15px
                         bedanya tidak terlihat. --}}
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

        <p class="text-ink-soft mt-4">
            Tidak ada formulir yang harus diselesaikan saat Anda sedang di kasir.
            Draf menumpuk di Inbox, dan dirapikan sekaligus saat Anda santai.
        </p>
    </section>

    {{-- ── Tiga manfaat ─────────────────────────────────────────────────
         Tiga, bukan enam. Daftar panjang membuat pembaca memindai lalu tidak
         mengingat satu pun. --}}
    <section class="py-12" aria-labelledby="manfaat">
        <h2 id="manfaat" class="judul">Tiga hal yang membedakan</h2>

        <ul class="mt-6 space-y-7">
            <li class="manfaat">
                <span class="manfaat-ikon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <path d="M4.5 5.5h15A1.5 1.5 0 0 1 21 7v10a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17V7a1.5 1.5 0 0 1 1.5-1.5Z"/>
                        <path d="m3.5 7 8.5 6 8.5-6"/>
                    </svg>
                </span>
                <div>
                    <h3 class="font-semibold">Dari mana pun, termasuk tanpa sinyal</h3>
                    <p class="text-ink-soft mt-1">
                        Web, atau kirim pesan ke bot Telegram. Kalau jaringan sedang
                        putus, catatan Anda mengantre di ponsel dan terkirim sendiri
                        begitu sinyalnya kembali — tanpa tergandakan.
                    </p>
                </div>
            </li>

            <li class="manfaat">
                <span class="manfaat-ikon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <path d="M12 3v18"/><path d="M5 7h14"/>
                        <path d="M5 7 2.5 13a3.5 3.5 0 0 0 5 0L5 7Z"/>
                        <path d="M19 7l-2.5 6a3.5 3.5 0 0 0 5 0L19 7Z"/>
                    </svg>
                </span>
                <div>
                    <h3 class="font-semibold">Angkanya tidak bisa diam-diam salah</h3>
                    <p class="text-ink-soft mt-1">
                        Setiap transaksi dicatat berpasangan seperti pembukuan
                        sungguhan, dan database menolak menyimpannya kalau tidak
                        seimbang. Rupiah disimpan utuh sampai satuan terkecil —
                        tidak ada pembulatan yang menggerogoti saldo pelan-pelan.
                    </p>
                </div>
            </li>

            <li class="manfaat">
                <span class="manfaat-ikon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5">
                        <path d="M12 3 4.5 6v6c0 4.5 3 7.8 7.5 9 4.5-1.2 7.5-4.5 7.5-9V6L12 3Z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                </span>
                <div>
                    <h3 class="font-semibold">Privasi yang bisa Anda periksa</h3>
                    <p class="text-ink-soft mt-1">
                        Kami yang mengelola Rafin tidak bisa melihat nominal
                        transaksi Anda. Bukan karena berjanji tidak melihat —
                        panel admin memang tidak punya jalan ke sana, dan uji
                        otomatis menggagalkan rilis kalau ada yang menambahkannya.
                    </p>
                    <a href="{{ route('transparansi') }}"
                       class="text-biru tap mt-2 inline-flex items-center gap-1 underline underline-offset-4">
                        Baca apa yang kami simpan
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true">
                            <path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>
                        </svg>
                    </a>
                </div>
            </li>
        </ul>
    </section>

    {{-- ── Jujur soal tahapnya ──────────────────────────────────────────
         Menyebut kekurangan lebih dulu justru menaikkan kepercayaan, dan
         menghindari kekecewaan yang jauh lebih mahal setelah orang memindahkan
         pembukuannya ke sini. --}}
    <section class="py-12" aria-labelledby="tahap">
        <h2 id="tahap" class="judul">Rafin masih beta</h2>

        <div class="kartu mt-4 px-4 py-4">
            <p>
                Semua paket berharga Rp 0 selama masa ini, dan Anda bisa mengunduh
                seluruh data Anda kapan saja. Belum ada aplikasi Android — Rafin
                berjalan di peramban dan bisa dipasang ke layar utama seperti
                aplikasi biasa.
            </p>
            <p class="text-ink-soft mt-3">
                Kalau ada yang tidak beres, katakan. Beta berarti kami masih bisa
                mengubahnya.
            </p>
        </div>
    </section>

    {{-- ── Ajakan terakhir ──────────────────────────────────────────────
         Satu tombol saja. Halaman ini punya satu tujuan. --}}
    <section class="rule-t pt-12 pb-4">
        <h2 class="display">Mulai dari satu catatan</h2>
        <p class="text-ink-soft mt-3">
            Tidak perlu menyiapkan apa pun. Buat akun, catat satu pengeluaran
            hari ini, dan lihat apakah ini cocok untuk Anda.
        </p>

        @if (Route::has('register'))
            <a href="{{ route('register') }}" class="tombol-utama mt-6 w-full">Buat akun gratis</a>
        @endif
    </section>

</x-layouts.publik>
