<div>
    <header class="rule-b px-5 py-4">
        <h1 class="judul">Inbox</h1>
        <p class="text-ink-soft text-[13px]">Yang masuk tapi belum lengkap.</p>
    </header>

    @if (session('kabar'))
        <p class="kartu text-hijau mx-5 mt-4 px-4 py-3" role="status">{{ session('kabar') }}</p>
    @endif

    @forelse ($daftar as $item)
        <article class="rule-b px-5 py-3">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate">{{ $item->raw_text ?: '(foto atau pesan suara)' }}</p>
                    <p class="text-ink-soft text-[13px] leading-[18px]">
                        {{ $item->source->label() }} · {{ $item->created_at->diffForHumans() }}
                        @if (($item->parsed_draft['catatan'] ?? []) !== [])
                            · {{ implode(' ', $item->parsed_draft['catatan']) }}
                        @endif
                    </p>
                </div>

                <div class="flex shrink-0 gap-2">
                    @if ($sedangDiisi !== $item->id)
                        <button type="button" wire:click="buka('{{ $item->id }}')"
                                class="tombol-utama px-3 text-[13px]">Lengkapi</button>
                    @endif
                    <button type="button" wire:click="abaikan('{{ $item->id }}')"
                            wire:confirm="Abaikan catatan ini?"
                            class="tap text-ink-soft px-2 text-[13px]">Abaikan</button>
                </div>
            </div>

            @if ($sedangDiisi === $item->id)
                <form wire:submit="selesaikan" class="mt-4 space-y-3">
                    <div class="border-rule grid grid-cols-2 gap-1 rounded-[10px] border p-1">
                        @foreach (['expense' => 'Keluar', 'income' => 'Masuk'] as $nilai => $label)
                            <button type="button" wire:click="$set('arah', '{{ $nilai }}')"
                                    aria-pressed="{{ $arah === $nilai ? 'true' : 'false' }}"
                                    class="tap rounded-[7px] text-[14px] font-medium
                                           {{ $arah === $nilai ? 'bg-biru text-paper' : 'text-ink-soft' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="nominal-{{ $item->id }}" class="label mb-1.5 block">Nominal</label>
                            <input id="nominal-{{ $item->id }}" type="text" inputmode="numeric"
                                   wire:model="nominal" required class="isian" placeholder="50000">
                            <x-galat untuk="nominal" />
                        </div>
                        <div>
                            <label for="akun-{{ $item->id }}" class="label mb-1.5 block">Akun</label>
                            <select id="akun-{{ $item->id }}" wire:model="akunId" class="isian" required>
                                @foreach ($akunPilihan as $akun)
                                    <option value="{{ $akun->id }}">{{ $akun->name }}</option>
                                @endforeach
                            </select>
                            <x-galat untuk="akunId" />
                        </div>
                    </div>

                    <div>
                        <label for="kategori-{{ $item->id }}" class="label mb-1.5 block">Kategori</label>
                        <select id="kategori-{{ $item->id }}" wire:model="kategoriId" class="isian">
                            <option value="">Belum dikategorikan</option>
                            @foreach ($kategoriPilihan as $kategori)
                                <option value="{{ $kategori->id }}">{{ $kategori->fullName() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="ket-{{ $item->id }}" class="label mb-1.5 block">Keterangan</label>
                        <input id="ket-{{ $item->id }}" type="text" wire:model="keterangan"
                               maxlength="255" class="isian">
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="tombol-utama flex-1">Simpan</button>
                        <button type="button" wire:click="tutupFormulir" class="tombol-halus">Tutup</button>
                    </div>
                </form>
            @endif
        </article>
    @empty
        <section class="px-5 py-12 text-center">
            <p class="mb-1 font-medium">Inbox kosong.</p>
            <p class="text-ink-soft mx-auto max-w-[34ch]">
                Semua yang masuk sudah lengkap. Kirim apa saja ke bot dan yang belum jelas akan mendarat di sini.
            </p>
        </section>
    @endforelse
</div>
