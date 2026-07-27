<div>
    <header class="rule-b flex items-start justify-between gap-3 px-5 py-4">
        <div class="min-w-0">
            <p class="label">{{ $workspace?->type->label() }}</p>
            <h1 class="judul truncate">{{ $workspace?->name }}</h1>
        </div>

        <a href="{{ route('app.akun') }}" class="tombol-halus shrink-0 px-3 text-[13px]">Akun</a>
    </header>

    {{-- Saldo utama. IBM Plex Mono, tabular, rata kanan — bahkan saat nol. --}}
    <section class="px-5 pt-6 pb-4">
        <p class="label mb-2">
            {{ $akunAktif !== null ? 'Saldo '.$akunAktif->name : 'Saldo total' }}
        </p>
        <x-nominal :uang="$saldoTotal" besar class="block" />
    </section>

    {{-- Chip akun yang bisa digeser. Menyaring buku besar tanpa memuat ulang. --}}
    @if ($akunUang->isNotEmpty())
        <section class="rule-b pb-4">
            <div class="flex snap-x gap-2 overflow-x-auto px-5" role="group" aria-label="Saring per akun">
                <button
                    type="button"
                    wire:click="pilihAkun('')"
                    aria-pressed="{{ $akunAktif === null ? 'true' : 'false' }}"
                    class="tap shrink-0 snap-start rounded-full border px-4 text-[13px]
                           {{ $akunAktif === null ? 'border-transparent bg-biru text-paper' : 'border-rule text-ink-soft' }}"
                >
                    Semua
                </button>

                @foreach ($akunUang as $akun)
                    <button
                        type="button"
                        wire:click="pilihAkun('{{ $akun->id }}')"
                        aria-pressed="{{ $akunAktif?->is($akun) ? 'true' : 'false' }}"
                        class="tap shrink-0 snap-start rounded-full border px-4 text-[13px]
                               {{ $akunAktif?->is($akun) ? 'border-transparent bg-biru text-paper' : 'border-rule' }}"
                    >
                        <span class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $akun->color() }}"></span>
                            {{ $akun->name }}
                        </span>
                    </button>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Saldo rail: garis rambut per hari, net harian di kepala kelompok,
         kolom saldo berjalan rata kanan. Persis kolom saldo di buku kas fisik,
         dan berfungsi karena mata bisa menelusuri ke bawah dan melihat uangnya
         bergerak. --}}
    @forelse ($hari as $kelompok)
        <section aria-labelledby="hari-{{ $kelompok->tanggal->format('Ymd') }}">
            <h2
                id="hari-{{ $kelompok->tanggal->format('Ymd') }}"
                class="bg-paper-sunk rule-b flex items-baseline justify-between px-5 py-1.5"
            >
                <span class="label">
                    {{ $kelompok->tanggal->translatedFormat('j M Y') }}
                </span>
                <x-nominal
                    :uang="$kelompok->netHarian"
                    :arah="$kelompok->netHarian->isNegative() ? 'keluar' : ($kelompok->netHarian->isZero() ? null : 'masuk')"
                    :simbol="false"
                    class="text-[12px]"
                />
            </h2>

            <ul>
                @foreach ($kelompok->baris as $baris)
                    <li class="rule-b grid grid-cols-[1fr_auto] items-baseline gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate {{ $baris->transaksi->isVoid() ? 'text-ink-soft line-through' : '' }}">
                                {{ $baris->transaksi->description ?: $baris->transaksi->kind->label() }}
                            </p>
                            <p class="text-ink-soft truncate text-[13px] leading-[18px]">
                                {{ $baris->transaksi->category?->fullName() ?? $baris->transaksi->kind->label() }}
                                @if ($baris->transaksi->project !== null)
                                    · {{ $baris->transaksi->project->name }}
                                @endif
                                @if ($baris->transaksi->isVoid())
                                    · Dibatalkan
                                @endif
                            </p>
                        </div>

                        <div class="text-right">
                            <x-nominal
                                :uang="$baris->delta"
                                :arah="$baris->delta->isNegative() ? 'keluar' : 'masuk'"
                                :simbol="false"
                                class="block"
                            />
                            {{-- Kolom saldo berjalan: yang membuat daftar ini
                                 terbaca sebagai pembukuan. --}}
                            <x-nominal
                                :uang="$baris->saldo"
                                :simbol="false"
                                class="text-ink-soft block text-[12px]"
                            />
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <section class="px-5 py-12 text-center">
            <p class="mb-1 font-medium">Belum ada transaksi.</p>
            <p class="text-ink-soft mx-auto max-w-[34ch]">
                Catat lewat bot atau tekan tombol + di bawah.
            </p>
        </section>
    @endforelse
</div>
