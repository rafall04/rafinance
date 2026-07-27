<div>
    <header class="layar-kepala">
        <div class="min-w-0">
        <h1 class="judul">Laporan</h1>
        <p class="text-ink-soft text-[13px]">
            {{ $dari->translatedFormat('j M Y') }} – {{ $sampai->translatedFormat('j M Y') }}
        </p>
        </div>
    </header>

    <section class="rule-b py-3">
        <div class="flex snap-x gap-2 overflow-x-auto px-5" role="group" aria-label="Pilih periode">
            @foreach (['bulan-ini' => 'Bulan ini', 'bulan-lalu' => 'Bulan lalu', 'minggu-ini' => 'Minggu ini', 'tahun-ini' => 'Tahun ini'] as $nilai => $label)
                <button type="button" wire:click="pilihPeriode('{{ $nilai }}')"
                        aria-pressed="{{ $periode === $nilai ? 'true' : 'false' }}"
                        class="tap shrink-0 snap-start rounded-full border px-4 text-[13px]
                               {{ $periode === $nilai ? 'border-transparent bg-biru text-paper' : 'border-rule text-ink-soft' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </section>

    {{-- Ringkasan periode. Angka besar dulu, rinciannya di bawah. --}}
    <section class="rule-b grid grid-cols-2 gap-x-4 gap-y-3 px-5 py-5">
        <div>
            <p class="label mb-1">Pemasukan</p>
            <x-nominal :uang="$labaRugi->pendapatan" arah="masuk" class="block" />
        </div>
        <div>
            <p class="label mb-1">Pengeluaran</p>
            <x-nominal :uang="$labaRugi->beban" arah="keluar" class="block" />
        </div>
        <div class="col-span-2 rule-t pt-3">
            <p class="label mb-1">{{ $labaRugi->laba->isNegative() ? 'Rugi' : 'Laba' }}</p>
            <x-nominal :uang="$labaRugi->laba" besar
                       :arah="$labaRugi->laba->isNegative() ? 'keluar' : 'masuk'" class="block" />

            @if (! $banding->sebelumnya->laba->isZero())
                <p class="text-ink-soft mt-1 text-[13px]">
                    {{ $banding->selisih->isNegative() ? 'Turun' : 'Naik' }}
                    {{ $banding->selisih->abs()->format() }} dari periode sebelumnya
                </p>
            @endif
        </div>
    </section>

    <section class="rule-b py-3">
        <div class="flex snap-x gap-2 overflow-x-auto px-5" role="group" aria-label="Pilih tampilan">
            @foreach (['kategori' => 'Kategori', 'akun' => 'Akun', 'proyek' => 'Proyek', 'kontak' => 'Kontak', 'arus' => 'Arus kas'] as $nilai => $label)
                <button type="button" wire:click="$set('tampilan', '{{ $nilai }}')"
                        aria-pressed="{{ $tampilan === $nilai ? 'true' : 'false' }}"
                        class="tap shrink-0 snap-start rounded-full border px-4 text-[13px]
                               {{ $tampilan === $nilai ? 'border-transparent bg-biru text-paper' : 'border-rule text-ink-soft' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </section>

    @if ($tampilan === 'kategori')
        <section>
            <h2 class="label bg-paper-sunk rule-b px-5 py-1.5">Pengeluaran per kategori</h2>
            @forelse ($isi as $baris)
                <div class="rule-b flex items-baseline justify-between gap-3 px-5 py-2.5">
                    <span class="truncate">{{ $baris->nama }}
                        <span class="text-ink-soft text-[13px]">· {{ $baris->jumlah }}</span>
                    </span>
                    <x-nominal :uang="$baris->total" arah="keluar" />
                </div>
            @empty
                <p class="text-ink-soft px-5 py-8 text-center">Belum ada pengeluaran di periode ini.</p>
            @endforelse

            @if ($pemasukan->isNotEmpty())
                <h2 class="label bg-paper-sunk rule-b rule-t px-5 py-1.5">Pemasukan per kategori</h2>
                @foreach ($pemasukan as $baris)
                    <div class="rule-b flex items-baseline justify-between gap-3 px-5 py-2.5">
                        <span class="truncate">{{ $baris->nama }}</span>
                        <x-nominal :uang="$baris->total" arah="masuk" />
                    </div>
                @endforeach
            @endif
        </section>
    @elseif ($tampilan === 'arus')
        <section>
            <h2 class="label bg-paper-sunk rule-b px-5 py-1.5">Arus kas</h2>
            @forelse ($isi as $baris)
                <div class="rule-b grid grid-cols-[1fr_auto] gap-3 px-5 py-2.5">
                    <span>{{ \Carbon\CarbonImmutable::parse($baris->periode)->translatedFormat('j M Y') }}</span>
                    <div class="text-right">
                        <x-nominal :uang="$baris->net"
                                   :arah="$baris->net->isNegative() ? 'keluar' : 'masuk'" class="block" />
                        <span class="text-ink-soft text-[12px]">
                            +{{ $baris->masuk->formatPlain() }} / −{{ $baris->keluar->formatPlain() }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="text-ink-soft px-5 py-8 text-center">Belum ada pergerakan di periode ini.</p>
            @endforelse
        </section>
    @else
        <section>
            <h2 class="label bg-paper-sunk rule-b px-5 py-1.5">Ringkasan per {{ $tampilan }}</h2>
            @forelse ($isi as $baris)
                <div class="rule-b grid grid-cols-[1fr_auto] gap-3 px-5 py-2.5">
                    <span class="truncate">{{ $baris->nama }}</span>
                    <div class="text-right">
                        <x-nominal :uang="$baris->net"
                                   :arah="$baris->net->isNegative() ? 'keluar' : 'masuk'" class="block" />
                        <span class="text-ink-soft text-[12px]">
                            +{{ $baris->masuk->formatPlain() }} / −{{ $baris->keluar->formatPlain() }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="text-ink-soft px-5 py-8 text-center">Belum ada data untuk pengelompokan ini.</p>
            @endforelse
        </section>
    @endif

    {{-- Neraca. Baris "seimbang" bukan hiasan: kalau ia pernah menunjukkan
         tidak, ada entries yang masuk tanpa melewati trigger keseimbangan. --}}
    <section class="rule-t px-5 py-5">
        <h2 class="label mb-3">Neraca per {{ $sampai->translatedFormat('j M Y') }}</h2>

        <dl class="space-y-2">
            <div class="flex items-baseline justify-between">
                <dt>Harta</dt>
                <dd><x-nominal :uang="$neraca->harta" /></dd>
            </div>
            <div class="flex items-baseline justify-between">
                <dt>Utang</dt>
                <dd><x-nominal :uang="$neraca->utang" /></dd>
            </div>
            <div class="rule-t flex items-baseline justify-between pt-2">
                <dt class="font-medium">Modal</dt>
                <dd><x-nominal :uang="$neraca->modal" /></dd>
            </div>
        </dl>

        @unless ($neraca->seimbang)
            <p class="pesan-galat mt-3" role="alert">
                <strong>Neraca tidak seimbang.</strong>
                Harta seharusnya sama dengan utang ditambah modal. Laporkan ini — ada entries yang
                masuk tanpa melewati pemeriksaan keseimbangan.
            </p>
        @endunless
    </section>

    <section class="rule-t px-5 py-5">
        <a href="{{ route('app.ekspor', ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]) }}"
           class="tombol-halus w-full">Unduh CSV periode ini</a>
        <p class="text-ink-soft mt-2 text-[13px] leading-[18px]">
            Setiap ekspor tercatat sebagai peristiwa keamanan dan diberitahukan ke pemilik buku.
        </p>
    </section>
</div>
