<div>
    <header class="rule-b flex items-center justify-between px-5 py-4">
        <div>
            <h1 class="judul">Proyek</h1>
            <p class="text-ink-soft text-[13px]">Untung rugi per pekerjaan.</p>
        </div>

        @unless ($sedangMenambah)
            <button type="button" wire:click="bukaFormulir" class="tombol-utama px-4 text-[14px]">Tambah</button>
        @endunless
    </header>

    @if (session('kabar'))
        <p class="kartu text-hijau mx-5 mt-4 px-4 py-3" role="status">{{ session('kabar') }}</p>
    @endif

    @if ($sedangMenambah)
        <form wire:submit="simpan" class="rule-b space-y-3 px-5 py-5">
            <div>
                <label for="nama" class="label mb-1.5 block">Nama proyek</label>
                <input id="nama" type="text" wire:model="nama" maxlength="80" required autofocus
                       placeholder="Event Kantor Juli" class="isian">
                <p class="text-ink-soft mt-1.5 text-[13px] leading-[18px]">
                    Tandai transaksinya dengan <code class="text-ink">#{{ Str::slug($nama ?: 'namaproyek', '') }}</code> lewat bot.
                </p>
                <x-galat untuk="nama" />
            </div>

            <div class="flex gap-2">
                <button type="submit" class="tombol-utama flex-1">Simpan</button>
                <button type="button" wire:click="$set('sedangMenambah', false)" class="tombol-halus">Batal</button>
            </div>
        </form>
    @endif

    @forelse ($daftar as $proyek)
        @php($angka = $ringkasan->get($proyek->id))
        <section class="rule-b px-5 py-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate font-medium {{ $proyek->status === 'done' ? 'text-ink-soft' : '' }}">
                        {{ $proyek->name }}
                    </p>
                    <p class="text-ink-soft text-[13px] leading-[18px]">
                        {{ $proyek->status === 'done' ? 'Selesai' : 'Berjalan' }}
                        @if ($proyek->budget_minor !== null)
                            · anggaran {{ $proyek->budget_minor->format() }}
                        @endif
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    <x-nominal :uang="$angka?->net ?? \App\Support\Money::zero('IDR')"
                               :arah="($angka?->net?->isNegative() ?? false) ? 'keluar' : 'masuk'" class="block" />
                    <span class="text-ink-soft text-[12px]">
                        +{{ $angka?->masuk?->formatPlain() ?? '0' }} / −{{ $angka?->keluar?->formatPlain() ?? '0' }}
                    </span>
                </div>
            </div>

            @if ($proyek->status !== 'done')
                <button type="button" wire:click="tutup('{{ $proyek->id }}')"
                        wire:confirm="Tandai proyek ini selesai?"
                        class="tap text-ink-soft mt-1 text-[13px]">Tandai selesai</button>
            @endif
        </section>
    @empty
        <section class="px-5 py-12 text-center">
            <p class="mb-1 font-medium">Belum ada proyek.</p>
            <p class="text-ink-soft mx-auto max-w-[34ch]">
                Berguna kalau pengeluaran Anda datang per pekerjaan — acara, pemasangan, pesanan.
            </p>
        </section>
    @endforelse
</div>
