{{--
    Halaman transparansi.

    Ditulis ulang karena versi sebelumnya berbahasa saya, bukan berbahasa
    pembacanya: "panel admin", "tabel", "test otomatis menggagalkan rilis".
    Orang yang membuka halaman ini sedang menimbang apakah aman menaruh catatan
    uangnya di sini, dan kalimat yang harus diurai dua kali justru menambah
    ragu — persis kebalikan dari gunanya halaman ini.

    Aturannya: kalimat pendek, kata sehari-hari, yang terpenting di paling
    atas. Penjelasan teknis tetap ada, tapi dilipat di bawah, untuk yang
    memang mencarinya.
--}}

<x-layouts.publik title="Apa yang kami bisa lihat">
    <article class="prosa py-6">

        <h1 class="judul-bagian">Apa yang kami bisa lihat dari catatan Anda</h1>
        <p class="text-ink-soft mt-3">
            Jawaban singkatnya: nyaris tidak ada. Halaman ini merincinya satu per
            satu, supaya Anda tidak perlu percaya begitu saja.
        </p>

        {{-- Yang paling ingin diketahui pembaca diletakkan pertama. --}}
        <section class="mt-10" aria-labelledby="tidak">
            <h2 id="tidak" class="judul">Yang tidak bisa kami lihat</h2>

            <ul class="mt-3 space-y-2.5">
                <li class="flex gap-2.5">
                    <x-tanda-silang /> <span>Nominal transaksi Anda. Berapa pun, kapan pun.</span>
                </li>
                <li class="flex gap-2.5">
                    <x-tanda-silang /> <span>Keterangan, kategori, dan catatan yang Anda tulis.</span>
                </li>
                <li class="flex gap-2.5">
                    <x-tanda-silang /> <span>Saldo, laporan, dan anggaran Anda.</span>
                </li>
                <li class="flex gap-2.5">
                    <x-tanda-silang /> <span>Foto struk dan lampiran apa pun.</span>
                </li>
                <li class="flex gap-2.5">
                    <x-tanda-silang /> <span>Isi pesan Anda ke bot Telegram.</span>
                </li>
            </ul>

            <p class="text-ink-soft mt-4">
                Ini bukan aturan yang kami janjikan akan kami patuhi. Halaman
                pengelola Rafin memang tidak punya jalan ke sana — seperti pintu
                yang tidak dipasang, bukan pintu yang dikunci.
            </p>
        </section>

        <section class="mt-10" aria-labelledby="bisa">
            <h2 id="bisa" class="judul">Yang bisa kami lihat</h2>

            <ul class="mt-3 space-y-2.5">
                <li class="flex gap-2.5">
                    <x-tanda-centang /> <span>Nama dan alamat surel Anda.</span>
                </li>
                <li class="flex gap-2.5">
                    <x-tanda-centang /> <span>Nama buku kas, berapa orang di dalamnya, dan kapan terakhir dibuka.</span>
                </li>
                <li class="flex gap-2.5">
                    <x-tanda-centang /> <span><strong>Berapa banyak</strong> transaksi Anda. Hitungannya saja, bukan isinya.</span>
                </li>
                <li class="flex gap-2.5">
                    <x-tanda-centang /> <span>Besar ruang yang dipakai foto struk Anda.</span>
                </li>
                <li class="flex gap-2.5">
                    <x-tanda-centang /> <span>Paket langganan dan riwayat pembayaran.</span>
                </li>
                <li class="flex gap-2.5">
                    <x-tanda-centang /> <span>Waktu Anda masuk, dari alamat internet dan perangkat apa.</span>
                </li>
            </ul>

            <p class="text-ink-soft mt-4">
                Yang terakhir itu justru untuk keamanan Anda: dari situ ketahuan
                kalau ada orang lain mencoba masuk ke akun Anda.
            </p>
        </section>

        <section class="mt-10" aria-labelledby="bantuan">
            <h2 id="bantuan" class="judul">Kalau Anda minta bantuan kami</h2>

            <p class="mt-3">
                Kami tidak bisa masuk sebagai Anda. Tombolnya tidak ada, dan memang
                sengaja tidak pernah dibuat.
            </p>
            <p class="mt-3">
                Yang bisa Anda lakukan: memberi izin lihat dari halaman Keamanan.
                Izin itu berlaku paling lama 24 jam, bisa dicabut kapan saja, dan
                setiap kali dipakai Anda langsung diberi tahu — saat itu juga,
                bukan belakangan.
            </p>
        </section>

        <section class="mt-10" aria-labelledby="kenapa">
            <h2 id="kenapa" class="judul">Kenapa dibuat serepot ini</h2>

            <p class="mt-3">
                Catatan keuangan termasuk hal paling pribadi yang dimiliki
                seseorang. Ia memperlihatkan ke mana Anda pergi, siapa yang Anda
                bayar, dan seberapa berat bulan Anda.
            </p>
            <p class="mt-3">
                Kalau perlindungannya cuma berupa janji, ia bertahan selama
                orangnya masih orang yang sama. Perusahaan berpindah tangan,
                karyawan berganti, sistem bisa ditembus. Janji tidak menolong di
                hari mana pun itu terjadi.
            </p>
            <p class="mt-3">
                Karena itu batasnya dipasang di dalam mesinnya, bukan di dalam
                aturan kerja kami.
            </p>
        </section>

        {{-- Detail teknis dilipat. Ia penting bagi sebagian kecil pembaca dan
             jadi penghalang bagi sisanya — menaruhnya di alur utama membuat
             seluruh halaman terbaca sebagai dokumen teknis. --}}
        <section class="mt-10" aria-labelledby="teknis">
            <h2 id="teknis" class="judul">Buat yang ingin tahu teknisnya</h2>

            <details class="kartu mt-3 px-4 py-3">
                <summary class="tap text-biru flex cursor-pointer items-center font-medium">
                    Bagaimana batas itu ditegakkan
                </summary>

                <div class="text-ink-soft mt-3 space-y-3">
                    <p>
                        Aplikasi Rafin dan proses pemeliharaannya memakai dua akun
                        database yang berbeda. Akun yang dipakai aplikasi sengaja
                        tidak diberi hak untuk melewati penyaringan.
                    </p>
                    <p>
                        Penyaringannya dikerjakan PostgreSQL sendiri, bukan oleh kode
                        aplikasi. Artinya perintah yang lupa menyaring pun tetap tidak
                        mengembalikan isi buku kas orang lain.
                    </p>
                    <p>
                        Halaman pengelola tidak memuat kode untuk membaca transaksi,
                        lampiran, anggaran, maupun tagihan. Ada pemeriksaan otomatis
                        yang menolak setiap perubahan kode yang mencoba menambahkannya,
                        jadi ini tidak bisa bocor pelan-pelan lewat kelalaian.
                    </p>
                    <p>
                        Catatan keamanan — waktu masuk dan alamat internet — disimpan
                        terpisah, dengan pemeriksaan yang memastikan tidak ada nilai
                        uang yang pernah ikut tersimpan di sana.
                    </p>
                </div>
            </details>
        </section>

        <p class="rule-t text-ink-soft mt-10 pt-6">
            Ada yang belum jelas atau terasa janggal? Katakan. Halaman ini ada
            supaya bisa diperiksa, bukan supaya terlihat meyakinkan.
        </p>

    </article>
</x-layouts.publik>
