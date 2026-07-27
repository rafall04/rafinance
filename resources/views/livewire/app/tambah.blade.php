<div class="flex min-h-[calc(100dvh-6rem)] flex-col">
    <header class="rule-b flex items-center justify-between px-5 py-3">
        <a href="{{ route('app.beranda') }}" wire:navigate class="tombol-halus px-3 text-[13px]">Batal</a>
        <h1 class="judul text-[17px]">Catat transaksi</h1>
        <span class="w-[62px]"></span>
    </header>

    {{-- Nominal lebih dulu. Orang membuka layar ini karena baru mengeluarkan
         uang, dan angkanya satu-satunya hal yang pasti ia ingat saat itu.
         Di layar mendatar panel ini menempel di atas — lihat app.css. --}}
    <section class="panel-nominal px-5 text-center">
        <p class="label mb-2">Nominal</p>
        <p class="nominal nominal-lg block text-center" aria-live="polite" aria-atomic="true">
            <span class="angka">{{ $nominal?->format() ?? 'Rp 0' }}</span>
        </p>
        <x-galat untuk="angka" />
    </section>

    <div class="flex-1 space-y-5 px-5 pb-6">
        <fieldset>
            <legend class="sr-only">Arah transaksi</legend>
            <div class="border-rule grid grid-cols-3 gap-1 rounded-[10px] border p-1">
                @foreach ($arahPilihan as $pilihan)
                    <button
                        type="button"
                        wire:click="pilihArah('{{ $pilihan->value }}')"
                        aria-pressed="{{ $arah === $pilihan->value ? 'true' : 'false' }}"
                        class="tap rounded-[7px] text-[14px] font-medium
                               {{ $arah === $pilihan->value ? 'bg-biru text-paper' : 'text-ink-soft' }}"
                    >
                        {{ $pilihan->label() }}
                    </button>
                @endforeach
            </div>
        </fieldset>

        <div>
            <label for="akunId" class="label mb-1.5 block">
                {{ $arah === 'transfer' ? 'Dari akun' : 'Akun' }}
            </label>
            <select id="akunId" wire:model.live="akunId" class="isian" required>
                @foreach ($akunPilihan as $akun)
                    <option value="{{ $akun->id }}">{{ $akun->name }} · {{ $akun->balance()->format() }}</option>
                @endforeach
            </select>
            <x-galat untuk="akunId" />
        </div>

        @if ($arah === 'transfer')
            <div>
                <label for="akunTujuanId" class="label mb-1.5 block">Ke akun</label>
                <select id="akunTujuanId" wire:model.live="akunTujuanId" class="isian" required>
                    <option value="">Pilih akun tujuan</option>
                    @foreach ($akunPilihan as $akun)
                        <option value="{{ $akun->id }}">{{ $akun->name }}</option>
                    @endforeach
                </select>
                <x-galat untuk="akunTujuanId" />
            </div>
        @else
            <div>
                <label for="kategoriId" class="label mb-1.5 block">Kategori</label>
                <select id="kategoriId" wire:model="kategoriId" class="isian">
                    {{-- Boleh kosong: capture dulu, klasifikasi belakangan. --}}
                    <option value="">Belum dikategorikan</option>
                    @foreach ($kategoriPilihan as $kategori)
                        <option value="{{ $kategori->id }}">{{ $kategori->fullName() }}</option>
                    @endforeach
                </select>
                <x-galat untuk="kategoriId" />
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="tanggal" class="label mb-1.5 block">Tanggal</label>
                <input id="tanggal" type="date" wire:model="tanggal" class="isian" required>
                <x-galat untuk="tanggal" />
            </div>
            <div>
                <label for="keterangan" class="label mb-1.5 block">Keterangan</label>
                <input id="keterangan" type="text" wire:model="keterangan" maxlength="255"
                       placeholder="Bensin" class="isian">
                <x-galat untuk="keterangan" />
            </div>
        </div>
    </div>

    {{-- Papan angka besar, menempel di bawah supaya tetap di jangkauan ibu jari
         satu tangan. Tombol sistem terlalu kecil dan memaksa pindah genggaman. --}}
    <div class="bg-paper rule-t sticky bottom-0 px-3 pt-3 pb-3">
        <div class="papan-angka" role="group" aria-label="Papan angka">
            @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', '000', '0'] as $tombol)
                <button
                    type="button"
                    wire:click="tekan('{{ $tombol }}')"
                    class="kartu tap text-[20px] font-medium"
                >{{ $tombol }}</button>
            @endforeach

            <button
                type="button"
                wire:click="hapusDigit"
                wire:dblclick="kosongkan"
                aria-label="Hapus satu angka"
                class="kartu tap text-[18px]"
            >⌫</button>
        </div>

        {{--
            Tombol simpan SELALU bekerja, online maupun tidak.

            Saat ada jaringan, jalurnya lewat Livewire seperti biasa. Saat tidak,
            transaksi dibuat di sisi client dengan ULID buatan ponsel dan
            didorong ke antrean IndexedDB — ULID itu pula yang jadi
            Idempotency-Key, sehingga pengiriman ulang tidak pernah jadi
            pengeluaran kedua (aturan A7 dan A9).

            Sinyal paling sering hilang justru di tempat orang mengeluarkan
            uang: pasar, parkiran basement, jalan antar kota.
        --}}
        <button
            type="button"
            x-data
            x-on:click="
                if (navigator.onLine) { $wire.simpan(); return; }

                const angka = $wire.get('angka');
                if (! angka) { return; }

                await window.rafin.simpanTransaksi({
                    kind: $wire.get('arah'),
                    amount_minor: Number(angka) * 100,
                    account_id: $wire.get('akunId'),
                    to_account_id: $wire.get('akunTujuanId') || null,
                    category_id: $wire.get('kategoriId') || null,
                    description: $wire.get('keterangan') || null,
                    booked_date: $wire.get('tanggal'),
                });

                window.location.href = '{{ route('app.beranda') }}';
            "
            wire:loading.attr="disabled"
            class="tombol-utama mt-3 w-full"
        >
            <span wire:loading.remove wire:target="simpan">Simpan</span>
            <span wire:loading wire:target="simpan">Menyimpan…</span>
        </button>

        <p class="catatan-offline text-ink-soft mt-2 text-center text-[12px] leading-[16px]">
            Bisa disimpan tanpa sinyal. Akan terkirim sendiri saat online.
        </p>
    </div>
</div>
