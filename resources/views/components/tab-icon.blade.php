@props(['name' => '', 'utama' => false])

{{--
    Ikon garis, digambar langsung sebagai SVG.

    Tidak memakai pustaka ikon: satu paket ikon berarti puluhan kilobyte untuk
    lima gambar, dan anggaran 200 KB JavaScript itu harus dibelanjakan untuk
    antrean offline, bukan untuk gambar.
--}}
@php
    $garis = match ($name) {
        // Buku terbuka — beranda adalah buku besarnya.
        'Beranda' => '<path d="M3 5.5c2.5-1 5-1 7 0v13c-2-1-4.5-1-7 0v-13Z"/><path d="M21 5.5c-2.5-1-5-1-7 0v13c2.5-1 4.5-1 7 0v-13Z"/>',
        // Kotak masuk.
        'Inbox' => '<path d="M3 13h5l1.5 3h5l1.5-3h5"/><path d="M4.5 5.5h15l1.5 7.5v5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18v-5l1.5-7.5Z"/>',
        'Tambah' => '<path d="M12 6v12"/><path d="M6 12h12"/>',
        // Batang laporan.
        'Laporan' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
        'Lainnya' => '<circle cx="5" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="19" cy="12" r="1.4"/>',
        default => '',
    };
@endphp

@if ($utama)
    {{-- Tombol tambah dinaikkan dan diberi warna: ia yang ditekan paling
         sering, dan harus tetap terjangkau ibu jari tanpa memindah genggaman. --}}
    <span class="bg-biru text-paper -mt-5 flex h-11 w-11 items-center justify-center rounded-full shadow-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
            {!! $garis !!}
        </svg>
    </span>
@else
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
         stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true">
        {!! $garis !!}
    </svg>
@endif
