<div>
    <header class="rule-b flex items-center justify-between px-5 py-4">
        <div>
            <h1 class="judul">Anggaran</h1>
            <p class="text-ink-soft text-[13px]">Batas belanja per kategori.</p>
        </div>

        @unless ($sedangMenambah)
            <button type="button" wire:click="bukaFormulir" class="tombol-utama px-4 text-[14px]">Tambah</button>
        @endunless
    </header>

    @if (session('kabar'))
        <p class="kartu text-hijau mx-5 mt-4 px-4 py-3" role="status">{{ session('kabar') }}</p>
    @endif

    @if ($sedangMenambah)
        <form wire:submit="simpan" class="rule-b space-y-4 px-5 py-5">
            <div>
                <label for="kategoriId" class="label mb-1.5 block">Kategori</label>
                <select id="kategoriId" wire:model="kategoriId" class="isian" required>
                    <option value="">Pilih kategori</option>
                    @foreach ($kategoriPilihan as $kategori)
                        <option value="{{ $kategori->id }}">{{ $kategori->fullName() }}</option>
                    @endforeach
                </select>
                <x-galat untuk="kategoriId" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="jumlah" class="label mb-1.5 block">Batas</label>
                    <input id="jumlah" type="text" inputmode="numeric" wire:model="jumlah"
                           placeholder="500000" required class="isian">
                    <x-galat untuk="jumlah" />
                </div>
                <div>
                    <label for="periode" class="label mb-1.5 block">Periode</label>
                    <select id="periode" wire:model="periode" class="isian" required>
                        <option value="monthly">Bulanan</option>
                        <option value="weekly">Mingguan</option>
                    </select>
                </div>
            </div>

            <label class="tap flex items-start gap-2.5">
                <input type="checkbox" wire:model="rollover" class="mt-1 h-4 w-4 accent-[var(--biru-50)]">
                <span>
                    <span class="block">Bawa sisa ke periode berikutnya</span>
                    {{-- Kelebihan belanja TIDAK dibawa jadi minus: anggaran alat
                         perencanaan, bukan alat menghukum. --}}
                    <span class="text-ink-soft block text-[13px] leading-[18px]">
                        Hanya sisanya yang dibawa. Kelebihan belanja tidak jadi minus bulan depan.
                    </span>
                </span>
            </label>

            <div class="flex gap-2">
                <button type="submit" class="tombol-utama flex-1">Simpan</button>
                <button type="button" wire:click="batal" class="tombol-halus">Batal</button>
            </div>
        </form>
    @endif

    @forelse ($kemajuan as $baris)
        <section class="rule-b px-5 py-3">
            <div class="mb-2 flex items-baseline justify-between gap-3">
                <span class="truncate font-medium">{{ $baris->budget->category?->fullName() }}</span>
                <button type="button" wire:click="hapus('{{ $baris->budget->id }}')"
                        wire:confirm="Nonaktifkan anggaran ini?"
                        class="tap text-ink-soft shrink-0 px-2 text-[13px]">Hapus</button>
            </div>

            {{-- Bilah kemajuan diberi role dan nilai, bukan sekadar warna:
                 pembaca layar juga perlu tahu sudah sejauh mana. --}}
            <div class="bg-paper-sunk h-2 w-full overflow-hidden rounded-full"
                 role="progressbar"
                 aria-valuenow="{{ $baris->persentase }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Terpakai {{ $baris->persentase }} persen">
                <div class="h-full rounded-full {{ $baris->terlampaui ? 'bg-merah' : 'bg-biru' }}"
                     style="width: {{ $baris->persentase }}%"></div>
            </div>

            <div class="mt-2 flex items-baseline justify-between text-[13px]">
                <span class="{{ $baris->terlampaui ? 'text-merah' : 'text-ink-soft' }}">
                    @if ($baris->terlampaui)
                        Lewat {{ $baris->sisa->abs()->format() }}
                    @else
                        Sisa {{ $baris->sisa->format() }}
                    @endif
                </span>
                <span class="text-ink-soft">
                    {{ $baris->terpakai->formatPlain() }} / {{ $baris->tersedia->formatPlain() }}
                </span>
            </div>

            @if (! $baris->periode->carried_in_minor->isZero())
                <p class="text-ink-soft mt-1 text-[12px]">
                    Termasuk {{ $baris->periode->carried_in_minor->format() }} sisa periode lalu.
                </p>
            @endif
        </section>
    @empty
        <section class="px-5 py-12 text-center">
            <p class="mb-1 font-medium">Belum ada anggaran.</p>
            <p class="text-ink-soft mx-auto max-w-[34ch]">
                Mulai dari satu kategori yang paling sering bikin kaget di akhir bulan.
            </p>
        </section>
    @endforelse

    @if ($target->isNotEmpty())
        <section class="rule-t px-5 py-5">
            <h2 class="label mb-3">Target tabungan</h2>

            @foreach ($target as $satu)
                <div class="mb-4">
                    <div class="mb-1 flex items-baseline justify-between gap-3">
                        <span class="truncate">{{ $satu->name }}</span>
                        <x-nominal :uang="$satu->target_minor" class="text-[13px]" />
                    </div>
                    <div class="bg-paper-sunk h-2 w-full overflow-hidden rounded-full"
                         role="progressbar" aria-valuenow="{{ $satu->persentase() }}"
                         aria-valuemin="0" aria-valuemax="100">
                        <div class="bg-hijau h-full rounded-full" style="width: {{ $satu->persentase() }}%"></div>
                    </div>
                    <p class="text-ink-soft mt-1 text-[12px]">
                        Terkumpul {{ $satu->terkumpul()->format() }}
                        @if ($satu->target_date !== null)
                            · target {{ $satu->target_date->translatedFormat('j M Y') }}
                        @endif
                    </p>
                </div>
            @endforeach
        </section>
    @endif
</div>
