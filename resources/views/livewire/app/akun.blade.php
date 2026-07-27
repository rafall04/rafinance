<div>
    <header class="layar-kepala">
        <div>
            <h1 class="judul">Akun</h1>
            <p class="text-ink-soft text-[13px]">Tempat uang Anda berada.</p>
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
                <label for="nama" class="label mb-1.5 block">Nama akun</label>
                <input id="nama" type="text" wire:model="nama" maxlength="60" required autofocus
                       placeholder="BCA" class="isian"
                       @error('nama') aria-invalid="true" @enderror>
                <x-galat untuk="nama" />
            </div>

            <div>
                <label for="subtype" class="label mb-1.5 block">Jenis</label>
                <select id="subtype" wire:model="subtype" class="isian" required>
                    @foreach ($jenis as $pilihan)
                        <option value="{{ $pilihan->value }}">{{ $pilihan->label() }}</option>
                    @endforeach
                </select>
                <x-galat untuk="subtype" />
            </div>

            <div>
                <label for="saldoAwal" class="label mb-1.5 block">Saldo awal</label>
                <input id="saldoAwal" type="text" inputmode="numeric" wire:model="saldoAwal"
                       placeholder="0" class="isian">
                <p class="text-ink-soft mt-1.5 text-[13px] leading-[18px]">
                    Isi saldo saat ini, bukan nol, supaya buku langsung cocok dengan kenyataan.
                </p>
                <x-galat untuk="saldoAwal" />
            </div>

            <div class="flex gap-2">
                <button type="submit" class="tombol-utama flex-1">Simpan</button>
                <button type="button" wire:click="batal" class="tombol-halus">Batal</button>
            </div>
        </form>
    @endif

    @forelse ($daftar as $akun)
        <div class="rule-b flex items-center justify-between gap-3 px-5 py-3">
            <div class="flex min-w-0 items-center gap-3">
                <span class="h-8 w-1 shrink-0 rounded-full" style="background: {{ $akun->color() }}"></span>
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ $akun->name }}</p>
                    <p class="text-ink-soft text-[13px] leading-[18px]">{{ $akun->subtype->label() }}</p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <x-nominal :uang="$akun->balance()" />
                <button
                    type="button"
                    wire:click="bukaCashOpname('{{ $akun->id }}')"
                    class="tap text-ink-soft px-2 text-[13px]"
                >Hitung</button>
                <button
                    type="button"
                    wire:click="tutupAkun('{{ $akun->id }}')"
                    wire:confirm="Tutup akun {{ $akun->name }}? Riwayatnya tetap tersimpan."
                    class="tap text-ink-soft px-2 text-[13px]"
                >Tutup</button>
            </div>
        </div>

        @if ($menghitungId === $akun->id)
            <form wire:submit="simpanCashOpname" class="rule-b bg-paper-sunk space-y-3 px-5 py-4">
                <div>
                    <label for="hitung-{{ $akun->id }}" class="label mb-1.5 block">
                        Jumlah uang sungguhan di {{ $akun->name }}
                    </label>
                    <input id="hitung-{{ $akun->id }}" type="text" inputmode="numeric"
                           wire:model="jumlahTerhitung" required autofocus
                           placeholder="{{ intdiv($akun->balance()->minor, 100) }}" class="isian">
                    {{-- Selisih tidak dihapus diam-diam: ia jadi penyesuaian
                         yang muncul di laporan, karena angka itulah yang
                         memberi tahu ada yang perlu diperiksa. --}}
                    <p class="text-ink-soft mt-1.5 text-[13px] leading-[18px]">
                        Buku mencatat {{ $akun->balance()->format() }}. Selisihnya akan dicatat sebagai penyesuaian.
                    </p>
                    <x-galat untuk="jumlahTerhitung" />
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="tombol-utama flex-1">Simpan hasil hitungan</button>
                    <button type="button" wire:click="$set('menghitungId', '')" class="tombol-halus">Batal</button>
                </div>
            </form>
        @endif
    @empty
        <x-kosong judul="Belum ada akun." ikon="dompet">
            Mulai dari yang paling sering dipakai — biasanya Kas atau satu rekening bank.
        </x-kosong>
    @endforelse

    @if ($ditutup->isNotEmpty())
        <section class="px-5 py-5">
            <h2 class="label mb-2">Akun yang ditutup</h2>
            <ul class="text-ink-soft space-y-1">
                @foreach ($ditutup as $akun)
                    <li class="flex items-center justify-between">
                        <span>{{ $akun->name }}</span>
                        <x-nominal :uang="$akun->balance()" class="text-[13px]" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
