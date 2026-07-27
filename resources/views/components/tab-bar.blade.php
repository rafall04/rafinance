@php
    /*
     * Lima slot, tidak lebih. Yang keenam selalu berarti ada yang tidak
     * dipikirkan matang: bilah navigasi utama adalah tempat memutuskan apa yang
     * penting, bukan tempat menampung semuanya.
     *
     * Bagian yang belum dibangun ditandai `siap => false` dan dirender teredam
     * dengan aria-disabled, bukan sebagai tautan yang mengecewakan. FASE
     * berikutnya cukup menyalakan tandanya.
     */
    $tab = [
        ['label' => 'Beranda', 'rute' => 'app.beranda', 'siap' => Route::has('app.beranda')],
        ['label' => 'Inbox', 'rute' => 'app.inbox', 'siap' => Route::has('app.inbox')],
        ['label' => 'Tambah', 'rute' => 'app.tambah', 'siap' => Route::has('app.tambah'), 'utama' => true],
        ['label' => 'Laporan', 'rute' => 'app.laporan', 'siap' => Route::has('app.laporan')],
        ['label' => 'Lainnya', 'rute' => 'app.lainnya', 'siap' => Route::has('app.lainnya')],
    ];
@endphp

@auth
<nav
    aria-label="Navigasi utama"
    class="bg-paper rule-t fixed inset-x-0 bottom-0 z-40"
    style="padding-bottom: env(safe-area-inset-bottom);"
>
    <ul class="shell grid grid-cols-5 items-center">
        @foreach ($tab as $item)
            @php
                $aktif = $item['siap'] && request()->routeIs($item['rute']);
                $utama = $item['utama'] ?? false;
            @endphp

            <li class="flex justify-center">
                @if ($item['siap'])
                    <a
                        href="{{ route($item['rute']) }}"
                        @if ($aktif) aria-current="page" @endif
                        class="tap flex flex-col items-center justify-center gap-1 px-2 py-2 text-center
                               {{ $aktif ? 'text-biru' : 'text-ink-soft' }}"
                    >
                        <x-tab-icon :name="$item['label']" :utama="$utama" />
                        {{-- 12px, bukan 11px: itu batas bawah teks yang masih
                             nyaman dibaca, dan label navigasi dibaca sekilas
                             sambil berjalan. --}}
                        <span class="text-[12px] leading-none">{{ $item['label'] }}</span>
                    </a>
                @else
                    <span
                        aria-disabled="true"
                        title="Belum tersedia"
                        class="tap text-ink-soft/40 flex flex-col items-center justify-center gap-1 px-2 py-2 text-center"
                    >
                        <x-tab-icon :name="$item['label']" :utama="$utama" />
                        {{-- 12px, bukan 11px: itu batas bawah teks yang masih
                             nyaman dibaca, dan label navigasi dibaca sekilas
                             sambil berjalan. --}}
                        <span class="text-[12px] leading-none">{{ $item['label'] }}</span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
@endauth
