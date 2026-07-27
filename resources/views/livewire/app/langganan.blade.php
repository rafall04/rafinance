<div>
    <header class="rule-b px-5 py-4">
        <h1 class="judul">Langganan</h1>
        <p class="text-ink-soft text-[13px]">
            Semua plan Rp 0 selama masa beta.
        </p>
    </header>

    <section class="rule-b px-5 py-5">
        <p class="label mb-1">Plan saat ini</p>
        <p class="display">{{ $langganan?->plan?->name ?? 'Gratis' }}</p>
        <p class="text-ink-soft mt-1 text-[13px]">
            {{ $langganan?->statusLabel() ?? 'Aktif' }}
            @if ($langganan?->current_period_end)
                · diperbarui {{ $langganan->current_period_end->translatedFormat('j M Y') }}
            @endif
        </p>
    </section>

    <section class="rule-b px-5 py-5">
        <h2 class="label mb-3">Pemakaian</h2>

        @foreach ($pemakaian as $baris)
            <div class="mb-4 last:mb-0">
                <div class="mb-1 flex items-baseline justify-between gap-3">
                    <span>{{ $baris->label }}</span>
                    <span class="nominal text-[13px]">
                        <span class="angka">
                            {{ number_format($baris->terpakai, 0, ',', '.') }}
                            @if ($baris->batas >= 0)
                                / {{ number_format($baris->batas, 0, ',', '.') }}
                            @else
                                / tanpa batas
                            @endif
                        </span>
                    </span>
                </div>

                @if ($baris->batas >= 0)
                    <div class="bg-paper-sunk h-2 w-full overflow-hidden rounded-full"
                         role="progressbar" aria-valuenow="{{ $baris->persen }}"
                         aria-valuemin="0" aria-valuemax="100" aria-label="{{ $baris->label }}">
                        <div class="h-full rounded-full {{ $baris->persen >= 90 ? 'bg-merah' : 'bg-biru' }}"
                             style="width: {{ $baris->persen }}%"></div>
                    </div>
                @endif
            </div>
        @endforeach

        {{-- Yang dibatasi hanya menambah, tidak pernah membaca. --}}
        <p class="text-ink-soft mt-4 text-[13px] leading-[18px]">
            Melewati batas berarti tidak bisa menambah catatan baru. Catatan lama tetap bisa dibuka,
            dicari, dan diekspor — buku Anda tidak pernah disandera.
        </p>
    </section>

    <section class="rule-b px-5 py-5">
        <h2 class="label mb-3">Plan yang tersedia</h2>

        @foreach ($planTersedia as $plan)
            <div class="kartu mb-2 px-4 py-3 last:mb-0">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="font-medium">
                        {{ $plan->name }}
                        @if ($langganan?->plan_id === $plan->id)
                            <span class="text-hijau text-[13px]">· sekarang</span>
                        @endif
                    </span>
                    <x-nominal :uang="$plan->price_minor" />
                </div>

                <ul class="text-ink-soft mt-1 space-y-0.5 text-[13px]">
                    <li>
                        {{ $plan->batas('transactions_per_month') < 0
                            ? 'Transaksi tanpa batas'
                            : number_format($plan->batas('transactions_per_month'), 0, ',', '.').' transaksi/bulan' }}
                    </li>
                    <li>
                        {{ $plan->batas('members') < 0
                            ? 'Anggota tanpa batas'
                            : $plan->batas('members').' anggota' }}
                    </li>
                    <li>
                        {{ $plan->batas('attachments_mb') > 0
                            ? $plan->batas('attachments_mb').' MB lampiran'
                            : 'Tanpa lampiran' }}
                    </li>
                </ul>
            </div>
        @endforeach

        <p class="text-ink-soft mt-3 text-[13px] leading-[18px]">
            Pembayaran belum dibuka. Selama beta, semua plan berharga nol dan tidak ada yang ditagih.
        </p>
    </section>

    @if ($pembayaran->isNotEmpty())
        <section class="px-5 py-5">
            <h2 class="label mb-3">Riwayat pembayaran</h2>

            @foreach ($pembayaran as $bayar)
                <div class="rule-b flex items-baseline justify-between gap-3 py-2 last:border-0">
                    <span class="text-[13px]">
                        {{ $bayar->paid_at?->translatedFormat('j M Y') ?? 'Belum dibayar' }}
                    </span>
                    <x-nominal :uang="$bayar->amount_minor" class="text-[13px]" />
                </div>
            @endforeach
        </section>
    @endif
</div>
