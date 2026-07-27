<x-layouts.tamu title="Apa yang admin bisa lihat">
    <article class="space-y-6">
        <header>
            <h1 class="judul mb-1">Apa yang admin Rafin bisa dan tidak bisa lihat</h1>
            <p class="text-ink-soft">
                Halaman ini publik dan sengaja spesifik. Janji privasi yang tidak menyebut nama tabel
                bukan janji, melainkan pemasaran.
            </p>
        </header>

        <section>
            <h2 class="label mb-2">Tidak pernah bisa dilihat</h2>
            <ul class="space-y-1.5">
                <li>Nominal transaksi Anda — berapa pun, kapan pun.</li>
                <li>Keterangan, kategori, dan catatan transaksi.</li>
                <li>Saldo akun, laporan, dan anggaran.</li>
                <li>Foto struk dan lampiran apa pun.</li>
                <li>Riwayat perubahan di dalam buku Anda.</li>
                <li>Isi pesan yang Anda kirim ke bot Telegram.</li>
            </ul>
            <p class="text-ink-soft mt-2 text-[13px] leading-[18px]">
                Ini bukan kebijakan yang bisa dilanggar diam-diam. Panel admin dibangun tanpa satu
                pun jalan menuju tabel-tabel itu, dan ada test otomatis yang menggagalkan rilis kalau
                seseorang mencoba menambahkannya.
            </p>
        </section>

        <section>
            <h2 class="label mb-2">Bisa dilihat</h2>
            <ul class="space-y-1.5">
                <li>Nama dan alamat surel Anda.</li>
                <li>Nama buku, jumlah anggota, dan kapan terakhir dipakai.</li>
                <li><strong>Jumlah</strong> transaksi — angka hitungannya saja, tanpa isi.</li>
                <li>Besar penyimpanan lampiran, dalam byte.</li>
                <li>Plan langganan dan riwayat pembayaran.</li>
                <li>Peristiwa keamanan: waktu masuk, alamat IP, jenis perangkat.</li>
            </ul>
        </section>

        <section>
            <h2 class="label mb-2">Kalau Anda butuh bantuan</h2>
            <p>
                Admin tidak bisa masuk sebagai Anda. Tidak ada tombol impersonate, dan itu memang
                tidak dibangun.
            </p>
            <p class="mt-2">
                Yang ada: Anda menerbitkan izin akses dukungan dari halaman Keamanan, berdurasi paling
                lama 24 jam dan bisa dicabut kapan saja. Setiap kali izin itu dipakai, Anda diberi
                tahu — bukan sesudahnya, tapi saat itu juga.
            </p>
        </section>

        <section>
            <h2 class="label mb-2">Kenapa dipisah begini</h2>
            <p>
                Catatan keuangan adalah salah satu hal paling pribadi yang dimiliki seseorang. Ia
                memperlihatkan ke mana orang pergi, siapa yang dibayarnya, dan seberapa berat
                bulannya.
            </p>
            <p class="mt-2">
                Menjaganya bukan soal niat baik pengelola. Sistem yang bergantung pada niat baik akan
                gagal pada hari pengelolanya berubah, dijual, atau ditembus. Karena itu pemisahannya
                ditegakkan di struktur: dua peran database yang berbeda, penyaringan baris di dalam
                PostgreSQL, dan panel admin yang secara harfiah tidak punya kode untuk membaca tabel
                transaksi.
            </p>
        </section>

        <footer class="rule-t pt-6">
            <a href="{{ route('app.beranda') }}" class="text-biru tap inline-flex items-center">
                Kembali ke Rafin
            </a>
        </footer>
    </article>
</x-layouts.tamu>
