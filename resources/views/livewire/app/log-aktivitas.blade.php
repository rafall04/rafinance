<div>
    <header class="layar-kepala">
        <div class="min-w-0">
        <h1 class="judul">Log aktivitas</h1>
        <p class="text-ink-soft text-[13px]">Setiap perubahan di buku ini, beserta pelakunya.</p>
        </div>
    </header>

    {{-- Verifikasi rantai. Ada di halaman pengguna, bukan hanya di perintah
         artisan: janji "riwayat tidak bisa disunting" hanya berarti kalau
         pemiliknya sendiri bisa memeriksanya. --}}
    <section class="rule-b px-5 py-4">
        <button type="button" wire:click="periksaRantai" class="tombol-halus w-full"
                wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="periksaRantai">Periksa keutuhan riwayat</span>
            <span wire:loading wire:target="periksaRantai">Memeriksa…</span>
        </button>

        @if ($hasilVerifikasi !== null)
            @if ($hasilVerifikasi['ok'])
                <p class="kartu text-hijau mt-3 px-4 py-3" role="status">
                    <strong>Riwayat utuh.</strong>
                    {{ $hasilVerifikasi['total'] }} baris tersambung tanpa putus.
                </p>
            @else
                <div class="mt-3" role="alert">
                    <p class="pesan-galat">
                        <strong>Rantai putus di {{ count($hasilVerifikasi['broken']) }} baris.</strong>
                        Artinya audit_logs pernah disunting dari luar aplikasi. Periksa siapa yang
                        punya akses langsung ke database.
                    </p>
                    <ul class="text-ink-soft mt-2 space-y-1 text-[13px]">
                        @foreach (array_slice($hasilVerifikasi['broken'], 0, 5) as $putus)
                            <li><code>{{ $putus['id'] }}</code> — {{ $putus['alasan'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </section>

    @if ($aksiTersedia->isNotEmpty())
        <section class="rule-b py-3">
            <div class="flex snap-x gap-2 overflow-x-auto px-5" role="group" aria-label="Saring tindakan">
                @foreach ($aksiTersedia as $aksi)
                    <button type="button" wire:click="saring('{{ $aksi->value }}')"
                            aria-pressed="{{ $saringAksi === $aksi->value ? 'true' : 'false' }}"
                            class="tap shrink-0 snap-start rounded-full border px-4 text-[13px]
                                   {{ $saringAksi === $aksi->value ? 'border-transparent bg-biru text-paper' : 'border-rule text-ink-soft' }}">
                        {{ $aksi->label() }}
                    </button>
                @endforeach
            </div>
        </section>
    @endif

    @forelse ($baris as $catatan)
        <article class="rule-b px-5 py-3">
            <div class="flex items-baseline justify-between gap-3">
                <span class="truncate font-medium">{{ $catatan->action->label() }}</span>
                <span class="text-ink-soft shrink-0 text-[13px]">
                    {{ $catatan->created_at->translatedFormat('j M, H:i') }}
                </span>
            </div>

            <p class="text-ink-soft text-[13px] leading-[18px]">
                {{ $catatan->actor?->name ?? 'Sistem' }}
                @if ($catatan->ip)
                    · {{ $catatan->ip }}
                @endif
            </p>

            @if (! empty($catatan->after))
                <details class="mt-1">
                    <summary class="text-ink-soft tap inline-flex cursor-pointer items-center text-[13px]">
                        Lihat perubahan
                    </summary>
                    <pre class="kartu mt-2 overflow-x-auto px-3 py-2 text-[12px]">{{ json_encode($catatan->after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            @endif
        </article>
    @empty
        <x-kosong judul="Belum ada aktivitas." ikon="lembar">
            Setiap transaksi, akun, dan penguncian periode akan muncul di sini.
        </x-kosong>
    @endforelse

    <div class="px-5 py-4">
        {{ $baris->links() }}
    </div>
</div>
