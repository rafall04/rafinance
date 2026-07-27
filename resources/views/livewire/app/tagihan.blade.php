<div>
    <header class="rule-b flex items-center justify-between px-5 py-4">
        <div>
            <h1 class="judul">Tagihan</h1>
            <p class="text-ink-soft text-[13px]">Siapa yang belum bayar, dan sudah berapa lama.</p>
        </div>

        @unless ($sedangMenambah)
            <button type="button" wire:click="bukaFormulir" class="tombol-utama px-4 text-[14px]">Tambah</button>
        @endunless
    </header>

    @if (session('kabar'))
        <p class="kartu text-hijau mx-5 mt-4 px-4 py-3" role="status">{{ session('kabar') }}</p>
    @endif

    <section class="rule-b px-5 py-5">
        <p class="label mb-2">Total piutang</p>
        <x-nominal :uang="$totalPiutang" besar class="block" />
    </section>

    @if ($umur->isNotEmpty())
        <section class="rule-b">
            <h2 class="label bg-paper-sunk rule-b px-5 py-1.5">Umur piutang</h2>

            @foreach (['Belum jatuh tempo', '1-30 hari', '31-60 hari', '61-90 hari', 'Lebih dari 90 hari'] as $kelompok)
                @continue (! $umur->has($kelompok))
                <div class="rule-b flex items-baseline justify-between gap-3 px-5 py-2.5">
                    <span>
                        {{ $kelompok }}
                        <span class="text-ink-soft text-[13px]">· {{ $umur[$kelompok]->jumlah }} tagihan</span>
                    </span>
                    <x-nominal :uang="$umur[$kelompok]->total"
                               :arah="$kelompok === 'Lebih dari 90 hari' ? 'keluar' : null" />
                </div>
            @endforeach
        </section>
    @endif

    @if ($sedangMenambah)
        <form wire:submit="simpan" class="rule-b space-y-4 px-5 py-5">
            <div>
                <label for="kontakId" class="label mb-1.5 block">Pelanggan</label>
                <select id="kontakId" wire:model.live="kontakId" class="isian">
                    <option value="">Pelanggan baru</option>
                    @foreach ($kontakPilihan as $kontak)
                        <option value="{{ $kontak->id }}">{{ $kontak->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($kontakId === '')
                <div>
                    <label for="namaKontak" class="label mb-1.5 block">Nama pelanggan baru</label>
                    <input id="namaKontak" type="text" wire:model="namaKontak" maxlength="80" class="isian">
                    <x-galat untuk="namaKontak" />
                </div>
            @endif

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="nomor" class="label mb-1.5 block">Nomor</label>
                    <input id="nomor" type="text" wire:model="nomor" maxlength="32" required class="isian">
                    <x-galat untuk="nomor" />
                </div>
                <div>
                    <label for="jatuhTempo" class="label mb-1.5 block">Jatuh tempo</label>
                    <input id="jatuhTempo" type="date" wire:model="jatuhTempo" required class="isian">
                    <x-galat untuk="jatuhTempo" />
                </div>
            </div>

            <div>
                <label for="jumlah" class="label mb-1.5 block">Jumlah</label>
                <input id="jumlah" type="text" inputmode="numeric" wire:model="jumlah"
                       placeholder="1500000" required class="isian">
                <x-galat untuk="jumlah" />
            </div>

            <div class="flex gap-2">
                <button type="submit" class="tombol-utama flex-1">Simpan</button>
                <button type="button" wire:click="$set('sedangMenambah', false)" class="tombol-halus">Batal</button>
            </div>
        </form>
    @endif

    @forelse ($belumLunas as $tagihan)
        <article class="rule-b px-5 py-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ $tagihan->contact?->name }}</p>
                    <p class="text-ink-soft text-[13px] leading-[18px]">
                        {{ $tagihan->number }} · {{ $tagihan->statusLabel() }} ·
                        <span class="{{ $tagihan->umurHari() > 30 ? 'text-merah' : '' }}">
                            {{ $tagihan->kelompokUmur() }}
                        </span>
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    <x-nominal :uang="$tagihan->sisa()" class="block" />
                    @unless ($tagihan->dibayar()->isZero())
                        <span class="text-ink-soft text-[12px]">
                            dari {{ $tagihan->total_minor->formatPlain() }}
                        </span>
                    @endunless
                </div>
            </div>

            @if ($membayarId === $tagihan->id)
                <form wire:submit="catatPembayaran" class="mt-3 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="bayar-{{ $tagihan->id }}" class="label mb-1.5 block">Jumlah bayar</label>
                            <input id="bayar-{{ $tagihan->id }}" type="text" inputmode="numeric"
                                   wire:model="jumlahBayar" required class="isian">
                            <x-galat untuk="jumlahBayar" />
                        </div>
                        <div>
                            <label for="akun-{{ $tagihan->id }}" class="label mb-1.5 block">Masuk ke</label>
                            <select id="akun-{{ $tagihan->id }}" wire:model="akunPenerimaId" class="isian" required>
                                @foreach ($akunPilihan as $akun)
                                    <option value="{{ $akun->id }}">{{ $akun->name }}</option>
                                @endforeach
                            </select>
                            <x-galat untuk="akunPenerimaId" />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="tombol-utama flex-1">Catat pembayaran</button>
                        <button type="button" wire:click="$set('membayarId', '')" class="tombol-halus">Tutup</button>
                    </div>
                </form>
            @else
                <button type="button" wire:click="bukaPembayaran('{{ $tagihan->id }}')"
                        class="tap text-biru mt-1 text-[13px]">Catat pembayaran</button>
            @endif
        </article>
    @empty
        <section class="px-5 py-12 text-center">
            <p class="mb-1 font-medium">Tidak ada piutang.</p>
            <p class="text-ink-soft mx-auto max-w-[34ch]">
                Semua tagihan sudah lunas — atau belum ada yang dibuat.
            </p>
        </section>
    @endforelse
</div>
