<div>
    <header class="layar-kepala">
        <div class="min-w-0">
        <h1 class="judul">Telegram</h1>
        <p class="text-ink-soft text-[13px]">Catat pengeluaran tanpa membuka aplikasi.</p>
        </div>
    </header>

    @if (session('kabar'))
        <p class="kartu text-hijau mx-5 mt-4 px-4 py-3" role="status">{{ session('kabar') }}</p>
    @endif

    @if ($terhubung !== null)
        <section class="rule-b px-5 py-5">
            <p class="label mb-1">Terhubung</p>
            <p class="font-medium">
                {{ $terhubung->username ? '@'.$terhubung->username : 'Akun Telegram Anda' }}
            </p>
            <p class="text-ink-soft text-[13px]">Sejak {{ $terhubung->linked_at->diffForHumans() }}</p>

            <button type="button" wire:click="putuskan"
                    wire:confirm="Putuskan Telegram? Anda bisa menghubungkannya lagi kapan saja."
                    class="tombol-halus mt-4">Putuskan</button>
        </section>
    @else
        <section class="rule-b px-5 py-5">
            @if ($kode === null)
                <p class="mb-4">
                    Buat kode, lalu kirim ke <strong>&#64;{{ $namaBot }}</strong> di Telegram.
                </p>
                <button type="button" wire:click="terbitkanKode" class="tombol-utama w-full">Buat kode</button>
            @else
                <p class="label mb-2">Kirim ini ke bot</p>

                {{-- Kode ditampilkan dengan huruf monospace supaya digitnya
                     mudah dibaca ulang saat mengetiknya di aplikasi lain. --}}
                <p class="nominal nominal-lg block text-center tracking-[0.2em]">
                    <span class="angka">/link {{ $kode }}</span>
                </p>

                <p class="text-ink-soft mt-3 text-center text-[13px]">
                    Berlaku {{ $kedaluwarsa }}. Setelah itu buat yang baru.
                </p>

                <button type="button" wire:click="terbitkanKode" class="tombol-halus mt-4 w-full">Buat kode baru</button>
            @endif
        </section>
    @endif

    <section class="px-5 py-5">
        <h2 class="label mb-2">Contoh yang dimengerti bot</h2>
        <ul class="text-ink-soft space-y-1 text-[13px]">
            <li><code class="text-ink">50k bensin</code></li>
            <li><code class="text-ink">50rb bensin bca</code></li>
            <li><code class="text-ink">+2jt dp event pak budi</code></li>
            <li><code class="text-ink">150.000 solar genset #eventA</code></li>
            <li><code class="text-ink">pindah 500k kas ke bca</code></li>
        </ul>
        <p class="text-ink-soft mt-3 text-[13px] leading-[18px]">
            Yang tidak terbaca tidak ditolak — ia masuk ke Inbox untuk dilengkapi nanti.
        </p>
    </section>
</div>
