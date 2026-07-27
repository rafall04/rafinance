{{--
    Halaman diagnostik. Hanya membaca — lihat catatan panjang di
    App\Filament\Admin\Pages\Diagnostik soal kenapa tidak ada formulir di sini.

    Setiap keadaan ditandai bentuk DAN warna. Halaman ini dibuka justru saat ada
    yang tidak beres, sering dengan tergesa, dan sering di layar ponsel di bawah
    cahaya matahari — keadaan paling buruk untuk membedakan hijau dari merah.
--}}

<x-filament-panels::page>
    @php
        $tanda = [
            'siap' => ['warna' => 'success', 'label' => 'Siap', 'ikon' => 'heroicon-m-check-circle'],
            'separuh' => ['warna' => 'warning', 'label' => 'Belum lengkap', 'ikon' => 'heroicon-m-exclamation-triangle'],
            'mati' => ['warna' => 'gray', 'label' => 'Tidak aktif', 'ikon' => 'heroicon-m-minus-circle'],
        ];
    @endphp

    <div class="space-y-6">

        {{-- ── Integrasi ────────────────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">Integrasi luar</x-slot>
            <x-slot name="description">
                Nilai rahasianya tidak pernah ditampilkan di sini — hanya terisi atau
                tidak. Untuk mengisinya, jalankan
                <code class="font-mono text-xs">deploy/set-secret.sh</code> di server.
            </x-slot>

            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($integrasi as $satu)
                    @php $t = $tanda[$satu['keadaan']]; @endphp
                    <li class="flex flex-col gap-1.5 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-950 dark:text-white">{{ $satu['nama'] }}</p>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $satu['catatan'] }}</p>
                        </div>

                        <x-filament::badge :color="$t['warna']" :icon="$t['ikon']" class="shrink-0 self-start">
                            {{ $t['label'] }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

        {{-- ── Infrastruktur ────────────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">Infrastruktur</x-slot>
            <x-slot name="description">
                Diperiksa saat halaman ini dibuka, bukan diambil dari cache.
            </x-slot>

            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($infrastruktur as $baris)
                    <li class="flex items-center justify-between gap-4 py-2.5 first:pt-0 last:pb-0">
                        <span class="text-gray-950 dark:text-white">{{ $baris['nama'] }}</span>

                        <span class="flex min-w-0 items-center gap-2">
                            <span class="truncate font-mono text-sm tabular-nums text-gray-500 dark:text-gray-400">
                                {{ $baris['nilai'] }}
                            </span>
                            <x-filament::badge
                                :color="$baris['sehat'] ? 'success' : 'danger'"
                                :icon="$baris['sehat'] ? 'heroicon-m-check' : 'heroicon-m-x-mark'"
                                class="shrink-0"
                            >
                                {{ $baris['sehat'] ? 'Baik' : 'Perlu dilihat' }}
                            </x-filament::badge>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

        {{-- ── Feature flag ─────────────────────────────────────────────── --}}
        <x-filament::section>
            <x-slot name="heading">Feature flag</x-slot>
            <x-slot name="description">
                Aturan A12 menuntut keduanya mati. Ditampilkan di sini supaya tidak
                ada yang menyala tanpa ada yang menyadarinya.
            </x-slot>

            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($bendera as $satu)
                    <li class="flex items-start justify-between gap-4 py-2.5 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-950 dark:text-white">{{ $satu['nama'] }}</p>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $satu['catatan'] }}</p>
                        </div>

                        <x-filament::badge
                            :color="$satu['aktif'] ? 'danger' : 'gray'"
                            :icon="$satu['aktif'] ? 'heroicon-m-bolt' : 'heroicon-m-minus'"
                            class="shrink-0"
                        >
                            {{ $satu['aktif'] ? 'MENYALA' : 'Mati' }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            Halaman ini tidak bisa mengubah apa pun. Itu disengaja: memindahkan
            rahasia ke formulir web berarti membuatnya bisa diambil siapa pun yang
            menguasai satu sesi admin, dari mana pun.
        </p>

    </div>
</x-filament-panels::page>
