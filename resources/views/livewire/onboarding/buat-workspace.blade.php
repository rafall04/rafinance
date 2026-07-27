<div>
    <h1 class="judul mb-1">Buat buku pertama</h1>
    <p class="text-ink-soft mb-6">Bisa diubah kapan saja, dan Anda boleh punya lebih dari satu.</p>

    <form wire:submit="simpan" class="space-y-5">
        <div>
            <label for="nama" class="label mb-1.5 block">Nama buku</label>
            <input
                id="nama" type="text" wire:model="nama" required autofocus maxlength="80"
                placeholder="Warung Bu Sri" class="isian"
                @error('nama') aria-invalid="true" aria-describedby="galat-nama" @enderror
            >
            <x-galat untuk="nama" />
        </div>

        <fieldset>
            <legend class="label mb-2">Jenis buku</legend>
            <div class="space-y-2">
                @foreach ($jenisBuku as $jenis)
                    <label class="kartu tap flex cursor-pointer items-start gap-3 px-4 py-3">
                        <input
                            type="radio" wire:model="tipe" value="{{ $jenis->value }}" name="tipe"
                            class="mt-1 h-4 w-4 shrink-0 accent-[var(--biru-50)]"
                        >
                        <span>
                            <span class="block font-medium">{{ $jenis->label() }}</span>
                            <span class="text-ink-soft block text-[13px] leading-[18px]">{{ $jenis->description() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <x-galat untuk="tipe" />
        </fieldset>

        <fieldset>
            <legend class="label mb-2">Di mana uang Anda</legend>
            <div class="flex flex-col gap-2">
                @foreach ($jenisAkun as $pilihan)
                    <label class="kartu tap flex cursor-pointer items-center gap-3 px-4 py-2">
                        <input
                            type="checkbox" wire:model="akunAwal" value="{{ $pilihan->value }}"
                            class="h-4 w-4 shrink-0 accent-[var(--biru-50)]"
                        >
                        <span>{{ $pilihan->label() }}</span>
                    </label>
                @endforeach
            </div>
            {{-- Saldo awalnya diisi belakangan di halaman Akun: yang penting
                 sekarang adalah bisa langsung mencatat, bukan langsung akurat. --}}
            <p class="text-ink-soft mt-1.5 text-[13px] leading-[18px]">
                Saldo awalnya bisa diisi nanti di halaman Akun.
            </p>
            <x-galat untuk="akunAwal" />
        </fieldset>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="awalPeriode" class="label mb-1.5 block">Awal periode</label>
                <input
                    id="awalPeriode" type="number" inputmode="numeric" wire:model="awalPeriode"
                    min="1" max="28" required class="isian"
                    @error('awalPeriode') aria-invalid="true" aria-describedby="galat-awalPeriode" @enderror
                >
                {{-- Banyak usaha kecil menutup buku ikut tanggal gajian atau
                     tanggal tagihan, bukan tanggal 1. --}}
                <p class="text-ink-soft mt-1.5 text-[13px] leading-[18px]">Tanggal buku bulanan dimulai.</p>
                <x-galat untuk="awalPeriode" />
            </div>

            <div>
                <label for="timezone" class="label mb-1.5 block">Zona waktu</label>
                <select id="timezone" wire:model="timezone" class="isian" required>
                    <option value="Asia/Jakarta">WIB · Jakarta</option>
                    <option value="Asia/Makassar">WITA · Makassar</option>
                    <option value="Asia/Jayapura">WIT · Jayapura</option>
                </select>
                <x-galat untuk="timezone" />
            </div>
        </div>

        <button type="submit" class="tombol-utama w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="simpan">Buat buku</span>
            <span wire:loading wire:target="simpan">Menyimpan…</span>
        </button>
    </form>
</div>
